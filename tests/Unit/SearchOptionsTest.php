<?php

namespace Tests\Unit;

use App\Service\JobScanner\Support\SearchOptions;
use PHPUnit\Framework\TestCase;

class SearchOptionsTest extends TestCase
{
    public function test_defaults_to_no_city_and_relevance(): void
    {
        $options = SearchOptions::fromArray([]);

        $this->assertNull($options->city);
        $this->assertSame(SearchOptions::SORT_RELEVANCE, $options->sort);
        $this->assertTrue($options->matchesCity('anything'));
        $this->assertTrue($options->matchesCity(null));
    }

    public function test_blank_city_means_no_filter_and_unknown_sort_falls_back(): void
    {
        $options = SearchOptions::fromArray(['city' => '   ', 'sort' => 'bogus']);

        $this->assertNull($options->city);
        $this->assertSame(SearchOptions::SORT_RELEVANCE, $options->sort);
    }

    public function test_city_matching_ignores_arabic_letter_variants_and_spacing(): void
    {
        // Arabic Yeh/Kaf vs Persian, and a ZWNJ inside the place name.
        $this->assertTrue(SearchOptions::fromArray(['city' => 'كرج'])->matchesCity('کرج'));
        $this->assertTrue(SearchOptions::fromArray(['city' => 'شهریار'])->matchesCity("شهري\u{200C}ار"));
        // Jobinja's location is a free-text address: substring match.
        $this->assertTrue(SearchOptions::fromArray(['city' => 'تهران'])->matchesCity('تهران، خیابان ولیعصر'));
        $this->assertFalse(SearchOptions::fromArray(['city' => 'مشهد'])->matchesCity('تهران، خیابان ولیعصر', null, ''));
    }
}
