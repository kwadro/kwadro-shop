<?php

namespace App\Controller\Admin;

use App\Entity\CartItem;
use App\Entity\CartItemStatus;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use Symfony\Contracts\Translation\TranslatorInterface;

class CartItemCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return CartItem::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular($this->translator->trans('menu.link_cart_item_single', [], 'messages'))
            ->setEntityLabelInPlural($this->translator->trans('menu.link_cart_item', [], 'messages'))
            ->setDefaultSort(['updated_at' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::DELETE);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('status')->setChoices([
                $this->translator->trans('admin.cart_item.status.active', [], 'messages') => CartItemStatus::Active,
                $this->translator->trans('admin.cart_item.status.inactive', [], 'messages') => CartItemStatus::Inactive,
            ]));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('cart');
        yield IntegerField::new('product_id');
        yield TextField::new('product_name');
        yield TextField::new('product_sku')->hideOnIndex();
        yield IntegerField::new('quantity');
        yield MoneyField::new('unit_price')->setCurrency('UAH')->setStoredAsCents(false);
        yield MoneyField::new('lineTotal', 'admin.cart_item.line_total')
            ->setCurrency('UAH')
            ->setStoredAsCents(false)
            ->onlyOnIndex();
        yield ChoiceField::new('status')
            ->setChoices([
                $this->translator->trans('admin.cart_item.status.active', [], 'messages') => CartItemStatus::Active,
                $this->translator->trans('admin.cart_item.status.inactive', [], 'messages') => CartItemStatus::Inactive,
            ])
            ->renderAsBadges([
                CartItemStatus::Active->value => 'success',
                CartItemStatus::Inactive->value => 'secondary',
            ]);
        yield Field::new('product_snapshot')
            ->setTemplateName('crud/field/text')
            ->onlyOnDetail()
            ->formatValue(static fn (?array $value): string => json_encode($value ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}');
        yield DateTimeField::new('created_at')->hideOnForm();
        yield DateTimeField::new('updated_at')->hideOnForm();
    }
}
