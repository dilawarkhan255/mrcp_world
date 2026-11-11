<?php

namespace Tests\Feature;

use App\Models\Qbank;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_students_with_a_package_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        grantAccess($user, Qbank::create(['name' => 'Qbank', 'slug' => 'qbank']));
        $this->actingAs($user);

        $response = $this->get('/dashboard');
        $response->assertStatus(200);
    }
}
