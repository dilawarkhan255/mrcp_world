<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A student's access to one Qbank. Paid by card (Stripe Checkout): the row
 * waits in "checkout" until Stripe confirms the payment, then it is
 * activated automatically. Paid another way (manual): the request is
 * pending until an admin activates it. Activation sets the access period.
 */
class Subscription extends Model
{
    public const STATUS_PENDING = 'pending';

    /** A card payment was started on Stripe Checkout and is not confirmed yet. */
    public const STATUS_CHECKOUT = 'checkout';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CANCELLED = 'cancelled';

    public const PROVIDER_STRIPE = 'stripe';

    /** @var array<string, string> */
    public const STATUSES = [
        self::STATUS_PENDING => 'Waiting for activation',
        self::STATUS_CHECKOUT => 'Card payment not completed',
        self::STATUS_ACTIVE => 'Active',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    /** @var list<string> */
    protected $fillable = [
        'user_id', 'qbank_id', 'plan_id', 'status', 'amount', 'currency', 'payment_reference', 'student_note', 'admin_note',
        'starts_at', 'ends_at', 'activated_by', 'activated_at', 'reminded_at',
        'payment_provider', 'stripe_session_id', 'stripe_payment_intent', 'paid_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'activated_at' => 'datetime',
            'reminded_at' => 'datetime', 'paid_at' => 'datetime',
        ];
    }

    /**
     * Rows that give access right now.
     *
     * @param  Builder<Subscription>  $query
     */
    public function scopeCurrent(Builder $query): void
    {
        $query->where('status', self::STATUS_ACTIVE)
            ->where('ends_at', '>', now())
            ->where(fn (Builder $query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()));
    }

    public function isCurrent(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->ends_at !== null && $this->ends_at->isFuture()
            && ($this->starts_at === null || ! $this->starts_at->isFuture());
    }

    /** The label shown to the student and in the admin table. */
    public function stateLabel(): string
    {
        if ($this->status === self::STATUS_ACTIVE && $this->ends_at !== null && ! $this->ends_at->isFuture()) {
            return 'Expired';
        }

        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Qbank, $this> */
    public function qbank(): BelongsTo
    {
        return $this->belongsTo(Qbank::class);
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** @return BelongsTo<User, $this> */
    public function activator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by');
    }
}
