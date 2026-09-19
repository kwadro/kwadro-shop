<?php

namespace App\Form\Type;

use App\Dto\EmailTemplateTestData;
use App\Entity\EmailSender;
use App\Entity\EmailTemplate;
use App\Entity\Order;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

final class EmailTemplateTestFormType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $selectRowOptions = [
            'row_attr' => ['class' => 'mb-3'],
            'label_attr' => ['class' => 'form-label'],
        ];

        $builder
            ->add('template', EntityType::class, [
                ...$selectRowOptions,
                'class' => EmailTemplate::class,
                'label' => $this->translator->trans('admin.email_template_test.template', [], 'messages'),
                'placeholder' => $this->translator->trans('admin.email_template_test.choose', [], 'messages'),
                'attr' => ['class' => 'form-select'],
                'choice_label' => static fn (EmailTemplate $template): string => sprintf(
                    '%s (%s)',
                    $template->getName(),
                    $template->getSubject(),
                ),
                'choice_attr' => static fn (?EmailTemplate $template): array => $template === null ? [] : [
                    'data-context' => $template->getContext()->value,
                ],
                'query_builder' => static fn ($repository) => $repository->createQueryBuilder('t')
                    ->orderBy('t.name', 'ASC'),
            ])
            ->add('sender', EntityType::class, [
                ...$selectRowOptions,
                'class' => EmailSender::class,
                'label' => $this->translator->trans('admin.email_template_test.sender', [], 'messages'),
                'placeholder' => $this->translator->trans('admin.email_template_test.choose', [], 'messages'),
                'attr' => ['class' => 'form-select'],
                'choice_label' => static fn (EmailSender $sender): string => sprintf(
                    '%s <%s>',
                    $sender->getName(),
                    $sender->getEmail(),
                ),
                'query_builder' => static fn ($repository) => $repository->createQueryBuilder('s')
                    ->orderBy('s.name', 'ASC'),
            ])
            ->add('recipient', EmailType::class, [
                'row_attr' => ['class' => 'mb-3'],
                'label_attr' => ['class' => 'form-label'],
                'label' => $this->translator->trans('admin.email_template_test.recipient', [], 'messages'),
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'test@example.com',
                ],
            ])
            ->add('order', EntityType::class, [
                ...$selectRowOptions,
                'class' => Order::class,
                'label' => $this->translator->trans('admin.email_template_test.context_value', [], 'messages'),
                'required' => false,
                'placeholder' => $this->translator->trans('admin.email_template_test.context_order_none', [], 'messages'),
                'help' => $this->translator->trans('admin.email_template_test.context_order_help', [], 'messages'),
                'help_attr' => ['class' => 'form-text form-help'],
                'attr' => [
                    'class' => 'form-select',
                    'data-context-target' => 'order',
                ],
                'row_attr' => [
                    'class' => 'mb-3 d-none',
                    'data-context-field' => 'order',
                ],
                'query_builder' => static fn ($repository) => $repository->createQueryBuilder('o')
                    ->orderBy('o.id', 'DESC'),
            ])
            ->add('user', EntityType::class, [
                ...$selectRowOptions,
                'class' => User::class,
                'label' => $this->translator->trans('admin.email_template_test.context_value', [], 'messages'),
                'required' => false,
                'placeholder' => $this->translator->trans('admin.email_template_test.context_user_none', [], 'messages'),
                'help' => $this->translator->trans('admin.email_template_test.context_user_help', [], 'messages'),
                'help_attr' => ['class' => 'form-text form-help'],
                'attr' => [
                    'class' => 'form-select',
                    'data-context-target' => 'user',
                ],
                'row_attr' => [
                    'class' => 'mb-3 d-none',
                    'data-context-field' => 'user',
                ],
                'query_builder' => static fn ($repository) => $repository->createQueryBuilder('u')
                    ->orderBy('u.email', 'ASC'),
            ])
            ->add('preview', SubmitType::class, [
                'label' => $this->translator->trans('admin.email_template_test.preview', [], 'messages'),
                'attr' => ['class' => 'btn btn-secondary'],
            ])
            ->add('send', SubmitType::class, [
                'label' => $this->translator->trans('admin.email_template_test.send', [], 'messages'),
                'attr' => ['class' => 'btn btn-primary'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EmailTemplateTestData::class,
        ]);
    }
}
