<?php

use Livewire\Livewire;

beforeEach(function (): void {
    setupAuthzRoles();
});

test('guests are redirected to login from employee pages', function (): void {
    $this->get(route('admin.employees.index'))->assertRedirect(route('login'));
});

test('authenticated users can view employee index', function (): void {
    $user = createAdminUser();
    $this->actingAs($user);

    $this->get(route('admin.employees.index'))->assertOk();
});

test('employees.index Livewire component resolves', function (): void {
    $user = createAdminUser();
    $this->actingAs($user);

    Livewire::test('employees.index')->assertOk();
});
