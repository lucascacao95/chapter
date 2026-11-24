<?php

namespace App\Services\Books;

use Kiwilan\Ebook\Ebook;
use RuntimeException;

class ReadEpubMetadata
{
    public function read(string $path): array
    {
        // Keep our bounded ZIP/XML validation before handing untrusted data to the library.
        $original = app(EpubReader::class)->read($path);
        $ebook = Ebook::read($path, 'epub');
        if (! $ebook || $ebook->isBadFile()) {
            throw new RuntimeException('invalid_epub');
        }

        $authors = [];
        foreach ($ebook->getAuthors() as $author) {
            if (in_array(strtolower($author->getRole() ?? ''), ['', 'aut', 'author'], true)) {
                $name = trim($author->getName() ?? '');
                if ($name !== '') {
                    $authors[] = $name;
                }
            }
        }
        $metadata = array_filter([
            // The library has a filename fallback; only accept an actual bibliographic title.
            'title' => isset($original['title']) ? trim($ebook->getTitle() ?? '') : null,
            'authors' => array_values(array_unique($authors)),
            'publisher' => trim($ebook->getPublisher() ?? ''),
            'description' => trim(strip_tags($ebook->getDescription() ?? '')),
            'language' => trim($ebook->getLanguage() ?? ''),
            'published_year' => $ebook->getPublishDate() ? (int) $ebook->getPublishDate()->format('Y') : null,
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);
        foreach ($ebook->getIdentifiers() as $identifier) {
            if (in_array(strtolower($identifier->getScheme() ?? ''), ['isbn', 'isbn10', 'isbn13', 'urn:isbn'], true)) {
                $metadata['isbn'] = preg_replace('/^urn:isbn:/i', '', $identifier->getValue() ?? '');
                break;
            }
        }

        // EPUB pages are fluid. Do not store the library's estimated page count as an editorial count.
        return ['metadata' => $metadata, 'cover' => $ebook->getCover()?->getContents()];
    }
}
