<?php

namespace App\Services;

use App\Models\AutoLoginToken;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AutoLoginService
{
    public static function generate($userId)
    {
        $plain = Str::random(64);

        $record = AutoLoginToken::create([
            'user_id'    => $userId,
            'token'      => hash('sha256', $plain),
            'expires_at' => Carbon::now()->addMinutes(2),
            'used'       => false,
        ]);

        $record->plain_token = $plain;

        return (object)[
            'token' => $plain
        ];
    }

    public static function validateToken($token)
    {
        $hashed = hash('sha256', $token);

        $record = AutoLoginToken::where('token', $hashed)->first();

        if (!$record || $record->used) return null;
        if ($record->expires_at && $record->expires_at->isPast()) return null;

        $record->update(['used' => true]);

        return $record->user;
    }
}