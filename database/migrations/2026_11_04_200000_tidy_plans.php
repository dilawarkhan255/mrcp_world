<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Plans are named after their Qbank only ("MRCPWorld 1 Qbank"), without the
 * duration. The old 1, 3 and 12 month plans from before the Qbanks (already
 * switched off) are removed, unless a subscription refers to one.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('plans')->whereNotNull('qbank_id')->where('name', 'like', '% — 6 months')->get(['id', 'name'])
            ->each(fn ($plan) => DB::table('plans')->where('id', $plan->id)->update([
                'name' => mb_substr($plan->name, 0, mb_strlen($plan->name) - mb_strlen(' — 6 months')), 'updated_at' => now(),
            ]));

        DB::table('plans')
            ->whereNull('qbank_id')
            ->where('is_active', false)
            ->whereIn('name', ['1 month access', '3 month access', '12 month access'])
            ->whereNotExists(fn ($query) => $query->from('subscriptions')->whereColumn('subscriptions.plan_id', 'plans.id'))
            ->delete();
    }

    public function down(): void
    {
        // The removed plans were unused and switched off; they are not restored.
        DB::table('plans')->whereNotNull('qbank_id')->where('name', 'not like', '% — 6 months')->where('duration_days', 180)->get(['id', 'name'])
            ->each(fn ($plan) => DB::table('plans')->where('id', $plan->id)->update(['name' => $plan->name.' — 6 months']));
    }
};
