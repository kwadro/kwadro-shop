<?php

namespace App\Controller\Admin;

use App\Entity\EmailTemplate;
use App\Entity\EmailTemplateContext;
use App\Entity\EmailTemplateType;
use App\Entity\Site;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class EmailTemplateCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return EmailTemplate::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular($this->translator->trans('menu.link_email_template_single', [], 'messages'))
            ->setEntityLabelInPlural($this->translator->trans('menu.link_email_template', [], 'messages'))
            ->setDefaultSort(['name' => 'ASC'])
            ->setFormOptions([
                'csrf_protection' => false,
            ]);
    }

    public function createEntity(string $entityFqcn): object
    {
        $entity = new EmailTemplate();
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
        yield AssociationField::new('site', $this->translator->trans('admin.email_template.site', [], 'messages'))
            ->setRequired(true);
        yield TextField::new('name', $this->translator->trans('admin.email_template.name', [], 'messages'))
            ->setHelp($this->translator->trans('admin.email_template.name_help', [], 'messages'));
        yield TextField::new('subject', $this->translator->trans('admin.email_template.subject', [], 'messages'));
        yield ChoiceField::new('context', $this->translator->trans('admin.email_template.context', [], 'messages'))
            ->setFormType(EnumType::class)
            ->setFormTypeOption('class', EmailTemplateContext::class)
            ->setFormTypeOption('choice_label', fn (EmailTemplateContext $context): string => match ($context) {
                EmailTemplateContext::Order => $this->translator->trans('admin.email_template.context_order', [], 'messages'),
                EmailTemplateContext::User => $this->translator->trans('admin.email_template.context_user', [], 'messages'),
            })
            ->setHelp($this->translator->trans('admin.email_template.context_help', [], 'messages'))
            ->renderAsBadges([
                EmailTemplateContext::Order->value => 'info',
                EmailTemplateContext::User->value => 'success',
            ]);
        yield ChoiceField::new('type', $this->translator->trans('admin.email_template.type', [], 'messages'))
            ->setFormType(EnumType::class)
            ->setFormTypeOption('class', EmailTemplateType::class)
            ->setFormTypeOption('choice_label', fn (EmailTemplateType $type): string => match ($type) {
                EmailTemplateType::Html => $this->translator->trans('admin.email_template.type_html', [], 'messages'),
                EmailTemplateType::Text => $this->translator->trans('admin.email_template.type_text', [], 'messages'),
            })
            ->renderExpanded()
            ->renderAsNativeWidget()
            ->renderAsBadges([
                EmailTemplateType::Html->value => 'primary',
                EmailTemplateType::Text->value => 'secondary',
            ]);
        yield TextareaField::new('content', $this->translator->trans('admin.email_template.content', [], 'messages'))
            ->setHelp($this->translator->trans('admin.email_template.content_help', [], 'messages') . ' ' . $this->translator->trans('admin.email_template.section_help', [], 'messages'))
            ->hideOnIndex()
            ->setNumOfRows(18);
        yield TextareaField::new('additionalCss', $this->translator->trans('admin.email_template.additional_css', [], 'messages'))
            ->setHelp($this->translator->trans('admin.email_template.additional_css_help', [], 'messages'))
            ->hideOnIndex()
            ->setNumOfRows(10)
            ->setFormTypeOption('attr', ['class' => 'font-monospace']);
    }
}
