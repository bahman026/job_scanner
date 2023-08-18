<?php
namespace App\Service\JobScanner;

use App\Factories\Services\DiscountedPriceCalculator;
use App\Factories\Services\RegularPriceCalculator;
use App\Factories\Services\TaxIncludedPriceCalculator;
use App\Service\JobScanner\Services\Jobinja;
use App\Service\JobScanner\Services\Jobvision;

class JobScanner
{

    public static function getJobs($site, $keywords): array
    {
        return match ($site) {
            'jobinja' => (new Jobinja())->getJobs($keywords),
            'jobvision' => (new Jobvision())->getJobs($keywords),
            default => throw new \InvalidArgumentException('Invalid product type.'),
        };
    }
}
