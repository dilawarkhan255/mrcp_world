<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-base font-semibold text-gray-950 dark:text-white">What the panel may show</h2>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">{{ config('privacy.summary') }}</p>
            <dl class="mt-6 grid gap-4 sm:grid-cols-2">
                <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Study data retention</dt>
                    <dd class="mt-1 text-sm font-medium text-gray-950 dark:text-white">{{ config('privacy.retention_days') }} days</dd>
                </div>
                <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Student fields in the panel</dt>
                    <dd class="mt-1 text-sm font-medium text-gray-950 dark:text-white">
                        {{ implode(', ', (array) config('privacy.admin_visible_fields')) }}
                    </dd>
                </div>
                <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Email addresses</dt>
                    <dd class="mt-1 text-sm font-medium text-gray-950 dark:text-white">
                        {{ config('privacy.mask_email_for_editors') ? 'Masked for editors, complete for administrators' : 'Complete for every panel user' }}
                    </dd>
                </div>
                <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Passwords</dt>
                    <dd class="mt-1 text-sm font-medium text-gray-950 dark:text-white">Never shown, only stored as a hash</dd>
                </div>
            </dl>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-base font-semibold text-gray-950 dark:text-white">The notice shown to students</h2>
            <div class="mt-4 space-y-4">
                @foreach ((array) config('privacy.sections') as $section)
                    <div>
                        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $section['title'] }}</h3>
                        <p class="mt-1 text-sm leading-relaxed text-gray-600 dark:text-gray-300">{{ $section['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-filament-panels::page>
