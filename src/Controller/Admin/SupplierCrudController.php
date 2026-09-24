<?php

namespace App\Controller\Admin;

use App\Entity\Supplier;
use App\Repository\SupplierRepository;
use Doctrine\ORM\EntityManagerInterface;
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
        private readonly SupplierRepository $supplierRepository,
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
        yield TextField::new('slug', $this->translator->trans('admin.supplier.slug', [], 'messages'))
            ->setHelp($this->translator->trans('admin.supplier.slug_help', [], 'messages'));
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

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Supplier) {
            $this->ensureUniqueSlug($entityInstance);
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Supplier) {
            $this->ensureUniqueSlug($entityInstance);
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    private function ensureUniqueSlug(Supplier $supplier): void
    {
        $supplier->ensureSlug();
        $base = $supplier->getSlug();
        $slug = $base;
        $suffix = 1;

        while ($this->supplierRepository->slugExists($slug, $supplier->getId())) {
            $slug = $base . '-' . $suffix;
            ++$suffix;
        }

        $supplier->setSlug($slug);
    }
}
