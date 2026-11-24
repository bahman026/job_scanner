<?php

namespace Tests\Feature;

use Tests\TestCase;

class JobSearchValidationTest extends TestCase
{
    public function test_rejects_unknown_sort_value(): void
    {
        $this->postJson('/api/search', ['keywords' => 'laravel', 'sort' => 'oldest'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('sort');
    }

    public function test_rejects_overlong_city(): void
    {
        $this->postJson('/api/search', ['keywords' => 'laravel', 'city' => str_repeat('a', 101)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('city');
    }
}
