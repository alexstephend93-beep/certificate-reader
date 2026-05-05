<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;

class ApiTesterController extends Controller
{
    public function index()
    {
        return view('api-tester.index');
    }

    public function send(Request $request)
    {
        $validated = $request->validate([
            'url'              => ['required', 'url', 'max:2048'],
            'method'           => ['required', 'in:GET,POST,PUT,PATCH,DELETE,HEAD,OPTIONS'],
            'body_type'        => ['nullable', 'in:none,json,form,raw'],
            'body'             => ['nullable', 'string', 'max:500000'],
            'timeout'          => ['nullable', 'integer', 'min:1', 'max:120'],
            'follow_redirects' => ['nullable', 'boolean'],
            'headers'          => ['nullable', 'array'],
            'headers.*.key'    => ['nullable', 'string', 'max:200'],
            'headers.*.value'  => ['nullable', 'string', 'max:1000'],
        ]);

        $method          = strtoupper($validated['method']);
        $url             = $validated['url'];
        $timeout         = $validated['timeout'] ?? 30;
        $followRedirects = isset($validated['follow_redirects']) ? (bool)$validated['follow_redirects'] : true;
        $bodyType        = $validated['body_type'] ?? 'none';
        $rawBody         = $validated['body'] ?? '';

        // Build headers array
        $headersArray = [];
        if (!empty($validated['headers'])) {
            foreach ($validated['headers'] as $h) {
                $k = trim($h['key'] ?? '');
                $v = trim($h['value'] ?? '');
                if ($k !== '') {
                    $headersArray[$k] = $v;
                }
            }
        }

        // Auth header from request
        $authType = $request->input('auth_type', 'none');
        if ($authType === 'bearer') {
            $token = $request->input('auth_token', '');
            if ($token) {
                $headersArray['Authorization'] = 'Bearer ' . $token;
            }
        } elseif ($authType === 'basic') {
            $user = $request->input('auth_username', '');
            $pass = $request->input('auth_password', '');
            if ($user) {
                $headersArray['Authorization'] = 'Basic ' . base64_encode($user . ':' . $pass);
            }
        }

        // Build Guzzle options
        $options = [
            'timeout'         => $timeout,
            'connect_timeout' => 10,
            'allow_redirects' => $followRedirects ? ['max' => 10, 'track_redirects' => true] : false,
            'verify'          => false,
            'http_errors'     => false,
            'headers'         => $headersArray,
        ];

        // Attach body for non-safe methods
        if (!in_array($method, ['GET', 'HEAD', 'OPTIONS'])) {
            if ($bodyType === 'json') {
                $options['body'] = $rawBody;
                if (!isset($headersArray['Content-Type'])) {
                    $options['headers']['Content-Type'] = 'application/json';
                }
            } elseif ($bodyType === 'form') {
                parse_str($rawBody, $formData);
                $options['form_params'] = $formData;
            } elseif ($bodyType === 'raw') {
                $options['body'] = $rawBody;
            }
        }

        $client = new Client();
        $start  = microtime(true);

        try {
            $response = $client->request($method, $url, $options);
            $elapsed  = round((microtime(true) - $start) * 1000, 2);

            $statusCode     = $response->getStatusCode();
            $reasonPhrase   = $response->getReasonPhrase();
            $responseBody   = (string) $response->getBody();
            $responseHeaders = [];
            foreach ($response->getHeaders() as $name => $values) {
                $responseHeaders[$name] = implode(', ', $values);
            }
            $sizeBytes = strlen($responseBody);

            // Try to pretty-print JSON
            $prettyBody = $responseBody;
            $contentType = $response->getHeaderLine('Content-Type');
            $isJson = str_contains($contentType, 'application/json') || str_contains($contentType, 'text/json');
            if (!$isJson) {
                $decoded = json_decode($responseBody, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $isJson = true;
                }
            }
            if ($isJson && !empty($responseBody)) {
                $decoded = json_decode($responseBody, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $prettyBody = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            }

            // Build cURL command
            $curlCmd = $this->buildCurlCommand($method, $url, $headersArray, $bodyType, $rawBody, $followRedirects);

            return response()->json([
                'success'          => true,
                'status_code'      => $statusCode,
                'reason_phrase'    => $reasonPhrase,
                'elapsed_ms'       => $elapsed,
                'size_bytes'       => $sizeBytes,
                'body_raw'         => $responseBody,
                'body_pretty'      => $prettyBody,
                'is_json'          => $isJson,
                'headers'          => $responseHeaders,
                'curl_command'     => $curlCmd,
                'effective_url'    => $url,
            ]);
        } catch (ConnectException $e) {
            $elapsed = round((microtime(true) - $start) * 1000, 2);
            return response()->json([
                'success'     => false,
                'error'       => 'Connection failed: ' . $e->getMessage(),
                'elapsed_ms'  => $elapsed,
            ], 422);
        } catch (RequestException $e) {
            $elapsed = round((microtime(true) - $start) * 1000, 2);
            return response()->json([
                'success'    => false,
                'error'      => 'Request error: ' . $e->getMessage(),
                'elapsed_ms' => $elapsed,
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => 'Unexpected error: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function buildCurlCommand(string $method, string $url, array $headers, string $bodyType, string $body, bool $followRedirects): string
    {
        $parts = ['curl'];
        if ($method !== 'GET') {
            $parts[] = "-X $method";
        }
        if ($followRedirects) {
            $parts[] = '-L';
        }
        foreach ($headers as $k => $v) {
            $parts[] = "-H '" . addslashes($k) . ': ' . addslashes($v) . "'";
        }
        if (!in_array($method, ['GET', 'HEAD', 'OPTIONS']) && !empty($body)) {
            if ($bodyType === 'json') {
                $parts[] = "--data '" . addslashes($body) . "'";
            } elseif ($bodyType === 'form') {
                $parts[] = "--data-urlencode '" . addslashes($body) . "'";
            } else {
                $parts[] = "--data-raw '" . addslashes($body) . "'";
            }
        }
        $parts[] = "'" . $url . "'";
        return implode(" \\\n  ", $parts);
    }
}
