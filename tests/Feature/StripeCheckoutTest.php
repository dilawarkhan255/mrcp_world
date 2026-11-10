<?php

use App\Models\Plan;
use App\Models\Qbank;
use App\Models\Subscription;
use App\Models\User;
use App\Services\StripeCheckout;
use App\Services\SubscriptionService;
use Database\Seeders\ExamSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/** Stripe's API replaced by an in-memory one; the webhook signature check stays real. */
class FakeStripeCheckout extends StripeCheckout
{
    /** @var list<array<string, mixed>> */
    public array $created = [];

    /** @var array<string, array<string, mixed>> */
    public array $sessions = [];

    protected function createSession(array $params): array
    {
        $this->created[] = $params;
        $id = 'cs_test_'.count($this->created);

        return ['id' => $id, 'url' => 'https://checkout.stripe.com/c/pay/'.$id];
    }

    protected function retrieveSession(string $sessionId): array
    {
        return $this->sessions[$sessionId] ?? ['id' => $sessionId, 'payment_status' => 'unpaid'];
    }
}

const WEBHOOK_SECRET = 'whsec_test_secret';

/** Posts a webhook event signed the way Stripe signs it. */
function stripeWebhook(string $type, array $session, ?string $secret = null): TestResponse
{
    $payload = json_encode(['id' => 'evt_test', 'object' => 'event', 'type' => $type, 'data' => ['object' => ['object' => 'checkout.session', ...$session]]], JSON_THROW_ON_ERROR);
    $time = time();
    $signature = 't='.$time.',v1='.hash_hmac('sha256', $time.'.'.$payload, $secret ?? WEBHOOK_SECRET);

    return test()->call('POST', '/stripe/webhook', [], [], [], ['HTTP_STRIPE_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'], $payload);
}

beforeEach(function () {
    $this->seed([ExamSeeder::class, PlanSeeder::class, RoleSeeder::class]);
    config(['services.stripe.secret' => 'sk_test_fake', 'services.stripe.webhook_secret' => WEBHOOK_SECRET]);
    $this->stripe = new FakeStripeCheckout(app(SubscriptionService::class));
    $this->app->instance(StripeCheckout::class, $this->stripe);
    $this->qbank1 = Qbank::where('slug', 'qbank-1')->firstOrFail();
    $this->threeMonths = $this->qbank1->plans()->where('duration_days', 90)->firstOrFail();
    $this->fiveMonths = $this->qbank1->plans()->where('duration_days', 150)->firstOrFail();
});

it('creates the account and sends a new student straight to Stripe Checkout', function () {
    $response = $this->post('/register', [
        'name' => 'Card Student', 'email' => 'card@example.test', 'password' => 'password', 'password_confirmation' => 'password',
        'plan_id' => $this->threeMonths->id,
    ]);

    $response->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_1');
    $user = User::where('email', 'card@example.test')->firstOrFail();
    $this->assertAuthenticatedAs($user);
    $subscription = Subscription::where('user_id', $user->id)->firstOrFail();
    expect($subscription->status)->toBe(Subscription::STATUS_CHECKOUT)
        ->and($subscription->payment_provider)->toBe('stripe')
        ->and($subscription->amount)->toBe('8.00')
        ->and($subscription->stripe_session_id)->toBe('cs_test_1')
        ->and(app(SubscriptionService::class)->hasAccess($user, $this->qbank1->id))->toBeFalse();

    $params = $this->stripe->created[0];
    expect($params['mode'])->toBe('payment')
        ->and($params['customer_email'])->toBe('card@example.test')
        ->and($params['client_reference_id'])->toBe((string) $subscription->id)
        ->and($params['line_items'][0]['price_data']['currency'])->toBe('gbp')
        ->and($params['line_items'][0]['price_data']['unit_amount'])->toBe(800)
        ->and($params['line_items'][0]['price_data']['product_data']['name'])->toBe('MRCPWorld 1 Qbank — 3 months')
        ->and($params['success_url'])->toContain('/subscription/checkout/success?session_id={CHECKOUT_SESSION_ID}');
});

it('opens the Qbank when Stripe confirms the payment, once', function () {
    $student = User::factory()->unverified()->create();
    $this->actingAs($student)->post('/subscription/checkout', ['plan_id' => $this->fiveMonths->id])
        ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_1');
    $this->app['auth']->forgetGuards();

    stripeWebhook('checkout.session.completed', ['id' => 'cs_test_1', 'payment_status' => 'paid', 'amount_total' => 1200, 'currency' => 'gbp', 'payment_intent' => 'pi_123'])
        ->assertOk();

    $subscription = Subscription::where('user_id', $student->id)->firstOrFail();
    expect($subscription->status)->toBe(Subscription::STATUS_ACTIVE)
        ->and($subscription->activated_by)->toBeNull()
        ->and($subscription->paid_at)->not->toBeNull()
        ->and($subscription->payment_reference)->toBe('pi_123')
        ->and($subscription->ends_at->diffInDays($subscription->starts_at, true))->toEqualWithDelta(150, 0.01)
        ->and(app(SubscriptionService::class)->hasAccess($student, $this->qbank1->id))->toBeTrue();

    // Stripe may send the same event again: nothing changes.
    $endsAt = $subscription->ends_at;
    stripeWebhook('checkout.session.completed', ['id' => 'cs_test_1', 'payment_status' => 'paid', 'amount_total' => 1200, 'currency' => 'gbp'])->assertOk();
    expect($subscription->fresh()->ends_at->equalTo($endsAt))->toBeTrue();
});

it('refuses a webhook with a wrong signature', function () {
    $student = User::factory()->create();
    $this->actingAs($student)->post('/subscription/checkout', ['plan_id' => $this->threeMonths->id]);

    stripeWebhook('checkout.session.completed', ['id' => 'cs_test_1', 'payment_status' => 'paid', 'amount_total' => 800, 'currency' => 'gbp'], 'whsec_wrong')
        ->assertStatus(400);
    expect(Subscription::firstOrFail()->status)->toBe(Subscription::STATUS_CHECKOUT);
});

it('does not open access when the paid amount does not match the plan', function () {
    $student = User::factory()->create();
    $this->actingAs($student)->post('/subscription/checkout', ['plan_id' => $this->threeMonths->id]);

    stripeWebhook('checkout.session.completed', ['id' => 'cs_test_1', 'payment_status' => 'paid', 'amount_total' => 100, 'currency' => 'gbp'])->assertOk();
    $subscription = Subscription::firstOrFail();
    expect($subscription->status)->toBe(Subscription::STATUS_CHECKOUT)->and($subscription->admin_note)->toContain('did not match');
});

it('checks the payment with Stripe when the student comes back', function () {
    $student = User::factory()->create();
    $this->actingAs($student)->post('/subscription/checkout', ['plan_id' => $this->threeMonths->id]);

    // Not paid yet: the student waits on the subscription page.
    $this->get('/subscription/checkout/success?session_id=cs_test_1')->assertRedirect('/subscription');
    expect(Subscription::firstOrFail()->status)->toBe(Subscription::STATUS_CHECKOUT);

    $this->stripe->sessions['cs_test_1'] = ['id' => 'cs_test_1', 'payment_status' => 'paid', 'amount_total' => 800, 'currency' => 'gbp', 'payment_intent' => 'pi_9'];
    $this->get('/subscription/checkout/success?session_id=cs_test_1')->assertRedirect('/dashboard')->assertSessionHas('status');
    expect(Subscription::firstOrFail()->isCurrent())->toBeTrue();

    // Another student cannot use someone else's session.
    $this->actingAs(User::factory()->create())->get('/subscription/checkout/success?session_id=cs_test_1')->assertRedirect('/subscription');
});

it('reuses an unfinished card payment and closes it when Checkout expires', function () {
    $student = User::factory()->create();
    $this->actingAs($student)->post('/subscription/checkout', ['plan_id' => $this->threeMonths->id]);
    $this->post('/subscription/checkout', ['plan_id' => $this->fiveMonths->id]);

    expect(Subscription::count())->toBe(1);
    $subscription = Subscription::firstOrFail();
    expect($subscription->amount)->toBe('12.00')->and($subscription->stripe_session_id)->toBe('cs_test_2');

    stripeWebhook('checkout.session.expired', ['id' => 'cs_test_2', 'payment_status' => 'unpaid'])->assertOk();
    expect($subscription->fresh()->status)->toBe(Subscription::STATUS_CANCELLED);
});

it('shows card payment on the pages and keeps the manual request when Stripe is off', function () {
    $student = User::factory()->unverified()->create();
    // The subscription page works before the email is verified, so a new student can pay.
    $this->actingAs($student)->get('/subscription?checkout=cancelled')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('payments.card', true)->where('checkoutCancelled', true));

    config(['services.stripe.secret' => null]);
    $this->get('/subscription')->assertInertia(fn (Assert $page) => $page->where('payments.card', false));
    $this->post('/subscription/checkout', ['plan_id' => $this->threeMonths->id])->assertNotFound();
    auth()->logout();
    $this->post('/register', [
        'name' => 'Manual', 'email' => 'manual@example.test', 'password' => 'password', 'password_confirmation' => 'password',
        'plan_id' => $this->threeMonths->id,
    ])->assertSessionHasErrors('payment_reference');
});

it('ignores a plan that is not on sale', function () {
    $this->threeMonths->update(['is_active' => false]);
    $this->actingAs(User::factory()->create())->post('/subscription/checkout', ['plan_id' => $this->threeMonths->id])->assertSessionHasErrors('plan_id');
    expect(Plan::whereKey($this->threeMonths->id)->value('is_active'))->toBeFalse()->and(Subscription::count())->toBe(0);
});

/** A student who paid the 3-month plan by card (pi_paid). */
function paidByCard(object $test): User
{
    $student = User::factory()->create();
    $test->actingAs($student)->post('/subscription/checkout', ['plan_id' => $test->threeMonths->id]);
    stripeWebhook('checkout.session.completed', ['id' => 'cs_test_1', 'payment_status' => 'paid', 'amount_total' => 800, 'currency' => 'gbp', 'payment_intent' => 'pi_paid'])->assertOk();
    expect(app(SubscriptionService::class)->hasAccess($student, $test->qbank1->id))->toBeTrue();

    return $student;
}

it('closes the access automatically when the payment is fully refunded in Stripe', function () {
    $student = paidByCard($this);

    stripeWebhook('charge.refunded', ['object' => 'charge', 'id' => 'ch_1', 'payment_intent' => 'pi_paid', 'amount' => 800, 'amount_refunded' => 800, 'refunded' => true, 'currency' => 'gbp'])
        ->assertOk();

    $subscription = Subscription::firstOrFail();
    expect($subscription->status)->toBe(Subscription::STATUS_CANCELLED)
        ->and($subscription->admin_note)->toContain('Refunded in Stripe (8.00 GBP)')
        ->and(app(SubscriptionService::class)->hasAccess($student, $this->qbank1->id))->toBeFalse()
        ->and($student->notifications()->where('data->title', 'Your payment was refunded')->count())->toBe(1);

    // The same event again changes nothing and sends no second notice.
    stripeWebhook('charge.refunded', ['object' => 'charge', 'id' => 'ch_1', 'payment_intent' => 'pi_paid', 'amount' => 800, 'amount_refunded' => 800, 'refunded' => true, 'currency' => 'gbp'])
        ->assertOk();
    expect($student->notifications()->where('data->title', 'Your payment was refunded')->count())->toBe(1);
});

it('keeps the access on a partial refund and leaves a note for an admin', function () {
    $student = paidByCard($this);

    stripeWebhook('charge.refunded', ['object' => 'charge', 'id' => 'ch_1', 'payment_intent' => 'pi_paid', 'amount' => 800, 'amount_refunded' => 300, 'refunded' => false, 'currency' => 'gbp'])
        ->assertOk();

    $subscription = Subscription::firstOrFail();
    expect($subscription->status)->toBe(Subscription::STATUS_ACTIVE)
        ->and($subscription->admin_note)->toContain('Partly refunded in Stripe (3.00 GBP)')
        ->and(app(SubscriptionService::class)->hasAccess($student, $this->qbank1->id))->toBeTrue();
});

it('ignores a refund of a payment that is not ours', function () {
    $student = paidByCard($this);

    stripeWebhook('charge.refunded', ['object' => 'charge', 'id' => 'ch_9', 'payment_intent' => 'pi_other', 'amount' => 800, 'amount_refunded' => 800, 'refunded' => true, 'currency' => 'gbp'])
        ->assertOk();
    expect(app(SubscriptionService::class)->hasAccess($student, $this->qbank1->id))->toBeTrue();
});

it('never opens access when the refund arrives before the payment is confirmed', function () {
    $student = User::factory()->create();
    $this->actingAs($student)->post('/subscription/checkout', ['plan_id' => $this->threeMonths->id]);

    // Stripe sends the refund first…
    stripeWebhook('charge.refunded', ['object' => 'charge', 'id' => 'ch_1', 'payment_intent' => 'pi_early', 'amount' => 800, 'amount_refunded' => 800, 'refunded' => true, 'currency' => 'gbp'])
        ->assertOk();
    expect(Subscription::firstOrFail()->status)->toBe(Subscription::STATUS_CHECKOUT);

    // …then the payment confirmation: the subscription is closed, not opened.
    stripeWebhook('checkout.session.completed', ['id' => 'cs_test_1', 'payment_status' => 'paid', 'amount_total' => 800, 'currency' => 'gbp', 'payment_intent' => 'pi_early'])
        ->assertOk();

    $subscription = Subscription::firstOrFail();
    expect($subscription->status)->toBe(Subscription::STATUS_CANCELLED)
        ->and($subscription->starts_at)->toBeNull()
        ->and($subscription->admin_note)->toContain('before the payment was confirmed')
        ->and(app(SubscriptionService::class)->hasAccess($student, $this->qbank1->id))->toBeFalse()
        ->and($student->notifications()->where('data->title', 'Your subscription is active')->count())->toBe(0)
        ->and($student->notifications()->where('data->title', 'Your payment was refunded')->count())->toBe(1);

    // The student coming back from Checkout does not open it either.
    $this->stripe->sessions['cs_test_1'] = ['id' => 'cs_test_1', 'payment_status' => 'paid', 'amount_total' => 800, 'currency' => 'gbp', 'payment_intent' => 'pi_early'];
    $this->actingAs($student)->get('/subscription/checkout/success?session_id=cs_test_1')->assertRedirect('/subscription');
    expect(app(SubscriptionService::class)->hasAccess($student, $this->qbank1->id))->toBeFalse();
});

it('opens access with a note when a partial refund arrives before the payment is confirmed', function () {
    $student = User::factory()->create();
    $this->actingAs($student)->post('/subscription/checkout', ['plan_id' => $this->threeMonths->id]);

    stripeWebhook('charge.refunded', ['object' => 'charge', 'id' => 'ch_1', 'payment_intent' => 'pi_part', 'amount' => 800, 'amount_refunded' => 200, 'refunded' => false, 'currency' => 'gbp']);
    stripeWebhook('checkout.session.completed', ['id' => 'cs_test_1', 'payment_status' => 'paid', 'amount_total' => 800, 'currency' => 'gbp', 'payment_intent' => 'pi_part']);

    $subscription = Subscription::firstOrFail();
    expect($subscription->status)->toBe(Subscription::STATUS_ACTIVE)
        ->and($subscription->admin_note)->toContain('Partly refunded in Stripe (2.00 GBP)')
        ->and(app(SubscriptionService::class)->hasAccess($student, $this->qbank1->id))->toBeTrue();

    // Refunding the rest later closes it.
    stripeWebhook('charge.refunded', ['object' => 'charge', 'id' => 'ch_1', 'payment_intent' => 'pi_part', 'amount' => 800, 'amount_refunded' => 800, 'refunded' => true, 'currency' => 'gbp']);
    expect($subscription->fresh()->status)->toBe(Subscription::STATUS_CANCELLED)
        ->and(DB::table('stripe_refunds')->where('payment_intent', 'pi_part')->value('fully_refunded'))->toBeTruthy();
});

it('explains the webhook address when it is opened in a browser', function () {
    config(['services.stripe.secret' => null, 'services.stripe.webhook_secret' => null]);
    $this->get('/stripe/webhook')->assertOk()
        ->assertSee('receives payment events from Stripe')
        ->assertDontSee('sk_test')->assertDontSee('whsec');
});
