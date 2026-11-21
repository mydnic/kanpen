<?php

namespace Mydnic\Kanpen\Test;

use Illuminate\Support\Facades\Route;
use Mydnic\Kanpen\Models\Subscriber;
use PHPUnit\Framework\Attributes\Test;

/**
 * A site that collects addresses through its own form wants verification and
 * unsubscription without a public subscribe endpoint, which 'routes.web' alone
 * cannot express.
 */
class WithoutSubscribeRoutesTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app->get('config')->set('kanpen.routes.subscribe', false);
    }

    #[Test]
    public function it_does_not_register_the_subscribe_endpoints(): void
    {
        $this->assertFalse(Route::has('kanpen.store'));
        $this->assertFalse(Route::has('kanpen.api.store'));
    }

    #[Test]
    public function it_keeps_the_rest_of_the_web_group(): void
    {
        $this->assertTrue(Route::has('kanpen.verify'));
        $this->assertTrue(Route::has('kanpen.unsubscribe'));
        $this->assertTrue(Route::has('kanpen.unsubscribe.destroy'));
        $this->assertTrue(Route::has('kanpen.tracking.open'));
        $this->assertTrue(Route::has('kanpen.tracking.click'));
    }

    #[Test]
    public function it_returns_404_on_the_web_subscribe_endpoint(): void
    {
        $this->post('/kanpen/subscriber', ['email' => 'some@email.com'])
            ->assertStatus(404);

        $this->assertEquals(0, Subscriber::count());
    }

    #[Test]
    public function it_returns_404_on_the_api_subscribe_endpoint(): void
    {
        $this->postJson('/kanpen-api/subscriber', ['email' => 'some@email.com'])
            ->assertStatus(404);

        $this->assertEquals(0, Subscriber::count());
    }

    #[Test]
    public function it_still_unsubscribes_through_the_package_routes(): void
    {
        $subscriber = Subscriber::create(['email' => 'some@email.com']);

        $this->delete("/kanpen/unsubscribe/{$subscriber->unsubscribe_token}")
            ->assertStatus(200);

        $this->assertEquals(0, Subscriber::count());
    }
}
