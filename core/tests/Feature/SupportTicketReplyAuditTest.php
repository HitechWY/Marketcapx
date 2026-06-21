<?php

namespace Tests\Feature;

use App\Constants\Status;
use App\Http\Middleware\AdminAuditLog;
use App\Http\Middleware\CheckStatus;
use App\Http\Middleware\Module;
use App\Http\Middleware\RegistrationStep;
use App\Models\AdminAuditLog as AuditRow;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\CreatesMoneyFixtures;
use Tests\TestCase;

/**
 * F14b: admin ticket reply writes a semantic `support.ticket.replied` audit row WITHOUT
 * changing reply behavior. Metadata only — never the message body, HTML, or attachment
 * names/paths. DB-backed on `marketcapx_test`; DatabaseTransactions rolls back. The shared
 * SupportTicketManager trait is untouched (override lives in the admin controller).
 */
class SupportTicketReplyAuditTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesMoneyFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([
            \Laramin\Utility\Utility::class,
            \Laramin\Utility\GoToCore::class,
            CheckStatus::class,
            RegistrationStep::class,
            AdminAuditLog::class,
            Module::class,
        ]);
    }

    private function makeTicket(int $status, int $userId): SupportTicket
    {
        $t = new SupportTicket();
        $t->user_id    = $userId;
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

    public function test_admin_reply_creates_message_sets_status_and_redirects(): void
    {
        $user   = $this->makeUser();
        $ticket = $this->makeTicket(Status::TICKET_OPEN, $user->id);
        $before = SupportMessage::where('support_ticket_id', $ticket->id)->count();

        $this->actingAs($this->makeAdmin(), 'admin')
            ->post(route('admin.ticket.reply', $ticket->id), ['message' => 'Hello from admin'])
            ->assertRedirect();

        $this->assertSame($before + 1, SupportMessage::where('support_ticket_id', $ticket->id)->count());
        $this->assertSame(Status::TICKET_ANSWER, (int) $ticket->fresh()->status);
    }

    public function test_reply_writes_semantic_audit_row(): void
    {
        $user   = $this->makeUser();
        $ticket = $this->makeTicket(Status::TICKET_OPEN, $user->id);

        $this->actingAs($this->makeAdmin(), 'admin')
            ->post(route('admin.ticket.reply', $ticket->id), ['message' => 'Hello there'])
            ->assertRedirect();

        $rows = AuditRow::where('action', 'support.ticket.replied')->get();
        $this->assertCount(1, $rows);

        $row = $rows->first();
        $this->assertSame('ticket', $row->entity_type);
        $this->assertSame($ticket->id, (int) $row->entity_id);
        $this->assertSame('succeeded', $row->result);
        $this->assertSame(Status::TICKET_OPEN, (int) $row->before_values['status']);
        $this->assertSame(Status::TICKET_ANSWER, (int) $row->after_values['status']);
        $this->assertSame($ticket->ticket, $row->metadata['ticket']);
        $this->assertSame(11, (int) $row->metadata['message_length']); // 'Hello there'
        $this->assertSame(0, (int) $row->metadata['attachment_count']);
        $this->assertArrayHasKey('message_id', $row->metadata);
    }

    public function test_reply_audit_excludes_body_and_attachment_names(): void
    {
        $user   = $this->makeUser();
        $ticket = $this->makeTicket(Status::TICKET_OPEN, $user->id);
        $secret = 'REPLY-BODY-DO-NOT-LEAK';

        $this->actingAs($this->makeAdmin(), 'admin')
            ->post(route('admin.ticket.reply', $ticket->id), ['message' => $secret])
            ->assertRedirect();

        $row = AuditRow::where('action', 'support.ticket.replied')->latest('id')->first();
        $serialized = json_encode($row->toArray());

        $this->assertStringNotContainsString($secret, $serialized);
        $this->assertArrayNotHasKey('message', (array) $row->metadata);
        $this->assertArrayNotHasKey('attachment', (array) $row->metadata);
        $this->assertArrayNotHasKey('attachments', (array) $row->metadata);
    }

    public function test_validation_failure_creates_no_message_and_no_audit(): void
    {
        $user   = $this->makeUser();
        $ticket = $this->makeTicket(Status::TICKET_OPEN, $user->id);
        $before = SupportMessage::where('support_ticket_id', $ticket->id)->count();

        $this->actingAs($this->makeAdmin(), 'admin')
            ->from(route('admin.ticket.view', $ticket->id))
            ->post(route('admin.ticket.reply', $ticket->id), ['message' => ''])
            ->assertRedirect();

        $this->assertSame($before, SupportMessage::where('support_ticket_id', $ticket->id)->count());
        $this->assertSame(0, AuditRow::where('action', 'support.ticket.replied')->count());
        $this->assertSame(Status::TICKET_OPEN, (int) $ticket->fresh()->status);
    }

    public function test_missing_ticket_is_404_and_writes_no_audit(): void
    {
        $this->actingAs($this->makeAdmin(), 'admin')
            ->post(route('admin.ticket.reply', 999999), ['message' => 'x'])
            ->assertNotFound();

        $this->assertSame(0, AuditRow::where('action', 'support.ticket.replied')->count());
    }
}
