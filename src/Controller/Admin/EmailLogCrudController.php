<?php

namespace App\Controller\Admin;

use App\Entity\EmailLog;
use App\Entity\EmailLogStatus;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use Symfony\Contracts\Translation\TranslatorInterface;

class EmailLogCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return EmailLog::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular($this->translator->trans('menu.link_email_log_single', [], 'messages'))
            ->setEntityLabelInPlural($this->translator->trans('menu.link_email_log', [], 'messages'))
            ->setDefaultSort(['created_at' => 'DESC'])
            ->setSearchFields(['event_code', 'recipient_email', 'subject', 'order_number', 'sender_email']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('status')->setChoices([
                $this->translator->trans('admin.email_log.status.sent', [], 'messages') => EmailLogStatus::Sent,
                $this->translator->trans('admin.email_log.status.failed', [], 'messages') => EmailLogStatus::Failed,
                $this->translator->trans('admin.email_log.status.skipped', [], 'messages') => EmailLogStatus::Skipped,
            ]));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield DateTimeField::new('created_at', $this->translator->trans('admin.email_log.created_at', [], 'messages'));
        yield ChoiceField::new('status', $this->translator->trans('admin.email_log.status_label', [], 'messages'))
            ->setChoices([
                $this->translator->trans('admin.email_log.status.sent', [], 'messages') => EmailLogStatus::Sent,
                $this->translator->trans('admin.email_log.status.failed', [], 'messages') => EmailLogStatus::Failed,
                $this->translator->trans('admin.email_log.status.skipped', [], 'messages') => EmailLogStatus::Skipped,
            ])
            ->renderAsBadges([
                EmailLogStatus::Sent->value => 'success',
                EmailLogStatus::Failed->value => 'danger',
                EmailLogStatus::Skipped->value => 'secondary',
            ]);
        yield TextField::new('event_code', $this->translator->trans('admin.email_log.event_code', [], 'messages'));
        yield AssociationField::new('order', $this->translator->trans('admin.email_log.order', [], 'messages'));
        yield TextField::new('orderNumber', $this->translator->trans('admin.email_log.order_number', [], 'messages'));
        yield AssociationField::new('orderEmail', $this->translator->trans('admin.email_log.configuration', [], 'messages'))
            ->hideOnIndex();
        yield TextField::new('recipientEmail', $this->translator->trans('admin.email_log.recipient', [], 'messages'));
        yield TextField::new('senderEmail', $this->translator->trans('admin.email_log.sender_email', [], 'messages'))
            ->hideOnIndex();
        yield TextField::new('senderName', $this->translator->trans('admin.email_log.sender_name', [], 'messages'))
            ->hideOnIndex();
        yield TextField::new('subject', $this->translator->trans('admin.email_log.subject', [], 'messages'));
        yield BooleanField::new('isHtml', $this->translator->trans('admin.email_log.is_html', [], 'messages'))
            ->hideOnIndex()
            ->renderAsSwitch(false);
        yield TextareaField::new('body', $this->translator->trans('admin.email_log.body', [], 'messages'))
            ->onlyOnDetail()
            ->setNumOfRows(20);
        yield TextareaField::new('errorMessage', $this->translator->trans('admin.email_log.error_message', [], 'messages'))
            ->onlyOnDetail()
            ->setNumOfRows(4);
        yield TextareaField::new('skipReason', $this->translator->trans('admin.email_log.skip_reason', [], 'messages'))
            ->onlyOnDetail()
            ->setNumOfRows(3);
        yield TextareaField::new('context')->onlyOnDetail()
            ->formatValue(static fn (?array $value): string => json_encode($value ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}');
    }
}
