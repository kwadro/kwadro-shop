<?php

namespace App\Controller\Admin;

use App\Entity\EmailSender;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Contracts\Translation\TranslatorInterface;

class EmailSenderCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return EmailSender::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular($this->translator->trans('menu.link_email_sender_single', [], 'messages'))
            ->setEntityLabelInPlural($this->translator->trans('menu.link_email_sender', [], 'messages'))
            ->setDefaultSort(['name' => 'ASC'])
            ->setFormOptions([
                'csrf_protection' => false,
            ]);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('name', $this->translator->trans('admin.email_sender.name', [], 'messages'));
        yield EmailField::new('email', $this->translator->trans('admin.email_sender.email', [], 'messages'));
    }
}
