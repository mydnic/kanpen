<?php

namespace Mydnic\Kanpen\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Mydnic\Kanpen\Events\SubscriberCreated;
use Mydnic\Kanpen\Events\SubscriberDeleted;
use Mydnic\Kanpen\Notifications\SubscriberVerifyEmail;

class Subscriber extends Model
{
    use Notifiable, SoftDeletes;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTable(config('kanpen.tables.subscribers'));
    }

    protected $fillable = [
        'email',
        'email_verified_at',
        'unsubscribe_token',
    ];

    protected $dispatchesEvents = [
        'created' => SubscriberCreated::class,
        'deleted' => SubscriberDeleted::class,
    ];

    protected static function booted(): void
    {
        static::creating(function (self $subscriber) {
            $subscriber->unsubscribe_token ??= Str::random(64);
        });
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(CampaignDelivery::class);
    }

    public function hasVerifiedEmail(): bool
    {
        return ! is_null($this->email_verified_at);
    }

    public function markEmailAsVerified(): bool
    {
        return $this->forceFill([
            'email_verified_at' => $this->freshTimestamp(),
        ])->save();
    }

    public function sendEmailVerificationNotification(): void
    {
        try {
            $this->notify(new SubscriberVerifyEmail);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function getEmailForVerification(): string
    {
        return $this->email;
    }

    /**
     * Return the token-based unsubscribe URL for this subscriber.
     * Generates and persists a token on-the-fly for legacy rows that predate the column.
     * The route name is configurable so apps that register their own routes
     * instead of the package ones can still use this method.
     */
    public function getUnsubscribeUrl(): string
    {
        if (empty($this->unsubscribe_token)) {
            $this->updateQuietly(['unsubscribe_token' => Str::random(64)]);
        }

        return route(
            config('kanpen.routes.unsubscribe_name', 'kanpen.unsubscribe'),
            ['token' => $this->unsubscribe_token]
        );
    }
}
