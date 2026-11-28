<?php

namespace App\PartnerProgram\Controller\Admin;

use App\PartnerProgram\Entity\PartnerReferralHit;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Symfony\Contracts\Translation\TranslatorInterface;

class PartnerReferralHitCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return PartnerReferralHit::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular($this->translator->trans('menu.link_partner_referral_hit_single', [], 'messages'))
            ->setEntityLabelInPlural($this->translator->trans('menu.link_partner_referral_hit', [], 'messages'))
            ->setDefaultSort(['id' => 'DESC'])
            ->setSearchFields(['referralKey', 'visitorId', 'fbclid', 'gclid', 'utmCampaign', 'landingUrl'])
            ->setPaginatorPageSize(50)
            ->setHelp('index', $this->translator->trans('admin.partner_referral_hit.help', [], 'messages'));
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE, Action::BATCH_DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('referralKey', $this->translator->trans('admin.partner_referral_hit.referral_key', [], 'messages')))
            ->add(ChoiceFilter::new('adSource', $this->translator->trans('admin.partner_referral_hit.ad_source', [], 'messages'))
                ->setChoices([
                    $this->translator->trans('admin.partner_referral_hit.ad_source_facebook', [], 'messages') => 'facebook',
                    $this->translator->trans('admin.partner_referral_hit.ad_source_google_ads', [], 'messages') => 'google_ads',
                ]))
            ->add(TextFilter::new('utmSource', $this->translator->trans('admin.partner_referral_hit.utm_source', [], 'messages')))
            ->add(TextFilter::new('visitorId', $this->translator->trans('admin.partner_referral_hit.visitor_id', [], 'messages')));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield DateTimeField::new('created_at', $this->translator->trans('admin.partner_referral_hit.created_at', [], 'messages'));
        yield TextField::new('referralKey', $this->translator->trans('admin.partner_referral_hit.referral_key', [], 'messages'));
        yield TextField::new('param', $this->translator->trans('admin.partner_referral_hit.param', [], 'messages'))
            ->hideOnIndex();
        yield TextField::new('adSource', $this->translator->trans('admin.partner_referral_hit.ad_source', [], 'messages'));
        yield TextField::new('fbclid', $this->translator->trans('admin.partner_referral_hit.fbclid', [], 'messages'))
            ->hideOnIndex();
        yield TextField::new('gclid', $this->translator->trans('admin.partner_referral_hit.gclid', [], 'messages'))
            ->hideOnIndex();
        yield TextField::new('gbraid', $this->translator->trans('admin.partner_referral_hit.gbraid', [], 'messages'))
            ->hideOnIndex();
        yield TextField::new('wbraid', $this->translator->trans('admin.partner_referral_hit.wbraid', [], 'messages'))
            ->hideOnIndex();
        yield TextField::new('utmSource', $this->translator->trans('admin.partner_referral_hit.utm_source', [], 'messages'));
        yield TextField::new('utmMedium', $this->translator->trans('admin.partner_referral_hit.utm_medium', [], 'messages'))
            ->hideOnIndex();
        yield TextField::new('utmCampaign', $this->translator->trans('admin.partner_referral_hit.utm_campaign', [], 'messages'));
        yield TextField::new('visitorId', $this->translator->trans('admin.partner_referral_hit.visitor_id', [], 'messages'));
        yield TextField::new('ipAddress', $this->translator->trans('admin.partner_referral_hit.ip_address', [], 'messages'))
            ->hideOnIndex();
        yield IntegerField::new('advertisingLinkId', $this->translator->trans('admin.partner_referral_hit.advertising_link_id', [], 'messages'))
            ->hideOnIndex();
        yield TextareaField::new('landingUrl', $this->translator->trans('admin.partner_referral_hit.landing_url', [], 'messages'))
            ->hideOnIndex();
        yield TextField::new('fbc', $this->translator->trans('admin.partner_referral_hit.fbc', [], 'messages'))
            ->hideOnIndex();
        yield TextField::new('fbp', $this->translator->trans('admin.partner_referral_hit.fbp', [], 'messages'))
            ->hideOnIndex();
    }
}
