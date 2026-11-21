<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class LoginAttempts
{
    /** @return array{failures: int, seconds: int} */
    public function verify(string $email, ?User $user, ?string $ip, callable $check): array
    {
        $key = $this->key($email);
        $table = fn () => DB::table('account_locks')->where('account_key', $key);

        return DB::transaction(function () use ($key, $table, $user, $ip, $check): array {
            DB::table('account_locks')->upsert([
                'account_key' => $key,
                'updated_at' => now(),
            ], ['account_key'], ['updated_at']);
            $lock = $table()->lockForUpdate()->first();
            $now = now();
            $until = $lock->locked_until === null ? null : Carbon::parse($lock->locked_until);

            if ($until !== null && $until->isAfter($now)) {
                return ['failures' => 0, 'seconds' => (int) ceil($now->diffInSeconds($until))];
            }
            if ($check()) {
                $table()->delete();

                return ['failures' => 0, 'seconds' => 0];
            }

            $inWindow = $until === null && $lock->window_started_at !== null
                && Carbon::parse($lock->window_started_at)->isAfter($now->copy()->subMinutes(5));
            $failures = $inWindow ? $lock->failures + 1 : 1;
            $seconds = $failures === 3 ? 180 : 0;

            DB::table('login_attempts')->insert([
                'user_id' => $user?->id,
                'account_key' => $key,
                'ip_address' => $ip,
                'attempted_at' => $now,
            ]);
            $table()->update([
                'user_id' => $user?->id,
                'failures' => $failures,
                'window_started_at' => $inWindow ? $lock->window_started_at : $now,
                'locked_until' => $seconds ? $now->copy()->addSeconds($seconds) : null,
                'updated_at' => $now,
            ]);

            return ['failures' => $failures, 'seconds' => $seconds];
        }, 3);
    }

    public function lock(User $user, int $seconds): void
    {
        DB::table('account_locks')->upsert([
            'account_key' => $this->key($user->email),
            'user_id' => $user->id,
            'failures' => 0,
            'window_started_at' => null,
            'locked_until' => now()->addSeconds($seconds),
            'updated_at' => now(),
        ], ['account_key'], ['user_id', 'failures', 'window_started_at', 'locked_until', 'updated_at']);
    }

    public function clear(string $email): void
    {
        DB::table('account_locks')->where('account_key', $this->key($email))->delete();
    }

    private function key(string $email): string
    {
        return hash_hmac('sha256', $email, config('app.key'));
    }
}
