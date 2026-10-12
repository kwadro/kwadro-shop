<?php

namespace App\Entity;

use App\Entity\Traits\TimeStampAbleTrait;
use App\Repository\ProductAttributeValueRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductAttributeValueRepository::class)]
#[ORM\Table(name: 'shop_product_attribute_value')]
#[ORM\UniqueConstraint(name: 'uniq_shop_product_attribute_value', columns: ['product_id', 'attribute_id'])]
#[ORM\Index(name: 'idx_shop_pav_varchar', columns: ['attribute_id', 'value_varchar'])]
#[ORM\Index(name: 'idx_shop_pav_int', columns: ['attribute_id', 'value_int'])]
#[ORM\HasLifecycleCallbacks]
class ProductAttributeValue
{
    use TimeStampAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'attributeValues')]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Product $product = null;

    #[ORM\ManyToOne(targetEntity: ProductAttribute::class)]
    #[ORM\JoinColumn(name: 'attribute_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?ProductAttribute $attribute = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $value_varchar = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $value_text = null;

    #[ORM\Column(nullable: true)]
    private ?int $value_int = null;

    #[ORM\Column(type: 'decimal', precision: 16, scale: 4, nullable: true)]
    private ?string $value_decimal = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getAttribute(): ?ProductAttribute
    {
        return $this->attribute;
    }

    public function setAttribute(?ProductAttribute $attribute): static
    {
        $this->attribute = $attribute;

        return $this;
    }

    public function getValueVarchar(): ?string
    {
        return $this->value_varchar;
    }

    public function getValueText(): ?string
    {
        return $this->value_text;
    }

    public function getValueInt(): ?int
    {
        return $this->value_int;
    }

    public function getValueDecimal(): ?string
    {
        return $this->value_decimal;
    }

    public function clearValues(): static
    {
        $this->value_varchar = null;
        $this->value_text = null;
        $this->value_int = null;
        $this->value_decimal = null;

        return $this;
    }

    public function setFromRaw(string $raw, ProductAttributeDataType $dataType): static
    {
        $raw = trim($raw);
        $this->clearValues();
        if ($raw === '') {
            return $this;
        }

        return match ($dataType) {
            ProductAttributeDataType::Int => $this->setIntValue($raw),
            ProductAttributeDataType::Decimal => $this->setDecimalValue($raw),
            ProductAttributeDataType::Text => $this->setTextValue($raw),
            ProductAttributeDataType::Varchar => $this->setVarcharValue($raw),
        };
    }

    private function setVarcharValue(string $raw): static
    {
        $this->value_varchar = mb_substr($raw, 0, 255);

        return $this;
    }

    private function setTextValue(string $raw): static
    {
        $this->value_text = $raw;

        return $this;
    }

    private function setIntValue(string $raw): static
    {
        if (preg_match('/-?\d+/', $raw, $m) === 1) {
            $this->value_int = (int) $m[0];
            $this->value_varchar = mb_substr($raw, 0, 255);
        } else {
            $this->value_varchar = mb_substr($raw, 0, 255);
        }

        return $this;
    }

    private function setDecimalValue(string $raw): static
    {
        $normalized = str_replace(',', '.', $raw);
        if (preg_match('/-?\d+(?:\.\d+)?/', $normalized, $m) === 1) {
            $this->value_decimal = number_format((float) $m[0], 4, '.', '');
            $this->value_varchar = mb_substr($raw, 0, 255);
        } else {
            $this->value_varchar = mb_substr($raw, 0, 255);
        }

        return $this;
    }

    /** Display / filter value. */
    public function getDisplayValue(): string
    {
        if ($this->value_varchar !== null && $this->value_varchar !== '') {
            return $this->value_varchar;
        }
        if ($this->value_text !== null && $this->value_text !== '') {
            return $this->value_text;
        }
        if ($this->value_int !== null) {
            return (string) $this->value_int;
        }
        if ($this->value_decimal !== null && $this->value_decimal !== '') {
            return rtrim(rtrim($this->value_decimal, '0'), '.') ?: '0';
        }

        return '';
    }

    public function __toString(): string
    {
        return $this->getDisplayValue();
    }
}
