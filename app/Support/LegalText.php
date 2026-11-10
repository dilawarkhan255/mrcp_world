<?php

namespace App\Support;

/**
 * Fills the owner details from config/legal.php into the Terms of use and
 * the Privacy notice (:service, :owner, :email, :address, :law), so both
 * texts, on the site and in the admin panel, show the same details.
 */
class LegalText
{
    /**
     * A legal document from config: its summary and sections, filled in.
     *
     * @return array{summary: string, sections: list<array{title: string, body: string}>, updated: string}
     */
    public static function document(string $key): array
    {
        $sections = [];
        foreach ((array) config($key.'.sections') as $section) {
            $sections[] = ['title' => self::fill((string) ($section['title'] ?? '')), 'body' => self::fill((string) ($section['body'] ?? ''))];
        }

        return [
            'summary' => self::fill((string) config($key.'.summary')),
            'sections' => $sections,
            'updated' => (string) config('legal.updated'),
        ];
    }

    public static function fill(string $text): string
    {
        return strtr($text, [
            ':service' => (string) config('legal.service'),
            ':owner' => (string) config('legal.owner'),
            ':email' => (string) config('legal.email'),
            ':address' => (string) config('legal.address'),
            ':law' => (string) config('legal.governing_law'),
        ]);
    }
}
