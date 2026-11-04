<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A Qbank is the product students subscribe to. It groups one or more exams.
 * An exam either owns its content (subjects, papers, questions) or reads the
 * content of another exam in the same Qbank through content_exam_id, so IMM
 * and FCPS Part 2 never duplicate the MRCP question banks.
 */
return new class extends Migration
{
    /** @var array<string, array{name: string, sort: int}> Existing exam slug => PDF name. */
    private const RENAMES = [
        'mrcp-part-1' => ['name' => 'MRCP 1 UK/Irish', 'sort' => 1],
        'mrcp-part-2-written' => ['name' => 'MRCP 2 UK/Irish', 'sort' => 3],
    ];

    /** @var array<string, string> Previous names, restored by down(). */
    private const PREVIOUS = [
        'mrcp-part-1' => 'MRCP Part 1',
        'mrcp-part-2-written' => 'MRCP Part 2 Written',
    ];

    public function up(): void
    {
        Schema::create('qbanks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'sort_order'], 'qbanks_public_index');
        });

        Schema::table('exams', function (Blueprint $table) {
            $table->foreignId('qbank_id')->nullable()->after('id')->constrained()->restrictOnDelete();
            $table->foreignId('content_exam_id')->nullable()->after('qbank_id')->constrained('exams')->restrictOnDelete();
        });

        $this->seedStructure();
    }

    /** Put the existing exams into the two PDF Qbanks and add the shared-content exams. */
    private function seedStructure(): void
    {
        $now = now();
        $exams = DB::table('exams')->whereIn('slug', array_keys(self::RENAMES))->pluck('id', 'slug');
        if ($exams->isEmpty()) {
            return; // Fresh install: ExamSeeder creates the structure.
        }
        $banks = [
            'mrcp-part-1' => ['name' => 'Qbank 1', 'slug' => 'qbank-1', 'description' => 'MRCP 1 UK/Irish and IMM', 'sort_order' => 1,
                'alias' => ['name' => 'IMM', 'slug' => 'imm', 'code' => 'IMM', 'sort' => 2]],
            'mrcp-part-2-written' => ['name' => 'Qbank 2', 'slug' => 'qbank-2', 'description' => 'MRCP 2 UK/Irish and FCPS Part 2', 'sort_order' => 2,
                'alias' => ['name' => 'FCPS Part 2', 'slug' => 'fcps-part-2', 'code' => 'FC2', 'sort' => 4]],
        ];
        foreach ($banks as $examSlug => $bank) {
            $examId = $exams[$examSlug] ?? null;
            if ($examId === null) {
                continue;
            }
            $qbankId = DB::table('qbanks')->where('slug', $bank['slug'])->value('id') ?? DB::table('qbanks')->insertGetId([
                'name' => $bank['name'], 'slug' => $bank['slug'], 'description' => $bank['description'],
                'sort_order' => $bank['sort_order'], 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('exams')->where('id', $examId)->update([
                'qbank_id' => $qbankId, 'name' => self::RENAMES[$examSlug]['name'], 'sort_order' => self::RENAMES[$examSlug]['sort'], 'updated_at' => $now,
            ]);
            $alias = $bank['alias'];
            if (! DB::table('exams')->where('slug', $alias['slug'])->exists()) {
                DB::table('exams')->insert([
                    'qbank_id' => $qbankId, 'content_exam_id' => $examId, 'name' => $alias['name'], 'slug' => $alias['slug'],
                    'code' => $alias['code'], 'sort_order' => $alias['sort'], 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Shared-content exams hold no content of their own, so they can be removed safely.
        DB::table('exams')->whereNotNull('content_exam_id')
            ->whereNotExists(fn ($query) => $query->from('practice_sessions')->whereColumn('practice_sessions.exam_id', 'exams.id'))
            ->delete();
        foreach (self::PREVIOUS as $slug => $name) {
            DB::table('exams')->where('slug', $slug)->update(['name' => $name]);
        }
        Schema::table('exams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('content_exam_id');
            $table->dropConstrainedForeignId('qbank_id');
        });
        Schema::dropIfExists('qbanks');
    }
};
