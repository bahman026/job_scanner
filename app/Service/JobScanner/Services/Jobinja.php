<?php

namespace App\Service\JobScanner\Services;

use App\Service\JobScanner\Contracts\JobScanner;
use PHPHtmlParser\Dom;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;

class Jobinja implements JobScanner
{
    private $client;
    private $headers;

    public function __construct()
    {
        $this->headers = [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.5',
            'Accept-Encoding' => 'gzip, deflate, br',
            'DNT' => '1',
            'Connection' => 'keep-alive',
            'Upgrade-Insecure-Requests' => '1',
        ];
        
        $this->client = new Client([
            'headers' => $this->headers,
            'timeout' => 15,
            'connect_timeout' => 5,
            'http_errors' => false,
            'verify' => false, // Disable SSL verification for faster requests
            'allow_redirects' => true,
            'max_redirects' => 3
        ]);
    }

    public function getLocation($link)
    {
        try {
            $response = $this->client->get($link);
            $body = $response->getBody()->getContents();
            
            $dom = new Dom;
            $dom->loadStr($body);
            $links = $dom->find('.c-companyMap__desc');
            return $links->text ?? '';
        } catch (\Exception $e) {
            \Log::warning("Failed to get location for {$link}: " . $e->getMessage());
            return '';
        }
    }

    public function getOffers($companyLink, $keywords = []): array
    {
        try {
            $response = $this->client->get($companyLink . "/jobs");
            $body = $response->getBody()->getContents();
            libxml_use_internal_errors(true);
            $doc = new \DOMDocument();
            libxml_use_internal_errors(true);
            $doc->loadHTML($body);
            $links = $doc->getElementsByTagName('a');
            $extractedLinks = array();

            foreach ($links as $link) {
                $linkHref = $link->getAttribute('href');

                if (str_contains(trim(strtolower($link->parentNode->nodeValue)), "(منقضی شده)")) {
                    continue;
                }

                if (strlen(trim($linkHref)) == 0) {
                    continue;
                }
                if ($linkHref[0] == '#' || !str_contains(strtolower($linkHref), strtolower($companyLink))) {
                    continue;
                }
                if (in_array($linkHref, $extractedLinks)) {
                    continue;
                }
                if ($link->parentNode->tagName != "div") {
                    continue;
                }

                $searchFlag = false;
                foreach ($keywords as $keyword) {
                    if (str_contains(strtolower($link->parentNode->nodeValue), strtolower($keyword))) {
                        $searchFlag = true;
                    }
                }
                if (!$searchFlag && count($keywords)) {
                    continue;
                }
                $extractedLinks[] = $linkHref;
            }
            return array_values($extractedLinks);
        } catch (\Exception $e) {
            \Log::warning("Failed to get offers for {$companyLink}: " . $e->getMessage());
            return [];
        }
    }

    public function getCompanies(): array
    {
        try {
            $top50Url = "https://jobinja.ir/top50";
            $response = $this->client->get($top50Url);
            $body = $response->getBody()->getContents();
        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML($body);
        $links = $doc->getElementsByTagName('a');
        $extractedLinks = array();

        foreach ($links as $link) {
            $linkHref = $link->getAttribute('href');
            if (strlen(trim($linkHref)) == 0) {
                continue;
            }
            if ($linkHref[0] == '#') {
                continue;
            }
            $extractedLinks[] = $linkHref;
        }

            $extractedLinks = array_filter($extractedLinks, function ($item) {
                return str_contains(strtolower($item), "https://jobinja.ir/companies/") && !str_contains(strtolower($item), "/jobs");
            });
            return array_values($extractedLinks);
        } catch (\Exception $e) {
            \Log::error("Failed to get companies: " . $e->getMessage());
            return [];
        }
    }


    public function getJobs($keywords, $process = false)
    {
        $companyLinks = $this->getCompanies();
        
        if (empty($companyLinks)) {
            return [];
        }

        // Create requests for parallel processing
        $requests = [];
        $companyData = [];
        
        foreach ($companyLinks as $key => $link) {
            $companyData[$key] = ['link' => $link];
            
            // Create requests for both jobs and location
            $requests["jobs_{$key}"] = new Request('GET', $link . "/jobs");
            $requests["location_{$key}"] = new Request('GET', $link);
        }

        // Execute requests in parallel with concurrency limit
        $responses = [];
        $pool = new Pool($this->client, $requests, [
            'concurrency' => 8, // Process up to 8 requests simultaneously (optimized for jobinja.ir)
            'fulfilled' => function ($response, $index) use (&$responses) {
                $responses[$index] = $response;
            },
            'rejected' => function ($reason, $index) use (&$responses) {
                \Log::warning("Request failed for {$index}: " . $reason);
                $responses[$index] = null;
            }
        ]);

        $promise = $pool->promise();
        $promise->wait();

        // Process responses
        $results = [];
        foreach ($companyLinks as $key => $link) {
            if ($process) {
                $process->progressAdvance();
            }

            $jobsResponse = $responses["jobs_{$key}"] ?? null;
            $locationResponse = $responses["location_{$key}"] ?? null;

            // Process jobs
            $jobs = [];
            if ($jobsResponse && $jobsResponse->getStatusCode() === 200) {
                $jobs = $this->parseJobsFromResponse($jobsResponse->getBody()->getContents(), $link, $keywords);
            }

            if (empty($jobs)) {
                continue;
            }

            // Process location
            $state = '';
            if ($locationResponse && $locationResponse->getStatusCode() === 200) {
                $state = $this->parseLocationFromResponse($locationResponse->getBody()->getContents());
            }

            $results[] = compact('link', 'state', 'jobs');
        }

        return $results;
    }

    private function parseJobsFromResponse($body, $companyLink, $keywords)
    {
        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $doc->loadHTML($body);
        $links = $doc->getElementsByTagName('a');
        $extractedLinks = array();

        foreach ($links as $link) {
            $linkHref = $link->getAttribute('href');

            if (str_contains(trim(strtolower($link->parentNode->nodeValue)), "(منقضی شده)")) {
                continue;
            }

            if (strlen(trim($linkHref)) == 0) {
                continue;
            }
            if ($linkHref[0] == '#' || !str_contains(strtolower($linkHref), strtolower($companyLink))) {
                continue;
            }
            if (in_array($linkHref, $extractedLinks)) {
                continue;
            }
            if ($link->parentNode->tagName != "div") {
                continue;
            }

            $searchFlag = false;
            foreach ($keywords as $keyword) {
                if (str_contains(strtolower($link->parentNode->nodeValue), strtolower($keyword))) {
                    $searchFlag = true;
                }
            }
            if (!$searchFlag && count($keywords)) {
                continue;
            }
            $extractedLinks[] = $linkHref;
        }
        return array_values($extractedLinks);
    }

    private function parseLocationFromResponse($body)
    {
        $dom = new Dom;
        $dom->loadStr($body);
        $links = $dom->find('.c-companyMap__desc');
        return $links->text ?? '';
    }
}
