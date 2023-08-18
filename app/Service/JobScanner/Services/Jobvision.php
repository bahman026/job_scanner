<?php

namespace App\Service\JobScanner\Services;

use App\Service\JobScanner\Contracts\JobScanner;
use Illuminate\Support\Facades\Http;

class Jobvision implements JobScanner
{

    public function getLocation($company): string
    {
        $url = "https://candidateapi.jobvision.ir/api/v1/Company/Details?companyId=" . $company['companyId'];
        $response = Http::get($url);
        $response = json_decode($response->body(), true)['data'];
        return $response['cityFa'] . " _ " . $response['provinceFa'];
    }

    public function getOffers($companyLink, $keywords = [])
    {
        $url = "https://candidateapi.jobvision.ir/api/v1/JobPost/GetListOfCompanyJobPosts?companyId=" . $companyLink['companyId'];
        $response = Http::get($url);
        $response = json_decode($response->body(), true)['data'];

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
    }

    public function getCompanies()
    {
        $url = "https://candidateapi.jobvision.ir/api/v1/Company/GetListOfFilteredCompaniesSummaries";
        $data = [
            "keyword" => "",
            "filterParameters" => [
                "onlyHasActiveJobPosts" => true,
                "listOfCityIds" => [17, 41],
                "listOfCompanyBenefits" => [],
                "listOfCompanyScores" => [],
                "listOfCompanySizes" => [],
                "listOfIndustryIds" => [],
            ],
            "pageSize" => 200,
            "pageNumber" => 1,
            "orderBy" => 1,
        ];
        $response = Http::post($url, $data);
        $biggestCompanies = $response['data']['companies'];
        $data['orderBy'] = 0;
        $response = Http::post($url, $data);
        $largestCompanies = $response['data']['companies'];
        foreach ($largestCompanies as $company) {
            if (in_array($company['companyId'], $biggestCompanies)) {
                continue;
            }
            $biggestCompanies[] = $company;
        }
        return $biggestCompanies;
    }


    public function getJobs($keywords, $process = false): array
    {
        $companies = $this->getCompanies();
        foreach ($companies as $key => $company) {
            if ($process) {
                $process->progressAdvance();
            }
            $jobs = $this->getOffers($company, $keywords);
            if (!$jobs) {
                unset($companies[$key]);
                continue;
            }
            $state = $this->getLocation($company);
            $companies[$key] = compact('state', 'jobs', 'company');

        }
        return array_values($companies);
    }
}
