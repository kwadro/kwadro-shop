<?php

namespace App\Controller\Admin;

use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;


// @GENERATE USE START
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;
use App\Controller\Admin\FooterSettingCrudController;
use App\Controller\Admin\FooterTranslationCrudController;
use App\Controller\Admin\HeaderSettingCrudController;
use App\Controller\Admin\HeaderTranslationCrudController;
use App\Controller\Admin\LocaleCrudController;
use App\Controller\Admin\MegaMenuSettingCrudController;
use App\Controller\Admin\MegaMenuTranslationCrudController;
use App\Controller\Admin\MegaMenuTypeCrudController;
use App\Controller\Admin\PopularsearchCrudController;
use App\Controller\Admin\SeoSettingCrudController;
use App\Controller\Admin\SeoSettingsTranslationCrudController;
use App\Controller\Admin\SiteCrudController;
use App\Controller\Admin\CartCrudController;
use App\Controller\Admin\CartItemCrudController;
use App\Controller\Admin\OrderCrudController;
use App\Controller\Admin\OrderItemCrudController;
use App\Controller\Admin\PaymentCrudController;
use App\Controller\Admin\ProductCrudController;
use App\Controller\Admin\ProductOfferCrudController;
use App\Controller\Admin\ShipmentAddressCrudController;
use App\Controller\Admin\SupplierCrudController;
use App\Controller\Admin\UserCrudController;
use App\Controller\Admin\EmailTemplateCrudController;
use App\Controller\Admin\EmailTemplateSectionCrudController;
use App\Controller\Admin\EmailLogCrudController;
use App\Controller\Admin\BankAccountCrudController;
use App\Controller\Admin\EmailParameterCrudController;
use App\Controller\Admin\EmailSenderCrudController;
use App\Controller\Admin\OrderEmailCrudController;
// @GENERATE USE FINISH

#[AdminDashboard(routePath: '/admin/{_locale}', routeName: 'admin')]
#[IsGranted('ROLE_SUPER_ADMIN')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly ChartBuilderInterface $chartBuilder,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function index(): Response
    {
        $chart = $this->chartBuilder->createChart(Chart::TYPE_LINE);
        // ...set chart data and options somehow

        return $this->render('admin/my-dashboard.html.twig', [
            'chart' => $chart
        ]);

        //return parent::index();

        // Option 1. You can make your dashboard redirect to some common page of your backend
        //
        // 1.1) If you have enabled the "pretty URLs" feature:
        // return $this->redirectToRoute('admin_user_index');
        //
        // 1.2) Same example but using the "ugly URLs" that were used in previous EasyAdmin versions:
        // $adminUrlGenerator = $this->container->get(AdminUrlGenerator::class);
        // return $this->redirect($adminUrlGenerator->setController(OneOfYourCrudController::class)->generateUrl());

        // Option 2. You can make your dashboard redirect to different pages depending on the user
        //
        // if ('jane' === $this->getUser()->getUsername()) {
        //     return $this->redirectToRoute('...');
        // }

        // Option 3. You can render some custom template to display a proper dashboard with widgets, etc.
        // (tip: it's easier if your template extends from @EasyAdmin/page/content.html.twig)
        //
        // return $this->render('some/path/my-dashboard.html.twig');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle($this->translator->trans('catalog_recipe', [], 'messages'))
            // set this option if you prefer the page content to span the entire
            // browser width, instead of the default design which sets a max width
            ->renderContentMaximized()
            //->renderSidebarMinimized()
            ->disableDarkMode()
            ->setDefaultColorScheme('dark')
            ->generateRelativeUrls()
            ->setLocales(['uk'])
            ;
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard($this->translator->trans('menu.dashboard', [], 'messages'), 'fa fa-home');
        yield MenuItem::section($this->translator->trans('menu.group_catalog', [], 'messages'));
        yield MenuItem::linkTo(ProductCrudController::class, $this->translator->trans('menu.link_product', [], 'messages'), 'fas fa-box');
        yield MenuItem::linkTo(SupplierCrudController::class, $this->translator->trans('menu.link_supplier', [], 'messages'), 'fas fa-truck');
        yield MenuItem::linkTo(ProductOfferCrudController::class, $this->translator->trans('menu.link_product_offer', [], 'messages'), 'fas fa-tags');
        yield MenuItem::linkTo(CartCrudController::class, $this->translator->trans('menu.link_cart', [], 'messages'), 'fas fa-shopping-cart');
        yield MenuItem::linkTo(CartItemCrudController::class, $this->translator->trans('menu.link_cart_item', [], 'messages'), 'fas fa-list');
        yield MenuItem::linkTo(OrderCrudController::class, $this->translator->trans('menu.link_order', [], 'messages'), 'fas fa-receipt');
        yield MenuItem::linkTo(OrderItemCrudController::class, $this->translator->trans('menu.link_order_item', [], 'messages'), 'fas fa-list-ul');
        yield MenuItem::linkTo(PaymentCrudController::class, $this->translator->trans('menu.link_payment', [], 'messages'), 'fas fa-credit-card');
        yield MenuItem::linkTo(ShipmentAddressCrudController::class, $this->translator->trans('menu.link_shipment_address', [], 'messages'), 'fas fa-map-marker-alt');
        yield MenuItem::section($this->translator->trans('menu.group_email', [], 'messages'));
        yield MenuItem::linkTo(EmailTemplateCrudController::class, $this->translator->trans('menu.link_email_template', [], 'messages'), 'fas fa-envelope');
        yield MenuItem::linkTo(EmailTemplateSectionCrudController::class, $this->translator->trans('menu.link_email_template_section', [], 'messages'), 'fas fa-layer-group');
        yield MenuItem::linkTo(EmailParameterCrudController::class, $this->translator->trans('menu.link_email_parameter', [], 'messages'), 'fas fa-sliders-h');
        yield MenuItem::linkTo(EmailSenderCrudController::class, $this->translator->trans('menu.link_email_sender', [], 'messages'), 'fas fa-paper-plane');
        yield MenuItem::linkTo(OrderEmailCrudController::class, $this->translator->trans('menu.link_order_email', [], 'messages'), 'fas fa-envelope-open-text');
        yield MenuItem::linkTo(EmailLogCrudController::class, $this->translator->trans('menu.link_email_log', [], 'messages'), 'fas fa-inbox');
        yield MenuItem::linkToRoute(
            $this->translator->trans('menu.link_email_template_test', [], 'messages'),
            'fas fa-vial',
            'admin_email_template_test',
            ['_locale' => 'uk'],
        );
        yield MenuItem::section($this->translator->trans('menu.group_bank', [], 'messages'));
        yield MenuItem::linkTo(BankAccountCrudController::class, $this->translator->trans('menu.link_bank_account', [], 'messages'), 'fas fa-university');
        yield MenuItem::section($this->translator->trans('menu.users', [], 'messages'));
        yield MenuItem::linkTo(UserCrudController::class, $this->translator->trans('menu.link_user', [], 'messages'), 'fas fa-users');
        // @GENERATE MENU START
yield MenuItem::section($this->translator->trans('menu.group_setting', [], 'messages'));
yield MenuItem::linkTo(FootersettingCrudController::class,$this->translator->trans('menu.link_footersetting', [], 'messages'), 'fas fa-list' );
yield MenuItem::linkTo(FootertranslationCrudController::class,$this->translator->trans('menu.link_footertranslation', [], 'messages'), 'fas fa-list' );
yield MenuItem::linkTo(HeadersettingCrudController::class,$this->translator->trans('menu.link_headersetting', [], 'messages'), 'fas fa-list' );
yield MenuItem::linkTo(HeadertranslationCrudController::class,$this->translator->trans('menu.link_headertranslation', [], 'messages'), 'fas fa-list' );
yield MenuItem::linkTo(LocaleCrudController::class,$this->translator->trans('menu.link_locale', [], 'messages'), 'fas fa-list' );
yield MenuItem::linkTo(MegamenusettingCrudController::class,$this->translator->trans('menu.link_megamenusetting', [], 'messages'), 'fas fa-list' );
yield MenuItem::linkTo(MegamenutranslationCrudController::class,$this->translator->trans('menu.link_megamenutranslation', [], 'messages'), 'fas fa-list' );
yield MenuItem::linkTo(MegamenutypeCrudController::class,$this->translator->trans('menu.link_megamenutype', [], 'messages'), 'fas fa-list' );
yield MenuItem::linkTo(PopularsearchCrudController::class,$this->translator->trans('menu.link_popularsearch', [], 'messages'), 'fas fa-list' );
yield MenuItem::linkTo(SeosettingCrudController::class,$this->translator->trans('menu.link_seosetting', [], 'messages'), 'fas fa-list' );
yield MenuItem::linkTo(SeosettingstranslationCrudController::class,$this->translator->trans('menu.link_seosettingstranslation', [], 'messages'), 'fas fa-list' );
yield MenuItem::linkTo(SiteCrudController::class,$this->translator->trans('menu.link_site', [], 'messages'), 'fas fa-list' );
// @GENERATE MENU FINISH
    }


    public function configureAssets(): Assets
    {
        return Assets::new()
            ->addCssFile('build/admin-css.css')
            ->addJsFile('build/admin.js')
            ->addCssFile('https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css')
            ->addJsFile('https://cdn.jsdelivr.net/npm/flatpickr')
            ->addJsFile('lib/admin-datepicker.js')
            ->addCssFile('https://cdn.jsdelivr.net/npm/jodit@4.2.27/es2021/jodit.min.css')
            ->addCssFile('lib/admin-html-editor.css')
            ->addJsFile('https://cdn.jsdelivr.net/npm/jodit@4.2.27/es2021/jodit.min.js')
            ->addJsFile('lib/admin-html-editor.js');
    }
}
