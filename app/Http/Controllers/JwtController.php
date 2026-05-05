<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class JwtController extends Controller
{
    public function index()
    {
        return view('jwt.index');
    }

    public function analyze(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        $token = trim($request->input('token'));
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Invalid JWT format. A JWT must have exactly 3 parts separated by dots (header.payload.signature).');
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        $header   = $this->base64UrlDecode($headerB64);
        $payload  = $this->base64UrlDecode($payloadB64);
        $signature = $signatureB64; // Raw signature (binary, shown as base64url)

        $headerJson  = json_decode($header, true);
        $payloadJson = json_decode($payload, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($headerJson)) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Could not decode JWT header. The token may be malformed or corrupt.');
        }

        if (!is_array($payloadJson)) {
            $payloadJson = ['raw' => $payload];
        }

        // Expiry validation
        $expiryStatus = 'no_exp';
        $expiryMessage = 'No expiration claim (exp) found in this token.';
        $expiryTime = null;
        $issuedAt = null;
        $notBefore = null;
        $remainingSeconds = null;

        if (isset($payloadJson['exp'])) {
            $expTimestamp = (int) $payloadJson['exp'];
            $now = time();
            $expiryTime = date('Y-m-d H:i:s T', $expTimestamp);
            $remainingSeconds = $expTimestamp - $now;

            if ($now > $expTimestamp) {
                $expiryStatus = 'expired';
                $expiredSecondsAgo = $now - $expTimestamp;
                $expiryMessage = 'Token EXPIRED ' . $this->humanDuration($expiredSecondsAgo) . ' ago.';
            } else {
                $expiryStatus = 'valid';
                $expiryMessage = 'Token is valid for ' . $this->humanDuration($remainingSeconds) . ' more.';
            }
        }

        if (isset($payloadJson['iat'])) {
            $issuedAt = date('Y-m-d H:i:s T', (int) $payloadJson['iat']);
        }
        if (isset($payloadJson['nbf'])) {
            $notBefore = date('Y-m-d H:i:s T', (int) $payloadJson['nbf']);
        }

        $algorithm = $headerJson['alg'] ?? 'Unknown';
        $tokenType = $headerJson['typ'] ?? 'Unknown';

        $result = [
            'algorithm'      => $algorithm,
            'tokenType'      => $tokenType,
            'header'         => $headerJson,
            'header_raw'     => json_encode($headerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'payload'        => $payloadJson,
            'payload_raw'    => json_encode($payloadJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'signature'      => $signature,
            'expiry_status'  => $expiryStatus,
            'expiry_message' => $expiryMessage,
            'expiry_time'    => $expiryTime,
            'issued_at'      => $issuedAt,
            'not_before'     => $notBefore,
            'original_token' => $token,
        ];

        return redirect()->back()->with('jwt_result', $result)->withInput();
    }

    private function base64UrlDecode(string $input): string
    {
        // Add padding if needed
        $remainder = strlen($input) % 4;
        if ($remainder) {
            $input .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($input, '-_', '+/'));
    }

    private function humanDuration(int $seconds): string
    {
        if ($seconds < 60) return $seconds . 's';
        if ($seconds < 3600) return floor($seconds / 60) . 'm ' . ($seconds % 60) . 's';
        if ($seconds < 86400) return floor($seconds / 3600) . 'h ' . floor(($seconds % 3600) / 60) . 'm';
        return floor($seconds / 86400) . 'd ' . floor(($seconds % 86400) / 3600) . 'h';
    }
}
