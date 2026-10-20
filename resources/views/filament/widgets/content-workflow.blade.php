<x-filament-widgets::widget>
    <x-filament::section heading="Manage learning content">
        <div class="space-y-4 text-sm">
            <p>Create categories, add or import MCQs, then publish the content for students.</p>
            <div class="flex flex-wrap gap-3">
                <x-filament::button href="/admin/questions" tag="a">Manage MCQs</x-filament::button>
                <x-filament::button href="/admin/question-imports" tag="a" color="gray">Bulk upload CSV / XLSX</x-filament::button>
                <x-filament::button href="/admin/past-papers" tag="a" color="gray">Past papers</x-filament::button>
                <x-filament::button href="/admin/categories" tag="a" color="gray">Categories</x-filament::button>
                <x-filament::button href="/questions/bank" tag="a" color="gray">Open student question bank</x-filament::button>
            </div>
            <ol class="list-decimal space-y-2 pl-5">
                <li>Create the exam categories first. The category slug goes in each import row.</li>
                <li>For a past paper, create a paper and select it when adding or importing its questions.</li>
                <li>Download the template on Bulk MCQ uploads, fill it, then upload. Check the progress and rejected-row report.</li>
                <li>Publish questions and the paper. Students can then start practice and resume their saved sessions.</li>
            </ol>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
