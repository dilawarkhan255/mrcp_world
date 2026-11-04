<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Plan extends Model
{
    /** @var list<string> */
    protected $fillable = ['qbank_id', 'name', 'duration_days', 'price', 'currency', 'is_active', 'sort_order'];

    /** @var array<string, string> Currency code => symbol shown before the amount. */
    private const SYMBOLS = ['GBP' => '£', 'USD' => '$', 'EUR' => '€'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'duration_days' => 'integer', 'is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    /** Price with the currency, for example "£8" or "PKR 1,500". */
    public function priceLabel(): string
    {
        $symbol = self::SYMBOLS[$this->currency] ?? null;
        $amount = number_format((float) $this->price, fmod((float) $this->price, 1.0) === 0.0 ? 0 : 2);

        return $symbol !== null ? $symbol.$amount : $this->currency.' '.$amount;
    }

    /** "6 months", "1 year" or "45 days". */
    public function durationLabel(): string
    {
        $days = (int) $this->duration_days;
        if ($days % 365 === 0) {
            return ($days / 365).' '.($days === 365 ? 'year' : 'years');
        }
        if ($days % 30 === 0) {
            return ($days / 30).' '.($days === 30 ? 'month' : 'months');
        }

        return $days.' days';
    }

    /** @return BelongsTo<Qbank, $this> */
    public function qbank(): BelongsTo
    {
        return $this->belongsTo(Qbank::class);
    }

    /** Price without the currency symbol, for example "1,500". */
    public function amountLabel(): string
    {
        return number_format((float) $this->price, 0);
    }
}
