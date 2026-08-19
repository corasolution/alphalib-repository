<?php

namespace Tests\Feature;

use Alpha\Journal\Models\User;
use Alpha\Journal\Ror\RorClient;
use Alpha\Journal\Ror\RorException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RorTest extends TestCase
{
    use RefreshDatabase;

    private function fakeRorResponse(): array
    {
        return [
            'items' => [
                [
                    'id' => 'https://ror.org/03yrm5c26',
                    'names' => [
                        ['value' => 'Stanford Univ.', 'types' => ['alias']],
                        ['value' => 'Stanford University', 'types' => ['ror_display']],
                    ],
                    'locations' => [
                        ['geonames_details' => ['country_name' => 'United States']],
                    ],
                ],
            ],
        ];
    }

    public function test_an_empty_query_returns_no_results_and_makes_no_request(): void
    {
        Http::fake();

        $results = app(RorClient::class)->search('');

        $this->assertSame([], $results);
        Http::assertNothingSent();
    }

    public function test_a_query_returns_mapped_organizations_preferring_the_display_name(): void
    {
        Http::fake(['api.ror.org/*' => Http::response($this->fakeRorResponse(), 200)]);

        $results = app(RorClient::class)->search('Stanford');

        $this->assertCount(1, $results);
        $this->assertSame('03yrm5c26', $results[0]->rorId);
        $this->assertSame('Stanford University', $results[0]->name);
        $this->assertSame('United States', $results[0]->country);
    }

    public function test_a_failed_response_throws(): void
    {
        Http::fake(['api.ror.org/*' => Http::response('error', 503)]);

        $this->expectException(RorException::class);

        app(RorClient::class)->search('Stanford');
    }

    public function test_the_search_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/ror/search?q=Stanford');

        $response->assertUnauthorized();
    }

    public function test_the_search_endpoint_returns_json(): void
    {
        Http::fake(['api.ror.org/*' => Http::response($this->fakeRorResponse(), 200)]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/ror/search?q=Stanford');

        $response->assertOk()->assertJson([
            ['ror_id' => '03yrm5c26', 'name' => 'Stanford University', 'country' => 'United States'],
        ]);
    }
}
