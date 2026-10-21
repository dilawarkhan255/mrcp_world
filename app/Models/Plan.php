<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    /** @var list<string> */
    protected $fillable = ['name', 'duration_days', 'price', 'currency', 'is_active', 'sort_order'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'duration_days' => 'integer', 'is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    /** Price with the currency, for example "PKR 1,500". */
    public function priceLabel(): string
    {
        return $this->currency.' '.number_format((float) $this->price, 0);
    }

    /** Price without the currency symbol, for example "1,500". */
    public function amountLabel(): string
    {
        return number_format((float) $this->price, 0);
    }
}
