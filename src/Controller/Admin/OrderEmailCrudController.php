<?php

namespace App\Controller\Admin;

use App\Entity\OrderEmail;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Contracts\Translation\TranslatorInterface;

class OrderEmailCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return OrderEmail::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular($this->translator->trans('menu.link_order_email_single', [], 'messages'))
            ->setEntityLabelInPlural($this->translator->trans('menu.link_order_email', [], 'messages'))
            ->setDefaultSort(['name' => 'ASC'])
            ->setFormOptions([
                'csrf_protection' => false,
            ]);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('name', $this->translator->trans('admin.order_email.name', [], 'messages'));
        yield TextField::new('code', $this->translator->trans('admin.order_email.code', [], 'messages'))
            ->setHelp($this->translator->trans('admin.order_email.code_help', [], 'messages'))
            ->setFormTypeOption('attr', ['placeholder' => 'order_created']);
        yield AssociationField::new('template', $this->translator->trans('admin.order_email.template', [], 'messages'))
            ->setRequired(true);
        yield AssociationField::new('sender', $this->translator->trans('admin.order_email.sender', [], 'messages'))
            ->setRequired(true);
    }
}
