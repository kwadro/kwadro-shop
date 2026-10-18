<?php

namespace App\Controller\Admin;

use App\Entity\HomepageHeroSlide;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Contracts\Translation\TranslatorInterface;

class HomepageHeroSlideCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return HomepageHeroSlide::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular($this->t('admin.homepage_setting.hero_slide'))
            ->setEntityLabelInPlural($this->t('admin.homepage_setting.hero_slides'))
            ->setDefaultSort(['position' => 'ASC', 'id' => 'ASC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->disable(Action::INDEX, Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield BooleanField::new('enabled', $this->t('admin.homepage_setting.hero_slide_enabled'))
            ->renderAsSwitch(true);
        yield IntegerField::new('position', $this->t('admin.homepage_setting.hero_slide_position'))
            ->setFormTypeOption('attr', ['min' => 0, 'max' => 999])
            ->setHelp($this->t('admin.homepage_setting.hero_slide_position_help'));
        yield TextField::new('brand', $this->t('admin.homepage_setting.hero_brand'))
            ->setHelp($this->t('admin.homepage_setting.hero_brand_help'))
            ->setRequired(false);
        yield TextField::new('title', $this->t('admin.homepage_setting.hero_title'))
            ->setHelp($this->t('admin.homepage_setting.hero_title_help'))
            ->setRequired(false);
        yield TextareaField::new('lead', $this->t('admin.homepage_setting.hero_lead'))
            ->setHelp($this->t('admin.homepage_setting.hero_lead_help'))
            ->setRequired(false);
        yield ImageField::new('image', $this->t('admin.homepage_setting.hero_image'))
            ->setBasePath('/uploads/images')
            ->setUploadDir('public/uploads/images')
            ->setRequired(false)
            ->setHelp($this->t('admin.homepage_setting.hero_image_help'));
        yield TextField::new('primaryCtaLabel', $this->t('admin.homepage_setting.hero_primary_cta_label'))
            ->setRequired(false);
        yield TextField::new('primaryCtaUrl', $this->t('admin.homepage_setting.hero_primary_cta_url'))
            ->setHelp($this->t('admin.homepage_setting.hero_cta_url_help'))
            ->setRequired(false);
        yield TextField::new('secondaryCtaLabel', $this->t('admin.homepage_setting.hero_secondary_cta_label'))
            ->setRequired(false);
        yield TextField::new('secondaryCtaUrl', $this->t('admin.homepage_setting.hero_secondary_cta_url'))
            ->setHelp($this->t('admin.homepage_setting.hero_cta_url_help'))
            ->setRequired(false);
    }

    private function t(string $key): string
    {
        return $this->translator->trans($key, [], 'messages');
    }
}
