<?php

namespace App\Controller\Admin;

use App\Entity\ProductFeedSetting;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ProductFeedSettingCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return ProductFeedSetting::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular($this->t('menu.link_product_feed_setting_single'))
            ->setEntityLabelInPlural($this->t('menu.link_product_feed_setting'))
            ->setDefaultSort(['id' => 'ASC'])
            ->setPageTitle(Crud::PAGE_INDEX, $this->t('menu.group_product_feed'))
            ->setHelp(Crud::PAGE_EDIT, $this->feedHelp())
            ->setHelp(Crud::PAGE_NEW, $this->feedHelp())
            ->setHelp(Crud::PAGE_DETAIL, $this->feedHelp());
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        yield FormField::addFieldset($this->t('admin.product_feed.fieldset_general'));
        yield AssociationField::new('site', $this->t('admin.product_feed.site'))
            ->setRequired(true);
        yield BooleanField::new('enabled', $this->t('admin.product_feed.enabled'))
            ->setHelp($this->t('admin.product_feed.enabled_help'));
        yield TextField::new('accessToken', $this->t('admin.product_feed.access_token'))
            ->setHelp($this->t('admin.product_feed.access_token_help'))
            ->hideOnIndex();
        yield ChoiceField::new('format', $this->t('admin.product_feed.format'))
            ->setChoices([
                'CSV' => ProductFeedSetting::FORMAT_CSV,
                'TSV' => ProductFeedSetting::FORMAT_TSV,
            ]);
        yield TextField::new('localeCode', $this->t('admin.product_feed.locale_code'))
            ->setHelp($this->t('admin.product_feed.locale_code_help'));
        yield TextField::new('currency', $this->t('admin.product_feed.currency'))
            ->setHelp($this->t('admin.product_feed.currency_help'));

        yield FormField::addFieldset($this->t('admin.product_feed.fieldset_catalog'));
        yield TextField::new('defaultBrand', $this->t('admin.product_feed.default_brand'))
            ->setHelp($this->t('admin.product_feed.default_brand_help'));
        yield ChoiceField::new('itemCondition', $this->t('admin.product_feed.item_condition'))
            ->setChoices([
                'new' => ProductFeedSetting::CONDITION_NEW,
                'refurbished' => ProductFeedSetting::CONDITION_REFURBISHED,
                'used' => ProductFeedSetting::CONDITION_USED,
            ]);
        yield TextField::new('defaultGoogleProductCategory', $this->t('admin.product_feed.default_google_product_category'))
            ->setHelp($this->t('admin.product_feed.default_google_product_category_help'))
            ->hideOnIndex();
        yield TextField::new('defaultFbProductCategory', $this->t('admin.product_feed.default_fb_product_category'))
            ->setHelp($this->t('admin.product_feed.default_fb_product_category_help'))
            ->hideOnIndex();
    }

    private function feedHelp(): string
    {
        $url = $this->urlGenerator->generate(
            'shop_facebook_product_feed',
            ['_format' => 'csv'],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        return $this->t('admin.product_feed.help', ['%url%' => $url]);
    }

    /** @param array<string, string> $parameters */
    private function t(string $key, array $parameters = []): string
    {
        return $this->translator->trans($key, $parameters, 'messages');
    }
}
