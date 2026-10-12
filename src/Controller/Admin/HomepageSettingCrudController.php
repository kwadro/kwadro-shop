<?php

namespace App\Controller\Admin;

use App\Entity\BlogArticle;
use App\Entity\HomepageSetting;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Contracts\Translation\TranslatorInterface;

class HomepageSettingCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
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
            ->hideOnIndex();
        yield AssociationField::new('products', $this->t('admin.homepage_setting.products'))
            ->autocomplete()
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
            ->setFormTypeOptions([
                'by_reference' => false,
                'query_builder' => static fn (EntityRepository $repository): QueryBuilder => $repository
                    ->createQueryBuilder('a')
                    ->leftJoin('a.site', 's')
                    ->leftJoin('a.locale', 'l')
                    ->addSelect('s', 'l')
                    ->orderBy('a.publishedAt', 'DESC')
                    ->addOrderBy('a.id', 'DESC'),
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
            ->setDefaultSort(['id' => 'ASC']);
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

    private function t(string $key): string
    {
        return $this->translator->trans($key, [], 'messages');
    }
}
