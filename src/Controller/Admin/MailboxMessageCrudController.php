<?php

namespace App\Controller\Admin;

use App\Entity\MailboxMessage;
use App\Repository\MailboxMessageRepository;
use App\Service\Mail\Mailbox\MailboxAttachmentStorage;
use App\Service\Mail\Mailbox\MailboxImapSyncService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

class MailboxMessageCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly EntityManagerInterface $entityManager,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly MailboxImapSyncService $mailboxSyncService,
        private readonly MailboxMessageRepository $messageRepository,
        private readonly MailboxAttachmentStorage $attachmentStorage,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return MailboxMessage::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular($this->translator->trans('menu.link_mailbox_message_single', [], 'messages'))
            ->setEntityLabelInPlural($this->translator->trans('menu.link_mailbox_message', [], 'messages'))
            ->setDefaultSort(['receivedAt' => 'DESC'])
            ->setPaginatorPageSize(30)
            ->showEntityActionsInlined()
            ->setFormOptions(['csrf_protection' => false]);
    }

    public function configureAssets(Assets $assets): Assets
    {
        return $assets->addCssFile('lib/admin-mailbox-message.css');
    }

    public function configureActions(Actions $actions): Actions
    {
        $compose = Action::new('compose', $this->translator->trans('admin.mailbox_compose.title', [], 'messages'))
            ->linkToUrl(fn (): string => $this->adminUrlGenerator
                ->setController(MailboxComposeController::class)
                ->setAction('compose')
                ->generateUrl())
            ->createAsGlobalAction()
            ->setIcon('fa fa-pen');

        $reply = Action::new('reply', $this->translator->trans('admin.mailbox_compose.reply', [], 'messages'))
            ->linkToUrl(function (MailboxMessage $message): string {
                return $this->adminUrlGenerator
                    ->setController(MailboxComposeController::class)
                    ->setAction('compose')
                    ->set('to', $message->getFromAddress())
                    ->set('subject', $message->getSubject())
                    ->set('mailbox', $message->getMailbox()?->getId())
                    ->generateUrl();
            })
            ->setIcon('fa fa-reply');

        $batchMarkRead = Action::new('batchMarkRead', $this->translator->trans('admin.mailbox_message.batch_mark_read', [], 'messages'))
            ->linkToCrudAction('batchMarkRead')
            ->createAsBatchAction()
            ->asSuccessAction()
            ->setIcon('fa fa-envelope-open');

        $batchDelete = Action::new('batchDeleteMessages', $this->translator->trans('admin.mailbox_message.batch_delete', [], 'messages'))
            ->linkToCrudAction('batchDelete')
            ->createAsBatchAction()
            ->asDangerAction()
            ->setIcon('fa fa-trash');

        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::BATCH_DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $compose)
            ->add(Crud::PAGE_INDEX, $reply)
            ->add(Crud::PAGE_DETAIL, $reply)
            ->addBatchAction($batchMarkRead)
            ->addBatchAction($batchDelete);
    }

    public function configureFilters(Filters $filters): Filters
    {
        $groupChoices = $this->messageRepository->findDistinctMessageGroupChoices();

        $filters = $filters
            ->add(EntityFilter::new('mailbox', $this->translator->trans('admin.mailbox_message.mailbox', [], 'messages')));

        if ($groupChoices !== []) {
            $filters = $filters->add(
                ChoiceFilter::new('messageGroup', $this->translator->trans('admin.mailbox_message.group', [], 'messages'))
                    ->setChoices($groupChoices)
                    ->canSelectMultiple(),
            );
        }

        return $filters;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('mailbox', $this->translator->trans('admin.mailbox_message.mailbox', [], 'messages'));
        yield TextField::new('messageGroup', $this->translator->trans('admin.mailbox_message.group', [], 'messages'));
        yield DateTimeField::new('receivedAt', $this->translator->trans('admin.mailbox_message.received_at', [], 'messages'));
        yield TextField::new('fromDisplay', $this->translator->trans('admin.mailbox_message.from', [], 'messages'));
        yield TextField::new('subject', $this->translator->trans('admin.mailbox_message.subject', [], 'messages'));
        yield BooleanField::new('isSeen', $this->translator->trans('admin.mailbox_message.is_seen', [], 'messages'));
        yield BooleanField::new('hasAttachments', $this->translator->trans('admin.mailbox_message.has_attachments', [], 'messages'))
            ->hideOnDetail();
        yield Field::new('attachments', $this->translator->trans('admin.mailbox_message.attachments', [], 'messages'))
            ->onlyOnDetail()
            ->setTemplatePath('admin/field/mailbox_attachments.html.twig');
        yield TextField::new('bodyHtml', $this->translator->trans('admin.mailbox_message.body_html', [], 'messages'))
            ->onlyOnDetail()
            ->renderAsHtml()
            ->setTemplatePath('admin/field/mailbox_body_html.html.twig');
    }

    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        $responseParameters = parent::configureResponseParameters($responseParameters);

        if ($responseParameters->get('pageName') === Crud::PAGE_DETAIL) {
            $entityDto = $responseParameters->get('entity');
            $instance = \is_object($entityDto) && method_exists($entityDto, 'getInstance')
                ? $entityDto->getInstance()
                : null;
            if ($instance instanceof MailboxMessage) {
                if (!$instance->isSeen()) {
                    $instance->setIsSeen(true);
                    $this->entityManager->flush();
                }
                if ($instance->hasAttachments() && $instance->getAttachments() === []) {
                    $this->mailboxSyncService->ensureAttachments($instance);
                }
            }
        }

        return $responseParameters;
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof MailboxMessage) {
            $this->attachmentStorage->deleteFor($entityInstance);
        }

        parent::deleteEntity($entityManager, $entityInstance);
    }

    #[AdminRoute('/batch-mark-read', name: 'batch_mark_read')]
    public function batchMarkRead(AdminContext $context, BatchActionDto $batchActionDto): Response
    {
        if (!$this->isCsrfTokenValid(
            'ea-batch-action-batchMarkRead-'.$batchActionDto->getEntityFqcn(),
            $batchActionDto->getCsrfToken(),
        )) {
            return $this->redirectToRoute($context->getDashboardRouteName());
        }

        if ($batchActionDto->getEntityFqcn() !== MailboxMessage::class) {
            return $this->redirectToIndex();
        }

        $updated = 0;
        foreach ($batchActionDto->getEntityIds() as $entityId) {
            $message = $this->messageRepository->find($entityId);
            if (!$message instanceof MailboxMessage || $message->isSeen()) {
                continue;
            }
            $message->setIsSeen(true);
            ++$updated;
        }

        if ($updated > 0) {
            $this->entityManager->flush();
        }

        $this->addFlash(
            'success',
            $this->translator->trans('admin.mailbox_message.batch_mark_read_success', ['%count%' => $updated], 'messages'),
        );

        return $this->redirectToIndex();
    }

    #[AdminRoute('/batch-delete', name: 'batch_delete')]
    public function batchDelete(AdminContext $context, BatchActionDto $batchActionDto): Response
    {
        if (!$this->isCsrfTokenValid(
            'ea-batch-action-batchDeleteMessages-'.$batchActionDto->getEntityFqcn(),
            $batchActionDto->getCsrfToken(),
        )) {
            return $this->redirectToRoute($context->getDashboardRouteName());
        }

        if ($batchActionDto->getEntityFqcn() !== MailboxMessage::class) {
            return $this->redirectToIndex();
        }

        $deleted = 0;
        foreach ($batchActionDto->getEntityIds() as $entityId) {
            $message = $this->messageRepository->find($entityId);
            if (!$message instanceof MailboxMessage) {
                continue;
            }
            $this->attachmentStorage->deleteFor($message);
            $this->entityManager->remove($message);
            ++$deleted;
        }

        if ($deleted > 0) {
            $this->entityManager->flush();
        }

        $this->addFlash(
            'success',
            $this->translator->trans('admin.mailbox_message.batch_delete_success', ['%count%' => $deleted], 'messages'),
        );

        return $this->redirectToIndex();
    }

    private function redirectToIndex(): Response
    {
        return $this->redirect(
            $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction(Action::INDEX)
                ->generateUrl(),
        );
    }
}
