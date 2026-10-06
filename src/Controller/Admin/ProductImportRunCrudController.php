<?php

namespace App\Controller\Admin;

use App\Entity\ProductImportRun;
use App\Entity\ProductImportRunStatus;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Contracts\Translation\TranslatorInterface;

class ProductImportRunCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return ProductImportRun::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular($this->translator->trans('menu.link_product_import_run_single', [], 'messages'))
            ->setEntityLabelInPlural($this->translator->trans('menu.link_product_import_run', [], 'messages'))
            ->setDefaultSort(['started_at' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('productImport', $this->translator->trans('admin.product_import_run.import', [], 'messages'));
        yield DateTimeField::new('startedAt', $this->translator->trans('admin.product_import_run.started_at', [], 'messages'));
        yield DateTimeField::new('finishedAt', $this->translator->trans('admin.product_import_run.finished_at', [], 'messages'));
        yield ChoiceField::new('status', $this->translator->trans('admin.product_import_run.status', [], 'messages'))
            ->setFormType(EnumType::class)
            ->setFormTypeOption('class', ProductImportRunStatus::class)
            ->setFormTypeOption('choice_label', static fn (ProductImportRunStatus $status): string => ucfirst($status->value))
            ->onlyOnForms();
        yield TextField::new('status', $this->translator->trans('admin.product_import_run.status', [], 'messages'))
            ->formatValue(static fn (mixed $value): string => $value instanceof ProductImportRunStatus
                ? ucfirst($value->value)
                : ucfirst((string) $value))
            ->hideOnForm();
        yield IntegerField::new('createdCount', $this->translator->trans('admin.product_import_run.created', [], 'messages'))
            ->hideOnIndex();
        yield IntegerField::new('updatedCount', $this->translator->trans('admin.product_import_run.updated', [], 'messages'))
            ->hideOnIndex();
        yield IntegerField::new('disabledCount', $this->translator->trans('admin.product_import_run.disabled', [], 'messages'))
            ->hideOnIndex();
        yield IntegerField::new('deletedCount', $this->translator->trans('admin.product_import_run.deleted', [], 'messages'))
            ->hideOnIndex();
        yield TextareaField::new('result', $this->translator->trans('admin.product_import_run.result', [], 'messages'));
        yield TextareaField::new('errorMessage', $this->translator->trans('admin.product_import_run.error', [], 'messages'))
            ->onlyOnDetail();
    }
}
