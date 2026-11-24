<?php

namespace App\Services\Books;

use Symfony\Component\Process\Process;
use Throwable;

class PrepareBookCover
{
    public function fromImage(?string $contents): ?string
    {
        if ($contents === null || strlen($contents) > 8 * 1024 * 1024) {
            return null;
        }
        $info = @getimagesizefromstring($contents);
        if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)
            || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > 12000000) {
            return null;
        }
        $source = @imagecreatefromstring($contents);
        if ($source === false) {
            return null;
        }
        $scale = min(1, 1200 / max($info[0], $info[1]));
        $target = imagecreatetruecolor(max(1, (int) ($info[0] * $scale)), max(1, (int) ($info[1] * $scale)));
        try {
            $white = imagecolorallocate($target, 255, 255, 255);
            imagefill($target, 0, 0, $white);
            imagecopyresampled($target, $source, 0, 0, 0, 0, imagesx($target), imagesy($target), $info[0], $info[1]);
            ob_start();
            try {
                if (! imagejpeg($target, null, 85)) {
                    return null;
                }

                return ob_get_contents() ?: null;
            } finally {
                ob_end_clean();
            }
        } finally {
            imagedestroy($source);
            imagedestroy($target);
        }
    }

    public function fromPdf(string $path): ?string
    {
        $temp = tempnam(sys_get_temp_dir(), 'book-cover-');
        if ($temp === false) {
            return null;
        }
        try {
            $process = new Process(['pdftoppm', '-f', '1', '-l', '1', '-singlefile', '-scale-to', '1200', '-jpeg', $path, $temp]);
            $process->setTimeout(15);
            $process->run();
            if (! $process->isSuccessful() || ! is_file($temp.'.jpg') || filesize($temp.'.jpg') > 8 * 1024 * 1024) {
                return null;
            }

            return $this->fromImage(file_get_contents($temp.'.jpg') ?: null);
        } catch (Throwable) {
            // A missing or unreadable cover must not discard otherwise useful metadata.
            return null;
        } finally {
            @unlink($temp);
            @unlink($temp.'.jpg');
        }
    }
}
