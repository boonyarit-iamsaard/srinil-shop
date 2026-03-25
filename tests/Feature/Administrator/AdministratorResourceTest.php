<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Filament\Resources\Administrators\Pages\CreateAdministrator;
use App\Filament\Resources\Administrators\Pages\EditAdministrator;
use App\Filament\Resources\Administrators\Pages\ListAdministrators;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('allows admin to list administrators', function () {
    $admin = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->create();

    actingAs($admin);

    Livewire::test(ListAdministrators::class)
        ->assertCanSeeTableRecords([$admin, $otherAdmin]);
});

it('does not show customers in the administrators list', function () {
    $admin = User::factory()->admin()->create();
    $customer = User::factory()->create();

    actingAs($admin);

    Livewire::test(ListAdministrators::class)
        ->assertCanNotSeeTableRecords([$customer]);
});

it('allows admin to create an administrator', function () {
    $admin = User::factory()->admin()->create();

    actingAs($admin);

    Livewire::test(CreateAdministrator::class)
        ->fillForm([
            'name' => 'John Administrator',
            'email' => 'john.administrator@example.com',
            'password' => 'password',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(User::where('email', 'john.administrator@example.com')->value('role'))->toBe(UserRole::Admin);
});

it('allows admin to edit an administrator', function () {
    $admin = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->create(['name' => 'Old Administrator Name']);

    actingAs($admin);

    Livewire::test(EditAdministrator::class, ['record' => $otherAdmin->getRouteKey()])
        ->fillForm([
            'name' => 'New Administrator Name',
            'email' => $otherAdmin->email,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($otherAdmin->fresh()->name)->toBe('New Administrator Name');
});

it('validates required fields on create', function () {
    $admin = User::factory()->admin()->create();

    actingAs($admin);

    Livewire::test(CreateAdministrator::class)
        ->fillForm()
        ->call('create')
        ->assertHasFormErrors(['name', 'email', 'password']);
});
