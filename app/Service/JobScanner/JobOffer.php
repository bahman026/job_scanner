<?php

namespace App\Service\JobScanner;

use GuzzleHttp\Exception\ClientException;
use Mockery\Exception;

class JobOffer
{

    public static function getOffers($companyLink, $keywords = [])
    {
        try {
            $client = new \GuzzleHttp\Client();
            $request = $client->get($companyLink."/jobs");
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

                if (str_contains(trim($link->parentNode->nodeValue), "(منقضی شده)")) {
                    continue;
                }

                if (strlen(trim($linkHref)) == 0) {
                    continue;
                }
                if ($linkHref[0] == '#' || !str_contains($linkHref, $companyLink)) {
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
                    if (str_contains($link->parentNode->nodeValue, $keyword)) {
                        $searchFlag = true;
                    }
                }
                if (!$searchFlag && count($keywords)) {
                    continue;
                }
                $extractedLinks[] = $linkHref;
            }
            return array_values($extractedLinks);
        } catch (Exception|ClientException $exception) {
            return null;
        }
    }
}
