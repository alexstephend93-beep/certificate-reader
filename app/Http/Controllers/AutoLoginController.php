<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\AdminCredential;

class AutoLoginController extends Controller
{
    /**
     * One-click auto login — server-side authentication with session forwarding
     */
    public function login(Request $request, $credentialId)
    {
        $credential = AdminCredential::with('dashboard')->findOrFail($credentialId);

        $credential->incrementUsage();
        $credential->dashboard->incrementUsage();

        $loginUrl = rtrim($credential->dashboard->url ?? '', '/');
        $email = $credential->email;
        $username = $credential->username ?? $credential->email;
        $password = $credential->password;

        // ----------------------------------------------------------------
        // STEP 1: GET login page - capture session
        // ----------------------------------------------------------------
        try {
            $getResp = Http::withOptions([
                'allow_redirects' => false,
                'verify' => false,
                'timeout' => 30
            ])
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ])
            ->get($loginUrl);
            
        } catch (\Exception $e) {
            return $this->errorPage("Cannot reach login page: " . $e->getMessage(), $credential);
        }

        $html = $getResp->body();
        $cookies = $this->extractCookiesFromResponse($getResp);
        $csrfToken = $this->extractCsrfToken($html);
        
        Log::info('Step 1: GET login page', [
            'url' => $loginUrl,
            'status' => $getResp->status(),
            'csrf_token' => $csrfToken ? 'found' : 'not found',
            'cookies' => array_keys($cookies),
        ]);

        if (!$csrfToken) {
            return $this->errorPage("Cannot extract CSRF token from login page. The target application may have different authentication mechanism.", $credential);
        }

        // ----------------------------------------------------------------
        // STEP 2: POST login credentials (server-side)
        // ----------------------------------------------------------------
        $payload = [
            '_token' => $csrfToken,
            'email' => $email,
            'password' => $password,
            'remember' => '1',
        ];
        
        // Try alternative field names
        $possibleFields = ['username', 'user', 'log', 'login', 'email'];
        foreach ($possibleFields as $field) {
            $payload[$field] = ($field === 'email') ? $email : $username;
        }
        
        $possiblePasswordFields = ['pwd', 'pass', 'passwd', 'password'];
        foreach ($possiblePasswordFields as $field) {
            $payload[$field] = $password;
        }

        try {
            $postResp = Http::withOptions([
                'allow_redirects' => ['max' => 5, 'strict' => false],
                'verify' => false,
                'timeout' => 30
            ])
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Referer' => $loginUrl,
                'Origin' => $this->getOrigin($loginUrl),
            ])
            ->withCookies($cookies, parse_url($loginUrl, PHP_URL_HOST))
            ->asForm()
            ->post($loginUrl, $payload);
            
        } catch (\Exception $e) {
            return $this->errorPage("Login POST failed: " . $e->getMessage(), $credential);
        }

        // ----------------------------------------------------------------
        // STEP 3: Extract final session and redirect to our proxy
        // ----------------------------------------------------------------
        $finalUrl = $postResp->effectiveUri() ?? $loginUrl;
        $status = $postResp->status();
        $responseBody = $postResp->body();
        
        // Merge all cookies from the entire request chain
        $allCookies = array_merge($cookies, $this->extractCookiesFromResponse($postResp));
        
        Log::info('Step 2: POST login', [
            'status' => $status,
            'final_url' => $finalUrl,
            'cookies' => array_keys($allCookies),
        ]);
        
        // Check if login was successful
        if ($this->isLoginSuccessful($status, $responseBody)) {
            // Store the authenticated session
            session([
                'auth_cookies_' . $credentialId => $allCookies,
                'auth_base_url_' . $credentialId => $this->getBaseUrl($loginUrl),
                'auth_dashboard_name_' . $credentialId => $credential->dashboard->name,
            ]);
            
            // Redirect to our proxy page
            return redirect()->route('auto.dashboard', ['credentialId' => $credentialId]);
        }
        
        $errorMessage = $this->extractErrorMessage($responseBody);
        return $this->errorPage(
            "Login failed. " . ($errorMessage ?: "Invalid credentials or authentication method not supported."),
            $credential
        );
    }
    
    /**
     * Show the dashboard via proxy
     */
    public function dashboard($credentialId)
    {
        $cookies = session('auth_cookies_' . $credentialId, []);
        $baseUrl = session('auth_base_url_' . $credentialId, '');
        $dashboardName = session('auth_dashboard_name_' . $credentialId, 'Dashboard');
        
        if (empty($cookies) || empty($baseUrl)) {
            return redirect()->route('auto.login', $credentialId);
        }
        
        // Convert cookies to a format that can be passed to JavaScript
        $cookieJson = json_encode($cookies);
        
        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$dashboardName} Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
        }
        
        .toolbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .toolbar h2 {
            font-size: 18px;
            font-weight: 600;
        }
        
        .toolbar-buttons {
            display: flex;
            gap: 10px;
        }
        
        .toolbar button {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .toolbar button:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }
        
        .iframe-container {
            position: fixed;
            top: 52px;
            left: 0;
            right: 0;
            bottom: 0;
            background: white;
        }
        
        iframe {
            width: 100%;
            height: 100%;
            border: none;
        }
        
        .loading {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            z-index: 999;
            background: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        
        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @media (max-width: 768px) {
            .toolbar {
                padding: 8px 12px;
            }
            .toolbar h2 {
                font-size: 14px;
            }
            .toolbar button {
                padding: 6px 12px;
                font-size: 12px;
            }
            .iframe-container {
                top: 48px;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <h2>🔐 {$dashboardName} Dashboard</h2>
        <div class="toolbar-buttons">
            <button onclick="refreshIframe()">🔄 Refresh</button>
            <button onclick="openInNewTab()">📌 Open in New Tab</button>
            <button onclick="history.back()">← Back</button>
        </div>
    </div>
    <div class="iframe-container">
        <iframe id="dashboardFrame" src="about:blank" frameborder="0"></iframe>
    </div>
    
    <div class="loading" id="loadingOverlay">
        <div class="spinner"></div>
        <p>Loading dashboard...</p>
    </div>
    
    <script>
        const cookies = {$cookieJson};
        const proxyUrl = "{$baseUrl}";
        const iframe = document.getElementById('dashboardFrame');
        const loading = document.getElementById('loadingOverlay');
        
        // Function to set cookies
        function setCookies() {
            for (const [name, value] of Object.entries(cookies)) {
                document.cookie = name + '=' + encodeURIComponent(value) + '; path=/; SameSite=Lax';
            }
            console.log('Cookies set:', Object.keys(cookies));
        }
        
        // Function to load the dashboard
        function loadDashboard() {
            setCookies();
            iframe.src = proxyUrl;
        }
        
        // Refresh iframe
        function refreshIframe() {
            loading.style.display = 'flex';
            setCookies();
            iframe.src = iframe.src;
        }
        
        // Open in new tab
        function openInNewTab() {
            const newWindow = window.open('about:blank', '_blank');
            newWindow.document.write(`
                <html>
                <head><title>{$dashboardName}</title></head>
                <body>
                    <script>
                        const cookies = {$cookieJson};
                        for (const [name, value] of Object.entries(cookies)) {
                            document.cookie = name + '=' + encodeURIComponent(value) + '; path=/';
                        }
                        window.location.href = '{$baseUrl}';
                    <\/script>
                    <p>Redirecting...</p>
                </body>
                </html>
            `);
        }
        
        iframe.addEventListener('load', function() {
            loading.style.display = 'none';
        });
        
        iframe.addEventListener('error', function() {
            loading.innerHTML = '<div style="color: red;">⚠️ Failed to load dashboard. <button onclick="refreshIframe()">Try Again</button></div>';
        });
        
        // Load the dashboard
        loadDashboard();
        
        // Hide loading after 10 seconds as fallback
        setTimeout(function() {
            loading.style.display = 'none';
        }, 10000);
    </script>
</body>
</html>
HTML;

        return response($html, 200)->header('Content-Type', 'text/html; charset=UTF-8');
    }
    
    /**
     * Extract cookies from HTTP response
     */
    private function extractCookiesFromResponse($response): array
    {
        $cookies = [];
        
        if ($response && method_exists($response, 'cookies')) {
            foreach ($response->cookies() as $cookie) {
                $cookies[$cookie->getName()] = $cookie->getValue();
            }
        }
        
        return $cookies;
    }
    
    /**
     * Extract CSRF token from HTML
     */
    private function extractCsrfToken(string $html): ?string
    {
        $patterns = [
            '/<meta[^>]+name=["\']csrf-token["\'][^>]+content=["\']([^"\']+)["\']/i',
            '/<input[^>]+name=["\']_token["\'][^>]+value=["\']([^"\']+)["\']/i',
            '/<input[^>]+name=["\']csrf_token["\'][^>]+value=["\']([^"\']+)["\']/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                return $matches[1];
            }
        }
        
        return null;
    }
    
    /**
     * Check if login was successful
     */
    private function isLoginSuccessful(int $status, string $body): bool
    {
        // Redirect indicates success
        if ($status >= 300 && $status < 400) {
            return true;
        }
        
        // Check for success indicators
        $successPatterns = ['/dashboard/i', '/welcome/i', '/home/i', '/admin/i'];
        foreach ($successPatterns as $pattern) {
            if (preg_match($pattern, $body)) {
                return true;
            }
        }
        
        // Check for failure indicators
        $failurePatterns = ['/invalid/i', '/incorrect/i', '/failed/i', '/error/i'];
        foreach ($failurePatterns as $pattern) {
            if (preg_match($pattern, $body)) {
                return false;
            }
        }
        
        return false;
    }
    
    /**
     * Extract error message from response
     */
    private function extractErrorMessage(string $html): ?string
    {
        $patterns = [
            '/<div[^>]*class="[^"]*(?:alert|error|danger|invalid)[^"]*"[^>]*>(.*?)<\//is',
            '/(?:Invalid|Incorrect) (?:credentials|username|password|email)/i',
            '/(?:Login|Authentication) failed/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $error = strip_tags($matches[1] ?? $matches[0]);
                if (strlen($error) > 5 && strlen($error) < 200) {
                    return trim($error);
                }
            }
        }
        
        return null;
    }
    
    /**
     * Get base URL
     */
    private function getBaseUrl(string $url): string
    {
        $parsed = parse_url($url);
        return ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');
    }
    
    /**
     * Get origin from URL
     */
    private function getOrigin(string $url): string
    {
        $parsed = parse_url($url);
        return ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');
    }
    
    /**
     * Display error page
     */
    private function errorPage(string $message, $credential): \Illuminate\Http\Response
    {
        $safe = htmlspecialchars($message, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $dashboardName = htmlspecialchars($credential->dashboard->name ?? 'Unknown', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $url = htmlspecialchars($credential->dashboard->url ?? '#', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $email = htmlspecialchars($credential->email ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $username = htmlspecialchars($credential->username ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Auto Login Failed</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;display:flex;justify-content:center;align-items:center;min-height:100vh;padding:20px}
        .card{background:#fff;border-radius:24px;padding:40px;max-width:500px;width:100%;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,0.3)}
        .err{font-size:64px;margin-bottom:20px}
        h2{color:#dc2626;margin-bottom:12px;font-size:24px}
        .msg{color:#4b5563;font-size:14px;margin-bottom:28px;line-height:1.6;word-break:break-word}
        .grid{background:#f9fafb;padding:20px;border-radius:16px;margin-bottom:28px;text-align:left}
        .row{display:flex;gap:12px;margin-bottom:10px;font-size:13px;flex-wrap:wrap}
        .lbl{color:#6b7280;min-width:100px;font-weight:600}
        .val{color:#1f2937;word-break:break-all}
        .btns{display:flex;gap:12px;justify-content:center;flex-wrap:wrap}
        a{display:inline-block;padding:12px 24px;border-radius:12px;text-decoration:none;font-size:14px;font-weight:600;transition:all 0.3s}
        .primary{background:#667eea;color:#fff}
        .primary:hover{background:#5a67d8;transform:translateY(-2px)}
        .secondary{background:#e5e7eb;color:#4b5563}
        .secondary:hover{background:#d1d5db}
    </style>
</head>
<body>
<div class="card">
    <div class="err">⚠️</div>
    <h2>Auto Login Failed</h2>
    <p class="msg">{$safe}</p>
    <div class="grid">
        <div class="row"><span class="lbl">Dashboard:</span><span class="val">{$dashboardName}</span></div>
        <div class="row"><span class="lbl">URL:</span><span class="val">{$url}</span></div>
        <div class="row"><span class="lbl">Email:</span><span class="val">{$email}</span></div>
        <div class="row"><span class="lbl">Username:</span><span class="val">{$username}</span></div>
    </div>
    <div class="btns">
        <a class="primary" href="javascript:history.back()">← Go Back</a>
        <a class="secondary" href="{$url}" target="_blank">Open Manually →</a>
    </div>
</div>
</body>
</html>
HTML;

        return response($html, 200)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    public function directLogin($credentialId)
{
    $credential = AdminCredential::with('dashboard')->findOrFail($credentialId);
    
    $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Auto Login</title>
</head>
<body>
    <form id="loginForm" method="POST" action="{$credential->dashboard->url}">
        <input type="hidden" name="email" value="{$credential->email}">
        <input type="hidden" name="password" value="{$credential->password}">
        <input type="hidden" name="_token" value="">
    </form>
    <script>
        // Fetch CSRF token first
        fetch('{$credential->dashboard->url}', {
            method: 'GET',
            credentials: 'include'
        })
        .then(response => response.text())
        .then(html => {
            const match = html.match(/name="_token" value="([^"]+)"/);
            if (match) {
                document.querySelector('input[name="_token"]').value = match[1];
                document.getElementById('loginForm').submit();
            } else {
                document.getElementById('loginForm').submit();
            }
        });
    </script>
</body>
</html>
HTML;
    
    return response($html);
}
}