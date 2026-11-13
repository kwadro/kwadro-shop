<?php

namespace App\Controller\Admin;

use App\Entity\ProductAttribute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use Symfony\Contracts\Translation\TranslatorInterface;

class ProductAttributeCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return ProductAttribute::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular($this->translator->trans('menu.link_product_attribute_single', [], 'messages'))
            ->setEntityLabelInPlural($this->translator->trans('menu.link_product_attribute', [], 'messages'))
            ->setDefaultSort(['name' => 'ASC'])
            ->setSearchFields(['name', 'slug']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->disable(Action::NEW, Action::DELETE);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(BooleanFilter::new('show_in_filter', $this->translator->trans('admin.product_attribute.show_in_filter', [], 'messages')));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('name', $this->translator->trans('admin.product_attribute.name', [], 'messages'));
        yield TextField::new('groups', $this->translator->trans('admin.product_attribute.groups', [], 'messages'))
            ->formatValue(static function (mixed $value, ProductAttribute $attribute): string {
                $names = [];
                foreach ($attribute->getGroups() as $group) {
                    $names[] = $group->getName();
                }

                return implode(', ', $names);
            })
            ->onlyOnIndex();
        yield TextField::new('slug', $this->translator->trans('admin.product_attribute.slug', [], 'messages'))
            ->hideOnForm();
        yield BooleanField::new('show_in_filter', $this->translator->trans('admin.product_attribute.show_in_filter', [], 'messages'))
            ->renderAsSwitch()
            ->setHelp($this->translator->trans('admin.product_attribute.show_in_filter_help', [], 'messages'));
    }
}
