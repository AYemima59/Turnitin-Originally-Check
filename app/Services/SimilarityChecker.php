<?php

namespace App\Services;

use App\Models\ComparisonSource;

class SimilarityChecker
{
    public function __construct(
        private readonly OnlineResearchService $researchService
    ) {}

    public function check(string $text, string $title = '', array $options = []): array
    {
        $excludeBibliography = $options['exclude_bibliography'] ?? true;
        $excludeQuotes = $options['exclude_quotes'] ?? false;
        $minWords = max(4, (int) ($options['min_words'] ?? 6));
        $skipOnline = $options['skip_online'] ?? false;

        $bodyText = $text;
        $bibliographyText = '';
        if ($excludeBibliography && preg_match('/\R\s*(daftar\s+pustaka|references|bibliography)\s*\R/iu', $text, $m, PREG_OFFSET_CAPTURE)) {
            $offset = $m[0][1];
            $bodyText = substr($text, 0, $offset);
            $bibliographyText = substr($text, $offset);
        }

        $tokens = $this->tokenizeWithPositions($bodyText);
        $total = count($tokens);

        if ($total === 0) {
            return [
                'score' => 0,
                'matched_words' => 0,
                'total_words' => 0,
                'matches' => [],
                'segments' => [['text' => $text, 'matched' => false, 'source_num' => null, 'word_count' => 0, 'in_quote' => false]],
            ];
        }

        $words = array_column($tokens, 'norm');

        // Deteksi posisi tanda petik ("...") pada setiap token untuk mendukung Live Toggle Exclude Quotes
        $quoteIndices = [];
        preg_match_all('/["“][^"”]{15,}["”]/u', $bodyText, $quoteMatches, PREG_OFFSET_CAPTURE);
        foreach ($quoteMatches[0] as [$qStr, $qStart]) {
            $qEnd = $qStart + strlen($qStr);
            foreach ($tokens as $idx => $tok) {
                if ($tok['start'] >= $qStart && $tok['end'] <= $qEnd) {
                    $quoteIndices[$idx] = true;
                }
            }
        }

        $allSources = [];
        foreach (ComparisonSource::query()->where('is_active', true)->get() as $local) {
            $allSources[] = [
                'id' => $local->id,
                'title' => $local->title,
                'url' => $local->url,
                'source_type' => $local->source_type ?: 'Internal Repository',
                'content' => $local->content,
            ];
        }

        if (!$skipOnline) {
            foreach ($this->researchService->research($bodyText, $title) as $online) {
                $allSources[] = $online;
            }
        }

        $rawMatches = [];
        $tokenSources = [];

        foreach ($allSources as $sIdx => $source) {
            $sourceWords = $this->normalizeWords($source['content']);
            if (count($sourceWords) < $minWords) {
                continue;
            }

            $sourceNgrams = [];
            for ($i = 0; $i <= count($sourceWords) - $minWords; $i++) {
                $gram = implode(' ', array_slice($sourceWords, $i, $minWords));
                $sourceNgrams[$gram] = true;
            }

            $foundRanges = [];
            $sourceMatchedIndices = [];

            for ($i = 0; $i <= $total - $minWords; $i++) {
                if ($excludeQuotes) {
                    $skip = false;
                    for ($k = $i; $k < $i + $minWords; $k++) {
                        if (isset($quoteIndices[$k])) {
                            $skip = true;
                            break;
                        }
                    }
                    if ($skip) {
                        continue;
                    }
                }

                $gram = implode(' ', array_slice($words, $i, $minWords));
                if (isset($sourceNgrams[$gram])) {
                    for ($j = $i; $j < $i + $minWords; $j++) {
                        $sourceMatchedIndices[$j] = true;
                        $tokenSources[$j][] = $sIdx;
                    }
                    $foundRanges[] = [
                        'start_word' => $i,
                        'end_word' => $i + $minWords - 1,
                        'phrase' => implode(' ', array_slice($words, $i, $minWords)),
                    ];
                }
            }

            if (!empty($sourceMatchedIndices)) {
                $matchedCountForSource = count($sourceMatchedIndices);
                $rawMatches[$sIdx] = [
                    'source_id' => $source['id'],
                    'title' => $source['title'],
                    'url' => $source['url'],
                    'source_type' => $source['source_type'],
                    'matched_words' => $matchedCountForSource,
                    'percentage' => max(1, (int) round(($matchedCountForSource / $total) * 100)),
                    'exact_percentage' => round(($matchedCountForSource / $total) * 100, 2),
                    'ranges' => $foundRanges,
                ];
            }
        }

        uasort($rawMatches, fn ($a, $b) => $b['matched_words'] <=> $a['matched_words']);

        $sIdxToRank = [];
        $matches = [];
        $rank = 1;
        foreach ($rawMatches as $sIdx => $mData) {
            $sIdxToRank[$sIdx] = $rank;
            $mData['rank'] = $rank;
            $matches[] = $mData;
            $rank++;
        }

        $coveredRank = [];
        foreach ($tokenSources as $wordIdx => $candidateSIndices) {
            $bestRank = null;
            foreach ($candidateSIndices as $sIdx) {
                if (isset($sIdxToRank[$sIdx])) {
                    $r = $sIdxToRank[$sIdx];
                    if ($bestRank === null || $r < $bestRank) {
                        $bestRank = $r;
                    }
                }
            }
            if ($bestRank !== null) {
                $coveredRank[$wordIdx] = $bestRank;
            }
        }

        $matched = count($coveredRank);
        $score = min(100, round(($matched / $total) * 100, 2));

        $segments = $this->buildPreservedSegments($bodyText, $tokens, $coveredRank, $quoteIndices);
        if ($bibliographyText !== '') {
            $segments[] = [
                'text' => $bibliographyText,
                'matched' => false,
                'source_num' => null,
                'word_count' => 0,
                'in_quote' => false,
            ];
        }

        return [
            'score' => $score,
            'matched_words' => $matched,
            'total_words' => $total,
            'matches' => $matches,
            'segments' => $segments,
        ];
    }

    private function tokenizeWithPositions(string $text): array
    {
        preg_match_all('/[\p{L}\p{N}]+/u', $text, $matches, PREG_OFFSET_CAPTURE);
        $tokens = [];
        foreach ($matches[0] ?? [] as [$rawWord, $offset]) {
            $tokens[] = [
                'raw' => $rawWord,
                'norm' => mb_strtolower($rawWord),
                'start' => $offset,
                'end' => $offset + strlen($rawWord),
            ];
        }
        return $tokens;
    }

    private function normalizeWords(string $text): array
    {
        preg_match_all('/[\p{L}\p{N}]+/u', mb_strtolower($text), $m);
        return $m[0] ?? [];
    }

    private function buildPreservedSegments(string $bodyText, array $tokens, array $coveredRank, array $quoteIndices): array
    {
        $segments = [];
        $cursor = 0;
        $totalTokens = count($tokens);

        $i = 0;
        while ($i < $totalTokens) {
            if (!isset($coveredRank[$i])) {
                $i++;
                continue;
            }

            $currentRank = $coveredRank[$i];
            $isQuote = isset($quoteIndices[$i]);
            $startOffset = $tokens[$i]['start'];
            $endOffset = $tokens[$i]['end'];
            $wordCount = 1;

            while (
                $i + 1 < $totalTokens &&
                isset($coveredRank[$i + 1]) &&
                $coveredRank[$i + 1] === $currentRank &&
                (isset($quoteIndices[$i + 1]) === $isQuote)
            ) {
                $i++;
                $endOffset = $tokens[$i]['end'];
                $wordCount++;
            }

            if ($startOffset > $cursor) {
                $segments[] = [
                    'text' => substr($bodyText, $cursor, $startOffset - $cursor),
                    'matched' => false,
                    'source_num' => null,
                    'word_count' => 0,
                    'in_quote' => false,
                ];
            }

            $segments[] = [
                'text' => substr($bodyText, $startOffset, $endOffset - $startOffset),
                'matched' => true,
                'source_num' => $currentRank,
                'word_count' => $wordCount,
                'in_quote' => $isQuote,
            ];

            $cursor = $endOffset;
            $i++;
        }

        if ($cursor < strlen($bodyText)) {
            $segments[] = [
                'text' => substr($bodyText, $cursor),
                'matched' => false,
                'source_num' => null,
                'word_count' => 0,
                'in_quote' => false,
            ];
        }

        return $segments;
    }
}