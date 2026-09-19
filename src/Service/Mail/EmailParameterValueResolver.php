<?php

namespace App\Service\Mail;

use App\Entity\EmailParameter;
use App\Entity\EmailParameterType;
use App\Entity\Site;

final class EmailParameterValueResolver
{
    private const IMAGE_UPLOAD_PATH = '/uploads/images';

    public function resolve(EmailParameter $parameter, Site $site): string
    {
        $value = trim($parameter->getValue());
        if ($value === '') {
            return '';
        }

        if ($parameter->getType() !== EmailParameterType::Image) {
            return $value;
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        $domain = trim((string) ($site->getDomain() ?? ''));
        if ($domain === '') {
            return sprintf('%s/%s', self::IMAGE_UPLOAD_PATH, rawurlencode($value));
        }

        return sprintf('https://%s%s/%s', $domain, self::IMAGE_UPLOAD_PATH, rawurlencode($value));
    }
}
