<?php

namespace App\Controller\Admin;

use App\Entity\MailboxMessage;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Contracts\Translation\TranslatorInterface;

class MailboxMessageCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly EntityManagerInterface $entityManager,
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

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('mailbox', $this->translator->trans('admin.mailbox_message.mailbox', [], 'messages'));
        yield DateTimeField::new('receivedAt', $this->translator->trans('admin.mailbox_message.received_at', [], 'messages'));
        yield TextField::new('fromDisplay', $this->translator->trans('admin.mailbox_message.from', [], 'messages'));
        yield TextField::new('subject', $this->translator->trans('admin.mailbox_message.subject', [], 'messages'));
        yield BooleanField::new('isSeen', $this->translator->trans('admin.mailbox_message.is_seen', [], 'messages'));
        yield BooleanField::new('hasAttachments', $this->translator->trans('admin.mailbox_message.has_attachments', [], 'messages'))
            ->hideOnIndex();
        yield TextareaField::new('bodyPreview', $this->translator->trans('admin.mailbox_message.preview', [], 'messages'))
            ->onlyOnDetail();
        yield TextareaField::new('bodyText', $this->translator->trans('admin.mailbox_message.body_text', [], 'messages'))
            ->onlyOnDetail();
        yield TextareaField::new('bodyHtml', $this->translator->trans('admin.mailbox_message.body_html', [], 'messages'))
            ->onlyOnDetail();
    }

    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        $responseParameters = parent::configureResponseParameters($responseParameters);

        if ($responseParameters->get('pageName') === Crud::PAGE_DETAIL) {
            $entityDto = $responseParameters->get('entity');
            $instance = \is_object($entityDto) && method_exists($entityDto, 'getInstance')
                ? $entityDto->getInstance()
                : null;
            if ($instance instanceof MailboxMessage && !$instance->isSeen()) {
                $instance->setIsSeen(true);
                $this->entityManager->flush();
            }
        }

        return $responseParameters;
    }
}
