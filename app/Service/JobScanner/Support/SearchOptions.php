<?php

namespace App\Service\JobScanner\Support;

/**
 * Optional filters/ordering for a job search, shared by every source.
 */
final class SearchOptions
{
    public const SORT_RELEVANCE = 'relevance';
    public const SORT_NEWEST = 'newest';

    public function __construct(
        public readonly ?string $city = null,
        public readonly string $sort = self::SORT_RELEVANCE,
    ) {
    }

    public static function fromArray(array $options): self
    {
        $city = trim((string) ($options['city'] ?? ''));

        return new self(
            city: $city === '' ? null : $city,
            sort: ($options['sort'] ?? null) === self::SORT_NEWEST ? self::SORT_NEWEST : self::SORT_RELEVANCE,
        );
    }

    public function sortsByNewest(): bool
    {
        return $this->sort === self::SORT_NEWEST;
    }

    /**
     * True when no city filter is set, or when any of $places mentions the city.
     * Matching is case-insensitive, ignores spacing/ZWNJ and Arabic vs Persian
     * letter variants, so "Tehran", "tehran" and "تهران" behave as users expect.
     */
    public function matchesCity(?string ...$places): bool
    {
        if ($this->city === null) {
            return true;
        }

        $needle = self::normalize($this->city);

        foreach ($places as $place) {
            if ($place !== null && $place !== '' && str_contains(self::normalize($place), $needle)) {
                return true;
            }
        }

        return false;
    }

    private static function normalize(string $text): string
    {
        $text = str_replace(
            ['ي', 'ك', 'ة', "\u{200C}", "\u{200F}", ' ', '-', '_'],
            ['ی', 'ک', 'ه', '', '', '', '', ''],
            $text
        );

        return mb_strtolower($text);
    }
}
