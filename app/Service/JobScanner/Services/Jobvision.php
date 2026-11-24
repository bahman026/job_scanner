<?php

namespace App\Service\JobScanner\Services;

use App\Service\JobScanner\Contracts\JobScanner;
use App\Service\JobScanner\Exceptions\SourceUnavailableException;
use App\Service\JobScanner\Support\SearchOptions;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;

class Jobvision implements JobScanner
{
    private const SEARCH_URL = 'https://candidateapi.jobvision.ir/api/v1/JobPost/List';
    private const SEARCH_PAGE_SIZE = 30;
    // Safety cap per keyword: 10 pages x 30 = up to 300 job posts.
    private const SEARCH_MAX_PAGES = 10;

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

    /**
     * Merge Jobvision's "biggest" (orderBy 0) and "largest" (orderBy 1) company
     * lists, de-duplicated by companyId.
     *
     * @throws SourceUnavailableException when either list can't be fetched.
     */
    public function getCompanies(): array
    {
        $companies = $this->fetchCompanySummaries(0);
        $seenIds = array_column($companies, 'companyId');

        foreach ($this->fetchCompanySummaries(1) as $company) {
            if (!in_array($company['companyId'], $seenIds)) {
                $companies[] = $company;
                $seenIds[] = $company['companyId'];
            }
        }

        return $companies;
    }

    private function fetchCompanySummaries(int $orderBy): array
    {
        $url = "https://candidateapi.jobvision.ir/api/v1/Company/GetListOfFilteredCompaniesSummaries";
        $payload = [
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
            "orderBy" => $orderBy,
        ];

        try {
            // 'json' sets the body and the Content-Type header; the client already carries the browser headers.
            $response = $this->client->post($url, ['json' => $payload]);
        } catch (GuzzleException $e) {
            \Log::error('Jobvision company list request failed: ' . $e->getMessage(), ['orderBy' => $orderBy]);
            throw new SourceUnavailableException('Jobvision could not be reached.', 0, $e);
        }

        $status = $response->getStatusCode();
        $body = $response->getBody()->getContents();

        if ($status !== 200) {
            \Log::error('Jobvision company list request failed', [
                'status' => $status,
                'orderBy' => $orderBy,
                'body' => mb_substr($body, 0, 500),
            ]);
            throw new SourceUnavailableException("Jobvision returned HTTP {$status}.");
        }

        $responseData = json_decode($body, true);

        if (!isset($responseData['data']['companies']) || !is_array($responseData['data']['companies'])) {
            \Log::error('Unexpected Jobvision company list response', [
                'orderBy' => $orderBy,
                'body' => mb_substr($body, 0, 500),
            ]);
            throw new SourceUnavailableException('Jobvision returned an unexpected response (the API may have changed).');
        }

        return $responseData['data']['companies'];
    }


    /**
     * With keywords, use Jobvision's own site-wide job search (matches titles
     * and descriptions across every company). Without keywords, fall back to
     * listing every job at the top companies.
     *
     * Both return a list of ['company' => [...], 'state' => string, 'jobs' => [...]].
     * City filter and newest-first sorting apply to the keyword search only.
     */
    public function getJobs($keywords, $process = false, ?SearchOptions $options = null): array
    {
        $options ??= new SearchOptions();
        $keywords = array_values(array_filter(array_map('trim', (array) $keywords), 'strlen'));

        if (empty($keywords)) {
            return $this->scanTopCompanies($process);
        }

        $jobPosts = [];
        foreach ($keywords as $keyword) {
            foreach ($this->searchJobPosts($keyword, $process) as $post) {
                $jobPosts[$post['id']] = $post; // de-duplicate across keywords
            }
        }

        return $this->buildResults($jobPosts, $options);
    }

    /**
     * All active job posts matching $keyword, following pagination up to SEARCH_MAX_PAGES.
     *
     * @throws SourceUnavailableException when the first page can't be fetched.
     */
    private function searchJobPosts(string $keyword, $process = false): array
    {
        $posts = [];

        for ($page = 1; $page <= self::SEARCH_MAX_PAGES; $page++) {
            try {
                $data = $this->fetchSearchPage($keyword, $page);
            } catch (SourceUnavailableException $e) {
                if ($page === 1) {
                    throw $e;
                }
                // Keep what we already have rather than failing the whole search.
                \Log::warning("Jobvision search stopped at page {$page} for '{$keyword}': " . $e->getMessage());
                break;
            }

            if ($process) {
                $process->progressAdvance();
            }

            $pagePosts = $data['jobPosts'] ?? [];
            foreach ($pagePosts as $post) {
                if (!($post['expireTime']['isExpired'] ?? false)) {
                    $posts[] = $post;
                }
            }

            $total = (int) ($data['jobPostCount'] ?? 0);
            if (count($pagePosts) < self::SEARCH_PAGE_SIZE || $page * self::SEARCH_PAGE_SIZE >= $total) {
                break;
            }
        }

        return $posts;
    }

    private function fetchSearchPage(string $keyword, int $page): array
    {
        try {
            $response = $this->client->post(self::SEARCH_URL, ['json' => [
                'keyword' => $keyword,
                'requestedPage' => $page,
                'pageSize' => self::SEARCH_PAGE_SIZE,
                'sortBy' => 1,
            ]]);
        } catch (GuzzleException $e) {
            \Log::error("Jobvision search request failed for '{$keyword}' page {$page}: " . $e->getMessage());
            throw new SourceUnavailableException('Jobvision could not be reached.', 0, $e);
        }

        $status = $response->getStatusCode();
        $body = $response->getBody()->getContents();

        if ($status !== 200) {
            \Log::error('Jobvision search request failed', [
                'status' => $status, 'keyword' => $keyword, 'page' => $page, 'body' => mb_substr($body, 0, 500),
            ]);
            throw new SourceUnavailableException("Jobvision returned HTTP {$status}.");
        }

        $responseData = json_decode($body, true);
        if (!isset($responseData['data']['jobPosts']) || !is_array($responseData['data']['jobPosts'])) {
            \Log::error('Unexpected Jobvision search response', [
                'keyword' => $keyword, 'page' => $page, 'body' => mb_substr($body, 0, 500),
            ]);
            throw new SourceUnavailableException('Jobvision returned an unexpected response (the API may have changed).');
        }

        return $responseData['data'];
    }

    /**
     * Turn raw search posts into the company-grouped result shape, applying the
     * city filter and (optionally) newest-first ordering.
     *
     * Public so it can be tested without hitting Jobvision.
     */
    public function buildResults(array $jobPosts, SearchOptions $options): array
    {
        $companies = [];

        foreach ($jobPosts as $post) {
            $location = $post['location'] ?? [];
            $city = $location['city']['titleFa'] ?? '';
            $province = $location['province']['titleFa'] ?? '';

            if (!$options->matchesCity(
                $city,
                $location['city']['titleEn'] ?? null,
                $province,
                $location['province']['titleEn'] ?? null,
            )) {
                continue;
            }

            $company = $post['company'] ?? [];
            $companyId = $company['id'] ?? 0;
            $companyName = $company['nameFa'] ?? ($company['nameEn'] ?? '');

            if (!isset($companies[$companyId])) {
                $companies[$companyId] = [
                    'company' => [
                        'companyId' => $companyId,
                        'name' => $companyName,
                        'nameEn' => $company['nameEn'] ?? null,
                        'link' => isset($company['pageUrl']) ? 'https://jobvision.ir' . $company['pageUrl'] : null,
                    ],
                    'state' => trim("{$city} _ {$province}", ' _'),
                    'jobs' => [],
                ];
            }

            $companies[$companyId]['jobs'][] = [
                'id' => $post['id'],
                'title' => $post['title'] ?? '',
                'company' => $companyName,
                'city' => $city,
                'posted_at' => $this->postedAt($post),
                'link' => 'https://jobvision.ir/jobs/' . $post['id'],
            ];
        }

        $companies = array_values($companies);

        if ($options->sortsByNewest()) {
            $companies = $this->sortNewestFirst($companies);
        }

        return $companies;
    }

    /**
     * Newest job first within each company, and companies ordered by their newest job.
     * Jobs without a known date go last.
     */
    private function sortNewestFirst(array $companies): array
    {
        $timestamp = fn (array $job) => $job['posted_at'] ? strtotime($job['posted_at']) : 0;

        foreach ($companies as &$company) {
            usort($company['jobs'], fn ($a, $b) => $timestamp($b) <=> $timestamp($a));
        }
        unset($company);

        usort($companies, fn ($a, $b) => $timestamp($b['jobs'][0]) <=> $timestamp($a['jobs'][0]));

        return $companies;
    }

    /**
     * When the post went live, as an ISO-8601 string, or null if unknown.
     * Jobvision sends time fields as objects like {"date": "2026-11-23T10:00:00Z", ...}
     * (see expireTime); accept a plain string too in case activationTime differs.
     */
    private function postedAt(array $post): ?string
    {
        foreach (['activationTime', 'firstActivationTime'] as $field) {
            $value = $post[$field] ?? null;
            $date = is_array($value) ? ($value['date'] ?? null) : $value;

            if (is_string($date) && strtotime($date) !== false) {
                return gmdate('Y-m-d\TH:i:s\Z', strtotime($date));
            }
        }

        return null;
    }

    /**
     * Every active job at the top companies (used when no keywords are given).
     */
    private function scanTopCompanies($process = false): array
    {
        $keywords = [];
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
