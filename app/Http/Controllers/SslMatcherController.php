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
}