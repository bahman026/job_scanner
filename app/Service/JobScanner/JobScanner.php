<?php
namespace App\Service\JobScanner;

use GuzzleHttp\Exception\GuzzleException;
use PHPHtmlParser\Exceptions\ChildNotFoundException;
use PHPHtmlParser\Exceptions\CircularException;
use PHPHtmlParser\Exceptions\CurlException;
use PHPHtmlParser\Exceptions\NotLoadedException;
use PHPHtmlParser\Exceptions\StrictException;

class JobScanner
{
    /**
     * @throws GuzzleException
     */
    protected static function getTopCompanies(): array
    {
        return TopCompany::getCompanies();
    }

    /**
     * @throws CurlException
     * @throws ChildNotFoundException
     * @throws NotLoadedException
     * @throws CircularException
     * @throws GuzzleException
     * @throws StrictException
     */
    public static function getJobs($keywords): array
    {
        $companyLinks = JobScanner::getTopCompanies();
        foreach ($companyLinks as $key => $link) {
            $jobs = (JobOffer::getOffers($link, $keywords));
            if (!$jobs) {
                unset($companyLinks[$key]);
                continue;
            }
            $state = CompanyLocation::getLocation($link);
            $companyLinks[$key] = compact('link', 'state', 'jobs');
        }
        return array_values($companyLinks);
    }
}
