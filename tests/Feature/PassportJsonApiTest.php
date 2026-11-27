<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Tests\Concerns\UsesPassportKeys;
use Tests\TestCase;

/**
 * The /oauth JSON API behind the passport-* Vue components.
 */
class PassportJsonApiTest extends TestCase
{
    use RefreshDatabase, UsesPassportKeys;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPassportKeys();
    }

    public function test_guests_cannot_use_the_json_api(): void
    {
        foreach (['/oauth/clients', '/oauth/scopes', '/oauth/personal-access-tokens', '/oauth/tokens'] as $uri) {
            $this->getJson($uri)->assertUnauthorized();
        }
    }

    public function test_a_user_manages_their_oauth_clients(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/oauth/clients')->assertOk()->assertExactJson([]);

        $this->actingAs($user)->postJson('/oauth/clients', ['name' => '', 'redirect' => 'x'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'redirect']);

        $created = $this->actingAs($user)->postJson('/oauth/clients', [
            'name' => 'My App',
            'redirect' => 'https://app.test/callback',
        ])->assertCreated()->assertJsonPath('name', 'My App');

        $this->assertNotEmpty($created->json('plain_secret'));
        $this->assertArrayNotHasKey('secret', $created->json());

        $id = $created->json('id');

        $this->actingAs($user)->getJson('/oauth/clients')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.redirect_uris', ['https://app.test/callback']);

        $this->actingAs($user)->putJson("/oauth/clients/{$id}", [
            'name' => 'Renamed',
            'redirect' => 'https://app.test/a,https://app.test/b',
        ])->assertOk()->assertJsonPath('name', 'Renamed');

        $other = User::factory()->create();
        $this->actingAs($other)->deleteJson("/oauth/clients/{$id}")->assertNotFound();

        $this->actingAs($user)->deleteJson("/oauth/clients/{$id}")->assertNoContent();
        $this->actingAs($user)->getJson('/oauth/clients')->assertExactJson([]);
    }

    public function test_a_user_creates_lists_and_revokes_personal_access_tokens(): void
    {
        app(ClientRepository::class)->createPersonalAccessGrantClient('Personal Access Client', 'users');
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/oauth/scopes')->assertOk()->assertExactJson([]);

        $this->actingAs($user)->postJson('/oauth/personal-access-tokens', ['name' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $accessToken = $this->actingAs($user)->postJson('/oauth/personal-access-tokens', ['name' => 'CLI'])
            ->assertOk()
            ->json('accessToken');

        $this->assertNotEmpty($accessToken);

        $list = $this->actingAs($user)->getJson('/oauth/personal-access-tokens')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.name', 'CLI');

        $this->app['auth']->forgetGuards();
        $this->withToken($accessToken)->getJson('/api/user')->assertOk()->assertJson(['id' => $user->id]);

        // The bearer request above made 'api' the default guard, so name the session guard.
        $this->withoutToken()->actingAs($user, 'web')
            ->deleteJson('/oauth/personal-access-tokens/'.$list->json('0.id'))
            ->assertNoContent();

        $this->app['auth']->forgetGuards();
        $this->withToken($accessToken)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_a_user_sees_and_revokes_authorized_applications(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/oauth/tokens')->assertOk()->assertExactJson([]);

        $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
            'Third Party App', ['https://client.test/callback'], true, User::factory()->create()
        );
        $token = Passport::token()->forceFill([
            'id' => str_repeat('a', 80),
            'user_id' => $user->id,
            'client_id' => $client->getKey(),
            'scopes' => [],
            'revoked' => false,
            'expires_at' => now()->addDay(),
        ]);
        $token->save();

        $this->actingAs($user)->getJson('/oauth/tokens')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.client.name', 'Third Party App');

        $this->actingAs($user)->deleteJson('/oauth/tokens/'.$token->id)->assertNoContent();

        $this->assertTrue($token->fresh()->revoked);
        $this->actingAs($user)->getJson('/oauth/tokens')->assertOk()->assertExactJson([]);
    }
}
