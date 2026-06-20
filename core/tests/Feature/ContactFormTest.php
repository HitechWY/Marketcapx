<?php

namespace Tests\Feature;

use App\Models\SupportTicket;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * F1c-3 coverage: the public contact page is migrated to Inertia (GET /contact),
 * while the POST /contact submit flow (SiteController@contactSubmit) is UNCHANGED.
 *
 * The Vue ContactForm does a native HTML POST to the same route with the same
 * field names — so the backend contract is exercised end-to-end here. All
 * DB-writing tests use DatabaseTransactions (rolled back; no persistent mutation),
 * matching the repo's MoneyFlow test pattern.
 */
class ContactFormTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        // The bundled license/activation middleware (Laramin\Utility\*) redirects
        // un-activated installs to /activate, which blocks full HTTP dispatch in the
        // test environment. Disable ONLY that check so the contact contract can be
        // exercised end-to-end; all other middleware (session, validation, CSRF) stay.
        $this->withoutMiddleware([
            \Laramin\Utility\Utility::class,
            \Laramin\Utility\GoToCore::class,
        ]);
    }

    public function test_contact_routes_are_intact(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes());

        $get = $routes->first(fn ($r) => $r->uri() === 'contact' && in_array('GET', $r->methods()));
        $post = $routes->first(fn ($r) => $r->uri() === 'contact' && in_array('POST', $r->methods()));

        $this->assertNotNull($get, 'GET /contact route missing.');
        $this->assertNotNull($post, 'POST /contact route missing.');
        $this->assertStringContainsString('SiteController@contact', $get->getActionName());
        $this->assertStringContainsString('SiteController@contactSubmit', $post->getActionName());
    }

    public function test_contact_get_renders_inertia_contact_page(): void
    {
        $this->get('/contact')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/Contact')
                ->has('content')
                ->has('captcha')
                ->has('sections'));
    }

    public function test_contact_post_rejects_missing_fields_and_creates_no_ticket(): void
    {
        $before = SupportTicket::count();

        $this->from('/contact')
            ->post('/contact', [])
            ->assertRedirect()
            ->assertSessionHasErrors(['name', 'email', 'subject', 'message']);

        // Validation fails before any DB write — count is unchanged.
        $this->assertSame($before, SupportTicket::count());
    }

    public function test_contact_post_success_creates_ticket_and_redirects(): void
    {
        $before = SupportTicket::count();

        $response = $this->post('/contact', [
            'name'    => 'F1c Contract Test',
            'email'   => 'f1c.contract.test@example.com',
            'subject' => 'F1c automated contract test',
            'message' => 'Automated contact contract test — rolled back by DatabaseTransactions.',
        ]);

        // Redirects to the ticket view with the new ticket.
        $response->assertRedirect();
        $this->assertSame($before + 1, SupportTicket::count());
        $this->assertDatabaseHas('support_tickets', [
            'subject' => 'F1c automated contract test',
            'email'   => 'f1c.contract.test@example.com',
        ]);
        // DatabaseTransactions rolls all of this back at teardown — no persistent mutation.
    }
}
