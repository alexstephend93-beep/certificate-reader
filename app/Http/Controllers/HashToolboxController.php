<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class HashToolboxController extends Controller
{
    public function index()
    {
        $history = session('hash_history', []);
        return view('hash-toolbox.index', compact('history'));
    }

    public function hashText(Request $request)
    {
        $request->validate([
            'text' => 'required|string',
        ]);

        $text = $request->input('text');
        
        $results = [
            'type' => 'Text Hash',
            'input' => Str::limit($text, 50),
            'hashes' => [
                'MD5' => md5($text),
                'SHA1' => sha1($text),
                'SHA256' => hash('sha256', $text),
                'SHA512' => hash('sha512', $text),
                'Bcrypt' => Hash::make($text),
            ],
            'timestamp' => now()->format('Y-m-d H:i:s'),
        ];

        $this->addToHistory($results);

        return redirect()->back()->with('hash_results', $results)->with('active_tab', 'text');
    }

    public function hashFile(Request $request)
    {
        $request->validate([
            'file' => 'required|file',
        ]);

        $file = $request->file('file');
        $path = $file->getRealPath();
        
        $results = [
            'type' => 'File Hash',
            'input' => $file->getClientOriginalName(),
            'hashes' => [
                'MD5' => hash_file('md5', $path),
                'SHA1' => hash_file('sha1', $path),
                'SHA256' => hash_file('sha256', $path),
            ],
            'timestamp' => now()->format('Y-m-d H:i:s'),
        ];

        $this->addToHistory($results);

        return redirect()->back()->with('hash_results', $results)->with('active_tab', 'file');
    }

    public function aesAction(Request $request)
    {
        $action = $request->input('action'); // encrypt or decrypt
        $request->validate([
            'text' => 'required|string',
            'key' => 'required|string|min:8',
        ]);

        $text = $request->input('text');
        $key = $request->input('key');
        $method = 'aes-256-cbc';
        
        // Ensure key is 32 bytes for AES-256
        $key = hash('sha256', $key, true);
        
        $result = '';
        $error = null;

        try {
            if ($action === 'encrypt') {
                $ivLength = openssl_cipher_iv_length($method);
                $iv = openssl_random_pseudo_bytes($ivLength);
                $encrypted = openssl_encrypt($text, $method, $key, 0, $iv);
                $result = base64_encode($iv . $encrypted);
            } else {
                $data = base64_decode($text);
                $ivLength = openssl_cipher_iv_length($method);
                $iv = substr($data, 0, $ivLength);
                $encrypted = substr($data, $ivLength);
                $result = openssl_decrypt($encrypted, $method, $key, 0, $iv);
                if ($result === false) {
                    throw new \Exception('Decryption failed. Invalid key or corrupted data.');
                }
            }
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage())->with('active_tab', 'aes');
        }

        $results = [
            'type' => 'AES ' . ucfirst($action),
            'input' => Str::limit($text, 30),
            'output' => $result,
            'timestamp' => now()->format('Y-m-d H:i:s'),
        ];

        $this->addToHistory($results);

        return redirect()->back()->with('aes_results', $results)->with('active_tab', 'aes');
    }

    public function generatePassword(Request $request)
    {
        $length = $request->input('length', 16);
        $useUpper = $request->has('upper');
        $useLower = $request->has('lower');
        $useNumbers = $request->has('numbers');
        $useSymbols = $request->has('symbols');

        $chars = '';
        if ($useLower) $chars .= 'abcdefghijklmnopqrstuvwxyz';
        if ($useUpper) $chars .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        if ($useNumbers) $chars .= '0123456789';
        if ($useSymbols) $chars .= '!@#$%^&*()-_=+[]{}|;:,.<>?';

        if (empty($chars)) {
            return redirect()->back()->with('error', 'Select at least one character type.')->with('active_tab', 'password');
        }

        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }

        $results = [
            'type' => 'Password Gen',
            'output' => $password,
            'timestamp' => now()->format('Y-m-d H:i:s'),
        ];

        // We don't necessarily want to store passwords in history for security, but for a toolbox we can.
        // Maybe just the type and timestamp? No, users might want to copy it again.
        $this->addToHistory($results);

        return redirect()->back()->with('password_results', $results)->with('active_tab', 'password');
    }

    private function addToHistory($operation)
    {
        $history = session('hash_history', []);
        array_unshift($history, $operation);
        $history = array_slice($history, 0, 10); // Keep last 10
        session(['hash_history' => $history]);
    }

    public function generateBcrypt(Request $request)
    {
        $request->validate([
            'password' => 'required|string|max:255',
            'rounds' => 'nullable|integer|min:4|max:31'
        ]);
        
        $rounds = $request->input('rounds', 10);
        $hash = Hash::make($request->password, ['rounds' => $rounds]);
        
        return response()->json([
            'success' => true,
            'hash' => $hash,
            'rounds' => $rounds
        ]);
    }
}
