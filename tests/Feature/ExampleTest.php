<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The root sends a guest to the login page.
     *
     * It used to redirect to /dashboard, which recorded a phantom "intended"
     * URL during the auth bounce and made admins land on the portal instead of
     * the admin panel. Landing per role is covered in depth by LoginRedirectTest.
     */
    public function test_the_root_redirects_a_guest_to_the_login_page(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }
}
