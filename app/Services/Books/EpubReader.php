<?php

namespace App\Services\Books;

use DOMDocument;
use DOMXPath;
use RuntimeException;
use ZipArchive;

class EpubReader
{
    public function read(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY | ZipArchive::CHECKCONS) !== true) {
            throw new RuntimeException('invalid_epub');
        }

        try {
            if ($zip->numFiles > 10000 || $this->entry($zip, 'mimetype', 100) !== 'application/epub+zip'
                || $zip->locateName('META-INF/encryption.xml') !== false) {
                throw new RuntimeException('invalid_epub');
            }
            $total = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if ($stat === false || ($stat['encryption_method'] ?? 0) !== 0) {
                    throw new RuntimeException('invalid_epub');
                }
                // Bound every entry before the library can allocate its contents.
                if ($stat['size'] > 8 * 1024 * 1024) {
                    throw new RuntimeException('invalid_epub');
                }
                if (in_array(strtolower(pathinfo($stat['name'], PATHINFO_EXTENSION)), ['xml', 'opf'], true)) {
                    $this->xml($this->entry($zip, $stat['name']));
                }
                $total += $stat['size'];
                if ($total > 200 * 1024 * 1024) {
                    throw new RuntimeException('invalid_epub');
                }
            }
            $container = $this->xml($this->entry($zip, 'META-INF/container.xml'));
            $opfPath = (string) $container->evaluate('string(//*[local-name()="rootfile"]/@full-path)');
            if (! in_array(strtolower(pathinfo($opfPath, PATHINFO_EXTENSION)), ['opf', 'xml'], true)) {
                throw new RuntimeException('invalid_epub');
            }
            $opf = $this->xml($this->entry($zip, $opfPath));
            if ($opf->document->documentElement?->localName !== 'package'
                || $opf->query('//*[local-name()="manifest"]/*[local-name()="item"]')->length === 0
                || $opf->query('//*[local-name()="spine"]/*[local-name()="itemref"]')->length === 0) {
                throw new RuntimeException('invalid_epub');
            }
            foreach ($opf->query('//*[local-name()="manifest"]/*[local-name()="item"]/@href') as $href) {
                $entry = $this->resolve($opfPath, $href->nodeValue);
                if ($zip->locateName($entry) === false) {
                    throw new RuntimeException('invalid_epub');
                }
            }
            $value = fn (string $field) => trim((string) $opf->evaluate('string(//*[local-name()="metadata"]/*[namespace-uri()="http://purl.org/dc/elements/1.1/" and local-name()="'.$field.'"][1])'));
            $authors = [];
            foreach ($opf->query('//*[local-name()="metadata"]/*[namespace-uri()="http://purl.org/dc/elements/1.1/" and local-name()="creator"]') as $creator) {
                if (trim($creator->textContent) !== '') {
                    $authors[] = trim($creator->textContent);
                }
            }
            $metadata = array_filter([
                'title' => $value('title'), 'authors' => $authors,
                'publisher' => $value('publisher'), 'description' => strip_tags($value('description')),
                'language' => $value('language'),
            ], fn ($value) => $value !== '' && $value !== []);
            if (preg_match('/^(\d{4})(?:-|$)/', $value('date'), $year) && (int) $year[1] > 0) {
                $metadata['published_year'] = (int) $year[1];
            }
            foreach ($opf->query('//*[local-name()="metadata"]/*[local-name()="identifier"]') as $identifier) {
                $isbn = trim($identifier->textContent);
                if (preg_match('/^(?:urn:isbn:)?([0-9Xx -]{10,32})$/', $isbn, $match)) {
                    $metadata['isbn'] = $match[1];
                    break;
                }
            }

            return $metadata;
        } finally {
            $zip->close();
        }
    }

    private function entry(ZipArchive $zip, string $name, int $limit = 2097152): string
    {
        if ($name === '' || str_contains($name, "\0") || str_contains($name, '\\') || str_starts_with($name, '/') || in_array('..', explode('/', $name), true)) {
            throw new RuntimeException('invalid_epub');
        }
        $stat = $zip->statName($name);
        if ($stat === false || $stat['size'] > $limit) {
            throw new RuntimeException('invalid_epub');
        }
        $stream = $zip->getStream($name);
        if ($stream === false) {
            throw new RuntimeException('invalid_epub');
        }
        try {
            $data = stream_get_contents($stream, $limit + 1);
            if ($data === false || strlen($data) > $limit || strlen($data) !== $stat['size']) {
                throw new RuntimeException('invalid_epub');
            }

            return $data;
        } finally {
            fclose($stream);
        }
    }

    private function xml(string $data): DOMXPath
    {
        if (stripos($data, '<!DOCTYPE') !== false || stripos($data, '<!ENTITY') !== false) {
            throw new RuntimeException('invalid_epub');
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument;
            if (! $document->loadXML($data, LIBXML_NONET | LIBXML_COMPACT) || $document->doctype !== null) {
                throw new RuntimeException('invalid_epub');
            }

            return new DOMXPath($document);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function resolve(string $opfPath, string $href): string
    {
        $href = rawurldecode(explode('#', $href)[0]);
        if ($href === '' || preg_match('~^[a-z][a-z0-9+.-]*:|^/|[\\\\\x00]~i', $href)) {
            throw new RuntimeException('invalid_epub');
        }
        $parts = explode('/', (dirname($opfPath) === '.' ? '' : dirname($opfPath).'/').$href);
        $normalized = [];
        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                if ($normalized === []) {
                    throw new RuntimeException('invalid_epub');
                }
                array_pop($normalized);
            } else {
                $normalized[] = $part;
            }
        }

        return implode('/', $normalized);
    }
}
