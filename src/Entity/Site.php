<?php

namespace App\Entity;

use DateTimeImmutable;
use App\Entity\Traits\TimeStampAbleTrait;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;
use App\Repository\SiteRepository;
use App\Entity\HeaderSetting;
use App\Entity\SeoSetting;
use App\Entity\FooterSetting;
use App\Entity\MegaMenuSetting;
use App\Entity\Popularsearch;
use App\Entity\EmailTemplate;
use App\Entity\EmailTemplateSection;

#[ORM\Entity(repositoryClass: SiteRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Site
{
    use TimestampableTrait;
    #[ORM\Id]
    #[ORM\GeneratedValue]

    #[ORM\Column(type:"integer", nullable:true)]
    private ?int $id;

    #[ORM\Column(type:"string", nullable:true)]
    private ?string $code;

    #[ORM\Column(type:"string", nullable:true)]
    private ?string $domain;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => '100.00'])]
    private ?string $courier_delivery_cost = '100.00';

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, options: ['default' => '2.00'])]
    private ?string $cod_standard_percent = '2.00';

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, options: ['default' => '1.00'])]
    private ?string $cod_novapay_percent = '1.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => '100.00'])]
    private ?string $cod_prepayment_amount = '100.00';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $cod_commission_notice_uk = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $cod_commission_notice_en = null;

    /** @var list<string>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $active_payment_methods = null;

    /** @var list<string>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $active_delivery_methods = null;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(name: 'feature_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Product $featuredProduct = null;

    #[ORM\OneToMany(
        targetEntity: HeaderSetting::class,
        mappedBy: 'site',
        cascade: ['persist'],
        orphanRemoval: false,
    )]
        public ?Collection $headersettingsites;
    #[ORM\OneToMany(
        targetEntity: SeoSetting::class,
        mappedBy: 'site',
        cascade: ['persist'],
        orphanRemoval: false,
    )]
        public ?Collection $seosettingsites;
    #[ORM\OneToMany(
        targetEntity: FooterSetting::class,
        mappedBy: 'site',
        cascade: ['persist'],
        orphanRemoval: false,
    )]
        public ?Collection $footersettingsites;
    #[ORM\OneToMany(
        targetEntity: MegaMenuSetting::class,
        mappedBy: 'site',
        cascade: ['persist'],
        orphanRemoval: false,
    )]
        public ?Collection $megamenusites;
    #[ORM\OneToMany(
        targetEntity: Popularsearch::class,
        mappedBy: 'site',
        cascade: ['persist'],
        orphanRemoval: false,
    )]
        public ?Collection $popularsearchsites;
    #[ORM\OneToMany(
        targetEntity: EmailTemplate::class,
        mappedBy: 'site',
        cascade: ['persist'],
        orphanRemoval: false,
    )]
    private Collection $emailTemplates;

    #[ORM\OneToMany(
        targetEntity: EmailTemplateSection::class,
        mappedBy: 'site',
        cascade: ['persist'],
        orphanRemoval: false,
    )]
    private Collection $emailTemplateSections;

    public function __construct()
    {
        $this->headersettingsites = new ArrayCollection();
        $this->seosettingsites = new ArrayCollection();
        $this->footersettingsites = new ArrayCollection();
        $this->megamenusites = new ArrayCollection();
        $this->popularsearchsites = new ArrayCollection();
        $this->emailTemplates = new ArrayCollection();
        $this->emailTemplateSections = new ArrayCollection();
    }
    public function setId(?int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }
    public function setCode(?string $code): self
    {
        $this->code = $code;
        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }
    public function setDomain(?string $domain): self
    {
        $this->domain = $domain;
        return $this;
    }

    public function getDomain(): ?string
    {
        return $this->domain;
    }

    public function getCourierDeliveryCost(): ?float
    {
        return $this->courier_delivery_cost !== null ? (float) $this->courier_delivery_cost : null;
    }

    public function setCourierDeliveryCost(?float $courierDeliveryCost): self
    {
        $this->courier_delivery_cost = $courierDeliveryCost !== null
            ? number_format(max(0, $courierDeliveryCost), 2, '.', '')
            : null;

        return $this;
    }

    public function getCodStandardPercent(): float
    {
        return $this->cod_standard_percent !== null ? (float) $this->cod_standard_percent : 2.0;
    }

    public function setCodStandardPercent(?float $codStandardPercent): self
    {
        $this->cod_standard_percent = $codStandardPercent !== null
            ? number_format(max(0, min(100, $codStandardPercent)), 2, '.', '')
            : null;

        return $this;
    }

    public function getCodNovapayPercent(): float
    {
        return $this->cod_novapay_percent !== null ? (float) $this->cod_novapay_percent : 1.0;
    }

    public function setCodNovapayPercent(?float $codNovapayPercent): self
    {
        $this->cod_novapay_percent = $codNovapayPercent !== null
            ? number_format(max(0, min(100, $codNovapayPercent)), 2, '.', '')
            : null;

        return $this;
    }

    public function getCodPrepaymentAmount(): float
    {
        return $this->cod_prepayment_amount !== null ? (float) $this->cod_prepayment_amount : 100.0;
    }

    public function setCodPrepaymentAmount(?float $codPrepaymentAmount): self
    {
        $this->cod_prepayment_amount = $codPrepaymentAmount !== null
            ? number_format(max(0, $codPrepaymentAmount), 2, '.', '')
            : null;

        return $this;
    }

    public function getCodCommissionNoticeUk(): ?string
    {
        return $this->cod_commission_notice_uk;
    }

    public function setCodCommissionNoticeUk(?string $codCommissionNoticeUk): self
    {
        $this->cod_commission_notice_uk = $codCommissionNoticeUk;

        return $this;
    }

    public function getCodCommissionNoticeEn(): ?string
    {
        return $this->cod_commission_notice_en;
    }

    public function setCodCommissionNoticeEn(?string $codCommissionNoticeEn): self
    {
        $this->cod_commission_notice_en = $codCommissionNoticeEn;

        return $this;
    }

    public function getFeaturedProduct(): ?Product
    {
        return $this->featuredProduct;
    }

    public function setFeaturedProduct(?Product $featuredProduct): self
    {
        $this->featuredProduct = $featuredProduct;

        return $this;
    }

    public function getFeatureId(): ?int
    {
        return $this->featuredProduct?->getId();
    }

    /** @return list<string> */
    public function getActivePaymentMethods(): array
    {
        if (!\is_array($this->active_payment_methods) || $this->active_payment_methods === []) {
            return ShopPaymentMethod::all();
        }

        return array_values(array_intersect($this->active_payment_methods, ShopPaymentMethod::all()));
    }

    /** @param list<string>|null $methods */
    public function setActivePaymentMethods(?array $methods): self
    {
        if ($methods === null || $methods === []) {
            $this->active_payment_methods = null;

            return $this;
        }

        $this->active_payment_methods = array_values(array_intersect($methods, ShopPaymentMethod::all()));

        return $this;
    }

    /** @return list<string> */
    public function getActiveDeliveryMethods(): array
    {
        if (!\is_array($this->active_delivery_methods) || $this->active_delivery_methods === []) {
            return ShopDeliveryMethod::all();
        }

        return array_values(array_intersect($this->active_delivery_methods, ShopDeliveryMethod::all()));
    }

    /** @param list<string>|null $methods */
    public function setActiveDeliveryMethods(?array $methods): self
    {
        if ($methods === null || $methods === []) {
            $this->active_delivery_methods = null;

            return $this;
        }

        $this->active_delivery_methods = array_values(array_intersect($methods, ShopDeliveryMethod::all()));

        return $this;
    }

    public function __toString(): string
    {
        return $this->domain;
    }

    public function addHeaderSetting(HeaderSetting $headersetting): self
    {
        if(!$this->headersettingsites->contains($headersetting)) {
           $this->headersettingsites[] = $headersetting;
           $headersetting->setSite($this);
        }
        return $this;
    }

    public function removeHeaderSetting(HeaderSetting $headersetting): self
    {
        if($this->headersettingsites->removeElement($headersetting)) {
           if ($headersetting->getSite() === $this) {
               $headersetting->setSite(null);
           }
        }
        return $this;
    }

    public function getHeadersettingsites(): ?Collection
    {
        return $this->headersettingsites;
    }

    public function addSeoSetting(SeoSetting $seosetting): self
    {
        if(!$this->seosettingsites->contains($seosetting)) {
           $this->seosettingsites[] = $seosetting;
           $seosetting->setSite($this);
        }
        return $this;
    }

    public function removeSeoSetting(SeoSetting $seosetting): self
    {
        if($this->seosettingsites->removeElement($seosetting)) {
           if ($seosetting->getSite() === $this) {
               $seosetting->setSite(null);
           }
        }
        return $this;
    }

    public function getSeosettingsites(): ?Collection
    {
        return $this->seosettingsites;
    }

    public function addFooterSetting(FooterSetting $footersetting): self
    {
        if(!$this->footersettingsites->contains($footersetting)) {
           $this->footersettingsites[] = $footersetting;
           $footersetting->setSite($this);
        }
        return $this;
    }

    public function removeFooterSetting(FooterSetting $footersetting): self
    {
        if($this->footersettingsites->removeElement($footersetting)) {
           if ($footersetting->getSite() === $this) {
               $footersetting->setSite(null);
           }
        }
        return $this;
    }

    public function getFootersettingsites(): ?Collection
    {
        return $this->footersettingsites;
    }

    public function addMegaMenuSetting(MegaMenuSetting $megamenusetting): self
    {
        if(!$this->megamenusites->contains($megamenusetting)) {
           $this->megamenusites[] = $megamenusetting;
           $megamenusetting->setSite($this);
        }
        return $this;
    }

    public function removeMegaMenuSetting(MegaMenuSetting $megamenusetting): self
    {
        if($this->megamenusites->removeElement($megamenusetting)) {
           if ($megamenusetting->getSite() === $this) {
               $megamenusetting->setSite(null);
           }
        }
        return $this;
    }

    public function getMegamenusites(): ?Collection
    {
        return $this->megamenusites;
    }

    public function addPopularsearch(Popularsearch $popularsearch): self
    {
        if(!$this->popularsearchsites->contains($popularsearch)) {
           $this->popularsearchsites[] = $popularsearch;
           $popularsearch->setSite($this);
        }
        return $this;
    }

    public function removePopularsearch(Popularsearch $popularsearch): self
    {
        if($this->popularsearchsites->removeElement($popularsearch)) {
           if ($popularsearch->getSite() === $this) {
               $popularsearch->setSite(null);
           }
        }
        return $this;
    }

    public function getPopularsearchsites(): ?Collection
    {
        return $this->popularsearchsites;
    }

    /** @return Collection<int, EmailTemplate> */
    public function getEmailTemplates(): Collection
    {
        return $this->emailTemplates;
    }

    public function addEmailTemplate(EmailTemplate $emailTemplate): self
    {
        if (!$this->emailTemplates->contains($emailTemplate)) {
            $this->emailTemplates[] = $emailTemplate;
            $emailTemplate->setSite($this);
        }

        return $this;
    }

    public function removeEmailTemplate(EmailTemplate $emailTemplate): self
    {
        if ($this->emailTemplates->removeElement($emailTemplate)) {
            if ($emailTemplate->getSite() === $this) {
                $emailTemplate->setSite(null);
            }
        }

        return $this;
    }

    /** @return Collection<int, EmailTemplateSection> */
    public function getEmailTemplateSections(): Collection
    {
        return $this->emailTemplateSections;
    }

    public function addEmailTemplateSection(EmailTemplateSection $emailTemplateSection): self
    {
        if (!$this->emailTemplateSections->contains($emailTemplateSection)) {
            $this->emailTemplateSections[] = $emailTemplateSection;
            $emailTemplateSection->setSite($this);
        }

        return $this;
    }

    public function removeEmailTemplateSection(EmailTemplateSection $emailTemplateSection): self
    {
        if ($this->emailTemplateSections->removeElement($emailTemplateSection)) {
            if ($emailTemplateSection->getSite() === $this) {
                $emailTemplateSection->setSite(null);
            }
        }

        return $this;
    }

}
