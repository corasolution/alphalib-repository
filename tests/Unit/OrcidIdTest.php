<?php

namespace Tests\Unit;

use Corasoft\AlphaScholar\Identity\OrcidId;
use InvalidArgumentException;
use Tests\TestCase;

class OrcidIdTest extends TestCase
{
    public function test_it_accepts_a_valid_orcid(): void
    {
        $orcid = new OrcidId('0000-0002-1825-0097');

        $this->assertSame('0000-0002-1825-0097', $orcid->value());
        $this->assertSame('https://orcid.org/0000-0002-1825-0097', $orcid->url());
    }

    public function test_it_rejects_a_bad_checksum(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new OrcidId('0000-0002-1825-0098');
    }

    public function test_it_rejects_a_malformed_orcid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new OrcidId('not-an-orcid');
    }
}
