<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = ['name', 'email', 'password', 'phone', 'profile_photo_path', 'google_id'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'admin_session_version' => 'integer',
        ];
    }

    public function adminLoginEvents(): HasMany
    {
        return $this->hasMany(AdminLoginEvent::class);
    }

    public function adminActivities(): HasMany
    {
        return $this->hasMany(AdminUserActivity::class);
    }

    public function adminLoginChallenges(): HasMany
    {
        return $this->hasMany(AdminLoginChallenge::class);
    }

    public function adminTotpCredential(): HasOne
    {
        return $this->hasOne(AdminTotpCredential::class);
    }

    public function trustedAdminDevices(): HasMany
    {
        return $this->hasMany(TrustedAdminDevice::class);
    }

    public function agency(): HasOne
    {
        return $this->hasOne(Agency::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'user_id');
    }

    public function travelerIssues(): HasMany
    {
        return $this->hasMany(TravelerIssue::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function inquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class);
    }

    public function travelerConversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'traveler_id');
    }

    public function conversationMessages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class, 'sender_id');
    }

    public function travelRequests(): HasMany
    {
        return $this->hasMany(TravelRequest::class);
    }

    public function travelRequestProposals(): HasMany
    {
        return $this->hasMany(TravelRequestProposal::class, 'owner_id');
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function favoriteAgencies(): BelongsToMany
    {
        return $this->belongsToMany(Agency::class, 'favorites')->withTimestamps();
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMIN;
    }

    public function isAgencyOwner(): bool
    {
        return $this->role === UserRole::AGENCY_OWNER;
    }

    public function isTraveler(): bool
    {
        return $this->role === UserRole::TRAVELER;
    }
}
