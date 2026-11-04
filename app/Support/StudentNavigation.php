<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Exam;

/**
 * Builds the student sidebar: the fixed items from config('student.navigation')
 * plus one subject per category of the current exam that has published questions.
 */
final class StudentNavigation
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function for(?Exam $exam, bool $locked = false): array
    {
        $items = self::markLocked(config('student.navigation', []), $locked);
        if ($exam === null) {
            return $items;
        }

        $subjects = Category::where('exam_id', $exam->id)
            ->whereHas('questions', fn ($query) => $query->published())
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        foreach ($items as $index => $item) {
            if (! ($item['subjects'] ?? false)) {
                continue;
            }
            foreach ($subjects as $subject) {
                $items[$index]['children'][] = [
                    'title' => $subject->name,
                    'icon' => 'Folder',
                    'href' => null,
                    'children' => [
                        ['title' => 'Main MCQ Bank', 'icon' => 'Library', 'href' => "/questions/subjects/{$subject->slug}"],
                        ['title' => 'Past Paper MCQs', 'icon' => 'FileClock', 'href' => "/questions/subjects/{$subject->slug}/past-papers", 'locked' => $locked],
                    ],
                ];
            }
        }

        return $items;
    }

    /**
     * Mark the items that need a subscription as locked for a student without one.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private static function markLocked(array $items, bool $locked): array
    {
        foreach ($items as $index => $item) {
            $items[$index]['locked'] = $locked && ($item['subscription'] ?? false);
            unset($items[$index]['subscription']);
            if (isset($item['children'])) {
                $items[$index]['children'] = self::markLocked($item['children'], $locked);
            }
        }

        return $items;
    }

    /**
     * Every fixed href in the sidebar, without the per-exam subject links.
     *
     * @return list<string>
     */
    public static function staticHrefs(): array
    {
        $hrefs = [];
        $collect = function (array $items) use (&$collect, &$hrefs): void {
            foreach ($items as $item) {
                if (! empty($item['href'])) {
                    $hrefs[] = $item['href'];
                }
                $collect($item['children'] ?? []);
            }
        };
        $collect(config('student.navigation', []));

        return array_values(array_unique($hrefs));
    }
}
