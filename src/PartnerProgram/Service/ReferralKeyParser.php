<?php

namespace App\PartnerProgram\Service;

/**
 * Validates a partner referral key from a query parameter value.
 *
 * Combined key: {pageCode}{linkKey}  (e.g. pb24jc3yts)
 */
final class ReferralKeyParser
{
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

        return [
            'pageCode' => $pageCode,
            'linkKey' => $linkKey,
            'referralKey' => $pageCode.$linkKey,
        ];
    }
}
