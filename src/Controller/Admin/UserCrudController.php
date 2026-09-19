<?php

namespace App\Controller\Admin;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class UserCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            EmailField::new('email')->setRequired(true),
            TextField::new('fullName', 'admin.user.full_name'),
            TextField::new('phone', 'admin.user.phone'),
            ChoiceField::new('roles')
                ->setChoices([
                    'Super Admin' => User::ROLE_SUPER_ADMIN,
                    'Admin' => User::ROLE_ADMIN,
                    'User' => User::ROLE_USER,
                ])
                ->allowMultipleChoices()
                ->renderAsBadges(),
            TextField::new('plainPassword')
                ->setFormType(PasswordType::class)
                ->onlyOnForms()
                ->setRequired(Crud::PAGE_NEW === $pageName)
                ->setHelp($this->translator->trans('user.password_help', [], 'messages')),
            CollectionField::new('savedShipmentAddresses', 'admin.user.saved_addresses')
                ->onlyOnDetail()
                ->allowAdd(false)
                ->allowDelete(false)
                ->useEntryCrudForm(ShipmentAddressCrudController::class),
        ];
    }

    public function configureCrud(Crud $crud): Crud
    {
        $linkName = $this->translator->trans('menu.link_user_single', [], 'messages');

        return $crud
            ->setEntityLabelInSingular($linkName)
            ->setEntityLabelInPlural($this->translator->trans('menu.link_user', [], 'messages'))
            ->setDefaultSort(['created_at' => 'DESC']);
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof User) {
            $this->hashPassword($entityInstance);
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof User) {
            $this->hashPassword($entityInstance);
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    private function hashPassword(User $user): void
    {
        $plainPassword = $user->getPlainPassword();
        if (null === $plainPassword || '' === $plainPassword) {
            return;
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $user->setPlainPassword(null);
    }
}
