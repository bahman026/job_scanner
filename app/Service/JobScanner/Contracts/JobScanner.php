<?php

namespace App\Service\JobScanner\Contracts;

use App\Service\JobScanner\Support\SearchOptions;

interface JobScanner
{
    public function getLocation($link);

    public function getOffers($link, $keywords = []);

    public function getCompanies();

    public function getJobs($keywords, $process = false, ?SearchOptions $options = null);

}
