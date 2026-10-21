<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->editor = User::factory()->create();
    $this->editor->assignRole('editor');
    $this->student = User::factory()->create();
    $this->student->assignRole('student');
});

/** The parameters the DataTables client sends with every request. */
function yajraParameters(string $search = '', int $orderColumn = 0): array
{
    $parameters = [
        'draw' => 1,
        'start' => 0,
        'length' => 25,
        'search' => ['value' => $search, 'regex' => 'false'],
        'order' => [['column' => $orderColumn, 'dir' => 'desc']],
    ];
    foreach (['id', 'name', 'email', 'roles', 'created_at'] as $index => $column) {
        $parameters['columns'][$index] = ['data' => $column, 'name' => $column, 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']];
    }

    return $parameters;
}

/** Make a GET request with query parameters for DataTables. */
function yajraGet($testCase, string $uri, array $parameters = []): \Illuminate\Testing\TestResponse
{
    return $testCase->call('GET', $uri, $parameters);
}

it('keeps the user list and its rows for administrators only', function () {
    yajraGet($this->actingAs($this->editor), '/admin/users/data', yajraParameters())->assertForbidden();
    yajraGet($this->actingAs($this->student), '/admin/users/data', yajraParameters())->assertForbidden();
    yajraGet($this->actingAs($this->admin), '/admin/users/data', yajraParameters())
        ->assertOk()
        ->assertJsonPath('recordsTotal', 3)
        ->assertJsonPath('recordsFiltered', 3)
        ->assertJsonFragment(['roles' => 'admin']);
});

it('masks the email address of a student for a panel user without the manage permission', function () {
    User::factory()->create(['email' => 'student@example.test']);
    $this->editor->givePermissionTo('billing.users.view');
    yajraGet($this->actingAs($this->editor), '/admin/users/data', yajraParameters())
        ->assertOk()
        ->assertJsonFragment(['email' => 'st***@example.test']);
});

it('searches the user rows on the server', function () {
    User::factory()->create(['name' => 'Findable Student', 'email' => 'findable@example.test']);
    yajraGet($this->actingAs($this->admin), '/admin/users/data', yajraParameters('Findable'))
        ->assertOk()
        ->assertJsonPath('recordsFiltered', 1)
        ->assertJsonFragment(['name' => 'Findable Student']);
});

it('shows the privacy notice to the panel staff and hides it from students', function () {
    $this->actingAs($this->editor)->get('/admin/privacy')->assertOk()->assertSee('Privacy');
    $this->actingAs($this->admin)->get('/admin/privacy')->assertOk();
    $this->actingAs($this->student)->get('/admin/privacy')->assertForbidden();
});

it('publishes the privacy notice on the public site', function () {
    $this->get('/privacy')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Legal/Privacy')
        ->has('sections', 5)
        ->where('sections.0.title', 'What we store')
    );
});

it('gives an editor the content permissions and keeps the billing permissions for an admin', function () {
    expect($this->editor->can('content.questions.create'))->toBeTrue()
        ->and($this->editor->can('content.questions.publish'))->toBeFalse()
        ->and($this->editor->can('billing.users.view'))->toBeFalse()
        ->and($this->admin->can('content.questions.publish'))->toBeTrue()
        ->and($this->admin->can('billing.users.manage'))->toBeTrue();
});
