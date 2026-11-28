<?php

namespace App\Tests\PartnerProgram;

use App\PartnerProgram\Service\AdClickAttribution;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class AdClickAttributionTest extends TestCase
{
    private AdClickAttribution $attribution;

    protected function setUp(): void
    {
        $this->attribution = new AdClickAttribution();
    }

    public function testExtractsFacebookClickId(): void
    {
        $request = Request::create('/ua/?pr=pr24jc3yts&fbclid=Iw123abc');
        $data = $this->attribution->extract($request);

        self::assertSame(AdClickAttribution::SOURCE_FACEBOOK, $data['source']);
        self::assertSame('Iw123abc', $data['fbclid']);
        self::assertNotNull($data['new_fbc']);
        self::assertStringStartsWith('fb.1.', (string) $data['new_fbc']);
        self::assertStringEndsWith('.Iw123abc', (string) $data['new_fbc']);
        self::assertSame($data['new_fbc'], $data['fbc']);
    }

    public function testExtractsGoogleAdsClickId(): void
    {
        $request = Request::create('/ua/?pr=pr24jc3yts&gclid=Cj0KCQiw');
        $data = $this->attribution->extract($request);

        self::assertSame(AdClickAttribution::SOURCE_GOOGLE_ADS, $data['source']);
        self::assertSame('Cj0KCQiw', $data['gclid']);
        self::assertNull($data['fbclid']);
    }

    public function testUtmSourceFacebookWithoutClickId(): void
    {
        $request = Request::create('/ua/?pr=pr24jc3yts&utm_source=facebook&utm_medium=paid');
        $data = $this->attribution->extract($request);

        self::assertSame(AdClickAttribution::SOURCE_FACEBOOK, $data['source']);
        self::assertSame('facebook', $data['utm_source']);
        self::assertSame('paid', $data['utm_medium']);
    }

    public function testReusesExistingFbpCookie(): void
    {
        $request = Request::create('/ua/?pr=pr24jc3yts&fbclid=xyz');
        $request->cookies->set(AdClickAttribution::COOKIE_FBP, 'fb.1.1700000000000.1234567890');
        $data = $this->attribution->extract($request);

        self::assertSame('fb.1.1700000000000.1234567890', $data['fbp']);
        self::assertNull($data['new_fbp']);
    }

    public function testToApiPayloadOmitsEmptyFields(): void
    {
        $request = Request::create('/ua/?pr=pr24jc3yts&gclid=ABC&utm_campaign=spring');
        $payload = $this->attribution->toApiPayload($this->attribution->extract($request));

        self::assertSame(AdClickAttribution::SOURCE_GOOGLE_ADS, $payload['source']);
        self::assertSame('ABC', $payload['gclid']);
        self::assertSame('spring', $payload['utm_campaign']);
        self::assertArrayNotHasKey('fbclid', $payload);
        self::assertArrayNotHasKey('new_fbc', $payload);
        self::assertArrayNotHasKey('new_fbp', $payload);
    }
}
