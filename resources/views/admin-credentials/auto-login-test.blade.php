<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auto Login - {{ $credential->dashboard->name }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .container {
            text-align: center;
            padding: 20px;
        }
        
        .card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 500px;
            margin: 0 auto;
        }
        
        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #10b981;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .success-icon {
            width: 60px;
            height: 60px;
            background: #10b981;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        
        .success-icon i {
            color: white;
            font-size: 30px;
        }
        
        h2 {
            margin-bottom: 10px;
            color: #1e293b;
        }
        
        .info {
            background: #f1f5f9;
            padding: 15px;
            border-radius: 12px;
            margin: 20px 0;
            text-align: left;
        }
        
        .info p {
            margin: 5px 0;
            font-size: 14px;
        }
        
        .info strong {
            color: #667eea;
        }
        
        .status {
            margin-top: 15px;
            padding: 10px;
            border-radius: 8px;
            font-size: 13px;
        }
        
        .status.success {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status.error {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .status.info {
            background: #e0e7ff;
            color: #3730a3;
        }
        
        .hidden {
            display: none;
        }
        
        .progress-bar {
            width: 100%;
            height: 4px;
            background: #e2e8f0;
            border-radius: 2px;
            overflow: hidden;
            margin-top: 20px;
        }
        
        .progress-fill {
            width: 0%;
            height: 100%;
            background: linear-gradient(90deg, #10b981, #059669);
            transition: width 0.5s ease;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="success-icon">
                <i>✓</i>
            </div>
            <h2>Auto Login in Progress</h2>
            <p>Logging in to <strong>{{ $credential->dashboard->name }}</strong></p>
            
            <div class="info">
                <p><strong>URL:</strong> {{ $credential->dashboard->url }}</p>
                <p><strong>Email:</strong> {{ $credential->email }}</p>
                @if($credential->username)
                <p><strong>Username:</strong> {{ $credential->username }}</p>
                @endif
                <p><strong>Status:</strong> <span id="loginStatus">Connecting...</span></p>
            </div>
            
            <div class="progress-bar">
                <div class="progress-fill" id="progressFill"></div>
            </div>
            
            <div id="statusMessage" class="status info">🔄 Attempting auto-login...</div>
        </div>
    </div>

    <script>
        const credential = {
            email: '{{ $credential->email }}',
            username: '{{ $credential->username ?? $credential->email }}',
            password: '{{ addslashes($credential->password) }}',
            url: '{{ $credential->dashboard->url }}',
            name: '{{ $credential->dashboard->name }}'
        };
        
        let statusDiv = document.getElementById('statusMessage');
        let loginStatusSpan = document.getElementById('loginStatus');
        let progressFill = document.getElementById('progressFill');
        
        function updateStatus(message, type = 'info', progress = null) {
            if (statusDiv) {
                statusDiv.innerHTML = message;
                statusDiv.className = `status ${type}`;
            }
            if (loginStatusSpan) {
                loginStatusSpan.textContent = message.replace(/[<>]/g, '');
            }
            if (progress !== null && progressFill) {
                progressFill.style.width = progress + '%';
            }
            console.log(`[${type.toUpperCase()}] ${message}`);
        }
        
        // Method 1: Direct POST with form submission
        function directPostLogin() {
            return new Promise((resolve) => {
                updateStatus('📤 Sending login request...', 'info', 25);
                
                // Create a form dynamically
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = credential.url;
                form.target = '_blank';
                form.style.display = 'none';
                
                // Common field names used by different systems
                const fieldSets = [
                    // WordPress / Common PHP
                    { 
                        fields: [
                            { name: 'log', value: credential.email },
                            { name: 'pwd', value: credential.password },
                            { name: 'rememberme', value: 'forever' },
                            { name: 'wp-submit', value: 'Log In' },
                            { name: 'redirect_to', value: credential.url }
                        ]
                    },
                    // Laravel / Modern PHP
                    {
                        fields: [
                            { name: 'email', value: credential.email },
                            { name: 'password', value: credential.password },
                            { name: 'remember', value: '1' }
                        ]
                    },
                    // cPanel
                    {
                        fields: [
                            { name: 'user', value: credential.username },
                            { name: 'pass', value: credential.password }
                        ]
                    },
                    // Generic
                    {
                        fields: [
                            { name: 'username', value: credential.username },
                            { name: 'email', value: credential.email },
                            { name: 'password', value: credential.password },
                            { name: 'login', value: 'Login' },
                            { name: 'submit', value: 'Login' }
                        ]
                    }
                ];
                
                // Try all field combinations
                let formSubmitted = false;
                
                for (const fieldSet of fieldSets) {
                    const testForm = document.createElement('form');
                    testForm.method = 'POST';
                    testForm.action = credential.url;
                    testForm.target = '_blank';
                    testForm.style.display = 'none';
                    
                    for (const field of fieldSet.fields) {
                        const input = document.createElement('input');
                        input.type = 'text';
                        input.name = field.name;
                        input.value = field.value;
                        testForm.appendChild(input);
                    }
                    
                    document.body.appendChild(testForm);
                    
                    // Try to submit
                    try {
                        testForm.submit();
                        formSubmitted = true;
                        updateStatus('✅ Login request sent! Waiting for response...', 'success', 100);
                        resolve(true);
                        break;
                    } catch(e) {
                        // Continue to next field set
                    }
                }
                
                if (!formSubmitted) {
                    resolve(false);
                }
            });
        }
        
        // Method 2: Open in new window with auto-fill script
        function openWithAutoFill() {
            updateStatus('📝 Opening login page with auto-fill...', 'info', 50);
            
            // Create a comprehensive auto-fill script
            const autoFillScript = `
                (function() {
                    console.log('Auto-login script running...');
                    
                    // Function to find and fill form
                    function fillAndSubmit() {
                        // Try to find any form on the page
                        const forms = document.querySelectorAll('form');
                        let targetForm = null;
                        
                        // Find form that contains password field
                        for (let form of forms) {
                            if (form.querySelector('input[type="password"]')) {
                                targetForm = form;
                                break;
                            }
                        }
                        
                        if (!targetForm && forms.length > 0) {
                            targetForm = forms[0];
                        }
                        
                        if (targetForm) {
                            // Find email/username field
                            const emailSelectors = [
                                'input[type="email"]',
                                'input[name*="email"]',
                                'input[name*="user"]',
                                'input[name*="login"]',
                                'input[name*="username"]',
                                'input[name="log"]'
                            ];
                            
                            let emailField = null;
                            for (let selector of emailSelectors) {
                                emailField = targetForm.querySelector(selector);
                                if (emailField) break;
                            }
                            
                            // Find password field
                            const passwordField = targetForm.querySelector('input[type="password"]');
                            
                            // Fill email/username
                            if (emailField) {
                                emailField.value = '${credential.email}';
                                emailField.dispatchEvent(new Event('input', { bubbles: true }));
                                emailField.dispatchEvent(new Event('change', { bubbles: true }));
                            }
                            
                            // Fill password
                            if (passwordField) {
                                passwordField.value = '${credential.password}';
                                passwordField.dispatchEvent(new Event('input', { bubbles: true }));
                                passwordField.dispatchEvent(new Event('change', { bubbles: true }));
                            }
                            
                            // Auto submit after a short delay
                            setTimeout(() => {
                                const submitBtn = targetForm.querySelector('button[type="submit"], input[type="submit"], button:not([type])');
                                if (submitBtn) {
                                    submitBtn.click();
                                } else {
                                    targetForm.submit();
                                }
                            }, 500);
                            
                            return true;
                        }
                        return false;
                    }
                    
                    // Try to fill immediately
                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', fillAndSubmit);
                    } else {
                        setTimeout(fillAndSubmit, 500);
                    }
                })();
            `;
            
            // Create a blob URL for the script
            const blob = new Blob([autoFillScript], { type: 'text/javascript' });
            const scriptUrl = URL.createObjectURL(blob);
            
            // Open new window with the script
            const newWindow = window.open();
            newWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Auto Login - ${credential.name}</title>
                    <style>
                        body {
                            font-family: monospace;
                            display: flex;
                            justify-content: center;
                            align-items: center;
                            height: 100vh;
                            margin: 0;
                            background: #f5f5f5;
                        }
                        .message {
                            text-align: center;
                            padding: 20px;
                        }
                        .spinner {
                            width: 40px;
                            height: 40px;
                            border: 3px solid #e2e8f0;
                            border-top-color: #667eea;
                            border-radius: 50%;
                            animation: spin 1s linear infinite;
                            margin: 0 auto 20px;
                        }
                        @keyframes spin {
                            to { transform: rotate(360deg); }
                        }
                    </style>
                </head>
                <body>
                    <div class="message">
                        <div class="spinner"></div>
                        <h3>Redirecting to ${credential.name}</h3>
                        <p>Please wait...</p>
                    </div>
                    <script>
                        window.location.href = '${credential.url}';
                        
                        // Inject auto-fill script after page loads
                        window.addEventListener('load', function() {
                            setTimeout(function() {
                                ${autoFillScript}
                            }, 1000);
                        });
                    <\/script>
                </body>
                </html>
            `);
            
            updateStatus('✅ Auto-login window opened!', 'success', 100);
            
            // Close current window after 2 seconds
            setTimeout(() => {
                window.close();
            }, 2000);
        }
        
        // Method 3: Use fetch with credentials
        async function fetchLogin() {
            updateStatus('🔄 Trying API login...', 'info', 75);
            
            try {
                const formData = new FormData();
                formData.append('email', credential.email);
                formData.append('username', credential.username);
                formData.append('password', credential.password);
                formData.append('login', 'Login');
                
                const response = await fetch(credential.url, {
                    method: 'POST',
                    body: formData,
                    credentials: 'include',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                if (response.redirected) {
                    updateStatus('✅ Login successful! Redirecting...', 'success', 100);
                    window.open(response.url, '_blank');
                    return true;
                }
            } catch(e) {
                return false;
            }
            return false;
        }
        
        // Start auto-login process
        async function startAutoLogin() {
            updateStatus('🚀 Starting auto-login process...', 'info', 10);
            
            // Try direct POST first
            const postResult = await directPostLogin();
            if (postResult) {
                updateStatus('✅ Login form submitted!', 'success', 100);
                setTimeout(() => {
                    window.close();
                }, 2000);
                return;
            }
            
            // Try fetch login
            const fetchResult = await fetchLogin();
            if (fetchResult) {
                updateStatus('✅ API login successful!', 'success', 100);
                setTimeout(() => {
                    window.close();
                }, 2000);
                return;
            }
            
            // Fallback: Open with auto-fill
            updateStatus('📝 Using auto-fill method...', 'info', 50);
            setTimeout(() => {
                openWithAutoFill();
                updateStatus('✅ Auto-login window opened!', 'success', 100);
                setTimeout(() => {
                    window.close();
                }, 2000);
            }, 500);
        }
        
        // Start the process
        startAutoLogin();
    </script>
</body>
</html>