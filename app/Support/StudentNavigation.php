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
    public static function for(?Exam $exam): array
    {
        $items = config('student.navigation', []);
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
                    'href' => null,
                    'children' => [
                        ['title' => 'Main MCQ Bank', 'href' => "/questions/subjects/{$subject->slug}"],
                        ['title' => 'Past Paper MCQs', 'href' => "/questions/subjects/{$subject->slug}/past-papers"],
                    ],
                ];
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
