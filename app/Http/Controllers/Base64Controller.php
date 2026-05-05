<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class Base64Controller extends Controller
{
    public function index()
    {
        return view('base64.index');
    }

    public function encodeText(Request $request)
    {
        $request->validate([
            'text' => ['required', 'string', 'max:500000'],
        ]);

        $encoded = base64_encode($request->input('text'));

        return response()->json([
            'success'  => true,
            'result'   => $encoded,
            'input_bytes'  => strlen($request->input('text')),
            'output_bytes' => strlen($encoded),
        ]);
    }

    public function decodeText(Request $request)
    {
        $request->validate([
            'text' => ['required', 'string', 'max:700000'],
        ]);

        $input = trim($request->input('text'));

        // Validate Base64 format (allow URL-safe variant too)
        $clean = str_replace(['-', '_'], ['+', '/'], $input);
        // Add padding if needed
        $padded = $clean . str_repeat('=', (4 - strlen($clean) % 4) % 4);

        // Check characters
        if (!preg_match('/^[A-Za-z0-9+\/=]+$/', $padded)) {
            return response()->json([
                'success' => false,
                'error'   => 'Invalid Base64 input: contains illegal characters.',
            ], 422);
        }

        $decoded = base64_decode($padded, true);

        if ($decoded === false) {
            return response()->json([
                'success' => false,
                'error'   => 'Invalid Base64 input: decoding failed.',
            ], 422);
        }

        // Detect if output is binary (non-UTF8)
        $isBinary = !mb_check_encoding($decoded, 'UTF-8');

        return response()->json([
            'success'      => true,
            'result'       => $isBinary ? null : $decoded,
            'is_binary'    => $isBinary,
            'input_bytes'  => strlen($input),
            'output_bytes' => strlen($decoded),
            'mime_hint'    => $isBinary ? $this->guessMime($decoded) : 'text/plain',
            'base64_for_download' => $isBinary ? base64_encode($decoded) : null,
        ]);
    }

    public function encodeFile(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240'], // 10 MB
        ]);

        $file    = $request->file('file');
        $content = file_get_contents($file->getRealPath());
        $encoded = base64_encode($content);
        $mime    = $file->getMimeType();

        return response()->json([
            'success'      => true,
            'result'       => $encoded,
            'filename'     => $file->getClientOriginalName(),
            'mime'         => $mime,
            'input_bytes'  => strlen($content),
            'output_bytes' => strlen($encoded),
            'data_url'     => 'data:' . $mime . ';base64,' . $encoded,
        ]);
    }

    public function decodeFile(Request $request)
    {
        $request->validate([
            'text'     => ['required', 'string', 'max:15000000'],
            'filename' => ['nullable', 'string', 'max:255'],
        ]);

        $input  = trim($request->input('text'));
        $clean  = str_replace(['-', '_', "\n", "\r", ' '], ['+', '/', '', '', ''], $input);
        $padded = $clean . str_repeat('=', (4 - strlen($clean) % 4) % 4);

        if (!preg_match('/^[A-Za-z0-9+\/=]+$/', $padded)) {
            return response()->json(['success' => false, 'error' => 'Invalid Base64 input.'], 422);
        }

        $decoded = base64_decode($padded, true);

        if ($decoded === false) {
            return response()->json(['success' => false, 'error' => 'Decoding failed.'], 422);
        }

        $mime     = $this->guessMime($decoded);
        $filename = $request->input('filename', 'decoded_file');

        return response()->json([
            'success'     => true,
            'mime'        => $mime,
            'filename'    => $filename,
            'size_bytes'  => strlen($decoded),
            'data_url'    => 'data:' . $mime . ';base64,' . base64_encode($decoded),
        ]);
    }

    private function guessMime(string $data): string
    {
        $sig = substr($data, 0, 12);
        $hex = bin2hex($sig);

        if (str_starts_with($hex, 'ffd8ff')) return 'image/jpeg';
        if (str_starts_with($hex, '89504e47')) return 'image/png';
        if (str_starts_with($hex, '47494638')) return 'image/gif';
        if (str_starts_with($hex, '25504446')) return 'application/pdf';
        if (str_starts_with($hex, '504b0304')) return 'application/zip';
        if (str_starts_with($hex, '52494646') && substr($hex, 16, 8) === '57415645') return 'audio/wav';

        // Check if XML or HTML
        $trimmed = ltrim($data);
        if (str_starts_with($trimmed, '<?xml') || str_starts_with($trimmed, '<svg')) return 'image/svg+xml';
        if (stripos($trimmed, '<html') !== false) return 'text/html';

        return 'application/octet-stream';
    }
}
