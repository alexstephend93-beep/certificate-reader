<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AIChatController extends Controller
{
    public function chat(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:5000',
            'conversation_id' => 'nullable|string',
        ]);

        $message = $request->message;
        $conversationId = $request->conversation_id ?? session()->getId();
        
        // Get conversation history
        $history = $this->getConversationHistory($conversationId);
        
        // Add user message to history
        $history[] = ['role' => 'user', 'content' => $message];
        
        // Get REAL AI response from free API
        $response = $this->getRealAIResponse($history);
        
        // If real AI fails, use intelligent fallback
        if (!$response) {
            $response = $this->getIntelligentFallbackResponse($message);
        }
        
        // Detect if the response is actually an API error about image input
        if ($response && $this->isApiImageInputError($response)) {
            return response()->json([
                'success' => false,
                'error_message' => $response,
                'message' => 'This AI model only processes text. Image and file attachments are not supported. Please describe what you need without referencing files or images.',
                'is_image_error' => true,
                'conversation_id' => $conversationId,
                'timestamp' => now()->toIso8601String()
            ]);
        }
        
        // Add AI response to history
        $history[] = ['role' => 'assistant', 'content' => $response];
        
        // Save conversation
        $this->saveConversationHistory($conversationId, array_slice($history, -30));
        
        return response()->json([
            'success' => true,
            'response' => $response,
            'conversation_id' => $conversationId,
            'timestamp' => now()->toIso8601String()
        ]);
    }
    
    private function getRealAIResponse($history)
    {
        $lastUserMessage = '';
        if (!empty($history)) {
            $lastUserMessage = is_array(end($history)) ? (end($history)['content'] ?? '') : '';
        }

        // FREE API #1: Pollinations.ai (No API key, unlimited, works great!)
        try {
            $response = Http::timeout(30)
                ->post('https://text.pollinations.ai/openai/v1/chat/completions', [
                    'model' => 'openai',
                    'messages' => $history,
                    'temperature' => 0.7,
                    'max_tokens' => 1000,
                ]);
            
            if ($response->successful()) {
                $data = $response->json();
                $content = $data['choices'][0]['message']['content'] ?? null;
                if ($content && strlen($content) > 5) {
                    Log::info('AI response from Pollinations.ai');
                    return $content;
                }
            }

            if ($response->failed()) {
                $body = $response->body();
                if ($this->isImageInputError($body, $lastUserMessage)) {
                    return null;
                }
            }
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            if ($this->containsImageRef($msg, $lastUserMessage)) {
                return null;
            }
            Log::warning('Pollinations.ai failed: ' . $e->getMessage());
        }
        
        // FREE API #2: OpenRouter (No API key, multiple models)
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'HTTP-Referer' => url('/'),
                    'X-Title' => 'Certificate Tools'
                ])
                ->post('https://openrouter.ai/api/v1/chat/completions', [
                    'model' => 'microsoft/phi-3-mini-128k-instruct:free',
                    'messages' => $history,
                    'temperature' => 0.7,
                    'max_tokens' => 1000,
                ]);
            
            if ($response->successful()) {
                $data = $response->json();
                $content = $data['choices'][0]['message']['content'] ?? null;
                if ($content && strlen($content) > 5) {
                    Log::info('AI response from OpenRouter');
                    return $content;
                }
            }

            if ($response->failed()) {
                $body = $response->body();
                if ($this->isImageInputError($body, $lastUserMessage)) {
                    return null;
                }
            }
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            if ($this->containsImageRef($msg, $lastUserMessage)) {
                return null;
            }
            Log::warning('OpenRouter failed: ' . $e->getMessage());
        }
        
        // FREE API #3: Llama API (No API key)
        try {
            $response = Http::timeout(30)
                ->post('https://api.llama-api.com/chat/completions', [
                    'model' => 'llama3-8b',
                    'messages' => $history,
                    'temperature' => 0.7,
                    'max_tokens' => 1000,
                ]);
            
            if ($response->successful()) {
                $data = $response->json();
                $content = $data['choices'][0]['message']['content'] ?? null;
                if ($content && strlen($content) > 5) {
                    Log::info('AI response from Llama API');
                    return $content;
                }
            }

            if ($response->failed()) {
                $body = $response->body();
                if ($this->isImageInputError($body, $lastUserMessage)) {
                    return null;
                }
            }
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            if ($this->containsImageRef($msg, $lastUserMessage)) {
                return null;
            }
            Log::warning('Llama API failed: ' . $e->getMessage());
        }
        
        return null;
    }

    private function isImageInputError($body, $userMessage)
    {
        if ($this->containsImageRef($body, $userMessage)) {
            return true;
        }
        if (stripos($body, 'image input') !== false) return true;
        if (stripos($body, 'image_url') !== false) return true;
        if (stripos($body, 'input_image') !== false) return true;
        if (stripos($body, 'content_type') !== false && stripos($body, 'image') !== false) return true;
        return false;
    }

    private function containsImageRef($text, $userMessage)
    {
        $text = strtolower($text);
        $message = strtolower($userMessage ?? '');
        if (preg_match('/\.(png|jpg|jpeg|gif|bmp|webp|svg|ico|tiff|heic|avif)(\b|$)/i', $message)) {
            return true;
        }
        if (preg_match('/image\.png/i', $text)) return true;
        if (preg_match('/attach.*image/i', $message)) return true;
        if (preg_match('/upload.*image/i', $message)) return true;
        if (preg_match('/send.*image/i', $message)) return true;
        return false;
    }
    
    private function getIntelligentFallbackResponse($message)
    {
        $messageLower = strtolower($message);
        
        // Greetings
        if (trim($messageLower) === 'hi' || trim($messageLower) === 'hello' || trim($messageLower) === 'hey') {
            return "👋 Hello! I'm your AI assistant for security tools.\n\nI can help you with:\n• 🔐 SSL/TLS certificates\n• 🔑 JWT token analysis\n• 🌐 API testing & debugging\n• 🔒 Encryption & hashing\n\nWhat would you like to know about?";
        }
        
        // SSL Certificate Generation on Ubuntu
        if ((strpos($messageLower, 'generate') !== false || strpos($messageLower, 'create') !== false) && 
            (strpos($messageLower, 'ssl') !== false || strpos($messageLower, 'certificate') !== false)) {
            
            if (strpos($messageLower, 'ubuntu') !== false || strpos($messageLower, 'server') !== false) {
                return "🔐 **How to Generate SSL Certificate on Ubuntu Server**\n\n**Method 1: Self-Signed Certificate (Testing)**\n\n```bash\n# Generate private key and certificate\nsudo openssl req -x509 -nodes -days 365 -newkey rsa:2048 \\\n    -keyout /etc/ssl/private/yourdomain.key \\\n    -out /etc/ssl/certs/yourdomain.crt\n\n# You'll be prompted to enter:\n# - Country Name (2 letter code)\n# - State or Province Name\n# - Locality Name (City)\n# - Organization Name\n# - Organizational Unit Name\n# - Common Name (your domain name)\n# - Email Address\n```\n\n**Method 2: Let's Encrypt (Free & Trusted)**\n\n```bash\n# Install Certbot\nsudo apt update\nsudo apt install certbot python3-certbot-apache\n\n# For Apache\nsudo certbot --apache -d yourdomain.com -d www.yourdomain.com\n\n# For Nginx\nsudo certbot --nginx -d yourdomain.com -d www.yourdomain.com\n```\n\n**Method 3: Generate CSR for Commercial CA**\n\n```bash\n# Generate private key and CSR\nsudo openssl req -new -newkey rsa:2048 -nodes \\\n    -keyout yourdomain.key \\\n    -out yourdomain.csr\n```\n\n**Certificate Locations:**\n• Private Key: `/etc/ssl/private/`\n• Certificate: `/etc/ssl/certs/`\n• Let's Encrypt: `/etc/letsencrypt/live/yourdomain/`\n\nWould you like help with a specific web server (Apache/Nginx)?";
            }
        }
        
        // Certificate validation
        if (strpos($messageLower, 'validate') !== false || strpos($messageLower, 'check') !== false) {
            return "✅ **How to Validate SSL Certificate**\n\n**Using OpenSSL:**\n```bash\n# Check certificate details\nopenssl x509 -in certificate.crt -text -noout\n\n# Verify against CA bundle\nopenssl verify -CAfile ca-bundle.crt certificate.crt\n\n# Check expiration date\nopenssl x509 -in certificate.crt -noout -dates\n```\n\n**Using our Certificate Reader:**\nUpload your certificate file or paste the PEM content to see:\n• Validity period\n• Issuer details\n• Subject Alternative Names\n• Certificate chain\n\nTry it now!";
        }
        
        // JWT questions
        if (strpos($messageLower, 'jwt') !== false) {
            return "🔑 **JWT (JSON Web Token)**\n\nA JWT consists of 3 parts: Header.Payload.Signature\n\n**To generate a JWT:**\n```php\nuse Firebase\\JWT\\JWT;\n\n$payload = ['user_id' => 123, 'exp' => time() + 3600];\n$token = JWT::encode($payload, 'your-secret-key', 'HS256');\n```\n\n**To decode a JWT:**\n```php\n$decoded = JWT::decode($token, 'your-secret-key', ['HS256']);\n```\n\nUse our JWT Analyzer tool to decode and verify tokens!";
        }
        
        // Default response
        return "I'm your AI assistant for security tools! 🔐\n\nI can help you with:\n• **SSL/TLS Certificates** - Generate, install, validate\n• **JWT Tokens** - Generate, decode, verify\n• **API Testing** - Debug HTTP requests\n• **Encryption** - Hashing and encryption methods\n\nTry asking:\n• 'How to generate SSL certificate on Ubuntu?'\n• 'How to validate a certificate?'\n• 'What is JWT?'";
    }
    
    public function getConversations(Request $request)
    {
        $conversations = Cache::get('ai_conversations_' . session()->getId(), []);
        return response()->json(['conversations' => $conversations]);
    }
    
    public function clearConversation(Request $request)
    {
        $conversationId = $request->conversation_id ?? session()->getId();
        Cache::forget('ai_conversation_' . $conversationId);
        return response()->json(['success' => true]);
    }
    
    public function deleteMessage(Request $request)
    {
        $request->validate([
            'message_index' => 'required|integer',
            'conversation_id' => 'nullable|string'
        ]);
        
        $conversationId = $request->conversation_id ?? session()->getId();
        $history = $this->getConversationHistory($conversationId);
        
        if (isset($history[$request->message_index])) {
            array_splice($history, $request->message_index, 1);
            $this->saveConversationHistory($conversationId, $history);
        }
        
        return response()->json(['success' => true]);
    }
    
    public function exportConversation(Request $request)
    {
        $conversationId = $request->conversation_id ?? session()->getId();
        $history = $this->getConversationHistory($conversationId);
        
        $export = [
            'exported_at' => now()->toIso8601String(),
            'conversation_id' => $conversationId,
            'messages' => $history
        ];
        
        return response()->json($export);
    }
    
    public function suggestPrompts(Request $request)
    {
        return response()->json([
            'prompts' => [
                'How to generate SSL certificate on Ubuntu?',
                'How to validate a certificate?',
                'How to generate JWT token?',
                'API testing best practices',
                'Difference between SHA-256 and MD5'
            ]
        ]);
    }
    
    public function testConnection()
    {
        $testHistory = [
            ['role' => 'user', 'content' => 'Say "Hello! AI is working!"']
        ];
        
        $response = $this->getRealAIResponse($testHistory);
        
        if ($response) {
            return response()->json([
                'success' => true,
                'message' => 'Real AI is working!',
                'response' => $response
            ]);
        }
        
        return response()->json([
            'success' => false,
            'message' => 'AI service unavailable, using fallback responses'
        ]);
    }
    
    private function getConversationHistory($conversationId)
    {
        return Cache::get('ai_conversation_' . $conversationId, []);
    }
    
    private function saveConversationHistory($conversationId, $history)
    {
        Cache::put('ai_conversation_' . $conversationId, $history, now()->addDays(7));
        
        $conversations = Cache::get('ai_conversations_' . session()->getId(), []);
        if (!in_array($conversationId, $conversations)) {
            $conversations[] = $conversationId;
            Cache::put('ai_conversations_' . session()->getId(), $conversations, now()->addDays(7));
        }
    }

    private function isApiImageInputError(?string $text): bool
    {
        if (!$text) return false;
        $lower = strtolower($text);
        if (str_contains($lower, 'image input')) return true;
        if (str_contains($lower, 'image_url')) return true;
        if (str_contains($lower, 'input_image')) return true;
        if (str_contains($lower, 'this model')) return true;
        if (str_contains($lower, 'unsupported')) return true;
        return false;
    }
}