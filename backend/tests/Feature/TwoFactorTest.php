<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure default migrations run
        $this->artisan('migrate');
    }

    public function test_2fa_setup_confirm_and_login_flow(): void
    {
        $this->withoutMiddleware();
        $user = User::factory()->create([
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => Hash::make('password123'),
            'role' => 'owner',
        ]);

        // Owner calls setup
        Sanctum::actingAs($user);
        $setup = $this->postJson('/api/security/2fa/setup');
        $setup->assertStatus(200)->assertJsonStructure(['otpauth_url', 'qr_svg']);

        // Generate a valid code using the stored secret
        $secret = $user->fresh()->getTwoFactorSecretDecrypted();
        $this->assertNotEmpty($secret);
        $g2fa = new Google2FA();
        $code = $g2fa->getCurrentOtp($secret);

        // Confirm
        $confirm = $this->postJson('/api/security/2fa/confirm', ['code' => $code]);
        $confirm->assertStatus(200)->assertJsonStructure(['recovery_codes']);
        $this->assertTrue($user->fresh()->isTwoFactorEnabled());

        // Login step 1
        $login1 = $this->postJson('/api/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'password123',
            'device_name' => 'test',
        ]);
        $login1->assertStatus(200)->assertJson(['requires_2fa' => true]);
        $challenge = $login1->json();

        // Login step 2 with TOTP
        $code2 = $g2fa->getCurrentOtp($secret);
        $login2 = $this->postJson('/api/auth/login/2fa', [
            'challenge_id' => $challenge['challenge_id'],
            'challenge_token' => $challenge['challenge_token'],
            'code' => $code2,
            'device_name' => 'test',
            'remember_device' => true,
        ]);
        $login2->assertStatus(200)->assertJsonStructure(['token', 'user', 'trusted_device_token']);
    }
}
