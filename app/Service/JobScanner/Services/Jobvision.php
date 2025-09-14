<?php

namespace App\Service\JobScanner\Services;

use App\Service\JobScanner\Contracts\JobScanner;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;

class Jobvision implements JobScanner
{
    private $client;
    private $headers;

    public function __construct()
    {
        $this->headers = [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept' => 'application/json, text/plain, */*',
            'Accept-Language' => 'en-US,en;q=0.9,fa;q=0.8',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Referer' => 'https://jobvision.ir/',
            'Origin' => 'https://jobvision.ir',
            'Sec-Fetch-Dest' => 'empty',
            'Sec-Fetch-Mode' => 'cors',
            'Sec-Fetch-Site' => 'same-site',
            'Cache-Control' => 'no-cache',
            'Pragma' => 'no-cache'
        ];
        
        $this->client = new Client([
            'headers' => $this->headers,
            'timeout' => 15,
            'connect_timeout' => 5,
            'http_errors' => false,
            'verify' => false,
            'allow_redirects' => true,
            'max_redirects' => 3
        ]);
    }

    public function getLocation($company): string
    {
        try {
            $url = "https://candidateapi.jobvision.ir/api/v1/Company/Details?companyId=" . $company['companyId'];
            $response = $this->client->get($url);
            
            if ($response->getStatusCode() !== 200) {
                \Log::warning("Jobvision API request failed for company details with status: " . $response->getStatusCode());
                return '';
            }
            
            $responseData = json_decode($response->getBody()->getContents(), true);
            
            if (!isset($responseData['data'])) {
                \Log::warning('Unexpected Jobvision API response structure for company details:', $responseData);
                return '';
            }
            
            $data = $responseData['data'];
            return ($data['cityFa'] ?? '') . " _ " . ($data['provinceFa'] ?? '');
        } catch (\Exception $e) {
            \Log::warning("Failed to get location for company {$company['companyId']}: " . $e->getMessage());
            return '';
        }
    }

    public function getOffers($companyLink, $keywords = [])
    {
        try {
            $url = "https://candidateapi.jobvision.ir/api/v1/JobPost/GetListOfCompanyJobPosts?companyId=" . $companyLink['companyId'];
            $response = $this->client->get($url);
            
            if ($response->getStatusCode() !== 200) {
                \Log::warning("Jobvision API request failed for job offers with status: " . $response->getStatusCode());
                return [];
            }
            
            $responseData = json_decode($response->getBody()->getContents(), true);
            
            if (!isset($responseData['data'])) {
                \Log::warning('Unexpected Jobvision API response structure for job offers:', $responseData);
                return [];
            }
            
            $response = $responseData['data'];

        foreach ($response as $key => $job) {
            if ($job['expireTime']['isExpired']) {
                unset($response[$key]);
                continue;
            }

            $searchFlag = false;
            foreach ($keywords as $keyword) {
                if (str_contains(strtolower($job['title']), strtolower($keyword))) {
                    $searchFlag = true;
                }
            }
            if (!$searchFlag && count($keywords)) {
                unset($response[$key]);
                continue;
            }
            $response[$key]['link'] = "https://jobvision.ir/jobs/" . $job['id'];

            $response[$key] = array_filter(
                $response[$key],
                function ($key) {
                    // N.b. in_array() is notorious for being slow
                    if (in_array($key, ['link', 'id', 'title', 'company', 'city'])) {
                        return $key;
                    }
                },
                ARRAY_FILTER_USE_KEY
            );
        }
        return array_values($response);
        } catch (\Exception $e) {
            \Log::warning("Failed to get offers for company {$companyLink['companyId']}: " . $e->getMessage());
            return [];
        }
    }

    public function getCompanies()
    {
        $url = "https://candidateapi.jobvision.ir/api/v1/Company/GetListOfFilteredCompaniesSummaries";
        $data = [
            "keyword" => "",
            "filterParameters" => [
                "onlyHasActiveJobPosts" => false,
                "listOfCityIds" => [],
                "listOfCompanyBenefits" => [],
                "listOfCompanyScores" => [],
                "listOfCompanySizes" => [],
                "listOfIndustryIds" => [],
            ],
            "pageSize" => 20,
            "pageNumber" => 1,
            "orderBy" => 0,
        ];
        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept' => 'application/json, text/plain, */*',
            'Accept-Language' => 'en-US,en;q=0.9,fa;q=0.8',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Content-Type' => 'application/json',
            'Referer' => 'https://jobvision.ir/',
            'Origin' => 'https://jobvision.ir',
            'Sec-Fetch-Dest' => 'empty',
            'Sec-Fetch-Mode' => 'cors',
            'Sec-Fetch-Site' => 'same-site',
            'Cache-Control' => 'no-cache',
            'Pragma' => 'no-cache'
        ])->post($url, $data);
        
        // Check if the response is successful and has the expected structure
        if (!$response->successful()) {
            \Log::error('Jobvision API request failed:', [
                'status' => $response->status(),
                'body' => $response->body(),
                'url' => $url,
                'data' => $data
            ]);
            throw new \Exception("Jobvision API request failed with status: " . $response->status() . " - Response: " . $response->body());
        }
        
        $responseData = $response->json();
        
        // Debug: Log the actual response structure
        \Log::info('Jobvision API Response Structure:', $responseData);
        
        // Check if the response has the expected structure
        if (!isset($responseData['data']) || !isset($responseData['data']['companies'])) {
            \Log::error('Unexpected Jobvision API response structure:', $responseData);
            throw new \Exception("Unexpected API response structure from Jobvision");
        }
        
        $biggestCompanies = $responseData['data']['companies'];
        $data['orderBy'] = 1;
        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept' => 'application/json, text/plain, */*',
            'Accept-Language' => 'en-US,en;q=0.9,fa;q=0.8',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Content-Type' => 'application/json',
            'Referer' => 'https://jobvision.ir/',
            'Origin' => 'https://jobvision.ir',
            'Sec-Fetch-Dest' => 'empty',
            'Sec-Fetch-Mode' => 'cors',
            'Sec-Fetch-Site' => 'same-site',
            'Cache-Control' => 'no-cache',
            'Pragma' => 'no-cache'
        ])->post($url, $data);
        
        if (!$response->successful()) {
            \Log::error('Jobvision API request failed (second request):', [
                'status' => $response->status(),
                'body' => $response->body(),
                'url' => $url,
                'data' => $data
            ]);
            throw new \Exception("Jobvision API request failed with status: " . $response->status() . " - Response: " . $response->body());
        }
        
        $responseData = $response->json();
        
        if (!isset($responseData['data']) || !isset($responseData['data']['companies'])) {
            \Log::error('Unexpected Jobvision API response structure for second request:', $responseData);
            throw new \Exception("Unexpected API response structure from Jobvision (second request)");
        }
        
        $largestCompanies = $responseData['data']['companies'];
        
        // Extract company IDs from biggestCompanies for comparison
        $biggestCompanyIds = array_column($biggestCompanies, 'companyId');
        
        foreach ($largestCompanies as $company) {
            if (in_array($company['companyId'], $biggestCompanyIds)) {
                continue;
            }
            $biggestCompanies[] = $company;
        }
        return $biggestCompanies;
    }


    public function getJobs($keywords, $process = false): array
    {
        $companies = $this->getCompanies();
        
        if (empty($companies)) {
            return [];
        }

        // Create requests for parallel processing
        $requests = [];
        
        foreach ($companies as $key => $company) {
            // Create requests for both jobs and location
            $requests["jobs_{$key}"] = new Request('GET', "https://candidateapi.jobvision.ir/api/v1/JobPost/GetListOfCompanyJobPosts?companyId=" . $company['companyId']);
            $requests["location_{$key}"] = new Request('GET', "https://candidateapi.jobvision.ir/api/v1/Company/Details?companyId=" . $company['companyId']);
        }

        // Execute requests in parallel with concurrency limit
        $responses = [];
        $pool = new Pool($this->client, $requests, [
            'concurrency' => 6, // Process up to 6 requests simultaneously (optimized for jobvision.ir)
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
        foreach ($companies as $key => $company) {
            if ($process) {
                $process->progressAdvance();
            }

            $jobsResponse = $responses["jobs_{$key}"] ?? null;
            $locationResponse = $responses["location_{$key}"] ?? null;

            // Process jobs
            $jobs = [];
            if ($jobsResponse && $jobsResponse->getStatusCode() === 200) {
                $jobs = $this->parseJobsFromResponse($jobsResponse->getBody()->getContents(), $company, $keywords);
            }

            if (empty($jobs)) {
                continue;
            }

            // Process location
            $state = '';
            if ($locationResponse && $locationResponse->getStatusCode() === 200) {
                $state = $this->parseLocationFromResponse($locationResponse->getBody()->getContents());
            }

            $results[] = compact('state', 'jobs', 'company');
        }

        return $results;
    }

    private function parseJobsFromResponse($body, $company, $keywords)
    {
        try {
            $responseData = json_decode($body, true);
            
            if (!isset($responseData['data'])) {
                return [];
            }
            
            $response = $responseData['data'];

            foreach ($response as $key => $job) {
                if ($job['expireTime']['isExpired']) {
                    unset($response[$key]);
                    continue;
                }

                $searchFlag = false;
                foreach ($keywords as $keyword) {
                    if (str_contains(strtolower($job['title']), strtolower($keyword))) {
                        $searchFlag = true;
                    }
                }
                if (!$searchFlag && count($keywords)) {
                    unset($response[$key]);
                    continue;
                }
                $response[$key]['link'] = "https://jobvision.ir/jobs/" . $job['id'];

                $response[$key] = array_filter(
                    $response[$key],
                    function ($key) {
                        if (in_array($key, ['link', 'id', 'title', 'company', 'city'])) {
                            return $key;
                        }
                    },
                    ARRAY_FILTER_USE_KEY
                );
            }
            return array_values($response);
        } catch (\Exception $e) {
            \Log::warning("Failed to parse jobs for company {$company['companyId']}: " . $e->getMessage());
            return [];
        }
    }

    private function parseLocationFromResponse($body)
    {
        try {
            $responseData = json_decode($body, true);
            
            if (!isset($responseData['data'])) {
                return '';
            }
            
            $data = $responseData['data'];
            return ($data['cityFa'] ?? '') . " _ " . ($data['provinceFa'] ?? '');
        } catch (\Exception $e) {
            \Log::warning("Failed to parse location: " . $e->getMessage());
            return '';
        }
    }
}
