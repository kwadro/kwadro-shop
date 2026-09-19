<?php

namespace App\Controller\Admin;

use App\Entity\OrderItem;
use App\Entity\OrderItemStatus;
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

class OrderItemCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return OrderItem::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular($this->translator->trans('menu.link_order_item_single', [], 'messages'))
            ->setEntityLabelInPlural($this->translator->trans('menu.link_order_item', [], 'messages'))
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
                $this->translator->trans('admin.order_item.status.pending', [], 'messages') => OrderItemStatus::Pending,
                $this->translator->trans('admin.order_item.status.confirmed', [], 'messages') => OrderItemStatus::Confirmed,
                $this->translator->trans('admin.order_item.status.cancelled', [], 'messages') => OrderItemStatus::Cancelled,
            ]));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('order');
        yield IntegerField::new('product_id');
        yield TextField::new('product_name');
        yield TextField::new('product_sku')->hideOnIndex();
        yield TextField::new('supplier_name', $this->translator->trans('admin.order_item.supplier', [], 'messages'));
        yield IntegerField::new('offer_id')->hideOnIndex();
        yield IntegerField::new('quantity');
        yield MoneyField::new('unit_price')->setCurrency('UAH')->setStoredAsCents(false);
        yield MoneyField::new('line_total')->setCurrency('UAH')->setStoredAsCents(false);
        yield ChoiceField::new('status')
            ->setChoices([
                $this->translator->trans('admin.order_item.status.pending', [], 'messages') => OrderItemStatus::Pending,
                $this->translator->trans('admin.order_item.status.confirmed', [], 'messages') => OrderItemStatus::Confirmed,
                $this->translator->trans('admin.order_item.status.cancelled', [], 'messages') => OrderItemStatus::Cancelled,
            ])
            ->renderAsBadges([
                OrderItemStatus::Pending->value => 'warning',
                OrderItemStatus::Confirmed->value => 'success',
                OrderItemStatus::Cancelled->value => 'danger',
            ]);
        yield Field::new('product_snapshot')
            ->setTemplateName('crud/field/text')
            ->onlyOnDetail()
            ->formatValue(static fn (?array $value): string => json_encode($value ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}');
        yield DateTimeField::new('created_at')->hideOnForm();
        yield DateTimeField::new('updated_at')->hideOnForm();
    }
}
