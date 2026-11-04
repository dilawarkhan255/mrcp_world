<?php

use App\Filament\Resources\RoleResource\Pages\ManageRoles;
use App\Filament\Resources\UserResource\Pages\ManageUsers;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function roleId(string $name): int
{
    return (int) Role::where('name', $name)->value('id');
}

it('lets an admin add a user with a role and a password', function () {
    Livewire::test(ManageUsers::class)->callAction('create', data: [
        'name' => 'New Editor', 'email' => 'new.editor@example.test', 'password' => 'Str0ng!Passw0rd',
        'email_verified_at' => true, 'roles' => [roleId('editor')],
    ])->assertHasNoActionErrors();

    $user = User::where('email', 'new.editor@example.test')->firstOrFail();
    expect($user->hasRole('editor'))->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('Str0ng!Passw0rd', $user->password))->toBeTrue()
        ->and($user->can('content.questions.create'))->toBeTrue();
});

it('changes roles and keeps the password when it is left empty', function () {
    $user = User::factory()->create();
    $user->assignRole('student');
    $hash = $user->password;

    Livewire::test(ManageUsers::class)->callTableAction('edit', $user, data: ['name' => 'Promoted', 'password' => '', 'roles' => [roleId('editor')]])
        ->assertHasNoTableActionErrors();

    $user->refresh();
    expect($user->name)->toBe('Promoted')->and($user->password)->toBe($hash)
        ->and($user->getRoleNames()->all())->toBe(['editor']);
});

it('gives a single extra permission without changing the role', function () {
    $user = User::factory()->create();
    $user->assignRole('editor');

    Livewire::test(ManageUsers::class)->callTableAction('edit', $user, data: [
        'roles' => [roleId('editor')], 'permissions' => [Permission::where('name', 'content.questions.publish')->value('id')],
    ])->assertHasNoTableActionErrors();

    expect($user->refresh()->can('content.questions.publish'))->toBeTrue()
        ->and(User::factory()->create()->assignRole('editor')->can('content.questions.publish'))->toBeFalse();
});

it('never removes the admin role from yourself or from the last admin', function () {
    Livewire::test(ManageUsers::class)->callTableAction('edit', $this->admin, data: ['roles' => [roleId('editor')]])
        ->assertHasTableActionErrors(['roles']);
    expect($this->admin->refresh()->hasRole('admin'))->toBeTrue();

    $second = User::factory()->create();
    $second->assignRole('admin');
    Livewire::test(ManageUsers::class)->callTableAction('edit', $second, data: ['roles' => [roleId('editor')]])->assertHasNoTableActionErrors();
    expect($second->refresh()->hasRole('admin'))->toBeFalse();
});

it('creates a custom role with chosen permissions and applies them at once', function () {
    $permissions = Permission::whereIn('name', ['content.questions.view', 'content.questions.update'])->pluck('id')->all();
    Livewire::test(ManageRoles::class)->callAction('create', data: ['name' => 'content-reviewer', 'permissions' => $permissions])->assertHasNoActionErrors();

    $reviewer = User::factory()->create();
    $reviewer->assignRole('content-reviewer');
    expect($reviewer->can('content.questions.update'))->toBeTrue()
        ->and($reviewer->can('content.questions.publish'))->toBeFalse();
    $this->actingAs($reviewer)->get('/admin/questions')->assertOk();
    $this->get('/admin/subscriptions')->assertForbidden();
});

it('updates a role and the change applies straight away', function () {
    $editor = User::factory()->create();
    $editor->assignRole('editor');
    expect($editor->can('content.questions.publish'))->toBeFalse();

    $role = Role::findByName('editor');
    $ids = [...$role->permissions->pluck('id')->all(), Permission::where('name', 'content.questions.publish')->value('id')];
    Livewire::test(ManageRoles::class)->callTableAction('edit', $role, data: ['permissions' => $ids])->assertHasNoTableActionErrors();

    expect(User::find($editor->id)->can('content.questions.publish'))->toBeTrue();
});

it('protects the built-in roles and keeps every permission on admin', function () {
    $admin = Role::findByName('admin');
    Livewire::test(ManageRoles::class)->assertTableActionHidden('delete', $admin)
        ->assertTableActionHidden('delete', Role::findByName('student'));
    Livewire::test(ManageRoles::class)->callTableAction('edit', $admin, data: ['name' => 'boss'])->assertHasNoTableActionErrors();
    expect($admin->refresh()->name)->toBe('admin')
        ->and($admin->permissions()->count())->toBe(count(config('permissions.list')));

    $custom = Role::create(['name' => 'temporary', 'guard_name' => 'web']);
    Livewire::test(ManageRoles::class)->callTableAction('delete', $custom);
    expect(Role::where('name', 'temporary')->exists())->toBeFalse();
});

it('keeps role changes made in the panel when the seeder runs again', function () {
    Role::findByName('editor')->revokePermissionTo('content.atlas.manage');
    Role::findByName('editor')->givePermissionTo('content.questions.publish');

    $this->seed(RoleSeeder::class);

    $editor = Role::findByName('editor');
    expect($editor->hasPermissionTo('content.atlas.manage'))->toBeFalse()
        ->and($editor->hasPermissionTo('content.questions.publish'))->toBeTrue();
});

it('hides user and role management from editors and students', function () {
    foreach (['editor', 'student'] as $name) {
        $user = User::factory()->create();
        $user->assignRole($name);
        $this->actingAs($user)->get('/admin/users')->assertForbidden();
        $this->get('/admin/roles')->assertForbidden();
    }
});
