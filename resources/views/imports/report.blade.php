<div class="space-y-4 text-sm">
    <p>Status: {{ $record->status }} · Accepted: {{ $record->imported }} · Rejected: {{ $record->rejected }}</p>
    @if ($record->failure)<p>{{ $record->failure }}</p>@endif
    @if ($record->status === 'pending')<p>Waiting for the queue worker. Locally run: php artisan queue:listen --queue=imports,default --timeout=1200 --tries=3</p>@endif
    @if ($record->source === 'pdf')
        <p>Read from a PDF. Questions that could not be read are listed below: fix them in the PDF (numbered question, options A., B., … and "Answer: B") and upload it again; MCQs already saved are updated, not duplicated. MCQs without a known subject are in "Unsorted (PDF import)" and marked Needs review.</p>
        <ul>@foreach ($record->errors ?? [] as $error)<li>Question {{ $error['row'] }}: {{ $error['message'] }}</li>@endforeach</ul>
    @else
        <p>First 100 rejected rows are shown. Correct these rows and upload them again. Matching codes update existing questions.</p>
        <ul>@foreach ($record->errors ?? [] as $error)<li>Row {{ $error['row'] }}: {{ $error['message'] }}</li>@endforeach</ul>
    @endif
</div>
