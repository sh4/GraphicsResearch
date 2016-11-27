<?php

namespace GraphicsResearch;

class Image {
    static public function grayScaleBitmap($image, $filename = null) {
        $bitsPerPixel = 8;

        $biWidth = imagesx($image);
        $biHeight = imagesy($image);
        $biBytePerLine = $biWidth;
        $biStride = ($biBytePerLine + 3) & ~3; // 4 バイトアライン
        $biSizeImage = $biStride * $biHeight;

        // ファイル先頭から pixel array までのオフセット (byte) =
        //   BITMAPFILEHADER(14byte) +
        //   BITMAPINFOHEADER(40byte) +
        //   PALETTE(4byte * 256)
        $bfOffBits = 14 + 40 + (256 * 4);
        $bfSize = $bfOffBits + $biSizeImage;

        $f = fopen($filename === null ? "php://output" : $filename, "wb");
        if (!$f) {
            return false;
        }

        // BITMAPFILEHEADER
        fwrite($f, "BM", 2);
        fwrite($f, pack('VvvV', $bfSize, 0, 0, $bfOffBits));
        // BITMAPINFOHEADER
        fwrite($f, pack('VVVvvVVVVVV', 40, $biWidth, $biHeight, 1, $bitsPerPixel, 0, $biSizeImage, 0, 0, 0, 0));
        // PALETTE
        $palette = "";
        for ($i = 0; $i < 256; $i++) {
            $palette .= pack('CCCC', $i, $i, $i, 0);
        }
        fwrite($f, $palette);
        // PIXEL ARRAY
        $pixels = "";
        for ($y = $biHeight - 1; $y >= 0; $y--) {
            for ($x = 0; $x < $biWidth; $x++) {
                $color = imagecolorat($image, $x, $y);
                $pixels .= pack('C', $color&0xff);
            }
        }
        fwrite($f, $pixels);

        if ($filename !== null) {
            fclose($f);
        }

        return true;
    }
}

