<?php

namespace App\Service\JobScanner;
use GuzzleHttp\Exception\GuzzleException;

class TopCompany
{
    /**
     * @throws GuzzleException
     */
    public static function getCompanies(): array
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
            return str_contains($item, "https://jobinja.ir/companies/") && !str_contains($item, "/jobs");
        });
        return array_values($extractedLinks);
    }
}
