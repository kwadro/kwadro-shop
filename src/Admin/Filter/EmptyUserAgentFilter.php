<?php

namespace App\Admin\Filter;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NullFilter;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\NullFilterType;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * Filters RequestList by empty / non-empty User-Agent without occupying the userAgent property
 * (so TextFilter can still search UA strings).
 */
final class EmptyUserAgentFilter implements FilterInterface
{
    use FilterTrait;

    public const CHOICE_EMPTY = NullFilter::CHOICE_VALUE_NULL;
    public const CHOICE_FILLED = NullFilter::CHOICE_VALUE_NOT_NULL;

    /**
     * @param TranslatableInterface|string|false|null $label
     */
    public static function new(
        string $propertyName = 'userAgentEmpty',
        TranslatableInterface|string|false|null $label = null,
    ): self {
        return (new self())
            ->setFilterFqcn(__CLASS__)
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setFormType(NullFilterType::class)
            ->setFormTypeOption('translation_domain', 'messages')
            ->setFormTypeOption('expanded', true);
    }

    public function setChoiceLabels(
        string|TranslatableInterface $emptyLabel,
        string|TranslatableInterface $filledLabel,
    ): self {
        $this->dto->setFormTypeOption('choices', [
            $emptyLabel => self::CHOICE_EMPTY,
            $filledLabel => self::CHOICE_FILLED,
        ]);

        return $this;
    }

    public function apply(QueryBuilder $queryBuilder, FilterDataDto $filterDataDto, ?FieldDto $fieldDto, EntityDto $entityDto): void
    {
        $alias = $filterDataDto->getEntityAlias();
        $empty = self::CHOICE_EMPTY === $filterDataDto->getValue();

        if ($empty) {
            $queryBuilder->andWhere(sprintf(
                '(%1$s.userAgent IS NULL OR %1$s.userAgent = \'\')',
                $alias,
            ));

            return;
        }

        $queryBuilder->andWhere(sprintf(
            '(%1$s.userAgent IS NOT NULL AND %1$s.userAgent <> \'\')',
            $alias,
        ));
    }
}
