<?php

namespace App\Controller\Admin;

use App\Entity\ProductImport;
use App\Entity\ProductImportMode;
use App\Service\ProductImport\SivitekProductImportService;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Contracts\Translation\TranslatorInterface;

class ProductImportCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly SivitekProductImportService $sivitekProductImportService,
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return ProductImport::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular($this->translator->trans('menu.link_product_import_single', [], 'messages'))
            ->setEntityLabelInPlural($this->translator->trans('menu.link_product_import', [], 'messages'))
            ->setDefaultSort(['name' => 'ASC'])
            ->setSearchFields(['name', 'code', 'file_path']);
    }

    public function configureActions(Actions $actions): Actions
    {
        $run = Action::new('runImport', $this->translator->trans('admin.product_import.run', [], 'messages'))
            ->linkToCrudAction('runImport')
            ->setIcon('fa fa-play')
            ->displayIf(static fn (?ProductImport $import): bool => $import?->isActive() === true);

        return $actions
            ->add(Crud::PAGE_INDEX, $run)
            ->add(Crud::PAGE_DETAIL, $run)
            ->add(Crud::PAGE_EDIT, $run)
            ->disable(Action::NEW, Action::DELETE);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('name', $this->translator->trans('admin.product_import.name', [], 'messages'));
        yield TextField::new('code', $this->translator->trans('admin.product_import.code', [], 'messages'))
            ->setFormTypeOption('disabled', true);
        yield ChoiceField::new('mode', $this->translator->trans('admin.product_import.mode', [], 'messages'))
            ->setFormType(EnumType::class)
            ->setFormTypeOption('class', ProductImportMode::class)
            ->setFormTypeOption('choice_label', static fn (ProductImportMode $mode): string => $mode->label())
            ->setHelp($this->translator->trans('admin.product_import.mode_help', [], 'messages'))
            ->onlyOnForms();
        yield TextField::new('mode', $this->translator->trans('admin.product_import.mode', [], 'messages'))
            ->formatValue(static fn (mixed $value): string => $value instanceof ProductImportMode
                ? $value->label()
                : (ProductImportMode::tryFrom((string) $value)?->label() ?? (string) $value))
            ->hideOnForm();
        yield TextField::new('filePath', $this->translator->trans('admin.product_import.file_path', [], 'messages'))
            ->setHelp($this->translator->trans('admin.product_import.file_path_help', [], 'messages'));
        yield TextareaField::new('disabledSkusText', $this->translator->trans('admin.product_import.disabled_skus', [], 'messages'))
            ->setHelp($this->translator->trans('admin.product_import.disabled_skus_help', [], 'messages'))
            ->setNumOfRows(8)
            ->hideOnIndex();
        yield BooleanField::new('active', $this->translator->trans('admin.product_import.active', [], 'messages'))
            ->renderAsSwitch(false);
        yield DateTimeField::new('lastRunAt', $this->translator->trans('admin.product_import.last_run_at', [], 'messages'))
            ->onlyOnIndex();
        yield TextareaField::new('lastResult', $this->translator->trans('admin.product_import.last_result', [], 'messages'))
            ->onlyOnIndex()
            ->formatValue(static fn (?string $value): string => $value ?? '—');
        yield DateTimeField::new('lastRunAt', $this->translator->trans('admin.product_import.last_run_at', [], 'messages'))
            ->onlyOnDetail();
        yield TextareaField::new('lastResult', $this->translator->trans('admin.product_import.last_result', [], 'messages'))
            ->onlyOnDetail()
            ->setNumOfRows(4);
    }

    #[AdminRoute('/{entityId:import.id}/run', name: 'run')]
    public function runImport(ProductImport $import): RedirectResponse
    {
        if (!$import->isActive()) {
            $this->addFlash('danger', $this->translator->trans('admin.product_import.run_inactive', [], 'messages'));

            return $this->redirect(
                $this->adminUrlGenerator
                    ->setController(self::class)
                    ->setAction(Action::INDEX)
                    ->generateUrl()
            );
        }

        set_time_limit(0);

        $run = match ($import->getCode()) {
            ProductImport::CODE_SIVITEK => $this->sivitekProductImportService->run($import, false),
            default => null,
        };

        if ($run === null) {
            $this->addFlash('danger', $this->translator->trans('admin.product_import.run_unsupported', [], 'messages'));
        } elseif ($run->getStatus()->value === 'failed') {
            $this->addFlash('danger', $run->getErrorMessage() ?? $run->getResult() ?? 'Import failed');
        } else {
            $this->addFlash('success', $run->getResult() ?? $this->translator->trans('admin.product_import.run_ok', [], 'messages'));
        }

        return $this->redirect(
            $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction(Action::DETAIL)
                ->setEntityId($import->getId())
                ->generateUrl()
        );
    }
}
