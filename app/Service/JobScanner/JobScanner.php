<?php
namespace App\Service\JobScanner;


use App\Service\JobScanner\Services\Jobinja;
use App\Service\JobScanner\Services\Jobvision;

class JobScanner
{
    public static function getJobs($site, $keywords, $process): array
    {


        return match ($site) {
            'jobinja' => (new Jobinja())->getJobs($keywords, $process),
            'jobvision' => (new Jobvision())->getJobs($keywords, $process),
            default => throw new \InvalidArgumentException('Invalid product type.'),
        };
    }
}
