<?php

namespace App\Service\JobScanner\Services;

use App\Service\JobScanner\Contracts\JobScanner;
use PHPHtmlParser\Dom;

class Jobinja implements JobScanner
{

    public function getLocation($link)
    {

            $dom = new Dom;
            $dom->loadFromUrl($link);
            $links = $dom->find('.c-companyMap__desc');
            return ($links->text);

    }

    public function getOffers($companyLink, $keywords = []): array
    {

            $client = new \GuzzleHttp\Client();
            $request = $client->get($companyLink . "/jobs");
            $response = $request->getBody();
            $body = $response->getContents();
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
    }

    public function getCompanies(): array
    {

        $top50Url = "https://jobinja.ir/top50";
        $client = new \GuzzleHttp\Client();
        $request = $client->get($top50Url);
        $response = $request->getBody();
        $body = $response->getContents();
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
    }


    public function getJobs($keywords, $process = false)
    {
        $companyLinks = $this->getCompanies();
        foreach ($companyLinks as $key => $link) {
            if ($process) {
                $process->progressAdvance();
            }

            $jobs = $this->getOffers($link, $keywords);

            if (!$jobs) {
                unset($companyLinks[$key]);
                continue;
            }
            $state = $this->getLocation($link);
            $companyLinks[$key] = compact('link', 'state', 'jobs');
        }
        return array_values($companyLinks);
    }
}
