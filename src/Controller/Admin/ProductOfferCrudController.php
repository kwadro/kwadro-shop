<?php

namespace App\Controller\Admin;

use App\Entity\ProductOffer;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Symfony\Contracts\Translation\TranslatorInterface;

class ProductOfferCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return ProductOffer::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular($this->translator->trans('menu.link_product_offer_single', [], 'messages'))
            ->setEntityLabelInPlural($this->translator->trans('menu.link_product_offer', [], 'messages'))
            ->setDefaultSort(['price' => 'ASC']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add(EntityFilter::new('product'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('product', $this->translator->trans('admin.product_offer.product', [], 'messages'));
        yield AssociationField::new('supplier', $this->translator->trans('admin.product_offer.supplier', [], 'messages'));
        yield TextField::new('sku', $this->translator->trans('admin.product_offer.sku', [], 'messages'));
        yield MoneyField::new('price', $this->translator->trans('admin.product_offer.price', [], 'messages'))
            ->setCurrency('UAH')
            ->setStoredAsCents(false);
        yield MoneyField::new('oldPrice', $this->translator->trans('admin.product_offer.old_price', [], 'messages'))
            ->setCurrency('UAH')
            ->setStoredAsCents(false);
        yield IntegerField::new('qty', $this->translator->trans('admin.product_offer.qty', [], 'messages'))
            ->setHelp($this->translator->trans('admin.product_offer.qty_help', [], 'messages'));
    }
}
