<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class CertificateController extends Controller
{
    public function index(Request $request)
    {

        // Clear sessions on fresh load
        if (!session()->has('success') && !session()->has('error') && !$request->has('action') && !$request->has('certificate') && !$request->hasFile('cert_file') && $request->method() === 'GET') {
            session()->forget(['cert_data', 'original_cert', 'domain_name']);
        }

        return view('certificate.index');
    }

    public function parse(Request $request)
    {
        $certContent = '';

        if ($request->hasFile('cert_file') && $request->file('cert_file')->isValid()) {
            $certContent = trim(file_get_contents($request->file('cert_file')->getRealPath()));
        }
        elseif ($request->filled('certificate')) {
            $certContent = trim($request->input('certificate'));
        }

        if (empty($certContent)) {
            return redirect()->back()->with('error', 'Please paste a certificate or upload a certificate file.');
        }

        $parsed = openssl_x509_parse($certContent);

        if ($parsed === false) {
            return redirect()->back()->with('error', 'Invalid certificate format. Please check your certificate.');
        }

        $extensions = $parsed['extensions'] ?? [];
        $subjectData = $parsed['subject'] ?? [];
        $issuerData = $parsed['issuer'] ?? [];

        $isCA = false;
        $isSelfSigned = ($subjectData === $issuerData);

        if (isset($extensions['basicConstraints'])) {
            $isCA = (stripos($extensions['basicConstraints'], 'CA:TRUE') !== false);
        }

        $isCodeSigning = false;
        $isSSLServer = false;
        $isSSLClient = false;
        $isEmailProtection = false;

        if (isset($extensions['extendedKeyUsage'])) {
            $eku = $extensions['extendedKeyUsage'];
            $isCodeSigning = stripos($eku, 'codeSigning') !== false;
            $isSSLServer = stripos($eku, 'serverAuth') !== false;
            $isSSLClient = stripos($eku, 'clientAuth') !== false;
            $isEmailProtection = stripos($eku, 'emailProtection') !== false;
        }

        if ($isCA && $isSelfSigned) {
            $certType = 'Root CA';
            $certTypeDesc = 'Self-signed Certificate Authority — trust anchor';
            $certTypeIcon = '🏛️';
        }
        elseif ($isCA && !$isSelfSigned) {
            $certType = 'Intermediate CA';
            $certTypeDesc = 'Intermediate Certificate Authority — signed by another CA';
            $certTypeIcon = '🔗';
        }
        else {
            $certType = 'Leaf / End-Entity';
            if ($isCodeSigning)
                $certType .= ' (Code Signing)';
            elseif ($isSSLServer)
                $certType .= ' (SSL/TLS Server)';
            elseif ($isSSLClient)
                $certType .= ' (SSL/TLS Client)';
            elseif ($isEmailProtection)
                $certType .= ' (Email/SMIME)';
            $certTypeDesc = 'End-entity certificate — issued to a domain or service';
            $certTypeIcon = '🍃';
        }

        // Validation level
        $policies = $extensions['certificatePolicies'] ?? '';
        $hasOrg = !empty($subjectData['O']);
        $isEV = false;
        $isOV = false;
        $isDV = false;

        if (strpos($policies, '2.23.140.1.1') !== false) {
            $isEV = true;
        }
        elseif (strpos($policies, '2.23.140.1.2.2') !== false) {
            $isOV = true;
        }
        elseif (strpos($policies, '2.23.140.1.2.1') !== false) {
            $isDV = true;
        }
        else {
            $subjectKeys = array_keys($subjectData);
            $hasEvFields = !empty(array_intersect(['businessCategory', 'jurisdictionC', 'jurisdictionST', 'jurisdictionL', 'jurisdictionCountryName', 'OID.2.5.4.15'], $subjectKeys));
            if ($hasOrg && $hasEvFields)
                $isEV = true;
            elseif ($hasOrg)
                $isOV = true;
            else
                $isDV = true;
        }

        if ($isEV) {
            $validationLevel = 'EV (Extended Validation)';
            $valLevelDesc = 'Maximum trust with verified organization and jurisdiction details';
            $valLevelIcon = '🟢';
        }
        elseif ($isOV) {
            $validationLevel = 'OV (Organization Validation)';
            $valLevelDesc = 'High trust with verified organization details';
            $valLevelIcon = '🟡';
        }
        else {
            $validationLevel = 'DV (Domain Validation)';
            $valLevelDesc = 'Basic trust, only domain ownership verified';
            $valLevelIcon = '⚪';
        }

        if ($isCA) {
            $validationLevel = 'N/A (CA Certificate)';
            $valLevelDesc = 'Validation level typically applies to end-entity certificates';
            $valLevelIcon = '🏛️';
        }

        if ($isSelfSigned && !$isCA) {
            $validationLevel = 'Self-Signed';
            $valLevelDesc = 'Not issued by a trusted CA';
            $valLevelIcon = '⚠️';
        }

        $result = [
            'Common_Name' => $subjectData['CN'] ?? null,
            'SAN' => isset($extensions['subjectAltName']) ? str_replace('DNS:', '', $extensions['subjectAltName']) : null,
            'Organization' => $subjectData['O'] ?? null,
            'Organization_Unit' => $subjectData['OU'] ?? 'NA',
            'Country' => $subjectData['C'] ?? null,
            'State' => $subjectData['ST'] ?? null,
            'City' => $subjectData['L'] ?? null,
            'Address' => $subjectData['street'] ?? 'NA',
            'Postal_Code' => $subjectData['postalCode'] ?? 'NA',
            'Cert_Type' => $certType,
            'Cert_Type_Icon' => $certTypeIcon,
            'Cert_Type_Desc' => $certTypeDesc,
            'Validation_Level' => $validationLevel,
            'Validation_Level_Icon' => $valLevelIcon,
            'Validation_Level_Desc' => $valLevelDesc
        ];

        $domain = $result['Common_Name'] ?? 'certificate';
        $additionalInfo = [];

        if (isset($parsed['validFrom_time_t']))
            $additionalInfo['Valid From'] = date('Y-m-d H:i:s T', $parsed['validFrom_time_t']);
        if (isset($parsed['validTo_time_t']))
            $additionalInfo['Valid To'] = date('Y-m-d H:i:s T', $parsed['validTo_time_t']);
        if (isset($parsed['version']))
            $additionalInfo['Version'] = $parsed['version'] + 1;
        if (isset($parsed['serialNumberHex']))
            $additionalInfo['Serial Number'] = $parsed['serialNumberHex'];
        elseif (isset($parsed['serialNumber']))
            $additionalInfo['Serial Number'] = $parsed['serialNumber'];
        if (isset($parsed['signatureTypeSN']))
            $additionalInfo['Signature Algorithm'] = $parsed['signatureTypeSN'];
        if (isset($parsed['hash']))
            $additionalInfo['Hash Algorithm'] = $parsed['hash'];

        if (!empty($issuerData)) {
            $issuerString = [];
            foreach ($issuerData as $k => $v) {
                if (is_array($v))
                    $v = implode(', ', $v);
                $issuerString[] = "$k = $v";
            }
            $additionalInfo['Issuer Details'] = implode("\n", $issuerString);
        }

        if (!empty($extensions)) {
            $mappedExtensions = [
                'keyUsage' => 'Key Usage', 'extendedKeyUsage' => 'Extended Key Usage',
                'basicConstraints' => 'Basic Constraints', 'subjectKeyIdentifier' => 'Subject Key Identifier',
                'authorityKeyIdentifier' => 'Authority Key Identifier', 'crlDistributionPoints' => 'CRL Distribution Points',
                'authorityInfoAccess' => 'Authority Info Access', 'certificatePolicies' => 'Certificate Policies',
                'subjectAltName' => 'Subject Alternative Name', 'issuerAltName' => 'Issuer Alternative Name'
            ];
            foreach ($extensions as $extKey => $extVal) {
                if ($extKey === 'ct_precert_scts')
                    continue;
                $label = $mappedExtensions[$extKey] ?? ucfirst(preg_replace('/(?<!^)[A-Z]/', ' $0', $extKey));
                $additionalInfo[$label] = $extVal;
            }
        }

        $additionalInfo = array_filter($additionalInfo, function ($value) {
            return !empty($value) || $value === '0' || $value === 0;
        });

        $fingerprint = [];
        if (function_exists('openssl_x509_fingerprint')) {
            $fingerprint['sha1'] = openssl_x509_fingerprint($certContent, 'sha1');
            $fingerprint['sha256'] = openssl_x509_fingerprint($certContent, 'sha256');
        }

        session([
            'cert_data' => array_merge($result, ['additional_info' => $additionalInfo, 'fingerprint' => $fingerprint]),
            'original_cert' => $certContent,
            'domain_name' => $domain
        ]);

        return redirect('/certificate')->with('success', true);
    }

    public function download($action)
    {
        if ($action === 'cert' && session()->has('original_cert')) {
            $certContent = session('original_cert');
            $domain = session('domain_name', 'certificate');
            $filename = preg_replace('/[^a-zA-Z0-9.-]/', '_', $domain) . '_cert.txt';

            return response($certContent)
                ->header('Content-Type', 'application/x-pem-file')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0')
                ->header('Cache-Control', 'must-revalidate, post-check=0, pre-check=0');
        }
        elseif ($action === 'details' && session()->has('cert_data')) {
            $certData = session('cert_data');
            $domain = session('domain_name', 'certificate');
            $filename = preg_replace('/[^a-zA-Z0-9.-]/', '_', $domain) . '_details.txt';

            $fileContent = "╔══════════════════════════════════════════════════╗\n";
            $fileContent .= "║         CERTIFICATE DETAILS REPORT              ║\n";
            $fileContent .= "╚══════════════════════════════════════════════════╝\n\n";

            $fileContent .= "┌─────────────────────────────────────────────────┐\n";
            $fileContent .= "│           PARSED CERTIFICATE DETAILS            │\n";
            $fileContent .= "├─────────────────────────────────────────────────┤\n";
            $fileContent .= "│ Generated on: " . str_pad(date('Y-m-d H:i:s'), 36, ' ', STR_PAD_LEFT) . " │\n";
            $fileContent .= "└─────────────────────────────────────────────────┘\n\n";

            $fields = [
                'Common_Name' => 'Common Name',
                'SAN' => 'Subject Alternative Names',
                'Organization' => 'Organization',
                'Organization_Unit' => 'Organization Unit',
                'Country' => 'Country',
                'State' => 'State/Province',
                'City' => 'City/Locality',
                'Address' => 'Address',
                'Postal_Code' => 'Postal Code',
                'Cert_Type' => 'Certificate Type',
                'Validation_Level' => 'Validation Level'
            ];

            foreach ($fields as $key => $label) {
                $fileContent .= str_pad($label . ":", 30) . ($certData[$key] ?? 'N/A') . "\n";
            }

            if (isset($certData['additional_info'])) {
                $fileContent .= "\n" . str_repeat('─', 50) . "\n";
                $fileContent .= "ADDITIONAL INFORMATION:\n";
                $fileContent .= str_repeat('─', 50) . "\n";
                foreach ($certData['additional_info'] as $label => $value) {
                    $fileContent .= str_pad($label . ":", 30) . $value . "\n";
                }
            }

            if (isset($certData['fingerprint'])) {
                $fileContent .= "\n" . str_repeat('─', 50) . "\n";
                $fileContent .= "FINGERPRINTS:\n";
                $fileContent .= str_repeat('─', 50) . "\n";
                foreach ($certData['fingerprint'] as $algo => $hash) {
                    $fileContent .= str_pad(strtoupper($algo) . ":", 30) . $hash . "\n";
                }
            }

            return response($fileContent)
                ->header('Content-Type', 'text/plain')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0')
                ->header('Cache-Control', 'must-revalidate, post-check=0, pre-check=0');
        }

        // Invalid action or missing session data
        return redirect('/certificate')->with('error', 'Session expired or invalid request. Please parse the certificate again.');
     }
}