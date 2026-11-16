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
 * Select: only bot requests / only non-bot requests (by User-Agent markers).
 */
final class BotRequestFilter implements FilterInterface
{
    use FilterTrait;

    public const CHOICE_BOTS = 'bots';
    public const CHOICE_HUMANS = 'humans';

    /**
     * @param TranslatableInterface|string|false|null $label
     */
    public static function new(
        string $propertyName = 'botRequest',
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

    public function setChoiceLabels(
        string|TranslatableInterface $botsLabel,
        string|TranslatableInterface $humansLabel,
    ): self {
        $this->dto->setFormTypeOption('choices', [
            $botsLabel => self::CHOICE_BOTS,
            $humansLabel => self::CHOICE_HUMANS,
        ]);

        return $this;
    }

    /**
     * @param list<string> $likePatterns SQL/DQL LIKE patterns, e.g. %googlebot%
     */
    public function setLikePatterns(array $likePatterns): self
    {
        $normalized = array_values(array_filter(
            $likePatterns,
            static fn (mixed $p): bool => \is_string($p) && $p !== '',
        ));
        $this->dto->setCustomOption('like_patterns', $normalized);

        return $this;
    }

    public function apply(QueryBuilder $queryBuilder, FilterDataDto $filterDataDto, ?FieldDto $fieldDto, EntityDto $entityDto): void
    {
        $value = $filterDataDto->getValue();
        if ($value !== self::CHOICE_BOTS && $value !== self::CHOICE_HUMANS) {
            return;
        }

        $alias = $filterDataDto->getEntityAlias();
        $uaExpr = sprintf('LOWER(COALESCE(%s.userAgent, \'\'))', $alias);
        /** @var list<string> $likePatterns */
        $likePatterns = $this->dto->getCustomOption('like_patterns') ?? [];

        if ($likePatterns === []) {
            // No markers configured: nothing is a bot.
            if ($value === self::CHOICE_BOTS) {
                $queryBuilder->andWhere('1 = 0');
            }

            return;
        }

        $parts = [];
        foreach ($likePatterns as $i => $pattern) {
            $param = $filterDataDto->getParameterName().'_'.$i;
            $parts[] = sprintf('%s LIKE :%s', $uaExpr, $param);
            $queryBuilder->setParameter($param, $pattern);
        }

        $botCondition = '('.implode(' OR ', $parts).')';
        if ($value === self::CHOICE_BOTS) {
            $queryBuilder->andWhere($botCondition);

            return;
        }

        $queryBuilder->andWhere(sprintf('NOT %s', $botCondition));
    }
}
