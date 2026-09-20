<?php

namespace App\Service\Checkout;

use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

final class QrCodeGenerator
{
    public function generatePng(string $data, int $moduleSize = 6): string
    {
        $data = trim($data);
        if ($data === '') {
            throw new \InvalidArgumentException('QR code data cannot be empty.');
        }

        if (!extension_loaded('gd')) {
            throw new \RuntimeException('PHP GD extension is required to generate QR codes.');
        }

        $options = new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'scale' => max(1, min(10, $moduleSize)),
            'outputBase64' => false,
            'addQuietzone' => true,
            'quietzoneSize' => 1,
        ]);

        $png = (new QRCode($options))->render($data);
        if (!\is_string($png) || $png === '') {
            throw new \RuntimeException('Unable to generate QR code.');
        }

        return $png;
    }
}
