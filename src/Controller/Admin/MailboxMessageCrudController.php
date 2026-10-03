<?php

namespace App\Controller\Admin;

use App\Entity\MailboxMessage;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class MailboxMessageCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $urlGenerator,
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
            ->linkToUrl(fn (): string => $this->urlGenerator->generate('admin_mailbox_compose', ['_locale' => 'uk']))
            ->createAsGlobalAction()
            ->setIcon('fa fa-pen');

        $reply = Action::new('reply', $this->translator->trans('admin.mailbox_compose.reply', [], 'messages'))
            ->linkToUrl(function (MailboxMessage $message): string {
                return $this->urlGenerator->generate('admin_mailbox_compose', [
                    '_locale' => 'uk',
                    'to' => $message->getFromAddress(),
                    'subject' => $message->getSubject(),
                    'mailbox' => $message->getMailbox()?->getId(),
                ]);
            })
            ->setIcon('fa fa-reply');

        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $compose)
            ->add(Crud::PAGE_INDEX, $reply)
            ->add(Crud::PAGE_DETAIL, $reply);
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
        yield TextField::new('bodyHtml', $this->translator->trans('admin.mailbox_message.body_html', [], 'messages'))
            ->onlyOnDetail()
            ->setTemplatePath('admin/field/mailbox_body_html.html.twig')
            ->formatValue(static function (?string $value, MailboxMessage $message): string {
                if ($value !== null && trim($value) !== '') {
                    return $value;
                }

                $text = trim((string) $message->getBodyText());
                if ($text === '') {
                    return '';
                }

                return '<pre style="white-space:pre-wrap;font-family:inherit;margin:0">'
                    .htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    .'</pre>';
            });
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
