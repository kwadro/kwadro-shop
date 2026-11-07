<?php

namespace App\Controller\Admin;

use App\Entity\ProductAttributeGroup;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Symfony\Contracts\Translation\TranslatorInterface;

class ProductAttributeGroupCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return ProductAttributeGroup::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular($this->translator->trans('menu.link_product_attribute_group_single', [], 'messages'))
            ->setEntityLabelInPlural($this->translator->trans('menu.link_product_attribute_group', [], 'messages'))
            ->setDefaultSort(['name' => 'ASC'])
            ->setSearchFields(['name', 'slug']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->disable(Action::NEW, Action::DELETE);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add(EntityFilter::new('category', $this->translator->trans('admin.product_attribute_group.category', [], 'messages')));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('name', $this->translator->trans('admin.product_attribute_group.name', [], 'messages'));
        yield TextField::new('slug', $this->translator->trans('admin.product_attribute_group.slug', [], 'messages'))
            ->hideOnForm();
        yield AssociationField::new('category', $this->translator->trans('admin.product_attribute_group.category', [], 'messages'));
        yield IntegerField::new('external_category_id', $this->translator->trans('admin.product_attribute_group.external_category_id', [], 'messages'))
            ->hideOnForm();
        yield IntegerField::new('position', $this->translator->trans('admin.product_attribute_group.position', [], 'messages'));
    }
}
