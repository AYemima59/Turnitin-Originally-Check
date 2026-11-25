<?php

namespace App\Services;

use Smalot\PdfParser\Parser as PdfParser;
use RuntimeException;
use ZipArchive;
use DOMDocument;
use DOMXPath;

class DocumentTextExtractor
{
    public function extract(string $path, string $extension): string
    {
        $text = match (strtolower($extension)) {
            'pdf' => (new PdfParser())->parseFile($path)->getText(),
            'docx' => $this->extractDocx($path),
            default => throw new RuntimeException(
                'Format file tidak didukung. Gunakan PDF atau DOCX.'
            ),
        };

        $text = html_entity_decode(
            $text,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        $text = preg_replace(
            '/\{[^{}]*(?:SEQ|PAGE|NUMPAGES|REF|TOC|HYPERLINK)[^{}]*\}/i',
            '',
            $text
        ) ?? $text;

        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\R{3,}/u', "\n\n", $text) ?? $text;
        $text = trim($text);

        if (mb_strlen($text) < 30) {
            throw new RuntimeException(
                'Teks tidak berhasil dibaca. Pastikan PDF bukan hasil scan gambar atau gunakan DOCX.'
            );
        }

        return $text;
    }

    private function extractDocx(string $path): string
    {
        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            throw new RuntimeException('File DOCX tidak dapat dibuka.');
        }

        try {
            $xml = $zip->getFromName('word/document.xml');

            if ($xml === false) {
                throw new RuntimeException(
                    'Struktur dokumen Word tidak ditemukan.'
                );
            }

            $dom = new DOMDocument();

            $previous = libxml_use_internal_errors(true);

            try {
                if (!$dom->loadXML($xml, LIBXML_NONET)) {
                    throw new RuntimeException(
                        'Struktur XML dokumen Word tidak valid.'
                    );
                }
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            }

            $xpath = new DOMXPath($dom);

            $xpath->registerNamespace(
                'w',
                'http://schemas.openxmlformats.org/wordprocessingml/2006/main'
            );

            $paragraphs = $xpath->query('//w:p');

            $parts = [];

            foreach ($paragraphs as $paragraph) {
                $paragraphText = '';

                foreach ($paragraph->childNodes as $child) {
                    $this->collectWordText($child, $paragraphText);
                }

                $paragraphText = trim($paragraphText);

                if ($paragraphText !== '') {
                    $parts[] = $paragraphText;
                }
            }

            return implode("\n", $parts);

        } finally {
            $zip->close();
        }
    }

    private function collectWordText(
        \DOMNode $node,
        string &$text
    ): void {
        if ($node instanceof \DOMElement) {
            $localName = $node->localName;

            if ($localName === 't') {
                $text .= $node->textContent;
                return;
            }

            if ($localName === 'tab') {
                $text .= ' ';
                return;
            }

            if ($localName === 'br' || $localName === 'cr') {
                $text .= ' ';
                return;
            }
        }

        foreach ($node->childNodes as $child) {
            $this->collectWordText($child, $text);
        }
    }
}
