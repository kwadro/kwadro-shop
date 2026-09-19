<?php

namespace App\Service\Mail;

use App\Entity\EmailTemplate;
use App\Entity\EmailTemplateType;
use App\Entity\Site;
use App\Repository\EmailTemplateSectionRepository;

final class OrderEmailTemplateRenderer
{
    public function __construct(
        private readonly EmailTemplateSectionRepository $sectionRepository,
    ) {
    }

    /**
     * @return array{subject: string, body: string, isHtml: bool}
     */
    public function render(EmailTemplate $template, array $context): array
    {
        $site = $template->getSite();
        $subject = $this->replacePlaceholders($template->getSubject(), $context, $site);
        $content = $this->replacePlaceholders($template->getContent(), $context, $site);
        $isHtml = $template->getType() === EmailTemplateType::Html;

        if (!$isHtml) {
            return [
                'subject' => $subject,
                'body' => $content,
                'isHtml' => false,
            ];
        }

        $css = trim((string) $template->getAdditionalCss());
        if ($css === '') {
            return [
                'subject' => $subject,
                'body' => $content,
                'isHtml' => true,
            ];
        }

        return [
            'subject' => $subject,
            'body' => sprintf(
                "<!DOCTYPE html>\n<html>\n<head>\n<meta charset=\"UTF-8\">\n<style>\n%s\n</style>\n</head>\n<body>\n%s\n</body>\n</html>",
                $css,
                $content,
            ),
            'isHtml' => true,
        ];
    }

    /** @param array<string, string> $context */
    private function replacePlaceholders(string $template, array $context, ?Site $site): string
    {
        $withSections = $this->replaceSections($template, $context, $site);

        return (string) preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/',
            static fn (array $matches): string => $context[$matches[1]] ?? '',
            $withSections,
        );
    }

    /** @param array<string, string> $context */
    private function replaceSections(string $template, array $context, ?Site $site): string
    {
        if ($site === null) {
            return (string) preg_replace('/\{\{\s*section\s*=\s*(["\'])[^"\']+\1\s*\}\}/i', '', $template);
        }

        return (string) preg_replace_callback(
            '/\{\{\s*section\s*=\s*(["\'])([^"\']+)\1\s*\}\}/i',
            function (array $matches) use ($context, $site): string {
                $section = $this->sectionRepository->findOneBySiteAndName($site, $matches[2]);
                if ($section === null) {
                    return '';
                }

                return $this->replacePlaceholders($section->getContent(), $context, $site);
            },
            $template,
        );
    }
}
