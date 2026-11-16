<?php

namespace App\PartnerProgram\Controller\Admin;

use App\PartnerProgram\Entity\PartnerQueryParameter;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Contracts\Translation\TranslatorInterface;

class PartnerQueryParameterCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return PartnerQueryParameter::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular($this->translator->trans('menu.link_partner_query_parameter_single', [], 'messages'))
            ->setEntityLabelInPlural($this->translator->trans('menu.link_partner_query_parameter', [], 'messages'))
            ->setDefaultSort(['name' => 'ASC'])
            ->setSearchFields(['name', 'note'])
            ->setPaginatorPageSize(50)
            ->setFormOptions(['csrf_protection' => false])
            ->setHelp('index', $this->translator->trans('admin.partner_query_parameter.help', [], 'messages'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('name', $this->translator->trans('admin.partner_query_parameter.name', [], 'messages'))
            ->setHelp($this->translator->trans('admin.partner_query_parameter.name_help', [], 'messages'));
        yield TextField::new('note', $this->translator->trans('admin.partner_query_parameter.note', [], 'messages'))
            ->setRequired(false);
        yield BooleanField::new('isActive', $this->translator->trans('admin.partner_query_parameter.is_active', [], 'messages'));
        yield DateTimeField::new('created_at', $this->translator->trans('admin.partner_query_parameter.created_at', [], 'messages'))
            ->hideOnForm();
        yield DateTimeField::new('updated_at', $this->translator->trans('admin.partner_query_parameter.updated_at', [], 'messages'))
            ->hideOnForm()
            ->hideOnIndex();
    }
}
