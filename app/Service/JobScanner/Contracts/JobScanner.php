<?php

namespace App\Service\JobScanner\Contracts;

interface JobScanner
{
    public function getLocation($link);

    public function getOffers($link, $keywords = []);

    public function getCompanies();

    public function getJobs($keywords);

}
