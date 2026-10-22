<?php

namespace Dominservice\MediaKit\Tests;

class CreatesImage
{
    public static function makePngTemp(int $w = 64, int $h = 64): string
    {
        $img = imagecreatetruecolor($w, $h);
        $bg = imagecolorallocate($img, 240, 240, 240);
        imagefilledrectangle($img, 0, 0, $w, $h, $bg);
        $black = imagecolorallocate($img, 0, 0, 0);
        imagestring($img, 2, 5, 5, 'T', $black);

        $tmp = tempnam(sys_get_temp_dir(), 'img_').'.png';
        imagepng($img, $tmp);
        imagedestroy($img);

        return $tmp;
    }

    public static function makeJpegTemp(int $w = 64, int $h = 64): string
    {
        $img = imagecreatetruecolor($w, $h);

        for ($y = 0; $y < $h; $y += 8) {
            $color = imagecolorallocate($img, $y % 255, ($y * 2) % 255, ($y * 3) % 255);
            imagefilledrectangle($img, 0, $y, $w, min($h - 1, $y + 7), $color);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'img_').'.jpg';
        imagejpeg($img, $tmp, 96);
        imagedestroy($img);

        return $tmp;
    }
}
