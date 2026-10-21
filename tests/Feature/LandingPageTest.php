<?php

use App\Models\Category;
use App\Models\Exam;
use App\Models\Plan;
use App\Models\Question;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->exam = Exam::factory()->create(['name' => 'MRCP Part 1', 'slug' => 'mrcp-part-1', 'code' => 'MP1', 'description' => 'Part one questions', 'is_active' => true, 'sort_order' => 1]);
    $this->hiddenExam = Exam::factory()->create(['name' => 'Hidden exam', 'slug' => 'hidden-exam', 'is_active' => false, 'sort_order' => 2]);
    $this->category = Category::factory()->create(['exam_id' => $this->exam->id]);
});

it('shows the landing page to guests', function () {
    $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Welcome')
        ->where('appName', config('app.name'))
        ->has('exams', 1)
        ->has('plans')
        ->has('histogram.buckets', 20)
        ->where('histogram.is_sample', true)
    );
});

it('sends a signed in student to the dashboard', function () {
    $this->actingAs(User::factory()->create())->get('/')->assertRedirect('/dashboard');
});

it('passes only active exams in display order', function () {
    Exam::factory()->create(['name' => 'Second exam', 'slug' => 'second-exam', 'is_active' => true, 'sort_order' => 0]);
    $this->get('/')->assertInertia(fn (Assert $page) => $page
        ->has('exams', 2)
        ->where('exams.0.slug', 'second-exam')
        ->where('exams.1.slug', 'mrcp-part-1')
        ->where('exams.1.code', 'MP1')
        ->where('exams.1.description', 'Part one questions')
    );
});

it('counts the published questions of an exam', function () {
    Question::create(['exam_id' => $this->exam->id, 'category_id' => $this->category->id, 'code' => 'LAND-1', 'body' => 'Landing question', 'options' => ['A' => 'One', 'B' => 'Two'], 'correct_option' => 'A', 'is_published' => true]);
    Question::create(['exam_id' => $this->exam->id, 'category_id' => $this->category->id, 'code' => 'LAND-2', 'body' => 'Draft question', 'options' => ['A' => 'One', 'B' => 'Two'], 'correct_option' => 'A', 'is_published' => false]);
    $this->get('/')->assertInertia(fn (Assert $page) => $page->where('exams.0.question_count', 1));
});

it('passes only active plans grouped by duration', function () {
    Plan::create(['name' => '1 month access', 'duration_days' => 30, 'price' => 2500, 'currency' => 'PKR', 'is_active' => true, 'sort_order' => 1]);
    Plan::create(['name' => 'Hidden plan', 'duration_days' => 60, 'price' => 100, 'currency' => 'PKR', 'is_active' => false]);
    $this->get('/')->assertInertia(fn (Assert $page) => $page
        ->has('plans', 1)
        ->where('plans.0.duration_days', 30)
        ->where('plans.0.plans.0.name', '1 month access')
        ->where('plans.0.plans.0.price', 'PKR 2,500')
        ->where('plans.0.plans.0.amount', '2,500')
        ->where('plans.0.plans.0.currency', 'PKR')
    );
});

it('sends an empty plan list and a placeholder price when no plans exist', function () {
    $this->get('/')->assertInertia(fn (Assert $page) => $page->has('plans', 0));
    expect(file_get_contents(base_path('resources/js/content/landing.ts')))
        ->toContain('[PRICE]')
        ->toContain('[YOUR COPY HERE]');
});

it('serves a sitemap that lists the landing page, the exams and the pricing', function () {
    $response = $this->get('/sitemap.xml')->assertOk();
    expect($response->headers->get('content-type'))->toContain('xml');
    expect($response->getContent())
        ->toContain(route('home'))
        ->toContain(route('landing.demo', 'mrcp-part-1'))
        ->toContain(route('legal.terms'))
        ->toContain(route('legal.privacy'))
        ->toContain('#pricing');
});

it('shows the demo questions of an exam to guests and hides the rest', function () {
    Question::create(['exam_id' => $this->exam->id, 'category_id' => $this->category->id, 'code' => 'DEMO-1', 'body' => 'Demo question', 'options' => ['A' => 'One', 'B' => 'Two'], 'correct_option' => 'A', 'explanation' => 'Demo explanation', 'is_published' => true, 'is_demo' => true]);
    Question::create(['exam_id' => $this->exam->id, 'category_id' => $this->category->id, 'code' => 'PAID-1', 'body' => 'Paid question', 'options' => ['A' => 'One', 'B' => 'Two'], 'correct_option' => 'A', 'is_published' => true]);
    $this->get('/exams/mrcp-part-1/demo')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('ExamDemo')
        ->has('questions', 1)
        ->where('questions.0.body', 'Demo question')
        ->where('questions.0.correct_option', 'A')
    );
    $this->actingAs(User::factory()->create())->get('/exams/mrcp-part-1/demo')->assertRedirect('/dashboard');
});

it('shows the legal placeholder pages', function () {
    $this->get('/terms')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Legal/Terms'));
    $this->get('/privacy')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Legal/Privacy'));
});
