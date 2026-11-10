<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refunds Stripe reported (charge.refunded), by payment. A refund can arrive
 * before the payment itself is confirmed; the confirmation then finds it
 * here and does not open access that was already refunded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stripe_refunds', function (Blueprint $table) {
            $table->id();
            $table->string('payment_intent', 255)->unique();
            $table->string('charge_id', 255)->nullable();
            $table->unsignedInteger('amount_refunded')->default(0);
            $table->char('currency', 3)->nullable();
            $table->boolean('fully_refunded')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_refunds');
    }
};
