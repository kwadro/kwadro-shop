<?php

namespace App\AiContentAgent\Service;

use App\AiContentAgent\Dto\DiscoveredSource;
use App\AiContentAgent\Dto\SeoArticleDraft;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Generates an SEO article. Uses an OpenAI-compatible API when a key is configured,
 * and the local composer when it is not or when the API call fails.
 */
final class SeoArticleGenerator
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly SeoArticleComposer $composer,
        private readonly SeoArticleNormalizer $normalizer,
        private readonly string $apiUrl,
        private readonly string $apiKey,
        private readonly string $model,
    ) {
    }

    /**
     * @param list<DiscoveredSource> $sources
     */
    public function generate(string $topic, array $sources, string $locale): SeoArticleDraft
    {
        $locale = $locale === 'uk' ? 'uk' : 'en';
        if (trim($this->apiKey) === '') {
            return $this->normalizer->normalize($this->composer->compose($topic, $sources, $locale), $locale);
        }

        try {
            $draft = $this->fromApi($topic, $sources, $locale);

            return $this->normalizer->normalize($draft, $locale);
        } catch (\Throwable $exception) {
            $offline = $this->composer->compose($topic, $sources, $locale);

            return $this->normalizer->normalize($offline, $locale, [
                'AI generation failed and the local SEO composer was used: '.$exception->getMessage(),
            ]);
        }
    }

    /**
     * @param list<DiscoveredSource> $sources
     */
    private function fromApi(string $topic, array $sources, string $locale): SeoArticleDraft
    {
        $language = $locale === 'uk' ? 'Ukrainian' : 'English';
        $sourceLines = [];
        foreach ($sources as $source) {
            $sourceLines[] = trim($source->title.' '.$source->url.' '.$source->excerpt);
        }

        $response = $this->httpClient->request('POST', $this->endpoint(), [
            'timeout' => 60,
            'headers' => [
                'Authorization' => 'Bearer '.$this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => $this->model !== '' ? $this->model : 'gpt-4o-mini',
                'temperature' => 0.4,
                'max_tokens' => 2500,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You write SEO shop blog articles. Respond with JSON only. Keys: title, metaTitle, metaDescription, ogTitle, ogDescription, slug, tags, focusKeyword, contentHtml. '
                            .'Write in '.$language.'. metaTitle max 70 characters. metaDescription 110-160 characters. '
                            .'contentHtml uses h2 and h3 only, never h1. Put the focus keyword in the title, meta description, and the first paragraph. '
                            .'Include at least three h2 sections and a short FAQ. Do not invent prices or stock.',
                    ],
                    [
                        'role' => 'user',
                        'content' => "Topic:\n".$topic."\n\nSources:\n".implode("\n", $sourceLines),
                    ],
                ],
            ],
        ]);

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException(sprintf('AI API returned HTTP %d.', $status));
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->toArray(false);
        $content = $payload['choices'][0]['message']['content'] ?? null;
        if (!is_string($content) || trim($content) === '') {
            throw new \RuntimeException('AI API returned an empty article.');
        }

        $decoded = $this->decodeContent($content);

        return new SeoArticleDraft(
            title: $this->stringField($decoded, 'title'),
            metaTitle: $this->stringField($decoded, 'metaTitle'),
            metaDescription: $this->stringField($decoded, 'metaDescription'),
            ogTitle: $this->stringField($decoded, 'ogTitle'),
            ogDescription: $this->stringField($decoded, 'ogDescription'),
            slug: $this->stringField($decoded, 'slug'),
            tags: $this->stringField($decoded, 'tags'),
            focusKeyword: $this->stringField($decoded, 'focusKeyword') !== ''
                ? $this->stringField($decoded, 'focusKeyword')
                : trim($topic),
            contentHtml: $this->stringField($decoded, 'contentHtml'),
        );
    }

    private function endpoint(): string
    {
        $url = trim($this->apiUrl);
        if ($url === '') {
            return 'https://api.openai.com/v1/chat/completions';
        }

        return $url;
    }

    /** @return array<string, mixed> */
    private function decodeContent(string $content): array
    {
        $content = trim($content);
        if (preg_match('/```(?:json)?\s*(.*?)```/s', $content, $matches)) {
            $content = trim($matches[1]);
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('AI response was not JSON.');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /** @param array<string, mixed> $data */
    private function stringField(array $data, string $key): string
    {
        $value = $data[$key] ?? '';
        if (is_array($value)) {
            $value = implode(', ', array_map(static fn (mixed $item): string => is_scalar($item) ? (string) $item : '', $value));
        }

        return is_scalar($value) ? trim((string) $value) : '';
    }
}
