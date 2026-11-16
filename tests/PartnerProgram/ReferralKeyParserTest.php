<?php

namespace App\Tests\PartnerProgram;

use App\PartnerProgram\Service\ReferralKeyParser;
use PHPUnit\Framework\TestCase;

final class ReferralKeyParserTest extends TestCase
{
    private ReferralKeyParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ReferralKeyParser();
    }

    public function testParseReferralKey(): void
    {
        $parsed = $this->parser->parseReferralKey('pb24jc3yts');
        self::assertNotNull($parsed);
        self::assertSame('pb', $parsed['pageCode']);
        self::assertSame('24jc3yts', $parsed['linkKey']);
        self::assertSame('pb24jc3yts', $parsed['referralKey']);
    }

    public function testRejectShortKey(): void
    {
        self::assertNull($this->parser->parseReferralKey('ab'));
    }

    public function testRejectLoginLikeValue(): void
    {
        self::assertNull($this->parser->parseReferralKey('login'));
    }
}
