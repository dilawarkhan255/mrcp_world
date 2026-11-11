<?php

use App\Models\Exam;
use App\Models\User;
use Database\Seeders\ExamSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(ExamSeeder::class);
});

it('reads the Qbank of an exam from the database once per request', function () {
    $exam = Exam::where('slug', 'mrcp-part-1')->firstOrFail();
    DB::enableQueryLog();

    expect(Exam::qbankIdOf($exam->id))->toBe($exam->qbank_id)
        ->and(Exam::qbankIdOf($exam->id))->toBe($exam->qbank_id);

    expect(collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'qbank_id'))->count())->toBe(1);
});

it('does not look up an exam with an empty id when none is chosen yet', function () {
    $student = User::factory()->create();
    grantAccess($student, Exam::where('slug', 'mrcp-part-1')->value('qbank_id'));
    DB::enableQueryLog();

    $this->actingAs($student)->get('/questions/mocks')->assertOk();

    expect(collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], '`exams`.`id` is null'))->count())->toBe(0);
});
