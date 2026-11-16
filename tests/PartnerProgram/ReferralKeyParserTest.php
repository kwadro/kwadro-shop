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
        $parsed = $this->parser->parseReferralKey('pr24jc3yts');
        self::assertNotNull($parsed);
        self::assertSame('pr', $parsed['pageCode']);
        self::assertSame('24jc3yts', $parsed['linkKey']);
        self::assertSame('pr24jc3yts', $parsed['referralKey']);
    }

    public function testParsePathOnlyWhenPageCodeInAdminList(): void
    {
        $allowed = $this->parser->filterPathPageCodes(['pr', 'ref', 'key', 'bb_ref', 'pb']);
        self::assertSame(['pr', 'pb'], $allowed);

        $parsed = $this->parser->parsePath('/ua/product/gear-1000/pr/24jc3yts', $allowed);
        self::assertNotNull($parsed);
        self::assertSame('pr24jc3yts', $parsed['referralKey']);
        self::assertSame('/ua/product/gear-1000', $this->parser->stripPathSuffix('/ua/product/gear-1000/pr/24jc3yts'));
    }

    public function testRejectPathWhenPageCodeNotInAdminList(): void
    {
        $allowed = $this->parser->filterPathPageCodes(['pr', 'ref']);
        self::assertNull($this->parser->parsePath('/ua/login', $allowed));
        self::assertNull($this->parser->parsePath('/ua/product/x/pb/24jc3yts', $allowed));
        self::assertNull($this->parser->parsePath('/ua/product/x/ref/24jc3yts', $allowed));
    }

    public function testRejectShortKey(): void
    {
        self::assertNull($this->parser->parseReferralKey('ab'));
    }
}
