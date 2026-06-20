<?php

namespace App\Http\Controllers\Admin;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Traits\BuildsAdminReadonly;
use App\Traits\SupportTicketManager;

class SupportTicketController extends Controller
{
    use SupportTicketManager;
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
