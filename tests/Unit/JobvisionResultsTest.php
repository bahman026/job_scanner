<?php

namespace Tests\Unit;

use App\Service\JobScanner\Services\Jobvision;
use App\Service\JobScanner\Support\SearchOptions;
use PHPUnit\Framework\TestCase;

class JobvisionResultsTest extends TestCase
{
    /** A search post in the shape JobPost/List returns (trimmed to the fields we use). */
    private function post(int $id, int $companyId, string $cityFa, string $cityEn, ?string $activated): array
    {
        return [
            'id' => $id,
            'title' => "Job {$id}",
            'company' => [
                'id' => $companyId,
                'nameFa' => "شرکت {$companyId}",
                'nameEn' => "Company {$companyId}",
                'pageUrl' => "/companies/{$companyId}/x",
            ],
            'location' => [
                'province' => ['titleFa' => $cityFa, 'titleEn' => $cityEn],
                'city' => ['titleFa' => $cityFa, 'titleEn' => $cityEn],
            ],
            'activationTime' => $activated === null ? null : ['date' => $activated, 'isExpired' => false],
            'expireTime' => ['isExpired' => false],
        ];
    }

    private function posts(): array
    {
        return [
            $this->post(1, 10, 'تهران', 'Tehran', '2026-11-01T08:00:00Z'),
            $this->post(2, 20, 'اصفهان', 'Isfahan', '2026-11-20T08:00:00Z'),
            $this->post(3, 10, 'تهران', 'Tehran', '2026-11-22T08:00:00Z'),
            $this->post(4, 30, 'تهران', 'Tehran', null),
        ];
    }

    private function jobIds(array $results): array
    {
        return array_merge(...array_map(fn ($c) => array_column($c['jobs'], 'id'), $results));
    }

    public function test_relevance_keeps_search_order_and_groups_by_company(): void
    {
        $results = (new Jobvision())->buildResults($this->posts(), new SearchOptions());

        $this->assertSame([10, 20, 30], array_column(array_column($results, 'company'), 'companyId'));
        $this->assertSame([1, 3, 2, 4], $this->jobIds($results));
        $this->assertSame('شرکت 10', $results[0]['company']['name']);
        $this->assertSame('https://jobvision.ir/companies/10/x', $results[0]['company']['link']);
        $this->assertSame('2026-11-01T08:00:00Z', $results[0]['jobs'][0]['posted_at']);
        $this->assertNull($results[2]['jobs'][0]['posted_at']);
    }

    public function test_newest_sorts_jobs_and_companies_with_undated_last(): void
    {
        $results = (new Jobvision())->buildResults($this->posts(), new SearchOptions(sort: SearchOptions::SORT_NEWEST));

        // Company 10's newest job (22nd) beats company 20 (20th); company 30 has no date.
        $this->assertSame([10, 20, 30], array_column(array_column($results, 'company'), 'companyId'));
        $this->assertSame([3, 1, 2, 4], $this->jobIds($results));
    }

    public function test_city_filter_matches_persian_and_english_names(): void
    {
        $jobvision = new Jobvision();

        foreach (['تهران', 'tehran', 'TEHRAN', ' Tehran '] as $city) {
            $results = $jobvision->buildResults($this->posts(), SearchOptions::fromArray(['city' => $city]));
            $this->assertSame([1, 3, 4], $this->jobIds($results), "city '{$city}'");
        }

        $results = $jobvision->buildResults($this->posts(), SearchOptions::fromArray(['city' => 'اصفهان']));
        $this->assertSame([2], $this->jobIds($results));

        $results = $jobvision->buildResults($this->posts(), SearchOptions::fromArray(['city' => 'شیراز']));
        $this->assertSame([], $results);
    }

    public function test_activation_time_as_plain_string_is_accepted(): void
    {
        $post = $this->post(5, 50, 'تهران', 'Tehran', null);
        $post['activationTime'] = '2026-11-23T10:30:00Z';

        $results = (new Jobvision())->buildResults([$post], new SearchOptions());

        $this->assertSame('2026-11-23T10:30:00Z', $results[0]['jobs'][0]['posted_at']);
    }
}
