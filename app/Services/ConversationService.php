<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Inquiry;
use App\Models\User;
use App\Notifications\AgencyActivityNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConversationService
{
    public function startForInquiry(Inquiry $inquiry): Conversation
    {
        return DB::transaction(function () use ($inquiry): Conversation {
            $conversation = Conversation::query()->firstOrCreate(
                ['inquiry_id' => $inquiry->id],
                [
                    'agency_id' => $inquiry->agency_id,
                    'traveler_id' => $inquiry->user_id,
                    'subject' => $inquiry->subject,
                    'status' => $inquiry->status->value === 'closed' ? 'closed' : 'open',
                ]
            );

            if ($conversation->wasRecentlyCreated) {
                $conversation->messages()->create([
                    'sender_id' => $inquiry->user_id,
                    'body' => $inquiry->message,
                ]);

                $agencyOwner = $conversation->agency()->with('owner')->firstOrFail()->owner;
                $agencyOwner?->notify(new AgencyActivityNotification(
                    __('messages.new_conversation_notification_title'),
                    __('messages.new_inquiry_message_notification', ['name' => $inquiry->user?->name ?? __('messages.traveler'), 'subject' => $inquiry->subject]),
                    'conversation',
                    route('agency.conversations.show', ['conversation' => $conversation->id, 'lang' => app()->getLocale()])
                ));
            }

            return $conversation;
        });
    }

    public function send(Conversation $conversation, User $sender, string $body): ConversationMessage
    {
        $isAgencyParticipant = $sender->isAgencyOwner() && $sender->agency()->whereKey($conversation->agency_id)->exists();
        $isTravelerParticipant = $sender->isTraveler() && $conversation->traveler_id === $sender->id;

        if (! $isAgencyParticipant && ! $isTravelerParticipant) {
            throw new AuthorizationException;
        }

        if ($conversation->status !== 'open') {
            throw ValidationException::withMessages(['body' => __('messages.conversation_closed')]);
        }

        $message = DB::transaction(function () use ($conversation, $sender, $body): ConversationMessage {
            $message = $conversation->messages()->create([
                'sender_id' => $sender->id,
                'body' => $body,
            ]);

            $conversation->touch();

            return $message;
        });

        /** @var User|null $recipient */
        $recipient = $isAgencyParticipant
            ? $conversation->traveler()->first()
            : $conversation->agency()->with('owner')->firstOrFail()->owner;

        $recipient?->notify(new AgencyActivityNotification(
            __('messages.new_conversation_notification_title'),
            __('messages.new_conversation_message_notification', ['name' => $sender->name, 'subject' => $conversation->subject]),
            'conversation',
            $isAgencyParticipant
                ? route('account.conversations.show', ['conversation' => $conversation->id, 'lang' => app()->getLocale()])
                : route('agency.conversations.show', ['conversation' => $conversation->id, 'lang' => app()->getLocale()])
        ));

        return $message;
    }
}
