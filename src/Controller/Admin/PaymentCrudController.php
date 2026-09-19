<?php

namespace App\Controller\Admin;

use App\Entity\Order;
use App\Entity\Payment;
use App\Entity\PaymentStatus;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Contracts\Translation\TranslatorInterface;

class PaymentCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Payment::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular($this->translator->trans('menu.link_payment_single', [], 'messages'))
            ->setEntityLabelInPlural($this->translator->trans('menu.link_payment', [], 'messages'))
            ->setDefaultSort(['created_at' => 'DESC']);
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
                $this->translator->trans('admin.payment.status.pending', [], 'messages') => PaymentStatus::Pending,
                $this->translator->trans('admin.payment.status.success', [], 'messages') => PaymentStatus::Success,
                $this->translator->trans('admin.payment.status.failed', [], 'messages') => PaymentStatus::Failed,
            ]));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('orderLink', 'admin.payment.order')
            ->onlyOnDetail()
            ->renderAsHtml()
            ->formatValue(fn (?string $value, Payment $payment): string => $this->formatOrderLink($payment));
        yield AssociationField::new('order', 'admin.payment.order')
            ->hideOnDetail();
        yield TextField::new('method')
            ->formatValue(fn (?string $value, Payment $payment): string => $this->formatPaymentMethod($payment->getMethod()));
        yield ChoiceField::new('status')
            ->setChoices([
                $this->translator->trans('admin.payment.status.pending', [], 'messages') => PaymentStatus::Pending,
                $this->translator->trans('admin.payment.status.success', [], 'messages') => PaymentStatus::Success,
                $this->translator->trans('admin.payment.status.failed', [], 'messages') => PaymentStatus::Failed,
            ])
            ->renderAsBadges([
                PaymentStatus::Pending->value => 'warning',
                PaymentStatus::Success->value => 'success',
                PaymentStatus::Failed->value => 'danger',
            ]);
        yield MoneyField::new('amount')->setCurrency('UAH')->setStoredAsCents(false);
        yield TextField::new('currency')->hideOnIndex();
        yield TextField::new('gateway_reference');
        yield UrlField::new('redirect_url')->hideOnIndex();
        yield TextareaField::new('gateway_response')->onlyOnDetail()
            ->formatValue(static fn (?array $value): string => json_encode($value ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}');
        yield TextareaField::new('result_data')->onlyOnDetail()
            ->formatValue(static fn (?array $value): string => json_encode($value ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}');
        yield DateTimeField::new('created_at')->hideOnForm();
        yield DateTimeField::new('updated_at')->hideOnForm();
    }

    private function formatOrderLink(Payment $payment): string
    {
        $order = $payment->getOrder();
        if (!$order instanceof Order || $order->getId() === null) {
            return '—';
        }

        $url = $this->adminUrlGenerator
            ->setController(OrderCrudController::class)
            ->setAction(Action::DETAIL)
            ->setEntityId($order->getId())
            ->generateUrl();

        $label = htmlspecialchars($order->getOrderNumber(), ENT_QUOTES, 'UTF-8');

        return sprintf('<a href="%s">%s</a>', htmlspecialchars($url, ENT_QUOTES, 'UTF-8'), $label);
    }

    private function formatPaymentMethod(string $method): string
    {
        $key = 'admin.payment.method.' . $method;
        $translated = $this->translator->trans($key, [], 'messages');

        return $translated !== $key ? $translated : $method;
    }
}
