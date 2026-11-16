<?php

namespace App\Admin\Filter;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\NullFilterType;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * Select of known request paths (keeps TextFilter on "path" available).
 */
final class RequestPathChoiceFilter implements FilterInterface
{
    use FilterTrait;

    /**
     * @param TranslatableInterface|string|false|null $label
     */
    public static function new(
        string $propertyName = 'requestPathChoice',
        TranslatableInterface|string|false|null $label = null,
    ): self {
        return (new self())
            ->setFilterFqcn(__CLASS__)
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setFormType(NullFilterType::class)
            ->setFormTypeOption('translation_domain', 'messages')
            ->setFormTypeOption('expanded', false)
            ->setFormTypeOption('required', false)
            ->setFormTypeOption('placeholder', '');
    }

    /**
     * @param list<string> $paths
     */
    public function setPaths(array $paths): self
    {
        $choices = [];
        foreach ($paths as $path) {
            if (!\is_string($path) || $path === '') {
                continue;
            }
            $choices[$path] = $path;
        }

        $this->dto->setFormTypeOption('choices', $choices);

        return $this;
    }

    public function apply(QueryBuilder $queryBuilder, FilterDataDto $filterDataDto, ?FieldDto $fieldDto, EntityDto $entityDto): void
    {
        $value = $filterDataDto->getValue();
        if (!\is_string($value) || $value === '') {
            return;
        }

        $alias = $filterDataDto->getEntityAlias();
        $parameter = $filterDataDto->getParameterName();
        $queryBuilder
            ->andWhere(sprintf('%s.path = :%s', $alias, $parameter))
            ->setParameter($parameter, $value);
    }
}
