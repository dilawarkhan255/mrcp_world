<?php

use App\Models\Exam;
use App\Models\Qbank;
use App\Models\Subscription;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Livewire;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/** Give a student an active subscription to a Qbank (manual activation, as an admin would). */
function grantAccess(User $user, Qbank|int $qbank, int $days = 180): Subscription
{
    return Subscription::create([
        'user_id' => $user->id, 'qbank_id' => $qbank instanceof Qbank ? $qbank->id : $qbank,
        'status' => Subscription::STATUS_ACTIVE, 'starts_at' => now()->subMinute(), 'ends_at' => now()->addDays($days),
    ]);
}

/** An exam in the owner's Qbank that reads the owner's question bank (content_exam_id). */
function linkedExam(Exam $owner, string $name = 'Linked exam', int $sortOrder = 2): Exam
{
    return Exam::create([
        'name' => $name, 'slug' => Str::slug($name), 'qbank_id' => $owner->qbank_id, 'content_exam_id' => $owner->id,
        'sort_order' => $sortOrder, 'is_active' => true,
    ]);
}

/**
 * The rows an admin list (Yajra DataTable, App\Filament\Concerns\UsesYajraTable)
 * returns for the signed-in user, as the browser would request them.
 *
 * @param  class-string<Component>  $page
 * @return list<array<string, string>>
 */
function yajraRows(string $page, array $params = []): array
{
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    return Livewire::test($page)->instance()
        ->yajraRows(array_merge(['draw' => 1, 'start' => 0, 'length' => 100, 'search' => '', 'sort' => '', 'dir' => ''], $params))['data'];
}

/** Every cell of an admin list as one HTML string, for assertions on what is shown. */
function yajraHtml(string $page, array $params = []): string
{
    return collect(yajraRows($page, $params))->flatten()->implode("\n");
}
