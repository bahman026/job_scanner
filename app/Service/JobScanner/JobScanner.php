<?php
namespace App\Service\JobScanner;


use App\Service\JobScanner\Services\Jobinja;
use App\Service\JobScanner\Services\Jobvision;
use App\Service\JobScanner\Support\SearchOptions;

class JobScanner
{
    public static function getJobs($site, $keywords, $process, ?SearchOptions $options = null): array
    {
        $options ??= new SearchOptions();

        return match ($site) {
            'jobinja' => (new Jobinja())->getJobs($keywords, $process, $options),
            'jobvision' => (new Jobvision())->getJobs($keywords, $process, $options),
            default => throw new \InvalidArgumentException('Invalid product type.'),
        };
    }
}
