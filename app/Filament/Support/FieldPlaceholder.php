<?php

namespace App\Filament\Support;

use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Str;

/**
 * A default placeholder for every admin form field, made from its label
 * ("Title" → "Enter title"; an email field → "name@example.com"). Registered
 * in AdminPanelProvider::boot(); a field that sets its own placeholder
 * ("e.g. MRCP 1 — September 2024") keeps it. Required fields already get a
 * red asterisk from Filament.
 */
final class FieldPlaceholder
{
    public static function for(Field $field): string
    {
        $label = trim(strip_tags((string) $field->getLabel()));
        if ($label === '') {
            return $field instanceof Select ? 'Select an option' : 'Type here…';
        }
        $thing = Str::lower($label);

        if ($field instanceof Select) {
            return 'Select '.Str::lower(Str::singular($label));
        }
        if ($field instanceof TextInput) {
            if ($field->isEmail()) {
                return 'name@example.com';
            }
            if ($field->isPassword()) {
                return '••••••••';
            }
            if ($field->isUrl()) {
                return 'https://…';
            }
            if ($field->isTel()) {
                return 'e.g. 0300 1234567';
            }
        }

        return (in_array(class_basename($field), ['Textarea', 'MarkdownEditor', 'RichEditor'], true) ? 'Write ' : 'Enter ').$thing.($thing === 'slug' ? ' (filled from the title)' : '');
    }
}
