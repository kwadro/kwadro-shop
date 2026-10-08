<?php

namespace App\Controller\Admin;

use App\Entity\ProductSearchSetting;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use Symfony\Contracts\Translation\TranslatorInterface;

class ProductSearchSettingCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return ProductSearchSetting::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('site', $this->translator->trans('admin.product_search_setting.site', [], 'messages'))
            ->setRequired(true);
        yield ChoiceField::new('searchFields', $this->translator->trans('admin.product_search_setting.search_fields', [], 'messages'))
            ->setChoices([
                $this->translator->trans('admin.product_search_setting.field_name', [], 'messages') => ProductSearchSetting::FIELD_NAME,
                $this->translator->trans('admin.product_search_setting.field_model', [], 'messages') => ProductSearchSetting::FIELD_MODEL,
                $this->translator->trans('admin.product_search_setting.field_sku', [], 'messages') => ProductSearchSetting::FIELD_SKU,
                $this->translator->trans('admin.product_search_setting.field_brand', [], 'messages') => ProductSearchSetting::FIELD_BRAND,
            ])
            ->allowMultipleChoices()
            ->renderExpanded()
            ->setRequired(true)
            ->setHelp($this->translator->trans('admin.product_search_setting.search_fields_help', [], 'messages'));
    }

    public function configureCrud(Crud $crud): Crud
    {
        $manage = $this->translator->trans('grud.manage', [], 'messages');
        $edit = $this->translator->trans('grud.edit', [], 'messages');
        $createNew = $this->translator->trans('grud.create_new', [], 'messages');
        $linkName = $this->translator->trans('menu.link_product_search_setting_single', [], 'messages');

        return $crud
            ->setPageTitle('index', sprintf('%s %s', $manage, $linkName))
            ->setPageTitle('edit', sprintf('%s %s', $edit, $linkName).' #%entity_id%')
            ->setPageTitle('new', sprintf('%s %s', $createNew, $linkName))
            ->setDefaultSort(['id' => 'ASC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        $addNew = $this->translator->trans('menu.link_new', [], 'messages');
        $linkName = $this->translator->trans('menu.link_product_search_setting_single', [], 'messages');

        return $actions
            ->update(
                Crud::PAGE_INDEX,
                Action::NEW,
                static fn (Action $action): Action => $action->setLabel(sprintf('%s %s', $addNew, $linkName)),
            );
    }
}
