<?php
namespace App\Controller\Admin;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use App\Entity\ShopDeliveryMethod;
use App\Entity\ShopPaymentMethod;
use App\Entity\Site;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use Symfony\Contracts\Translation\TranslatorInterface;



class SiteCrudController extends AbstractCrudController
{
    public function __construct(
        private TranslatorInterface $translator
    ) {
    }
    public static function getEntityFqcn(): string { return Site::class; }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('code')->setRequired(true),
            TextField::new('domain')->setRequired(true),
            AssociationField::new('featuredProduct', $this->translator->trans('admin.site.featured_product', [], 'messages'))
                ->setRequired(false)
                ->setHelp($this->translator->trans('admin.site.featured_product_help', [], 'messages')),
            MoneyField::new('courierDeliveryCost', $this->translator->trans('admin.site.courier_delivery_cost', [], 'messages'))
                ->setCurrency('UAH')
                ->setStoredAsCents(false)
                ->setHelp($this->translator->trans('admin.site.courier_delivery_cost_help', [], 'messages'))
                ->hideOnIndex(),
            NumberField::new('codStandardPercent', $this->translator->trans('admin.site.cod_standard_percent', [], 'messages'))
                ->setNumDecimals(2)
                ->setHelp($this->translator->trans('admin.site.cod_standard_percent_help', [], 'messages'))
                ->hideOnIndex(),
            NumberField::new('codNovapayPercent', $this->translator->trans('admin.site.cod_novapay_percent', [], 'messages'))
                ->setNumDecimals(2)
                ->setHelp($this->translator->trans('admin.site.cod_novapay_percent_help', [], 'messages'))
                ->hideOnIndex(),
            MoneyField::new('codPrepaymentAmount', $this->translator->trans('admin.site.cod_prepayment_amount', [], 'messages'))
                ->setCurrency('UAH')
                ->setStoredAsCents(false)
                ->setHelp($this->translator->trans('admin.site.cod_prepayment_amount_help', [], 'messages'))
                ->hideOnIndex(),
            TextareaField::new('codCommissionNoticeUk', $this->translator->trans('admin.site.cod_commission_notice_uk', [], 'messages'))
                ->setHelp($this->translator->trans('admin.site.cod_commission_notice_help', [], 'messages'))
                ->hideOnIndex(),
            TextareaField::new('codCommissionNoticeEn', $this->translator->trans('admin.site.cod_commission_notice_en', [], 'messages'))
                ->setHelp($this->translator->trans('admin.site.cod_commission_notice_help', [], 'messages'))
                ->hideOnIndex(),
            ChoiceField::new('activePaymentMethods', $this->translator->trans('admin.site.active_payment_methods', [], 'messages'))
                ->setChoices([
                    $this->translator->trans('admin.site.payment.on_delivery', [], 'messages') => ShopPaymentMethod::OnDelivery,
                    $this->translator->trans('admin.site.payment.privatbank', [], 'messages') => ShopPaymentMethod::Privatbank,
                    $this->translator->trans('admin.site.payment.monobank', [], 'messages') => ShopPaymentMethod::Monobank,
                ])
                ->allowMultipleChoices()
                ->renderExpanded(false)
                ->setHelp($this->translator->trans('admin.site.active_payment_methods_help', [], 'messages'))
                ->hideOnIndex(),
            ChoiceField::new('activeDeliveryMethods', $this->translator->trans('admin.site.active_delivery_methods', [], 'messages'))
                ->setChoices([
                    $this->translator->trans('admin.site.delivery.courier', [], 'messages') => ShopDeliveryMethod::Courier,
                    $this->translator->trans('admin.site.delivery.np_branch', [], 'messages') => ShopDeliveryMethod::NovaPoshtaBranch,
                    $this->translator->trans('admin.site.delivery.np_postomat', [], 'messages') => ShopDeliveryMethod::NovaPoshtaPostomat,
                ])
                ->allowMultipleChoices()
                ->renderExpanded(false)
                ->setHelp($this->translator->trans('admin.site.active_delivery_methods_help', [], 'messages'))
                ->hideOnIndex(),
            AssociationField::new('headersettingsites')->setFormTypeOption('by_reference', false)->hideOnForm()->hideOnIndex(),
            AssociationField::new('seosettingsites')->setFormTypeOption('by_reference', false)->hideOnForm()->hideOnIndex(),
            AssociationField::new('footersettingsites')->setFormTypeOption('by_reference', false)->hideOnForm()->hideOnIndex(),
            AssociationField::new('megamenusites')->setFormTypeOption('by_reference', false)->hideOnForm()->hideOnIndex(),
            AssociationField::new('popularsearchsites')->setFormTypeOption('by_reference', false)->hideOnForm()->hideOnIndex(),
        ];
    }


    public function configureCrud(Crud $crud): Crud
    {
         $manage = $this->translator->trans('grud.manage', [], 'messages');
         $edit = $this->translator->trans('grud.edit', [], 'messages');
         $createNew = $this->translator->trans('grud.create_new', [], 'messages');
         $linkName = $this->translator->trans('menu.link_site_single', [], 'messages');
         return $crud
            ->setFormThemes([
               '@EasyAdmin/crud/form_theme.html.twig',
               'admin/fields.html.twig'
            ])
            ->setPageTitle('index', sprintf('%s %s',$manage,$linkName)) // For the list view
            ->setPageTitle('edit', sprintf('%s %s',$edit,$linkName).' id : %entity_id%') // For the edit form
            ->setPageTitle('new', sprintf('%s %s',$createNew,$linkName))
            ->setDefaultSort(['created_at' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
          $export = Action::new('exportCsv', $this->translator->trans('menu.link_export_csv', [], 'messages'))
                      ->linkToRoute('admin_export_csv', ['entity' => 'Site'])
                      ->createAsGlobalAction()
                      ->addCssClass('btn btn-secondary')
                      ->setIcon('fa fa-file-csv');
          $import = Action::new('import', $this->translator->trans('menu.link_import_csv', [], 'messages'))
                       ->linkToRoute('admin_import', ['entity' => 'Site'])
                       ->createAsGlobalAction()
                       ->addCssClass('btn btn-secondary')
                       ->setIcon('fa fa-upload');
          $addNew = $this->translator->trans('menu.link_new', [], 'messages');
          $linkName = $this->translator->trans('menu.link_site_single', [], 'messages');
          return $actions
             ->add(Crud::PAGE_INDEX, $export)
             ->add(Crud::PAGE_INDEX, $import)
             ->update(Crud::PAGE_INDEX, Action::NEW,
                         fn (Action $action) =>
                             $action->setLabel(sprintf('%s %s',$addNew,$linkName))
                     );
    //    return $actions
    //        ->setPermission(Action::DETAIL, 'ROLE_USER');
    }
}
