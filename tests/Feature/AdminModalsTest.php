<?php

use App\Filament\Resources\PastPaperResource\Pages\ManagePastPapers;
use App\Filament\Resources\QuestionResource\Pages\ManageQuestions;
use App\Models\Category;
use App\Models\Exam;
use App\Models\Question;
use App\Models\User;
use Database\Seeders\ExamSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([ExamSeeder::class, RoleSeeder::class]);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('opens every Create, Edit and View window as a centred modal with a sticky header and footer', function () {
    foreach ([Actions\CreateAction::make(), Actions\EditAction::make(), Tables\Actions\EditAction::make(), Tables\Actions\ViewAction::make(), Tables\Actions\CreateAction::make()] as $action) {
        expect($action->isModalSlideOver())->toBeFalse()
            ->and($action->isModalHeaderSticky())->toBeTrue()
            ->and($action->isModalFooterSticky())->toBeTrue()
            ->and($action->getModalIcon())->not->toBeNull();
    }
    // Forms keep typed data when the backdrop is clicked; a read-only view may close.
    expect(Tables\Actions\EditAction::make()->isModalClosedByClickingAway())->toBeFalse()
        ->and(Tables\Actions\ViewAction::make()->isModalClosedByClickingAway())->toBeTrue();
});

it('keeps the wide MCQ editor centred', function () {
    $exam = Exam::where('slug', 'mrcp-part-1')->firstOrFail();
    $category = Category::create(['exam_id' => $exam->id, 'name' => 'Cardiology', 'slug' => 'cardiology']);
    $question = Question::create(['exam_id' => $exam->id, 'category_id' => $category->id, 'code' => 'M-1', 'body' => 'Stem', 'options' => ['A' => 'One', 'B' => 'Two'], 'correct_option' => 'A']);

    $component = Livewire::actingAs($this->admin)->test(ManageQuestions::class)->mountTableAction('edit', $question);
    $action = $component->instance()->getMountedTableAction();

    expect($action->isModalSlideOver())->toBeFalse()
        ->and($action->getModalWidth())->toBe(MaxWidth::FiveExtraLarge);
});

it('gives admin form fields a placeholder from their label unless they set their own', function () {
    Livewire::actingAs($this->admin)->test(ManagePastPapers::class)
        ->mountAction('create')
        ->assertSeeHtml('placeholder="Enter slug (filled from the title)"')
        ->assertSeeHtml('placeholder="Enter year"')
        // A field with its own placeholder keeps it.
        ->assertSeeHtml('placeholder="e.g. MRCP 1 — September 2024"');
});

it('marks required fields with a red asterisk in the student app', function () {
    expect(file_get_contents(resource_path('js/app.ts')))->toContain('watchRequiredLabels()')
        ->and(file_get_contents(resource_path('js/lib/required-labels.ts')))->toContain('input[required]')
        ->and(file_get_contents(resource_path('css/app.css')))->toContain('.mw-required-mark');
});
