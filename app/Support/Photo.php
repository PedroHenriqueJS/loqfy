<?php

namespace App\Support;

use RuntimeException;

class Photo
{
    public static function resizedJpeg(string $path, int $maxSide): string
    {
        $bytes = file_get_contents($path);
        $image = $bytes === false ? false : @imagecreatefromstring($bytes);

        $orientation = @exif_read_data($path)['Orientation'] ?? 1;

        if ($image === false && ($converted = self::heicToJpeg($path)) !== null) {
            [$jpeg, $orientation] = $converted;
            $image = @imagecreatefromstring($jpeg);
        }

        if ($image === false) {
            throw new RuntimeException("Could not read image at {$path}. Use JPG, PNG or WebP.");
        }

        $image = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, $maxSide / max($width, $height));

        if ($scale < 1) {
            $image = imagescale($image, (int) round($width * $scale), (int) round($height * $scale));
        }

        ob_start();
        imagejpeg($image, null, 82);

        return (string) ob_get_clean();
    }

    private static function heicToJpeg(string $path): ?array
    {
        if (! preg_match('/\.hei[cf]$/i', $path) || ! is_executable('/usr/bin/sips')) {
            return null;
        }

        $target = tempnam(sys_get_temp_dir(), 'heic').'.jpg';
        exec('/usr/bin/sips -s format jpeg '.escapeshellarg($path).' --out '.escapeshellarg($target).' 2>&1', $output, $status);
        $bytes = $status === 0 && is_file($target) ? file_get_contents($target) : false;
        $orientation = $bytes === false ? 1 : (@exif_read_data($target)['Orientation'] ?? 1);
        @unlink($target);

        return $bytes === false ? null : [$bytes, (int) $orientation];
    }
}
