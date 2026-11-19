<?php

namespace App\Support;

use GdImage;

/**
 * Prepares an uploaded logo so it looks the same everywhere it appears:
 * - trims empty margins (transparent pixels, or a plain white/near-white background),
 * - scales it down to fit MAX_WIDTH × MAX_HEIGHT (twice the largest size it is shown at,
 *   so it stays sharp on high-density screens) without ever enlarging it,
 * - makes a plain white background transparent (white inside the logo is kept),
 * - keeps transparency and saves it as PNG.
 *
 * Returns null when the image cannot be read (or GD is missing); callers then keep the original file.
 */
class LogoImage
{
    public const MAX_WIDTH = 640;

    public const MAX_HEIGHT = 160;

    /** Larger uploads are refused before decoding: GD needs about 4 bytes per pixel in memory. */
    public const MAX_SOURCE_SIDE = 3000;

    /** A pixel whose darkest channel is at least this counts as white background. */
    private const WHITE = 240;

    /** Pixels between EDGE and WHITE next to the background are anti-aliased edges, made partly transparent. */
    private const EDGE = 200;

    /** Breathing room kept around the trimmed artwork, as a share of the shorter side. */
    private const PADDING = 0.04;

    /** @return array{data: string, width: int, height: int}|null */
    public static function prepare(string $path): ?array
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }
        $info = @getimagesize($path);
        if (! $info || $info[0] > self::MAX_SOURCE_SIDE || $info[1] > self::MAX_SOURCE_SIDE) {
            return null;
        }
        $bytes = @file_get_contents($path);
        $source = $bytes === false ? false : @imagecreatefromstring($bytes);
        unset($bytes);
        if (! $source instanceof GdImage) {
            return null;
        }
        if (! imageistruecolor($source)) {
            imagepalettetotruecolor($source);
        }
        imagealphablending($source, false);
        imagesavealpha($source, true);

        $corner = imagecolorsforindex($source, imagecolorat($source, 0, 0));
        $whiteBackground = $corner['alpha'] < 10 && min($corner['red'], $corner['green'], $corner['blue']) >= self::WHITE;

        [$x, $y, $w, $h] = self::contentBox($source);

        $pad = (int) round(min($w, $h) * self::PADDING);
        $x = max(0, $x - $pad);
        $y = max(0, $y - $pad);
        $w = min(imagesx($source) - $x, $w + 2 * $pad);
        $h = min(imagesy($source) - $y, $h + 2 * $pad);

        $scale = min(1, self::MAX_WIDTH / $w, self::MAX_HEIGHT / $h);
        $outW = max(1, (int) round($w * $scale));
        $outH = max(1, (int) round($h * $scale));

        $out = imagecreatetruecolor($outW, $outH);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
        imagecopyresampled($out, $source, 0, 0, $x, $y, $outW, $outH, $w, $h);
        unset($source);
        if ($whiteBackground) {
            // Done on the small, resized image so it stays fast and light on memory.
            self::clearWhiteBackground($out);
        }

        ob_start();
        imagepng($out, null, 9);
        $data = (string) ob_get_clean();

        return $data === '' ? null : ['data' => $data, 'width' => $outW, 'height' => $outH];
    }

    /**
     * Makes a plain white background transparent, so the logo sits on any page colour.
     * Only white reachable from the edges is removed (flood fill), so white shapes or
     * letters inside the logo stay. The soft, anti-aliased border pixels become partly
     * transparent instead of leaving a white halo.
     */
    private static function clearWhiteBackground(GdImage $image): void
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $whiteness = function (int $px, int $py) use ($image): int {
            $c = imagecolorsforindex($image, imagecolorat($image, $px, $py));

            return $c['alpha'] >= 120 ? 255 : min($c['red'], $c['green'], $c['blue']);
        };

        $seen = [];
        $queue = [];
        for ($px = 0; $px < $width; $px++) {
            $queue[] = [$px, 0];
            $queue[] = [$px, $height - 1];
        }
        for ($py = 0; $py < $height; $py++) {
            $queue[] = [0, $py];
            $queue[] = [$width - 1, $py];
        }

        while ($queue) {
            [$px, $py] = array_pop($queue);
            $key = $py * $width + $px;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $level = $whiteness($px, $py);
            if ($level < self::EDGE) {
                continue; // The logo itself: stop here.
            }
            $c = imagecolorsforindex($image, imagecolorat($image, $px, $py));
            // Pure white becomes fully clear; near-white edge pixels fade with how white they are.
            $alpha = $level >= self::WHITE ? 127 : max($c['alpha'], (int) round(127 * ($level - self::EDGE) / (self::WHITE - self::EDGE)));
            imagesetpixel($image, $px, $py, imagecolorallocatealpha($image, $c['red'], $c['green'], $c['blue'], $alpha));
            if ($level < self::WHITE) {
                continue; // A soft edge pixel: fade it, but do not spread into the logo through it.
            }
            foreach ([[1, 0], [-1, 0], [0, 1], [0, -1]] as [$dx, $dy]) {
                $nx = $px + $dx;
                $ny = $py + $dy;
                if ($nx >= 0 && $ny >= 0 && $nx < $width && $ny < $height && ! isset($seen[$ny * $width + $nx])) {
                    $queue[] = [$nx, $ny];
                }
            }
        }
    }

    /**
     * The smallest box holding everything that is not background.
     * Background = fully transparent, or the colour of the top-left corner when it is near-white.
     *
     * @return array{0: int, 1: int, 2: int, 3: int} x, y, width, height
     */
    private static function contentBox(GdImage $image): array
    {
        $width = imagesx($image);
        $height = imagesy($image);
        // Large images are scanned on a grid so the trim stays fast; the box is then widened by one step.
        $step = max(1, (int) floor(max($width, $height) / 800));

        $corner = imagecolorsforindex($image, imagecolorat($image, 0, 0));
        $whiteBackground = $corner['alpha'] < 10 && min($corner['red'], $corner['green'], $corner['blue']) >= self::WHITE;

        $isBackground = function (int $px, int $py) use ($image, $whiteBackground): bool {
            $c = imagecolorsforindex($image, imagecolorat($image, $px, $py));
            if ($c['alpha'] >= 120) {
                return true;
            }

            return $whiteBackground && min($c['red'], $c['green'], $c['blue']) >= self::WHITE;
        };

        $minX = $width;
        $minY = $height;
        $maxX = -1;
        $maxY = -1;
        for ($py = 0; $py < $height; $py += $step) {
            for ($px = 0; $px < $width; $px += $step) {
                if (! $isBackground($px, $py)) {
                    $minX = min($minX, $px);
                    $maxX = max($maxX, $px);
                    $minY = min($minY, $py);
                    $maxY = max($maxY, $py);
                }
            }
        }

        if ($maxX < 0) {
            return [0, 0, $width, $height]; // Nothing but background: keep the whole image.
        }
        $minX = max(0, $minX - $step);
        $minY = max(0, $minY - $step);
        $maxX = min($width - 1, $maxX + $step);
        $maxY = min($height - 1, $maxY + $step);

        return [$minX, $minY, $maxX - $minX + 1, $maxY - $minY + 1];
    }
}
