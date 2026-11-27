<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\Passport;

/**
 * Passport 13 schema for oauth_clients (see vendor/laravel/passport/UPGRADE.md):
 * user_id becomes the owner morph, redirect becomes redirect_uris and the
 * personal_access_client / password_client flags become grant_types.
 * The oauth_personal_access_clients table is no longer used.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table) {
            $table->nullableMorphs('owner', after: 'user_id');

            $table->after('provider', function (Blueprint $table) {
                $table->text('redirect_uris')->nullable();
                $table->text('grant_types')->nullable();
            });
        });

        foreach (Passport::client()->cursor() as $client) {
            Model::withoutTimestamps(fn () => $client->forceFill([
                'owner_id' => $client->user_id,
                'owner_type' => $client->user_id
                    ? config('auth.providers.'.($client->provider ?: config('auth.guards.api.provider')).'.model')
                    : null,
                'redirect_uris' => $client->redirect_uris,
                'grant_types' => $client->grant_types,
            ])->save());
        }

        Schema::table('oauth_clients', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        Schema::table('oauth_clients', function (Blueprint $table) {
            $table->dropColumn(['user_id', 'redirect', 'personal_access_client', 'password_client']);

            $table->text('redirect_uris')->nullable(false)->change();
            $table->text('grant_types')->nullable(false)->change();
        });

        Schema::dropIfExists('oauth_personal_access_clients');
    }

    public function down(): void
    {
        Schema::create('oauth_personal_access_clients', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('client_id');
            $table->timestamps();
        });

        Schema::table('oauth_clients', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->text('redirect')->nullable();
            $table->boolean('personal_access_client')->default(false);
            $table->boolean('password_client')->default(false);
        });

        foreach (DB::table('oauth_clients')->get() as $client) {
            $grants = json_decode($client->grant_types ?? '[]', true) ?: [];

            DB::table('oauth_clients')->where('id', $client->id)->update([
                'user_id' => $client->owner_id,
                'redirect' => implode(',', json_decode($client->redirect_uris ?? '[]', true) ?: []),
                'personal_access_client' => in_array('personal_access', $grants, true),
                'password_client' => in_array('password', $grants, true),
            ]);

            if (in_array('personal_access', $grants, true)) {
                DB::table('oauth_personal_access_clients')->insert([
                    'client_id' => $client->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Schema::table('oauth_clients', function (Blueprint $table) {
            $table->dropMorphs('owner');
            $table->dropColumn(['redirect_uris', 'grant_types']);
        });
    }
};
