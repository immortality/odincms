<?php

// The OdinAdmin layouts. The shared components are covered by the core modules' tests
// (modules/Users, Groups, Permissions), which use them on every screen.

it('renders the admin layout with the OdinAdmin assets', function () {
    $this->actingAsAdmin()->get('/admin')
        ->assertOk()
        ->assertSee('odinadmin/css/style.css', false)
        ->assertSee('odinadmin/js/app.js', false)
        ->assertSee('name="csrf-token"', false)
        ->assertSee('id="sidebar"', false)
        ->assertSee('class="topbar"', false)
        ->assertSee(route('logout'), false)
        ->assertDontSee('dashlite', false);
});

it('renders the auth layout', function () {
    $this->get('/admin/login')
        ->assertOk()
        ->assertSee('auth-page-soft', false)
        ->assertSee('odinadmin/css/style.css', false)
        ->assertDontSee('id="sidebar"', false)
        ->assertDontSee('dashlite', false);
});

it('renders every auth page with OdinAdmin', function () {
    foreach (['/admin/login', '/admin/forgot-password'] as $url) {
        $this->get($url)->assertOk()->assertSee('odinadmin/css/style.css', false)->assertDontSee('dashlite', false);
    }
});

it('no longer has the template preview', function () {
    $this->get('/admin-preview')->assertNotFound();
});
