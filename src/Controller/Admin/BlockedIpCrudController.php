<?php

namespace App\Controller\Admin;

use App\Entity\BlockedIp;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Contracts\Translation\TranslatorInterface;

class BlockedIpCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return BlockedIp::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular($this->translator->trans('menu.link_blocked_ip_single', [], 'messages'))
            ->setEntityLabelInPlural($this->translator->trans('menu.link_blocked_ip', [], 'messages'))
            ->setDefaultSort(['created_at' => 'DESC'])
            ->setSearchFields(['ip', 'note'])
            ->setPaginatorPageSize(50)
            ->setFormOptions(['csrf_protection' => false]);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('ip', $this->translator->trans('admin.blocked_ip.ip', [], 'messages'))
            ->setHelp($this->translator->trans('admin.blocked_ip.ip_help', [], 'messages'));
        yield TextField::new('note', $this->translator->trans('admin.blocked_ip.note', [], 'messages'))
            ->setRequired(false);
        yield BooleanField::new('isActive', $this->translator->trans('admin.blocked_ip.is_active', [], 'messages'));
        yield DateTimeField::new('created_at', $this->translator->trans('admin.blocked_ip.created_at', [], 'messages'))
            ->hideOnForm();
        yield DateTimeField::new('updated_at', $this->translator->trans('admin.blocked_ip.updated_at', [], 'messages'))
            ->hideOnForm()
            ->hideOnIndex();
    }
}
