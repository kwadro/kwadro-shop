<?php

namespace App\Controller\Admin;

use App\Entity\ProductAttributeGroup;
use App\Entity\ProductAttributeGroupItem;
use App\Form\Admin\ProductAttributeGroupItemType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
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
        yield IntegerField::new('position', $this->translator->trans('admin.product_attribute_group.position', [], 'messages'));
        yield CollectionField::new('items', $this->translator->trans('admin.product_attribute_group.attributes', [], 'messages'))
            ->setEntryType(ProductAttributeGroupItemType::class)
            ->setEntryIsComplex()
            ->allowAdd(false)
            ->allowDelete(false)
            ->setFormTypeOptions([
                'entry_options' => ['label' => false],
            ])
            ->setHelp($this->translator->trans('admin.product_attribute_group.attributes_help', [], 'messages'))
            ->formatValue(static function (mixed $value, ProductAttributeGroup $group): string {
                $names = [];
                foreach ($group->getItems() as $item) {
                    if (!$item instanceof ProductAttributeGroupItem) {
                        continue;
                    }
                    $name = $item->getAttribute()?->getName() ?? '';
                    if ($name === '') {
                        continue;
                    }
                    $names[] = $item->isDeleted() ? $name.' [deleted]' : $name;
                }

                return implode(', ', $names);
            })
            ->hideOnIndex();
        yield IntegerField::new('external_category_id', $this->translator->trans('admin.product_attribute_group.external_category_id', [], 'messages'))
            ->hideOnForm();
    }
}
