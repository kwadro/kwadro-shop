<?php

namespace App\Controller\Admin;

use App\Entity\UserGroup;
use App\Service\User\UserGroupAssigner;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Contracts\Translation\TranslatorInterface;

class UserGroupCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly UserGroupAssigner $userGroupAssigner,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return UserGroup::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular($this->translator->trans('menu.link_user_group_single', [], 'messages'))
            ->setEntityLabelInPlural($this->translator->trans('menu.link_user_group', [], 'messages'))
            ->setDefaultSort(['name' => 'ASC'])
            ->setSearchFields(['name', 'code'])
            ->setFormOptions(['csrf_protection' => false])
            ->setHelp('index', $this->translator->trans('admin.user_group.help', [], 'messages'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('name', $this->translator->trans('admin.user_group.name', [], 'messages'));
        yield TextField::new('code', $this->translator->trans('admin.user_group.code', [], 'messages'))
            ->setHelp($this->translator->trans('admin.user_group.code_help', [], 'messages'));
        yield NumberField::new('discountPercent', $this->translator->trans('admin.user_group.discount_percent', [], 'messages'))
            ->setNumDecimals(2)
            ->setHelp($this->translator->trans('admin.user_group.discount_percent_help', [], 'messages'));
        yield AssociationField::new('roles', $this->translator->trans('admin.user_group.roles', [], 'messages'))
            ->setFormTypeOption('by_reference', false)
            ->setHelp($this->translator->trans('admin.user_group.roles_help', [], 'messages'));
        yield DateTimeField::new('created_at', $this->translator->trans('admin.user_group.created_at', [], 'messages'))
            ->hideOnForm();
        yield DateTimeField::new('updated_at', $this->translator->trans('admin.user_group.updated_at', [], 'messages'))
            ->hideOnForm()
            ->hideOnIndex();
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof UserGroup) {
            $this->userGroupAssigner->ensureUserRoleOnGroup($entityInstance);
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof UserGroup) {
            $this->userGroupAssigner->ensureUserRoleOnGroup($entityInstance);
        }

        parent::updateEntity($entityManager, $entityInstance);
    }
}
