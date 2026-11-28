<?php

namespace App\PartnerProgram\Entity;

use App\Entity\Traits\TimeStampAbleTrait;
use App\PartnerProgram\Repository\PartnerReferralHitRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * One captured partner landing (server-side), with optional FB / Google Ads click ids.
 */
#[ORM\Entity(repositoryClass: PartnerReferralHitRepository::class)]
#[ORM\Table(name: 'shop_partner_referral_hit')]
#[ORM\Index(columns: ['created_at'], name: 'idx_shop_partner_referral_hit_created')]
#[ORM\Index(columns: ['ad_source'], name: 'idx_shop_partner_referral_hit_ad_source')]
#[ORM\Index(columns: ['referral_key'], name: 'idx_shop_partner_referral_hit_referral_key')]
#[ORM\HasLifecycleCallbacks]
class PartnerReferralHit
{
    use TimeStampAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'referral_key', length: 64)]
    private string $referralKey = '';

    /** Query param or path page code that carried the referral (e.g. pr). */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $param = null;

    #[ORM\Column(name: 'landing_url', type: 'text')]
    private string $landingUrl = '';

    #[ORM\Column(name: 'visitor_id', length: 64, nullable: true)]
    private ?string $visitorId = null;

    #[ORM\Column(name: 'ip_address', length: 45, nullable: true)]
    private ?string $ipAddress = null;

    /** facebook | google_ads | null */
    #[ORM\Column(name: 'ad_source', length: 32, nullable: true)]
    private ?string $adSource = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $fbclid = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $fbc = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $fbp = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $gclid = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $gbraid = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $wbraid = null;

    #[ORM\Column(name: 'utm_source', length: 255, nullable: true)]
    private ?string $utmSource = null;

    #[ORM\Column(name: 'utm_medium', length: 255, nullable: true)]
    private ?string $utmMedium = null;

    #[ORM\Column(name: 'utm_campaign', length: 255, nullable: true)]
    private ?string $utmCampaign = null;

    #[ORM\Column(name: 'advertising_link_id', nullable: true)]
    private ?int $advertisingLinkId = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReferralKey(): string
    {
        return $this->referralKey;
    }

    public function setReferralKey(string $referralKey): static
    {
        $this->referralKey = mb_substr(trim($referralKey), 0, 64);

        return $this;
    }

    public function getParam(): ?string
    {
        return $this->param;
    }

    public function setParam(?string $param): static
    {
        $param = $param !== null ? trim($param) : null;
        $this->param = $param !== null && $param !== '' ? mb_substr($param, 0, 64) : null;

        return $this;
    }

    public function getLandingUrl(): string
    {
        return $this->landingUrl;
    }

    public function setLandingUrl(string $landingUrl): static
    {
        $this->landingUrl = trim($landingUrl);

        return $this;
    }

    public function getVisitorId(): ?string
    {
        return $this->visitorId;
    }

    public function setVisitorId(?string $visitorId): static
    {
        $visitorId = $visitorId !== null ? trim($visitorId) : null;
        $this->visitorId = $visitorId !== null && $visitorId !== '' ? mb_substr($visitorId, 0, 64) : null;

        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): static
    {
        $ipAddress = $ipAddress !== null ? trim($ipAddress) : null;
        $this->ipAddress = $ipAddress !== null && $ipAddress !== '' ? mb_substr($ipAddress, 0, 45) : null;

        return $this;
    }

    public function getAdSource(): ?string
    {
        return $this->adSource;
    }

    public function setAdSource(?string $adSource): static
    {
        $adSource = $adSource !== null ? trim($adSource) : null;
        $this->adSource = $adSource !== null && $adSource !== '' ? mb_substr($adSource, 0, 32) : null;

        return $this;
    }

    public function getFbclid(): ?string
    {
        return $this->fbclid;
    }

    public function setFbclid(?string $fbclid): static
    {
        $this->fbclid = self::nullableTruncated($fbclid, 512);

        return $this;
    }

    public function getFbc(): ?string
    {
        return $this->fbc;
    }

    public function setFbc(?string $fbc): static
    {
        $this->fbc = self::nullableTruncated($fbc, 512);

        return $this;
    }

    public function getFbp(): ?string
    {
        return $this->fbp;
    }

    public function setFbp(?string $fbp): static
    {
        $this->fbp = self::nullableTruncated($fbp, 512);

        return $this;
    }

    public function getGclid(): ?string
    {
        return $this->gclid;
    }

    public function setGclid(?string $gclid): static
    {
        $this->gclid = self::nullableTruncated($gclid, 512);

        return $this;
    }

    public function getGbraid(): ?string
    {
        return $this->gbraid;
    }

    public function setGbraid(?string $gbraid): static
    {
        $this->gbraid = self::nullableTruncated($gbraid, 512);

        return $this;
    }

    public function getWbraid(): ?string
    {
        return $this->wbraid;
    }

    public function setWbraid(?string $wbraid): static
    {
        $this->wbraid = self::nullableTruncated($wbraid, 512);

        return $this;
    }

    public function getUtmSource(): ?string
    {
        return $this->utmSource;
    }

    public function setUtmSource(?string $utmSource): static
    {
        $this->utmSource = self::nullableTruncated($utmSource, 255);

        return $this;
    }

    public function getUtmMedium(): ?string
    {
        return $this->utmMedium;
    }

    public function setUtmMedium(?string $utmMedium): static
    {
        $this->utmMedium = self::nullableTruncated($utmMedium, 255);

        return $this;
    }

    public function getUtmCampaign(): ?string
    {
        return $this->utmCampaign;
    }

    public function setUtmCampaign(?string $utmCampaign): static
    {
        $this->utmCampaign = self::nullableTruncated($utmCampaign, 255);

        return $this;
    }

    public function getAdvertisingLinkId(): ?int
    {
        return $this->advertisingLinkId;
    }

    public function setAdvertisingLinkId(?int $advertisingLinkId): static
    {
        $this->advertisingLinkId = $advertisingLinkId !== null && $advertisingLinkId > 0
            ? $advertisingLinkId
            : null;

        return $this;
    }

    public function __toString(): string
    {
        return sprintf('#%s %s', $this->id ?? 'new', $this->referralKey);
    }

    private static function nullableTruncated(?string $value, int $max): ?string
    {
        $value = $value !== null ? trim($value) : null;
        if ($value === null || $value === '') {
            return null;
        }

        return mb_substr($value, 0, $max);
    }
}
