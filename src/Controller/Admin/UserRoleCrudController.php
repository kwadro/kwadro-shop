<?php

namespace App\Controller\Admin;

use App\Entity\UserRole;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Contracts\Translation\TranslatorInterface;

class UserRoleCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return UserRole::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular($this->translator->trans('menu.link_user_role_single', [], 'messages'))
            ->setEntityLabelInPlural($this->translator->trans('menu.link_user_role', [], 'messages'))
            ->setDefaultSort(['name' => 'ASC'])
            ->setSearchFields(['name', 'code'])
            ->setFormOptions(['csrf_protection' => false]);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('name', $this->translator->trans('admin.user_role.name', [], 'messages'));
        yield TextField::new('code', $this->translator->trans('admin.user_role.code', [], 'messages'))
            ->setHelp($this->translator->trans('admin.user_role.code_help', [], 'messages'));
        yield DateTimeField::new('created_at', $this->translator->trans('admin.user_role.created_at', [], 'messages'))
            ->hideOnForm();
        yield DateTimeField::new('updated_at', $this->translator->trans('admin.user_role.updated_at', [], 'messages'))
            ->hideOnForm()
            ->hideOnIndex();
    }
}
