<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\SshConfigParser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\DatabaseCredential;
use phpseclib3\Net\SSH2;
use phpseclib3\Crypt\PublicKeyLoader;
use File;

class SshController extends Controller
{
    protected $sshParser;
    protected $sshConfigPath;
    
    public function __construct(SshConfigParser $sshParser)
    {
        $this->sshParser = $sshParser;
        $this->sshConfigPath = $this->getSshConfigPath();
    }
    
    /**
     * Get SSH servers from PEM files in Documents/SSH directory
     */
    private function getServersFromPemFiles()
    {
        $homeDir = $this->getHomeDirectory();
        $pemDir = $homeDir . '/Documents/SSH';

        \Log::info('SSH PEM Directory Check', [
            'homeDir' => $homeDir,
            'pemDir' => $pemDir,
            'pemDirExists' => is_dir($pemDir),
            'pemDirReadable' => is_readable($pemDir)
        ]);

        if (!is_dir($pemDir)) {
            \Log::error('SSH PEM directory does not exist: ' . $pemDir);
            return [];
        }

        $pemFiles = glob($pemDir . '/*.pem');
        \Log::info('SSH PEM Files Found', [
            'pemFilesCount' => count($pemFiles),
            'pemFiles' => $pemFiles
        ]);

        if (empty($pemFiles)) {
            \Log::info('No PEM files found in directory: ' . $pemDir);
            return [];
        }

        // Prioritized files that should appear at the top (basename without .pem)
        $priorityFiles = [
            'common_prod',
            'uat_oursKey',
            'server_common',
            'zconnect_staging'
        ];

        // Separate prioritized and regular files
        $priorityHosts = [];
        $regularHosts = [];

        foreach ($pemFiles as $pemFile) {
            $filename = basename($pemFile);
            $fileBaseName = pathinfo($filename, PATHINFO_FILENAME);

            // Create host entry with better naming
            $host = [
                'host' => $this->getDisplayNameFromPemFile($filename), // Display name for the UI
                'hostname' => $this->getHostnameFromPemFile($filename),
                'user' => $this->getUserFromPemFile($filename),
                'identity_file' => '~/Documents/SSH/' . $filename,
                'port' => 22,
                'domains' => $this->getDomainsFromPemFile($filename),
                'description' => $this->getDescriptionFromPemFile($filename),
                'pem_file' => $filename,
                'file_basename' => $fileBaseName // Store the original filename for reference
            ];

            // Check if this is a priority file (use basename without .pem)
            if (in_array($fileBaseName, $priorityFiles)) {
                $priorityHosts[] = $host;
            } else {
                $regularHosts[] = $host;
            }
        }

        // Combine with priority files first
        return array_merge($priorityHosts, $regularHosts);
    }

    /**
     * Get display name from PEM filename (for UI titles)
     */
    private function getDisplayNameFromPemFile($filename)
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);

        // Map common filenames to display names
        $displayNameMap = [
            'common_prod' => 'Production Server',
            'uat_oursKey' => 'UAT Server',
            'server_common' => 'Common Server',
            'zconnect_staging' => 'ZConnect Staging',
            // Add more mappings as needed
        ];

        foreach ($displayNameMap as $key => $displayName) {
            if (strpos($name, $key) !== false) {
                return $displayName;
            }
        }

        // Try to create a readable name from the filename
        // e.g., "my_server_key" -> "My Server Key"
        $readableName = ucwords(str_replace(['_', '-'], ' ', $name));
        return $readableName;
    }

    /**
     * Get hostname from PEM filename
     */
    private function getHostnameFromPemFile($filename)
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);

        // Map common filenames to hostnames
        $hostnameMap = [
            'common_prod' => 'prod-server.example.com',
            'uat_oursKey' => 'uat-server.example.com',
            'server_common' => 'common-server.example.com',
            'zconnect_staging' => 'staging.zconnect.com',
            // Add more mappings as needed
        ];

        foreach ($hostnameMap as $key => $hostname) {
            if (strpos($name, $key) !== false) {
                return $hostname;
            }
        }

        // Try to extract from filename patterns
        // e.g., servername_key.pem -> servername
        if (preg_match('/^([^_]+)_/', $name, $matches)) {
            return $matches[1] . '.example.com';
        }

        // Default fallback
        return 'unknown-server.com';
    }

    /**
     * Get user from PEM filename
     */
    private function getUserFromPemFile($filename)
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);

        // Common user patterns
        if (strpos($name, 'ubuntu') !== false || strpos($name, 'aws') !== false) {
            return 'ubuntu';
        }
        if (strpos($name, 'ec2') !== false) {
            return 'ec2-user';
        }
        if (strpos($name, 'centos') !== false || strpos($name, 'redhat') !== false) {
            return 'centos';
        }

        // Default user
        return 'ubuntu';
    }

    /**
     * Get domains from PEM filename
     */
    private function getDomainsFromPemFile($filename)
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);

        // Domain mapping based on filename patterns
        $domainMap = [
            'letzstyle' => ['prod.letzstyle.com', 'www.prod.letzstyle.com'],
            'fashion' => ['fashion.example.com'],
            'ecom' => ['ecom.example.com'],
            // Add more domain mappings as needed
        ];

        $domains = [];
        foreach ($domainMap as $key => $domainList) {
            if (strpos($name, $key) !== false) {
                $domains = array_merge($domains, $domainList);
            }
        }

        return array_unique($domains);
    }

    /**
     * Get description from PEM filename
     */
    private function getDescriptionFromPemFile($filename)
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);

        // Environment-based descriptions
        if (strpos($name, 'prod') !== false) {
            return 'Production Server';
        }
        if (strpos($name, 'uat') !== false || strpos($name, 'staging') !== false) {
            return 'UAT/Staging Server';
        }
        if (strpos($name, 'dev') !== false || strpos($name, 'development') !== false) {
            return 'Development Server';
        }
        if (strpos($name, 'test') !== false) {
            return 'Test Server';
        }

        // Service-based descriptions
        if (strpos($name, 'web') !== false) {
            return 'Web Server';
        }
        if (strpos($name, 'db') !== false || strpos($name, 'database') !== false) {
            return 'Database Server';
        }
        if (strpos($name, 'api') !== false) {
            return 'API Server';
        }

        return 'SSH Server';
    }

    /**
     * Get SSH config file path
     */
    private function getSshConfigPath()
    {
        // Try common SSH config locations
        $possiblePaths = [
            '/home/ubuntu/.ssh/config',
            '/home/ec2-user/.ssh/config',
            '/root/.ssh/config',
            '/home/admin/.ssh/config'
        ];

        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        // Fallback to home directory
        $homeDir = $this->getHomeDirectory();
        return $homeDir . '/.ssh/config';
    }
    
    /**
     * Expand tilde and relative paths to absolute paths
     */
    private function expandPath(string $path): string
    {
        if (empty($path)) {
            return '';
        }

        // Expand tilde to home directory
        if (str_starts_with($path, '~/')) {
            $homeDir = $this->getHomeDirectory();
            $expandedPath = $homeDir . '/' . substr($path, 2);

            // If the file doesn't exist in the web app's home, try common SSH locations
            if (!file_exists($expandedPath)) {
                $possiblePaths = [
                    '/home/ubuntu/' . substr($path, 2), // Common for Ubuntu
                    '/home/ec2-user/' . substr($path, 2), // Common for Amazon Linux
                    '/root/' . substr($path, 2), // Root user
                    '/Users/' . get_current_user() . '/' . substr($path, 2), // macOS
                    storage_path('app/ssh/keys/' . basename(substr($path, 2))), // Web app accessible location
                ];

                foreach ($possiblePaths as $possiblePath) {
                    if (file_exists($possiblePath)) {
                        \Log::info('Using alternative SSH key path: ' . $possiblePath);
                        return $possiblePath;
                    }
                }
            }

            $path = $expandedPath;
        }

        // Resolve relative paths (relative to home directory, not project directory)
        if (!str_starts_with($path, '/')) {
            $homeDir = $this->getHomeDirectory();
            // For web apps, if the path contains Documents/SSH, treat it as a direct filename
            if (strpos($path, 'Documents/SSH/') === 0) {
                $filename = basename($path);
                $storageKeyPath = storage_path('app/ssh/keys/' . $filename);
                if (file_exists($storageKeyPath)) {
                    $path = $storageKeyPath;
                } else {
                    $path = $homeDir . '/' . $path;
                }
            } else {
                $path = $homeDir . '/' . $path;
            }
        }

        return $path;
    }
    
    /**
     * Test database connection with configurable timeout
     * @param DatabaseCredential $credential
     * @param int $timeoutSeconds Connection timeout in seconds (default 5)
     * @return bool
     */
    private function testDatabaseConnection(DatabaseCredential $credential, int $timeoutSeconds = 5): bool
    {
        try {
            $host = $credential->host;
            $port = $credential->port;
            $database = $credential->database;
            $username = $credential->username;
            $password = $credential->decrypted_password;
            
            if (!$password) {
                return false;
            }
            
            $driver = $credential->connection_name;
            
            switch ($driver) {
                case 'mysql':
                    $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
                    $options = [
                        \PDO::ATTR_TIMEOUT => $timeoutSeconds,
                        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
                    ];
                    break;
                case 'pgsql':
                    $dsn = "pgsql:host={$host};port={$port};dbname={$database}";
                    $options = [
                        \PDO::ATTR_TIMEOUT => $timeoutSeconds,
                        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
                    ];
                    break;
                case 'sqlite':
                    $dsn = "sqlite:{$database}";
                    $options = [
                        \PDO::ATTR_TIMEOUT => $timeoutSeconds,
                        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
                    ];
                    break;
                default:
                    return false;
            }
            
            $pdo = new \PDO($dsn, $username, $password, $options);
            return true;
            
        } catch (\Exception $e) {
            \Log::debug("DB connection test failed for {$credential->name}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get home directory path for SSH operations
     */
    private function getHomeDirectory()
    {
        // For SSH operations, try to get the real user home directory
        // Web apps often have HOME set to web root, but SSH keys are in real user home

        // First try to get the real user home (not web server user)
        if (function_exists('posix_getpwuid')) {
            // Try to get the home of the user who owns the SSH config
            // Check if there's a .ssh directory in common locations
            $possibleHomes = ['/home/ubuntu', '/home/ec2-user', '/root', '/home/admin'];

            foreach ($possibleHomes as $home) {
                if (is_dir($home . '/.ssh')) {
                    return $home;
                }
            }

            // Try the owner of the .ssh config file if it exists
            $sshConfigPath = '/home/ubuntu/.ssh/config'; // Common location
            if (file_exists($sshConfigPath)) {
                $userInfo = posix_getpwuid(fileowner($sshConfigPath));
                if (isset($userInfo['dir'])) {
                    return $userInfo['dir'];
                }
            }
        }

        // Try environment variables
        if (isset($_SERVER['HOME']) && !empty($_SERVER['HOME']) && $_SERVER['HOME'] !== '/var/www/html') {
            return $_SERVER['HOME'];
        }

        if (isset($_ENV['HOME']) && !empty($_ENV['HOME']) && $_ENV['HOME'] !== '/var/www/html') {
            return $_ENV['HOME'];
        }

        // Try exec for real user home
        if (function_exists('exec')) {
            $home = trim(exec('echo $HOME 2>/dev/null'));
            if (!empty($home) && $home !== '/var/www/html') {
                return $home;
            }

            // Try to get home of the user running the script
            $home = trim(exec('eval echo ~$USER 2>/dev/null'));
            if (!empty($home) && $home !== '/var/www/html') {
                return $home;
            }
        }

        // For web applications, prefer storage path for SSH keys
        $storagePath = storage_path('app/ssh');
        if (is_dir($storagePath)) {
            return $storagePath;
        }

        // Last resort - look for .ssh directory in common locations
        $commonHomes = ['/home/ubuntu', '/home/ec2-user', '/home/admin', '/root'];
        foreach ($commonHomes as $home) {
            if (is_dir($home)) {
                return $home;
            }
        }

        // Final fallback - use storage path
        return storage_path('app/ssh');
    }


    public function debugKeys()
    {
        $homeDir = $this->getHomeDirectory();
        $sshDir = $homeDir . '/Documents/SSH';
        
        $keys = [];
        if (is_dir($sshDir)) {
            $files = scandir($sshDir);
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..' && str_ends_with($file, '.pem')) {
                    $fullPath = $sshDir . '/' . $file;
                    $keys[] = [
                        'filename' => $file,
                        'path' => $fullPath,
                        'exists' => file_exists($fullPath),
                        'permissions' => substr(sprintf('%o', fileperms($fullPath)), -4),
                        'size' => filesize($fullPath)
                    ];
                }
            }
        }
        
        return response()->json([
            'home_directory' => $homeDir,
            'ssh_directory' => $sshDir,
            'directory_exists' => is_dir($sshDir),
            'keys_found' => $keys,
            'total_keys' => count($keys)
        ]);
    }

    /**
     * Parse SSH config file and extract hosts with domains from comments
     */
    private function parseSshConfigWithDomains()
    {
        if (!file_exists($this->sshConfigPath)) {
            return [];
        }
        
        $content = file_get_contents($this->sshConfigPath);
        $lines = explode("\n", $content);
        
        $hosts = [];
        $currentHost = null;
        
        foreach ($lines as $line) {
            $trimmedLine = trim($line);
            
            // Skip empty lines
            if (empty($trimmedLine)) {
                continue;
            }
            
            // New Host entry
            if (preg_match('/^Host\s+(.+)$/i', $trimmedLine, $matches)) {
                // Save previous host
                if ($currentHost !== null) {
                    // Build SSH command
                    $currentHost['ssh_command'] = $this->buildSshCommand($currentHost);
                    $hosts[] = $currentHost;
                }
                
                // Start new host
                $currentHost = [
                    'host' => trim($matches[1]),
                    'hostname' => '',
                    'user' => '',
                    'identity_file' => '',
                    'port' => 22,
                    'domains' => [],
                    'description' => '',
                    'ssh_command' => ''
                ];
            }
            // Parse HostName
            elseif ($currentHost && preg_match('/^HostName\s+(.+)$/i', $trimmedLine, $matches)) {
                $currentHost['hostname'] = trim($matches[1]);
            }
            // Parse User
            elseif ($currentHost && preg_match('/^User\s+(.+)$/i', $trimmedLine, $matches)) {
                $currentHost['user'] = trim($matches[1]);
            }
            // Parse IdentityFile
            elseif ($currentHost && preg_match('/^IdentityFile\s+(.+)$/i', $trimmedLine, $matches)) {
                $currentHost['identity_file'] = trim($matches[1]);
            }
            // Parse Port
            elseif ($currentHost && preg_match('/^Port\s+(\d+)$/i', $trimmedLine, $matches)) {
                $currentHost['port'] = (int)$matches[1];
            }
            // Parse #Domain comment (exact match)
            elseif ($currentHost && preg_match('/^#Domain\s+(.+)$/i', $trimmedLine, $matches)) {
                $domain = trim($matches[1]);
                $domain = preg_replace('#^https?://#', '', $domain);
                $domain = rtrim($domain, '/');
                if (!empty($domain)) {
                    $currentHost['domains'][] = $domain;
                }
            }
            // Parse # Domain comment (with space after #)
            elseif ($currentHost && preg_match('/^#\s*Domain\s+(.+)$/i', $trimmedLine, $matches)) {
                $domain = trim($matches[1]);
                $domain = preg_replace('#^https?://#', '', $domain);
                $domain = rtrim($domain, '/');
                if (!empty($domain)) {
                    $currentHost['domains'][] = $domain;
                }
            }
            // Parse description (any other comment that's not #Domain)
            elseif ($currentHost && preg_match('/^#\s*(.+)$/', $trimmedLine, $matches)) {
                if (!preg_match('/^#\s*Domain/i', $trimmedLine) && empty($currentHost['description'])) {
                    $currentHost['description'] = trim($matches[1]);
                }
            }
        }
        
        // Add the last host
        if ($currentHost !== null) {
            $currentHost['ssh_command'] = $this->buildSshCommand($currentHost);
            $hosts[] = $currentHost;
        }
        
        return $hosts;
    }
    
    /**
     * Build SSH command from host configuration
     */
    private function buildSshCommand($host)
    {
        $command = "ssh {$host['host']}";
        
        if (!empty($host['port']) && $host['port'] != 22) {
            $command .= " -p {$host['port']}";
        }
        
        if (!empty($host['identity_file'])) {
            $command .= " -i {$host['identity_file']}";
        }
        
        if (!empty($host['user'])) {
            $command .= " -l {$host['user']}";
        }
        
        return $command;
    }
    
    /**
     * Write hosts back to SSH config file
     */
    private function writeSshConfig($hosts)
    {
        $content = "# SSH Config File - Generated by SSH Manager\n";
        $content .= "# Created: " . date('Y-m-d H:i:s') . "\n";
        $content .= "# ============================================\n\n";

        // Filter out invalid hosts (global configs and empty entries)
        $validHosts = array_filter($hosts, function($host) {
            // Skip global configuration "Host *"
            if ($host['host'] === '*') {
                return false;
    }

            return true;
        });

        foreach ($validHosts as $host) {
            $content .= "Host {$host['host']}\n";

            // Only write non-empty values
            if (!empty($host['hostname'])) {
                $content .= "    HostName {$host['hostname']}\n";
            }
            if (!empty($host['user'])) {
                $content .= "    User {$host['user']}\n";
            }
            if (!empty($host['identity_file'])) {
                $content .= "    IdentityFile {$host['identity_file']}\n";
            }

            if (!empty($host['port']) && $host['port'] != 22) {
                $content .= "    Port {$host['port']}\n";
            }

            // Always add these for every host
            $content .= "    IdentitiesOnly yes\n";
            $content .= "    PreferredAuthentications publickey\n";
            $content .= "    PubkeyAuthentication yes\n";
            $content .= "    PasswordAuthentication no\n";
            $content .= "    PubkeyAcceptedKeyTypes +ssh-rsa\n";
            $content .= "    HostKeyAlgorithms +ssh-rsa\n";
            $content .= "    ServerAliveInterval 60\n";
            $content .= "    ServerAliveCountMax 3\n";
            $content .= "    TCPKeepAlive yes\n";
            $content .= "    Compression yes\n";
            $content .= "    StrictHostKeyChecking accept-new\n";
            $content .= "    ConnectTimeout 15\n";
            $content .= "    ConnectionAttempts 2\n";

            // Add domains as comments
            if (!empty($host['domains'])) {
                foreach ($host['domains'] as $domain) {
                    $content .= "    #Domain https://{$domain}\n";
                }
            }

            // Add description if exists
            if (!empty($host['description'])) {
                $content .= "    # {$host['description']}\n";
            }

            $content .= "\n";
        }
        
        // Make directory and file temporarily writable
        $sshDir = dirname($this->sshConfigPath);
        if (is_dir($sshDir)) {
            chmod($sshDir, 0755);
        }

        if (file_exists($this->sshConfigPath)) {
            chmod($this->sshConfigPath, 0644);
        }

        file_put_contents($this->sshConfigPath, $content);

        // Restore secure permissions
        chmod($this->sshConfigPath, 0600);
        chmod($sshDir, 0700);
        
        return true;
    }
    
    /**
     * Display SSH servers index page
     */
    public function index()
    {
        return view('ssh.index');
    }
    
    /**
     * Get all servers as JSON (for AJAX loading)
     */
    public function listServers(Request $request): JsonResponse
    {
        try {
            // Get SSH servers from SSH config file
            $hosts = $this->parseSshConfigWithDomains();
            $connectionHistory = Cache::get('ssh_connection_history', []);
            $favorites = Cache::get('ssh_favorite_servers', []);

            foreach ($hosts as &$host) {
                $host['last_connected'] = $connectionHistory[$host['host']] ?? null;
                $host['is_favorite'] = in_array($host['host'], $favorites);

                // Add key status
                $keyPath = $host['identity_file'] ?? '';
                $host['key_exists'] = false;
                if (!empty($keyPath)) {
                    $fullPath = $this->expandPath($keyPath);
                    $host['key_exists'] = file_exists($fullPath);
                }
            }

            $totalServers = count($hosts);
            $validKeys = count(array_filter($hosts, function($h) {
                return !empty($h['identity_file']) && file_exists($this->expandPath($h['identity_file']));
            }));

            // For large configs, limit but allow more
            $maxHosts = 500; // Allow up to 500 hosts
            $limitedHosts = array_slice($hosts, 0, $maxHosts);
            $hasMore = count($hosts) > $maxHosts;

            return response()->json([
                'success' => true,
                'hosts' => $limitedHosts,
                'totalServers' => $totalServers,
                'validKeys' => $validKeys,
                'configPath' => $this->sshConfigPath,
                'hasMore' => $hasMore,
                'shownCount' => count($limitedHosts)
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in listServers: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load servers: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Add new host
     */
    public function addHost(Request $request)
    {
        // Check if this is an AJAX/JSON request
        $isJsonRequest = $request->expectsJson() ||
                        $request->header('Accept') === 'application/json' ||
                        $request->header('Content-Type') === 'application/json' ||
                        $request->ajax();

        try {
            $request->validate([
                'host' => 'required|string|max:255',
                'hostname' => 'required|string|max:255',
                'user' => 'required|string|max:255',
                'identity_file' => 'required|string|max:500',
                'port' => 'nullable|integer|min:1|max:65535',
                'description' => 'nullable|string|max:500',
                'domains' => 'nullable|array',
            ]);

            $hosts = $this->parseSshConfigWithDomains();

            // For adding new servers, we allow any valid identity file path
            // The file doesn't need to exist yet - it will be used when connecting
            if (empty($request->identity_file)) {
                $errorMsg = 'Identity file is required';
                if ($isJsonRequest) {
                    return response()->json([
                        'success' => false,
                        'message' => $errorMsg
                    ]);
                }
                return redirect()->route('ssh.index')->with('error', $errorMsg);
            }

            // Add the new host to the hosts array
            $newHost = [
                'host' => $request->host,
                'hostname' => $request->hostname,
                'user' => $request->user,
                'identity_file' => $request->identity_file,
                'port' => $request->port ?? 22,
                'domains' => array_filter($request->domains ?? []),
                'description' => $request->description ?? ''
            ];

            // Add the new host to the existing hosts array
            $hosts[] = $newHost;

            // Write the updated hosts array to the SSH config file
            $this->writeSshConfig($hosts);

            $successMsg = 'Server added successfully!';
            if ($isJsonRequest) {
                return response()->json([
                    'success' => true,
                    'message' => $successMsg
                ]);
            }

            return redirect()->route('ssh.index')->with('success', $successMsg);

        } catch (\Illuminate\Validation\ValidationException $e) {
            $errors = $e->errors();
            $errorMsg = 'Validation failed: ' . implode(', ', array_map(function($error) {
                return implode(', ', $error);
            }, $errors));

            if ($isJsonRequest) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMsg,
                    'errors' => $errors
                ], 422);
            }

            return redirect()->route('ssh.index')->with('error', $errorMsg);

        } catch (\Exception $e) {
            $errorMsg = 'Error adding server: ' . $e->getMessage();

            if ($isJsonRequest) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMsg
                ], 500);
            }

            return redirect()->route('ssh.index')->with('error', $errorMsg);
        }
    }
    
    /**
     * Update host
     */
    public function updateHost(Request $request, $originalHost)
    {
        // Check if this is an AJAX/JSON request
        $isJsonRequest = $request->expectsJson() ||
                        $request->header('Accept') === 'application/json' ||
                        $request->header('Content-Type') === 'application/json' ||
                        $request->ajax();

        try {
            $request->validate([
                'host' => 'required|string|max:255',
                'hostname' => 'required|string|max:255',
                'user' => 'required|string|max:255',
                'identity_file' => 'required|string|max:500',
                'port' => 'nullable|integer|min:1|max:65535',
                'description' => 'nullable|string|max:500',
                'domains' => 'nullable|array',
            ]);

            $hosts = $this->parseSshConfigWithDomains();
            $updated = false;

            foreach ($hosts as $key => $host) {
                if ($host['host'] === $originalHost) {
                    $hosts[$key] = [
                        'host' => $request->host,
                        'hostname' => $request->hostname,
                        'user' => $request->user,
                        'identity_file' => $request->identity_file,
                        'port' => $request->port ?? 22,
                        'domains' => array_filter($request->domains ?? []),
                        'description' => $request->description ?? ''
                    ];
                    $updated = true;
                    break;
                }
            }

            if ($updated) {
                $this->writeSshConfig($hosts);

                $successMsg = 'Server updated successfully!';
                if ($isJsonRequest) {
                    return response()->json([
                        'success' => true,
                        'message' => $successMsg
                    ]);
                }

                return redirect()->route('ssh.index')->with('success', $successMsg);
            }

            $errorMsg = 'Server not found!';
            if ($isJsonRequest) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMsg
                ], 404);
            }

            return redirect()->route('ssh.index')->with('error', $errorMsg);

        } catch (\Illuminate\Validation\ValidationException $e) {
            $errors = $e->errors();
            $errorMsg = 'Validation failed: ' . implode(', ', array_map(function($error) {
                return implode(', ', $error);
            }, $errors));

            if ($isJsonRequest) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMsg,
                    'errors' => $errors
                ], 422);
            }

            return redirect()->route('ssh.index')->with('error', $errorMsg);

        } catch (\Exception $e) {
            $errorMsg = 'Error updating server: ' . $e->getMessage();

            if ($isJsonRequest) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMsg
                ], 500);
            }

            return redirect()->route('ssh.index')->with('error', $errorMsg);
        }
    }
    
    /**
     * Delete server
     */
    public function deleteServer($host)
    {
        $hosts = $this->parseSshConfigWithDomains();
        $found = false;
        
        foreach ($hosts as $key => $h) {
            if ($h['host'] === $host) {
                unset($hosts[$key]);
                $found = true;
                break;
            }
        }
        
        if ($found) {
            // Re-index array
            $hosts = array_values($hosts);
            $this->writeSshConfig($hosts);
            return response()->json(['success' => true, 'message' => 'Server deleted successfully']);
        }
        
        return response()->json(['success' => false, 'message' => 'Server not found'], 404);
    }
    
    /**
     * Get single server data for editing
     */
    public function getServer($host)
    {
        $hosts = $this->parseSshConfigWithDomains();
        
        foreach ($hosts as $h) {
            if ($h['host'] === $host) {
                return response()->json([
                    'success' => true,
                    'server' => $h
                ]);
            }
        }
        
        return response()->json(['success' => false, 'message' => 'Server not found']);
    }
    
    /**
     * Test SSH connection with PEM key
     */
    public function testConnectionWithKey(Request $request)
    {
        $request->validate([
            'hostname' => 'required|string',
            'port' => 'nullable|integer|min:1|max:65535',
            'username' => 'required|string',
            'identity_file' => 'required|string'
        ]);

        try {
            $port = $request->input('port', 22);
            $identityFile = $this->expandPath($request->identity_file);

            if (!file_exists($identityFile) || !is_readable($identityFile)) {
                return response()->json([
                    'success' => false,
                    'message' => 'PEM key file not found or not readable at: ' . $identityFile
                ]);
            }

            // Use shell-based SSH test
            $sshCommand = sprintf(
                'ssh -i %s -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o ConnectTimeout=10 -p %d %s@%s %s',
                escapeshellarg($identityFile),
                $port,
                escapeshellarg($request->username),
                escapeshellarg($request->hostname),
                escapeshellarg('echo "SSH connection successful"')
            );

            exec($sshCommand . ' 2>&1', $output, $returnCode);

            if ($returnCode !== 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'SSH connection failed: ' . implode(' ', $output)
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'SSH connection successful!',
                'output' => trim(implode("\n", $output))
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection error: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get SSH command for a host
     */
    public function getSshCommand($host)
    {
        $hosts = $this->parseSshConfigWithDomains();
        
        foreach ($hosts as $h) {
            if ($h['host'] === $host) {
                return response()->json([
                    'success' => true,
                    'command' => $h['ssh_command']
                ]);
            }
        }
        
        return response()->json(['success' => false, 'message' => 'Host not found']);
    }
    
    /**
     * Record connection history
     */
    public function recordConnection(Request $request)
    {
        $request->validate([
            'host' => 'required|string'
        ]);
        
        $history = Cache::get('ssh_connection_history', []);
        $history[$request->host] = now()->toDateTimeString();
        Cache::put('ssh_connection_history', $history, now()->addDays(30));
        
        return response()->json(['success' => true]);
    }
    
    /**
     * List projects in /var/www directory
     */
    public function listProjects(Request $request)
    {
        $request->validate([
            'host' => 'required|string',
            'hostname' => 'required|string',
            'username' => 'required|string',
            'identity_file' => 'required|string',
            'port' => 'nullable|integer'
        ]);

        try {
            $port = $request->input('port', 22);
            $homeDir = $this->getHomeDirectory();
            $identityFile = $this->expandPath($request->identity_file);

            // Debug logging
            \Log::info('SSH List Projects Debug:', [
                'host' => $request->host,
                'hostname' => $request->hostname,
                'username' => $request->username,
                'identity_file_original' => $request->identity_file,
                'identity_file_expanded' => $identityFile,
                'home_directory' => $homeDir,
                'home_candidates' => [
                    'server_home' => $_SERVER['HOME'] ?? 'not set',
                    'env_home' => $_ENV['HOME'] ?? 'not set',
                    'exec_home' => function_exists('exec') ? trim(exec('echo $HOME 2>/dev/null')) : 'exec not available',
                    'current_user_home' => function_exists('posix_getpwuid') ? posix_getpwuid(posix_getuid())['dir'] ?? 'not found' : 'posix not available'
                ],
                'port' => $port,
                'current_user' => get_current_user(),
                'process_user' => posix_getpwuid(posix_getuid())['name'] ?? 'unknown',
                'file_exists' => file_exists($identityFile)
            ]);

            if (!file_exists($identityFile)) {
                \Log::error('SSH Key file not found: ' . $identityFile);
                return response()->json([
                    'success' => false,
                    'message' => 'Identity file not found at: ' . $identityFile
                ]);
            }

            \Log::info('Attempting SSH connection to: ' . $request->hostname . ':' . $port);

            // Use shell-based SSH execution instead of PHP library
            \Log::info('Using shell-based SSH execution for ' . $request->username . '@' . $request->hostname . ':' . $port);

            // Check if identity file exists and is readable
            if (!file_exists($identityFile) || !is_readable($identityFile)) {
                \Log::error('SSH key file not accessible: ' . $identityFile);
                return response()->json([
                    'success' => false,
                    'message' => 'SSH key file not found or not readable: ' . basename($identityFile)
                ]);
            }

            // Build SSH command
            $sshCommand = sprintf(
                'ssh -i %s -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o ConnectTimeout=15 -p %d %s@%s %s',
                escapeshellarg($identityFile),
                $port,
                escapeshellarg($request->username),
                escapeshellarg($request->hostname),
                escapeshellarg('ls -la /var/www/ 2>/dev/null | grep "^d" | awk \'{print $9}\' | grep -v "^\\.$" | grep -v "^\\.\\.$"')
            );

            \Log::info('Executing SSH command: ' . preg_replace('/-i\s+[^\s]+/', '-i [KEY]', $sshCommand));

            exec($sshCommand . ' 2>&1', $output, $returnCode);

            \Log::info('SSH command exit code: ' . $returnCode);
            \Log::info('SSH command output: ' . implode("\n", $output));

            if ($returnCode !== 0) {
                \Log::error('SSH command failed with code: ' . $returnCode);
                return response()->json([
                    'success' => false,
                    'message' => 'SSH connection failed: ' . implode(' ', $output)
                ]);
            }

            $projects = array_filter($output);
            
            return response()->json([
                'success' => true,
                'projects' => array_values($projects)
            ]);
            
        } catch (\Exception $e) {
            \Log::error('SSH Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Connection error: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Test basic network connectivity to a host:port
     */
    public function testConnectivity(Request $request)
    {
        $request->validate([
            'hostname' => 'required|string',
            'port' => 'nullable|integer|min:1|max:65535'
        ]);

        $port = $request->input('port', 22);

        try {
            // Check hostname resolution
            $ip = gethostbyname($request->hostname);
            if ($ip === $request->hostname) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot resolve hostname: ' . $request->hostname
                ]);
            }

            // Try basic connectivity with nc (netcat) if available
            if (function_exists('exec')) {
                $ncCommand = "nc -z -w5 " . escapeshellarg($request->hostname) . " $port 2>&1";
                exec($ncCommand, $output, $returnCode);

                if ($returnCode === 0) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Network connection successful'
                    ]);
                }

                // Fallback to timeout-based check
                $socket = @fsockopen($request->hostname, $port, $errno, $errstr, 10);
                if ($socket) {
                    fclose($socket);
                    return response()->json([
                        'success' => true,
                        'message' => 'Network connection successful'
                    ]);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Cannot connect to ' . $request->hostname . ':' . $port . '. Connection timeout.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connectivity test failed: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Open project in VS Code
     */
    public function openInVSCode(Request $request)
    {
        $request->validate([
            'host' => 'required|string',
            'project_path' => 'required|string'
        ]);

        // Generate the VS Code remote SSH command (opens in new window)
        // Using --new-window and --wait flags for better control
        $vscodeCommand = "code --new-window --wait --remote ssh-remote+{$request->host} {$request->project_path}";

        // Alternative command without wait (faster)
        $vscodeCommandFast = "code --new-window --remote ssh-remote+{$request->host} {$request->project_path}";

        // Check if we can execute VSCode on the server (experimental)
        $canExecuteLocally = $this->canExecuteVSCodeLocally();
        if ($canExecuteLocally) {
            // Try to execute locally on server (for testing/development environments)
            try {
                exec($vscodeCommand . ' > /dev/null 2>&1 &', $output, $returnCode);
                if ($returnCode === 0) {
                    return response()->json([
                        'success' => true,
                        'executed_locally' => true,
                        'message' => 'VSCode opened on server',
                        'command' => $vscodeCommand
                    ]);
                }
            } catch (\Exception $e) {
                // Fall back to showing command
            }
        }

        // SSH command for manual connection
        $sshCommand = "ssh {$request->host}";

        return response()->json([
            'success' => true,
            'command' => $vscodeCommand,
            'command_fast' => $vscodeCommandFast,
            'executed_locally' => false,
            'host' => $request->host,
            'path' => $request->project_path,
            'instructions' => 'Copy and run this command in your terminal to open the project in VS Code.'
        ]);
    }

    /**
     * Get list of SSH key files for dropdown
     */
    public function getSshKeyFiles(Request $request)
    {
        $homeDir = $this->getHomeDirectory();
        $pemDir = $homeDir . '/Documents/SSH';

        $keyFiles = [];

        if (is_dir($pemDir)) {
            $files = glob($pemDir . '/*.pem');
            if (!empty($files)) {
                // Prioritized files that should appear at the top
                $priorityFiles = [
                    'common_prod.pem',
                    'server_common.pem',
                    'uat_oursKey.pem'
                ];

                // Separate prioritized and regular files
                $priorityKeys = [];
                $regularKeys = [];

                foreach ($files as $file) {
                    $filename = basename($file);
                    $filePath = '~/Documents/SSH/' . $filename;

                    $fileInfo = [
                        'filename' => $filename,
                        'path' => $filePath,
                        'exists' => file_exists($this->expandPath($filePath))
                    ];

                    if (in_array($filename, $priorityFiles)) {
                        $priorityKeys[] = $fileInfo;
                    } else {
                        $regularKeys[] = $fileInfo;
                    }
                }

                // Combine with priority files first
                $keyFiles = array_merge($priorityKeys, $regularKeys);
            }
        }

        return response()->json([
            'success' => true,
            'keyFiles' => $keyFiles
        ]);
    }

    /**
     * Check if VSCode can be executed locally on the server
     */
    private function canExecuteVSCodeLocally()
    {
        if (!function_exists('exec')) {
            return false;
        }

        // Check if code command exists
        exec('which code 2>/dev/null', $output, $returnCode);
        if ($returnCode !== 0) {
            return false;
        }

        // Check if we have a display (for GUI applications)
        $display = getenv('DISPLAY');
        if (empty($display)) {
            return false;
        }

        // Only enable in development/testing environments
        // In production, this should be disabled for security
        $env = app()->environment();
        return in_array($env, ['local', 'development', 'testing']);
    }
    
    /**
     * Fetch Apache SSL configuration
     */
    public function getApacheConfig(Request $request)
    {
        $request->validate([
            'host' => 'required|string',
            'hostname' => 'required|string',
            'username' => 'required|string',
            'identity_file' => 'required|string',
            'port' => 'nullable|integer'
        ]);
        
        try {
            $port = $request->input('port', 22);
            $identityFile = $this->expandPath($request->identity_file);
            
            if (!file_exists($identityFile)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Identity file not found: ' . $identityFile
                ]);
            }
            
            // Use shell-based SSH execution
            $configPaths = [
                '/etc/apache2/sites-enabled/000-default-le-ssl.conf',
                '/etc/apache2/sites-available/000-default-le-ssl.conf',
                '/etc/apache2/sites-enabled/default-ssl.conf',
                '/etc/apache2/sites-available/default-ssl.conf'
            ];

            $configContent = '';
            $foundPath = '';

            foreach ($configPaths as $configPath) {
                $sshCommand = sprintf(
                    'ssh -i %s -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o ConnectTimeout=10 -p %d %s@%s %s',
                    escapeshellarg($identityFile),
                    $port,
                    escapeshellarg($request->username),
                    escapeshellarg($request->hostname),
                    escapeshellarg("cat {$configPath} 2>/dev/null")
                );

                exec($sshCommand . ' 2>&1', $output, $returnCode);

                if ($returnCode === 0 && !empty(trim(implode("\n", $output)))) {
                    $rawOutput = implode("\n", $output);
                    // Remove SSH warning messages
                    $configContent = preg_replace('/^Warning:.*$/m', '', $rawOutput);
                    $configContent = trim($configContent);
                    $foundPath = $configPath;
                    break;
                }
            }

            if (empty($configContent)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Apache SSL configuration file not found'
                ]);
            }

            // Parse VirtualHost blocks and extract domain-specific information
            $virtualHosts = $this->parseVirtualHosts($configContent);

            return response()->json([
                'success' => true,
                'content' => $configContent,
                'path' => $foundPath,
                'virtual_hosts' => $virtualHosts
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection error: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Parse VirtualHost blocks from Apache config and extract domain/DocumentRoot pairs
     */
    private function parseVirtualHosts($configContent)
    {
        $virtualHosts = [];
        $lines = explode("\n", $configContent);
        $currentVHost = null;
        $inVHost = false;

        foreach ($lines as $line) {
            $trimmedLine = trim($line);

            // Start of VirtualHost block
            if (preg_match('/<VirtualHost[^>]*>/i', $trimmedLine)) {
                $inVHost = true;
                $currentVHost = [
                    'domains' => [],
                    'document_root' => '',
                    'server_name' => '',
                    'server_aliases' => []
                ];
            }
            // End of VirtualHost block
            elseif (preg_match('/<\/VirtualHost>/i', $trimmedLine) && $inVHost) {
                if ($currentVHost && (!empty($currentVHost['domains']) || !empty($currentVHost['document_root']))) {
                    $virtualHosts[] = $currentVHost;
                }
                $inVHost = false;
                $currentVHost = null;
            }
            // Parse directives within VirtualHost
            elseif ($inVHost && $currentVHost) {
                // ServerName
                if (preg_match('/ServerName\s+(.+)/i', $trimmedLine, $matches)) {
                    $domain = trim($matches[1]);
                    $domain = preg_replace('#^https?://#', '', $domain);
                    $domain = rtrim($domain, '/');
                    $currentVHost['server_name'] = $domain;
                    $currentVHost['domains'][] = $domain;
                }
                // ServerAlias
                elseif (preg_match('/ServerAlias\s+(.+)/i', $trimmedLine, $matches)) {
                    $aliases = preg_split('/\s+/', trim($matches[1]));
                    foreach ($aliases as $alias) {
                        $alias = preg_replace('#^https?://#', '', $alias);
                        $alias = rtrim($alias, '/');
                        if (!empty($alias)) {
                            $currentVHost['server_aliases'][] = $alias;
                            $currentVHost['domains'][] = $alias;
                        }
                    }
                }
                // DocumentRoot
                elseif (preg_match('/DocumentRoot\s+(.+)/i', $trimmedLine, $matches)) {
                    $currentVHost['document_root'] = trim($matches[1]);
                }
            }
        }

        // Handle global DocumentRoot if no VirtualHosts found
        if (empty($virtualHosts)) {
            if (preg_match('/DocumentRoot\s+(.+)/i', $configContent, $matches)) {
                $virtualHosts[] = [
                    'domains' => ['default'],
                    'document_root' => trim($matches[1]),
                    'server_name' => 'default',
                    'server_aliases' => []
                ];
            }
        }

        return $virtualHosts;
    }

    /**
     * Get available PEM keys from config and scan Documents/SSH directory
     */
    public function getAvailableKeys()
    {
        $homeDir = $this->getHomeDirectory();
        $sshDir = $homeDir . '/Documents/SSH';
        
        $availableKeys = [];
        
        // Priority keys
        $priorityKeys = [
            'common_prod.pem',
            'server_common.pem',
            'uat_oursKey.pem',
            'fasion_common.pem'
        ];
        
        if (is_dir($sshDir)) {
            $files = scandir($sshDir);
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..' && str_ends_with($file, '.pem')) {
                    $fullPath = $sshDir . '/' . $file;
                    $relativePath = '~/Documents/SSH/' . $file;
                    
                    $perms = fileperms($fullPath);
                    $permissions = $perms ? substr(sprintf('%o', $perms), -4) : '0000';
                    
                    $group = $this->getKeyGroup($file);
                    
                    $availableKeys[] = [
                        'path' => $relativePath,
                        'filename' => $file,
                        'group' => $group,
                        'exists' => true,
                        'permissions' => $permissions,
                        'is_priority' => in_array($file, $priorityKeys)
                    ];
                }
            }
        }
        
        // Sort keys
        usort($availableKeys, function($a, $b) {
            if ($a['is_priority'] && !$b['is_priority']) return -1;
            if (!$a['is_priority'] && $b['is_priority']) return 1;
            return strcmp($a['filename'], $b['filename']);
        });
        
        return response()->json([
            'success' => true,
            'keys' => $availableKeys,
            'ssh_directory' => $sshDir,
            'home_directory' => $homeDir
        ]);
    }

    private function getKeyGroup($filename)
    {
        $filenameLower = strtolower($filename);
        
        if (strpos($filenameLower, 'server_common') !== false) return 'SERVER';
        if (strpos($filenameLower, 'common_prod') !== false) return 'COMMON';
        if (strpos($filenameLower, 'uat_ourskey') !== false) return 'OURSKEY';
        if (strpos($filenameLower, 'fasion_common') !== false) return 'FASION';
        if (strpos($filenameLower, 'prod') !== false) return 'PRODUCTION';
        if (strpos($filenameLower, 'uat') !== false || strpos($filenameLower, 'stage') !== false) return 'STAGING';
        if (strpos($filenameLower, 'dev') !== false) return 'DEVELOPMENT';
        
        return 'OTHER';
    }

    
    /**
     * Upload PEM key file
     */
    public function uploadPemKey(Request $request)
    {
        $request->validate([
            'pem_file' => 'required|file|mimes:txt,pem|max:1024',
        ]);
        
        try {
            $file = $request->file('pem_file');
            $filename = $file->getClientOriginalName();
            
            if (!str_ends_with($filename, '.pem')) {
                $filename .= '.pem';
            }
            
            $homeDir = $this->getHomeDirectory();
            $sshDir = $homeDir . '/Documents/SSH';
            
            if (!File::exists($sshDir)) {
                File::makeDirectory($sshDir, 0755, true);
            }
            
            $targetPath = $sshDir . '/' . $filename;
            $file->move($sshDir, $filename);
            chmod($targetPath, 0400);
            
            return response()->json([
                'success' => true,
                'message' => 'PEM key uploaded successfully!',
                'filename' => $filename,
                'path' => '~/Documents/SSH/' . $filename,
                'permissions' => '400'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Delete key file
     */
    public function deleteKeyFile(Request $request)
    {
        $request->validate([
            'identity_file' => 'required|string'
        ]);
        
        try {
            $fullPath = $this->expandPath($request->identity_file);
            
            if (file_exists($fullPath)) {
                unlink($fullPath);
                return response()->json([
                    'success' => true,
                    'message' => 'Key file deleted successfully'
                ]);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Key file not found'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting key file: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Toggle favorite server
     */
    public function toggleFavorite(Request $request)
    {
        $request->validate([
            'host' => 'required|string'
        ]);
        
        $favorites = Cache::get('ssh_favorite_servers', []);
        
        if (in_array($request->host, $favorites)) {
            $favorites = array_diff($favorites, [$request->host]);
            $isFavorite = false;
        } else {
            $favorites[] = $request->host;
            $isFavorite = true;
        }
        
        Cache::put('ssh_favorite_servers', $favorites, now()->addDays(30));
        
        return response()->json([
            'success' => true,
            'is_favorite' => $isFavorite,
            'favorites' => $favorites
        ]);
    }
    
    /**
     * Get favorite servers
     */
    public function getFavorites()
    {
        $favorites = Cache::get('ssh_favorite_servers', []);
        return response()->json([
            'success' => true,
            'favorites' => $favorites
        ]);
    }

    /**
     * Export servers to JSON file
     */
    public function exportServers()
    {
        $hosts = $this->parseSshConfigWithDomains();
        
        // Remove internal fields before export
        $exportData = [];
        foreach ($hosts as $host) {
            $exportData[] = [
                'host' => $host['host'],
                'hostname' => $host['hostname'],
                'user' => $host['user'],
                'identity_file' => $host['identity_file'],
                'port' => $host['port'],
                'domains' => $host['domains'],
                'description' => $host['description'] ?? ''
            ];
        }
        
        $json = json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        
        return response($json)
            ->header('Content-Type', 'application/json')
            ->header('Content-Disposition', 'attachment; filename="ssh_servers_' . date('Y-m-d_H-i-s') . '.json"');
    }

    /**
     * Import servers from JSON file
     */
    public function importServers(Request $request)
    {
        try {
            $request->validate([
                'json_file' => 'required|file|mimes:json|max:5120'
            ]);
            
            $content = file_get_contents($request->file('json_file')->getPathname());
            $importServers = json_decode($content, true);
            
            if (!is_array($importServers)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid JSON format. Please check the file structure.'
                ], 400);
            }
            
            $currentHosts = $this->parseSshConfigWithDomains();
            $existingHosts = array_column($currentHosts, 'host');
            $addedCount = 0;
            $skippedCount = 0;
            $errors = [];
            
            foreach ($importServers as $server) {
                // Validate required fields
                if (empty($server['host']) || empty($server['hostname']) || empty($server['user'])) {
                    $errors[] = 'Skipped: Missing required fields (host, hostname, user)';
                    $skippedCount++;
                    continue;
                }
                
                // Check if host already exists
                if (in_array($server['host'], $existingHosts)) {
                    $errors[] = "Skipped: Host '{$server['host']}' already exists";
                    $skippedCount++;
                    continue;
                }
                
                // Prepare new host
                $newHost = [
                    'host' => $server['host'],
                    'hostname' => $server['hostname'],
                    'user' => $server['user'],
                    'identity_file' => $server['identity_file'] ?? '',
                    'port' => $server['port'] ?? 22,
                    'domains' => $server['domains'] ?? [],
                    'description' => $server['description'] ?? ''
                ];
                
                $currentHosts[] = $newHost;
                $addedCount++;
            }
            
            if ($addedCount > 0) {
                $this->writeSshConfig($currentHosts);
            }
            
            $message = "✅ Import completed: {$addedCount} servers added, {$skippedCount} skipped.";
            if (!empty($errors)) {
                $message .= " " . implode('; ', array_slice($errors, 0, 3));
            }
            
            return response()->json([
                'success' => true,
                'message' => $message,
                'added' => $addedCount,
                'skipped' => $skippedCount,
                'errors' => $errors
            ]);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error: ' . implode(', ', $e->errors())
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download sample JSON file for import
     */
    public function downloadSampleJson()
    {
        $sample = [
            [
                "host" => "production_server",
                "hostname" => "192.168.1.100",
                "user" => "ubuntu",
                "identity_file" => "~/Documents/SSH/production_key.pem",
                "port" => 22,
                "domains" => [
                    "example.com",
                    "www.example.com"
                ],
                "description" => "Production web server"
            ],
            [
                "host" => "staging_server",
                "hostname" => "192.168.1.101",
                "user" => "ubuntu",
                "identity_file" => "~/Documents/SSH/staging_key.pem",
                "port" => 22,
                "domains" => [
                    "staging.example.com"
                ],
                "description" => "Staging environment"
            ],
            [
                "host" => "database_server",
                "hostname" => "192.168.1.102",
                "user" => "ubuntu",
                "identity_file" => "~/Documents/SSH/db_key.pem",
                "port" => 2222,
                "domains" => [],
                "description" => "MySQL database server"
            ]
        ];
        
        $json = json_encode($sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        
        return response($json)->header('Content-Type', 'application/json')->header('Content-Disposition', 'attachment; filename="ssh_servers_sample.json"');
    }

    /**
     * Fix SSH config for a specific server - applies all standard SSH updates
     */
    public function fixServerSshConfig($host)
    {
        try {
            $hosts = $this->parseSshConfigWithDomains();
            $updated = false;
            $targetHost = null;
            $updateLog = [];
            
            foreach ($hosts as $key => $h) {
                if ($h['host'] === $host) {
                    // Store original values for logging
                    $originalHost = $h;
                    
                    // Apply all standard SSH updates (same as update/edit flow)
                    $hosts[$key]['identities_only'] = true;
                    $hosts[$key]['preferred_auth'] = 'publickey';
                    $hosts[$key]['pubkey_auth'] = true;
                    $hosts[$key]['password_auth'] = false;
                    $hosts[$key]['strict_host_key'] = 'accept-new';
                    $hosts[$key]['server_alive_interval'] = 60;
                    $hosts[$key]['compression'] = true;
                    
                    // Ensure port is set
                    if (empty($hosts[$key]['port'])) {
                        $hosts[$key]['port'] = 22;
                    }
                    
                    $targetHost = $hosts[$key];
                    $updated = true;
                    $updateLog[] = "Added IdentitiesOnly = yes";
                    $updateLog[] = "Set PreferredAuthentications = publickey";
                    $updateLog[] = "Set ServerAliveInterval = 60";
                    break;
                }
            }
            
            if ($updated) {
                // Write updated config back to file
                $this->writeSshConfig($hosts);
                $updateLog[] = "SSH config file updated successfully";
                
                // Fix key file permissions (same as edit functionality)
                if ($targetHost && !empty($targetHost['identity_file'])) {
                    $keyPath = $this->expandPath($targetHost['identity_file']);
                    if (file_exists($keyPath)) {
                        $oldPerms = substr(sprintf('%o', fileperms($keyPath)), -4);
                        chmod($keyPath, 0400);
                        $updateLog[] = "Fixed key permissions: {$oldPerms} → 0400";
                    } else {
                        $updateLog[] = "Warning: Key file not found at {$keyPath}";
                    }
                }
                
                return response()->json([
                    'success' => true,
                    'message' => "✅ SSH configuration fixed for {$host}",
                    'details' => $updateLog,
                    'host' => $host
                ]);
            }
            
            return response()->json([
                'success' => false,
                'message' => "Server '{$host}' not found in SSH configuration"
            ], 404);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }


    /**
     * Fix all server SSH connections - Validate and repair keys
     */
    public function fixAllServerConnections()
    {
        try {
            $hosts = $this->parseSshConfigWithDomains();

            // Clean up invalid hosts (global configs and empty entries) from config file
            $validHosts = array_filter($hosts, function($host) {
                // Skip global configuration "Host *"
                if ($host['host'] === '*') {
                    return false;
                }

                // Skip hosts with no essential configuration
                if (empty($host['hostname']) && empty($host['user']) && empty($host['identity_file'])) {
                    return false;
                }

                return true;
            });

            $cleanedCount = count($hosts) - count($validHosts);
            if ($cleanedCount > 0) {
                // Rewrite config file without invalid entries
                $this->writeSshConfig($validHosts);
                $hosts = $validHosts; // Use cleaned hosts for processing
            }

            // Fix PEM file permissions
            $homeDir = $this->getHomeDirectory();
            $sshDir = $homeDir . '/Documents/SSH';
            $pemFixedCount = 0;
            $pemErrors = [];

            if (is_dir($sshDir)) {
                $files = scandir($sshDir);
                foreach ($files as $file) {
                    if ($file !== '.' && $file !== '..' && str_ends_with($file, '.pem')) {
                        $fullPath = $sshDir . '/' . $file;

                        // Set permission to 400 (read-only for owner only)
                        if (chmod($fullPath, 0400)) {
                            $pemFixedCount++;
                        } else {
                            $pemErrors[] = "Failed to chmod: {$file}";
                        }
                    }
                }
            }

            // Build response message
            $message = "✅ SSH config cleaned";
            if ($cleanedCount > 0) {
                $message .= " (removed {$cleanedCount} invalid entries)";
            }

            if ($pemFixedCount > 0) {
                $message .= "\n✅ Fixed {$pemFixedCount} PEM file(s) to 400 permissions";
            }

            if (!empty($pemErrors)) {
                $message .= "\n⚠️ PEM permission errors: " . implode(', ', $pemErrors);
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'cleaned_count' => $cleanedCount,
                'pem_fixed_count' => $pemFixedCount,
                'pem_errors' => $pemErrors
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Extract public key from private key file
     */
    private function extractPublicKey($privateKeyPath)
    {
        try {
            // Try with ssh-keygen
            $publicKeyPath = $privateKeyPath . '.pub';
            if (file_exists($publicKeyPath)) {
                return file_get_contents($publicKeyPath);
            }
            
            // Generate public key from private key
            $output = [];
            $returnCode = 0;
            exec("ssh-keygen -y -f {$privateKeyPath} 2>/dev/null", $output, $returnCode);
            
            if ($returnCode === 0 && !empty($output)) {
                return implode("\n", $output);
            }
            
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Quick connection test for a host
     */
    private function quickConnectionTest($host)
    {
        try {
            $hostname = $host['hostname'];
            $port = $host['port'] ?? 22;
            $username = $host['user'];
            $identityFile = $this->expandPath($host['identity_file'] ?? '');
            
            if (!file_exists($identityFile)) {
                return ['success' => false, 'message' => 'Key file not found'];
            }
            
            $ssh = new SSH2($hostname, $port);
            $key = PublicKeyLoader::load(file_get_contents($identityFile));
            
            if ($ssh->login($username, $key)) {
                return ['success' => true, 'message' => 'Connected'];
            }
            
            return ['success' => false, 'message' => 'Authentication failed'];
            
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Generate and add public key to server (requires password for first connection)
     */
    public function addPublicKeyToServer(Request $request)
    {
        $request->validate([
            'host' => 'required|string',
            'password' => 'required|string'
        ]);
        
        try {
            $hosts = $this->parseSshConfigWithDomains();
            $targetHost = null;
            
            foreach ($hosts as $h) {
                if ($h['host'] === $request->host) {
                    $targetHost = $h;
                    break;
                }
            }
            
            if (!$targetHost) {
                return response()->json([
                    'success' => false,
                    'message' => 'Server not found'
                ]);
            }
            
            $identityFile = $this->expandPath($targetHost['identity_file']);
            $publicKey = $this->extractPublicKey($identityFile);
            
            if (!$publicKey) {
                return response()->json([
                    'success' => false,
                    'message' => 'Could not extract public key from private key'
                ]);
            }
            
            // Connect with password to add public key
            $ssh = new SSH2($targetHost['hostname'], $targetHost['port'] ?? 22);
            
            if (!$ssh->login($targetHost['user'], $request->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Password authentication failed. Please check password.'
                ]);
            }
            
            // Add public key to authorized_keys
            $ssh->exec('mkdir -p ~/.ssh && chmod 700 ~/.ssh');
            $ssh->exec("echo '{$publicKey}' >> ~/.ssh/authorized_keys");
            $ssh->exec('chmod 600 ~/.ssh/authorized_keys');
            
            return response()->json([
                'success' => true,
                'message' => '✅ Public key added successfully! You can now connect without password.'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Diagnose SSH connection issues for a specific server
     */
    public function diagnoseServer($host)
    {
        try {
            $hosts = $this->parseSshConfigWithDomains();
            $targetHost = null;
            
            foreach ($hosts as $h) {
                if ($h['host'] === $host) {
                    $targetHost = $h;
                    break;
                }
            }
            
            if (!$targetHost) {
                return response()->json([
                    'success' => false,
                    'message' => "Server '{$host}' not found"
                ]);
            }
            
            $diagnostics = [];
            
            // 1. Check SSH config file
            $diagnostics[] = "=== SSH CONFIG CHECK ===";
            $diagnostics[] = "Config path: {$this->sshConfigPath}";
            $diagnostics[] = "Config exists: " . (file_exists($this->sshConfigPath) ? "Yes" : "No");
            
            // 2. Check key file
            $keyPath = $this->expandPath($targetHost['identity_file']);
            $diagnostics[] = "\n=== KEY FILE CHECK ===";
            $diagnostics[] = "Key path: {$keyPath}";
            $diagnostics[] = "Key exists: " . (file_exists($keyPath) ? "Yes" : "No");
            
            if (file_exists($keyPath)) {
                $perms = substr(sprintf('%o', fileperms($keyPath)), -4);
                $diagnostics[] = "Key permissions: {$perms} (should be 400 or 600)";
                $diagnostics[] = "Key size: " . filesize($keyPath) . " bytes";
            }
            
            // 3. Test SSH connection
            $diagnostics[] = "\n=== SSH CONNECTION TEST ===";
            
            $sshCommand = "ssh -v -o ConnectTimeout=10 -o IdentitiesOnly=yes -i {$keyPath} {$targetHost['user']}@{$targetHost['hostname']} -p {$targetHost['port']} 2>&1 | head -50";
            exec($sshCommand, $output, $returnCode);
            
            $diagnostics[] = "Connection test output:";
            $diagnostics = array_merge($diagnostics, $output);
            
            // 4. Check VS Code Remote SSH status
            $diagnostics[] = "\n=== VS CODE REMOTE SSH ===";
            $diagnostics[] = "To fix VS Code Remote SSH, try:";
            $diagnostics[] = "1. Command Palette (Cmd+Shift+P) → 'Remote-SSH: Kill VS Code Server on Host'";
            $diagnostics[] = "2. Then try reconnecting";
            $diagnostics[] = "3. Or delete: ~/.vscode-server on the remote server";
            
            return response()->json([
                'success' => true,
                'host' => $host,
                'diagnostics' => $diagnostics,
                'ssh_command' => "ssh -i {$keyPath} {$targetHost['user']}@{$targetHost['hostname']} -p {$targetHost['port']}"
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ]);
        }
    }

    // ============================================
    // DATABASE CREDENTIAL IMPORT FROM SSH SERVERS
    // ============================================

    /**
     * Import database credentials from a single SSH server
     * Reads Apache config → finds DocumentRoot → locates .env → extracts DB credentials
     */
    public function importDbFromServer(Request $request)
    {
        try {
            $request->validate([
                'host' => 'required|string',
                'hostname' => 'required|string',
                'user' => 'nullable|string',
                'identity_file' => 'nullable|string',
                'port' => 'nullable|integer',
                'domains' => 'nullable|array',
                'force' => 'boolean' // force re-import if already exists
            ]);

            $hostConfig = $request->only(['host', 'hostname', 'user', 'identity_file', 'port']);
            $hostConfig['port'] = $hostConfig['port'] ?? 22;
            $hostConfig['user'] = $hostConfig['user'] ?? 'ubuntu';
            
            // Get domains for this server
            $allHosts = $this->parseSshConfigWithDomains();
            $thisHost = collect($allHosts)->firstWhere('host', $request->host);
            $domains = $thisHost['domains'] ?? $request->domains ?? [];
            
            if (empty($domains)) {
                return response()->json([
                    'success' => false,
                    'message' => "Server '{$request->host}' has no domains configured"
                ]);
            }
            
            // Connect to SSH server
            $ssh = $this->connectToServer($hostConfig);
            
            // Get Apache config (multiple path attempts)
            $configContent = $this->getApacheConfigFromServer($ssh);
            if (!$configContent) {
                return response()->json([
                    'success' => false,
                    'message' => 'Could not read Apache config from server'
                ]);
            }
            
            $results = [
                'imported' => [],
                'skipped' => [],
                'errors' => []
            ];
            
            foreach ($domains as $domain) {
                try {
                    // Find DocumentRoot for this domain
                    $docRoot = $this->findDocumentRootInConfig($configContent, $domain);
                    if (!$docRoot) {
                        $results['errors'][] = "Could not find DocumentRoot for domain '{$domain}'";
                        $results['skipped'][] = $domain;
                        continue;
                    }
                    
                    // Get project root (remove /public, /public_html, /htdocs)
                    $projectPath = $this->extractProjectPath($docRoot);
                    
                    // Find .env file
                    $envPath = $this->findEnvFile($ssh, $projectPath);
                    if (!$envPath) {
                        $results['errors'][] = "No .env file found for domain '{$domain}' (checked: {$projectPath}/.env, ../.env)";
                        $results['skipped'][] = $domain;
                        continue;
                    }
                    
                    // Read .env content
                    $envContent = $ssh->exec("cat " . escapeshellarg($envPath) . " 2>/dev/null");
                    if (empty($envContent)) {
                        $results['errors'][] = "Could not read .env file: {$envPath}";
                        $results['skipped'][] = $domain;
                        continue;
                    }
                    
                    // Parse DB credentials
                    $envVars = $this->parseEnvFile($envContent);
                    $dbConfig = $this->extractDbConfig($envVars);
                    
                    if (!$dbConfig['database'] || !$dbConfig['username']) {
                        $results['errors'][] = "Incomplete DB config in .env for domain '{$domain}'";
                        $results['skipped'][] = $domain;
                        continue;
                    }
                    
                    // Check if already imported
                    $existingLog = \DB::table('ssh_db_import_log')
                        ->where('ssh_host', $request->host)
                        ->where('domain', $domain)
                        ->first();
                    
                    if ($existingLog && !$request->force) {
                        $results['skipped'][] = $domain . " (already imported)";
                        continue;
                    }
                    
                    // Check for duplicate database credential (same host+db+user)
                    $existingCred = DatabaseCredential::where('database', $dbConfig['database'])
                        ->where('username', $dbConfig['username'])
                        ->where('host', $dbConfig['host'] ?? '127.0.0.1')
                        ->where('port', $dbConfig['port'] ?? 3306)
                        ->first();
                    
                    if ($existingCred && !$request->force) {
                        $results['skipped'][] = $domain . " (duplicate credential exists)";
                        continue;
                    }
                    
                     // Create or update database credential (initially inactive)
                     $credentialData = [
                         'name' => "{$domain} - {$dbConfig['database']}",
                         'connection_name' => $dbConfig['connection'],
                         'host' => $dbConfig['host'],
                         'port' => $dbConfig['port'],
                         'database' => $dbConfig['database'],
                         'username' => $dbConfig['username'],
                         'password' => $dbConfig['password'],
                         'notes' => "Imported from SSH server '{$request->host}', domain: {$domain}. Path: {$projectPath}",
                         'ssh_host' => $request->host,  // Link to SSH server
                         'is_active' => false, // Will be tested below
                         'is_default' => false,
                     ];
                     
                     // Test connection BEFORE inserting to avoid cluttering with failed connections
                     $testCredential = new DatabaseCredential($credentialData);
                     $isActive = $this->testDatabaseConnection($testCredential, 12); // 12-second timeout
                     
                     // Only create/update if connection is successful
                     if (!$isActive) {
                         $results['skipped'][] = $domain . " (connection test failed)";
                         continue;
                     }
                     
                      // Connection successful - now save to database
                      if ($existingCred) {
                          // Update existing
                          $existingCred->update($credentialData);
                          $credentialObj = $existingCred;
                      } else {
                          // Create new
                          $credentialObj = DatabaseCredential::create($credentialData);
                      }
                      
                       // Handle phpMyAdmin URL logic for newly created/updated credentials
                       if (!empty($dbConfig['host']) && $dbConfig['host'] === '127.0.0.1') {
                           if (!empty($dbConfig['username']) && 
                               (strtoupper($dbConfig['username']) === 'PAYMENTS_ADMIN' || 
                                strtoupper($dbConfig['username']) === 'PAYTEST_ADMIN')) {
                               $credentialObj->phpmyadmin_url = 'https://admin.paytest.in/phpmyadmin';
                               $credentialObj->save();
                           } else {
                               // non-payments_admin localhost: detect alias + merge with SSH domain
                          // For localhost, detect phpMyAdmin alias from Apache config
                          $aliasPath = null;
                          $configPaths = [
                              '/etc/phpmyadmin/apache.conf',
                              '/etc/phpmyadmin/apache2.conf',
                              '/etc/phpmyadmin/conf.d/apache.conf',
                              '/usr/share/phpmyadmin/apache.conf',
                              '/etc/httpd/conf.d/phpMyAdmin.conf',
                              '/etc/httpd/conf.d/phpmyadmin.conf',
                          ];
                          
                          foreach ($configPaths as $path) {
                              $output = $ssh->exec("cat " . escapeshellarg($path) . " 2>/dev/null");
                              if (!empty($output)) {
                                  // Look for Alias directive
                                  if (preg_match('/Alias\s+\/([^\s]+)\s+"([^"]+)"/i', $output, $matches)) {
                                      $aliasPath = $matches[1]; // e.g., "phpmyadmin"
                                      break;
                                  } elseif (preg_match("/Alias\s+\/phpmyadmin\s+/i", $output)) {
                                      $aliasPath = 'phpmyadmin';
                                      break;
                                  }
                              }
                          }
                          
                          // If not found via config files, try to find the phpMyAdmin directory
                          if (!$aliasPath) {
                              $locations = $ssh->exec("ls -d /usr/share/phpmyadmin /var/www/html/phpmyadmin /var/www/phpmyadmin 2>/dev/null");
                              if (!empty($locations)) {
                                  $aliasPath = 'phpmyadmin'; // Assume standard alias
                              }
                          }
                          
                          // Build the URL using the first available domain
                          if (!empty($aliasPath) && !empty($domains)) {
                              $firstDomain = $domains[0];
                              $credentialObj->phpmyadmin_url = 'https://' . $firstDomain . '/' . $aliasPath;
                              $credentialObj->save();
                          } else {
                              // Fallback if we can't determine the alias
                              $credentialObj->phpmyadmin_url = null;
                              $credentialObj->save();
                           }
                        }
                    }
                      
                      $credentialId = $credentialObj->id;
                    
                    // Log import
                    \DB::table('ssh_db_import_log')->updateOrInsert(
                        [
                            'ssh_host' => $request->host,
                            'domain' => $domain
                        ],
                        [
                            'database_credential_id' => $credentialId,
                            'project_path' => $projectPath,
                            'env_path' => $envPath,
                            'imported_at' => now(),
                            'last_synced_at' => now(),
                            'import_status' => $isActive ? 'success' : 'failed',
                            'error_message' => $isActive ? null : 'Connection test failed',
                            'env_vars_snapshot' => json_encode(array_merge(
                                $envVars,
                                ['_imported_keys' => array_keys($envVars)]
                            ))
                        ]
                    );
                    
                    if ($isActive) {
                        $results['imported'][] = $domain;
                    } else {
                        $results['skipped'][] = $domain . " (connection test failed)";
                    }
                    
                } catch (\Exception $domainErr) {
                    $results['errors'][] = "Domain '{$domain}': " . $domainErr->getMessage();
                    $results['skipped'][] = $domain;
                }
            }
            
            return response()->json([
                'success' => true,
                'host' => $request->host,
                'imported_count' => count($results['imported']),
                'skipped_count' => count($results['skipped']),
                'domains_imported' => $results['imported'],
                'domains_skipped' => $results['skipped'],
                'errors' => $results['errors']
            ]);
            
        } catch (\Exception $e) {
            \Log::error('SSH DB Import failed: ' . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get import status summary (for badge count)
     */
    public function getImportStatus(Request $request): JsonResponse
    {
        try {
            $allHosts = $this->parseSshConfigWithDomains();
            $totalWithDomains = 0;
            foreach ($allHosts as $host) {
                if (!empty($host['domains'])) {
                    $totalWithDomains += count($host['domains']);
                }
            }
            
            $importedCount = \DB::table('ssh_db_import_log')->count();
            
            return response()->json([
                'success' => true,
                'total_domains' => $totalWithDomains,
                'imported_count' => $importedCount,
                'pending_count' => max(0, $totalWithDomains - $importedCount)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    // ============================================
    // HELPER METHODS FOR DB IMPORT
    // ============================================

    /**
     * Connect to SSH server using phpseclib
     */
    private function connectToServer(array $hostConfig): \phpseclib3\Net\SSH2
    {
        $hostname = $hostConfig['hostname'];
        $port = $hostConfig['port'] ?? 22;
        $user = $hostConfig['user'] ?? 'ubuntu';
        $identityFile = $this->expandPath($hostConfig['identity_file'] ?? '');
        
        if (!file_exists($identityFile)) {
            throw new \Exception("SSH key file not found: {$identityFile}");
        }
        
        try {
            $ssh = new \phpseclib3\Net\SSH2($hostname, $port);
            $key = \phpseclib3\Crypt\PublicKeyLoader::load(file_get_contents($identityFile));
            
            if (!$ssh->login($user, $key)) {
                throw new \Exception("SSH authentication failed for {$user}@{$hostname}");
            }
            
            return $ssh;
        } catch (\Exception $e) {
            throw new \Exception("SSH connection failed: " . $e->getMessage());
        }
    }

    /**
     * Read Apache config from server, trying multiple known paths
     */
    private function getApacheConfigFromServer($ssh): ?string
    {
        $possiblePaths = [
            '/etc/apache2/sites-enabled/000-default-le-ssl.conf',
            '/etc/apache2/sites-enabled/default-ssl.conf',
            '/etc/apache2/sites-enabled/001-ssl.conf',
            '/etc/httpd/conf.d/ssl.conf',
            '/etc/httpd/conf.d/default-ssl.conf',
            '/etc/apache2/sites-available/000-default-le-ssl.conf',
            '/etc/apache2/sites-available/default-ssl.conf',
        ];
        
        foreach ($possiblePaths as $path) {
            $output = $ssh->exec("cat " . escapeshellarg($path) . " 2>/dev/null");
            if (!empty($output) && str_contains($output, 'VirtualHost')) {
                return $output;
            }
        }
        
        // Try to find the active SSL config
        $sslConfigs = $ssh->exec("ls /etc/apache2/sites-enabled/ 2>/dev/null | grep -i ssl");
        if (!empty($sslConfigs)) {
            $lines = explode("\n", trim($sslConfigs));
            if (!empty($lines[0])) {
                $path = "/etc/apache2/sites-enabled/" . trim($lines[0]);
                $output = $ssh->exec("cat " . escapeshellarg($path) . " 2>/dev/null");
                if (!empty($output)) return $output;
            }
        }
        
        return null;
    }

    /**
     * Extract project path from DocumentRoot
     * Laravel: /var/www/site/public → /var/www/site
     */
    private function extractProjectPath(string $docRoot): string
    {
        $docRoot = rtrim($docRoot, '/');
        
        // Remove common public directory suffixes
        $suffixes = ['/public', '/public_html', '/htdocs'];
        foreach ($suffixes as $suffix) {
            if (str_ends_with($docRoot, $suffix)) {
                return substr($docRoot, 0, -strlen($suffix));
            }
        }
        
        return $docRoot;
    }

    /**
     * Test database connection for a credential
     * Returns true if successful, false otherwise
     */

    /**
     * Find .env file on server
     */
    private function findEnvFile($ssh, string $projectPath): ?string
    {
        $candidates = [
            $projectPath . '/.env',
            $projectPath . '/.env.example',
            dirname($projectPath) . '/.env',
            $projectPath . '/.env.production',
            $projectPath . '/.env.local',
        ];
        
        foreach ($candidates as $candidate) {
            $check = $ssh->exec("test -f " . escapeshellarg($candidate) . " && echo 'found'");
            if (trim($check) === 'found') {
                return $candidate;
            }
        }
        
        return null;
    }

    /**
     * Parse .env file content into associative array
     */
    private function parseEnvFile(string $content): array
    {
        $env = [];
        $lines = explode("\n", $content);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }
            
            // Handle: KEY=VALUE or KEY="VALUE" or KEY='VALUE'
            if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)=(.*)$/', $line, $matches)) {
                $key = $matches[1];
                $value = $matches[2];
                
                // Strip surrounding quotes
                if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                    (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                    $value = substr($value, 1, -1);
                }
                
                $env[$key] = $value;
            }
        }
        
        return $env;
    }

    /**
     * Extract database configuration from parsed .env array
     */
    private function extractDbConfig(array $env): array
    {
        $connection = strtolower($env['DB_CONNECTION'] ?? 'mysql');
        
        // Normalize connection name
        if (!in_array($connection, ['mysql', 'pgsql', 'sqlite'])) {
            $connection = 'mysql';
        }
        
        // Set default ports
        $defaultPort = match($connection) {
            'pgsql' => 5432,
            'sqlite' => null,
            default => 3306,
        };
        
        return [
            'connection' => $connection,
            'host' => $env['DB_HOST'] ?? '127.0.0.1',
            'port' => $env['DB_PORT'] ?? $defaultPort,
            'database' => $env['DB_DATABASE'] ?? null,
            'username' => $env['DB_USERNAME'] ?? null,
            'password' => $env['DB_PASSWORD'] ?? null,
        ];
    }

    /**
     * Find DocumentRoot for a domain in Apache config (PHP version)
     * Mirrors the JavaScript logic from ssh.js
     */
    private function findDocumentRootInConfig(string $configContent, string $domain): ?string
    {
        $cleanDomain = preg_replace('#^https?://#i', '', rtrim($domain, '/'));
        $vhostBlocks = explode('</VirtualHost>', $configContent);
        
        foreach ($vhostBlocks as $block) {
            $blockLower = strtolower($block);
            $cleanDomainLower = strtolower($cleanDomain);
            
            // Check for domain in ServerName or ServerAlias
            if (str_contains($blockLower, "servername {$cleanDomainLower}") ||
                str_contains($blockLower, "serveralias {$cleanDomainLower}")) {
                
                if (preg_match('/documentroot\s+["\']?([^"\'\s]+)["\']?/i', $block, $matches)) {
                    return rtrim($matches[1], '/');
                }
            }
        }
        
        return null;
    }

    /**
     * Get count of domains pending import (for badge)
     */
    public function getImportPendingCount(): JsonResponse
    {
        try {
            $allHosts = $this->parseSshConfigWithDomains();
            $totalWithDomains = 0;
            foreach ($allHosts as $host) {
                if (!empty($host['domains'])) {
                    $totalWithDomains += count($host['domains']);
                }
            }
            
            $importedCount = \DB::table('ssh_db_import_log')->count();
            return response()->json([
                'success' => true,
                'total_domains' => $totalWithDomains,
                'imported_count' => $importedCount,
                'pending_count' => max(0, $totalWithDomains - $importedCount)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get list of all SSH servers with domains (for import UI)
     */
    public function getServersWithDomains(Request $request): JsonResponse
    {
        try {
            $hosts = $this->parseSshConfigWithDomains();
            $servers = [];
            
            foreach ($hosts as $host) {
                if (!empty($host['domains'])) {
                    // Check if all domains have been imported
                    $importedCount = \DB::table('ssh_db_import_log')
                        ->where('ssh_host', $host['host'])
                        ->count();
                    $totalDomains = count($host['domains']);
                    $allImported = ($importedCount >= $totalDomains);
                    
                    $servers[] = [
                        'host' => $host['host'],
                        'hostname' => $host['hostname'],
                        'user' => $host['user'],
                        'identity_file' => $host['identity_file'],
                        'port' => $host['port'],
                        'domains' => $host['domains'],
                        'description' => $host['description'] ?? '',
                        'imported_count' => $importedCount,
                        'total_domains' => $totalDomains,
                        'all_imported' => $allImported
                    ];
                }
            }
            
            return response()->json([
                'success' => true,
                'servers' => $servers
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get phpMyAdmin URL and credentials for a database credential
     * Reads Apache config on the associated SSH server to find Alias
     */
    public function getPhpMyAdminInfo($id)
    {
        try {
            // Find the database credential
            $credential = DatabaseCredential::findOrFail($id);
            
            // Check if it's linked to an SSH server
            if (empty($credential->ssh_host)) {
                return response()->json([
                    'success' => false,
                    'message' => 'This database credential is not linked to an SSH server. Import it from SSH servers first.'
                ]);
            }
            
            // Get SSH server details from config
            $hosts = $this->parseSshConfigWithDomains();
            $sshServer = collect($hosts)->firstWhere('host', $credential->ssh_host);
            
            if (!$sshServer) {
                return response()->json([
                    'success' => false,
                    'message' => "SSH server '{$credential->ssh_host}' not found in config"
                ]);
            }
            
            // Connect to SSH server
            $ssh = $this->connectToServer($sshServer);
            
            // Try multiple common phpMyAdmin config paths
            $configPaths = [
                '/etc/phpmyadmin/apache.conf',
                '/etc/phpmyadmin/apache2.conf',
                '/etc/phpmyadmin/config.inc.php',
                '/etc/phpmyadmin/conf.d/apache.conf',
                '/usr/share/phpmyadmin/apache.conf',
                '/etc/httpd/conf.d/phpMyAdmin.conf',
                '/etc/httpd/conf.d/phpmyadmin.conf',
            ];
            
            $aliasUrl = null;
            $configContent = '';
            
            foreach ($configPaths as $path) {
                $output = $ssh->exec("cat " . escapeshellarg($path) . " 2>/dev/null");
                if (!empty($output)) {
                    $configContent = $output;
                    // Look for Alias directive
                    if (preg_match('/Alias\s+\/([^\s]+)\s+"([^"]+)"/i', $output, $matches)) {
                        $aliasUrl = $matches[1]; // e.g., "phpmyadmin"
                        break;
                    } elseif (preg_match("/Alias\s+\/phpmyadmin\s+/i", $output)) {
                        $aliasUrl = 'phpmyadmin';
                        break;
                    }
                }
            }
            
            // If not found via config files, try to find the phpMyAdmin directory
            if (!$aliasUrl) {
                // Check common installation locations
                $locations = $ssh->exec("ls -d /usr/share/phpmyadmin /var/www/html/phpmyadmin /var/www/phpmyadmin 2>/dev/null");
                if (!empty($locations)) {
                    $aliasUrl = 'phpmyadmin'; // Assume standard alias
                }
            }
            
            // Determine the server hostname for URL construction
            $serverHostname = $sshServer['hostname'];
            
            // Build phpMyAdmin URL (guess protocol - could be http or https)
            // We'll return both possibilities or let user know
            $possibleUrls = [
                'https://' . $serverHostname . '/' . $aliasUrl,
                'http://' . $serverHostname . '/' . $aliasUrl,
            ];
            
            // Also try with domain if domains exist
            $domainUrl = null;
            if (!empty($sshServer['domains'])) {
                $firstDomain = $sshServer['domains'][0];
                $domainUrl = 'https://' . $firstDomain . '/' . $aliasUrl;
            }
            
            // Return the info
            return response()->json([
                'success' => true,
                'ssh_host' => $credential->ssh_host,
                'server_hostname' => $serverHostname,
                'alias_path' => $aliasUrl,
                'possible_urls' => $possibleUrls,
                'domain_url' => $domainUrl,
                'database_name' => $credential->database,
                'database_username' => $credential->username,
                'database_password' => $credential->decrypted_password, // Decrypted!
                'config_found' => !empty($configContent),
                'config_path_tried' => $configPaths
            ]);
            
        } catch (\Exception $e) {
            \Log::error('phpMyAdmin info fetch failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch phpMyAdmin info: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Perform NS lookup for a domain
     */
    public function performNsLookup(Request $request)
    {
        $request->validate([
            'domain' => 'required|string|max:253'
        ]);

        try {
            $domain = trim($request->domain);

            // Basic domain validation
            if (!preg_match('/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $domain)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid domain format'
                ], 400);
            }

            $result = $this->executeNsLookup($domain);

            return response()->json([
                'success' => true,
                'result' => $result,
                'domain' => $domain
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'NS lookup failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Execute NS lookup using system commands
     */
    private function executeNsLookup($domain)
    {
        $output = [];
        $result = [];

        // Get A records (IPv4)
        exec("nslookup -type=A " . escapeshellarg($domain) . " 2>/dev/null", $aRecords, $returnCode);
        if ($returnCode === 0) {
            $result[] = "=== A Records (IPv4) ===";
            $result[] = implode("\n", array_slice($aRecords, 2)); // Skip first 2 lines
            $result[] = "";
        }

        // Get AAAA records (IPv6)
        exec("nslookup -type=AAAA " . escapeshellarg($domain) . " 2>/dev/null", $aaaaRecords, $returnCode);
        if ($returnCode === 0 && count($aaaaRecords) > 2) {
            $result[] = "=== AAAA Records (IPv6) ===";
            $result[] = implode("\n", array_slice($aaaaRecords, 2));
            $result[] = "";
        }

        // Get MX records
        exec("nslookup -type=MX " . escapeshellarg($domain) . " 2>/dev/null", $mxRecords, $returnCode);
        if ($returnCode === 0 && count($mxRecords) > 2) {
            $result[] = "=== MX Records (Mail Servers) ===";
            $result[] = implode("\n", array_slice($mxRecords, 2));
            $result[] = "";
        }

        // Get NS records
        exec("nslookup -type=NS " . escapeshellarg($domain) . " 2>/dev/null", $nsRecords, $returnCode);
        if ($returnCode === 0 && count($nsRecords) > 2) {
            $result[] = "=== NS Records (Name Servers) ===";
            $result[] = implode("\n", array_slice($nsRecords, 2));
            $result[] = "";
        }

        // Get CNAME records
        exec("nslookup -type=CNAME " . escapeshellarg($domain) . " 2>/dev/null", $cnameRecords, $returnCode);
        if ($returnCode === 0 && count($cnameRecords) > 2) {
            $result[] = "=== CNAME Records ===";
            $result[] = implode("\n", array_slice($cnameRecords, 2));
            $result[] = "";
        }

        // Get TXT records
        exec("nslookup -type=TXT " . escapeshellarg($domain) . " 2>/dev/null", $txtRecords, $returnCode);
        if ($returnCode === 0 && count($txtRecords) > 2) {
            $result[] = "=== TXT Records ===";
            $result[] = implode("\n", array_slice($txtRecords, 2));
            $result[] = "";
        }

        // Basic nslookup output
        exec("nslookup " . escapeshellarg($domain) . " 2>/dev/null", $basicLookup, $returnCode);
        if ($returnCode === 0) {
            $result[] = "=== Basic Lookup ===";
            $result[] = implode("\n", $basicLookup);
            $result[] = "";
        }

        // Get additional info using dig if available
        exec("dig " . escapeshellarg($domain) . " +short 2>/dev/null", $digOutput, $returnCode);
        if ($returnCode === 0 && !empty($digOutput)) {
            $result[] = "=== DIG Results ===";
            $result[] = implode("\n", $digOutput);
            $result[] = "";
        }

        if (empty($result)) {
            return "No DNS records found for domain: " . $domain . "\n\nPossible reasons:\n- Domain does not exist\n- DNS server not responding\n- Network connectivity issues";
        }

        return implode("\n", $result);
    }
}