<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AIChatController extends Controller
{
    /** Provider that produced the last successful answer (useful for debugging). */
    private ?string $lastProvider = null;

    private const MAX_TOKENS = 1200;

    private const SYSTEM_PROMPT = 'You are the AI assistant embedded in "Certificate Tools", a '
        . 'security-focused toolkit. Help the user with SSL/TLS certificates, certificate chains, '
        . 'CSRs, private/public keys, JWT, hashing, HMAC, Base64, SSH, API testing, and general '
        . 'Linux/DevOps questions. Answer the actual question first, keep answers concise, and put '
        . 'shell/PHP commands in fenced code blocks. If a question is unrelated to security, still '
        . 'answer it helpfully and accurately.';

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
        
        // Ask the configured AI providers (see providerChain())
        $response = $this->getRealAIResponse($history);
        
        // If real AI fails, use intelligent fallback
        if (!$response) {
            $response = $this->getIntelligentFallbackResponse($message);
        }
        
        // Only surface the "images are unsupported" error when the user's own message
        // actually referenced an attachment; otherwise a genuine answer would be lost.
        if ($response && $this->isApiImageInputError($response) && $this->messageReferencesFile($message)) {
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
            'provider' => $this->lastProvider,
            'conversation_id' => $conversationId,
            'timestamp' => now()->toIso8601String()
        ]);
    }
    
    private function getRealAIResponse($history)
    {
        // Provider chain: configured/keyed providers first (fast + reliable),
        // keyless providers last because they are rate limited by upstream.
        $budgetSeconds = (int) config('services.ai.budget', 90);
        $deadline = microtime(true) + $budgetSeconds;

        foreach ($this->providerChain($history, $deadline) as $providerName => $call) {
            if (microtime(true) >= $deadline) {
                Log::warning('AI provider budget exhausted, skipping remaining providers', [
                    'provider' => $providerName,
                ]);
                break;
            }

            $startedAt = microtime(true);

            try {
                $content = $call($deadline);

                if (is_string($content) && trim($content) !== '') {
                    $this->lastProvider = $providerName;
                    Log::info('AI response served', [
                        'provider' => $providerName,
                        'latency_ms' => (int) ((microtime(true) - $startedAt) * 1000),
                    ]);

                    return $content;
                }
            } catch (\Throwable $e) {
                Log::warning("AI provider [{$providerName}] failed: " . $e->getMessage());
            }
        }

        return null;
    }

    /**
     * Ordered provider chain: name => callable returning the answer text (or null).
     * Keyed providers come first because the keyless ones are rate limited upstream.
     *
     * @return array<string, callable(): ?string>
     */
    private function providerChain(array $history, ?float $deadline = null): array
    {
        $chain = [];

        if (config('services.gemini.key')) {
            $chain['gemini'] = fn () => $this->callGemini($history, $deadline);
        }

        if (config('services.github.token')) {
            $chain['github-models'] = fn () => $this->callGithubModels($history, $deadline);
        }

        if (config('services.ai.api_key')) {
            $chain['openrouter'] = fn () => $this->callOpenRouter($history, $deadline);
        }

        // Keyless last resort.
        $chain['pollinations'] = fn () => $this->callPollinations($history, $deadline);

        return $chain;
    }

    /**
     * Per-request timeout, clamped to whatever is left of the total budget so a
     * single slow provider cannot consume the whole allowance.
     */
    private function timeout(?float $deadline = null): int
    {
        $configured = max(5, (int) config('services.ai.timeout', 45));

        if ($deadline === null) {
            return $configured;
        }

        return (int) max(5, min($configured, (int) ceil($deadline - microtime(true))));
    }

    /**
     * Google Gemini "generateContent" API. Tries the configured model, then any
     * fallbacks (Google retires model names regularly).
     */
    private function callGemini(array $history, ?float $deadline = null): ?string
    {
        $key = (string) config('services.gemini.key');

        $models = array_values(array_unique(array_filter(array_merge(
            [(string) config('services.gemini.model')],
            (array) config('services.gemini.fallback_models', [])
        ))));

        [$systemInstruction, $contents] = $this->toGeminiPayload($history);

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => self::MAX_TOKENS,
                // Disable "thinking" tokens so answers stay fast and complete.
                'thinkingConfig' => ['thinkingBudget' => 0],
            ],
        ];

        if ($systemInstruction !== '') {
            $payload['systemInstruction'] = ['parts' => [['text' => $systemInstruction]]];
        }

        $baseUrl = rtrim((string) config('services.gemini.base_url'), '/');
        $withThinkingConfig = true;

        foreach ($models as $model) {
            if ($deadline !== null && microtime(true) >= $deadline) {
                Log::warning('Gemini budget exhausted before trying model', ['model' => $model]);
                break;
            }

            $url = $baseUrl . '/v1beta/models/' . $model . ':generateContent';

            try {
                $response = $this->postGemini($url, $payload, $withThinkingConfig, $key, $deadline);

                // Some models (e.g. the *-lite ones) reject thinkingConfig outright.
                if ($response->status() === 400
                    && $withThinkingConfig
                    && str_contains(strtolower($response->body()), 'thinking')
                ) {
                    Log::info('Gemini rejected thinkingConfig, retrying without it', ['model' => $model]);
                    $withThinkingConfig = false;
                    $response = $this->postGemini($url, $payload, false, $key, $deadline);
                }

                if ($response->successful()) {
                    $text = $this->extractGeminiText($response->json());

                    if ($text !== null) {
                        return $text;
                    }

                    Log::warning('Gemini returned an empty candidate', ['model' => $model]);
                    continue;
                }

                Log::warning('Gemini request failed', [
                    'model' => $model,
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 300),
                ]);

                // Only bad credentials are worth aborting for; other errors (503 high
                // demand, 429 quota, 404 retired model) may be model specific, so keep
                // trying the remaining fallback models.
                if (in_array($response->status(), [401, 403], true)) {
                    break;
                }
            } catch (\Throwable $e) {
                Log::warning('Gemini request threw', ['model' => $model, 'error' => $e->getMessage()]);
            }
        }

        return null;
    }

    /**
     * Send a Gemini request, optionally including the thinkingConfig extension.
     */
    private function postGemini(string $url, array $payload, bool $withThinkingConfig, string $key, ?float $deadline)
    {
        if (!$withThinkingConfig) {
            unset($payload['generationConfig']['thinkingConfig']);
        }

        return Http::timeout($this->timeout($deadline))
            ->acceptJson()
            ->withHeaders(['x-goog-api-key' => $key])
            ->post($url, $payload);
    }

    /**
     * GitHub Models - OpenAI compatible endpoint authenticated with GITHUB_TOKEN.
     */
    private function callGithubModels(array $history, ?float $deadline = null): ?string
    {
        $response = Http::timeout($this->timeout($deadline))
            ->acceptJson()
            ->withToken((string) config('services.github.token'))
            ->post((string) config('services.github.base_url'), [
                'model' => (string) config('services.github.model'),
                'messages' => $this->toOpenAiMessages($history),
                'temperature' => 0.7,
                'max_tokens' => self::MAX_TOKENS,
            ]);

        return $this->extractOpenAiContent($response, 'GitHub Models');
    }
    
    /**
     * OpenRouter - only used when AI_API_KEY is configured.
     */
    private function callOpenRouter(array $history, ?float $deadline = null): ?string
    {
        $response = Http::timeout($this->timeout($deadline))
            ->acceptJson()
            ->withHeaders([
                'Authorization' => 'Bearer ' . config('services.ai.api_key'),
                'HTTP-Referer' => url('/'),
                'X-Title' => config('app.name', 'Certificate Tools'),
            ])
            ->post((string) config('services.openrouter.base_url'), [
                'model' => (string) config('services.openrouter.model'),
                'messages' => $this->toOpenAiMessages($history),
                'temperature' => 0.7,
                'max_tokens' => self::MAX_TOKENS,
            ]);

        return $this->extractOpenAiContent($response, 'OpenRouter');
    }

    /**
     * Pollinations.ai - keyless and rate limited (402/429 when throttled),
     * so it retries once after a short back-off.
     */
    private function callPollinations(array $history, ?float $deadline = null): ?string
    {
        $url = (string) config('services.pollinations.base_url');

        $payload = [
            'model' => (string) config('services.pollinations.model'),
            'messages' => $this->toOpenAiMessages($history),
            'temperature' => 0.7,
            'max_tokens' => self::MAX_TOKENS,
        ];

        foreach ([0, 1] as $attempt) {
            $response = Http::timeout(min(20, $this->timeout($deadline)))
                ->acceptJson()
                ->post($url, $payload);

            if ($response->successful()) {
                return $this->extractOpenAiContent($response, 'Pollinations');
            }

            Log::warning('Pollinations request failed', [
                'status' => $response->status(),
                'attempt' => $attempt + 1,
                'body' => mb_substr($response->body(), 0, 200),
            ]);

            if (!in_array($response->status(), [402, 429, 503], true)) {
                return null;
            }

            // Throttled: back off briefly before the single retry, if time allows.
            if ($attempt === 0 && ($deadline === null || microtime(true) + 1.5 < $deadline)) {
                usleep(1500000);
            }
        }

        return null;
    }

    /**
     * Convert the stored OpenAI-style history into Gemini's contents/systemInstruction.
     *
     * @return array{0: string, 1: array<int, array<string, mixed>>}
     */
    private function toGeminiPayload(array $history): array
    {
        $system = '';
        $contents = [];

        foreach (array_slice($history, -20) as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $role = $entry['role'] ?? 'user';
            $text = trim((string) ($entry['content'] ?? ''));

            if ($text === '') {
                continue;
            }

            if ($role === 'system') {
                $system = $system === '' ? $text : $system . "\n\n" . $text;
                continue;
            }

            $geminiRole = $role === 'assistant' ? 'model' : 'user';
            $lastIndex = count($contents) - 1;

            // Gemini rejects two turns with the same role in a row.
            if ($lastIndex >= 0 && $contents[$lastIndex]['role'] === $geminiRole) {
                $contents[$lastIndex]['parts'][] = ['text' => $text];
            } else {
                $contents[] = ['role' => $geminiRole, 'parts' => [['text' => $text]]];
            }
        }

        // Gemini requires a non-empty conversation that ends on a user turn.
        if ($contents === [] || end($contents)['role'] !== 'user') {
            $contents[] = ['role' => 'user', 'parts' => [['text' => 'Hello']]];
        }

        return [$system, $contents];
    }

    /**
     * @return array<int, array{role: string, content: string}>
     */
    private function toOpenAiMessages(array $history): array
    {
        $messages = [['role' => 'system', 'content' => self::SYSTEM_PROMPT]];

        foreach (array_slice($history, -20) as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $role = $entry['role'] ?? 'user';
            $content = trim((string) ($entry['content'] ?? ''));

            if ($content === '' || !in_array($role, ['user', 'assistant', 'system'], true)) {
                continue;
            }

            $messages[] = ['role' => $role, 'content' => $content];
        }

        return $messages;
    }

    private function extractGeminiText(?array $data): ?string
    {
        $parts = $data['candidates'][0]['content']['parts'] ?? [];
        $text = '';

        foreach ($parts as $part) {
            // Skip internal "thinking" parts returned by Gemini 3.x models.
            if (!empty($part['thought'])) {
                continue;
            }

            $text .= $part['text'] ?? '';
        }

        $text = trim($text);

        return $text === '' ? null : $text;
    }

    private function extractOpenAiContent($response, string $provider): ?string
    {
        if (!$response->successful()) {
            Log::warning("{$provider} request failed", [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 300),
            ]);

            return null;
        }

        $data = $response->json();
        $content = $data['choices'][0]['message']['content'] ?? null;

        // Some providers return the content as an array of parts.
        if (is_array($content)) {
            $content = implode('', array_map(
                fn ($part) => is_array($part) ? ($part['text'] ?? '') : (string) $part,
                $content
            ));
        }

        $content = trim((string) $content);

        return $content === '' ? null : $content;
    }

    /**
     * Err on the side of caution: only clearly image-related upstream errors are
     * treated as "images unsupported". (The old check also matched the words
     * "this model"/"unsupported", which could swallow perfectly valid answers.)
     */
    private function isApiImageInputError(?string $text): bool
    {
        if (!$text) {
            return false;
        }

        $lower = strtolower($text);

        foreach ([
            'image input',
            'image_url',
            'input_image',
            'image content',
            'invalid image',
            'cannot read image',
            'does not support image',
            'no image support',
        ] as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Does the user's own message reference a file/image attachment?
     */
    private function messageReferencesFile(string $message): bool
    {
        return (bool) preg_match(
            '/\.(png|jpe?g|gif|bmp|webp|svg|ico|tiff?|heic|avif|pdf|docx?|xlsx?|csv|zip|txt)\b|data:image|base64/i',
            $message
        );
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
        
        // Default response: be honest that no live model answered, instead of
        // pretending the (possibly unrelated) question was handled.
        return "⚠️ I couldn't reach any live AI model just now, so this is an offline reply.\n\n"
            . "While online I can answer anything; offline I only know these topics:\n"
            . "• 🔐 **SSL/TLS Certificates** - generate, install, validate\n"
            . "• 🔗 **Certificate chains** - inspect and verify\n"
            . "• 🔑 **JWT tokens** - generate, decode, verify\n"
            . "• 🌐 **API testing** - debug HTTP requests\n"
            . "• 🔒 **Hashing & encryption** - SHA-256, HMAC, AES, Base64\n\n"
            . "Please try again in a moment, or ask one of these, e.g.:\n"
            . "• 'How to generate SSL certificate on Ubuntu?'\n"
            . "• 'How to validate a certificate?'\n"
            . "• 'What is JWT?'\n\n"
            . "_(Admins: check the AI provider keys via `/test-ai` and `storage/logs/laravel.log`.)_";
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
                'provider' => $this->lastProvider,
                'response' => $response
            ]);
        }
        
        return response()->json([
            'success' => false,
            'message' => 'No AI provider responded. Check GEMINI_API_KEY / GITHUB_TOKEN and storage/logs/laravel.log',
            'configured_providers' => array_keys($this->providerChain([])),
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
}