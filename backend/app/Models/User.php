<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'two_factor_enabled' => 'boolean',
        'two_factor_confirmed_at' => 'datetime',
        'two_factor_last_used_at' => 'datetime',
    ];

    public function isTwoFactorEnabled(): bool
    {
        return (bool) $this->two_factor_enabled && ! empty($this->two_factor_secret);
    }

    public function setTwoFactorSecretEncrypted(string $secret): void
    {
        $this->two_factor_secret = Crypt::encryptString($secret);
    }

    public function getTwoFactorSecretDecrypted(): ?string
    {
        if (! $this->two_factor_secret) return null;
        try {
            return Crypt::decryptString($this->two_factor_secret);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function setRecoveryCodesEncrypted(array $codes): void
    {
        $this->two_factor_recovery_codes = Crypt::encryptString(json_encode(array_values($codes)));
    }

    public function getRecoveryCodesDecrypted(): array
    {
        if (! $this->two_factor_recovery_codes) return [];
        try {
            $json = Crypt::decryptString($this->two_factor_recovery_codes);
            $arr = json_decode($json, true);
            return is_array($arr) ? $arr : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function generateRecoveryCodes(int $count = 10): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = Str::upper(Str::random(12));
        }
        return $codes;
    }

    public function consumeRecoveryCode(string $code): bool
    {
        $codes = $this->getRecoveryCodesDecrypted();
        $idx = array_search(Str::upper(trim($code)), array_map(fn($c) => Str::upper(trim($c)), $codes), true);
        if ($idx === false) return false;
        array_splice($codes, $idx, 1);
        $this->setRecoveryCodesEncrypted($codes);
        $this->save();
        return true;
    }

    public function getTrustedDevices(): array
    {
        $raw = (string) $this->two_factor_trusted_devices;
        $arr = json_decode($raw ?: '[]', true);
        return is_array($arr) ? $arr : [];
    }

    public function addTrustedDevice(string $hashedToken, array $meta = []): void
    {
        $devices = $this->getTrustedDevices();
        $devices[] = array_merge(['token' => $hashedToken, 'created_at' => now()->toIso8601String()], $meta);
        $this->two_factor_trusted_devices = json_encode($devices);
        $this->save();
    }

    public function hasTrustedDevice(string $hashedToken): bool
    {
        $devices = $this->getTrustedDevices();
        foreach ($devices as $d) {
            if (($d['token'] ?? '') === $hashedToken) return true;
        }
        return false;
    }
}
