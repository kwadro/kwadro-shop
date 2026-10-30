<?php

namespace App\AiContentAgent\Dto;

final readonly class DiscoveredSource
{
    public function __construct(
        public string $url,
        public string $title = '',
        public string $excerpt = '',
    ) {
    }
}
