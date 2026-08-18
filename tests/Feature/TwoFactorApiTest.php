<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_requires_two_factor_when_enabled(): void
    {
        $user = $this->userWithTwoFactor();

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])
            ->assertOk()
            ->assertJsonPath('requiresTwoFactor', true)
            ->assertJsonPath('user.twoFactorEnabled', true)
            ->assertJsonMissing(['token' => true])
            ->assertJsonStructure(['twoFactorToken']);
    }

    public function test_user_can_verify_two_factor_and_receive_token(): void
    {
        $user = $this->userWithTwoFactor();
        $google2fa = new Google2FA;
        $code = $google2fa->getCurrentOtp($user->two_factor_secret);

        $login = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $challengeToken = $login->json('twoFactorToken');

        $this->postJson('/api/auth/two-factor/verify', [
            'twoFactorToken' => $challengeToken,
            'code' => $code,
        ])
            ->assertOk()
            ->assertJsonStructure(['token', 'user'])
            ->assertJsonPath('user.twoFactorEnabled', true);
    }

    public function test_user_can_enable_and_disable_two_factor(): void
    {
        $user = User::factory()->create(['password' => 'password123']);
        $token = $user->createToken('test')->plainTextToken;
        $headers = ['Authorization' => 'Bearer '.$token];

        $setup = $this->withHeaders($headers)
            ->postJson('/api/auth/two-factor/setup')
            ->assertOk()
            ->assertJsonStructure(['secret', 'otpauthUrl']);

        $secret = $setup->json('secret');
        $code = (new Google2FA)->getCurrentOtp($secret);

        $this->withHeaders($headers)
            ->postJson('/api/auth/two-factor/confirm', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('user.twoFactorEnabled', true);

        $user->refresh();
        $disableCode = (new Google2FA)->getCurrentOtp($user->two_factor_secret);

        $this->withHeaders($headers)
            ->postJson('/api/auth/two-factor/disable', [
                'password' => 'password123',
                'code' => $disableCode,
            ])
            ->assertOk()
            ->assertJsonPath('user.twoFactorEnabled', false);
    }

    public function test_sensitive_person_phone_is_encrypted_at_rest(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/persons', [
                'name' => 'Ravi',
                'phone' => '9876543210',
            ])
            ->assertCreated();

        $rawPhone = \DB::table('persons')->value('phone');
        $this->assertNotSame('9876543210', $rawPhone);

        $this->withToken($token)
            ->getJson('/api/persons')
            ->assertOk()
            ->assertJsonPath('persons.0.phone', '9876543210');
    }

    private function userWithTwoFactor(): User
    {
        $secret = (new TwoFactorService(new Google2FA))->generateSecret();

        return User::factory()->create([
            'password' => 'password123',
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }
}
