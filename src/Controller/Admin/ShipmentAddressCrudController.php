<?php

namespace App\Controller\Admin;

use App\Entity\ShipmentAddress;
use App\Entity\ShipmentAddressType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use Symfony\Contracts\Translation\TranslatorInterface;

class ShipmentAddressCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return ShipmentAddress::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular($this->translator->trans('menu.link_shipment_address_single', [], 'messages'))
            ->setEntityLabelInPlural($this->translator->trans('menu.link_shipment_address', [], 'messages'))
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
            ->add(ChoiceFilter::new('type')->setChoices([
                $this->translator->trans('admin.shipment_address.type.courier', [], 'messages') => ShipmentAddressType::Courier,
                $this->translator->trans('admin.shipment_address.type.np_branch', [], 'messages') => ShipmentAddressType::NovaPoshtaBranch,
                $this->translator->trans('admin.shipment_address.type.np_postomat', [], 'messages') => ShipmentAddressType::NovaPoshtaPostomat,
            ]));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield ChoiceField::new('type')
            ->setChoices([
                $this->translator->trans('admin.shipment_address.type.courier', [], 'messages') => ShipmentAddressType::Courier,
                $this->translator->trans('admin.shipment_address.type.np_branch', [], 'messages') => ShipmentAddressType::NovaPoshtaBranch,
                $this->translator->trans('admin.shipment_address.type.np_postomat', [], 'messages') => ShipmentAddressType::NovaPoshtaPostomat,
            ])
            ->renderAsBadges([
                ShipmentAddressType::Courier->value => 'info',
                ShipmentAddressType::NovaPoshtaBranch->value => 'primary',
                ShipmentAddressType::NovaPoshtaPostomat->value => 'secondary',
            ]);
        yield TextField::new('summaryLabel', 'admin.shipment_address.summary')
            ->onlyOnIndex();
        yield TextField::new('label')->hideOnIndex();
        yield BooleanField::new('is_default')->hideOnIndex();
        yield MoneyField::new('delivery_cost', 'admin.shipment_address.delivery_cost')->setCurrency('UAH')->setStoredAsCents(false);
        yield TextareaField::new('courier_address')->hideOnIndex();
        yield TextField::new('np_city_name')->hideOnIndex();
        yield TextField::new('np_warehouse_name')->hideOnIndex();
        yield TextField::new('np_city_ref')->onlyOnDetail();
        yield TextField::new('np_warehouse_ref')->onlyOnDetail();
        yield TextField::new('ownerUser', 'admin.shipment_address.user')
            ->onlyOnIndex()
            ->formatValue(static fn ($value, ShipmentAddress $address): string => (string) ($address->getOwnerUser()?->getEmail() ?? '—'));
        yield AssociationField::new('user')->hideOnIndex();
        yield AssociationField::new('cart')->onlyOnDetail();
        yield AssociationField::new('order')->onlyOnDetail();
        yield DateTimeField::new('created_at')->hideOnForm();
        yield DateTimeField::new('updated_at')->hideOnForm();
    }
}
