<?php

namespace App\PartnerProgram\Service;

/**
 * Extracts a partner referral key from a path suffix or a raw key string.
 *
 * Path form: .../{pageCode}/{linkKey}  (e.g. /ua/product/x/pr/24jc3yts)
 * Combined key: {pageCode}{linkKey}    (e.g. pr24jc3yts)
 *
 * Path pageCode must be one of the admin-allowed 2-char names.
 */
final class ReferralKeyParser
{
    private const PATH_PATTERN = '#/([a-z0-9]{2})/([a-zA-Z0-9_-]{3,64})/?$#i';
    private const KEY_PATTERN = '/^[a-zA-Z0-9_-]{5,66}$/';

    /**
     * @return array{pageCode: string, linkKey: string, referralKey: string}|null
     */
    public function parseReferralKey(string $referralKey): ?array
    {
        $referralKey = trim($referralKey);

        if (1 !== preg_match(self::KEY_PATTERN, $referralKey)) {
            return null;
        }

        $pageCode = strtolower(substr($referralKey, 0, 2));
        $linkKey = substr($referralKey, 2);
        if (1 !== preg_match('/^[a-z0-9]{2}$/', $pageCode)) {
            return null;
        }
        if (1 !== preg_match('/^[a-zA-Z0-9_-]{3,64}$/', $linkKey)) {
            return null;
        }

        return $this->parts($pageCode, $linkKey);
    }

    /**
     * @param list<string> $allowedPageCodes Admin list names that may appear as the 2-char path segment
     *
     * @return array{pageCode: string, linkKey: string, referralKey: string}|null
     */
    public function parsePath(string $path, array $allowedPageCodes): ?array
    {
        $path = trim($path);
        if ($path === '' || $allowedPageCodes === []) {
            return null;
        }

        if (1 !== preg_match(self::PATH_PATTERN, $path, $matches)) {
            return null;
        }

        $pageCode = strtolower($matches[1]);
        if (!$this->isAllowedPageCode($pageCode, $allowedPageCodes)) {
            return null;
        }

        return $this->parts($pageCode, $matches[2]);
    }

    public function stripPathSuffix(string $path): string
    {
        $stripped = preg_replace(self::PATH_PATTERN, '', $path);
        if (!\is_string($stripped) || $stripped === '') {
            return '/';
        }

        return $stripped;
    }

    /**
     * Active admin names usable as a 2-char path pageCode.
     *
     * @param list<string> $allowedParamNames
     *
     * @return list<string>
     */
    public function filterPathPageCodes(array $allowedParamNames): array
    {
        $codes = [];
        foreach ($allowedParamNames as $name) {
            $code = strtolower(trim($name));
            if (1 === preg_match('/^[a-z0-9]{2}$/', $code)) {
                $codes[] = $code;
            }
        }

        return array_values(array_unique($codes));
    }

    /**
     * @param list<string> $allowedPageCodes
     */
    private function isAllowedPageCode(string $pageCode, array $allowedPageCodes): bool
    {
        foreach ($allowedPageCodes as $allowed) {
            if (strcasecmp($pageCode, trim((string) $allowed)) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{pageCode: string, linkKey: string, referralKey: string}
     */
    private function parts(string $pageCode, string $linkKey): array
    {
        return [
            'pageCode' => $pageCode,
            'linkKey' => $linkKey,
            'referralKey' => $pageCode.$linkKey,
        ];
    }
}
