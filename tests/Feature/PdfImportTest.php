<?php

use App\Filament\Resources\QuestionImportResource\Pages\ManageQuestionImports;
use App\Jobs\ImportQuestionsFromPdf;
use App\Models\Category;
use App\Models\Exam;
use App\Models\PastPaper;
use App\Models\Question;
use App\Models\QuestionImport;
use App\Models\User;
use App\Services\PdfQuestionParser;
use Database\Seeders\ExamSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/** A minimal one-page text PDF with the given lines (no parentheses in the text). */
function textPdf(array $lines): string
{
    $stream = "BT /F1 10 Tf 40 800 Td 14 TL\n";
    foreach ($lines as $line) {
        $stream .= '('.$line.") Tj T*\n";
    }
    $stream .= 'ET';
    $objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
        '<< /Length '.strlen($stream)." >>\nstream\n".$stream."\nendstream",
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    ];
    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objects as $i => $object) {
        $offsets[] = strlen($pdf);
        $pdf .= ($i + 1)." 0 obj\n".$object."\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
    foreach ($offsets as $offset) {
        $pdf .= sprintf("%010d 00000 n \n", $offset);
    }

    return $pdf."trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";
}

beforeEach(function () {
    $this->seed([ExamSeeder::class, RoleSeeder::class]);
    Storage::fake('local');
    $this->exam = Exam::where('slug', 'mrcp-part-1')->firstOrFail();
    $this->category = Category::create(['exam_id' => $this->exam->id, 'name' => 'Endocrinology', 'slug' => 'endocrinology']);
    $this->paper = PastPaper::create(['exam_id' => $this->exam->id, 'title' => 'September 2024', 'slug' => 'september-2024', 'year' => 2024]);
    $this->editor = User::factory()->create();
    $this->editor->assignRole('editor');
});

function pdfImport(string $pdf, array $extra = []): QuestionImport
{
    Storage::disk('local')->put('question-imports/paper.pdf', $pdf);

    return QuestionImport::create(array_merge([
        'user_id' => test()->editor->id, 'exam_id' => test()->exam->id, 'path' => 'question-imports/paper.pdf', 'source' => 'pdf', 'last_row' => 0,
    ], $extra));
}

it('parses numbered questions, options, answers, explanations and subjects from text', function () {
    $questions = (new PdfQuestionParser)->parse(<<<'TXT'
        MRCP Part 1 - Sample paper
        Answer every question.
        1. A 30-year-old woman has tremor and a goitre with a bruit.
        What is the most likely diagnosis?
        A. Toxic multinodular goitre
        B. Graves disease
        C) Hashimoto thyroiditis
        Answer: B
        Explanation: Graves disease is the commonest cause
        in young women.
        Subject: Endocrinology
        Q2. Sudden painless loss of vision with a cherry-red spot?
        A. Retinal vein occlusion   B. Retinal detachment   C. Retinal artery occlusion
        TXT."\n3) Stem three?\n(A) One\n(B) Two\nAnswers\n2. C\n3-A");

    expect($questions)->toHaveCount(3)
        ->and($questions[0]['number'])->toBe(1)
        ->and($questions[0]['stem'])->toBe('A 30-year-old woman has tremor and a goitre with a bruit. What is the most likely diagnosis?')
        ->and($questions[0]['options'])->toBe(['A' => 'Toxic multinodular goitre', 'B' => 'Graves disease', 'C' => 'Hashimoto thyroiditis'])
        ->and($questions[0]['correct_option'])->toBe('B')
        ->and($questions[0]['explanation'])->toBe("Graves disease is the commonest cause\nin young women.")
        ->and($questions[0]['subject'])->toBe('Endocrinology')
        ->and($questions[1]['options'])->toBe(['A' => 'Retinal vein occlusion', 'B' => 'Retinal detachment', 'C' => 'Retinal artery occlusion'])
        ->and($questions[1]['correct_option'])->toBe('C')
        ->and($questions[2]['options'])->toBe(['A' => 'One', 'B' => 'Two'])
        ->and($questions[2]['correct_option'])->toBe('A');
});

it('parses the long layout: "Question N of M", glued options, answer and sections', function () {
    $questions = (new PdfQuestionParser)->parse(implode("\n", [
        'MRCP Part 1', 'Cardiology pastpapers', 'MRCP Part 1 Cardiology pastpapers · page 1 of 9',
        'Question 1 of 2 Hypertrophic cardiomyopathy', 'Clinical scenario',
        'A 24-year-old man has syncope while running.', 'His father died suddenly.',
        'Investigations', "Investigation\tResult\tReference", 'Echocardiogram Asymmetric septal hypertrophy',
        'Question', 'Which of the following is the most appropriate intervention?',
        'AVerapamil', 'BBisoprolol', 'CAmiodarone', 'DImplantable cardioverter-defibrillator (ICD)', 'ESurgical septal myectomy',
        'MRCP Part 1 Cardiology pastpapers · page 2 of 9', '3',
        'Answer: D. Implantable cardioverter-defibrillator (ICD)',
        'Key point', 'High-risk HCM needs an ICD.',
        'Explanation: why D is correct', 'This is HCM with high-risk markers.',
        'Mnemonic', 'SYNCOPE.',
        'Why the other options are wrong', 'AVerapamil – symptoms only.',
        'YouTube Videos', 'HCM explained – a channel',
        'Question 2 of 2 Torsades', 'Clinical scenario', 'A 71-year-old woman collapses.', 'Question', 'What is the treatment?',
        'AMagnesium sulfate', 'BAmiodarone', 'Answer: A',
    ]));

    expect($questions)->toHaveCount(2)
        ->and($questions[0]['title'])->toBe('Hypertrophic cardiomyopathy')
        ->and($questions[0]['options'])->toBe(['A' => 'Verapamil', 'B' => 'Bisoprolol', 'C' => 'Amiodarone', 'D' => 'Implantable cardioverter-defibrillator (ICD)', 'E' => 'Surgical septal myectomy'])
        ->and($questions[0]['correct_option'])->toBe('D')
        ->and($questions[0]['stem'])->toContain('A 24-year-old man has syncope while running. His father died suddenly.')
        ->toContain('**Investigations**')->toContain('Which of the following is the most appropriate intervention?')->not->toContain('page 2')
        ->and($questions[0]['key_points'])->toBe('High-risk HCM needs an ICD.')
        ->and($questions[0]['mnemonics'])->toBe('SYNCOPE.')
        ->and($questions[0]['explanation'])->toContain('This is HCM with high-risk markers.')->toContain('**Why the other options are wrong**')
        ->toContain("\n- **A.** Verapamil – symptoms only.")
        ->not->toContain('a channel')
        ->and($questions[1]['options'])->toBe(['A' => 'Magnesium sulfate', 'B' => 'Amiodarone'])
        ->and($questions[1]['correct_option'])->toBe('A');
});

it('reads the long layout when the topic is on its own line and the key point comes before the answer', function () {
    $questions = (new PdfQuestionParser)->parse(implode("\n", [
        'Question 2 of 213 ', 'Torsades de pointes', 'Clinical scenario', 'A 71-year-old woman collapses while shopping.',
        'Question', 'Which of the following is the most appropriate immediate intervention?',
        'A IV verapamil', 'B IV amiodarone', 'C IV calcium gluconate', 'D IV metoprolol', 'E IV magnesium sulfate 2 g',
        'Key point', 'Answer: E. IV magnesium sulfate 2 g', 'Torsades → IV magnesium 2 g.',
        'Explanation: why E is correct', 'Torsades is polymorphic VT with a long QT.',
        'Question 3 of 213 ', 'Inherited dilated cardiomyopathy', 'Clinical scenario', 'A 34-year-old woman has DCM.', '—',
        'Question', 'Which mode of inheritance is most likely?',
        'A Autosomal recessive', 'B Mitochondrial inheritance', 'C Autosomal dominant',
        'Answer: C. Autosomal dominant', 'Key point', 'Familial DCM is mostly autosomal dominant.',
    ]));

    expect($questions)->toHaveCount(2)
        ->and($questions[0]['title'])->toBe('Torsades de pointes')
        ->and($questions[0]['stem'])->not->toContain('Torsades de pointes')->toContain('A 71-year-old woman collapses')
        ->and($questions[0]['options']['E'])->toBe('IV magnesium sulfate 2 g')
        ->and($questions[0]['correct_option'])->toBe('E')
        ->and($questions[0]['key_points'])->toBe('Torsades → IV magnesium 2 g.')
        ->and($questions[0]['explanation'])->toContain('polymorphic VT')
        ->and($questions[1]['title'])->toBe('Inherited dilated cardiomyopathy')
        ->and($questions[1]['stem'])->not->toContain('—')
        ->and($questions[1]['correct_option'])->toBe('C')
        ->and($questions[1]['key_points'])->toBe('Familial DCM is mostly autosomal dominant.');
});

it('turns the Investigations of the long layout into a Markdown table', function () {
    $stem = (new PdfQuestionParser)->parse(implode("\n", [
        'Question 1 of 1 Heart failure', 'Clinical scenario', 'A 70-year-old man is breathless.', 'Investigations',
        "Investigation Result\tReference",
        "Haemoglobin 138 g/L\t135–175 g/L",
        "Sodium 141 mmol/L\t135–145", 'mmol/L',
        "Creatine kinase 980 IU/L\t25–200 IU/L",
        "Platelets\t128 ×10⁹/L\t150–400 ×10⁹/L",
        'High-sensitivity', 'troponin T', "340 ng/L (rising to 1,120 at 3 h)\t<14 ng/L",
        "ECG\tSinus tachycardia, 1 mm ST depression", 'V2–V4', '—',
        'Echocardiogram Asymmetric septal hypertrophy (maximal wall', 'thickness 27 mm)', '—',
        '24-h Holter Frequent ectopics and a 6-', 'beat run of VT', '—',
        "LDL cholesterol (repeated twice)\t4.0 mmol/L <1.8 mmol/L target",
        'Question', 'What is the diagnosis?', 'A One', 'B Two', 'Answer: A',
    ]))[0]['stem'];

    expect($stem)->toContain("**Investigations**\n\n| Investigation | Result | Reference |\n| --- | --- | --- |")
        ->toContain('| Haemoglobin | 138 g/L | 135–175 g/L |')
        ->toContain('| Sodium | 141 mmol/L | 135–145 mmol/L |')
        ->toContain('| Creatine kinase | 980 IU/L | 25–200 IU/L |')
        ->toContain('| Platelets | 128 ×10⁹/L | 150–400 ×10⁹/L |')
        ->toContain('| High-sensitivity troponin T | 340 ng/L (rising to 1,120 at 3 h) | <14 ng/L |')
        ->toContain('| ECG | Sinus tachycardia, 1 mm ST depression V2–V4 | — |')
        ->toContain('| Echocardiogram | Asymmetric septal hypertrophy (maximal wall thickness 27 mm) | — |')
        ->toContain('| 24-h Holter | Frequent ectopics and a 6-beat run of VT | — |')
        ->toContain('| LDL cholesterol (repeated twice) | 4.0 mmol/L | <1.8 mmol/L target |')
        ->toContain('What is the diagnosis?');
});

it('keeps the tables, steps and key points of an explanation on their own lines', function () {
    $q = (new PdfQuestionParser)->parse(implode("\n", [
        'Question 1 of 1 HCM', 'Clinical scenario', 'A young man faints.', 'Question', 'What next?', 'A ICD', 'B Bisoprolol', 'Answer: A. ICD',
        'Key point', 'High-risk HCM needs an ICD.',
        'Explanation: why A is correct',
        'This is hypertrophic cardiomyopathy (HCM): autosomal-dominant sarcomeric disease with a double',
        'apex beat. The question is about preventing sudden death.',
        'HCM Risk-SCD inputs and modifiers',
        "Major SCD risk marker in HCM\tPresent here?",
        "Family history of SCD in a first-degr\tee relative <40–50 yrs Yes – father at 47",
        "Non-sustained VT on Holter\tYes",
        'ESC approach to SCD prevention in HCM',
        'Confirm HCM (echo ± cardiac MRI)1',
        'Calculate HCM Risk-SCD 5-year score2',
        'Plus: beta-blocker for obstructive symptoms',
        'High-yield points',
        'Murmur ↑ with Valsalva/standing.',
        'Avoid nitrates in obstructive HCM.',
    ]))[0];

    expect($q['explanation'])
        ->toContain('with a double apex beat. The question is about preventing sudden death.')
        ->toContain("HCM Risk-SCD inputs and modifiers  \nMajor SCD risk marker in HCM — Present here?  \n")
        ->toContain("first-degree relative <40–50 yrs Yes – father at 47  \nNon-sustained VT on Holter — Yes")
        ->toContain("\n\n1. Confirm HCM (echo ± cardiac MRI)\n2. Calculate HCM Risk-SCD 5-year score\n\nPlus: beta-blocker")
        ->and($q['key_points'])->toBe("- High-risk HCM needs an ICD.\n- Murmur ↑ with Valsalva/standing.\n- Avoid nitrates in obstructive HCM.");
});

it('creates MCQs from a text PDF without AI, linked to the past paper, as drafts', function () {
    $import = pdfImport(textPdf([
        '1. A 30-year-old woman has tremor and a goitre with a bruit. What is the diagnosis?',
        'A. Toxic multinodular goitre', 'B. Graves disease', 'C. Hashimoto thyroiditis',
        'Answer: B', 'Subject: Endocrinology',
        '2. Sudden painless loss of vision with a cherry-red spot?',
        'A. Retinal vein occlusion', 'B. Retinal artery occlusion',
        'Answer: B',
        '3. A question without an answer?', 'A. One', 'B. Two',
    ]), ['past_paper_id' => $this->paper->id]);

    (new ImportQuestionsFromPdf($import->id))->handle(new PdfQuestionParser);

    $import->refresh();
    expect($import->status)->toBe('completed')->and($import->imported)->toBe(2)->and($import->rejected)->toBe(1)
        ->and($import->errors[0]['row'])->toBe(3)->and($import->errors[0]['message'])->toContain('no answer');
    $questions = Question::orderBy('code')->get();
    expect($questions)->toHaveCount(2)
        ->and($questions->every(fn (Question $q): bool => ! $q->is_published && $q->past_paper_id === $this->paper->id))->toBeTrue()
        ->and($questions[0]->correct_option)->toBe('B')
        ->and($questions[0]->category_id)->toBe($this->category->id)
        ->and($questions[0]->needs_review)->toBeFalse()
        ->and($questions[1]->category->name)->toBe('Unsorted (PDF import)')
        ->and($questions[1]->needs_review)->toBeTrue();
});

it('updates the same MCQs when the same PDF is uploaded again, and publishes when asked', function () {
    $pdf = textPdf(['1. Stem one?', 'A. One', 'B. Two', 'Answer: A', 'Subject: Endocrinology']);
    (new ImportQuestionsFromPdf(pdfImport($pdf)->id))->handle(new PdfQuestionParser);
    (new ImportQuestionsFromPdf(pdfImport($pdf, ['publish' => true])->id))->handle(new PdfQuestionParser);

    expect(Question::count())->toBe(1)->and(Question::first()->is_published)->toBeTrue();
});

it('fails clearly for a file that is not a PDF or a PDF without MCQs', function () {
    $bad = pdfImport('not a pdf');
    (new ImportQuestionsFromPdf($bad->id))->handle(new PdfQuestionParser);
    expect($bad->refresh()->status)->toBe('failed')->and($bad->failure)->toBe('This file is not a PDF.');

    $empty = pdfImport(textPdf(['Just a title page']));
    (new ImportQuestionsFromPdf($empty->id))->handle(new PdfQuestionParser);
    expect($empty->refresh()->status)->toBe('failed')->and($empty->failure)->toContain('No MCQs were found');
});

it('queues a PDF upload from the admin panel', function () {
    Queue::fake();
    $this->actingAs($this->editor);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Storage::disk('local')->put('question-imports/upload.pdf', '%PDF-1.4');

    Livewire::test(ManageQuestionImports::class)
        ->callAction('uploadPdf', data: ['exam_id' => $this->exam->id, 'past_paper_id' => $this->paper->id, 'path' => ['question-imports/upload.pdf'], 'publish' => false])
        ->assertHasNoActionErrors();

    $import = QuestionImport::firstOrFail();
    expect($import->source)->toBe('pdf')->and($import->past_paper_id)->toBe($this->paper->id)->and($import->last_row)->toBe(0);
    Queue::assertPushed(ImportQuestionsFromPdf::class, fn (ImportQuestionsFromPdf $job): bool => $job->importId === $import->id);
});
