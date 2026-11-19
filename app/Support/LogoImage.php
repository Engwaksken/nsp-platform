<?php

namespace App\Support;

use GdImage;

/**
 * Prepares an uploaded logo so it looks the same everywhere it appears:
 * - trims empty margins (transparent pixels, or a plain white/near-white background),
 * - scales it down to fit MAX_WIDTH × MAX_HEIGHT (twice the largest size it is shown at,
 *   so it stays sharp on high-density screens) without ever enlarging it,
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

        ob_start();
        imagepng($out, null, 9);
        $data = (string) ob_get_clean();

        return $data === '' ? null : ['data' => $data, 'width' => $outW, 'height' => $outH];
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
        $whiteBackground = $corner['alpha'] < 10 && min($corner['red'], $corner['green'], $corner['blue']) >= 240;

        $isBackground = function (int $px, int $py) use ($image, $whiteBackground): bool {
            $c = imagecolorsforindex($image, imagecolorat($image, $px, $py));
            if ($c['alpha'] >= 120) {
                return true;
            }

            return $whiteBackground && min($c['red'], $c['green'], $c['blue']) >= 240;
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
