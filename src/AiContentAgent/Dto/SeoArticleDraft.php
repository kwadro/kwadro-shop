<?php

namespace App\AiContentAgent\Dto;

final readonly class SeoArticleDraft
{
    /**
     * @param list<string> $warnings
     */
    public function __construct(
        public string $title,
        public string $metaTitle,
        public string $metaDescription,
        public string $ogTitle,
        public string $ogDescription,
        public string $slug,
        public string $tags,
        public string $focusKeyword,
        public string $contentHtml,
        public array $warnings = [],
    ) {
    }

    /**
     * @param list<string> $warnings
     */
    public function withWarnings(array $warnings): self
    {
        return new self(
            $this->title,
            $this->metaTitle,
            $this->metaDescription,
            $this->ogTitle,
            $this->ogDescription,
            $this->slug,
            $this->tags,
            $this->focusKeyword,
            $this->contentHtml,
            $warnings,
        );
    }
}
