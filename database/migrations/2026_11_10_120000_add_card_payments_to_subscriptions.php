<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Card payments through Stripe Checkout. A subscription remembers how it was
 * paid, the Checkout Session that paid it and when the payment arrived.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('payment_provider', 20)->nullable()->after('payment_reference');
            $table->string('stripe_session_id', 255)->nullable()->unique()->after('payment_provider');
            $table->string('stripe_payment_intent', 255)->nullable()->after('stripe_session_id');
            $table->timestamp('paid_at')->nullable()->after('stripe_payment_intent');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropUnique(['stripe_session_id']);
            $table->dropColumn(['payment_provider', 'stripe_session_id', 'stripe_payment_intent', 'paid_at']);
        });
    }
};
