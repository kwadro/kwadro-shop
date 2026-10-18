<?php

namespace App\Controller\Admin;

use App\Entity\ShopMegaMenuSetting;
use App\Form\Admin\ShopMegaMenuItemType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use Symfony\Contracts\Translation\TranslatorInterface;

class ShopMegaMenuSettingCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return ShopMegaMenuSetting::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        yield FormField::addFieldset($this->t('admin.shop_mega_menu.fieldset_general'));
        yield AssociationField::new('site', $this->t('admin.shop_mega_menu.site'))
            ->setRequired(true);
        yield AssociationField::new('locale', $this->t('admin.shop_mega_menu.locale'))
            ->setRequired(true);

        yield FormField::addFieldset($this->t('admin.shop_mega_menu.fieldset_mode'))
            ->setHelp($this->t('admin.shop_mega_menu.fieldset_mode_help'))
            ->hideOnIndex();
        yield ChoiceField::new('mode', $this->t('admin.shop_mega_menu.mode'))
            ->setChoices([
                $this->t('admin.shop_mega_menu.mode_auto') => ShopMegaMenuSetting::MODE_AUTO,
                $this->t('admin.shop_mega_menu.mode_manual') => ShopMegaMenuSetting::MODE_MANUAL,
            ])
            ->renderExpanded($pageName !== Crud::PAGE_INDEX)
            ->setHelp($this->t('admin.shop_mega_menu.mode_help'));

        yield FormField::addFieldset($this->t('admin.shop_mega_menu.fieldset_items'))
            ->setHelp($this->t('admin.shop_mega_menu.fieldset_items_help'));
        yield CollectionField::new('items', $this->t('admin.shop_mega_menu.items'))
            ->setEntryType(ShopMegaMenuItemType::class)
            ->setEntryIsComplex()
            ->renderExpanded()
            ->allowAdd()
            ->allowDelete()
            ->setFormTypeOptions([
                'by_reference' => false,
                'entry_options' => ['label' => false],
            ])
            ->setHelp($this->t('admin.shop_mega_menu.items_help'))
            ->hideOnIndex();
    }

    public function configureCrud(Crud $crud): Crud
    {
        $linkName = $this->t('menu.link_shop_mega_menu_single');

        return $crud
            ->setPageTitle('index', sprintf('%s %s', $this->t('grud.manage'), $linkName))
            ->setPageTitle('edit', sprintf('%s %s', $this->t('grud.edit'), $linkName).' #%entity_id%')
            ->setPageTitle('new', sprintf('%s %s', $this->t('grud.create_new'), $linkName))
            ->setDefaultSort(['id' => 'ASC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        $label = sprintf('%s %s', $this->t('menu.link_new'), $this->t('menu.link_shop_mega_menu_single'));

        return $actions
            ->update(
                Crud::PAGE_INDEX,
                Action::NEW,
                static fn (Action $action): Action => $action->setLabel($label),
            );
    }

    private function t(string $key): string
    {
        return $this->translator->trans($key, [], 'messages');
    }
}
