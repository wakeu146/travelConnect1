<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Services\ConversationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TravelerConversationController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->isTraveler(), 403);
        $search = trim($request->string('search')->toString());
        $conversations = $request->user()->travelerConversations()
            ->with(['agency:id,company_name', 'latestMessage.sender:id,name'])
            ->withCount(['messages as unread_messages_count' => fn ($query) => $query
                ->whereNull('read_at')
                ->where('sender_id', '!=', $request->user()->id)])
            ->when($search !== '', fn ($query) => $query->where(function ($conversationQuery) use ($search): void {
                $conversationQuery->where('subject', 'like', "%{$search}%")
                    ->orWhereHas('agency', fn ($agencyQuery) => $agencyQuery->where('company_name', 'like', "%{$search}%"))
                    ->orWhereHas('messages', fn ($messageQuery) => $messageQuery->where('body', 'like', "%{$search}%"));
            }))
            ->orderByDesc('updated_at')
            ->paginate(20)
            ->withQueryString();

        return view('conversations.index', ['conversations' => $conversations, 'search' => $search]);
    }

    public function show(Request $request, Conversation $conversation): View
    {
        abort_unless($request->user()->isTraveler() && $conversation->traveler_id === $request->user()->id, 404);

        $conversation->messages()
            ->where('sender_id', '!=', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
        $conversation->load(['agency:id,company_name', 'booking:id,booking_reference,package_id', 'booking.package:id,title']);
        $messages = $conversation->messages()->with('sender:id,name')->oldest()->paginate(50)->withQueryString();

        return view('conversations.show', [
            'conversation' => $conversation,
            'messages' => $messages,
            'backRoute' => 'account.conversations.index',
            'sendRoute' => 'account.conversations.messages.store',
        ]);
    }

    public function send(Request $request, Conversation $conversation, ConversationService $conversationService): RedirectResponse
    {
        abort_unless($request->user()->isTraveler() && $conversation->traveler_id === $request->user()->id, 404);
        $data = $request->validate(['body' => ['required', 'string', 'min:1', 'max:4000']]);

        $conversationService->send($conversation, $request->user(), $data['body']);

        return redirect()->route('account.conversations.show', ['conversation' => $conversation->id, 'lang' => app()->getLocale()]);
    }
}
