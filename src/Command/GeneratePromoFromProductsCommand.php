<?php

namespace App\Command;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:promo:generate-from-products',
    description: 'Fill promo image editor data (JSON + JPG) from products in the current database',
)]
final class GeneratePromoFromProductsCommand extends Command
{
    private const CANVAS_SIZE = 526;

    public function __construct(
        private readonly ProductRepository $productRepository,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be generated without writing files')
            ->addOption('slug', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Limit to product slug(s)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        /** @var list<string> $slugs */
        $slugs = array_values(array_filter(array_map('strval', (array) $input->getOption('slug'))));

        $products = $this->productRepository->findBy([], ['id' => 'ASC']);
        if ($slugs !== []) {
            $products = array_values(array_filter(
                $products,
                static fn (Product $p): bool => \in_array($p->getSlug(), $slugs, true),
            ));
        }

        if ($products === []) {
            $io->warning('No products found in the current database.');

            return Command::SUCCESS;
        }

        $outDir = $this->projectDir.'/public/uploads/promo/saved';
        if (!$dryRun && !is_dir($outDir) && !mkdir($outDir, 0775, true) && !is_dir($outDir)) {
            $io->error('Cannot create '.$outDir);

            return Command::FAILURE;
        }

        $io->title('Promo data from products');
        $rows = [];

        foreach ($products as $index => $product) {
            $content = $this->buildContent($product);
            $filename = sprintf('promo-product-%s.jpg', $product->getSlug() !== '' ? $product->getSlug() : (string) $product->getId());
            $path = $outDir.'/'.$filename;
            $metaPath = $path.'.json';

            if ($dryRun) {
                $rows[] = [$product->getId(), $product->getName(), $filename, $content['price'], implode('; ', $content['features'])];
                continue;
            }

            $this->renderJpeg($content, $path);
            file_put_contents($metaPath, json_encode([
                'filename' => $filename,
                'savedAt' => date('c'),
                'content' => $content,
            ], \JSON_UNESCAPED_UNICODE | \JSON_PRETTY_PRINT));

            $rows[] = [$product->getId(), $product->getName(), $filename, $content['price'], 'OK'];
            $io->text(sprintf('[%d/%d] %s → %s', $index + 1, \count($products), $product->getName(), $filename));
        }

        $io->table(['ID', 'Product', 'File', 'Price', $dryRun ? 'Features' : 'Status'], $rows);
        $io->success($dryRun
            ? sprintf('Dry-run: %d product(s).', \count($products))
            : sprintf('Generated promo data for %d product(s) in public/uploads/promo/saved/.', \count($products)));

        return Command::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function buildContent(Product $product): array
    {
        $offer = $product->getLowestOffer();
        $price = $offer?->getPrice();
        $oldPrice = $product->getLowestOldPrice();
        $priceStr = $price !== null ? number_format($price, 0, ',', ' ').' ₴' : '';
        $oldStr = $oldPrice !== null ? number_format($oldPrice, 0, ',', ' ').' ₴' : '';
        $discount = '';
        if ($price !== null && $oldPrice !== null && $oldPrice > $price) {
            $pct = (int) round((($oldPrice - $price) / $oldPrice) * 100);
            if ($pct > 0) {
                $discount = '-'.$pct.'%';
            }
        }

        [$title, $model, $slogan, $features] = $this->resolveCopy($product);
        $productUrl = $this->resolveProductImageUrl($product);

        return [
            'canvasSize' => self::CANVAS_SIZE,
            'backgroundUrl' => '/uploads/promo/default-bg.jpg',
            'productUrl' => $productUrl,
            'brandName' => 'KVADRO',
            'brandTagline' => 'МАГАЗИН ТОВАРІВ ДЛЯ ДОМУ',
            'title' => $title,
            'model' => $model,
            'slogan' => $slogan,
            'features' => $features,
            'discount' => $discount,
            'price' => $priceStr,
            'oldPrice' => $oldStr,
            'stockText' => 'В наявності',
            'footerLeft' => 'Цифрове телебачення у вашому домі!',
            'footerRight' => 'Замовляйте на сайті kvadro.if.ua',
            'showDiscount' => $discount !== '',
            'showOldPrice' => $oldStr !== '',
            'showStock' => true,
            'featuresOffsetY' => 0,
            'productSlug' => $product->getSlug(),
            'productId' => $product->getId(),
        ];
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: list<string>}
     */
    private function resolveCopy(Product $product): array
    {
        $slug = mb_strtolower($product->getSlug());
        $sku = mb_strtolower($product->getSku());
        $name = $product->getName();

        if (str_contains($slug, 'delta') || str_contains($sku, 'delta') || str_contains(mb_strtolower($name), 'антен')) {
            return [
                'ТВ антена DVB-T2',
                'Delta 3000',
                'Більше улюблених каналів!',
                [
                    'Вбудований підсилювач',
                    'Прийом DVB-T / DVB-T2',
                    'Просте встановлення',
                    'Компактний розмір',
                    'Кабель 1,5 м',
                ],
            ];
        }

        if (str_contains($slug, 'wv') || str_contains($sku, 't624') || str_contains(mb_strtolower($name), 'приймач') || str_contains(mb_strtolower($name), 'тюнер')) {
            return [
                'Цифровий приймач DVB-T2',
                'World Vision T624',
                'ТВ без абонплати!',
                [
                    'DVB-T / DVB-T2 / DVB-C',
                    'HDMI та AV виходи',
                    'Пульт у комплекті',
                    'Просте налаштування',
                    'Підтримка USB медіа',
                ],
            ];
        }

        $model = $product->getSku() !== '' ? $product->getSku() : mb_substr($name, 0, 24);

        return [
            $name,
            $model,
            'Більше улюблених каналів!',
            [
                'Якісний товар',
                'Швидка доставка',
                'Гарантія від продавця',
                'Підтримка KVADRO',
                'Замовлення онлайн',
            ],
        ];
    }

    private function resolveProductImageUrl(Product $product): string
    {
        $gallery = $product->getGallery();
        if (isset($gallery[0]['full']) && \is_string($gallery[0]['full']) && $gallery[0]['full'] !== '') {
            $full = $gallery[0]['full'];
            if (str_starts_with($full, '/uploads/')) {
                return $full;
            }

            return '/uploads/products/'.basename($full);
        }

        $slug = $product->getSlug();
        $cutouts = [
            'delta-3000' => '/uploads/promo/products/111142.png',
            'wv-t624' => '/uploads/promo/products/11074.png',
        ];
        if (isset($cutouts[$slug]) && is_file($this->projectDir.'/public'.$cutouts[$slug])) {
            return $cutouts[$slug];
        }

        return '/uploads/promo/default-product.png';
    }

    /** @param array<string, mixed> $c */
    private function renderJpeg(array $c, string $path): void
    {
        $size = self::CANVAS_SIZE;
        $im = imagecreatetruecolor($size, $size);
        imagealphablending($im, true);

        $bg = $this->loadImage($this->projectDir.'/public'.(string) ($c['backgroundUrl'] ?? ''));
        if ($bg instanceof \GdImage) {
            $this->cover($im, $bg, 0, 0, $size, $size);
            imagedestroy($bg);
        } else {
            $fill = imagecolorallocate($im, 210, 190, 160);
            imagefilledrectangle($im, 0, 0, $size, $size, $fill);
        }

        $prod = $this->loadImage($this->projectDir.'/public'.(string) ($c['productUrl'] ?? ''));
        if ($prod instanceof \GdImage) {
            $this->contain($im, $prod, 200, 100, 325, 364);
            imagedestroy($prod);
        }

        $fontBold = $this->font(true);
        $fontReg = $this->font(false);
        $ink = imagecolorallocate($im, 26, 26, 26);
        $muted = imagecolorallocate($im, 102, 102, 102);
        $yellow = imagecolorallocate($im, 245, 197, 24);
        $green = imagecolorallocate($im, 46, 155, 58);
        $white = imagecolorallocate($im, 255, 255, 255);
        $red = imagecolorallocate($im, 214, 40, 40);
        $sloganColor = imagecolorallocate($im, 31, 42, 68);

        $offset = max(0, min(100, (int) ($c['featuresOffsetY'] ?? 0)));
        /** @var list<string> $features */
        $features = array_values(array_filter(array_map('strval', (array) ($c['features'] ?? []))));
        $features = \array_slice($features, 0, 5);

        $headerBottom = 78;
        $footerTop = 480;
        $featureStep = 32;
        $titleRel = 26;
        $modelRel = 62 + $offset;
        $featureRel = 112 + $offset;
        $lastFeatureRel = $features !== []
            ? $featureRel + (\count($features) - 1) * $featureStep
            : $modelRel + 24;
        $panelHeight = max(200, $lastFeatureRel + 28);
        $showStock = !empty($c['showStock']) && (string) ($c['stockText'] ?? '') !== '';
        $stockGap = $showStock ? 14 : 0;
        $stockH = $showStock ? 28 : 0;
        $totalBlockH = $panelHeight + $stockGap + $stockH;
        $available = $footerTop - $headerBottom;
        $panelTop = $headerBottom + max(6, (int) round(($available - $totalBlockH) / 2));
        $panelTop = max($headerBottom + 4, min($panelTop, $footerTop - $totalBlockH - 4));
        $titleY = $panelTop + $titleRel;
        $modelY = $panelTop + $modelRel;
        $featureStartY = $panelTop + $featureRel;
        $stockY = $panelTop + $panelHeight + $stockGap;

        imagefilledrectangle($im, 0, 0, $size, $headerBottom, imagecolorallocatealpha($im, 255, 255, 255, 90));
        $this->roundRect($im, 12, $panelTop, 230, $panelHeight, 10, imagecolorallocatealpha($im, 255, 255, 255, 50));

        $this->text($im, $fontBold, 23, 48, 34, (string) $c['brandName'], $ink);
        $this->text($im, $fontReg, 9, 48, 52, mb_strtoupper((string) $c['brandTagline']), $muted);
        $this->text($im, $fontBold, 14, 370, 38, (string) $c['slogan'], $sloganColor, 'center');
        $this->wrapText($im, $fontBold, 15, 22, $titleY, (string) $c['title'], $ink, 210, 20);

        $model = (string) $c['model'];
        $bbox = imagettfbbox(11, 0, $fontBold, $model);
        $modelW = max(88, ($bbox[2] - $bbox[0]) + 18);
        $this->roundRect($im, 22, $modelY, (int) $modelW, 24, 6, $yellow);
        $this->text($im, $fontBold, 11, 31, $modelY + 17, $model, $ink);

        foreach ($features as $i => $feat) {
            $y = $featureStartY + $i * $featureStep;
            imagefilledellipse($im, 34, $y, 22, 22, $yellow);
            $this->text($im, $fontReg, 10, 52, $y + 4, $feat, $ink);
        }

        if (!empty($c['showDiscount']) && (string) $c['discount'] !== '') {
            $this->burst($im, 455, 155, 32, 10, $green);
            $this->text($im, $fontBold, 12, 455, 160, (string) $c['discount'], $white, 'center');
        }
        if ((string) $c['price'] !== '') {
            $this->burst($im, 465, 230, 46, 12, $yellow);
            $this->text($im, $fontBold, 15, 465, 237, (string) $c['price'], $ink, 'center');
        }
        if (!empty($c['showOldPrice']) && (string) $c['oldPrice'] !== '') {
            $this->roundRect($im, 410, 285, 100, 28, 8, imagecolorallocatealpha($im, 255, 255, 255, 15));
            $this->text($im, $fontBold, 12, 460, 305, (string) $c['oldPrice'], $ink, 'center');
            imagesetthickness($im, 2);
            imageline($im, 425, 310, 495, 298, $red);
        }

        if ($showStock) {
            $this->roundRect($im, 18, $stockY, 130, $stockH, 8, $green);
            $this->text($im, $fontBold, 10, 28, $stockY + 19, '✓  '.$c['stockText'], $white);
        }

        imagefilledrectangle($im, 0, $footerTop, $size, $size, imagecolorallocatealpha($im, 255, 255, 255, 25));
        $this->text($im, $fontReg, 10, 14, 508, (string) $c['footerLeft'], $ink);
        $this->text($im, $fontReg, 8, $size - 14, 508, (string) $c['footerRight'].' ›', $ink, 'right');

        imagejpeg($im, $path, 92);
        imagedestroy($im);
    }

    private function loadImage(string $path): ?\GdImage
    {
        if ($path === '' || !is_file($path)) {
            return null;
        }
        $info = @getimagesize($path);
        if ($info === false) {
            return null;
        }

        return match ($info[2]) {
            \IMAGETYPE_JPEG => @imagecreatefromjpeg($path) ?: null,
            \IMAGETYPE_PNG => @imagecreatefrompng($path) ?: null,
            default => null,
        };
    }

    private function cover(\GdImage $dst, \GdImage $src, int $dx, int $dy, int $dw, int $dh): void
    {
        $sw = imagesx($src);
        $sh = imagesy($src);
        $ir = $sw / $sh;
        $tr = $dw / $dh;
        if ($ir > $tr) {
            $nw = (int) round($sh * $tr);
            $sx = (int) (($sw - $nw) / 2);
            $sy = 0;
            $sw = $nw;
        } else {
            $nh = (int) round($sw / $tr);
            $sx = 0;
            $sy = (int) (($sh - $nh) / 2);
            $sh = $nh;
        }
        imagecopyresampled($dst, $src, $dx, $dy, $sx, $sy, $dw, $dh, $sw, $sh);
    }

    private function contain(\GdImage $dst, \GdImage $src, int $dx, int $dy, int $dw, int $dh): void
    {
        $sw = imagesx($src);
        $sh = imagesy($src);
        $scale = min($dw / $sw, $dh / $sh);
        $tw = max(1, (int) round($sw * $scale));
        $th = max(1, (int) round($sh * $scale));
        $tx = $dx + (int) (($dw - $tw) / 2);
        $ty = $dy + (int) (($dh - $th) / 2);
        imagecopyresampled($dst, $src, $tx, $ty, 0, 0, $tw, $th, $sw, $sh);
    }

    private function roundRect(\GdImage $im, int $x, int $y, int $w, int $h, int $r, int $color): void
    {
        imagefilledrectangle($im, $x + $r, $y, $x + $w - $r, $y + $h, $color);
        imagefilledrectangle($im, $x, $y + $r, $x + $w, $y + $h - $r, $color);
        imagefilledellipse($im, $x + $r, $y + $r, $r * 2, $r * 2, $color);
        imagefilledellipse($im, $x + $w - $r, $y + $r, $r * 2, $r * 2, $color);
        imagefilledellipse($im, $x + $r, $y + $h - $r, $r * 2, $r * 2, $color);
        imagefilledellipse($im, $x + $w - $r, $y + $h - $r, $r * 2, $r * 2, $color);
    }

    private function burst(\GdImage $im, int $cx, int $cy, int $outerR, int $spikes, int $color): void
    {
        $inner = (int) round($outerR * 0.72);
        $points = [];
        for ($i = 0; $i < $spikes * 2; ++$i) {
            $r = $i % 2 === 0 ? $outerR : $inner;
            $a = (M_PI * $i) / $spikes - M_PI / 2;
            $points[] = (int) round($cx + cos($a) * $r);
            $points[] = (int) round($cy + sin($a) * $r);
        }
        imagefilledpolygon($im, $points, $color);
    }

    private function font(bool $bold): string
    {
        $candidates = $bold
            ? ['/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf', '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf']
            : ['/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf', '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf'];
        foreach ($candidates as $font) {
            if (is_file($font)) {
                return $font;
            }
        }

        return $candidates[0];
    }

    private function text(\GdImage $im, string $font, float $size, int $x, int $y, string $str, int $color, string $align = 'left'): void
    {
        if ($str === '' || !is_file($font)) {
            return;
        }
        $bbox = imagettfbbox($size, 0, $font, $str);
        $w = $bbox[2] - $bbox[0];
        if ($align === 'center') {
            $x = (int) round($x - $w / 2);
        }
        if ($align === 'right') {
            $x = (int) round($x - $w);
        }
        imagettftext($im, $size, 0, $x, $y, $color, $font, $str);
    }

    private function wrapText(\GdImage $im, string $font, float $size, int $x, int $y, string $str, int $color, int $maxW, int $lineH): void
    {
        if ($str === '' || !is_file($font)) {
            return;
        }
        $words = preg_split('/\s+/u', $str) ?: [];
        $line = '';
        $yy = $y;
        foreach ($words as $word) {
            $test = $line === '' ? $word : $line.' '.$word;
            $bbox = imagettfbbox($size, 0, $font, $test);
            if (($bbox[2] - $bbox[0]) > $maxW && $line !== '') {
                imagettftext($im, $size, 0, $x, $yy, $color, $font, $line);
                $line = $word;
                $yy += $lineH;
            } else {
                $line = $test;
            }
        }
        if ($line !== '') {
            imagettftext($im, $size, 0, $x, $yy, $color, $font, $line);
        }
    }
}
