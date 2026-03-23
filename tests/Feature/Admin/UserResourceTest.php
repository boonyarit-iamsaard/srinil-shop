<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('allows admin to access the admin panel', function () {
    $admin = User::factory()->admin()->create();

    actingAs($admin)
        ->get('/admin')
        ->assertSuccessful();
});

it('denies customer access to the admin panel', function () {
    $customer = User::factory()->create();

    actingAs($customer)
        ->get('/admin')
        ->assertForbidden();
});

it('redirects unauthenticated users to admin login', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('allows admin to list users', function () {
    $admin = User::factory()->admin()->create();
    $users = User::factory(3)->create();

    actingAs($admin);

    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords($users);
});

it('allows admin to create a user', function () {
    $admin = User::factory()->admin()->create();

    actingAs($admin);

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password',
            'role' => UserRole::Customer,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(User::where('email', 'jane@example.com')->exists())->toBeTrue();
});

it('allows admin to edit a user', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create(['name' => 'Old Name']);

    actingAs($admin);

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->fillForm([
            'name' => 'New Name',
            'email' => $user->email,
            'role' => UserRole::Customer,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->fresh()->name)->toBe('New Name');
});

it('validates required fields on create', function () {
    $admin = User::factory()->admin()->create();

    actingAs($admin);

    Livewire::test(CreateUser::class)
        ->fillForm([])
        ->call('create')
        ->assertHasFormErrors(['name', 'email', 'password', 'role']);
});
