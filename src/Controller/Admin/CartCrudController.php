<?php

namespace App\Controller\Admin;

use App\Entity\Cart;
use App\Entity\CartStatus;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use Symfony\Contracts\Translation\TranslatorInterface;

class CartCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Cart::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular($this->translator->trans('menu.link_cart_single', [], 'messages'))
            ->setEntityLabelInPlural($this->translator->trans('menu.link_cart', [], 'messages'))
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
                $this->translator->trans('admin.cart.status.active', [], 'messages') => CartStatus::Active,
                $this->translator->trans('admin.cart.status.inactive', [], 'messages') => CartStatus::Inactive,
                $this->translator->trans('admin.cart.status.suspended', [], 'messages') => CartStatus::Suspended,
            ]));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('cartSummaryLabel', 'admin.cart.items')
            ->onlyOnIndex();
        yield ChoiceField::new('status')
            ->setChoices([
                $this->translator->trans('admin.cart.status.active', [], 'messages') => CartStatus::Active,
                $this->translator->trans('admin.cart.status.inactive', [], 'messages') => CartStatus::Inactive,
                $this->translator->trans('admin.cart.status.suspended', [], 'messages') => CartStatus::Suspended,
            ])
            ->renderAsBadges([
                CartStatus::Active->value => 'success',
                CartStatus::Inactive->value => 'secondary',
                CartStatus::Suspended->value => 'warning',
            ]);
        yield TextField::new('visitor_id');
        yield AssociationField::new('customer');
        yield CollectionField::new('items')
            ->onlyOnDetail()
            ->allowAdd(false)
            ->allowDelete(false)
            ->useEntryCrudForm(CartItemCrudController::class);
        yield AssociationField::new('shipmentAddress')->onlyOnDetail();
        yield TextareaField::new('contact_data')->onlyOnDetail()
            ->formatValue(static fn (?array $value): string => json_encode($value ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}');
        yield TextareaField::new('delivery_data')->onlyOnDetail()
            ->formatValue(static fn (?array $value): string => json_encode($value ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}');
        yield TextareaField::new('order_data')->onlyOnDetail()
            ->formatValue(static fn (?array $value): string => json_encode($value ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}');
        yield TextareaField::new('cart_data')->onlyOnDetail()
            ->formatValue(static fn (?array $value): string => json_encode($value ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}');
        yield DateTimeField::new('created_at')->hideOnForm();
        yield DateTimeField::new('updated_at')->hideOnForm();
    }
}
