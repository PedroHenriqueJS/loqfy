<?php

namespace App\Support;

class PhotoFingerprint
{
    public const SAME_PHOTO = 10;

    public static function of(string $path): string
    {
        $image = imagecreatefromstring(Photo::resizedJpeg($path, 256));
        $small = imagecreatetruecolor(9, 8);
        imagecopyresampled($small, $image, 0, 0, 0, 0, 9, 8, imagesx($image), imagesy($image));

        $bits = '';
        for ($y = 0; $y < 8; $y++) {
            $previous = self::luminance($small, 0, $y);
            for ($x = 1; $x < 9; $x++) {
                $current = self::luminance($small, $x, $y);
                $bits .= $current > $previous ? '1' : '0';
                $previous = $current;
            }
        }

        return implode('', array_map(fn (string $nibble) => dechex(bindec($nibble)), str_split($bits, 4)));
    }

    public static function distance(string $a, string $b): int
    {
        $count = 0;
        for ($i = 0; $i < 16; $i++) {
            $count += substr_count(decbin(hexdec($a[$i]) ^ hexdec($b[$i])), '1');
        }

        return $count;
    }

    private static function luminance(\GdImage $image, int $x, int $y): float
    {
        $rgb = imagecolorat($image, $x, $y);

        return 0.299 * (($rgb >> 16) & 255) + 0.587 * (($rgb >> 8) & 255) + 0.114 * ($rgb & 255);
    }
}
