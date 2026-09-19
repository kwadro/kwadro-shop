<?php

namespace App\Controller\Admin;

use App\Entity\EmailTemplateSection;
use App\Entity\Site;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class EmailTemplateSectionCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return EmailTemplateSection::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular($this->translator->trans('menu.link_email_template_section_single', [], 'messages'))
            ->setEntityLabelInPlural($this->translator->trans('menu.link_email_template_section', [], 'messages'))
            ->setDefaultSort(['name' => 'ASC'])
            ->setFormOptions([
                'csrf_protection' => false,
            ]);
    }

    public function createEntity(string $entityFqcn): object
    {
        $entity = new EmailTemplateSection();
        $site = $this->container->get('doctrine')->getRepository(Site::class)->findOneBy([], ['id' => 'ASC']);
        if ($site instanceof Site) {
            $entity->setSite($site);
        }

        return $entity;
    }

    public function createNewFormBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormBuilderInterface
    {
        $context->getRequest()->getSession()->start();

        return parent::createNewFormBuilder($entityDto, $formOptions, $context);
    }

    public function createEditFormBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormBuilderInterface
    {
        $context->getRequest()->getSession()->start();

        return parent::createEditFormBuilder($entityDto, $formOptions, $context);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('site', $this->translator->trans('admin.email_template_section.site', [], 'messages'))
            ->setRequired(true);
        yield TextField::new('name', $this->translator->trans('admin.email_template_section.name', [], 'messages'))
            ->setHelp($this->translator->trans('admin.email_template_section.name_help', [], 'messages'));
        yield TextareaField::new('content', $this->translator->trans('admin.email_template_section.content', [], 'messages'))
            ->setHelp($this->translator->trans('admin.email_template_section.content_help', [], 'messages'))
            ->hideOnIndex()
            ->setNumOfRows(18);
    }
}
