<?php

namespace Mydnic\Kanpen\Test;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Mydnic\Kanpen\Models\Subscriber;
use Mydnic\Kanpen\Traits\HasNewsletterSubscription;
use PHPUnit\Framework\Attributes\Test;

class FakeUser extends Model
{
    use HasNewsletterSubscription;

    protected $table = 'fake_users';

    protected $fillable = ['email', 'subscribed_to_newsletter', 'email_verified_at'];

    public $timestamps = false;

    public function shouldBeSubscribed(): bool
    {
        return (bool) $this->subscribed_to_newsletter;
    }

    public function onUnsubscribed(): void
    {
        $this->updateQuietly(['subscribed_to_newsletter' => false]);
    }
}

class SyncTest extends TestCase
{
    protected function setUpDatabase(): void
    {
        parent::setUpDatabase();

        Schema::create('fake_users', function ($table) {
            $table->id();
            $table->string('email')->unique();
            $table->boolean('subscribed_to_newsletter')->default(false);
            $table->timestamp('email_verified_at')->nullable();
        });
    }

    #[Test]
    public function it_subscribes_user_when_flag_is_true(): void
    {
        FakeUser::create([
            'email' => 'user@example.com',
            'subscribed_to_newsletter' => true,
        ]);

        $this->assertDatabaseHas(config('kanpen.tables.subscribers'), ['email' => 'user@example.com']);
    }

    #[Test]
    public function it_does_not_subscribe_when_flag_is_false(): void
    {
        FakeUser::create([
            'email' => 'user@example.com',
            'subscribed_to_newsletter' => false,
        ]);

        $this->assertDatabaseMissing(config('kanpen.tables.subscribers'), ['email' => 'user@example.com']);
    }

    #[Test]
    public function it_unsubscribes_when_flag_is_turned_off(): void
    {
        $user = FakeUser::create([
            'email' => 'user@example.com',
            'subscribed_to_newsletter' => true,
        ]);

        $this->assertDatabaseHas(config('kanpen.tables.subscribers'), ['email' => 'user@example.com']);

        $user->update(['subscribed_to_newsletter' => false]);

        $this->assertEquals(0, Subscriber::count());
    }

    #[Test]
    public function it_calls_on_unsubscribed_when_subscriber_is_externally_removed(): void
    {
        $user = FakeUser::create([
            'email' => 'user@example.com',
            'subscribed_to_newsletter' => true,
        ]);

        Subscriber::where('email', 'user@example.com')->first()->delete();

        $user->refresh();
        $this->assertFalse((bool) $user->subscribed_to_newsletter);
    }

    #[Test]
    public function it_restores_soft_deleted_subscriber_on_re_subscribe(): void
    {
        $user = FakeUser::create([
            'email' => 'user@example.com',
            'subscribed_to_newsletter' => true,
        ]);

        $user->update(['subscribed_to_newsletter' => false]);
        $this->assertEquals(0, Subscriber::count());

        $user->update(['subscribed_to_newsletter' => true]);
        $this->assertEquals(1, Subscriber::count());
    }

    #[Test]
    public function it_copies_email_verified_at_to_subscriber_when_present(): void
    {
        $verifiedAt = now()->startOfSecond();

        FakeUser::create([
            'email' => 'user@example.com',
            'subscribed_to_newsletter' => true,
            'email_verified_at' => $verifiedAt,
        ]);

        $this->assertDatabaseHas(config('kanpen.tables.subscribers'), [
            'email' => 'user@example.com',
            'email_verified_at' => $verifiedAt->toDateTimeString(),
        ]);
    }
}
