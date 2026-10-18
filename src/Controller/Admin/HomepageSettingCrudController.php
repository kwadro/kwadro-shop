<?php

namespace App\Controller\Admin;

use App\Entity\BlogArticle;
use App\Entity\HomepageSetting;
use App\Form\Admin\HomepageCategoryItemType;
use App\Form\Admin\HomepageHeroSlideType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatorInterface;

class HomepageSettingCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return HomepageSetting::class;
    }

    public function configureFields(string $pageName): iterable
    {
        $sourceChoices = [
            $this->t('admin.homepage_setting.source_latest') => HomepageSetting::SOURCE_LATEST,
            $this->t('admin.homepage_setting.source_manual') => HomepageSetting::SOURCE_MANUAL,
        ];

        yield IdField::new('id')->hideOnForm();

        yield FormField::addFieldset($this->t('admin.homepage_setting.fieldset_general'));
        yield AssociationField::new('site', $this->t('admin.homepage_setting.site'))->setRequired(true);
        yield AssociationField::new('locale', $this->t('admin.homepage_setting.locale'))->setRequired(true);

        yield FormField::addFieldset($this->t('admin.homepage_setting.fieldset_hero'))
            ->setHelp($this->t('admin.homepage_setting.fieldset_hero_help'));
        yield BooleanField::new('heroEnabled', $this->t('admin.homepage_setting.enabled'))
            ->renderAsSwitch($pageName !== Crud::PAGE_INDEX);
        yield CollectionField::new('heroSlides', $this->t('admin.homepage_setting.hero_slides'))
            ->setEntryType(HomepageHeroSlideType::class)
            ->setEntryIsComplex()
            ->renderExpanded()
            ->allowAdd()
            ->allowDelete()
            ->setFormTypeOptions([
                'by_reference' => false,
                'entry_options' => ['label' => false],
            ])
            ->setHelp($this->t('admin.homepage_setting.hero_slides_help'))
            ->hideOnIndex();

        yield FormField::addFieldset($this->t('admin.homepage_setting.fieldset_categories'))
            ->setHelp($this->t('admin.homepage_setting.fieldset_categories_help'));
        yield BooleanField::new('categoriesEnabled', $this->t('admin.homepage_setting.enabled'))
            ->renderAsSwitch($pageName !== Crud::PAGE_INDEX);
        yield ChoiceField::new('categoriesSource', $this->t('admin.homepage_setting.source'))
            ->setChoices([
                $this->t('admin.homepage_setting.source_auto') => HomepageSetting::SOURCE_LATEST,
                $this->t('admin.homepage_setting.source_manual') => HomepageSetting::SOURCE_MANUAL,
            ])
            ->renderExpanded()
            ->setHelp($this->t('admin.homepage_setting.categories_source_help'));
        yield IntegerField::new('categoriesPerRow', $this->t('admin.homepage_setting.categories_per_row'))
            ->setFormTypeOption('attr', ['min' => 1, 'max' => HomepageSetting::MAX_CATEGORIES_PER_ROW])
            ->setHelp($this->t('admin.homepage_setting.categories_per_row_help'))
            ->hideOnIndex();
        yield CollectionField::new('categoryItems', $this->t('admin.homepage_setting.categories'))
            ->setEntryType(HomepageCategoryItemType::class)
            ->setEntryIsComplex()
            ->renderExpanded()
            ->allowAdd()
            ->allowDelete()
            ->setFormTypeOptions([
                'by_reference' => false,
                'entry_options' => ['label' => false],
            ])
            ->setHelp($this->t('admin.homepage_setting.categories_help'))
            ->hideOnIndex();

        yield FormField::addFieldset($this->t('admin.homepage_setting.fieldset_products'))
            ->setHelp($this->t('admin.homepage_setting.fieldset_products_help'));
        yield BooleanField::new('productsEnabled', $this->t('admin.homepage_setting.enabled'))
            ->renderAsSwitch($pageName !== Crud::PAGE_INDEX);
        yield TextField::new('productsTitle', $this->t('admin.homepage_setting.title'))
            ->setHelp($this->t('admin.homepage_setting.title_help'))
            ->hideOnIndex();
        yield TextareaField::new('productsLead', $this->t('admin.homepage_setting.lead'))
            ->setHelp($this->t('admin.homepage_setting.lead_help'))
            ->hideOnIndex();
        yield ChoiceField::new('productsSource', $this->t('admin.homepage_setting.source'))
            ->setChoices($sourceChoices)
            ->renderExpanded()
            ->setHelp($this->t('admin.homepage_setting.source_help'));
        yield IntegerField::new('productsLimit', $this->t('admin.homepage_setting.limit'))
            ->setFormTypeOption('attr', ['min' => 1, 'max' => HomepageSetting::MAX_PRODUCTS_LIMIT])
            ->setHelp($this->t('admin.homepage_setting.limit_help'))
            ->hideOnIndex();
        yield AssociationField::new('products', $this->t('admin.homepage_setting.products'))
            ->autocomplete()
            ->setCrudController(ProductCrudController::class)
            ->setFormTypeOption('by_reference', false)
            ->setHelp($this->t('admin.homepage_setting.products_help'))
            ->hideOnIndex();

        yield FormField::addFieldset($this->t('admin.homepage_setting.fieldset_articles'))
            ->setHelp($this->t('admin.homepage_setting.fieldset_articles_help'));
        yield BooleanField::new('articlesEnabled', $this->t('admin.homepage_setting.enabled'))
            ->renderAsSwitch($pageName !== Crud::PAGE_INDEX);
        yield TextField::new('articlesTitle', $this->t('admin.homepage_setting.title'))
            ->setHelp($this->t('admin.homepage_setting.title_help'))
            ->hideOnIndex();
        yield TextareaField::new('articlesLead', $this->t('admin.homepage_setting.lead'))
            ->setHelp($this->t('admin.homepage_setting.lead_help'))
            ->hideOnIndex();
        yield ChoiceField::new('articlesSource', $this->t('admin.homepage_setting.source'))
            ->setChoices($sourceChoices)
            ->renderExpanded()
            ->setHelp($this->t('admin.homepage_setting.source_help'));
        yield IntegerField::new('articlesLimit', $this->t('admin.homepage_setting.limit'))
            ->setFormTypeOption('attr', ['min' => 1, 'max' => HomepageSetting::MAX_ARTICLES_LIMIT])
            ->hideOnIndex();
        yield AssociationField::new('articles', $this->t('admin.homepage_setting.articles'))
            ->autocomplete()
            ->setCrudController(BlogArticleCrudController::class)
            ->setQueryBuilder(static function (QueryBuilder $qb): QueryBuilder {
                $alias = $qb->getRootAliases()[0] ?? 'entity';

                return $qb
                    ->leftJoin(sprintf('%s.site', $alias), 's')
                    ->leftJoin(sprintf('%s.locale', $alias), 'l')
                    ->addSelect('s', 'l')
                    ->orderBy(sprintf('%s.publishedAt', $alias), 'DESC')
                    ->addOrderBy(sprintf('%s.id', $alias), 'DESC');
            })
            ->setFormTypeOptions([
                'by_reference' => false,
                'choice_label' => static fn (BlogArticle $article): string => sprintf(
                    '%s [%s / %s]',
                    $article->getTitle(),
                    $article->getSite()?->getDomain() ?? '-',
                    $article->getLocale()?->getCode() ?? '-',
                ),
            ])
            ->setHelp($this->t('admin.homepage_setting.articles_help'))
            ->hideOnIndex();
    }

    public function configureCrud(Crud $crud): Crud
    {
        $linkName = $this->t('menu.link_homepage_setting_single');

        return $crud
            ->setPageTitle('index', sprintf('%s %s', $this->t('grud.manage'), $linkName))
            ->setPageTitle('edit', sprintf('%s %s', $this->t('grud.edit'), $linkName).' #%entity_id%')
            ->setPageTitle('new', sprintf('%s %s', $this->t('grud.create_new'), $linkName))
            ->setDefaultSort(['id' => 'ASC'])
            ->setFormThemes([
                'admin/form/homepage_hero_slide.html.twig',
                '@EasyAdmin/crud/form_theme.html.twig',
            ]);
    }

    public function configureAssets(Assets $assets): Assets
    {
        return $assets
            ->addCssFile('lib/admin-homepage-hero.css')
            ->addJsFile('js/admin-homepage-hero.js');
    }

    public function configureActions(Actions $actions): Actions
    {
        $label = sprintf('%s %s', $this->t('menu.link_new'), $this->t('menu.link_homepage_setting_single'));

        return $actions
            ->update(
                Crud::PAGE_INDEX,
                Action::NEW,
                static fn (Action $action): Action => $action->setLabel($label),
            );
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof HomepageSetting) {
            $entityInstance->syncHeroSlideImages($this->projectDir);
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof HomepageSetting) {
            $entityInstance->syncHeroSlideImages($this->projectDir);
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    private function t(string $key): string
    {
        return $this->translator->trans($key, [], 'messages');
    }
}
