<?php

use App\Models\Exam;
use App\Models\Plan;
use App\Models\Qbank;
use App\Models\User;
use Database\Seeders\ExamSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed([ExamSeeder::class, PlanSeeder::class, RoleSeeder::class]);
    $this->plan = Plan::where('qbank_id', Qbank::where('slug', 'qbank-2')->value('id'))->firstOrFail();
});

it('sends a visitor who chooses a plan to log in first, then to that plan', function () {
    $this->get('/subscription?plan='.$this->plan->id)->assertRedirect('/login');
    $this->get('/login')->assertInertia(fn (Assert $page) => $page->component('auth/Login')->where('subscribing', true));

    $student = User::factory()->create();
    $this->post('/login', ['email' => $student->email, 'password' => 'password'])
        ->assertRedirect('/subscription?plan='.$this->plan->id);
    $this->get('/subscription?plan='.$this->plan->id)->assertInertia(fn (Assert $page) => $page
        ->component('Subscription')->where('selectedPlan', $this->plan->id));
});

it('creates the account together with the chosen plan, then opens the subscription page', function () {
    $this->get('/register?plan='.$this->plan->id)->assertInertia(fn (Assert $page) => $page->where('selectedPlan', $this->plan->id));
    $this->post('/register', [
        'name' => 'New Doctor', 'email' => 'new@example.test', 'password' => 'password', 'password_confirmation' => 'password',
        'plan_id' => $this->plan->id, 'payment_reference' => 'TXN-99',
    ])->assertRedirect('/subscription');
    expect(User::where('email', 'new@example.test')->firstOrFail()->subscriptions()->value('plan_id'))->toBe($this->plan->id);
});

it('ignores a plan that is not on sale', function () {
    $this->plan->update(['is_active' => false]);
    $this->actingAs(User::factory()->create())->get('/subscription?plan='.$this->plan->id)
        ->assertInertia(fn (Assert $page) => $page->where('selectedPlan', null));
    $this->get('/subscription?plan=abc')->assertInertia(fn (Assert $page) => $page->where('selectedPlan', null));
});

it('points the landing pricing at sign-up with the plan, and members at the subscription page', function () {
    expect(file_get_contents(resource_path('js/components/landing/PricingPlans.vue')))
        ->toContain("'/register?plan=' + plan.id")
        ->toContain("'/subscription?plan=' + group.plans[0]?.id");
});

it('protects content for students with their own email as the watermark', function () {
    $student = User::factory()->create(['email' => 'student@example.test']);
    grantAccess($student, $this->plan->qbank_id);
    $this->actingAs($student)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
        ->where('protection.enabled', true)
        ->where('protection.watermark', 'student@example.test · '.now()->toDateString()));
});

it('does not protect content staff and can be switched off', function () {
    $editor = User::factory()->create();
    $editor->assignRole('editor');
    $this->actingAs($editor)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
        ->where('protection.enabled', false)->where('protection.watermark', null));

    config(['student.protection.enabled' => false]);
    $student = User::factory()->create();
    grantAccess($student, $this->plan->qbank_id);
    $this->actingAs($student)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('protection.enabled', false));
});

it('protects the public demo without a watermark', function () {
    $this->get('/exams/mrcp-part-1/demo')->assertInertia(fn (Assert $page) => $page
        ->where('protection.enabled', true)->where('protection.watermark', null));
});

it('protects every public page and mounts the protection once for the whole app', function () {
    foreach (['/', '/login', '/register', '/terms', '/privacy'] as $url) {
        $this->get($url)->assertInertia(fn (Assert $page) => $page->where('protection.enabled', true)->where('protection.watermark', null));
    }
    // Mounted at the root (app.ts), not per layout, so no page is left out.
    expect(file_get_contents(resource_path('js/app.ts')))->toContain('h(ContentProtection)')
        ->and(file_get_contents(resource_path('js/layouts/app/AppSidebarLayout.vue')))->not->toContain('ContentProtection');
    $composable = file_get_contents(resource_path('js/composables/useContentProtection.ts'));
    expect($composable)->toContain('blockClipboard')->toContain('Insert')->toContain('dragstart')->toContain('contextmenu')
        // The screenshot shield: Print Screen, the Windows/Command key and a window that loses focus hide the page.
        ->toContain('PrintScreen')->toContain('mw-shield')->toContain("addEventListener('blur'");
    expect(file_get_contents(resource_path('css/app.css')))->toContain('html.content-protected.mw-shield body::before');
});

it('has a login link per exam that opens that exam after logging in', function () {
    $mrcp2 = Exam::where('slug', 'mrcp-part-2-written')->firstOrFail();
    $this->get('/login?exam=mrcp-part-2-written')->assertInertia(fn (Assert $page) => $page
        ->component('auth/Login')->where('exam', ['name' => 'MRCP 2', 'slug' => 'mrcp-part-2-written']));

    $student = User::factory()->create();
    grantAccess($student, $mrcp2->qbank_id);
    $this->post('/login', ['email' => $student->email, 'password' => 'password', 'exam' => 'mrcp-part-2-written'])
        ->assertRedirect('/dashboard')->assertSessionHas('exam_id', $mrcp2->id);
    $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('currentExam.name', 'MRCP 2'));
});

it('ignores an unknown exam on the login link', function () {
    $this->get('/login?exam=nope')->assertInertia(fn (Assert $page) => $page->where('exam', null));
    $student = User::factory()->create();
    $this->post('/login', ['email' => $student->email, 'password' => 'password', 'exam' => 'nope'])->assertSessionMissing('exam_id');
});
