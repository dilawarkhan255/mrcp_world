<?php

namespace Tests\Feature\Auth;

use App\Models\Plan;
use App\Models\Qbank;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\ExamSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** An account is created together with a subscription request. */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ExamSeeder::class, PlanSeeder::class]);
    }

    public function test_registration_screen_lists_the_plans_on_sale()
    {
        $plan = Plan::orderBy('sort_order')->firstOrFail();

        $this->get('/register?plan='.$plan->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('auth/Register')
            ->has('plans', 4)
            // One Qbank after the other: 3 months (£8), then 5 months (£12).
            ->where('plans.0.qbank', 'MRCPWorld 1 Qbank')->where('plans.0.duration', '3 months')->where('plans.0.price', '£8')
            ->where('plans.1.qbank', 'MRCPWorld 1 Qbank')->where('plans.1.duration', '5 months')->where('plans.1.price', '£12')
            ->where('plans.2.qbank', 'MRCPWorld 2 Qbank')->where('plans.2.duration', '3 months')
            ->where('selectedPlan', $plan->id));
        // ?exam= preselects the 3-month plan of that exam's Qbank.
        $qbank2 = Qbank::where('slug', 'qbank-2')->value('id');
        $this->get('/register?exam=fcps-part-2')->assertInertia(fn (Assert $page) => $page
            ->where('selectedPlan', Plan::where('qbank_id', $qbank2)->where('duration_days', 90)->value('id')));
    }

    public function test_new_users_register_with_a_subscription_request()
    {
        $plan = Plan::orderBy('sort_order')->firstOrFail();
        $response = $this->post('/register', [
            'name' => 'Test User', 'email' => 'test@example.com', 'password' => 'password', 'password_confirmation' => 'password',
            'plan_id' => $plan->id, 'payment_reference' => 'TXN-42',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('subscription.index', absolute: false));
        $user = User::where('email', 'test@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('student'));
        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id, 'plan_id' => $plan->id, 'status' => Subscription::STATUS_PENDING, 'payment_reference' => 'TXN-42',
        ]);
    }

    public function test_an_account_needs_a_plan_and_a_payment_reference()
    {
        $this->post('/register', ['name' => 'No Plan', 'email' => 'none@example.com', 'password' => 'password', 'password_confirmation' => 'password'])
            ->assertSessionHasErrors(['plan_id', 'payment_reference']);

        $off = Plan::orderBy('sort_order')->firstOrFail();
        $off->update(['is_active' => false]);
        $this->post('/register', [
            'name' => 'Off Sale', 'email' => 'off@example.com', 'password' => 'password', 'password_confirmation' => 'password',
            'plan_id' => $off->id, 'payment_reference' => 'TXN-1',
        ])->assertSessionHasErrors('plan_id');

        $this->assertGuest();
        $this->assertSame(0, User::count());
        $this->assertSame(0, Subscription::count());
    }
}
