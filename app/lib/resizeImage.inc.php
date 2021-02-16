<?php
declare(strict_types=1);

/**
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 */

/**
 * ändert die Größe eines Bildes unter Beibehaltung des Seitenverhältnisses
 *
 * @param string $source Quell-Pfad
 * @param string $dest Ziel-Pfad
 * @param string $type Dateityp ("png" oder "jpeg")
 * @param int $maxWidth
 * @param int $maxHeight
 */
function resizeImage(string $source, string $dest, string $type, int $maxWidth, int $maxHeight)
{
    switch ($type) {
    case 'png':
        $im = imageCreateFromPng($source);
        break;
    case 'jpeg':
        $im = imageCreateFromJPEG($source);
        break;
    default:
        die('unknown image type: ' . $type);
    }

    // Groesse festlegen
    $tW = $maxWidth;
    $tH = (int)round(min($maxHeight, imageSY($im) * $tW / imageSX($im)));
    $tW = (int)round(min($maxWidth, imageSX($im) * $tH / imageSY($im)));

    // neues, transparentes Bild erstellen
    $thumb = imageCreateTrueColor($tW, $tH);
    imageAlphaBlending($thumb, false);
    imageSaveAlpha($thumb, true);
    $transparent = imageColorAllocateAlpha($thumb, 255, 255, 255, 127);
    imageFilledRectangle($thumb, 0, 0, $tW, $tH, $transparent);

    imageCopyResampled($thumb, $im, 0, 0, 0, 0, $tW, $tH, imageSX($im), imageSY($im));

    if ($type === 'png') {
        imagePng($thumb, $dest);
    } else {
        imageJpeg($thumb, $dest);
    }

    return [$tW, $tH];
}
