<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HmacController extends Controller
{
    public function index()
    {
        return view('hmac.index');
    }

    public function generate(Request $request)
    {
        $request->validate([
            'payload' => 'required|string',
            'secret'  => 'required|string',
            'algo'    => 'required|in:sha256,sha512,sha1,md5',
        ]);

        $payload   = $request->input('payload');
        $secret    = $request->input('secret');
        $algo      = $request->input('algo');
        $encoding  = $request->input('encoding', 'hex');

        $rawHmac   = hash_hmac($algo, $payload, $secret, true);

        $signatures = [
            'hex'    => hash_hmac($algo, $payload, $secret, false),
            'base64' => base64_encode($rawHmac),
            'base64url' => rtrim(strtr(base64_encode($rawHmac), '+/', '-_'), '='),
        ];

        // Verify signature if provided
        $verifyResult = null;
        if ($request->filled('verify_signature')) {
            $provided = trim($request->input('verify_signature'));
            $expected = $signatures[$encoding] ?? $signatures['hex'];
            $verifyResult = hash_equals($expected, $provided)
                ? ['status' => 'match', 'label' => '✅ Signature matches!']
                : ['status' => 'mismatch', 'label' => '❌ Signature does NOT match.'];
        }

        $result = [
            'payload'     => $payload,
            'algorithm'   => strtoupper($algo),
            'signatures'  => $signatures,
            'encoding'    => $encoding,
            'verify'      => $verifyResult,
        ];

        return redirect()->back()->with('hmac_result', $result)->withInput();
    }
}
