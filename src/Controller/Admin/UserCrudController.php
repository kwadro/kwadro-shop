<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Service\User\UserGroupAssigner;
use App\Service\User\UserInviteService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class UserCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UserGroupAssigner $userGroupAssigner,
        private readonly UserInviteService $userInviteService,
        private readonly RequestStack $requestStack,
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
            AssociationField::new('userGroup', $this->translator->trans('admin.user.user_group', [], 'messages'))
                ->setRequired(true)
                ->setHelp($this->translator->trans('admin.user.user_group_help', [], 'messages')),
            TextField::new('plainPassword')
                ->setFormType(PasswordType::class)
                ->onlyOnForms()
                ->setRequired(false)
                ->setHelp(
                    Crud::PAGE_NEW === $pageName
                        ? $this->translator->trans('admin.user.password_invite_help', [], 'messages')
                        : $this->translator->trans('admin.user.password_help', [], 'messages')
                ),
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
            ->setDefaultSort(['created_at' => 'DESC'])
            ->setHelp('new', $this->translator->trans('admin.user.invite_help', [], 'messages'));
    }

    public function createEntity(string $entityFqcn): ?object
    {
        /** @var User $user */
        $user = new $entityFqcn();
        $this->userGroupAssigner->assignClientGroup($user);

        return $user;
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof User) {
            if ($entityInstance->getUserGroup() === null) {
                $this->userGroupAssigner->assignClientGroup($entityInstance);
            }
            $hadPlainPassword = $this->hashPassword($entityInstance);
            parent::persistEntity($entityManager, $entityInstance);

            if (!$hadPlainPassword) {
                $locale = $this->requestStack->getCurrentRequest()?->getLocale() ?: 'ua';
                $this->userInviteService->issueAndSend($entityInstance, $locale);
                $this->addFlash(
                    'success',
                    $this->translator->trans('admin.user.invite_sent', [
                        '%email%' => (string) $entityInstance->getEmail(),
                    ], 'messages'),
                );
            }

            return;
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof User) {
            $this->hashPassword($entityInstance);
            if ($entityInstance->getUserGroup() !== null) {
                $entityInstance->setUserGroup($entityInstance->getUserGroup());
            }
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    /** @return bool True when a new password was hashed from the form. */
    private function hashPassword(User $user): bool
    {
        $plainPassword = $user->getPlainPassword();
        if (null === $plainPassword || '' === $plainPassword) {
            return false;
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $user->setPlainPassword(null);

        return true;
    }
}
