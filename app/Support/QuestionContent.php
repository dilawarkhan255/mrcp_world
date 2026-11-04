<?php

namespace App\Support;

use App\Models\Question;
use Illuminate\Support\Str;

/**
 * Renders and normalises the rich content of an MCQ.
 *
 * Question text, explanation, key points and mnemonics are Markdown. They are
 * rendered with raw HTML stripped and unsafe links (javascript:, data:)
 * removed, so the output can be shown with v-html. GitHub-flavoured Markdown
 * gives tables, and ![alt](url) gives images uploaded in the admin editor.
 */
final class QuestionContent
{
    private const MARKDOWN_OPTIONS = [
        'html_input' => 'strip',
        'allow_unsafe_links' => false,
        'max_nesting_level' => 20,
        'renderer' => ['soft_break' => "<br>\n"],
    ];

    /** Markdown to safe HTML, or null for empty text. */
    public static function html(?string $markdown): ?string
    {
        if ($markdown === null || trim($markdown) === '') {
            return null;
        }

        return trim((string) Str::markdown($markdown, self::MARKDOWN_OPTIONS));
    }

    /** The video id of a YouTube watch, share, shorts or embed link. */
    public static function youtubeId(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }
        $pattern = '~^https?://(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})(?:[?&#].*)?$~';

        return preg_match($pattern, trim($url), $match) === 1 ? $match[1] : null;
    }

    /**
     * Keep references that have a title. The editor writes the citation text;
     * nothing here invents or completes a citation.
     *
     * @param  array<int|string, mixed>|null  $references
     * @return list<array{source: string, title: string, url: string|null, year: string|null}>|null
     */
    public static function cleanReferences(?array $references): ?array
    {
        $clean = [];
        foreach ($references ?? [] as $reference) {
            if (! is_array($reference)) {
                continue;
            }
            $title = trim((string) ($reference['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $source = (string) ($reference['source'] ?? 'Other');
            $clean[] = [
                'source' => array_key_exists($source, Question::REFERENCE_SOURCES) ? $source : 'Other',
                'title' => Str::limit($title, 500, ''),
                'url' => self::safeUrl($reference['url'] ?? null),
                'year' => self::year($reference['year'] ?? null),
            ];
        }

        return $clean === [] ? null : array_slice($clean, 0, 20);
    }

    /**
     * Keep searchable image links with a valid http(s) address.
     *
     * @param  array<int|string, mixed>|null  $links
     * @return list<array{label: string, url: string}>|null
     */
    public static function cleanLinks(?array $links): ?array
    {
        $clean = [];
        foreach ($links ?? [] as $link) {
            if (! is_array($link)) {
                continue;
            }
            $url = self::safeUrl($link['url'] ?? null);
            if ($url === null) {
                continue;
            }
            $label = trim((string) ($link['label'] ?? ''));
            $clean[] = ['label' => Str::limit($label !== '' ? $label : (parse_url($url, PHP_URL_HOST) ?: 'Image'), 120, ''), 'url' => $url];
        }

        return $clean === [] ? null : array_slice($clean, 0, 20);
    }

    /** A trimmed search term of at most 150 characters, or null. */
    public static function cleanSearchTerm(?string $term): ?string
    {
        $term = trim(preg_replace('/\s+/', ' ', (string) $term) ?? '');

        return $term === '' ? null : mb_substr($term, 0, 150);
    }

    /**
     * Known image search sites in config order. Null (every site) when none or all are chosen.
     *
     * @param  array<int|string, mixed>|null  $sites
     * @return list<string>|null
     */
    public static function cleanSearchSites(?array $sites): ?array
    {
        $known = array_keys((array) config('student.image_search'));
        $chosen = array_values(array_intersect($known, array_map('strval', $sites ?? [])));

        return $chosen === [] || count($chosen) === count($known) ? null : $chosen;
    }

    /**
     * Live search links on the configured image sites for a term.
     *
     * @param  array<int|string, mixed>|null  $sites
     * @return list<array{site: string, label: string, url: string}>
     */
    public static function searchLinks(?string $term, ?array $sites): array
    {
        $term = self::cleanSearchTerm($term);
        if ($term === null) {
            return [];
        }
        $chosen = self::cleanSearchSites($sites);
        $links = [];
        foreach ((array) config('student.image_search') as $site => $provider) {
            if ($chosen !== null && ! in_array($site, $chosen, true)) {
                continue;
            }
            $links[] = ['site' => (string) $site, 'label' => (string) $provider['label'], 'url' => str_replace('{q}', rawurlencode($term), (string) $provider['url'])];
        }

        return $links;
    }

    /**
     * The answer content shown once a question is answered (or a mock closes).
     *
     * @param  array<string, mixed>  $snapshot
     * @return array{explanation: string|null, explanation_html: string|null, key_points_html: string|null, mnemonics_html: string|null, references: list<array{source: string, title: string, url: string|null, year: string|null}>, youtube_id: string|null, image_links: list<array{label: string, url: string}>, image_search: string|null, image_searches: list<array{site: string, label: string, url: string}>}
     */
    public static function reveal(array $snapshot): array
    {
        $explanation = isset($snapshot['explanation']) ? (string) $snapshot['explanation'] : null;

        return [
            'explanation' => $explanation,
            'explanation_html' => self::html($explanation),
            'key_points_html' => self::html(isset($snapshot['key_points']) ? (string) $snapshot['key_points'] : null),
            'mnemonics_html' => self::html(isset($snapshot['mnemonics']) ? (string) $snapshot['mnemonics'] : null),
            'references' => self::cleanReferences(is_array($snapshot['references'] ?? null) ? $snapshot['references'] : null) ?? [],
            'youtube_id' => self::youtubeId(isset($snapshot['youtube_url']) ? (string) $snapshot['youtube_url'] : null),
            'image_links' => self::cleanLinks(is_array($snapshot['image_links'] ?? null) ? $snapshot['image_links'] : null) ?? [],
            'image_search' => self::cleanSearchTerm(isset($snapshot['image_search']) ? (string) $snapshot['image_search'] : null),
            'image_searches' => self::searchLinks(
                isset($snapshot['image_search']) ? (string) $snapshot['image_search'] : null,
                is_array($snapshot['image_search_sites'] ?? null) ? $snapshot['image_search_sites'] : null,
            ),
        ];
    }

    private static function safeUrl(mixed $url): ?string
    {
        if (! is_string($url)) {
            return null;
        }
        $url = trim($url);
        if ($url === '' || strlen($url) > 2048 || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true) ? $url : null;
    }

    private static function year(mixed $year): ?string
    {
        $year = trim((string) ($year ?? ''));

        return preg_match('/^(19|20)\d{2}$/', $year) === 1 ? $year : null;
    }
}
