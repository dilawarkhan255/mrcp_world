<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * New prices: every Qbank is sold for 3 months (£8) or 5 months (£12).
 *
 * The old 6-month plans are switched off, not deleted or changed: a request
 * that is still waiting for activation keeps the plan (and the 6 months) the
 * student asked for, and old subscriptions keep their plan.
 */
return new class extends Migration
{
    /** @var list<array{days: int, price: int, sort: int}> */
    private const PLANS = [
        ['days' => 90, 'price' => 8, 'sort' => 1],
        ['days' => 150, 'price' => 12, 'sort' => 2],
    ];

    public function up(): void
    {
        foreach (DB::table('qbanks')->orderBy('sort_order')->orderBy('id')->get(['id', 'name']) as $qbank) {
            DB::table('plans')->where('qbank_id', $qbank->id)->where('duration_days', 180)->update(['is_active' => false, 'updated_at' => now()]);
            foreach (self::PLANS as $plan) {
                $exists = DB::table('plans')->where('qbank_id', $qbank->id)->where('duration_days', $plan['days'])->exists();
                if ($exists) {
                    DB::table('plans')->where('qbank_id', $qbank->id)->where('duration_days', $plan['days'])
                        ->update(['price' => $plan['price'], 'currency' => 'GBP', 'is_active' => true, 'sort_order' => $plan['sort'], 'updated_at' => now()]);

                    continue;
                }
                DB::table('plans')->insert([
                    'qbank_id' => $qbank->id, 'name' => $qbank->name, 'duration_days' => $plan['days'], 'price' => $plan['price'],
                    'currency' => 'GBP', 'is_active' => true, 'sort_order' => $plan['sort'], 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Back to £8 for 6 months: the new plans are switched off (deleted when nobody used them).
        DB::table('plans')->whereNotNull('qbank_id')->whereIn('duration_days', [90, 150])->update(['is_active' => false, 'updated_at' => now()]);
        DB::table('plans')->whereNotNull('qbank_id')->whereIn('duration_days', [90, 150])
            ->whereNotExists(fn ($query) => $query->from('subscriptions')->whereColumn('subscriptions.plan_id', 'plans.id'))
            ->delete();
        DB::table('plans')->whereNotNull('qbank_id')->where('duration_days', 180)->update(['is_active' => true, 'updated_at' => now()]);
    }
};
