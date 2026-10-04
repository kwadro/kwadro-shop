<?php

namespace App\Controller\Admin;

use App\Entity\MailboxMessage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SUPER_ADMIN')]
class MailboxMessageHtmlController extends AbstractController
{
    #[Route(
        '/admin/{_locale}/mailbox-message/{id}/html',
        name: 'admin_mailbox_message_html',
        requirements: ['_locale' => 'uk|en', 'id' => '\d+'],
        methods: ['GET'],
    )]
    public function __invoke(MailboxMessage $message): Response
    {
        $html = trim((string) $message->getBodyHtml());
        if ($html === '') {
            $text = trim((string) $message->getBodyText());
            $html = $text !== ''
                ? '<pre style="white-space:pre-wrap;font-family:system-ui,sans-serif;margin:16px">'
                    .htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    .'</pre>'
                : '<p style="margin:16px;color:#666">—</p>';
        }

        $html = $this->sanitizeEmailHtml($html);
        if (!preg_match('/<html[\s>]/i', $html)) {
            $html = '<!DOCTYPE html><html><head><meta charset="utf-8">'
                .'<meta name="viewport" content="width=device-width, initial-scale=1">'
                .'<style>body{margin:16px;font-family:system-ui,sans-serif;color:#202124;}</style>'
                .'</head><body>'.$html.'</body></html>';
        }

        return new Response($html, Response::HTTP_OK, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Content-Security-Policy' => "default-src 'none'; img-src * data: blob:; style-src 'unsafe-inline' *; font-src * data:; frame-ancestors 'self';",
        ]);
    }

    private function sanitizeEmailHtml(string $html): string
    {
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;
        $html = preg_replace('/<iframe\b[^>]*>.*?<\/iframe>/is', '', $html) ?? $html;
        $html = preg_replace('/\son\w+\s*=\s*(["\']).*?\1/iu', '', $html) ?? $html;
        $html = preg_replace('/\son\w+\s*=\s*[^\s>]+/iu', '', $html) ?? $html;
        $html = preg_replace('/javascript\s*:/iu', '', $html) ?? $html;

        return $html;
    }
}
