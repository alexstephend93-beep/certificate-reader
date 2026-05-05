<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChainValidatorController extends Controller
{
    public function index(Request $request)
    {
        // Clear sessions on fresh load
        if (!session()->has('success') && !session()->has('error') && !$request->has('action') && !$request->has('chain_bundle') && !$request->hasFile('chain_file') && $request->method() === 'GET') {
            session()->forget(['chain_data', 'original_bundle', 'chain_stats']);
        }
        
        return view('chain-validator.index');
    }

    public function parse(Request $request)
    {
        $bundleContent = '';

        if ($request->hasFile('chain_file') && $request->file('chain_file')->isValid()) {
            // Validate file size (max 5MB)
            $maxSize = 5 * 1024 * 1024;
            if ($request->file('chain_file')->getSize() > $maxSize) {
                return redirect()->back()->with('error', 'File too large. Maximum size is 5MB.');
            }
            $bundleContent = trim(file_get_contents($request->file('chain_file')->getRealPath()));
        } elseif ($request->filled('chain_bundle')) {
            $bundleContent = trim($request->input('chain_bundle'));
        }

        if (empty($bundleContent)) {
            return redirect()->back()->with('error', 'Please paste a certificate bundle or upload a file.');
        }

        // Limit input size to prevent DoS
        if (strlen($bundleContent) > 10 * 1024 * 1024) {
            return redirect()->back()->with('error', 'Input too large. Maximum size is 10MB.');
        }

        // Split multiple PEM blocks - more robust pattern
        preg_match_all('/-----BEGIN CERTIFICATE-----[\r\n]+(?:[^-]+|-[^-]+)*-----END CERTIFICATE-----/s', $bundleContent, $matches);
        $certs = $matches[0];

        if (empty($certs)) {
            return redirect()->back()->with('error', 'No valid PEM certificates found in the input. Ensure it contains BEGIN/END blocks.');
        }

        $parsedCerts = [];
        $errors = [];
        $warnings = [];

        foreach ($certs as $index => $certPem) {
            $parsed = openssl_x509_parse($certPem);
            if ($parsed === false) {
                $errors[] = "Certificate #" . ($index + 1) . " is invalid or corrupted.";
                continue;
            }
            
            // Extract OCSP and CRL with improved regex
            $extensions = $parsed['extensions'] ?? [];
            $ocsp = '';
            $crl = '';
            
            if (isset($extensions['authorityInfoAccess'])) {
                // Match multiple OCSP URIs, handle whitespace
                if (preg_match_all('/OCSP\s*-?\s*URI:\s*([^\s,]+)/i', $extensions['authorityInfoAccess'], $matches)) {
                    $ocsp = implode(', ', array_map('trim', $matches[1]));
                }
            }
            if (isset($extensions['crlDistributionPoints'])) {
                // Match CRL distribution points with better URI extraction
                if (preg_match_all('/URI:\s*([^\s,]+)/i', $extensions['crlDistributionPoints'], $matches)) {
                    $crl = implode(', ', array_map('trim', $matches[1]));
                }
            }
            
            // Build subject name with fallbacks
            $subject = $this->buildSubjectName($parsed['subject'] ?? []);
            $issuer = $this->buildSubjectName($parsed['issuer'] ?? []);
            
            // Determine certificate type
            $isCA = false;
            if (isset($extensions['basicConstraints'])) {
                $isCA = (stripos($extensions['basicConstraints'], 'CA:TRUE') !== false);
            }
            
            $isSelfSigned = ($parsed['subject'] === $parsed['issuer']);
            
            // Determine certificate category
            $certType = $this->determineCertType($isCA, $isSelfSigned, $parsed);
            
            $subjectFullStr = $this->formatSubjectArray($parsed['subject'] ?? []);
            $issuerFullStr = $this->formatSubjectArray($parsed['issuer'] ?? []);
            
            // Extract additional useful info
            $signatureAlgorithm = $parsed['signatureTypeSN'] ?? $parsed['signatureType'] ?? 'Unknown';
            $publicKeySize = $this->extractPublicKeySize($parsed);
            $keyUsage = $extensions['keyUsage'] ?? null;
            $extendedKeyUsage = $extensions['extendedKeyUsage'] ?? null;
            $subjectAltName = $extensions['subjectAltName'] ?? null;
            $basicConstraints = $extensions['basicConstraints'] ?? null;
            $pathLength = $this->extractPathLength($basicConstraints);
            $authorityKeyIdentifier = $extensions['authorityKeyIdentifier'] ?? null;
            $subjectKeyIdentifier = $extensions['subjectKeyIdentifier'] ?? null;
            $certificatePolicies = $extensions['certificatePolicies'] ?? null;
            $crlDistributionPoints = $extensions['crlDistributionPoints'] ?? null;
            $authorityInfoAccess = $extensions['authorityInfoAccess'] ?? null;
            
            // Compute fingerprints
            $fingerprintSha1 = function_exists('openssl_x509_fingerprint') ? openssl_x509_fingerprint($certPem, 'sha1') : null;
            $fingerprintSha256 = function_exists('openssl_x509_fingerprint') ? openssl_x509_fingerprint($certPem, 'sha256') : null;
            
            // Check signature validity (will be computed after ordering)
            $signatureValid = null;
            
            $parsedCerts[] = [
                'id' => uniqid('cert_', true),
                'pem' => $certPem,
                'subject' => $subject,
                'subject_full' => $subjectFullStr,
                'issuer' => $issuer,
                'issuer_full' => $issuerFullStr,
                'is_self_signed' => $isSelfSigned,
                'is_ca' => $isCA,
                'cert_type' => $certType, // 'root', 'intermediate', 'leaf'
                'valid_from' => $this->formatDate($parsed['validFrom_time_t'] ?? null),
                'valid_to' => $this->formatDate($parsed['validTo_time_t'] ?? null),
                'is_expired' => ($parsed['validTo_time_t'] ?? 0) < time(),
                'ocsp' => $ocsp,
                'crl' => $crl,
                'signature_algorithm' => $signatureAlgorithm,
                'public_key_size' => $publicKeySize,
                'key_usage' => $keyUsage,
                'extended_key_usage' => $extendedKeyUsage,
                'subject_alt_name' => $subjectAltName,
                'basic_constraints' => $basicConstraints,
                'path_length' => $pathLength,
                'authority_key_identifier' => $authorityKeyIdentifier,
                'subject_key_identifier' => $subjectKeyIdentifier,
                'certificate_policies' => $certificatePolicies,
                'crl_distribution_points' => $crlDistributionPoints,
                'authority_info_access' => $authorityInfoAccess,
                'fingerprint_sha1' => $fingerprintSha1,
                'fingerprint_sha256' => $fingerprintSha256,
                'serial_number' => $parsed['serialNumberHex'] ?? $parsed['serialNumber'] ?? null,
                'version' => ($parsed['version'] ?? 0) + 1,
                'signature_valid' => $signatureValid,
            ];
        }

        if (empty($parsedCerts)) {
            return redirect()->back()->with('error', 'All certificates were invalid. ' . implode(' ', $errors));
        }

        // Order the chain properly
        $orderedCerts = $this->orderCertificateChain($parsedCerts);
        
        // Verify signatures within the chain
        $orderedCerts = $this->verifyChainSignatures($orderedCerts);
        
        // Compute statistics
        $stats = [
            'total' => count($orderedCerts),
            'root_ca' => 0,
            'intermediate_ca' => 0,
            'leaf' => 0,
            'expired' => 0,
            'valid' => 0,
            'self_signed' => 0,
        ];
        
        foreach ($orderedCerts as $cert) {
            if ($cert['cert_type'] === 'root') $stats['root_ca']++;
            elseif ($cert['cert_type'] === 'intermediate') $stats['intermediate_ca']++;
            else $stats['leaf']++;
            
            if ($cert['is_expired']) $stats['expired']++;
            else $stats['valid']++;
            
            if ($cert['is_self_signed']) $stats['self_signed']++;
        }

        session([
            'chain_data' => $orderedCerts,
            'original_bundle' => implode("\n\n", array_column($orderedCerts, 'pem')),
            'chain_stats' => $stats,
            'parse_warnings' => $warnings,
            'parse_errors' => $errors,
        ]);

        return redirect('/chain-validator')->with('success', true);
    }

    private function buildSubjectName(array $subject): string
    {
        if (empty($subject)) return 'Unknown';
        
        // Try CN first, then O, then first available field
        if (isset($subject['CN'])) return $subject['CN'];
        if (isset($subject['O'])) return $subject['O'];
        if (isset($subject['OU'])) return $subject['OU'];
        if (isset($subject['C'])) return $subject['C'];
        
        // Return first available
        foreach ($subject as $key => $value) {
            if (is_string($value) && !empty($value)) {
                return $value;
            }
        }
        
        return 'Unknown';
    }

    private function extractPublicKeySize(array $parsed): ?string
    {
        // Try to get key bits from parsed data
        if (isset($parsed['bits'])) {
            return $parsed['bits'] . ' bits';
        }
        
        // Sometimes stored in extensions
        if (isset($parsed['extensions']['subjectKeyIdentifier'])) {
            // Not directly the size, but we could attempt to extract via openssl
            return null;
        }
        
        return null;
    }

    private function formatDate(?int $timestamp): string
    {
        if (!$timestamp) return 'N/A';
        return date('Y-m-d H:i:s T', $timestamp);
    }

    private function formatSubjectArray(array $subject): string
    {
        if (empty($subject)) return 'N/A';
        
        $parts = [];
        $priority = ['CN', 'O', 'OU', 'C', 'ST', 'L'];
        
        foreach ($priority as $key) {
            if (isset($subject[$key])) {
                $parts[] = "$key=" . $subject[$key];
            }
        }
        
        // Add any remaining fields
        foreach ($subject as $key => $value) {
            if (!in_array($key, $priority)) {
                $parts[] = "$key=" . (is_array($value) ? implode(', ', $value) : $value);
            }
        }
        
        return implode(', ', $parts);
    }

    private function determineCertType(bool $isCA, bool $isSelfSigned, array $parsed): string
    {
        if ($isCA && $isSelfSigned) {
            return 'root';
        } elseif ($isCA && !$isSelfSigned) {
            return 'intermediate';
        } else {
            return 'leaf';
        }
    }

    private function getPublicKeySize(array $parsed): ?string
    {
        if (!isset($parsed['extensions']['subjectKeyIdentifier'])) {
            // Try to extract from key details
            return isset($parsed['bits']) ? $parsed['bits'] . ' bits' : null;
        }
        // For RSA keys, the key identifier length roughly correlates to key size
        // Better to use openssl_pkey_get_details if we had the key
        return null;
    }

    private function extractPathLength(?string $basicConstraints): ?int
    {
        if (!$basicConstraints) return null;
        
        if (preg_match('/pathLength\s*:\s*(\d+)/i', $basicConstraints, $m)) {
            return (int)$m[1];
        }
        if (stripos($basicConstraints, 'pathLenConstraint:0') !== false) {
            return 0;
        }
        return null;
    }

    private function orderCertificateChain(array $certs): array
    {
        if (count($certs) <= 1) return array_values($certs);
        
        // Build subject->issuer mapping
        $bySubject = [];
        foreach ($certs as $cert) {
            $bySubject[$cert['subject']] = $cert;
        }
        
        // Build graph: each cert points to its issuer (if present in chain)
        $ordered = [];
        $usedIds = [];
        
        // Find leaves: certs whose issuer is NOT in the chain (external root) 
        // OR certs that are not used as issuer by anyone else within this chain
        $isIssuerFor = [];
        foreach ($certs as $cert) {
            if (isset($bySubject[$cert['issuer']])) {
                $issuerCert = $bySubject[$cert['issuer']];
                $isIssuerFor[$issuerCert['id']] = true;
            }
        }
        
        // Start with leaves (certs that are not issuers for anyone)
        $current = null;
        foreach ($certs as $cert) {
            if (!isset($isIssuerFor[$cert['id']])) {
                $current = $cert;
                break;
            }
        }
        
        // If no clear leaf (all are CAs or circular), use heuristics
        if (!$current) {
            // Find a leaf (non-CA) if any
            foreach ($certs as $cert) {
                if ($cert['cert_type'] === 'leaf') {
                    $current = $cert;
                    break;
                }
            }
            // Still nothing? Just take first
            if (!$current) {
                $current = $certs[0];
            }
        }
        
        // Build the chain
        while ($current && !isset($usedIds[$current['id']])) {
            $ordered[] = $current;
            $usedIds[$current['id']] = true;
            
            // Find next: cert where issuer == current subject
            $next = null;
            foreach ($certs as $cert) {
                if (!isset($usedIds[$cert['id']]) && $cert['issuer'] === $current['subject']) {
                    $next = $cert;
                    break;
                }
            }
            $current = $next;
        }
        
        // Append any remaining certs (orphaned, cross-signed, etc.)
        foreach ($certs as $cert) {
            if (!isset($usedIds[$cert['id']])) {
                $ordered[] = $cert;
            }
        }
        
        return $ordered;
    }

    private function verifyChainSignatures(array $orderedCerts): array
    {
        if (count($orderedCerts) < 2) {
            // Single cert - mark as self-signed check only
            foreach ($orderedCerts as &$cert) {
                $cert['signature_valid'] = $cert['is_self_signed'] ? null : 'cannot_verify';
            }
            return $orderedCerts;
        }
        
        // For each cert (except root), verify signature using issuer's public key
        $bySubject = [];
        foreach ($orderedCerts as $cert) {
            $bySubject[$cert['subject']] = $cert;
        }
        
        foreach ($orderedCerts as &$cert) {
            if ($cert['is_self_signed']) {
                $cert['signature_valid'] = null; // Self-signed - trust decision external
                continue;
            }
            
            if (!isset($bySubject[$cert['issuer']])) {
                $cert['signature_valid'] = 'issuer_missing'; // Issuer not in chain
                continue;
            }
            
            $issuerCert = $bySubject[$cert['issuer']];
            
            // Verify signature using openssl
            $result = openssl_x509_verify($cert['pem'], $issuerCert['pem']);
            if ($result === 1) {
                $cert['signature_valid'] = true;
            } elseif ($result === 0) {
                $cert['signature_valid'] = false;
            } else {
                $cert['signature_valid'] = 'error'; // openssl error
            }
        }
        
        return $orderedCerts;
    }

    public function download($id)
    {
        if (!session()->has('chain_data')) {
            return redirect('/chain-validator')->with('error', 'Session expired. Please validate your chain again.');
        }

        $chainData = session('chain_data');
        $certToDownload = null;

        foreach ($chainData as $cert) {
            if ($cert['id'] == $id) {
                $certToDownload = $cert;
                break;
            }
        }

        if (!$certToDownload) {
            return redirect('/chain-validator')->with('error', 'Certificate not found in the parsed chain.');
        }

        // Determine filename based on certificate type
        switch ($certToDownload['cert_type']) {
            case 'root':
                $filename = 'root.txt';
                break;
            case 'intermediate':
                $filename = 'intermediate.txt';
                break;
            case 'leaf':
                $sanitizedCN = preg_replace('/[^a-zA-Z0-9.-]/', '_', $certToDownload['subject']);
                $filename = $sanitizedCN . '.txt';
                break;
            default:
                $sanitizedCN = preg_replace('/[^a-zA-Z0-9.-]/', '_', $certToDownload['subject']);
                $filename = $sanitizedCN . '.txt';
        }

        return response($certToDownload['pem'])
            ->header('Content-Type', 'application/x-pem-file')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0')
            ->header('Cache-Control', 'must-revalidate, post-check=0, pre-check=0');
    }

    /**
     * Download the original complete bundle as-is
     */
    public function downloadBundle()
    {
        if (!session()->has('original_bundle')) {
            return redirect('/chain-validator')->with('error', 'No bundle data available. Please validate a chain first.');
        }

        $bundle = session('original_bundle');
        $filename = 'certificate_chain_' . date('Y-m-d_H-i-s') . '.pem';

        return response($bundle)
            ->header('Content-Type', 'application/x-pem-file')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0')
            ->header('Cache-Control', 'must-revalidate, post-check=0, pre-check=0');
    }
}
