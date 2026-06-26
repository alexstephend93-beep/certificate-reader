<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use phpseclib3\Crypt\RSA;
use phpseclib3\File\X509;

class SslMatcherController extends Controller
{
    public function index()
    {
        return view('ssl-matcher.index');
    }

    /**
     * Match Certificate and Private Key
     */
    public function matchCertKey(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'certificate' => 'required_without:cert_file|string',
            'cert_file' => 'required_without:certificate|file|mimes:cer,crt,pem|max:512',
            'private_key' => 'required_without:key_file|string',
            'key_file' => 'required_without:private_key|file|mimes:key,pem|max:512',
            'key_password' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            // Get certificate content
            $certContent = $request->input('certificate');
            if ($request->hasFile('cert_file')) {
                $certContent = file_get_contents($request->file('cert_file')->getPathname());
            }

            // Get private key content
            $keyContent = $request->input('private_key');
            if ($request->hasFile('key_file')) {
                $keyContent = file_get_contents($request->file('key_file')->getPathname());
            }

            $keyPassword = $request->input('key_password');

            // Parse certificate
            $certInfo = $this->parseCertificate($certContent);
            if (!$certInfo) {
                return response()->json(['success' => false, 'message' => 'Invalid certificate format. Please ensure you provide a valid PEM or DER certificate.'], 400);
            }

            // Parse private key
            $keyInfo = $this->parsePrivateKey($keyContent, $keyPassword);
            if (!$keyInfo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid private key format or wrong password. Please ensure you provide a valid RSA private key.'
                ], 400);
            }

            // Extract modulus from both
            $certModulus = $this->extractModulusFromCert($certContent);
            $keyModulus = $this->extractModulusFromKey($keyContent, $keyPassword);

            $match = ($certModulus === $keyModulus);

            return response()->json([
                'success' => true,
                'match' => $match,
                'certificate' => $certInfo,
                'private_key' => $keyInfo,
                'cert_modulus_hash' => $certModulus,
                'key_modulus_hash' => $keyModulus,
                'message' => $match ? '✅ Certificate and Private Key MATCH!' : '❌ Certificate and Private Key DO NOT MATCH!'
            ]);

        } catch (\Exception $e) {
            Log::error('Certificate-Key Match Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Match Certificate and Public Key
     */
    public function matchCertPublicKey(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'certificate' => 'required_without:cert_file|string',
            'cert_file' => 'required_without:certificate|file|mimes:cer,crt,pem|max:512',
            'public_key' => 'required_without:pub_file|string',
            'pub_file' => 'required_without:public_key|file|mimes:pub,pem,txt|max:512'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            // Get certificate content
            $certContent = $request->input('certificate');
            if ($request->hasFile('cert_file')) {
                $certContent = file_get_contents($request->file('cert_file')->getPathname());
            }

            // Get public key content
            $pubContent = $request->input('public_key');
            if ($request->hasFile('pub_file')) {
                $pubContent = file_get_contents($request->file('pub_file')->getPathname());
            }

            // Parse certificate
            $certInfo = $this->parseCertificate($certContent);
            if (!$certInfo) {
                return response()->json(['success' => false, 'message' => 'Invalid certificate format'], 400);
            }

            // Parse public key
            $pubInfo = $this->parsePublicKey($pubContent);
            if (!$pubInfo) {
                return response()->json(['success' => false, 'message' => 'Invalid public key format'], 400);
            }

            // Extract modulus
            $certModulus = $this->extractModulusFromCert($certContent);
            $pubModulus = $this->extractModulusFromPublicKey($pubContent);

            $match = ($certModulus === $pubModulus);

            return response()->json([
                'success' => true,
                'match' => $match,
                'certificate' => $certInfo,
                'public_key' => $pubInfo,
                'cert_modulus_hash' => $certModulus,
                'pub_modulus_hash' => $pubModulus,
                'message' => $match ? '✅ Certificate and Public Key MATCH!' : '❌ Certificate and Public Key DO NOT MATCH!'
            ]);

        } catch (\Exception $e) {
            Log::error('Certificate-Public Key Match Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Match Two Certificates
     */
    public function matchCerts(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'certificate1' => 'required_without:cert1_file|string',
            'cert1_file' => 'required_without:certificate1|file|mimes:cer,crt,pem|max:512',
            'certificate2' => 'required_without:cert2_file|string',
            'cert2_file' => 'required_without:certificate2|file|mimes:cer,crt,pem|max:512'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            // Get certificate 1 content
            $cert1Content = $request->input('certificate1');
            if ($request->hasFile('cert1_file')) {
                $cert1Content = file_get_contents($request->file('cert1_file')->getPathname());
            }

            // Get certificate 2 content
            $cert2Content = $request->input('certificate2');
            if ($request->hasFile('cert2_file')) {
                $cert2Content = file_get_contents($request->file('cert2_file')->getPathname());
            }

            // Parse certificates
            $cert1Info = $this->parseCertificate($cert1Content);
            $cert2Info = $this->parseCertificate($cert2Content);

            if (!$cert1Info || !$cert2Info) {
                return response()->json(['success' => false, 'message' => 'Invalid certificate format'], 400);
            }

            // Extract serial numbers and subjects
            $cert1Serial = $cert1Info['serial_number'] ?? '';
            $cert2Serial = $cert2Info['serial_number'] ?? '';
            $match = ($cert1Serial === $cert2Serial);

            return response()->json([
                'success' => true,
                'match' => $match,
                'certificate1' => $cert1Info,
                'certificate2' => $cert2Info,
                'message' => $match ? '✅ Certificates MATCH!' : '❌ Certificates DO NOT MATCH!'
            ]);

        } catch (\Exception $e) {
            Log::error('Certificate Match Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Parse Certificate using OpenSSL (reliable method)
     */
    private function parseCertificate($certContent)
    {
        try {
            // Clean and prepare certificate content
            $certContent = trim($certContent);
            
            if (empty($certContent)) {
                Log::error('Certificate content is empty');
                return null;
            }
            
            // Ensure proper PEM format
            if (strpos($certContent, '-----BEGIN CERTIFICATE-----') === false) {
                // Check if it's DER format (binary)
                if (strpos($certContent, '-----BEGIN') === false && strlen($certContent) > 0) {
                    // Try to convert DER to PEM
                    $pemCert = "-----BEGIN CERTIFICATE-----\n" . chunk_split(base64_encode($certContent), 64, "\n") . "-----END CERTIFICATE-----\n";
                    $certContent = $pemCert;
                } else {
                    // Add PEM headers if missing
                    $certContent = "-----BEGIN CERTIFICATE-----\n" . chunk_split(trim($certContent), 64, "\n") . "-----END CERTIFICATE-----\n";
                }
            }
            
            // Create temporary file
            $tempFile = tempnam(sys_get_temp_dir(), 'cert_');
            if ($tempFile === false) {
                Log::error('Failed to create temporary file');
                return null;
            }
            
            file_put_contents($tempFile, $certContent);
            
            // Validate certificate with OpenSSL
            $testOutput = shell_exec("openssl x509 -in " . escapeshellarg($tempFile) . " -noout 2>&1");
            if (strpos($testOutput, 'unable to load') !== false || strpos($testOutput, 'error') !== false) {
                unlink($tempFile);
                Log::error('OpenSSL validation failed: ' . $testOutput);
                return null;
            }
            
            // Extract certificate details
            $subject = trim(shell_exec("openssl x509 -in " . escapeshellarg($tempFile) . " -noout -subject 2>/dev/null"));
            $issuer = trim(shell_exec("openssl x509 -in " . escapeshellarg($tempFile) . " -noout -issuer 2>/dev/null"));
            $serial = trim(shell_exec("openssl x509 -in " . escapeshellarg($tempFile) . " -noout -serial 2>/dev/null"));
            $dates = shell_exec("openssl x509 -in " . escapeshellarg($tempFile) . " -noout -dates 2>/dev/null");
            $modulus = shell_exec("openssl x509 -in " . escapeshellarg($tempFile) . " -noout -modulus 2>/dev/null");
            $signatureAlgo = trim(shell_exec("openssl x509 -in " . escapeshellarg($tempFile) . " -noout -text 2>/dev/null | grep 'Signature Algorithm' | head -1"));
            
            // Remove labels
            $subject = str_replace('subject=', '', $subject);
            $issuer = str_replace('issuer=', '', $issuer);
            $serial = str_replace('serial=', '', $serial);
            
            // Parse signature algorithm
            $signatureAlgo = str_replace('Signature Algorithm:', '', $signatureAlgo);
            $signatureAlgo = trim($signatureAlgo);
            
            // Parse dates
            $notBefore = 'N/A';
            $notAfter = 'N/A';
            if ($dates) {
                if (preg_match('/notBefore=(.+)/', $dates, $matches)) {
                    $notBefore = trim($matches[1]);
                }
                if (preg_match('/notAfter=(.+)/', $dates, $matches)) {
                    $notAfter = trim($matches[1]);
                }
            }
            
            // Get certificate version
            $version = shell_exec("openssl x509 -in " . escapeshellarg($tempFile) . " -noout -text 2>/dev/null | grep 'Version:' | head -1");
            $version = trim(str_replace('Version:', '', $version));
            
            // Get public key algorithm
            $pubKeyAlgo = shell_exec("openssl x509 -in " . escapeshellarg($tempFile) . " -noout -text 2>/dev/null | grep 'Public Key Algorithm:' | head -1");
            $pubKeyAlgo = trim(str_replace('Public Key Algorithm:', '', $pubKeyAlgo));
            
            unlink($tempFile);
            
            return [
                'type' => 'X.509 Certificate',
                'subject' => $subject ?: 'N/A',
                'issuer' => $issuer ?: 'N/A',
                'serial_number' => $serial ?: 'N/A',
                'valid_from' => $notBefore,
                'valid_to' => $notAfter,
                'signature_algorithm' => $signatureAlgo ?: 'N/A',
                'public_key_algorithm' => $pubKeyAlgo ?: 'RSA',
                'version' => $version ?: 'N/A',
                'fingerprint_md5' => $this->getCertFingerprint($certContent, 'md5'),
                'fingerprint_sha1' => $this->getCertFingerprint($certContent, 'sha1'),
                'fingerprint_sha256' => $this->getCertFingerprint($certContent, 'sha256'),
            ];
            
        } catch (\Exception $e) {
            Log::error('Certificate parsing exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Parse Private Key
     */
    private function parsePrivateKey($keyContent, $password = null)
    {
        try {
            // Clean the key content
            $keyContent = trim($keyContent);
            
            if (empty($keyContent)) {
                Log::error('Private key content is empty');
                return null;
            }
            
            // Ensure proper PEM format for private key
            if (strpos($keyContent, '-----BEGIN') === false) {
                $keyContent = "-----BEGIN RSA PRIVATE KEY-----\n" . chunk_split(trim($keyContent), 64, "\n") . "-----END RSA PRIVATE KEY-----\n";
            }
            
            // Try to load with phpseclib
            $key = RSA::load($keyContent, $password);
            if (!$key) {
                // Try OpenSSL as fallback
                $tempFile = tempnam(sys_get_temp_dir(), 'key_');
                file_put_contents($tempFile, $keyContent);
                
                $testOutput = shell_exec("openssl rsa -in " . escapeshellarg($tempFile) . " -check -noout 2>&1");
                if (strpos($testOutput, 'RSA key ok') === false && strpos($testOutput, 'unable to load') !== false) {
                    unlink($tempFile);
                    return null;
                }
                
                unlink($tempFile);
                
                // Get key size using OpenSSL
                $keyDetails = shell_exec("openssl rsa -in " . escapeshellarg($tempFile) . " -text -noout 2>/dev/null | grep 'bits'");
                preg_match('/(\d+)\s+bit/', $keyDetails, $matches);
                $keySize = isset($matches[1]) ? (int)$matches[1] : 2048;
                
                return [
                    'type' => 'RSA Private Key',
                    'key_size' => $keySize,
                    'is_encrypted' => !empty($password),
                    'modulus_hash_md5' => $this->extractModulusFromKey($keyContent, $password),
                    'fingerprint_sha256' => $this->getKeyFingerprint($keyContent, $password),
                ];
            }
            
            return [
                'type' => 'RSA Private Key',
                'key_size' => (method_exists($key, 'getLength') ? $key->getLength() : (method_exists($key, 'getSize') ? $key->getSize() : 0)),
                'is_encrypted' => !empty($password),
                'modulus_hash_md5' => $this->extractModulusFromKey($keyContent, $password),
                'fingerprint_sha256' => $this->getKeyFingerprint($keyContent, $password),
            ];
        } catch (\Exception $e) {
            Log::error('Private key parsing error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Parse Public Key
     */
    private function parsePublicKey($pubContent)
    {
        try {
            // Clean the public key content
            $pubContent = trim($pubContent);
            
            if (empty($pubContent)) {
                Log::error('Public key content is empty');
                return null;
            }
            
            // Ensure proper PEM format for public key
            if (strpos($pubContent, '-----BEGIN PUBLIC KEY-----') === false && 
                strpos($pubContent, '-----BEGIN RSA PUBLIC KEY-----') === false) {
                $pubContent = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(trim($pubContent), 64, "\n") . "-----END PUBLIC KEY-----\n";
            }
            
            $key = RSA::load($pubContent);
            if (!$key) {
                return null;
            }
            
            return [
                'type' => 'RSA Public Key',
                'key_size' => $key->getLength(),
                'modulus_hash_md5' => $this->extractModulusFromPublicKey($pubContent),
                'fingerprint_sha256' => $this->getPublicKeyFingerprint($pubContent),
            ];
        } catch (\Exception $e) {
            Log::error('Public key parsing error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Extract modulus hash from certificate
     */
    private function extractModulusFromCert($certContent)
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'cert_');
        file_put_contents($tempFile, $certContent);
        
        $output = shell_exec("openssl x509 -noout -modulus -in " . escapeshellarg($tempFile) . " 2>/dev/null | openssl md5");
        unlink($tempFile);
        
        return trim($output);
    }

    /**
     * Extract modulus hash from private key
     */
    private function extractModulusFromKey($keyContent, $password = null)
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'key_');
        file_put_contents($tempFile, $keyContent);
        
        if ($password) {
            $output = shell_exec("openssl rsa -noout -modulus -in " . escapeshellarg($tempFile) . " -passin pass:" . escapeshellarg($password) . " 2>/dev/null | openssl md5");
        } else {
            $output = shell_exec("openssl rsa -noout -modulus -in " . escapeshellarg($tempFile) . " 2>/dev/null | openssl md5");
        }
        unlink($tempFile);
        
        return trim($output);
    }

    /**
     * Extract modulus hash from public key
     */
    private function extractModulusFromPublicKey($pubContent)
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'pub_');
        file_put_contents($tempFile, $pubContent);
        
        $output = shell_exec("openssl rsa -pubin -noout -modulus -in " . escapeshellarg($tempFile) . " 2>/dev/null | openssl md5");
        unlink($tempFile);
        
        return trim($output);
    }

    /**
     * Get fingerprint of X.509 certificate
     */
    private function getCertFingerprint($content, $algo = 'sha256')
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'cert_');
        file_put_contents($tempFile, $content);
        
        $output = shell_exec("openssl x509 -noout -fingerprint -" . $algo . " -in " . escapeshellarg($tempFile) . " 2>/dev/null");
        unlink($tempFile);
        
        if ($output) {
            preg_match('/Fingerprint=(.+)/', $output, $matches);
            return $matches[1] ?? 'N/A';
        }
        return 'N/A';
    }

    /**
     * Get fingerprint of private key
     */
    private function getKeyFingerprint($keyContent, $password = null)
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'key_');
        file_put_contents($tempFile, $keyContent);
        
        if ($password) {
            $output = shell_exec("openssl rsa -in " . escapeshellarg($tempFile) . " -passin pass:" . escapeshellarg($password) . " -outform DER 2>/dev/null | openssl sha256");
        } else {
            $output = shell_exec("openssl rsa -in " . escapeshellarg($tempFile) . " -outform DER 2>/dev/null | openssl sha256");
        }
        unlink($tempFile);
        
        if ($output) {
            preg_match('/([a-f0-9]{64})/', $output, $matches);
            return $matches[1] ?? 'N/A';
        }
        return 'N/A';
    }

    /**
     * Get fingerprint of public key
     */
    private function getPublicKeyFingerprint($pubContent)
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'pub_');
        file_put_contents($tempFile, $pubContent);
        
        $output = shell_exec("openssl rsa -pubin -in " . escapeshellarg($tempFile) . " -outform DER 2>/dev/null | openssl sha256");
        unlink($tempFile);
        
        if ($output) {
            preg_match('/([a-f0-9]{64})/', $output, $matches);
            return $matches[1] ?? 'N/A';
        }
        return 'N/A';
    }

    /**
     * Match Public Key and Private Key
     */
    public function matchPublicKeyPrivateKey(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'public_key' => 'required_without:pub_file|string',
            'pub_file' => 'required_without:public_key|file|mimes:pub,pem,txt|max:512',
            'private_key' => 'required_without:key_file|string',
            'key_file' => 'required_without:private_key|file|mimes:key,pem|max:512',
            'key_password' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $pubContent = $request->input('public_key');
            if ($request->hasFile('pub_file')) {
                $pubContent = file_get_contents($request->file('pub_file')->getPathname());
            }

            $keyContent = $request->input('private_key');
            if ($request->hasFile('key_file')) {
                $keyContent = file_get_contents($request->file('key_file')->getPathname());
            }

            $keyPassword = $request->input('key_password');

            $pubInfo = $this->parsePublicKey($pubContent);
            if (!$pubInfo) {
                return response()->json(['success' => false, 'message' => 'Invalid public key format'], 400);
            }

            $keyInfo = $this->parsePrivateKey($keyContent, $keyPassword);
            if (!$keyInfo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid private key format or wrong password. Please ensure you provide a valid RSA private key.'
                ], 400);
            }

            $pubModulus = $this->extractModulusFromPublicKey($pubContent);
            $keyModulus = $this->extractModulusFromKey($keyContent, $keyPassword);

            $match = ($pubModulus === $keyModulus);

            return response()->json([
                'success' => true,
                'match' => $match,
                'public_key' => $pubInfo,
                'private_key' => $keyInfo,
                'pub_modulus_hash' => $pubModulus,
                'key_modulus_hash' => $keyModulus,
                'message' => $match ? '✅ Public Key and Private Key MATCH!' : '❌ Public Key and Private Key DO NOT MATCH!'
            ]);
        } catch (\Exception $e) {
            Log::error('PublicKey-PrivateKey Match Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
      * Get command examples
      */
    public function getCommands()
    {

        return response()->json([
            'success' => true,
            'commands' => [
                [
                    'name' => 'Check Certificate and Private Key Match',
                    'command' => 'openssl x509 -noout -modulus -in certificate.crt | openssl md5',
                    'example' => 'openssl x509 -noout -modulus -in server.crt | openssl md5'
                ],
                [
                    'name' => 'Check Private Key Modulus',
                    'command' => 'openssl rsa -noout -modulus -in private.key | openssl md5',
                    'example' => 'openssl rsa -noout -modulus -in server.key | openssl md5'
                ],
                [
                    'name' => 'Check Certificate and Key Match (Full Command)',
                    'command' => 'if [ "$(openssl x509 -noout -modulus -in cert.crt | openssl md5)" = "$(openssl rsa -noout -modulus -in key.key | openssl md5)" ]; then echo "MATCH"; else echo "NO MATCH"; fi',
                    'example' => '# Compare certificate and private key'
                ],
                [
                    'name' => 'View Certificate Details',
                    'command' => 'openssl x509 -in certificate.crt -text -noout',
                    'example' => 'openssl x509 -in server.crt -text -noout'
                ],
                [
                    'name' => 'Decrypt Private Key',
                    'command' => 'openssl rsa -in encrypted.key -out decrypted.key',
                    'example' => 'openssl rsa -in server_encrypted.key -out server_decrypted.key'
                ],
                [
                    'name' => 'Check Public Key Modulus',
                    'command' => 'openssl rsa -pubin -noout -modulus -in public.key | openssl md5',
                    'example' => 'openssl rsa -pubin -noout -modulus -in public.key | openssl md5'
                ]
            ]
        ]);
    }

    /**
     * Decrypt Encrypted Private Key
     */
    public function decryptKey(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'private_key' => 'required_without:key_file|string',
            'key_file' => 'required_without:private_key|file|mimes:key,pem|max:512',
            'key_password' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $keyContent = $request->input('private_key');
            if ($request->hasFile('key_file')) {
                $keyContent = file_get_contents($request->file('key_file')->getPathname());
            }

            $keyPassword = $request->input('key_password');

            if (empty($keyContent)) {
                return response()->json(['success' => false, 'message' => 'Private key content is empty'], 400);
            }

            $keyContent = trim($keyContent);

            $tempFile = tempnam(sys_get_temp_dir(), 'decrypt_');
            file_put_contents($tempFile, $keyContent);

            $decryptedFile = $tempFile . '_decrypted.pem';

            $output = shell_exec("openssl rsa -in " . escapeshellarg($tempFile) . " -passin pass:" . escapeshellarg($keyPassword) . " -out " . escapeshellarg($decryptedFile) . " 2>&1");

            unlink($tempFile);

            if (!file_exists($decryptedFile) || filesize($decryptedFile) === 0) {
                if (file_exists($decryptedFile)) unlink($decryptedFile);
                $cleanOutput = trim($output);
                return response()->json([
                    'success' => false,
                    'message' => 'Decryption failed. Please check your password.',
                    'openssl_output' => $cleanOutput
                ], 400);
            }

            $decryptedContent = file_get_contents($decryptedFile);
            unlink($decryptedFile);

            return response()->json([
                'success' => true,
                'message' => '✅ Private key decrypted successfully!',
                'decrypted_key' => $decryptedContent
            ]);

        } catch (\Exception $e) {
            Log::error('Private key decryption error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Validate and parse CSR details
     */
    public function validateCSR(Request $request)
    {
        $request->validate([
            'csr' => 'required|string'
        ]);

        $csr = $request->input('csr');
        
        try {
            // Save CSR to temporary file
            $tempCsrFile = tempnam(sys_get_temp_dir(), 'csr_');
            file_put_contents($tempCsrFile, $csr);
            
            // Extract CSR details using openssl
            $cmd = "openssl req -text -noout -in " . escapeshellarg($tempCsrFile) . " 2>&1";
            $output = shell_exec($cmd);
            
            if (empty($output)) {
                unlink($tempCsrFile);
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to parse CSR. Invalid CSR format.'
                ]);
            }
            
            // Extract subject
            preg_match('/Subject:\s*(.+)/', $output, $subjectMatch);
            $subject = $subjectMatch[1] ?? 'N/A';
            
            // Extract public key algorithm
            preg_match('/Public Key Algorithm:\s*(.+)/', $output, $algoMatch);
            $publicKeyAlgorithm = $algoMatch[1] ?? 'N/A';
            
            // Extract key size
            preg_match('/RSA Public Key:\s*\((\d+)\s+bit\)/', $output, $keySizeMatch);
            $keySize = $keySizeMatch[1] ?? 'N/A';
            
            if ($keySize == 'N/A') {
                preg_match('/Public-Key:\s*\((\d+)\s+bit\)/', $output, $keySizeMatch2);
                $keySize = $keySizeMatch2[1] ?? 'N/A';
            }
            
            // Extract signature algorithm
            preg_match('/Signature Algorithm:\s*(.+)/', $output, $sigMatch);
            $signatureAlgorithm = $sigMatch[1] ?? 'N/A';
            
            // Extract version
            preg_match('/Version:\s*(\d+)/', $output, $versionMatch);
            $version = $versionMatch[1] ?? 'N/A';
            
            // Get MD5 fingerprint
            $md5Cmd = "openssl req -noout -modulus -in " . escapeshellarg($tempCsrFile) . " | openssl md5 2>&1";
            $md5Fingerprint = trim(shell_exec($md5Cmd));
            
            // Get SHA256 fingerprint
            $sha256Cmd = "openssl req -noout -modulus -in " . escapeshellarg($tempCsrFile) . " | openssl sha256 2>&1";
            $sha256Fingerprint = trim(shell_exec($sha256Cmd));
            
            // Extract SANs (Subject Alternative Names)
            preg_match_all('/DNS:([^,\s]+)/', $output, $sanMatches);
            $sanList = $sanMatches[1] ?? [];
            
            // Also check for DNS entries in the subjectAltName section
            if (empty($sanList)) {
                preg_match('/X509v3 Subject Alternative Name:\s+(.+)/', $output, $sanSection);
                if (!empty($sanSection[1])) {
                    preg_match_all('/DNS:([^,\s]+)/', $sanSection[1], $sanMatches2);
                    $sanList = $sanMatches2[1] ?? [];
                }
            }
            
            unlink($tempCsrFile);
            
            return response()->json([
                'success' => true,
                'message' => 'CSR validated successfully',
                'match' => true,
                'csr' => [
                    'subject' => $subject,
                    'public_key_algorithm' => $publicKeyAlgorithm,
                    'key_size' => $keySize,
                    'signature_algorithm' => $signatureAlgorithm,
                    'version' => $version,
                    'fingerprint_md5' => $md5Fingerprint,
                    'fingerprint_sha256' => $sha256Fingerprint,
                    'san_list' => $sanList
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error processing CSR: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Match CSR with Private Key
     */
    public function matchCSRWithKey(Request $request)
    {
        $request->validate([
            'csr' => 'required|string',
            'private_key' => 'required|string',
            'key_password' => 'nullable|string'
        ]);
        
        $csr = $request->input('csr');
        $privateKey = $request->input('private_key');
        $keyPassword = $request->input('key_password');
        
        try {
            // Save files to temp locations
            $tempCsrFile = tempnam(sys_get_temp_dir(), 'csr_');
            $tempKeyFile = tempnam(sys_get_temp_dir(), 'key_');
            
            file_put_contents($tempCsrFile, $csr);
            file_put_contents($tempKeyFile, $privateKey);
            
            // Get CSR modulus
            $csrModulusCmd = "openssl req -noout -modulus -in " . escapeshellarg($tempCsrFile) . " 2>&1";
            $csrModulus = trim(shell_exec($csrModulusCmd));
            $csrModulusHash = shell_exec("echo " . escapeshellarg($csrModulus) . " | openssl md5 2>&1");
            
            // Get private key modulus
            $keyCmd = $keyPassword 
                ? "openssl rsa -noout -modulus -in " . escapeshellarg($tempKeyFile) . " -passin pass:" . escapeshellarg($keyPassword) . " 2>&1"
                : "openssl rsa -noout -modulus -in " . escapeshellarg($tempKeyFile) . " 2>&1";
            
            $keyModulus = trim(shell_exec($keyCmd));
            
            if (strpos($keyModulus, 'unable to load Private Key') !== false || 
                strpos($keyModulus, 'bad decrypt') !== false ||
                strpos($keyModulus, 'No such file') !== false) {
                unlink($tempCsrFile);
                unlink($tempKeyFile);
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid private key or wrong password'
                ]);
            }
            
            $keyModulusHash = shell_exec("echo " . escapeshellarg($keyModulus) . " | openssl md5 2>&1");
            
            $match = (trim($csrModulusHash) === trim($keyModulusHash));
            
            // Also parse CSR details for display
            $csrDetailsCmd = "openssl req -text -noout -in " . escapeshellarg($tempCsrFile) . " 2>&1";
            $csrOutput = shell_exec($csrDetailsCmd);
            
            preg_match('/Subject:\s*(.+)/', $csrOutput, $subjectMatch);
            $subject = $subjectMatch[1] ?? 'N/A';
            
            preg_match('/Public Key Algorithm:\s*(.+)/', $csrOutput, $algoMatch);
            $publicKeyAlgorithm = $algoMatch[1] ?? 'N/A';
            
            preg_match('/RSA Public Key:\s*\((\d+)\s+bit\)/', $csrOutput, $keySizeMatch);
            $keySize = $keySizeMatch[1] ?? 'N/A';
            
            if ($keySize == 'N/A') {
                preg_match('/Public-Key:\s*\((\d+)\s+bit\)/', $csrOutput, $keySizeMatch2);
                $keySize = $keySizeMatch2[1] ?? 'N/A';
            }
            
            preg_match('/Signature Algorithm:\s*(.+)/', $csrOutput, $sigMatch);
            $signatureAlgorithm = $sigMatch[1] ?? 'N/A';
            
            // Get MD5 fingerprint of CSR modulus
            $md5Fingerprint = trim($csrModulusHash);
            $sha256Fingerprint = shell_exec("echo " . escapeshellarg($csrModulus) . " | openssl sha256 2>&1");
            
            unlink($tempCsrFile);
            unlink($tempKeyFile);
            
            return response()->json([
                'success' => true,
                'match' => $match,
                'message' => $match ? 'CSR matches the private key!' : 'CSR does NOT match the private key!',
                'csr' => [
                    'subject' => $subject,
                    'public_key_algorithm' => $publicKeyAlgorithm,
                    'key_size' => $keySize,
                    'signature_algorithm' => $signatureAlgorithm,
                    'fingerprint_md5' => trim($md5Fingerprint),
                    'fingerprint_sha256' => trim($sha256Fingerprint)
                ],
                'cert_modulus_hash' => trim($csrModulusHash),
                'key_modulus_hash' => trim($keyModulusHash)
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error matching CSR with key: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Convert certificate/key formats
     */
    public function convertFormat(Request $request)
    {
        $request->validate([
            'input_type' => 'required|in:certificate,private_key,public_key',
            'input_format' => 'required|in:pem,der,pkcs7,pkcs12',
            'output_format' => 'required|in:pem,der,pkcs7,pkcs12',
            'content' => 'required|string',
            'password' => 'nullable|string',
            'server_format' => 'nullable|string'
        ]);
        
        $inputType = $request->input('input_type');
        $inputFormat = $request->input('input_format');
        $outputFormat = $request->input('output_format');
        $content = $request->input('content');
        $password = $request->input('password');
        $serverFormat = $request->input('server_format');
        
        try {
            // Create temp files for conversion
            $tempInputFile = tempnam(sys_get_temp_dir(), 'convert_in_');
            $tempOutputFile = tempnam(sys_get_temp_dir(), 'convert_out_');
            
            file_put_contents($tempInputFile, $content);
            
            $convertedContent = '';
            $cmd = '';
            
            // Determine the appropriate OpenSSL command based on conversion type
            if ($inputType === 'certificate') {
                $convertedContent = $this->convertCertificate($inputFormat, $outputFormat, $tempInputFile, $tempOutputFile, $password);
            } elseif ($inputType === 'private_key') {
                $convertedContent = $this->convertPrivateKey($inputFormat, $outputFormat, $tempInputFile, $tempOutputFile, $password);
            } elseif ($inputType === 'public_key') {
                $convertedContent = $this->convertPublicKey($inputFormat, $outputFormat, $tempInputFile, $tempOutputFile);
            }
            
            // Apply server-specific formatting if requested
            if ($serverFormat && $serverFormat !== '') {
                $convertedContent = $this->applyServerFormatting($convertedContent, $serverFormat, $inputType);
            }
            
            unlink($tempInputFile);
            if (file_exists($tempOutputFile)) unlink($tempOutputFile);
            
            return response()->json([
                'success' => true,
                'message' => 'Conversion successful',
                'converted_content' => $convertedContent
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Conversion failed: ' . $e->getMessage()
            ]);
        }
    }
    
    private function convertCertificate($inputFormat, $outputFormat, $inputFile, $outputFile, $password)
    {
        $convertedContent = '';
        
        if ($inputFormat === 'pem' && $outputFormat === 'der') {
            $cmd = "openssl x509 -in " . escapeshellarg($inputFile) . " -outform DER -out " . escapeshellarg($outputFile) . " 2>&1";
            shell_exec($cmd);
            if (file_exists($outputFile)) {
                $convertedContent = "-----BEGIN CERTIFICATE-----\n" . chunk_split(base64_encode(file_get_contents($outputFile)), 64, "\n") . "-----END CERTIFICATE-----\n";
            }
        } 
        elseif ($inputFormat === 'der' && $outputFormat === 'pem') {
            $cmd = "openssl x509 -inform DER -in " . escapeshellarg($inputFile) . " -outform PEM -out " . escapeshellarg($outputFile) . " 2>&1";
            shell_exec($cmd);
            if (file_exists($outputFile)) {
                $convertedContent = file_get_contents($outputFile);
            }
        }
        elseif ($inputFormat === 'pem' && $outputFormat === 'pkcs7') {
            $cmd = "openssl crl2pkcs7 -nocrl -certfile " . escapeshellarg($inputFile) . " -out " . escapeshellarg($outputFile) . " 2>&1";
            shell_exec($cmd);
            if (file_exists($outputFile)) {
                $convertedContent = file_get_contents($outputFile);
            }
        }
        elseif ($inputFormat === 'pkcs7' && $outputFormat === 'pem') {
            $cmd = "openssl pkcs7 -in " . escapeshellarg($inputFile) . " -print_certs -out " . escapeshellarg($outputFile) . " 2>&1";
            shell_exec($cmd);
            if (file_exists($outputFile)) {
                $convertedContent = file_get_contents($outputFile);
            }
        }
        else {
            // Default: just return original content
            $convertedContent = file_get_contents($inputFile);
        }
        
        return $convertedContent ?: "Conversion not supported for this format combination";
    }
    
    private function convertPrivateKey($inputFormat, $outputFormat, $inputFile, $outputFile, $password)
    {
        $convertedContent = '';
        $passin = $password ? "-passin pass:" . escapeshellarg($password) : "";
        $passout = $outputFormat === 'pkcs12' ? "-passout pass:" . ($password ?: "") : "";
        
        if ($inputFormat === 'pem' && $outputFormat === 'der') {
            $cmd = "openssl rsa -in " . escapeshellarg($inputFile) . " $passin -outform DER -out " . escapeshellarg($outputFile) . " 2>&1";
            shell_exec($cmd);
            if (file_exists($outputFile)) {
                $convertedContent = "-----BEGIN PRIVATE KEY-----\n" . chunk_split(base64_encode(file_get_contents($outputFile)), 64, "\n") . "-----END PRIVATE KEY-----\n";
            }
        }
        elseif ($inputFormat === 'der' && $outputFormat === 'pem') {
            $cmd = "openssl rsa -inform DER -in " . escapeshellarg($inputFile) . " $passin -outform PEM -out " . escapeshellarg($outputFile) . " 2>&1";
            shell_exec($cmd);
            if (file_exists($outputFile)) {
                $convertedContent = file_get_contents($outputFile);
            }
        }
        else {
            $convertedContent = file_get_contents($inputFile);
        }
        
        return $convertedContent ?: "Conversion not supported for this format combination";
    }
    
    private function convertPublicKey($inputFormat, $outputFormat, $inputFile, $outputFile)
    {
        $convertedContent = file_get_contents($inputFile);
        return $convertedContent;
    }
    
    private function applyServerFormatting($content, $serverFormat, $inputType)
    {
        if ($serverFormat === 'apache' && $inputType === 'certificate') {
            return "# Apache Configuration\n# Add to your SSL VirtualHost:\n# SSLCertificateFile /path/to/this.crt\n\n" . $content;
        } elseif ($serverFormat === 'nginx') {
            return "# Nginx Configuration\n# Add to your server block:\n# ssl_certificate /path/to/this.crt;\n\n" . $content;
        } elseif ($serverFormat === 'iis') {
            return "# IIS Format\n# Import this certificate using IIS Manager\n# File format: .pfx or .p7b\n\n" . $content;
        }
        
        return $content;
    }


    /**
     * Match CSR, Private Key, and Certificate together
     */
    public function matchCSRKeyCert(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'csr' => 'required_without:csr_file|string',
            'csr_file' => 'required_without:csr|file|mimes:csr,pem|max:512',
            'private_key' => 'required_without:key_file|string',
            'key_file' => 'required_without:private_key|file|mimes:key,pem|max:512',
            'key_password' => 'nullable|string',
            'certificate' => 'required_without:cert_file|string',
            'cert_file' => 'required_without:certificate|file|mimes:cer,crt,pem|max:512'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            // Get CSR content
            $csrContent = $request->input('csr');
            if ($request->hasFile('csr_file')) {
                $csrContent = file_get_contents($request->file('csr_file')->getPathname());
            }

            // Get private key content
            $keyContent = $request->input('private_key');
            if ($request->hasFile('key_file')) {
                $keyContent = file_get_contents($request->file('key_file')->getPathname());
            }

            // Get certificate content
            $certContent = $request->input('certificate');
            if ($request->hasFile('cert_file')) {
                $certContent = file_get_contents($request->file('cert_file')->getPathname());
            }

            $keyPassword = $request->input('key_password');

            // Parse CSR
            $csrInfo = $this->parseCSR($csrContent);
            if (!$csrInfo) {
                return response()->json(['success' => false, 'message' => 'Invalid CSR format'], 400);
            }

            // Parse private key
            $keyInfo = $this->parsePrivateKey($keyContent, $keyPassword);
            if (!$keyInfo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid private key format or wrong password'
                ], 400);
            }

            // Parse certificate
            $certInfo = $this->parseCertificate($certContent);
            if (!$certInfo) {
                return response()->json(['success' => false, 'message' => 'Invalid certificate format'], 400);
            }

            // Extract modulus hashes from all three
            $csrModulus = $this->extractModulusFromCSR($csrContent);
            $keyModulus = $this->extractModulusFromKey($keyContent, $keyPassword);
            $certModulus = $this->extractModulusFromCert($certContent);

            // Compare all three
            $csrKeyMatch = ($csrModulus === $keyModulus);
            $csrCertMatch = ($csrModulus === $certModulus);
            $keyCertMatch = ($keyModulus === $certModulus);
            
            // Overall match (all three match)
            $allMatch = ($csrKeyMatch && $csrCertMatch && $keyCertMatch);

            // Build match details
            $matchDetails = [
                'csr_key' => $csrKeyMatch,
                'csr_cert' => $csrCertMatch,
                'key_cert' => $keyCertMatch,
                'all_match' => $allMatch
            ];

            // Build message
            if ($allMatch) {
                $message = '✅ CSR, Private Key, and Certificate ALL MATCH!';
            } else {
                $message = '❌ Mismatch detected! ';
                $mismatches = [];
                if (!$csrKeyMatch) $mismatches[] = 'CSR ↔ Private Key';
                if (!$csrCertMatch) $mismatches[] = 'CSR ↔ Certificate';
                if (!$keyCertMatch) $mismatches[] = 'Private Key ↔ Certificate';
                $message .= 'Mismatch between: ' . implode(', ', $mismatches);
            }

            return response()->json([
                'success' => true,
                'match' => $allMatch,
                'match_details' => $matchDetails,
                'csr' => $csrInfo,
                'private_key' => $keyInfo,
                'certificate' => $certInfo,
                'csr_modulus_hash' => $csrModulus,
                'key_modulus_hash' => $keyModulus,
                'cert_modulus_hash' => $certModulus,
                'message' => $message
            ]);

        } catch (\Exception $e) {
            Log::error('CSR-Key-Cert Match Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Extract modulus hash from CSR
     */
    private function extractModulusFromCSR($csrContent)
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'csr_');
        file_put_contents($tempFile, $csrContent);
        
        $output = shell_exec("openssl req -noout -modulus -in " . escapeshellarg($tempFile) . " 2>/dev/null | openssl md5");
        unlink($tempFile);
        
        return trim($output);
    }

    /**
     * Parse CSR details (reuse existing method or create new one)
     */
    private function parseCSR($csrContent)
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'csr_');
        file_put_contents($tempFile, $csrContent);
        
        // Get CSR details using openssl
        $output = shell_exec("openssl req -text -noout -in " . escapeshellarg($tempFile) . " 2>/dev/null");
        
        if (empty($output)) {
            unlink($tempFile);
            return null;
        }
        
        // Extract subject
        preg_match('/Subject:\s*(.+)/', $output, $subjectMatch);
        $subject = $subjectMatch[1] ?? 'N/A';
        
        // Extract public key algorithm
        preg_match('/Public Key Algorithm:\s*(.+)/', $output, $algoMatch);
        $publicKeyAlgorithm = $algoMatch[1] ?? 'N/A';
        
        // Extract key size
        preg_match('/RSA Public Key:\s*\((\d+)\s+bit\)/', $output, $keySizeMatch);
        $keySize = $keySizeMatch[1] ?? 'N/A';
        
        if ($keySize == 'N/A') {
            preg_match('/Public-Key:\s*\((\d+)\s+bit\)/', $output, $keySizeMatch2);
            $keySize = $keySizeMatch2[1] ?? 'N/A';
        }
        
        // Extract signature algorithm
        preg_match('/Signature Algorithm:\s*(.+)/', $output, $sigMatch);
        $signatureAlgorithm = $sigMatch[1] ?? 'N/A';
        
        // Extract version
        preg_match('/Version:\s*(\d+)/', $output, $versionMatch);
        $version = $versionMatch[1] ?? 'N/A';
        
        // Get MD5 fingerprint
        $md5Cmd = "openssl req -noout -modulus -in " . escapeshellarg($tempFile) . " | openssl md5 2>&1";
        $md5Fingerprint = trim(shell_exec($md5Cmd));
        
        // Get SHA256 fingerprint
        $sha256Cmd = "openssl req -noout -modulus -in " . escapeshellarg($tempFile) . " | openssl sha256 2>&1";
        $sha256Fingerprint = trim(shell_exec($sha256Cmd));
        
        // Extract SANs
        preg_match_all('/DNS:([^,\s]+)/', $output, $sanMatches);
        $sanList = $sanMatches[1] ?? [];
        
        if (empty($sanList)) {
            preg_match('/X509v3 Subject Alternative Name:\s+(.+)/', $output, $sanSection);
            if (!empty($sanSection[1])) {
                preg_match_all('/DNS:([^,\s]+)/', $sanSection[1], $sanMatches2);
                $sanList = $sanMatches2[1] ?? [];
            }
        }
        
        unlink($tempFile);
        
        return [
            'type' => 'Certificate Signing Request (CSR)',
            'subject' => $subject,
            'public_key_algorithm' => $publicKeyAlgorithm,
            'key_size' => $keySize,
            'signature_algorithm' => $signatureAlgorithm,
            'version' => $version,
            'fingerprint_md5' => $md5Fingerprint,
            'fingerprint_sha256' => $sha256Fingerprint,
            'san_list' => $sanList
        ];
    }
}