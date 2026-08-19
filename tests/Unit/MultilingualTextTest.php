<?php

namespace Tests\Unit;

use Corasoft\AlphaScholar\Metadata\MultilingualText;
use Tests\TestCase;

class MultilingualTextTest extends TestCase
{
    public function test_it_resolves_the_requested_locale(): void
    {
        $text = MultilingualText::fromArray(['en' => 'English title', 'km' => 'Khmer title']);

        $this->assertSame('Khmer title', $text->get('km', 'en'));
    }

    public function test_it_falls_back_through_the_locale_chain(): void
    {
        $text = MultilingualText::fromArray(['en' => 'Site primary title']);

        $this->assertSame('Site primary title', $text->get('fr', 'km', 'en'));
    }

    public function test_it_returns_null_when_no_locale_in_the_chain_resolves(): void
    {
        $text = MultilingualText::fromArray(['en' => 'English title']);

        $this->assertNull($text->get('fr', 'km'));
    }
}
