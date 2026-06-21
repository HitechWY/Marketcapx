<?php

namespace App\Http\Controllers\Admin;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Services\AdminAuditLogger;
use App\Traits\BuildsAdminReadonly;
use App\Traits\SupportTicketManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupportTicketController extends Controller
{
    use SupportTicketManager {
        closeTicket as protected runCloseTicket;
        replyTicket as protected runReplyTicket;
    }
    use BuildsAdminReadonly;

    public function __construct()
    {
        parent::__construct();
        $this->userType = 'admin';
        $this->column = 'admin_id';
        $this->user = auth()->guard('admin')->user();
    }

    public function tickets()
    {
        $pageTitle = 'Support Tickets';
        $items = SupportTicket::searchable(['name','subject','ticket'])->orderBy('id','desc')->with('user')->paginate(getPaginate());
        return $this->ticketInertia($pageTitle, $items);
    }

    public function pendingTicket()
    {
        $pageTitle = 'Pending Tickets';
        $items = SupportTicket::searchable(['name','subject','ticket'])->pending()->orderBy('id','desc')->with('user')->paginate(getPaginate());
        return $this->ticketInertia($pageTitle, $items);
    }

    public function closedTicket()
    {
        $pageTitle = 'Closed Tickets';
        $items = SupportTicket::searchable(['name','subject','ticket'])->closed()->orderBy('id','desc')->with('user')->paginate(getPaginate());
        return $this->ticketInertia($pageTitle, $items);
    }

    public function answeredTicket()
    {
        $pageTitle = 'Answered Tickets';
        $items = SupportTicket::searchable(['name','subject','ticket'])->orderBy('id','desc')->with('user')->answered()->paginate(getPaginate());
        return $this->ticketInertia($pageTitle, $items);
    }

    /**
     * F6b: read-only Inertia ticket list. Reply/close/delete stay on the (Blade) reply page.
     * Rollback (one line): return view('admin.support.tickets', compact('items', 'pageTitle'));
     */
    protected function ticketInertia(string $pageTitle, $items)
    {
        $items->appends(request()->all());
        $items->through(fn ($t) => $this->mapTicketRow($t));

        return inertia('Admin/SupportTickets', [
            'pageTitle' => $pageTitle,
            'tickets'   => $items,
            'filters'   => ['search' => (string) request('search')],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapTicketRow($t): array
    {
        return [
            'id'             => $t->id,
            'ticket'         => $t->ticket,
            'subject'        => strLimit((string) $t->subject, 60),
            'name'           => $t->user_id ? @$t->getUser->fullname : $t->name,
            'userType'       => __($t->user_type),
            'status'         => $this->ticketStatusChip($t->status),
            'priority'       => $this->ticketPriorityChip($t->priority),
            'lastReplyHuman' => diffForHumans($t->last_reply),
            'viewUrl'        => route('admin.ticket.view', $t->id),
        ];
    }

    public function ticketReply($id)
    {
        $ticket = SupportTicket::with('user')->where('id', $id)->firstOrFail();
        $pageTitle = 'Reply Ticket';
        $messages = SupportMessage::with('ticket','admin','attachments')->where('support_ticket_id', $ticket->id)->orderBy('id','desc')->get();
        return view('admin.support.reply', compact('ticket', 'messages', 'pageTitle'));
    }

    /**
     * F14b: admin ticket reply + semantic audit. Runs the unchanged shared-trait reply
     * (validation, saves, attachments, email/SMS, redirect/flash) and then — only if a new
     * admin message was actually created — writes a `support.ticket.replied` audit row with
     * METADATA ONLY (status before/after, ticket ref, message id + length, attachment count;
     * never the body, HTML, attachment names/paths, or file contents).
     *
     * Deliberately NOT wrapped in a transaction: reply has post-save email/attachment side
     * effects that a transaction would change. The audit is written after the reply completes
     * and is fail-open (logMutation), so it never alters or breaks the reply. A web validation
     * failure throws before any write, bypassing the log; the new-message gate covers the rest.
     */
    public function replyTicket(Request $request, $id)
    {
        $ticket         = SupportTicket::where('id', $id)->first();
        $beforeStatus   = $ticket?->status;
        $beforeMaxMsgId = $ticket ? (int) SupportMessage::where('support_ticket_id', $ticket->id)->max('id') : 0;

        $response = $this->runReplyTicket($request, $id); // unchanged trait behavior

        if ($ticket) {
            $newMessage = SupportMessage::where('support_ticket_id', $ticket->id)
                ->where('id', '>', $beforeMaxMsgId)
                ->where('admin_id', $this->user->id)
                ->latest('id')->first();

            if ($newMessage) {
                app(AdminAuditLogger::class)->logMutation(
                    'support.ticket.replied',
                    $this->user,
                    'ticket',
                    $ticket->id,
                    ['status' => $beforeStatus],
                    ['status' => Status::TICKET_ANSWER],
                    [
                        'ticket'           => $ticket->ticket,
                        'message_id'       => $newMessage->id,
                        'message_length'   => mb_strlen((string) $newMessage->message),
                        'attachment_count' => $newMessage->attachments()->count(),
                    ],
                    request()
                );
            }
        }

        return $response;
    }

    /**
     * F14: admin ticket close + semantic audit. Runs the unchanged shared-trait close
     * (status → TICKET_CLOSE, redirect/flash) and writes a `support.ticket.closed` audit
     * row inside the same transaction (status-only; no message body/attachments/email).
     * Behavior is identical to the trait close; only the audit row is added.
     */
    public function closeTicket($id)
    {
        $ticket = SupportTicket::where('id', $id)->first();
        $before = ['status' => $ticket?->status];

        return DB::transaction(function () use ($id, $ticket, $before) {
            $response = $this->runCloseTicket($id); // unchanged trait behavior (mutation + redirect/flash)

            if ($ticket) {
                app(AdminAuditLogger::class)->logMutation(
                    'support.ticket.closed',
                    $this->user,
                    'ticket',
                    $ticket->id,
                    $before,
                    ['status' => Status::TICKET_CLOSE],
                    ['ticket' => $ticket->ticket],
                    request()
                );
            }

            return $response;
        });
    }

    public function ticketDelete($id)
    {
        $message = SupportMessage::findOrFail($id);
        $path = getFilePath('ticket');
        if ($message->attachments()->count() > 0) {
            foreach ($message->attachments as $attachment) {
                fileManager()->removeFile($path.'/'.$attachment->attachment);
                $attachment->delete();
            }
        }
        $message->delete();
        $notify[] = ['success', "Support ticket deleted successfully"];
        return back()->withNotify($notify);

    }

}
