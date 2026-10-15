<?php

namespace App\Controller\Admin;

use App\Entity\AdminAuditLog;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Symfony\Contracts\Translation\TranslatorInterface;

class AdminAuditLogCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return AdminAuditLog::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular($this->translator->trans('menu.link_admin_audit_log_single', [], 'messages'))
            ->setEntityLabelInPlural($this->translator->trans('menu.link_admin_audit_log', [], 'messages'))
            ->setDefaultSort(['created_at' => 'DESC'])
            ->setSearchFields(['accountName', 'action', 'entityClass', 'entityId', 'entityLabel', 'ip'])
            ->setPaginatorPageSize(50);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('accountName'))
            ->add(ChoiceFilter::new('action')->setChoices([
                $this->translator->trans('admin.audit_log.action.login', [], 'messages') => AdminAuditLog::ACTION_LOGIN,
                $this->translator->trans('admin.audit_log.action.create', [], 'messages') => AdminAuditLog::ACTION_CREATE,
                $this->translator->trans('admin.audit_log.action.update', [], 'messages') => AdminAuditLog::ACTION_UPDATE,
                $this->translator->trans('admin.audit_log.action.delete', [], 'messages') => AdminAuditLog::ACTION_DELETE,
            ]))
            ->add(TextFilter::new('entityClass'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield DateTimeField::new('created_at', $this->translator->trans('admin.audit_log.created_at', [], 'messages'));
        yield TextField::new('accountName', $this->translator->trans('admin.audit_log.account_name', [], 'messages'));
        yield ChoiceField::new('action', $this->translator->trans('admin.audit_log.action_label', [], 'messages'))
            ->setChoices([
                $this->translator->trans('admin.audit_log.action.login', [], 'messages') => AdminAuditLog::ACTION_LOGIN,
                $this->translator->trans('admin.audit_log.action.create', [], 'messages') => AdminAuditLog::ACTION_CREATE,
                $this->translator->trans('admin.audit_log.action.update', [], 'messages') => AdminAuditLog::ACTION_UPDATE,
                $this->translator->trans('admin.audit_log.action.delete', [], 'messages') => AdminAuditLog::ACTION_DELETE,
            ])
            ->renderAsBadges([
                AdminAuditLog::ACTION_LOGIN => 'primary',
                AdminAuditLog::ACTION_CREATE => 'success',
                AdminAuditLog::ACTION_UPDATE => 'warning',
                AdminAuditLog::ACTION_DELETE => 'danger',
            ]);
        yield TextField::new('entityClass', $this->translator->trans('admin.audit_log.entity_class', [], 'messages'));
        yield TextField::new('entityId', $this->translator->trans('admin.audit_log.entity_id', [], 'messages'));
        yield TextField::new('entityLabel', $this->translator->trans('admin.audit_log.entity_label', [], 'messages'))
            ->hideOnIndex();
        yield TextField::new('ip', $this->translator->trans('admin.audit_log.ip', [], 'messages'));
        yield TextField::new('requestPath', $this->translator->trans('admin.audit_log.request_path', [], 'messages'))
            ->hideOnIndex();
        yield TextareaField::new('changes', $this->translator->trans('admin.audit_log.changes', [], 'messages'))
            ->onlyOnDetail()
            ->formatValue(static function (?array $value): string {
                if ($value === null || $value === []) {
                    return '—';
                }

                return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '—';
            })
            ->setNumOfRows(16);
    }
}
