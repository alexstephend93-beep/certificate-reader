<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AdminCredential;

class AutoLoginController extends Controller
{
    /**
     * Show intermediate auto-login page with credential helper
     */
    public function autoLoginPage($credentialId)
    {
        $credential = AdminCredential::with('dashboard')->findOrFail($credentialId);

        $dashboardName = htmlspecialchars($credential->dashboard->name ?? 'Dashboard', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $loginUrl = htmlspecialchars($credential->dashboard->url ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $email = htmlspecialchars($credential->email ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $password = htmlspecialchars($credential->password ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Create a helper page that provides easy login assistance
        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login Helper - {$dashboardName}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 24px;
            padding: 40px;
            max-width: 500px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideIn 0.5s ease-out;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .logo {
            font-size: 64px;
            margin-bottom: 20px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        h1 {
            color: #1f2937;
            margin-bottom: 12px;
            font-size: 28px;
            font-weight: 700;
        }

        .subtitle {
            color: #6b7280;
            font-size: 16px;
            margin-bottom: 32px;
        }

        .credential-box {
            background: #f9fafb;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            border: 2px dashed #e5e7eb;
        }

        .cred-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            padding: 12px;
            background: white;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }

        .cred-row:last-child {
            margin-bottom: 0;
        }

        .cred-label {
            font-weight: 600;
            color: #6b7280;
            font-size: 14px;
        }

        .cred-value {
            font-family: 'Courier New', monospace;
            background: #f3f4f6;
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid #d1d5db;
            font-size: 14px;
            color: #1f2937;
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .copy-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            margin-left: 8px;
            transition: background 0.3s;
        }

        .copy-btn:hover {
            background: #5a67d8;
        }

        .copy-btn.copied {
            background: #10b981;
        }

        .actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: inline-block;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5a67d8;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #e5e7eb;
            color: #4b5563;
        }

        .btn-secondary:hover {
            background: #d1d5db;
        }

        .instructions {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 8px;
            padding: 16px;
            margin-top: 20px;
            text-align: left;
        }

        .instructions h3 {
            color: #92400e;
            font-size: 16px;
            margin-bottom: 8px;
        }

        .instructions ol {
            color: #78350f;
            font-size: 14px;
            line-height: 1.5;
        }

        .instructions li {
            margin-bottom: 4px;
        }

        @media (max-width: 768px) {
            .container {
                padding: 30px 20px;
                margin: 20px;
            }

            h1 {
                font-size: 24px;
            }

            .cred-row {
                flex-direction: column;
                align-items: stretch;
                gap: 8px;
            }

            .cred-value {
                max-width: none;
            }

            .actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">🔐</div>
        <h1>Login Helper</h1>
        <p class="subtitle">Quick login to {$dashboardName}</p>

        <div class="credential-box">
            <div class="cred-row">
                <span class="cred-label">Email:</span>
                <div style="display: flex; align-items: center;">
                    <span class="cred-value" id="emailValue">{$email}</span>
                    <button class="copy-btn" onclick="copyToClipboard('emailValue')">Copy</button>
                </div>
            </div>
            <div class="cred-row">
                <span class="cred-label">Password:</span>
                <div style="display: flex; align-items: center;">
                    <span class="cred-value" id="passwordValue">{$password}</span>
                    <button class="copy-btn" onclick="copyToClipboard('passwordValue')">Copy</button>
                </div>
            </div>
        </div>

        <div class="actions">
            <a href="{$loginUrl}" target="_blank" class="btn btn-primary" id="loginBtn">
                Open Login Page
            </a>
            <button class="btn btn-secondary" onclick="copyAllCredentials()">
                Copy All Credentials
            </button>
        </div>

        <div class="instructions">
            <h3>Quick Login Steps:</h3>
            <ol>
                <li>Click "Open Login Page" to open the login form in a new tab</li>
                <li>Paste the email and password into the form fields</li>
                <li>Click the login button to sign in</li>
            </ol>
        </div>
    </div>

    <script>
        function copyToClipboard(elementId) {
            const element = document.getElementById(elementId);
            const text = element.textContent;

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(function() {
                    showCopiedFeedback(elementId);
                });
            } else {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                showCopiedFeedback(elementId);
            }
        }

        function copyAllCredentials() {
            const email = document.getElementById('emailValue').textContent;
            const password = document.getElementById('passwordValue').textContent;
            const allCreds = `Email: ${email}\nPassword: ${password}`;

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(allCreds).then(function() {
                    alert('Credentials copied to clipboard!');
                });
            } else {
                const textArea = document.createElement('textarea');
                textArea.value = allCreds;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                alert('Credentials copied to clipboard!');
            }
        }

        function showCopiedFeedback(elementId) {
            const btn = document.querySelector(\`#\${elementId}\`).nextElementSibling;
            const originalText = btn.textContent;
            btn.textContent = 'Copied!';
            btn.classList.add('copied');

            setTimeout(function() {
                btn.textContent = originalText;
                btn.classList.remove('copied');
            }, 2000);
        }

        // Auto-open login page after 2 seconds
        setTimeout(function() {
            document.getElementById('loginBtn').click();
        }, 2000);
    </script>
</body>
</html>
HTML;

        return response($html, 200)->header('Content-Type', 'text/html; charset=UTF-8');
    }
}