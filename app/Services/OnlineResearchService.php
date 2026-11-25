<?php

namespace App\Services;

use App\Models\ComparisonSource;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;
use Smalot\PdfParser\Parser as PdfParser;
use Throwable;

class OnlineResearchService
{
    /**
     * Melakukan Paragraph-by-Paragraph Deep Sweep:
     * Memindai setiap paragraf utama dari awal hingga akhir dokumen secara paralel
     * ke jurnal ilmiah (OpenAlex, Crossref, DOAJ, Semantic Scholar) dan web/blog/repository.
     */
    public function research(string $text, string $title = ''): array
    {
        @set_time_limit(240);

        $queries = $this->extractParagraphQueries($text, $title);
        $discoveredSources = [];
        $seenUrls = [];
        $urlsToDeepScrape = [];

        if (empty($queries)) {
            return [];
        }

        // Jalankan pencarian API & Web secara paralel dalam batch (5 kueri per gelombang)
        foreach (array_chunk($queries, 5) as $batchIndex => $batchQueries) {
            $responses = Http::pool(function (Pool $pool) use ($batchQueries, $batchIndex) {
                foreach ($batchQueries as $qIdx => $query) {
                    $keyPrefix = "b{$batchIndex}_q{$qIdx}";
                    $shortQuery = mb_substr($query, 0, 150);

                    // 1. OpenAlex (Semua batch)
                    $pool->as("{$keyPrefix}_oa")
                        ->timeout(8)
                        ->withHeaders(['User-Agent' => 'OriginalityCheck/3.0 (mailto:research@local.app)'])
                        ->get('https://api.openalex.org/works', [
                            'search' => $shortQuery,
                            'per-page' => 3,
                            'select' => 'title,doi,primary_location,open_access,abstract_inverted_index',
                        ]);

                    // 2. Crossref (Batch 0-3 / hingga 20 paragraf)
                    if ($batchIndex < 4) {
                        $pool->as("{$keyPrefix}_cr")
                            ->timeout(8)
                            ->withHeaders(['User-Agent' => 'OriginalityCheck/3.0 (mailto:research@local.app)'])
                            ->get('https://api.crossref.org/works', [
                                'query.bibliographic' => $shortQuery,
                                'rows' => 3,
                                'select' => 'title,DOI,URL,abstract,link',
                            ]);
                    }

                    // 3. DOAJ / Jurnal SINTA Open Access (Batch 0-2 / hingga 15 paragraf)
                    if ($batchIndex < 3) {
                        $encoded = rawurlencode(mb_substr($query, 0, 110));
                        $pool->as("{$keyPrefix}_doaj")
                            ->timeout(8)
                            ->get("https://doaj.org/api/search/articles/{$encoded}", [
                                'pageSize' => 3,
                            ]);
                    }

                    // 4. Semantic Scholar (Batch 0-1 / hingga 10 paragraf utama)
                    if ($batchIndex < 2) {
                        $pool->as("{$keyPrefix}_ss")
                            ->timeout(8)
                            ->withHeaders(['User-Agent' => 'OriginalityCheck/3.0'])
                            ->get('https://api.semanticscholar.org/graph/v1/paper/search', [
                                'query' => mb_substr($query, 0, 130),
                                'limit' => 3,
                                'fields' => 'title,url,abstract,openAccessPdf',
                            ]);
                    }

                    // 5. Web & Blog & Repository Kampus (Semua batch, kueri 9 kata agar menembus blokir)
                    $webWords = array_slice(preg_split('/\s+/u', $query) ?: [], 0, 9);
                    $pool->as("{$keyPrefix}_web")
                        ->timeout(8)
                        ->withHeaders([
                            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/126.0.0.0 Safari/537.36',
                            'Accept-Language' => 'id-ID,id;q=0.9,en-US;q=0.8,en;q=0.7',
                        ])
                        ->get('https://lite.duckduckgo.com/lite/', [
                            'q' => implode(' ', $webWords),
                        ]);
                }
            });

            // Parsing seluruh hasil gelombang paralel
            foreach ($responses as $key => $response) {
                if ($response instanceof Throwable || !$response->successful()) {
                    continue;
                }

                if (str_ends_with($key, '_oa')) {
                    foreach ($this->parseOpenAlex($response->json('results', [])) as $src) {
                        $this->registerSource($src, $discoveredSources, $seenUrls, $urlsToDeepScrape);
                    }
                } elseif (str_ends_with($key, '_cr')) {
                    foreach ($this->parseCrossref($response->json('message.items', [])) as $src) {
                        $this->registerSource($src, $discoveredSources, $seenUrls, $urlsToDeepScrape);
                    }
                } elseif (str_ends_with($key, '_doaj')) {
                    foreach ($this->parseDoaj($response->json('results', [])) as $src) {
                        $this->registerSource($src, $discoveredSources, $seenUrls, $urlsToDeepScrape);
                    }
                } elseif (str_ends_with($key, '_ss')) {
                    foreach ($this->parseSemanticScholar($response->json('data', [])) as $src) {
                        $this->registerSource($src, $discoveredSources, $seenUrls, $urlsToDeepScrape);
                    }
                } elseif (str_ends_with($key, '_web')) {
                    foreach ($this->parseWebHtml($response->body(), 'Internet / Blog / Repository') as $src) {
                        $this->registerSource($src, $discoveredSources, $seenUrls, $urlsToDeepScrape);
                    }
                }
            }
        }

        // Deep Scraping Paralel hingga 35 halaman/PDF yang ditemukan dari seluruh paragraf
        if (!empty($urlsToDeepScrape)) {
            $chunks = array_chunk($urlsToDeepScrape, 18, true);
            foreach (array_slice($chunks, 0, 2) as $urlBatch) {
                $discoveredSources = $this->deepScrapePages($discoveredSources, $urlBatch);
            }
        }

        $this->cacheSourcesToDatabase($discoveredSources);

        return $discoveredSources;
    }

    /**
     * Memecah dokumen per paragraf dan mengambil kalimat representatif dari TIAP paragraf
     * (hingga 25 paragraf utama dari Bab 1 sampai Penutup).
     */
    private function extractParagraphQueries(string $text, string $title): array
    {
        $queries = [];

        if (trim($title) !== '' && mb_strlen(trim($title)) > 12) {
            $queries[] = trim($title);
        }

        // Pisahkan dokumen berdasarkan paragraf (\n\n atau baris baru)
        $paragraphs = preg_split('/\R{2,}|\R/u', $text) ?: [];

        foreach ($paragraphs as $para) {
            $cleanPara = trim(preg_replace('/\s+/u', ' ', $para));
            $paraWords = preg_split('/\s+/u', $cleanPara) ?: [];

            // Lewati baris pendek (judul bab, nomor halaman, header tabel)
            if (count($paraWords) < 15) {
                continue;
            }

            if (preg_match('/^(bab\s+[ivx0-9]+|daftar\s+|tabel\s+|gambar\s+|halaman\s+|\d+\.)/i', $cleanPara)) {
                continue;
            }

            // Pecah paragraf menjadi kalimat, ambil kalimat paling padat informasi (10-22 kata)
            $sentences = preg_split('/(?<=[.?!])\s+/u', $cleanPara) ?: [$cleanPara];
            $chosenSentence = '';

            foreach ($sentences as $sent) {
                $sWords = preg_split('/\s+/u', trim($sent)) ?: [];
                if (count($sWords) >= 9) {
                    $chosenSentence = implode(' ', array_slice($sWords, 0, 14));
                    break;
                }
            }

            if ($chosenSentence === '') {
                $chosenSentence = implode(' ', array_slice($paraWords, 0, 14));
            }

            $queries[] = $chosenSentence;

            // Batasi maksimal 25 kueri unik per dokumen agar tetap cepat & komprehensif
            if (count($queries) >= 25) {
                break;
            }
        }

        return array_values(array_unique($queries));
    }

    private function registerSource(array $src, array &$discoveredSources, array &$seenUrls, array &$urlsToDeepScrape): void
    {
        $url = trim($src['url'] ?? '');
        $title = html_entity_decode(trim($src['title'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $content = html_entity_decode(trim($src['content'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $src['title'] = $title;
        $src['content'] = $content;

        $key = $url !== '' ? $url : md5($title);
        if (isset($seenUrls[$key])) {
            return;
        }

        $seenUrls[$key] = true;
        $idx = count($discoveredSources);
        $discoveredSources[$idx] = $src;

        if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL) && mb_strlen($content) < 1200) {
            $urlsToDeepScrape[$idx] = $url;
        }
    }

    private function parseOpenAlex(array $items): array
    {
        $results = [];
        foreach ($items as $item) {
            $title = $item['title'] ?? 'OpenAlex Publication';
            $abstract = $this->reconstructOpenAlexAbstract($item['abstract_inverted_index'] ?? null);
            $content = trim($title . '. ' . $abstract);

            if (mb_strlen($content) < 35) {
                continue;
            }

            $url = ($item['open_access']['oa_url'] ?? null)
                ?: (($item['primary_location']['landing_page_url'] ?? null) ?: ($item['doi'] ?? ''));

            $results[] = [
                'id' => 'oa_' . md5($url ?: $title),
                'title' => $title,
                'url' => $url,
                'source_type' => 'Publication (OpenAlex)',
                'content' => $content,
            ];
        }
        return $results;
    }

    private function reconstructOpenAlexAbstract(?array $invertedIndex): string
    {
        if (empty($invertedIndex)) {
            return '';
        }

        $words = [];
        foreach ($invertedIndex as $word => $positions) {
            foreach ($positions as $pos) {
                $words[$pos] = $word;
            }
        }
        ksort($words);

        return implode(' ', $words);
    }

    private function parseCrossref(array $items): array
    {
        $results = [];
        foreach ($items as $item) {
            $title = is_array($item['title'] ?? null) ? ($item['title'][0] ?? '') : ($item['title'] ?? '');
            $abstract = strip_tags($item['abstract'] ?? '');
            $content = trim($title . '. ' . $abstract);

            if (mb_strlen($content) < 35) {
                continue;
            }

            $url = $item['URL'] ?? (isset($item['DOI']) ? 'https://doi.org/' . $item['DOI'] : '');
            if (!empty($item['link'][0]['URL'])) {
                $url = $item['link'][0]['URL'];
            }

            $results[] = [
                'id' => 'cr_' . md5($url ?: $title),
                'title' => $title ?: 'Crossref Journal',
                'url' => $url,
                'source_type' => 'Journal / DOI (Crossref)',
                'content' => $content,
            ];
        }
        return $results;
    }

    private function parseDoaj(array $items): array
    {
        $results = [];
        foreach ($items as $item) {
            $bib = $item['bibjson'] ?? [];
            $title = $bib['title'] ?? '';
            $abstract = $bib['abstract'] ?? '';
            $content = trim($title . '. ' . $abstract);

            if (mb_strlen($content) < 35) {
                continue;
            }

            $url = $bib['link'][0]['url'] ?? '';
            $results[] = [
                'id' => 'doaj_' . md5($url ?: $title),
                'title' => $title ?: 'DOAJ / SINTA Journal',
                'url' => $url,
                'source_type' => 'Open Access Journal (DOAJ)',
                'content' => $content,
            ];
        }
        return $results;
    }

    private function parseSemanticScholar(array $items): array
    {
        $results = [];
        foreach ($items as $item) {
            $title = $item['title'] ?? '';
            $abstract = $item['abstract'] ?? '';
            $content = trim($title . '. ' . $abstract);

            if (mb_strlen($content) < 35) {
                continue;
            }

            $url = $item['openAccessPdf']['url'] ?? ($item['url'] ?? '');
            $results[] = [
                'id' => 'ss_' . md5($url ?: $title),
                'title' => $title ?: 'Semantic Scholar Paper',
                'url' => $url,
                'source_type' => 'Semantic Scholar',
                'content' => $content,
            ];
        }
        return $results;
    }

    private function parseWebHtml(string $html, string $sourceLabel): array
    {
        $results = [];
        preg_match_all(
            '/<a[^>]*class=["\']result-link["\'][^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>.*?<td[^>]*class=["\']result-snippet["\'][^>]*>(.*?)<\/td>/si',
            $html,
            $matches,
            PREG_SET_ORDER
        );

        foreach (array_slice($matches, 0, 4) as $m) {
            $rawUrl = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (str_contains($rawUrl, 'uddg=')) {
                parse_str(parse_url($rawUrl, PHP_URL_QUERY) ?? '', $qs);
                $rawUrl = $qs['uddg'] ?? $rawUrl;
            }

            $title = trim(strip_tags(html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            $snippet = trim(strip_tags(html_entity_decode($m[3], ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            $content = trim($title . '. ' . $snippet);

            if (mb_strlen($content) < 35) {
                continue;
            }

            $results[] = [
                'id' => 'web_' . md5($rawUrl ?: $title),
                'title' => $title ?: 'Web Source',
                'url' => $rawUrl,
                'source_type' => $sourceLabel,
                'content' => $content,
            ];
        }

        return $results;
    }

    private function deepScrapePages(array $sources, array $urlMap): array
    {
        try {
            $responses = Http::pool(function (Pool $pool) use ($urlMap) {
                foreach ($urlMap as $idx => $url) {
                    $pool->as((string) $idx)
                        ->timeout(7)
                        ->withOptions(['verify' => false, 'allow_redirects' => true])
                        ->withHeaders([
                            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/126.0 Safari/537.36',
                        ])
                        ->get($url);
                }
            });

            foreach ($responses as $idxStr => $response) {
                $idx = (int) $idxStr;
                if (!isset($sources[$idx]) || $response instanceof Throwable || !$response->successful()) {
                    continue;
                }

                $contentType = strtolower($response->header('Content-Type') ?? '');
                $url = $sources[$idx]['url'];

                if (str_contains($contentType, 'application/pdf') || str_ends_with(strtolower(parse_url($url, PHP_URL_PATH) ?? ''), '.pdf')) {
                    $extracted = $this->extractRemotePdf($response->body());
                } else {
                    $extracted = $this->extractCleanHtmlText($response->body());
                }

                if (mb_strlen($extracted) > mb_strlen($sources[$idx]['content'])) {
                    $sources[$idx]['content'] = mb_substr($sources[$idx]['title'] . ". \n" . $extracted, 0, 25000);
                }
            }
        } catch (Throwable) {
        }

        return $sources;
    }

    private function extractRemotePdf(string $binary): string
    {
        if (strlen($binary) < 500 || strlen($binary) > 8 * 1024 * 1024) {
            return '';
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'turnitin_src_');
        if (!$tmpFile) {
            return '';
        }

        try {
            file_put_contents($tmpFile, $binary);
            $text = (new PdfParser())->parseFile($tmpFile)->getText();
            $text = preg_replace('/\s+/u', ' ', $text) ?? '';
            return trim($text);
        } catch (Throwable) {
            return '';
        } finally {
            @unlink($tmpFile);
        }
    }

    private function extractCleanHtmlText(string $html): string
    {
        if ($html === '') {
            return '';
        }

        $clean = preg_replace('/<(script|style|nav|header|footer|aside|noscript)[^>]*>.*?<\/\1>/si', ' ', $html) ?? $html;
        $text = strip_tags(html_entity_decode($clean, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = preg_replace('/\s+/u', ' ', $text) ?? '';

        return trim($text);
    }

    private function cacheSourcesToDatabase(array $sources): void
    {
        foreach ($sources as $src) {
            $url = trim($src['url'] ?? '');
            if ($url === '' || mb_strlen($src['content']) < 120) {
                continue;
            }

            try {
                $existing = ComparisonSource::where('url', $url)->first();
                if (!$existing) {
                    ComparisonSource::create([
                        'title' => mb_substr($src['title'], 0, 250),
                        'source_type' => mb_substr($src['source_type'], 0, 100),
                        'url' => mb_substr($url, 0, 500),
                        'content' => $src['content'],
                        'is_active' => true,
                    ]);
                } elseif (mb_strlen($src['content']) > mb_strlen($existing->content)) {
                    $existing->update(['content' => $src['content']]);
                }
            } catch (Throwable) {
            }
        }
    }
}