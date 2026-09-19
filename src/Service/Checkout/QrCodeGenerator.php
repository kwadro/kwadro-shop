<?php

namespace App\Service\Checkout;

final class QrCodeGenerator
{
    public function generatePng(string $data, int $moduleSize = 6): string
    {
        $data = trim($data);
        if ($data === '') {
            throw new \InvalidArgumentException('QR code data cannot be empty.');
        }

        if ($this->isExecutableAvailable('qrencode')) {
            return $this->generateWithQrencode($data, $moduleSize);
        }

        return $this->generateWithGoogleChart($data);
    }

    private function generateWithQrencode(string $data, int $moduleSize): string
    {
        $command = sprintf(
            'qrencode -o - -s %d -m 1 -- %s',
            max(1, min(10, $moduleSize)),
            escapeshellarg($data),
        );

        $output = shell_exec($command);
        if (!\is_string($output) || $output === '') {
            throw new \RuntimeException('Unable to generate QR code.');
        }

        return $output;
    }

    private function generateWithGoogleChart(string $data): string
    {
        $url = 'https://chart.googleapis.com/chart?chs=240x240&cht=qr&chl=' . rawurlencode($data);
        $image = @file_get_contents($url);
        if (!\is_string($image) || $image === '') {
            throw new \RuntimeException('Unable to generate QR code.');
        }

        return $image;
    }

    private function isExecutableAvailable(string $binary): bool
    {
        $path = trim((string) shell_exec(sprintf('command -v %s 2>/dev/null', escapeshellarg($binary))));

        return $path !== '';
    }
}
