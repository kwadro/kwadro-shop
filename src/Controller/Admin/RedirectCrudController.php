<?php

namespace App\Controller\Admin;

use App\Entity\Redirect;
use App\Entity\RedirectType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Contracts\Translation\TranslatorInterface;

class RedirectCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Redirect::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular($this->translator->trans('menu.link_redirect_single', [], 'messages'))
            ->setEntityLabelInPlural($this->translator->trans('menu.link_redirect', [], 'messages'))
            ->setDefaultSort(['type' => 'ASC', 'fromPath' => 'ASC'])
            ->setSearchFields(['fromPath', 'toPath'])
            ->setPaginatorPageSize(50)
            ->setFormOptions(['csrf_protection' => false]);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('type')->setChoices([
                $this->translator->trans('admin.redirect.type.base', [], 'messages') => RedirectType::Base->value,
                $this->translator->trans('admin.redirect.type.custom', [], 'messages') => RedirectType::Custom->value,
            ]))
            ->add('enabled');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield ChoiceField::new('type', $this->translator->trans('admin.redirect.type', [], 'messages'))
            ->setFormType(EnumType::class)
            ->setFormTypeOption('class', RedirectType::class)
            ->setChoices([
                $this->translator->trans('admin.redirect.type.base', [], 'messages') => RedirectType::Base,
                $this->translator->trans('admin.redirect.type.custom', [], 'messages') => RedirectType::Custom,
            ]);
        yield TextField::new('fromPath', $this->translator->trans('admin.redirect.from_path', [], 'messages'))
            ->setHelp($this->translator->trans('admin.redirect.from_path_help', [], 'messages'));
        yield TextField::new('toPath', $this->translator->trans('admin.redirect.to_path', [], 'messages'))
            ->setHelp($this->translator->trans('admin.redirect.to_path_help', [], 'messages'))
            ->setRequired(false);
        yield IntegerField::new('statusCode', $this->translator->trans('admin.redirect.status_code', [], 'messages'))
            ->setHelp('301 / 302 / 307 / 308');
        yield BooleanField::new('enabled', $this->translator->trans('admin.redirect.enabled', [], 'messages'));
    }
}
