<?php

$width = 300;
$height = 80;

$img = imagecreatetruecolor($width, $height);

$bgColor = imagecolorallocate($img, 255, 255, 255);
$textColor = imagecolorallocate($img, 15, 118, 110);
$borderColor = imagecolorallocate($img, 15, 118, 110);

imagefill($img, 0, 0, $bgColor);

$borderWidth = 3;
imagerectangle($img, 0, 0, $width - 1, $height - 1, $borderColor);

imagerectangle($img, 0, 0, $width - 1, $borderWidth, $borderColor);
imagerectangle($img, 0, $height - $borderWidth, $width - 1, $height - 1, $borderColor);
imagerectangle($img, 0, 0, $borderWidth, $height - 1, $borderColor);
imagerectangle($img, $width - $borderWidth, 0, $width - 1, $height - 1, $borderColor);

$text = 'Plattadata';
$fontSize = 5;
$fontWidth = imagefontwidth($fontSize);
$textWidth = $fontWidth * strlen($text);
$x = ($width - $textWidth) / 2;
$y = ($height - imagefontheight($fontSize)) / 2;

imagestring($img, $fontSize, $x, $y, $text, $textColor);

$dir = __DIR__ . '/public/img';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

imagepng($img, __DIR__ . '/public/img/logo.png');
imagedestroy($img);

echo 'Logo created at public/img/logo.png';