<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Services\ConversationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AgencyConversationController extends Controller
{
    public function index(Request $request): View
    {
        $agency = $request->user()->agency()->firstOrFail();
        $search = trim($request->string('search')->toString());
        $status = $request->query('status', '');

        $conversations = $agency->conversations()
            ->with(['traveler:id,name,email,phone,profile_photo_path', 'latestMessage.sender:id,name'])
            ->withCount(['messages as unread_messages_count' => fn ($query) => $query
                ->whereNull('read_at')
                ->where('sender_id', '!=', $request->user()->id)])
            ->when($search !== '', fn ($query) => $query->where(function ($conversationQuery) use ($search): void {
                $conversationQuery->where('subject', 'like', "%{$search}%")
                    ->orWhereHas('traveler', fn ($travelerQuery) => $travelerQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"))
                    ->orWhereHas('messages', fn ($messageQuery) => $messageQuery->where('body', 'like', "%{$search}%"));
            }))
            ->when(in_array($status, ['open', 'closed'], true), fn ($query) => $query->where('status', $status))
            ->orderByDesc('updated_at')
            ->paginate(20)
            ->withQueryString();

        return view('agencies.conversations.index', compact('agency', 'conversations', 'search', 'status'));
    }

    public function show(Request $request, Conversation $conversation): View
    {
        $agency = $request->user()->agency()->firstOrFail();
        abort_unless($conversation->agency_id === $agency->id, 404);

        $conversation->messages()
            ->where('sender_id', '!=', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $conversation->load(['traveler:id,name,email,phone,profile_photo_path', 'booking:id,booking_reference,package_id', 'booking.package:id,title']);
        $messages = $conversation->messages()->with('sender:id,name')->oldest()->paginate(50)->withQueryString();

        return view('conversations.show', [
            'conversation' => $conversation,
            'messages' => $messages,
            'backRoute' => 'agency.conversations.index',
            'sendRoute' => 'agency.conversations.messages.store',
        ]);
    }

    public function send(Request $request, Conversation $conversation, ConversationService $conversationService): RedirectResponse
    {
        $agency = $request->user()->agency()->firstOrFail();
        abort_unless($conversation->agency_id === $agency->id, 404);
        $data = $request->validate(['body' => ['required', 'string', 'min:1', 'max:4000']]);

        $conversationService->send($conversation, $request->user(), $data['body']);

        return redirect()->route('agency.conversations.show', ['conversation' => $conversation->id, 'lang' => app()->getLocale()]);
    }
}
