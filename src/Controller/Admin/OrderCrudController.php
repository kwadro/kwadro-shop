<?php

namespace App\Controller\Admin;

use App\Entity\Order;
use App\Entity\OrderStatus;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Contracts\Translation\TranslatorInterface;

class OrderCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Order::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular($this->translator->trans('menu.link_order_single', [], 'messages'))
            ->setEntityLabelInPlural($this->translator->trans('menu.link_order', [], 'messages'))
            ->setDefaultSort(['created_at' => 'DESC'])
            ->setFormThemes([
                '@EasyAdmin/crud/form_theme.html.twig',
                'admin/fields.html.twig',
            ]);
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
                $this->translator->trans('admin.order.status.created', [], 'messages') => OrderStatus::Created,
                $this->translator->trans('admin.order.status.in_process', [], 'messages') => OrderStatus::InProcess,
                $this->translator->trans('admin.order.status.awaiting_deposit_for_shipment', [], 'messages') => OrderStatus::AwaitingDepositForShipment,
                $this->translator->trans('admin.order.status.deposit_paid', [], 'messages') => OrderStatus::DepositPaid,
                $this->translator->trans('admin.order.status.paid', [], 'messages') => OrderStatus::Paid,
            ]));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        yield Field::new('orderInfo', 'admin.order.info')
            ->onlyOnDetail()
            ->setTemplatePath('admin/field/order_info.html.twig');
        yield FormField::addFieldset('admin.order.info', 'fa fa-file-invoice')
            ->onlyOnForms()
            ->hideWhenCreating();
        yield Field::new('orderInfoPanel', false)
            ->onlyOnForms()
            ->hideWhenCreating()
            ->setFormType(HiddenType::class)
            ->setFormTypeOptions([
                'mapped' => false,
                'required' => false,
                'block_prefix' => 'order_info',
            ])
            ->setValue(true);

        yield Field::new('customerInfo', 'admin.order.customer_info')
            ->onlyOnDetail()
            ->setTemplatePath('admin/field/order_customer.html.twig');
        yield FormField::addFieldset('admin.order.customer_info', 'fa fa-user')
            ->onlyOnForms()
            ->hideWhenCreating();
        yield Field::new('customerInfoPanel', false)
            ->onlyOnForms()
            ->hideWhenCreating()
            ->setFormType(HiddenType::class)
            ->setFormTypeOptions([
                'mapped' => false,
                'required' => false,
                'block_prefix' => 'order_customer',
            ])
            ->setValue(true);

        yield Field::new('orderAmounts', 'admin.order.amounts')
            ->onlyOnDetail()
            ->setTemplatePath('admin/field/order_amounts.html.twig');
        yield FormField::addFieldset('admin.order.amounts', 'fa fa-coins')
            ->onlyOnForms()
            ->hideWhenCreating();
        yield Field::new('orderAmountsPanel', false)
            ->onlyOnForms()
            ->hideWhenCreating()
            ->setFormType(HiddenType::class)
            ->setFormTypeOptions([
                'mapped' => false,
                'required' => false,
                'block_prefix' => 'order_amounts',
            ])
            ->setValue(true);

        yield TextField::new('order_number', 'admin.order.order_number')->onlyOnIndex();
        yield TextField::new('contactSummary', 'admin.order.contact')
            ->onlyOnIndex();
        yield TextField::new('shipmentAddressSummary', 'admin.order.shipment_address')
            ->onlyOnDetail();
        yield TextField::new('npWaybillNumber', 'admin.order.np_waybill_number')
            ->onlyOnDetail()
            ->hideOnForm();
        yield TextField::new('npWaybillRef', 'admin.order.np_waybill_ref')
            ->onlyOnDetail()
            ->hideOnForm();
        yield AssociationField::new('shipmentAddress', 'admin.order.shipment_address')
            ->onlyOnDetail();
        yield ChoiceField::new('status', 'admin.order.status_label')
            ->onlyOnIndex()
            ->setChoices([
                $this->translator->trans('admin.order.status.created', [], 'messages') => OrderStatus::Created,
                $this->translator->trans('admin.order.status.in_process', [], 'messages') => OrderStatus::InProcess,
                $this->translator->trans('admin.order.status.awaiting_deposit_for_shipment', [], 'messages') => OrderStatus::AwaitingDepositForShipment,
                $this->translator->trans('admin.order.status.deposit_paid', [], 'messages') => OrderStatus::DepositPaid,
                $this->translator->trans('admin.order.status.paid', [], 'messages') => OrderStatus::Paid,
            ])
            ->renderAsBadges([
                OrderStatus::Created->value => 'secondary',
                OrderStatus::InProcess->value => 'warning',
                OrderStatus::AwaitingDepositForShipment->value => 'info',
                OrderStatus::DepositPaid->value => 'primary',
                OrderStatus::Paid->value => 'success',
            ]);
        yield MoneyField::new('amount', 'admin.order.order_total')
            ->setCurrency('UAH')
            ->setStoredAsCents(false)
            ->onlyOnIndex();
        yield TextareaField::new('delivery_data')->onlyOnDetail()
            ->formatValue(static fn (array $value): string => json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}');
        yield Field::new('itemsSummary', 'admin.order.items')
            ->onlyOnDetail()
            ->setTemplatePath('admin/field/order_items.html.twig');
        yield FormField::addFieldset('admin.order.items', 'fa fa-box')
            ->onlyOnForms()
            ->hideWhenCreating();
        yield Field::new('itemsSummaryPanel', false)
            ->onlyOnForms()
            ->hideWhenCreating()
            ->setFormType(HiddenType::class)
            ->setFormTypeOptions([
                'mapped' => false,
                'required' => false,
                'block_prefix' => 'order_items',
            ])
            ->setValue(true);
        yield Field::new('paymentsSummary', 'admin.order.payments')
            ->onlyOnDetail()
            ->setTemplatePath('admin/field/order_payments.html.twig');
        yield FormField::addFieldset('admin.order.payments', 'fa fa-credit-card')
            ->onlyOnForms()
            ->hideWhenCreating();
        yield Field::new('paymentsSummaryPanel', false)
            ->onlyOnForms()
            ->hideWhenCreating()
            ->setFormType(HiddenType::class)
            ->setFormTypeOptions([
                'mapped' => false,
                'required' => false,
                'block_prefix' => 'order_payments',
            ])
            ->setValue(true);
        yield TextareaField::new('cart_data')->onlyOnDetail()
            ->formatValue(static fn (array $value): string => json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}');
        yield DateTimeField::new('created_at', 'admin.order.created_at')->onlyOnIndex();
        yield DateTimeField::new('updated_at', 'admin.order.updated_at')->onlyOnIndex();
    }
}
