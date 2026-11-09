<?php

namespace Mydnic\Kanpen\Test;

use PHPUnit\Framework\Attributes\Test;

class SubscribeThrottleTest extends TestCase
{
    /**
     * Addresses on a .invalid domain (RFC 2606) never resolve, so every request
     * that reaches the controller stops at validation with a 422. Anything that
     * comes back 429 was stopped by the throttle instead.
     */
    protected function subscribe(string $email, string $ip = '10.0.0.1')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/kanpen-api/subscriber', ['email' => $email]);
    }

    #[Test]
    public function it_caps_subscribe_attempts_per_ip(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->subscribe("user{$i}@throttle.invalid")->assertStatus(422);
        }

        $this->subscribe('user11@throttle.invalid')->assertStatus(429);
    }

    #[Test]
    public function it_caps_subscribe_attempts_per_email_across_ips(): void
    {
        // A per-IP cap alone does not protect a mailbox from a pool of IPs.
        foreach (['10.0.0.1', '10.0.0.2', '10.0.0.3'] as $ip) {
            $this->subscribe('target@throttle.invalid', $ip)->assertStatus(422);
        }

        $this->subscribe('target@throttle.invalid', '10.0.0.4')->assertStatus(429);
    }

    #[Test]
    public function it_matches_emails_case_insensitively_when_throttling(): void
    {
        $this->subscribe('target@throttle.invalid', '10.0.0.1')->assertStatus(422);
        $this->subscribe('Target@Throttle.Invalid', '10.0.0.2')->assertStatus(422);
        $this->subscribe(' TARGET@throttle.invalid ', '10.0.0.3')->assertStatus(422);

        $this->subscribe('target@throttle.invalid', '10.0.0.4')->assertStatus(429);
    }

    #[Test]
    public function it_throttles_the_web_form_route_too(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
                ->post('/kanpen/subscriber', ['email' => "user{$i}@throttle.invalid"]);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->post('/kanpen/subscriber', ['email' => 'user11@throttle.invalid'])
            ->assertStatus(429);
    }

    #[Test]
    public function it_applies_no_limit_when_throttling_is_disabled(): void
    {
        config()->set('kanpen.throttle.enabled', false);

        for ($i = 1; $i <= 20; $i++) {
            $this->subscribe("user{$i}@throttle.invalid")->assertStatus(422);
        }
    }

    #[Test]
    public function it_honours_the_configured_caps(): void
    {
        config()->set('kanpen.throttle.per_ip', ['attempts' => 2, 'minutes' => 1]);

        $this->subscribe('user1@throttle.invalid')->assertStatus(422);
        $this->subscribe('user2@throttle.invalid')->assertStatus(422);
        $this->subscribe('user3@throttle.invalid')->assertStatus(429);
    }
}
