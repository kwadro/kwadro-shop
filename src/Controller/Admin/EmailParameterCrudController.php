<?php

namespace App\Controller\Admin;

use App\Entity\EmailParameter;
use App\Entity\EmailParameterSection;
use App\Entity\EmailParameterType;
use App\Entity\Locale;
use App\Entity\Site;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class EmailParameterCrudController extends AbstractCrudController
{
    private const IMAGE_UPLOAD_DIR = 'public/uploads/images';
    private const IMAGE_BASE_PATH = '/uploads/images';

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return EmailParameter::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular($this->translator->trans('menu.link_email_parameter_single', [], 'messages'))
            ->setEntityLabelInPlural($this->translator->trans('menu.link_email_parameter', [], 'messages'))
            ->setDefaultSort(['site' => 'ASC', 'locale' => 'ASC', 'name' => 'ASC'])
            ->setSearchFields(['name', 'value'])
            ->setFormOptions([
                'csrf_protection' => false,
            ]);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('site'))
            ->add(EntityFilter::new('locale'));
    }

    public function createEntity(string $entityFqcn): object
    {
        $entity = new EmailParameter();
        $site = $this->container->get('doctrine')->getRepository(Site::class)->findOneBy([], ['id' => 'ASC']);
        if ($site instanceof Site) {
            $entity->setSite($site);
        }

        $locale = $this->container->get('doctrine')->getRepository(Locale::class)->findOneBy(['code' => 'uk'])
            ?? $this->container->get('doctrine')->getRepository(Locale::class)->findOneBy([], ['id' => 'ASC']);
        if ($locale instanceof Locale) {
            $entity->setLocale($locale);
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

    public function createIndexQueryBuilder(
        SearchDto $searchDto,
        EntityDto $entityDto,
        FieldCollection $fields,
        FilterCollection $filters,
    ): QueryBuilder {
        $queryBuilder = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        return $queryBuilder
            ->andWhere('entity.section = :generalSection')
            ->setParameter('generalSection', EmailParameterSection::General);
    }

    public function configureFields(string $pageName): iterable
    {
        $parameter = $this->getContext()?->getEntity()?->getInstance();
        $isImageParameter = $parameter instanceof EmailParameter && $parameter->getType() === EmailParameterType::Image;

        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('site', $this->translator->trans('admin.email_parameter.site', [], 'messages'))
            ->setRequired(true);
        yield AssociationField::new('locale', $this->translator->trans('admin.email_parameter.locale', [], 'messages'))
            ->setRequired(true)
            ->setFormTypeOption('choice_label', static fn (Locale $locale): string => sprintf(
                '%s (%s)',
                (string) ($locale->getName() ?? $locale->getCode()),
                (string) ($locale->getCode() ?? ''),
            ));
        yield TextField::new('name', $this->translator->trans('admin.email_parameter.name', [], 'messages'))
            ->setHelp($this->translator->trans('admin.email_parameter.name_help', [], 'messages'));
        yield ChoiceField::new('type', $this->translator->trans('admin.email_parameter.type', [], 'messages'))
            ->setFormType(EnumType::class)
            ->setFormTypeOption('class', EmailParameterType::class)
            ->setFormTypeOption('choice_label', fn (EmailParameterType $type): string => match ($type) {
                EmailParameterType::Text => $this->translator->trans('admin.email_parameter.type_text', [], 'messages'),
                EmailParameterType::Image => $this->translator->trans('admin.email_parameter.type_image', [], 'messages'),
            })
            ->renderAsBadges([
                EmailParameterType::Text->value => 'secondary',
                EmailParameterType::Image->value => 'info',
            ])
            ->setHelp($this->translator->trans('admin.email_parameter.type_help', [], 'messages'));

        if ($isImageParameter) {
            yield ImageField::new('value', $this->translator->trans('admin.email_parameter.value', [], 'messages'))
                ->setBasePath(self::IMAGE_BASE_PATH)
                ->setUploadDir(self::IMAGE_UPLOAD_DIR)
                ->setRequired(false)
                ->hideOnIndex()
                ->setHelp($this->translator->trans('admin.email_parameter.image_help', [], 'messages'));

            yield ImageField::new('value', $this->translator->trans('admin.email_parameter.value', [], 'messages'))
                ->setBasePath(self::IMAGE_BASE_PATH)
                ->onlyOnIndex();
        } else {
            yield TextField::new('value', $this->translator->trans('admin.email_parameter.value', [], 'messages'))
                ->setHelp($this->translator->trans('admin.email_parameter.value_help', [], 'messages'));
        }
    }
}
