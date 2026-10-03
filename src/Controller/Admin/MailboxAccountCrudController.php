<?php

namespace App\Controller\Admin;

use App\Entity\MailboxAccount;
use App\Service\Mail\Mailbox\MailboxCredentialCipher;
use App\Service\Mail\Mailbox\MailboxImapSyncService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Contracts\Translation\TranslatorInterface;

class MailboxAccountCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly MailboxCredentialCipher $cipher,
        private readonly MailboxImapSyncService $syncService,
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return MailboxAccount::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular($this->translator->trans('menu.link_mailbox_account_single', [], 'messages'))
            ->setEntityLabelInPlural($this->translator->trans('menu.link_mailbox_account', [], 'messages'))
            ->setDefaultSort(['name' => 'ASC'])
            ->setFormOptions(['csrf_protection' => false]);
    }

    public function configureActions(Actions $actions): Actions
    {
        $sync = Action::new('syncMailbox', $this->translator->trans('admin.mailbox_account.sync', [], 'messages'))
            ->linkToCrudAction('syncMailbox')
            ->setIcon('fa fa-sync');

        return $actions
            ->add(Crud::PAGE_INDEX, $sync)
            ->add(Crud::PAGE_DETAIL, $sync)
            ->add(Crud::PAGE_EDIT, $sync);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('name', $this->translator->trans('admin.mailbox_account.name', [], 'messages'));
        yield EmailField::new('email', $this->translator->trans('admin.mailbox_account.email', [], 'messages'));
        yield AssociationField::new('site', $this->translator->trans('admin.mailbox_account.site', [], 'messages'))
            ->setRequired(false)
            ->hideOnIndex();
        yield TextField::new('username', $this->translator->trans('admin.mailbox_account.username', [], 'messages'))
            ->hideOnIndex();
        yield TextField::new('plainPassword', $this->translator->trans('admin.mailbox_account.password', [], 'messages'))
            ->setFormTypeOption('attr', [
                'type' => 'password',
                'autocomplete' => 'new-password',
            ])
            ->setHelp($this->translator->trans('admin.mailbox_account.password_help', [], 'messages'))
            ->onlyOnForms()
            ->setRequired($pageName === Crud::PAGE_NEW);

        yield TextField::new('imapHost', $this->translator->trans('admin.mailbox_account.imap_host', [], 'messages'))
            ->hideOnIndex();
        yield IntegerField::new('imapPort', $this->translator->trans('admin.mailbox_account.imap_port', [], 'messages'))
            ->hideOnIndex();
        yield ChoiceField::new('imapEncryption', $this->translator->trans('admin.mailbox_account.imap_encryption', [], 'messages'))
            ->setChoices([
                'SSL' => 'ssl',
                'TLS' => 'tls',
                'None' => 'none',
            ])
            ->hideOnIndex();

        yield TextField::new('smtpHost', $this->translator->trans('admin.mailbox_account.smtp_host', [], 'messages'))
            ->hideOnIndex();
        yield IntegerField::new('smtpPort', $this->translator->trans('admin.mailbox_account.smtp_port', [], 'messages'))
            ->hideOnIndex();
        yield ChoiceField::new('smtpEncryption', $this->translator->trans('admin.mailbox_account.smtp_encryption', [], 'messages'))
            ->setChoices([
                'SSL' => 'ssl',
                'TLS' => 'tls',
                'None' => 'none',
            ])
            ->hideOnIndex();

        yield TextareaField::new('allowedFromEmails', $this->translator->trans('admin.mailbox_account.allowed_from_emails', [], 'messages'))
            ->setHelp($this->translator->trans('admin.mailbox_account.allowed_from_emails_help', [], 'messages'))
            ->setFormTypeOption('attr', [
                'data-no-html-editor' => '1',
                'rows' => 5,
                'placeholder' => "info@example.com|Info\nbank@example.com|Bank\nplain@example.com",
            ])
            ->hideOnIndex();

        yield BooleanField::new('isActive', $this->translator->trans('admin.mailbox_account.is_active', [], 'messages'));
        yield DateTimeField::new('lastSyncedAt', $this->translator->trans('admin.mailbox_account.last_synced_at', [], 'messages'))
            ->hideOnForm();
        yield TextareaField::new('lastSyncError', $this->translator->trans('admin.mailbox_account.last_sync_error', [], 'messages'))
            ->hideOnForm()
            ->hideOnIndex();
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof MailboxAccount) {
            return;
        }

        $this->encryptPassword($entityInstance, true);
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof MailboxAccount) {
            return;
        }

        $this->encryptPassword($entityInstance, false);
        parent::updateEntity($entityManager, $entityInstance);
    }

    #[AdminRoute('/{entityId:mailboxAccount.id}/sync', name: 'sync')]
    public function syncMailbox(MailboxAccount $mailboxAccount): RedirectResponse
    {
        $result = $this->syncService->sync($mailboxAccount);
        if ($result['error'] !== null) {
            $this->addFlash('danger', $result['error']);
        } else {
            $this->addFlash('success', $this->translator->trans('admin.mailbox_account.sync_ok', [
                '%imported%' => $result['imported'],
                '%updated%' => $result['updated'],
            ], 'messages'));
        }

        return $this->redirect(
            $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction(Action::INDEX)
                ->generateUrl()
        );
    }

    private function encryptPassword(MailboxAccount $account, bool $required): void
    {
        $plain = trim((string) $account->getPlainPassword());
        $account->setPlainPassword(null);

        if ($plain !== '') {
            $account->setPasswordEncrypted($this->cipher->encrypt($plain));

            return;
        }

        if ($required && $account->getPasswordEncrypted() === '') {
            throw new \RuntimeException($this->translator->trans('admin.mailbox_account.password_required', [], 'messages'));
        }
    }
}
