<?php

namespace App\Controller\Admin;

use App\Dto\EmailTemplateTestData;
use App\Entity\EmailTemplateContext;
use App\Form\Type\EmailTemplateTestFormType;
use App\Service\Mail\EmailTemplateTestService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/{_locale}/email-template-test', name: 'admin_email_template_test', requirements: ['_locale' => 'uk'])]
#[IsGranted('ROLE_SUPER_ADMIN')]
final class EmailTemplateTestController extends AbstractController
{
    public function __construct(
        private readonly EmailTemplateTestService $testService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: '', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $data = new EmailTemplateTestData();
        $user = $this->getUser();
        if (\is_object($user) && method_exists($user, 'getEmail')) {
            $data->recipient = trim((string) $user->getEmail());
        }

        $form = $this->createForm(EmailTemplateTestFormType::class, $data);
        $form->handleRequest($request);

        $preview = null;

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var EmailTemplateTestData $data */
            $data = $form->getData();

            if ($data->template->getContext() === EmailTemplateContext::Order) {
                $data->user = null;
            } else {
                $data->order = null;
            }

            $context = $this->testService->buildContext($data->template, $data->order, $data->user, $data->recipient);

            if ($form->get('preview')->isClicked()) {
                $preview = $this->testService->preview($data->template, $context, $data->order);
            }

            if ($form->get('send')->isClicked()) {
                $result = $this->testService->send(
                    $data->template,
                    $data->sender,
                    $data->recipient,
                    $context,
                    $data->order,
                );

                if ($result['sent']) {
                    $this->addFlash('success', $this->translator->trans('admin.email_template_test.sent', [], 'messages'));
                } else {
                    $this->addFlash('danger', $this->translator->trans('admin.email_template_test.send_failed', [], 'messages'));
                }

                $preview = [
                    'subject' => $result['log']->getSubject() ?? '',
                    'body' => $result['log']->getBody() ?? '',
                    'isHtml' => $result['log']->isHtml(),
                ];
            }
        }

        return $this->render('admin/email_template_test/index.html.twig', [
            'form' => $form,
            'preview' => $preview,
            'page_title' => $this->translator->trans('admin.email_template_test.title', [], 'messages'),
        ]);
    }
}
