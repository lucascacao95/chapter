<?php

namespace App\Services\Books;

use RuntimeException;
use Symfony\Component\Process\Process;

class PdfReader
{
    public function read(string $path): array
    {
        $stream = fopen($path, 'rb');
        try {
            if ($stream === false || fread($stream, 5) !== '%PDF-') {
                throw new RuntimeException('invalid_pdf');
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
        $process = new Process(['pdfinfo', '-enc', 'UTF-8', $path], env: ['LC_ALL' => 'C']);
        $process->setTimeout(15);
        $process->run();
        if (! $process->isSuccessful()) {
            throw new RuntimeException('invalid_pdf');
        }
        $fields = [];
        foreach (explode("\n", $process->getOutput()) as $line) {
            if (preg_match('/^([^:]+):\s*(.*)$/u', $line, $match)) {
                $fields[$match[1]] = trim($match[2]);
            }
        }
        if (($fields['Encrypted'] ?? 'no') !== 'no' || (int) ($fields['Pages'] ?? 0) < 1) {
            throw new RuntimeException('invalid_pdf');
        }
        $metadata = ['page_count' => (int) $fields['Pages']];
        if (! empty($fields['Title'])) {
            $metadata['title'] = $fields['Title'];
        }
        if (! empty($fields['Author'])) {
            $metadata['authors'] = [$fields['Author']];
        }

        return $metadata;
    }
}
