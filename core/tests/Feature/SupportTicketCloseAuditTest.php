<?php

namespace Tests\Feature;

use App\Constants\Status;
use App\Http\Middleware\AdminAuditLog;
use App\Http\Middleware\CheckStatus;
use App\Http\Middleware\Module;
use App\Http\Middleware\RegistrationStep;
use App\Models\AdminAuditLog as AuditRow;
use App\Models\SupportTicket;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\CreatesMoneyFixtures;
use Tests\TestCase;

/**
 * F14: admin ticket close writes a semantic `support.ticket.closed` audit row WITHOUT
 * changing close behavior. DB-backed on `marketcapx_test`; DatabaseTransactions rolls back.
 * The shared SupportTicketManager trait is untouched (override lives in the admin controller).
 */
class SupportTicketCloseAuditTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesMoneyFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        // Disable license/status/registration/module noise + the generic audit middleware so the
        // test isolates the controller-level semantic row. PermissionMiddleware + admin auth stay.
        $this->withoutMiddleware([
            \Laramin\Utility\Utility::class,
            \Laramin\Utility\GoToCore::class,
            CheckStatus::class,
            RegistrationStep::class,
            AdminAuditLog::class,
            Module::class,
        ]);
    }

    private function makeTicket(int $status): SupportTicket
    {
        $t = new SupportTicket();
        $t->user_id    = 0;
        $t->user_type  = 'USER';
        $t->ticket     = $this->uniq('T');
        $t->name       = 'Tester';
        $t->email      = $this->uniq('t') . '@example.test';
        $t->subject    = 'Need help';
        $t->status     = $status;
        $t->priority   = Status::PRIORITY_LOW;
        $t->last_reply = now();
        $t->save();

        return $t;
    }

    public function test_admin_close_sets_status_and_redirects(): void
    {
        $ticket = $this->makeTicket(Status::TICKET_OPEN);

        $this->actingAs($this->makeAdmin(), 'admin')
            ->post(route('admin.ticket.close', $ticket->id))
            ->assertRedirect();

        $this->assertSame(Status::TICKET_CLOSE, (int) $ticket->fresh()->status);
    }

    public function test_close_writes_semantic_audit_row(): void
    {
        $ticket = $this->makeTicket(Status::TICKET_ANSWER);

        $this->actingAs($this->makeAdmin(), 'admin')
            ->post(route('admin.ticket.close', $ticket->id))
            ->assertRedirect();

        $rows = AuditRow::where('action', 'support.ticket.closed')->get();
        $this->assertCount(1, $rows);

        $row = $rows->first();
        $this->assertSame('ticket', $row->entity_type);
        $this->assertSame($ticket->id, (int) $row->entity_id);
        $this->assertSame('succeeded', $row->result);
        $this->assertSame(Status::TICKET_ANSWER, (int) $row->before_values['status']);
        $this->assertSame(Status::TICKET_CLOSE, (int) $row->after_values['status']);
    }

    public function test_close_audit_excludes_body_and_secrets(): void
    {
        $ticket = $this->makeTicket(Status::TICKET_OPEN);

        $this->actingAs($this->makeAdmin(), 'admin')
            ->post(route('admin.ticket.close', $ticket->id))
            ->assertRedirect();

        $row = AuditRow::where('action', 'support.ticket.closed')->latest('id')->first();
        // metadata carries the ticket reference only — never a message body, file path, or secret.
        $this->assertSame($ticket->ticket, $row->metadata['ticket']);
        $this->assertArrayNotHasKey('message', (array) $row->metadata);
        $this->assertArrayNotHasKey('attachment', (array) $row->metadata);
    }

    public function test_close_missing_ticket_is_404_and_writes_no_audit(): void
    {
        $this->actingAs($this->makeAdmin(), 'admin')
            ->post(route('admin.ticket.close', 999999))
            ->assertNotFound();

        $this->assertSame(0, AuditRow::where('action', 'support.ticket.closed')->count());
    }

    public function test_close_only_touches_the_target_ticket(): void
    {
        $target = $this->makeTicket(Status::TICKET_OPEN);
        $other  = $this->makeTicket(Status::TICKET_OPEN);

        $this->actingAs($this->makeAdmin(), 'admin')
            ->post(route('admin.ticket.close', $target->id))
            ->assertRedirect();

        $this->assertSame(Status::TICKET_CLOSE, (int) $target->fresh()->status);
        $this->assertSame(Status::TICKET_OPEN, (int) $other->fresh()->status);
        $this->assertCount(1, AuditRow::where('action', 'support.ticket.closed')->get());
    }
}
