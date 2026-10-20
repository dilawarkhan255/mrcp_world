<div class="space-y-4 text-sm">
    <p>Status: {{ $record->status }} · Accepted: {{ $record->imported }} · Rejected: {{ $record->rejected }}</p>
    @if ($record->failure)<p>{{ $record->failure }}</p>@endif
    @if ($record->status === 'pending')<p>Waiting for the queue worker. Locally run: php artisan queue:listen --queue=imports,default --timeout=1200 --tries=3</p>@endif
    <p>First 100 rejected rows are shown. Correct these rows and upload them again. Matching codes update existing questions.</p>
    <ul>@foreach ($record->errors ?? [] as $error)<li>Row {{ $error['row'] }}: {{ $error['message'] }}</li>@endforeach</ul>
</div>
