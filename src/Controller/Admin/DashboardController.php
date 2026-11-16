<?php

namespace App\Controller\Admin;

use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Asset;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;


// @GENERATE USE START
use Symfony\Contracts\Translation\TranslatorInterface;
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
use App\Controller\Admin\ProductAttributeCrudController;
use App\Controller\Admin\ProductAttributeGroupCrudController;
use App\Controller\Admin\ProductCrudController;
use App\Controller\Admin\CategoryCrudController;
use App\Controller\Admin\ProductOfferCrudController;
use App\Controller\Admin\ProductImportCrudController;
use App\Controller\Admin\ProductImportRunCrudController;
use App\Controller\Admin\ProductFeedSettingCrudController;
use App\Controller\Admin\ProductSearchSettingCrudController;
use App\Controller\Admin\ImageEditorController;
use App\Controller\Admin\ShipmentAddressCrudController;
use App\Controller\Admin\SupplierCrudController;
use App\Controller\Admin\UserCrudController;
use App\Controller\Admin\UserGroupCrudController;
use App\Controller\Admin\UserRoleCrudController;
use App\Controller\Admin\EmailTemplateCrudController;
use App\Controller\Admin\EmailTemplateSectionCrudController;
use App\Controller\Admin\EmailLogCrudController;
use App\Controller\Admin\BankAccountCrudController;
use App\Controller\Admin\EmailParameterCrudController;
use App\Controller\Admin\EmailSenderCrudController;
use App\Controller\Admin\OrderEmailCrudController;
use App\Controller\Admin\MailboxAccountCrudController;
use App\Controller\Admin\MailboxComposeController;
use App\Controller\Admin\MailboxMessageCrudController;
use App\Controller\Admin\MailboxSentMessageCrudController;
use App\Controller\Admin\MailboxConversationController;
use App\AiContentAgent\Controller\Admin\ContentAgentLogCrudController;
use App\AiContentAgent\Controller\Admin\ContentSourceCrudController;
use App\AiContentAgent\Controller\Admin\ContentTopicCrudController;
use App\AiContentAgent\Controller\Admin\PublicationQueueCrudController;
use App\Controller\Admin\BlogArticleCrudController;
use App\Controller\Admin\BlogCategoryCrudController;
use App\Controller\Admin\RequestListCrudController;
use App\Controller\Admin\AdminAuditLogCrudController;
use App\Controller\Admin\BlockedIpCrudController;
use App\Controller\Admin\BotUserAgentMarkerCrudController;
use App\Controller\Admin\RedirectCrudController;
use App\Controller\Admin\ReportRequestTrafficController;
use App\Controller\Admin\ShopMegaMenuSettingCrudController;
use App\PartnerProgram\Controller\Admin\PartnerQueryParameterCrudController;
// @GENERATE USE FINISH

#[AdminDashboard(routePath: '/admin/{_locale}', routeName: 'admin')]
#[IsGranted('ROLE_SUPER_ADMIN')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function index(): Response
    {
        return $this->render('admin/my-dashboard.html.twig');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle($this->translator->trans('admin.dashboard_title', [], 'messages'))
            // set this option if you prefer the page content to span the entire
            // browser width, instead of the default design which sets a max width
            ->renderContentMaximized()
            //->renderSidebarMinimized()
            ->disableDarkMode()
            ->setDefaultColorScheme('dark')
            ->generateRelativeUrls()
            // Do not call setLocales(['ua']): Symfony Intl has no "ua" language
            // (Ukrainian is "uk"). URL locale stays "ua"; admin has no locale switcher.
            ;
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard($this->translator->trans('menu.dashboard', [], 'messages'), 'fa fa-home');

        yield MenuItem::subMenu($this->translator->trans('menu.group_catalog', [], 'messages'), 'fas fa-boxes')->setSubItems([
            MenuItem::linkTo(ProductCrudController::class, $this->translator->trans('menu.link_product', [], 'messages'), 'fas fa-box'),
            MenuItem::linkTo(CategoryCrudController::class, $this->translator->trans('menu.link_category', [], 'messages'), 'fas fa-folder'),
            MenuItem::linkTo(ProductAttributeGroupCrudController::class, $this->translator->trans('menu.link_product_attribute_group', [], 'messages'), 'fas fa-layer-group'),
            MenuItem::linkTo(ProductAttributeCrudController::class, $this->translator->trans('menu.link_product_attribute', [], 'messages'), 'fas fa-sliders-h'),
            MenuItem::linkTo(SupplierCrudController::class, $this->translator->trans('menu.link_supplier', [], 'messages'), 'fas fa-truck'),
            MenuItem::linkTo(ProductOfferCrudController::class, $this->translator->trans('menu.link_product_offer', [], 'messages'), 'fas fa-tags'),
            MenuItem::linkTo(ProductImportCrudController::class, $this->translator->trans('menu.link_product_import', [], 'messages'), 'fas fa-file-import'),
            MenuItem::linkTo(ProductImportRunCrudController::class, $this->translator->trans('menu.link_product_import_run', [], 'messages'), 'fas fa-history'),
        ]);

        yield MenuItem::subMenu($this->translator->trans('menu.group_product_search', [], 'messages'), 'fas fa-search')->setSubItems([
            MenuItem::linkTo(ProductSearchSettingCrudController::class, $this->translator->trans('menu.link_product_search_setting', [], 'messages'), 'fas fa-search'),
        ]);

        yield MenuItem::subMenu($this->translator->trans('menu.group_product_feed', [], 'messages'), 'fas fa-rss')->setSubItems([
            MenuItem::linkTo(ProductFeedSettingCrudController::class, $this->translator->trans('menu.link_product_feed_setting', [], 'messages'), 'fas fa-rss'),
        ]);

        yield MenuItem::subMenu($this->translator->trans('menu.group_blog', [], 'messages'), 'fas fa-newspaper')->setSubItems([
            MenuItem::linkTo(BlogArticleCrudController::class, $this->translator->trans('menu.link_blog_article', [], 'messages'), 'fas fa-newspaper'),
            MenuItem::linkTo(BlogCategoryCrudController::class, $this->translator->trans('menu.link_blog_category', [], 'messages'), 'fas fa-folder-open'),
        ]);

        yield MenuItem::subMenu('AI Content', 'fas fa-robot')->setSubItems([
            MenuItem::linkTo(ContentSourceCrudController::class, 'Source history', 'fas fa-link'),
            MenuItem::linkTo(ContentTopicCrudController::class, 'Topics', 'fas fa-check-double'),
            MenuItem::linkTo(PublicationQueueCrudController::class, 'Publication queue', 'fas fa-clock'),
            MenuItem::linkTo(ContentAgentLogCrudController::class, 'Agent log', 'fas fa-clipboard-list'),
        ]);

        yield MenuItem::subMenu($this->translator->trans('menu.group_orders', [], 'messages'), 'fas fa-receipt')->setSubItems([
            MenuItem::linkTo(CartCrudController::class, $this->translator->trans('menu.link_cart', [], 'messages'), 'fas fa-shopping-cart'),
            MenuItem::linkTo(CartItemCrudController::class, $this->translator->trans('menu.link_cart_item', [], 'messages'), 'fas fa-list'),
            MenuItem::linkTo(OrderCrudController::class, $this->translator->trans('menu.link_order', [], 'messages'), 'fas fa-receipt'),
            MenuItem::linkTo(OrderItemCrudController::class, $this->translator->trans('menu.link_order_item', [], 'messages'), 'fas fa-list-ul'),
            MenuItem::linkTo(PaymentCrudController::class, $this->translator->trans('menu.link_payment', [], 'messages'), 'fas fa-credit-card'),
            MenuItem::linkTo(ShipmentAddressCrudController::class, $this->translator->trans('menu.link_shipment_address', [], 'messages'), 'fas fa-map-marker-alt'),
        ]);

        yield MenuItem::subMenu($this->translator->trans('menu.group_partner_program', [], 'messages'), 'fas fa-handshake')->setSubItems([
            MenuItem::linkTo(PartnerQueryParameterCrudController::class, $this->translator->trans('menu.link_partner_query_parameter', [], 'messages'), 'fas fa-question'),
        ]);

        yield MenuItem::subMenu($this->translator->trans('menu.group_marketing', [], 'messages'), 'fas fa-bullhorn')->setSubItems([
            MenuItem::linkToRoute(
                $this->translator->trans('menu.link_promo_image_editor', [], 'messages'),
                'fas fa-image',
                'admin_promo_image_editor',
                ['_locale' => 'ua'],
            ),
            MenuItem::linkToRoute(
                $this->translator->trans('menu.link_image_editor', [], 'messages'),
                'fas fa-crop-alt',
                'admin_image_editor',
                ['_locale' => 'ua'],
            ),
            MenuItem::linkToRoute(
                $this->translator->trans('menu.link_background_remover', [], 'messages'),
                'fas fa-eraser',
                'admin_background_remover',
                ['_locale' => 'ua'],
            ),
            MenuItem::linkToRoute(
                $this->translator->trans('menu.link_image_transform', [], 'messages'),
                'fas fa-expand',
                'admin_image_transform',
                ['_locale' => 'ua'],
            ),
            MenuItem::linkToRoute(
                $this->translator->trans('menu.link_media_library', [], 'messages'),
                'fas fa-photo-video',
                'admin_media_library',
                ['_locale' => 'ua'],
            ),
            MenuItem::linkToRoute(
                $this->translator->trans('menu.link_shape_library', [], 'messages'),
                'fas fa-shapes',
                'admin_shape_library',
                ['_locale' => 'ua'],
            ),
            MenuItem::linkToRoute(
                $this->translator->trans('menu.link_product_social_posts', [], 'messages'),
                'fas fa-share-alt',
                'admin_product_social_posts',
                ['_locale' => 'ua'],
            ),
            MenuItem::linkToRoute(
                $this->translator->trans('menu.link_product_rating', [], 'messages'),
                'fas fa-star',
                'admin_product_rating',
                ['_locale' => 'ua'],
            ),
        ]);

        yield MenuItem::subMenu($this->translator->trans('menu.group_email', [], 'messages'), 'fas fa-envelope')->setSubItems([
            MenuItem::linkTo(EmailTemplateCrudController::class, $this->translator->trans('menu.link_email_template', [], 'messages'), 'fas fa-envelope'),
            MenuItem::linkTo(EmailTemplateSectionCrudController::class, $this->translator->trans('menu.link_email_template_section', [], 'messages'), 'fas fa-layer-group'),
            MenuItem::linkTo(EmailParameterCrudController::class, $this->translator->trans('menu.link_email_parameter', [], 'messages'), 'fas fa-sliders-h'),
            MenuItem::linkTo(EmailSenderCrudController::class, $this->translator->trans('menu.link_email_sender', [], 'messages'), 'fas fa-paper-plane'),
            MenuItem::linkTo(OrderEmailCrudController::class, $this->translator->trans('menu.link_order_email', [], 'messages'), 'fas fa-envelope-open-text'),
            MenuItem::linkTo(EmailLogCrudController::class, $this->translator->trans('menu.link_email_log', [], 'messages'), 'fas fa-inbox'),
            MenuItem::linkTo(MailboxAccountCrudController::class, $this->translator->trans('menu.link_mailbox_account', [], 'messages'), 'fas fa-at'),
            MenuItem::linkTo(MailboxMessageCrudController::class, $this->translator->trans('menu.link_mailbox_message', [], 'messages'), 'fas fa-envelope-open'),
            MenuItem::linkTo(MailboxSentMessageCrudController::class, $this->translator->trans('menu.link_mailbox_sent_message', [], 'messages'), 'fas fa-paper-plane'),
            MenuItem::linkTo(
                MailboxConversationController::class,
                $this->translator->trans('menu.link_mailbox_conversation', [], 'messages'),
                'fas fa-comments',
            )->setAction('index'),
            MenuItem::linkTo(
                MailboxComposeController::class,
                $this->translator->trans('menu.link_mailbox_compose', [], 'messages'),
                'fas fa-pen',
            )->setAction('compose'),
            MenuItem::linkToRoute(
                $this->translator->trans('menu.link_email_template_test', [], 'messages'),
                'fas fa-vial',
                'admin_email_template_test',
                ['_locale' => 'ua'],
            ),
        ]);

        yield MenuItem::subMenu($this->translator->trans('menu.group_bank', [], 'messages'), 'fas fa-university')->setSubItems([
            MenuItem::linkTo(BankAccountCrudController::class, $this->translator->trans('menu.link_bank_account', [], 'messages'), 'fas fa-university'),
        ]);

        yield MenuItem::subMenu($this->translator->trans('menu.users', [], 'messages'), 'fas fa-users')->setSubItems([
            MenuItem::linkTo(UserCrudController::class, $this->translator->trans('menu.link_user', [], 'messages'), 'fas fa-users'),
            MenuItem::linkTo(UserGroupCrudController::class, $this->translator->trans('menu.link_user_group', [], 'messages'), 'fas fa-user-friends'),
            MenuItem::linkTo(UserRoleCrudController::class, $this->translator->trans('menu.link_user_role', [], 'messages'), 'fas fa-user-tag'),
            MenuItem::linkTo(RequestListCrudController::class, $this->translator->trans('menu.link_request_list', [], 'messages'), 'fas fa-globe'),
            MenuItem::linkToRoute(
                $this->translator->trans('menu.link_report_visitor_ids', [], 'messages'),
                'fas fa-chart-bar',
                'admin_report_visitor_ids',
                ['_locale' => 'ua'],
            ),
            MenuItem::linkTo(
                ReportRequestTrafficController::class,
                $this->translator->trans('menu.link_report_request_traffic', [], 'messages'),
                'fas fa-chart-line',
            )->setAction('index'),
            MenuItem::linkTo(BotUserAgentMarkerCrudController::class, $this->translator->trans('menu.link_bot_user_agent_marker', [], 'messages'), 'fas fa-robot'),
            MenuItem::linkTo(BlockedIpCrudController::class, $this->translator->trans('menu.link_blocked_ip', [], 'messages'), 'fas fa-ban'),
            MenuItem::linkTo(AdminAuditLogCrudController::class, $this->translator->trans('menu.link_admin_audit_log', [], 'messages'), 'fas fa-clipboard-list'),
            MenuItem::linkTo(RedirectCrudController::class, $this->translator->trans('menu.link_redirect', [], 'messages'), 'fas fa-random'),
        ]);

        // @GENERATE MENU START
        yield MenuItem::subMenu($this->translator->trans('menu.group_setting', [], 'messages'), 'fas fa-cog')->setSubItems([
            MenuItem::linkTo(FootersettingCrudController::class, $this->translator->trans('menu.link_footersetting', [], 'messages'), 'fas fa-list'),
            MenuItem::linkTo(FootertranslationCrudController::class, $this->translator->trans('menu.link_footertranslation', [], 'messages'), 'fas fa-list'),
            MenuItem::linkTo(HeadersettingCrudController::class, $this->translator->trans('menu.link_headersetting', [], 'messages'), 'fas fa-list'),
            MenuItem::linkTo(HeadertranslationCrudController::class, $this->translator->trans('menu.link_headertranslation', [], 'messages'), 'fas fa-list'),
            MenuItem::linkTo(LocaleCrudController::class, $this->translator->trans('menu.link_locale', [], 'messages'), 'fas fa-list'),
            MenuItem::linkTo(MegamenusettingCrudController::class, $this->translator->trans('menu.link_megamenusetting', [], 'messages'), 'fas fa-list'),
            MenuItem::linkTo(MegamenutranslationCrudController::class, $this->translator->trans('menu.link_megamenutranslation', [], 'messages'), 'fas fa-list'),
            MenuItem::linkTo(MegamenutypeCrudController::class, $this->translator->trans('menu.link_megamenutype', [], 'messages'), 'fas fa-list'),
            MenuItem::linkTo(PopularsearchCrudController::class, $this->translator->trans('menu.link_popularsearch', [], 'messages'), 'fas fa-list'),
            MenuItem::linkTo(SeosettingCrudController::class, $this->translator->trans('menu.link_seosetting', [], 'messages'), 'fas fa-list'),
            MenuItem::linkTo(SeosettingstranslationCrudController::class, $this->translator->trans('menu.link_seosettingstranslation', [], 'messages'), 'fas fa-list'),
            MenuItem::linkTo(SiteCrudController::class, $this->translator->trans('menu.link_site', [], 'messages'), 'fas fa-list'),
            MenuItem::linkTo(HomepageSettingCrudController::class, $this->translator->trans('menu.link_homepage_setting', [], 'messages'), 'fas fa-home'),
            MenuItem::linkTo(ShopMegaMenuSettingCrudController::class, $this->translator->trans('menu.link_shop_mega_menu', [], 'messages'), 'fas fa-bars'),
        ]);
        // @GENERATE MENU FINISH
    }


    public function configureAssets(): Assets
    {
        return Assets::new()
            ->addCssFile('lib/admin.css')
            ->addJsFile(Asset::new('lib/admin.js')->defer())
            ->addCssFile('https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css')
            ->addJsFile(Asset::new('https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.js')->defer())
            ->addJsFile(Asset::new('lib/admin-datepicker.js')->defer())
            ->addCssFile('https://cdn.jsdelivr.net/npm/jodit@4.2.27/es2021/jodit.min.css')
            ->addCssFile('lib/admin-html-editor.css')
            ->addJsFile(Asset::new('https://cdn.jsdelivr.net/npm/jodit@4.2.27/es2021/jodit.min.js')->defer())
            ->addJsFile(Asset::new('lib/admin-html-editor.js')->defer())
            ->addCssFile('lib/admin-mailbox-notifications.css')
            ->addJsFile(Asset::new('lib/admin-mailbox-notifications.js')->defer());
    }
}
