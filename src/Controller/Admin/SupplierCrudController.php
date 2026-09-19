<?php

namespace App\Controller\Admin;

use App\Entity\Supplier;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Contracts\Translation\TranslatorInterface;

class SupplierCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Supplier::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular($this->translator->trans('menu.link_supplier_single', [], 'messages'))
            ->setEntityLabelInPlural($this->translator->trans('menu.link_supplier', [], 'messages'))
            ->setDefaultSort(['name' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('name', $this->translator->trans('admin.supplier.name', [], 'messages'));
        yield TextareaField::new('description', $this->translator->trans('admin.supplier.description', [], 'messages'))
            ->hideOnIndex();
        yield TelephoneField::new('phone', $this->translator->trans('admin.supplier.phone', [], 'messages'));
        yield EmailField::new('email', $this->translator->trans('admin.supplier.email', [], 'messages'));
        yield CollectionField::new('offers', $this->translator->trans('admin.supplier.offers', [], 'messages'))
            ->onlyOnDetail()
            ->useEntryCrudForm(ProductOfferCrudController::class)
            ->allowAdd(false)
            ->allowDelete(false);
    }
}
