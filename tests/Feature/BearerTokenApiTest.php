<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Tests\Concerns\UsesPassportKeys;
use Tests\TestCase;

/**
 * The demo itself: Passport issues bearer tokens and /api/user only answers to them.
 */
class BearerTokenApiTest extends TestCase
{
    use RefreshDatabase, UsesPassportKeys;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPassportKeys();
    }

    public function test_api_user_refuses_a_request_without_a_token(): void
    {
        $this->getJson('/api/user')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_api_user_refuses_a_bad_token(): void
    {
        $this->withToken('not-a-real-token')->getJson('/api/user')->assertUnauthorized();
    }

    public function test_api_user_ignores_the_web_session(): void
    {
        $this->actingAs(User::factory()->create())->getJson('/api/user')->assertUnauthorized();
    }

    public function test_api_user_returns_the_token_owner(): void
    {
        $user = User::factory()->create();

        Passport::actingAs($user);

        $this->getJson('/api/user')
            ->assertOk()
            ->assertJson(['id' => $user->id, 'email' => $user->email])
            ->assertJsonMissingPath('password')
            ->assertJsonMissingPath('remember_token');
    }

    public function test_a_personal_access_token_opens_the_api_until_it_is_revoked(): void
    {
        app(ClientRepository::class)->createPersonalAccessGrantClient('Personal Access Client', 'users');
        $user = User::factory()->create();

        $result = $user->createToken('demo');

        $this->assertNotEmpty($result->accessToken);

        $this->withToken($result->accessToken)->getJson('/api/user')
            ->assertOk()
            ->assertJson(['id' => $user->id]);

        $result->getToken()->revoke();
        $this->app['auth']->forgetGuards();

        $this->withToken($result->accessToken)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_the_password_grant_issues_a_working_token(): void
    {
        $client = app(ClientRepository::class)->createPasswordGrantClient('Password Grant Client', 'users', true);
        $user = User::factory()->create();

        $this->postJson('/oauth/token', [
            'grant_type' => 'password',
            'client_id' => $client->getKey(),
            'client_secret' => $client->plainSecret,
            'username' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(400)->assertJson(['error' => 'invalid_grant']);

        $response = $this->postJson('/oauth/token', [
            'grant_type' => 'password',
            'client_id' => $client->getKey(),
            'client_secret' => $client->plainSecret,
            'username' => $user->email,
            'password' => 'password',
            'scope' => '',
        ])->assertOk()->assertJsonStructure(['token_type', 'expires_in', 'access_token', 'refresh_token']);

        $this->assertSame('Bearer', $response->json('token_type'));

        $this->withToken($response->json('access_token'))->getJson('/api/user')
            ->assertOk()
            ->assertJson(['id' => $user->id]);

        $this->app['auth']->forgetGuards();

        $refreshed = $this->postJson('/oauth/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $response->json('refresh_token'),
            'client_id' => $client->getKey(),
            'client_secret' => $client->plainSecret,
        ])->assertOk()->json('access_token');

        $this->withToken($refreshed)->getJson('/api/user')->assertOk()->assertJson(['id' => $user->id]);
    }

    public function test_the_token_endpoint_rejects_an_unknown_client(): void
    {
        $this->postJson('/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => 999,
            'client_secret' => 'nope',
        ])->assertUnauthorized()->assertJson(['error' => 'invalid_client']);
    }

    public function test_a_client_credentials_token_has_no_user(): void
    {
        $client = app(ClientRepository::class)->createClientCredentialsGrantClient('Machine');

        $token = $this->postJson('/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->getKey(),
            'client_secret' => $client->plainSecret,
        ])->assertOk()->json('access_token');

        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_the_authorization_code_flow_issues_a_token(): void
    {
        $owner = User::factory()->create();
        $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
            'Third Party App', ['https://client.test/callback'], true, $owner
        );
        $user = User::factory()->create();

        $query = http_build_query([
            'client_id' => $client->getKey(),
            'redirect_uri' => 'https://client.test/callback',
            'response_type' => 'code',
            'scope' => '',
            'state' => 'xyz',
        ]);

        $this->get('/oauth/authorize?'.$query)->assertRedirect('/login');

        $prompt = $this->actingAs($user)->get('/oauth/authorize?'.$query)
            ->assertOk()
            ->assertSee('Third Party App')
            ->assertSee('Authorize');

        $redirect = $this->post('/oauth/authorize', [
            'state' => 'xyz',
            'client_id' => $client->getKey(),
            'auth_token' => $prompt->viewData('authToken'),
        ])->assertRedirect()->headers->get('Location');

        $this->assertStringStartsWith('https://client.test/callback?', $redirect);
        parse_str(parse_url($redirect, PHP_URL_QUERY), $params);
        $this->assertSame('xyz', $params['state']);

        $token = $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $client->getKey(),
            'client_secret' => $client->plainSecret,
            'redirect_uri' => 'https://client.test/callback',
            'code' => $params['code'],
        ])->assertOk()->json('access_token');

        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/user')->assertOk()->assertJson(['id' => $user->id]);
    }
}
