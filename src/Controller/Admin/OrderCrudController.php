<?php

namespace App\Controller\Admin;

use App\Entity\MailboxMessage;
use App\Entity\Order;
use App\Entity\OrderStatus;
use App\Repository\MailboxMessageRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class OrderCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly MailboxMessageRepository $mailboxMessageRepository,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Order::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular($this->translator->trans('menu.link_order_single', [], 'messages'))
            ->setEntityLabelInPlural($this->translator->trans('menu.link_order', [], 'messages'))
            ->setDefaultSort(['created_at' => 'DESC'])
            ->setFormThemes([
                '@EasyAdmin/crud/form_theme.html.twig',
                'admin/fields.html.twig',
            ]);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::DELETE);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('status')->setChoices([
                $this->translator->trans('admin.order.status.created', [], 'messages') => OrderStatus::Created,
                $this->translator->trans('admin.order.status.in_process', [], 'messages') => OrderStatus::InProcess,
                $this->translator->trans('admin.order.status.awaiting_deposit_for_shipment', [], 'messages') => OrderStatus::AwaitingDepositForShipment,
                $this->translator->trans('admin.order.status.deposit_paid', [], 'messages') => OrderStatus::DepositPaid,
                $this->translator->trans('admin.order.status.paid', [], 'messages') => OrderStatus::Paid,
            ]));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        yield Field::new('orderInfo', 'admin.order.info')
            ->onlyOnDetail()
            ->setTemplatePath('admin/field/order_info.html.twig');
        yield FormField::addFieldset('admin.order.info', 'fa fa-file-invoice')
            ->onlyOnForms()
            ->hideWhenCreating();
        yield Field::new('orderInfoPanel', false)
            ->onlyOnForms()
            ->hideWhenCreating()
            ->setFormType(HiddenType::class)
            ->setFormTypeOptions([
                'mapped' => false,
                'required' => false,
                'block_prefix' => 'order_info',
            ])
            ->setValue(true);

        yield Field::new('customerInfo', 'admin.order.customer_info')
            ->onlyOnDetail()
            ->setTemplatePath('admin/field/order_customer.html.twig');
        yield FormField::addFieldset('admin.order.customer_info', 'fa fa-user')
            ->onlyOnForms()
            ->hideWhenCreating();
        yield Field::new('customerInfoPanel', false)
            ->onlyOnForms()
            ->hideWhenCreating()
            ->setFormType(HiddenType::class)
            ->setFormTypeOptions([
                'mapped' => false,
                'required' => false,
                'block_prefix' => 'order_customer',
            ])
            ->setValue(true);

        yield Field::new('orderAmounts', 'admin.order.amounts')
            ->onlyOnDetail()
            ->setTemplatePath('admin/field/order_amounts.html.twig');
        yield FormField::addFieldset('admin.order.amounts', 'fa fa-coins')
            ->onlyOnForms()
            ->hideWhenCreating();
        yield Field::new('orderAmountsPanel', false)
            ->onlyOnForms()
            ->hideWhenCreating()
            ->setFormType(HiddenType::class)
            ->setFormTypeOptions([
                'mapped' => false,
                'required' => false,
                'block_prefix' => 'order_amounts',
            ])
            ->setValue(true);

        yield TextField::new('order_number', 'admin.order.order_number')->onlyOnIndex();
        yield TextField::new('contactSummary', 'admin.order.contact')
            ->onlyOnIndex();
        yield TextField::new('shipmentAddressSummary', 'admin.order.shipment_address')
            ->onlyOnDetail();
        yield TextField::new('npWaybillNumber', 'admin.order.np_waybill_number')
            ->onlyOnDetail()
            ->hideOnForm();
        yield TextField::new('npWaybillRef', 'admin.order.np_waybill_ref')
            ->onlyOnDetail()
            ->hideOnForm();
        yield AssociationField::new('shipmentAddress', 'admin.order.shipment_address')
            ->onlyOnDetail();
        yield ChoiceField::new('status', 'admin.order.status_label')
            ->onlyOnIndex()
            ->setChoices([
                $this->translator->trans('admin.order.status.created', [], 'messages') => OrderStatus::Created,
                $this->translator->trans('admin.order.status.in_process', [], 'messages') => OrderStatus::InProcess,
                $this->translator->trans('admin.order.status.awaiting_deposit_for_shipment', [], 'messages') => OrderStatus::AwaitingDepositForShipment,
                $this->translator->trans('admin.order.status.deposit_paid', [], 'messages') => OrderStatus::DepositPaid,
                $this->translator->trans('admin.order.status.paid', [], 'messages') => OrderStatus::Paid,
            ])
            ->renderAsBadges([
                OrderStatus::Created->value => 'secondary',
                OrderStatus::InProcess->value => 'warning',
                OrderStatus::AwaitingDepositForShipment->value => 'info',
                OrderStatus::DepositPaid->value => 'primary',
                OrderStatus::Paid->value => 'success',
            ]);
        yield MoneyField::new('amount', 'admin.order.order_total')
            ->setCurrency('UAH')
            ->setStoredAsCents(false)
            ->onlyOnIndex();
        yield TextareaField::new('deliveryDataJson', 'delivery_data')
            ->onlyOnDetail()
            ->setNumOfRows(10);
        yield Field::new('itemsSummary', 'admin.order.items')
            ->onlyOnDetail()
            ->setTemplatePath('admin/field/order_items.html.twig');
        yield FormField::addFieldset('admin.order.items', 'fa fa-box')
            ->onlyOnForms()
            ->hideWhenCreating();
        yield Field::new('itemsSummaryPanel', false)
            ->onlyOnForms()
            ->hideWhenCreating()
            ->setFormType(HiddenType::class)
            ->setFormTypeOptions([
                'mapped' => false,
                'required' => false,
                'block_prefix' => 'order_items',
            ])
            ->setValue(true);
        yield Field::new('paymentsSummary', 'admin.order.payments')
            ->onlyOnDetail()
            ->setTemplatePath('admin/field/order_payments.html.twig');
        yield FormField::addFieldset('admin.order.payments', 'fa fa-credit-card')
            ->onlyOnForms()
            ->hideWhenCreating();
        yield Field::new('paymentsSummaryPanel', false)
            ->onlyOnForms()
            ->hideWhenCreating()
            ->setFormType(HiddenType::class)
            ->setFormTypeOptions([
                'mapped' => false,
                'required' => false,
                'block_prefix' => 'order_payments',
            ])
            ->setValue(true);

        yield from $this->emailConversationFields($pageName);

        yield TextareaField::new('cartDataJson', 'cart_data')
            ->onlyOnDetail()
            ->setNumOfRows(10);
        yield DateTimeField::new('created_at', 'admin.order.created_at')->onlyOnIndex();
        yield DateTimeField::new('updated_at', 'admin.order.updated_at')->onlyOnIndex();
    }

    /**
     * One field per email conversation linked to this order.
     */
    private function emailConversationFields(string $pageName): iterable
    {
        if (!\in_array($pageName, [Crud::PAGE_DETAIL, Crud::PAGE_EDIT], true)) {
            return;
        }

        $order = $this->getContext()?->getEntity()->getInstance();
        if (!$order instanceof Order) {
            return;
        }

        yield FormField::addFieldset('admin.order.emails', 'fa fa-envelope')
            ->hideWhenCreating();

        $composeUrl = $this->orderComposeUrl($order, $pageName);
        $cards = $this->mailboxMessageRepository->findOrderConversationCards($order->getOrderNumber());
        if ($cards === []) {
            yield Field::new('orderEmailsEmpty', false)
                ->onlyOnDetail()
                ->setTemplatePath('admin/field/order_emails_empty.html.twig')
                ->setCustomOption('compose_url', $composeUrl)
                ->setValue(true);
            yield Field::new('orderEmailsEmptyPanel', false)
                ->onlyOnForms()
                ->hideWhenCreating()
                ->setFormType(HiddenType::class)
                ->setFormTypeOptions([
                    'mapped' => false,
                    'required' => false,
                    'block_prefix' => 'order_emails_empty',
                    'attr' => [
                        'data-compose-url' => $composeUrl,
                    ],
                ])
                ->setValue(true);

            return;
        }

        yield Field::new('orderEmailCompose', false)
            ->onlyOnDetail()
            ->setTemplatePath('admin/field/order_email_compose.html.twig')
            ->setCustomOption('compose_url', $composeUrl)
            ->setValue(true);
        yield Field::new('orderEmailComposePanel', false)
            ->onlyOnForms()
            ->hideWhenCreating()
            ->setFormType(HiddenType::class)
            ->setFormTypeOptions([
                'mapped' => false,
                'required' => false,
                'block_prefix' => 'order_email_compose',
                'attr' => [
                    'data-compose-url' => $composeUrl,
                ],
            ])
            ->setValue(true);

        foreach ($cards as $index => $card) {
            $subject = trim($card['subject']);
            if ($subject === '') {
                $subject = $this->translator->trans('admin.order.email_conversation_untitled', [], 'messages');
            }

            $urls = $this->conversationUrls($card);
            $conversationField = static function (string $property) use ($subject): Field {
                return Field::new($property, $subject)
                    ->addCssClass('field-order-email-conversation')
                    ->setValue(true);
            };

            yield $conversationField('orderEmailConversation'.$index)
                ->onlyOnDetail()
                ->setTemplatePath('admin/field/order_email_conversation.html.twig')
                ->setCustomOption('dialog_url', $urls['dialog'])
                ->setCustomOption('open_url', $urls['open'])
                ->setCustomOption('meta_date', $card['lastAt']->format('d.m.Y H:i'))
                ->setCustomOption('meta_with', $card['correspondent'])
                ->setCustomOption('meta_count', $card['messageCount']);

            yield $conversationField('orderEmailConversationPanel'.$index)
                ->onlyOnForms()
                ->hideWhenCreating()
                ->setColumns(12)
                ->setFormType(HiddenType::class)
                ->setFormTypeOptions([
                    'mapped' => false,
                    'required' => false,
                    'block_prefix' => 'order_email_conversation',
                    'attr' => [
                        'data-dialog-url' => $urls['dialog'],
                        'data-open-url' => $urls['open'],
                        'data-meta-date' => $card['lastAt']->format('d.m.Y H:i'),
                        'data-meta-with' => $card['correspondent'],
                        'data-meta-count' => (string) $card['messageCount'],
                    ],
                ]);
        }
    }

    private function orderComposeUrl(Order $order, string $pageName): string
    {
        $orderId = $order->getId();
        if ($orderId === null) {
            return '';
        }

        $subject = $this->translator->trans(
            'admin.order.email_compose_subject',
            ['%number%' => $order->getOrderNumber()],
            'messages',
        );
        $returnAction = $pageName === Crud::PAGE_EDIT ? Action::EDIT : Action::DETAIL;

        return $this->adminUrlGenerator
            ->setController(MailboxComposeController::class)
            ->setAction('compose')
            ->unset('entityId')
            ->set('to', $order->getCustomerEmail())
            ->set('subject', $subject)
            ->set('link_type', MailboxMessage::LINK_ORDER)
            ->set('link_ref', $order->getOrderNumber())
            ->set('return_order', $orderId)
            ->set('return_action', $returnAction)
            ->generateUrl();
    }

    /**
     * @param array{
     *     conversationId: ?string,
     *     singleMessageId: ?int,
     *     singleMessageFolder: ?string
     * } $card
     *
     * @return array{dialog: string, open: string}
     */
    private function conversationUrls(array $card): array
    {
        $conversationId = $card['conversationId'];
        if ($conversationId !== null && $conversationId !== '') {
            return [
                'dialog' => $this->adminUrlGenerator
                    ->setController(MailboxConversationController::class)
                    ->setAction('dialog')
                    ->unset('entityId')
                    ->set('conversationId', $conversationId)
                    ->generateUrl(),
                'open' => $this->adminUrlGenerator
                    ->setController(MailboxConversationController::class)
                    ->setAction('show')
                    ->unset('entityId')
                    ->set('conversationId', $conversationId)
                    ->generateUrl(),
            ];
        }

        $messageId = $card['singleMessageId'];
        if ($messageId === null) {
            return ['dialog' => '', 'open' => ''];
        }

        $controller = $card['singleMessageFolder'] === MailboxMessage::FOLDER_SENT
            ? MailboxSentMessageCrudController::class
            : MailboxMessageCrudController::class;
        $locale = $this->getContext()?->getRequest()->getLocale() ?? 'ua';

        return [
            'dialog' => $this->urlGenerator->generate('admin_mailbox_message_html', [
                '_locale' => $locale,
                'id' => $messageId,
            ]),
            'open' => $this->adminUrlGenerator
                ->setController($controller)
                ->setAction(Action::DETAIL)
                ->setEntityId($messageId)
                ->unset('conversationId')
                ->generateUrl(),
        ];
    }
}
