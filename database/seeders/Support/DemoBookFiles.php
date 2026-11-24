<?php

namespace Database\Seeders\Support;

use Illuminate\Http\UploadedFile;
use RuntimeException;
use ZipArchive;

class DemoBookFiles
{
    /** The caller must unlink the temporary upload after use. No third-party content is included. */
    public function make(string $format, string $title): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'feed-demo-');
        if ($path === false) {
            throw new RuntimeException('demo_file_failed');
        }
        try {
            if ($format === 'epub') {
                $this->epub($path, $title);
            } elseif ($format === 'pdf') {
                $this->pdf($path, $title);
            } else {
                throw new RuntimeException('invalid_demo_format');
            }

            return new UploadedFile($path, $title.'.'.$format, null, null, true);
        } catch (\Throwable $error) {
            @unlink($path);
            throw $error;
        }
    }

    private function epub(string $path, string $title): void
    {
        $title = htmlspecialchars($title, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $image = imagecreatetruecolor(600, 900);
        imagefill($image, 0, 0, imagecolorallocate($image, 220, 232, 217));
        $ink = imagecolorallocate($image, 52, 84, 58);
        imagestring($image, 5, 70, 180, 'MINHA BIBLIOTECA', $ink);
        imagestring($image, 5, 70, 420, 'Uma leitura de exemplo', $ink);
        imagestring($image, 3, 70, 720, 'Conteudo sintetico para testes', $ink);
        ob_start();
        imagejpeg($image, null, 85);
        $cover = ob_get_clean();
        imagedestroy($image);
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('demo_zip_failed');
        }
        try {
            $zip->addFromString('mimetype', 'application/epub+zip');
            $zip->setCompressionName('mimetype', ZipArchive::CM_STORE);
            $zip->addFromString('META-INF/container.xml', '<?xml version="1.0"?><container version="1.0" xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="OEBPS/content.opf" media-type="application/oebps-package+xml"/></rootfiles></container>');
            $zip->addFromString('OEBPS/content.opf', '<?xml version="1.0" encoding="UTF-8"?><package xmlns="http://www.idpf.org/2007/opf" unique-identifier="id" version="2.0"><metadata xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:identifier id="id">feed-demo</dc:identifier><dc:title>'.$title.'</dc:title><dc:creator>Autoria de demonstracao</dc:creator><dc:language>pt-BR</dc:language><meta name="cover" content="cover"/></metadata><manifest><item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/><item id="cover" href="cover.jpg" media-type="image/jpeg"/><item id="ncx" href="toc.ncx" media-type="application/x-dtbncx+xml"/></manifest><spine toc="ncx"><itemref idref="chapter"/></spine></package>');
            $zip->addFromString('OEBPS/chapter.xhtml', '<?xml version="1.0"?><html xmlns="http://www.w3.org/1999/xhtml"><head><title>Exemplo</title></head><body><h1>Uma leitura de exemplo</h1><p>Este texto foi criado exclusivamente para testar a biblioteca e o feed.</p></body></html>');
            $zip->addFromString('OEBPS/cover.jpg', $cover);
            $zip->addFromString('OEBPS/toc.ncx', '<?xml version="1.0"?><ncx xmlns="http://www.daisy.org/z3986/2005/ncx/" version="2005-1"><head/><docTitle><text>Exemplo</text></docTitle><navMap><navPoint id="chapter" playOrder="1"><navLabel><text>Exemplo</text></navLabel><content src="chapter.xhtml"/></navPoint></navMap></ncx>');
        } finally {
            $zip->close();
        }
    }

    private function pdf(string $path, string $title): void
    {
        $title = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $title);
        $content = "0.86 0.91 0.85 rg 0 0 420 595 re f\n0.2 0.33 0.23 rg\nBT /F1 24 Tf 45 440 Td (Leitura de exemplo) Tj /F1 12 Tf 0 -45 Td (Conteudo sintetico para testes.) Tj ET";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 420 595] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Length '.strlen($content).">>\nstream\n".$content."\nendstream",
            '<< /Title ('.$title.') /Author (Autoria de demonstracao) >>',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 7\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= "trailer\n<< /Size 7 /Root 1 0 R /Info 6 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
        if (file_put_contents($path, $pdf) === false) {
            throw new RuntimeException('demo_pdf_failed');
        }
    }
}
