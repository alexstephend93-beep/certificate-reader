<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\SshConfigParser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\DatabaseCredential;
use App\Models\SshServer;
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
            
            // Skip auto-generated banner/comment separators so they are never treated
            // as a server description or a domain entry
            if (preg_match('/^#\s*=+/', $trimmedLine)
                || preg_match('/^#\s*(SSH Config File|Created:|Global Defaults)/i', $trimmedLine)
            ) {
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
                
                // Skip the global "Host *" defaults block - it is not a real connectable server
                if (trim($matches[1]) === '*') {
                    $currentHost = null;
                    continue;
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
        $content = "";

        // Filter out invalid hosts (global configs and empty entries)
        $validHosts = array_filter($hosts, function($host) {
            // Skip global configuration "Host *"
            if (($host['host'] ?? '') === '*') {
                return false;
            }

            return true;
        });

        foreach ($validHosts as $host) {
            // Keep the config clean: only the essential per-server params
            $content .= "Host {$host['host']}\n";

            if (!empty($host['hostname'])) {
                $content .= "    HostName {$host['hostname']}\n";
            }
            if (!empty($host['user'])) {
                $content .= "    User {$host['user']}\n";
            }
            if (!empty($host['identity_file'])) {
                $content .= "    IdentityFile {$host['identity_file']}\n";
            }

            // Only write Port when it differs from the SSH default to avoid extra lines
            if (!empty($host['port']) && (int)$host['port'] != 22) {
                $content .= "    Port {$host['port']}\n";
            }

            // Add domains as comments
            if (!empty($host['domains'])) {
                foreach ($host['domains'] as $domain) {
                    // Clean protocol from domain
                    $cleanDomain = preg_replace('#^https?://#', '', $domain);
                    $cleanDomain = rtrim($cleanDomain, '/');
                    $content .= "    #Domain https://{$cleanDomain}\n";
                }
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
     * Sync SSH servers from config file to database
     */
    private function syncServersFromConfig()
    {
        $configHosts = $this->parseSshConfigWithDomains();

        foreach ($configHosts as $configHost) {
            // Preserve an already-saved description (from Add/Edit form) when the
            // clean config no longer contains a "# description" comment line.
            $existingServer = SshServer::where('host', $configHost['host'])->first();
            $description = !empty($configHost['description'])
                ? $configHost['description']
                : ($existingServer->description ?? '');

            SshServer::updateOrCreate(
                ['host' => $configHost['host']],
                [
                    'hostname' => $configHost['hostname'] ?? '',
                    'user' => $configHost['user'] ?? '',
                    'identity_file' => $configHost['identity_file'] ?? '',
                    'port' => $configHost['port'] ?? 22,
                    'domains' => !empty($configHost['domains']) ? $configHost['domains'] : [],
                    'description' => $description,
                ]
            );
        }

        // Remove from DB hosts that no longer exist in config
        $configHostNames = collect($configHosts)->pluck('host')->toArray();
        SshServer::whereNotIn('host', $configHostNames)->delete();
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
            // Sync config to DB first
            $this->syncServersFromConfig();

            // Get servers from DB, ordered: favorites first, then by host name
            $dbServers = SshServer::ordered()->get();

            $connectionHistory = Cache::get('ssh_connection_history', []);

            $hosts = $dbServers->map(function ($server) use ($connectionHistory) {
                $host = [
                    'host' => $server->host,
                    'hostname' => $server->hostname,
                    'user' => $server->user,
                    'identity_file' => $server->identity_file,
                    'port' => $server->port,
                    'domains' => $server->domains ?? [],
                    'description' => $server->description ?? '',
                    'is_favorite' => $server->is_favorite,
                    'last_connected' => $connectionHistory[$server->host] ?? null,
                ];

                // Add key status
                $keyPath = $server->identity_file ?? '';
                $host['key_exists'] = false;
                if (!empty($keyPath)) {
                    $fullPath = $this->expandPath($keyPath);
                    $host['key_exists'] = file_exists($fullPath);
                }

                return $host;
            })->toArray();

            $totalServers = count($hosts);
            $validKeys = count(array_filter($hosts, function($h) {
                return !empty($h['identity_file']) && file_exists($this->expandPath($h['identity_file']));
            }));

            // For large configs, limit but allow more
            $maxHosts = 1000;
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

            // Clean domains: remove protocol and trailing slashes
            $cleanDomains = array_filter($request->domains ?? []);
            $cleanDomains = array_map(function($d) {
                $d = preg_replace('#^https?://#', '', $d);
                return rtrim($d, '/');
            }, $cleanDomains);

            // Prevent duplicates: if host already exists, update it instead of creating a second entry
            $incomingHost = $request->host;

            $newHost = [
                'host' => $incomingHost,
                'hostname' => $request->hostname,
                'user' => $request->user,
                'identity_file' => $request->identity_file,
                'port' => $request->port ?? 22,
                'domains' => $cleanDomains,
                'description' => $request->description ?? ''
            ];

            $updated = false;
            foreach ($hosts as $key => $host) {
                if (($host['host'] ?? null) === $incomingHost) {
                    $hosts[$key] = $newHost;
                    $updated = true;
                    break;
                }
            }

            if (!$updated) {
                $hosts[] = $newHost;
            }


            // Write the updated hosts array to the SSH config file
            $this->writeSshConfig($hosts);

            // Sync to DB
            SshServer::updateOrCreate(
                ['host' => $newHost['host']],
                [
                    'hostname' => $newHost['hostname'],
                    'user' => $newHost['user'],
                    'identity_file' => $newHost['identity_file'],
                    'port' => $newHost['port'],
                    'domains' => $newHost['domains'],
                    'description' => $newHost['description'],
                ]
            );

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

                // Sync to DB
                SshServer::updateOrCreate(
                    ['host' => $request->host],
                    [
                        'hostname' => $request->hostname,
                        'user' => $request->user,
                        'identity_file' => $request->identity_file,
                        'port' => $request->port ?? 22,
                        'domains' => array_filter($request->domains ?? []),
                        'description' => $request->description ?? '',
                    ]
                );

                // If host was renamed, update or delete the old record
                if ($originalHost !== $request->host) {
                    SshServer::where('host', $originalHost)->delete();
                }

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

            // Delete from DB
            SshServer::where('host', $host)->delete();

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
        
        // Also update DB
        SshServer::where('host', $request->host)->update([
            'last_connected_at' => now()
        ]);
        
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
    
    // ========== PROJECT EXPLORER (Remote File Browser) ==========

    private const MAX_PREVIEW_BYTES = 25 * 1024 * 1024; // 25 MB — safe in-browser render cap. Files of any size can exist; only previews that would risk hanging Chrome are blocked.
    private const MAX_EDIT_BYTES = 2 * 1024 * 1024; // 2 MB — in-browser EDITING cap. A giant textarea freezes Chrome; bigger files stay previewable/downloadable, just not editable.

    private function validateExplorePath(string $path): bool
    {
        if ($path === '' || $path[0] !== '/') {
            return false;
        }
        // Reject '.'/'..' segments — no legitimate UI-generated path contains
        // them, and they would enable path traversal for write operations.
        foreach (explode('/', $path) as $segment) {
            if ($segment === '.' || $segment === '..') {
                return false;
            }
        }
        // Only allow safe characters - reject shell metacharacters / control chars
        return (bool) preg_match('#^/[a-zA-Z0-9@%+=:,._~/ ()[\]-]*$#', $path);
    }

    /**
     * Validate a NEW single file/folder name for create/rename operations:
     * no slashes, no traversal, no empty or dot-only names. Uses a whitelist
     * loop (same safe set as the path validator, minus '/') so filenames like
     * "my file (v2).txt" work while shell metacharacters are rejected.
     */
    private function validateExploreName(?string $name): bool
    {
        if ($name === null || trim($name) === '' || $name === '.' || $name === '..') {
            return false;
        }
        if (strpos($name, '/') !== false || strpos($name, chr(0)) !== false) {
            return false;
        }
        $allowed = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789@%+=:,._~ ()[]-';
        for ($i = 0, $n = strlen($name); $i < $n; $i++) {
            if (strpos($allowed, $name[$i]) === false) {
                return false;
            }
        }
        return true;
    }

    /**
     * Run one remote command over the explorer SSH base. Returns [exitCode, outputLines].
     * The local `timeout` wrapper guarantees the PHP request can never hang
     * forever on a stalled SSH channel (the stuck "Saving..." spinner bug).
     */
    private function exploreRun(string $base, string $remoteCmd, int $timeoutSec = 60): array
    {
        $out = [];
        exec('timeout ' . (int) $timeoutSec . ' ' . $base . ' ' . escapeshellarg($remoteCmd) . ' 2>/dev/null', $out, $rc);
        return [$rc, $out];
    }

    private function remoteQuote(string $path): string
    {
        return '"' . str_replace(['\\', '"', '$', '`'], ['\\\\', '\\"', '\\$', '\\`'], $path) . '"';
    }

    private function buildExploreSshBase(string $username, string $hostname, int $port, string $identityFile): string
    {
        // BatchMode=yes: SSH must NEVER stop and wait for a password/passphrase
        // prompt — a blocked channel keeps the PHP exec() alive forever, which
        // is exactly what made the editor's "Saving..." spinner spin endlessly.
        // Key auth (the -i file) is the only supported path here.
        return sprintf(
            'ssh -i %s -o BatchMode=yes -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o ConnectTimeout=10 -p %d %s@%s',
            escapeshellarg($identityFile),
            $port,
            escapeshellarg($username),
            escapeshellarg($hostname)
        );
    }

    /**
     * Wrap a remote command so it runs as ROOT whenever the server allows
     * non-interactive sudo (typical for cloud/EC2 "ubuntu" users), falling
     * back to the login user otherwise. sudo -n never prompts for a password
     * — it fails fast instead — so this can never hang the HTTP request.
     *
     * Used for every WRITE operation (save / mkdir / touch / rename / chmod /
     * duplicate / upload): files owned by root (e.g. Laravel logs) were
     * previously unwritable and produced "Check permissions and disk space."
     */
    private function exploreAsRoot(string $remoteCmd): string
    {
        $quoted = escapeshellarg($remoteCmd);
        return 'if command -v sudo >/dev/null 2>&1 && sudo -n true 2>/dev/null; then '
            . 'sudo -n sh -c ' . $quoted . '; else sh -c ' . $quoted . '; fi';
    }

    private function exploreRequestParams(Request $request): array
    {
        return [
            'host' => $request->input('host'),
            'hostname' => $request->input('hostname'),
            'username' => $request->input('username'),
            'identity_file' => $this->expandPath($request->input('identity_file')),
            'port' => (int) $request->input('port', 22),
        ];
    }

    private function exploreCheckParams(array $p): ?JsonResponse
    {
        if (empty($p['host']) || empty($p['hostname']) || empty($p['username']) || empty($p['identity_file'])) {
            return response()->json(['success' => false, 'message' => 'Missing server connection details'], 422);
        }
        if (!file_exists($p['identity_file'])) {
            return response()->json(['success' => false, 'message' => 'Identity file not found: ' . basename($p['identity_file'])], 404);
        }
        return null;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $value = (float) $bytes;
        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }
        return round($value, 2) . ' ' . $units[$i];
    }

    private function formatExploreDate(?string $raw, ?string $tzOffset = null): ?string
    {
        if ($raw === null || $raw === '' || $raw === '-') {
            return null;
        }
        $ts = is_numeric($raw) ? (int) $raw : strtotime($raw);
        if ($ts === false || $ts <= 0) return null;
        // Render in the REMOTE server's local timezone (offset captured via
        // `date +%z` over the same SSH session) — NOT the PHP app timezone
        // (config app.timezone = UTC), which made every timestamp on IST
        // servers appear exactly 5h30m earlier than reality.
        $tzName = $this->remoteOffsetToTimezone($tzOffset);
        if ($tzName !== null) {
            $dt = new \DateTime('@' . $ts);
            $dt->setTimezone(new \DateTimeZone($tzName));
            return $dt->format('Y-m-d H:i');
        }
        return gmdate('Y-m-d H:i', $ts);
    }

    /**
     * Convert a `date +%z` style offset (e.g. "+0530") into a PHP timezone
     * name (e.g. "+05:30"). Returns null when missing/malformed.
     */
    private function remoteOffsetToTimezone(?string $offset): ?string
    {
        if ($offset === null) return null;
        $offset = trim($offset);
        if (!preg_match('/^([+-])(\d{2})(\d{2})$/', $offset, $m)) return null;
        $tzName = $m[1] . $m[2] . ':' . $m[3];
        try {
            new \DateTimeZone($tzName);
        } catch (\Exception $e) {
            return null;
        }
        return $tzName;
    }

    /**
     * Raw Unix epoch from a `stat` field ('' / '-' / '0' → null = unknown).
     * The client renders this in the viewer's local timezone.
     */
    private function exploreTs(?string $raw): ?int
    {
        if ($raw === null || $raw === '' || $raw === '-') return null;
        if (!ctype_digit($raw)) return null;
        $ts = (int) $raw;
        return $ts > 0 ? $ts : null;
    }

    public function exploreDirectory(Request $request): JsonResponse
    {
        $request->validate([
            'host' => 'required|string',
            'hostname' => 'required|string',
            'username' => 'required|string',
            'identity_file' => 'required|string',
            'path' => 'nullable|string',
            'port' => 'nullable|integer',
        ]);

        $p = $this->exploreRequestParams($request);
        $err = $this->exploreCheckParams($p);
        if ($err) return $err;

        $path = $request->input('path') ?: '/var/www';
        if (!$this->validateExplorePath($path)) {
            return response()->json(['success' => false, 'message' => 'Invalid path requested'], 422);
        }

        $remoteCmd = 'cd ' . $this->remoteQuote($path)
            . ' 2>/dev/null && pwd 2>/dev/null && { ls -1A 2>/dev/null | while IFS= read -r f; do '
            . 'if [ -d "$f" ]; then t=D; elif [ -f "$f" ]; then t=F; else t=O; fi; '
            . 'st=$(stat -c "%s|%Y|%W|%a|%U|%G" "$f" 2>/dev/null); '
            . 'printf "%s|%s|%s\n" "$t" "$st" "$f"; done 2>/dev/null; date +%z 2>/dev/null; }';

        $base = $this->buildExploreSshBase($p['username'], $p['hostname'], $p['port'], $p['identity_file']);
        $output = [];
        exec('timeout 60 ' . $base . ' ' . escapeshellarg($remoteCmd) . ' 2>/dev/null', $output, $returnCode);

        if ($returnCode !== 0) {
            return response()->json(['success' => false, 'message' => 'Unable to read directory. Check the path and SSH access.']);
        }

        $resolvedPath = $path;
        $entries = [];

        // The last output line is the remote server's UTC offset (`date +%z`,
        // e.g. "+0530") captured in the same SSH round-trip. It lets us render
        // created/modified timestamps in the SERVER's local timezone instead
        // of the PHP app's timezone (UTC), which displayed wrong times.
        $tzOffset = null;
        if (!empty($output)) {
            $lastLine = trim(end($output));
            if (preg_match('/^[+-]\d{4}$/', $lastLine)) {
                $tzOffset = $lastLine;
                array_pop($output);
            }
        }

        foreach ($output as $index => $line) {
            if ($index === 0) {
                $resolvedPath = trim($line) ?: $path;
                continue;
            }
            $parts = explode('|', $line, 8);
            if (count($parts) !== 8) continue;
            [$type, $sizeRaw, $mtimeRaw, $btimeRaw, $permsRaw, $ownerRaw, $groupRaw, $name] = $parts;
            if ($name === '') continue;
            $entries[] = [
                'name' => $name,
                'type' => $type,
                'size' => (int) $sizeRaw,
                'is_dir' => $type === 'D',
                // Octal permission (644/755...) + owner/group for the Perm column
                'perms' => $permsRaw !== '' ? $permsRaw : null,
                'owner' => $ownerRaw !== '' ? $ownerRaw : null,
                'group' => $groupRaw !== '' ? $groupRaw : null,
                'created_at' => $this->formatExploreDate($btimeRaw, $tzOffset),
                'modified_at' => $this->formatExploreDate($mtimeRaw, $tzOffset),
                // Raw epochs — the browser renders these in the viewer's local
                // timezone (IST for us), regardless of the remote OS timezone.
                'created_ts' => $this->exploreTs($btimeRaw),
                'modified_ts' => $this->exploreTs($mtimeRaw),
            ];
        }

        // Directory sizes: one batched `du -sb` for every subdirectory, capped with a
        // remote `timeout` so huge trees can never slow the listing down — dirs that
        // don't finish in time simply get a null size and show "—" in the UI.
        $dirPaths = [];
        $prefix = $resolvedPath === '/' ? '' : rtrim($resolvedPath, '/');
        foreach ($entries as $e) {
            if ($e['is_dir']) {
                $dirPaths[$prefix . '/' . $e['name']] = null;
            }
        }

        if (!empty($dirPaths)) {
            $duCmd = 'timeout 8 du -sb --';
            foreach (array_keys($dirPaths) as $dp) {
                $duCmd .= ' ' . $this->remoteQuote($dp);
            }
            $duCmd .= ' 2>/dev/null';

            $duOut = [];
            exec('timeout 30 ' . $base . ' ' . escapeshellarg($duCmd) . ' 2>/dev/null', $duOut, $duRc);

            if ($duRc === 0 || !empty($duOut)) {
                foreach ($duOut as $line) {
                    $tab = strpos($line, "\t");
                    if ($tab === false) continue;
                    $bytes = (int) substr($line, 0, $tab);
                    $dp = substr($line, $tab + 1);
                    if (array_key_exists($dp, $dirPaths)) {
                        $dirPaths[$dp] = $bytes;
                    }
                }
            }

            foreach ($entries as &$e) {
                $e['dir_size'] = $e['is_dir'] ? ($dirPaths[$prefix . '/' . $e['name']] ?? null) : null;
            }
            unset($e);
        }

        usort($entries, function ($a, $b) {
            if ($a['is_dir'] === $b['is_dir']) return strcasecmp($a['name'], $b['name']);
            return $a['is_dir'] ? -1 : 1;
        });

        return response()->json(['success' => true, 'path' => $resolvedPath, 'entries' => $entries]);
    }

    public function exploreFile(Request $request): JsonResponse
    {
        $request->validate([
            'host' => 'required|string',
            'hostname' => 'required|string',
            'username' => 'required|string',
            'identity_file' => 'required|string',
            'path' => 'required|string',
            'port' => 'nullable|integer',
        ]);

        $p = $this->exploreRequestParams($request);
        $err = $this->exploreCheckParams($p);
        if ($err) return $err;

        $path = $request->input('path');
        if (!$this->validateExplorePath($path)) {
            return response()->json(['success' => false, 'message' => 'Invalid path requested'], 422);
        }

        $base = $this->buildExploreSshBase($p['username'], $p['hostname'], $p['port'], $p['identity_file']);

        // Stat the remote file (size + last modified + created when available).
        // `date +%z` runs in the SAME SSH round-trip and yields the server's
        // local UTC offset so the preview modal renders Created/Modified in the
        // server's timezone rather than the app's UTC.
        $statCmd = 'stat -Lc "%s|%Y|%W" ' . $this->remoteQuote($path) . ' 2>/dev/null; date +%z 2>/dev/null';
        $statOut = [];
        exec('timeout 60 ' . $base . ' ' . escapeshellarg($statCmd) . ' 2>/dev/null', $statOut, $statRc);

        $size = 0;
        $mtime = null;
        $btime = null;
        $mtimeTs = null;
        $btimeTs = null;
        $tzOffset = null;
        $statLine = null;
        foreach ($statOut as $line) {
            $line = trim($line);
            if ($line === '') continue;
            if ($statLine === null && strpos($line, '|') !== false) {
                $statLine = $line;
                continue;
            }
            if ($tzOffset === null && preg_match('/^[+-]\d{4}$/', $line)) {
                $tzOffset = $line;
            }
            if ($statLine !== null && $tzOffset !== null) break;
        }
        if ($statLine !== null) {
            [$sizeRaw, $mtimeRaw, $btimeRaw] = array_pad(explode('|', $statLine, 3), 3, '');
            $size = (int) $sizeRaw;
            $mtime = $this->formatExploreDate($mtimeRaw, $tzOffset);
            $btime = $this->formatExploreDate($btimeRaw, $tzOffset);
            $mtimeTs = $this->exploreTs($mtimeRaw);
            $btimeTs = $this->exploreTs($btimeRaw);
        }

        if ($statRc !== 0 || $size <= 0) {
            return response()->json(['success' => false, 'message' => 'File not found or not readable on the server.']);
        }

        if ($size > self::MAX_PREVIEW_BYTES) {
            return response()->json([
                'success' => false,
                'code' => 'FILE_TOO_LARGE',
                'size' => $size,
                'message' => 'Previewing "' . basename($path) . '" (' . $this->formatBytes($size)
                    . ') may make Chrome unresponsive. This file is too large to preview safely in the browser. Use the download icon instead.',
            ]);
        }

        // base64 (not raw cat) so every byte survives the round-trip exactly —
        // reading via `exec()` line-splitting would silently strip the file's
        // trailing newline(s), corrupting the buffer on every open/save cycle
        $b64Cmd = 'base64 ' . $this->remoteQuote($path) . ' 2>/dev/null';
        $b64Out = [];
        exec('timeout 60 ' . $base . ' ' . escapeshellarg($b64Cmd) . ' 2>/dev/null', $b64Out, $b64Rc);

        if ($b64Rc !== 0) {
            return response()->json(['success' => false, 'message' => 'Failed to read file content.']);
        }

        $content = base64_decode(implode('', $b64Out), true);
        if ($content === false) {
            return response()->json(['success' => false, 'message' => 'Failed to decode file content.']);
        }

        return response()->json([
            'success' => true,
            'name' => basename($path),
            'path' => $path,
            'size' => $size,
            'modified_ts' => $mtimeTs,
            'content' => $content,
        ]);
    }

    // ---------- Project Explorer: file-manager write operations ----------

    /**
     * Read a file's exact bytes for in-browser editing. Refuses files above
     * MAX_EDIT_BYTES (a giant textarea freezes Chrome) and binary content.
     */
    public function exploreEditRead(Request $request): JsonResponse
    {
        $request->validate([
            'host' => 'required|string',
            'hostname' => 'required|string',
            'username' => 'required|string',
            'identity_file' => 'required|string',
            'path' => 'required|string',
            'port' => 'nullable|integer',
        ]);

        $p = $this->exploreRequestParams($request);
        $err = $this->exploreCheckParams($p);
        if ($err) return $err;

        $path = $request->input('path');
        if (!$this->validateExplorePath($path)) {
            return response()->json(['success' => false, 'message' => 'Invalid path requested'], 422);
        }

        $base = $this->buildExploreSshBase($p['username'], $p['hostname'], $p['port'], $p['identity_file']);

        // Stat first — refuse oversized files BEFORE pulling any content over SSH.
        // Run via the root wrapper: files owned by root with restrictive modes
        // must still be openable in the editor (the save is root-wrapped too).
        [$statRc, $statOut] = $this->exploreRun($base, $this->exploreAsRoot('stat -c "%s|%Y" ' . $this->remoteQuote($path) . ' 2>/dev/null'), 30);
        if ($statRc !== 0 || empty($statOut)) {
            return response()->json(['success' => false, 'message' => 'File not found or not readable on the server.']);
        }
        [$sizeRaw, $mtimeRaw] = array_pad(explode('|', trim($statOut[0]), 2), 2, '');
        $size = (int) $sizeRaw;
        $modifiedTs = $this->exploreTs($mtimeRaw);

        if ($size > self::MAX_EDIT_BYTES) {
            return response()->json([
                'success' => false,
                'code' => 'FILE_TOO_LARGE',
                'size' => $size,
                'message' => 'Editing "' . basename($path) . '" (' . $this->formatBytes($size) . ') is not supported. Files above '
                    . $this->formatBytes(self::MAX_EDIT_BYTES) . ' can freeze the browser — use download instead.',
            ]);
        }

        // Stream exact bytes to a local temp file — an exec() output array
        // silently strips the trailing newline, which is fatal for an editor.
        $tmp = tempnam(sys_get_temp_dir(), 'sshedit_');
        if ($tmp === false) {
            return response()->json(['success' => false, 'message' => 'Could not create a local temp file.']);
        }
        $catCmd = 'cat ' . $this->remoteQuote($path) . ' 2>/dev/null';
        exec('timeout 60 ' . $base . ' ' . escapeshellarg($this->exploreAsRoot($catCmd)) . ' > ' . escapeshellarg($tmp) . ' 2>/dev/null', $catOut, $catRc);
        if ($catRc !== 0) {
            @unlink($tmp);
            return response()->json(['success' => false, 'message' => 'Failed to read the file content from the server.']);
        }
        $content = (string) file_get_contents($tmp);
        @unlink($tmp);

        if (strpos($content, chr(0)) !== false) {
            return response()->json([
                'success' => false,
                'message' => '"' . basename($path) . '" appears to be a binary file and cannot be edited as text.',
            ]);
        }

        return response()->json([
            'success' => true,
            'name' => basename($path),
            'path' => $path,
            'size' => $size,
            'modified_ts' => $modifiedTs,
            'content' => $content,
        ]);
    }

    /**
     * Save edited content back to the remote file. Content is piped via
     * stdin (never interpolated into the shell command), the remote file is
     * re-stat'ed first (size + concurrent-modification guards) and the
     * written byte count is verified afterwards.
     */
    public function exploreSave(Request $request): JsonResponse
    {
        $request->validate([
            'host' => 'required|string',
            'hostname' => 'required|string',
            'username' => 'required|string',
            'identity_file' => 'required|string',
            'path' => 'required|string',
            'content' => 'nullable|string',
            'expected_mtime' => 'nullable|integer',
            'port' => 'nullable|integer',
        ]);

        $p = $this->exploreRequestParams($request);
        $err = $this->exploreCheckParams($p);
        if ($err) return $err;

        $path = $request->input('path');
        if (!$this->validateExplorePath($path)) {
            return response()->json(['success' => false, 'message' => 'Invalid path requested'], 422);
        }

        // Read content from the RAW JSON body — Laravel's global TrimStrings
        // middleware trims every parsed input value, which would silently
        // strip the file's trailing newline(s) (and any trailing spaces)
        // from the edited buffer on every single save.
        $rawBody = json_decode((string) $request->getContent(), true);
        $content = (is_array($rawBody) && isset($rawBody['content']) && is_scalar($rawBody['content']))
            ? (string) $rawBody['content']
            : (string) $request->input('content', '');
        $expectedMtime = $request->input('expected_mtime');

        if (strlen($content) > self::MAX_EDIT_BYTES) {
            return response()->json([
                'success' => false,
                'code' => 'FILE_TOO_LARGE',
                'message' => 'The edited content (' . $this->formatBytes(strlen($content)) . ') exceeds the '
                    . $this->formatBytes(self::MAX_EDIT_BYTES) . ' editing limit. Nothing was saved.',
            ]);
        }

        $base = $this->buildExploreSshBase($p['username'], $p['hostname'], $p['port'], $p['identity_file']);

        // Re-stat right before writing: refuse to clobber a huge file, and
        // detect edits made on the server after the user opened the editor.
        // Root-wrapped so root-owned files (e.g. Laravel logs) stat correctly.
        [$statRc, $statOut] = $this->exploreRun($base, $this->exploreAsRoot('stat -c "%s|%Y" ' . $this->remoteQuote($path) . ' 2>/dev/null'), 30);
        if ($statRc !== 0 || empty($statOut)) {
            return response()->json(['success' => false, 'message' => 'The file no longer exists on the server — nothing was saved.']);
        }
        [$sizeRaw, $mtimeRaw] = array_pad(explode('|', trim($statOut[0]), 2), 2, '');
        if ((int) $sizeRaw > self::MAX_EDIT_BYTES) {
            return response()->json([
                'success' => false,
                'code' => 'FILE_TOO_LARGE',
                'message' => 'This file has grown beyond the ' . $this->formatBytes(self::MAX_EDIT_BYTES)
                    . ' editing limit since you opened it. Nothing was saved.',
            ]);
        }
        $remoteMtime = $this->exploreTs($mtimeRaw);
        if ($expectedMtime !== null && $expectedMtime !== '' && $remoteMtime !== null && (int) $expectedMtime !== $remoteMtime) {
            return response()->json([
                'success' => false,
                'code' => 'FILE_CHANGED',
                'message' => 'This file was modified on the server after you opened it. Refresh and re-open the file before saving.',
            ]);
        }

        // Write exact bytes via stdin, then verify size+mtime in the SAME
        // remote shell — the command only succeeds when BOTH parts succeed.
        $tmp = tempnam(sys_get_temp_dir(), 'sshedit_');
        if ($tmp === false || file_put_contents($tmp, $content) === false) {
            return response()->json(['success' => false, 'message' => 'Could not stage the content locally.']);
        }
        $writeCmd = 'cat > ' . $this->remoteQuote($path)
            . ' 2>/dev/null && stat -c "%s|%Y" ' . $this->remoteQuote($path) . ' 2>/dev/null';
        $verifyOut = [];
        exec('timeout 120 ' . $base . ' ' . escapeshellarg($this->exploreAsRoot($writeCmd)) . ' < ' . escapeshellarg($tmp) . ' 2>/dev/null', $verifyOut, $rc);
        @unlink($tmp);

        if ($rc !== 0 || empty($verifyOut)) {
            return response()->json(['success' => false, 'message' => 'Failed to write the file on the server. Check disk space — or the file may be root-owned and this server does not allow passwordless sudo.']);
        }
        [$newSize, $newMtime] = array_pad(explode('|', trim($verifyOut[0]), 2), 2, '');
        if ((int) $newSize !== strlen($content)) {
            return response()->json([
                'success' => false,
                'message' => 'Save verification failed — the file size on the server does not match the edited content. Re-open the file and check it.',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => '"' . basename($path) . '" saved successfully (' . $this->formatBytes(strlen($content)) . ')',
            'size' => strlen($content),
            'modified_ts' => $this->exploreTs($newMtime),
        ]);
    }

    /**
     * Rename (move) a file or folder within its current directory.
     */
    public function exploreRename(Request $request): JsonResponse
    {
        $request->validate([
            'host' => 'required|string',
            'hostname' => 'required|string',
            'username' => 'required|string',
            'identity_file' => 'required|string',
            'path' => 'required|string',
            'new_name' => 'required|string',
            'port' => 'nullable|integer',
        ]);

        $p = $this->exploreRequestParams($request);
        $err = $this->exploreCheckParams($p);
        if ($err) return $err;

        $path = rtrim($request->input('path'), '/');
        $newName = trim($request->input('new_name'));
        if (!$this->validateExplorePath($path) || !$this->validateExploreName($newName)) {
            return response()->json(['success' => false, 'message' => 'Invalid path or name requested'], 422);
        }

        $parent = dirname($path);
        $target = ($parent === '/' ? '' : $parent) . '/' . $newName;
        if (!$this->validateExplorePath($target)) {
            return response()->json(['success' => false, 'message' => 'Invalid target path'], 422);
        }

        $base = $this->buildExploreSshBase($p['username'], $p['hostname'], $p['port'], $p['identity_file']);
        // Refuse to silently overwrite an existing target
        $cmd = 'if [ -e ' . $this->remoteQuote($target) . ' ]; then echo __TARGET_EXISTS__; exit 2; fi; '
            . 'mv -- ' . $this->remoteQuote($path) . ' ' . $this->remoteQuote($target) . ' 2>/dev/null';
        // Renamed as root so items inside root-owned directories can be moved
        [$rc, $out] = $this->exploreRun($base, $this->exploreAsRoot($cmd), 30);

        if ($rc !== 0) {
            if (!empty($out) && trim($out[0]) === '__TARGET_EXISTS__') {
                return response()->json(['success' => false, 'message' => 'A file or folder named "' . $newName . '" already exists in this directory.']);
            }
            return response()->json(['success' => false, 'message' => 'Rename failed — the server denied permission or the item no longer exists.']);
        }
        return response()->json(['success' => true, 'message' => 'Renamed to "' . $newName . '" successfully']);
    }

    /**
     * Create a new directory inside the currently browsed directory.
     */
    public function exploreMkdir(Request $request): JsonResponse
    {
        $request->validate([
            'host' => 'required|string',
            'hostname' => 'required|string',
            'username' => 'required|string',
            'identity_file' => 'required|string',
            'path' => 'required|string',
            'name' => 'required|string',
            'port' => 'nullable|integer',
        ]);

        $p = $this->exploreRequestParams($request);
        $err = $this->exploreCheckParams($p);
        if ($err) return $err;

        $parent = rtrim($request->input('path'), '/');
        $name = trim($request->input('name'));
        if (!$this->validateExplorePath($parent) || !$this->validateExploreName($name)) {
            return response()->json(['success' => false, 'message' => 'Invalid path or folder name requested'], 422);
        }

        $dirPath = ($parent === '/' ? '' : $parent) . '/' . $name;
        if (!$this->validateExplorePath($dirPath)) {
            return response()->json(['success' => false, 'message' => 'Invalid folder path requested'], 422);
        }

        $base = $this->buildExploreSshBase($p['username'], $p['hostname'], $p['port'], $p['identity_file']);
        $cmd = 'if [ -e ' . $this->remoteQuote($dirPath) . ' ]; then echo __TARGET_EXISTS__; exit 2; fi; '
            . 'mkdir -m 755 -- ' . $this->remoteQuote($dirPath) . ' 2>/dev/null';
        // Created as ROOT with an explicit 755 permission so new folders are
        // web-server friendly and root-owned trees accept new subfolders.
        [$rc, $out] = $this->exploreRun($base, $this->exploreAsRoot($cmd), 30);

        if ($rc !== 0) {
            if (!empty($out) && trim($out[0]) === '__TARGET_EXISTS__') {
                return response()->json(['success' => false, 'message' => 'A file or folder named "' . $name . '" already exists in this directory.']);
            }
            return response()->json(['success' => false, 'message' => 'Could not create the folder — the server denied permission.']);
        }
        return response()->json(['success' => true, 'message' => 'Folder "' . $name . '" created successfully', 'path' => $dirPath]);
    }

    /**
     * Create a new empty file inside the currently browsed directory.
     */
    public function exploreTouch(Request $request): JsonResponse
    {
        $request->validate([
            'host' => 'required|string',
            'hostname' => 'required|string',
            'username' => 'required|string',
            'identity_file' => 'required|string',
            'path' => 'required|string',
            'name' => 'required|string',
            'port' => 'nullable|integer',
        ]);

        $p = $this->exploreRequestParams($request);
        $err = $this->exploreCheckParams($p);
        if ($err) return $err;

        $parent = rtrim($request->input('path'), '/');
        $name = trim($request->input('name'));
        if (!$this->validateExplorePath($parent) || !$this->validateExploreName($name)) {
            return response()->json(['success' => false, 'message' => 'Invalid path or file name requested'], 422);
        }

        $filePath = ($parent === '/' ? '' : $parent) . '/' . $name;
        if (!$this->validateExplorePath($filePath)) {
            return response()->json(['success' => false, 'message' => 'Invalid file path requested'], 422);
        }

        $base = $this->buildExploreSshBase($p['username'], $p['hostname'], $p['port'], $p['identity_file']);
        // touch on an existing file would silently bump its mtime — refuse instead
        $cmd = 'if [ -e ' . $this->remoteQuote($filePath) . ' ]; then echo __TARGET_EXISTS__; exit 2; fi; '
            . 'touch -- ' . $this->remoteQuote($filePath) . ' 2>/dev/null && chmod 644 -- ' . $this->remoteQuote($filePath) . ' 2>/dev/null && stat -c "%s|%Y" ' . $this->remoteQuote($filePath) . ' 2>/dev/null';
        // Created as ROOT with a standard 644 permission
        [$rc, $out] = $this->exploreRun($base, $this->exploreAsRoot($cmd), 30);

        if ($rc !== 0 || empty($out)) {
            if (!empty($out) && trim($out[0]) === '__TARGET_EXISTS__') {
                return response()->json(['success' => false, 'message' => 'A file or folder named "' . $name . '" already exists in this directory.']);
            }
            return response()->json(['success' => false, 'message' => 'Could not create the file — the server denied permission.']);
        }
        [$sizeRaw, $mtimeRaw] = array_pad(explode('|', trim($out[0]), 2), 2, '');
        return response()->json([
            'success' => true,
            'message' => 'File "' . $name . '" created successfully',
            'path' => $filePath,
            'size' => (int) $sizeRaw,
            'modified_ts' => $this->exploreTs($mtimeRaw),
        ]);
    }

    /**
     * Delete a file (rm) or a directory recursively (rm -rf), with hard
     * safety rails against wiping anything near the filesystem root.
     */
    public function exploreDelete(Request $request): JsonResponse
    {
        $request->validate([
            'host' => 'required|string',
            'hostname' => 'required|string',
            'username' => 'required|string',
            'identity_file' => 'required|string',
            'path' => 'required|string',
            'type' => 'nullable|string|in:F,D',
            'port' => 'nullable|integer',
        ]);

        $p = $this->exploreRequestParams($request);
        $err = $this->exploreCheckParams($p);
        if ($err) return $err;

        $path = rtrim($request->input('path'), '/');
        if (!$this->validateExplorePath($path)) {
            return response()->json(['success' => false, 'message' => 'Invalid path requested'], 422);
        }
        // Hard safety rails for a destructive (potentially recursive) delete:
        // never the root, never a top-level system directory like /etc or /usr.
        if ($path === '' || $path === '/' || substr_count($path, '/') < 2) {
            return response()->json(['success' => false, 'message' => 'Refusing to delete: the path is too close to the filesystem root.'], 422);
        }

        $type = $request->input('type') === 'D' ? 'D' : 'F';
        $cmd = $type === 'D'
            ? 'rm -rf -- ' . $this->remoteQuote($path) . ' 2>/dev/null'
            : 'rm -- ' . $this->remoteQuote($path) . ' 2>/dev/null';

        $base = $this->buildExploreSshBase($p['username'], $p['hostname'], $p['port'], $p['identity_file']);
        [$rc] = $this->exploreRun($base, $cmd);

        if ($rc !== 0) {
            return response()->json(['success' => false, 'message' => 'Could not delete "' . basename($path) . '" — the server denied permission or the item no longer exists.']);
        }
        return response()->json(['success' => true, 'message' => 'Deleted "' . basename($path) . '" successfully']);
    }

    /**
     * Change the permission (octal mode) of a file or folder — applied as
     * ROOT via non-interactive sudo when the server allows it, so root-owned
     * items can be fixed too. Refuses paths too close to the filesystem root
     * (same guard as delete) so no top-level system directory can be chmod'ed.
     */
    public function exploreChmod(Request $request): JsonResponse
    {
        $request->validate([
            'host' => 'required|string',
            'hostname' => 'required|string',
            'username' => 'required|string',
            'identity_file' => 'required|string',
            'path' => 'required|string',
            'perms' => 'required|string',
            'port' => 'nullable|integer',
        ]);

        $p = $this->exploreRequestParams($request);
        $err = $this->exploreCheckParams($p);
        if ($err) return $err;

        $path = rtrim($request->input('path'), '/');
        $perms = trim($request->input('perms'));
        if (!$this->validateExplorePath($path) || !preg_match('/^[0-7]{3,4}$/', $perms)) {
            return response()->json(['success' => false, 'message' => 'Invalid path or permission value requested'], 422);
        }
        if ($path === '/' || substr_count($path, '/') < 2) {
            return response()->json(['success' => false, 'message' => 'Refusing to change permission: the path is too close to the filesystem root.'], 422);
        }

        $base = $this->buildExploreSshBase($p['username'], $p['hostname'], $p['port'], $p['identity_file']);
        $cmd = 'chmod ' . $perms . ' -- ' . $this->remoteQuote($path)
            . ' && stat -c "%a|%U|%G" ' . $this->remoteQuote($path) . ' 2>/dev/null';
        [$rc, $out] = $this->exploreRun($base, $this->exploreAsRoot($cmd), 30);

        if ($rc !== 0 || empty($out)) {
            return response()->json(['success' => false, 'message' => 'Could not change the permission of "' . basename($path) . '" — the server denied the operation.']);
        }
        [$newPerms, $owner, $group] = array_pad(explode('|', trim($out[0]), 3), 3, '');
        return response()->json([
            'success' => true,
            'message' => 'Permission of "' . basename($path) . '" changed to ' . $newPerms,
            'perms' => $newPerms,
            'owner' => $owner,
            'group' => $group,
        ]);
    }

    /**
     * Duplicate (copy) a file within its directory under a new name.
     * Non-destructive: the source is never modified and an existing target
     * name is refused — nothing on the server is ever overwritten.
     * Directories are intentionally not duplicated (a recursive copy of a
     * huge tree could hammer the server's disk).
     */
    public function exploreDuplicate(Request $request): JsonResponse
    {
        $request->validate([
            'host' => 'required|string',
            'hostname' => 'required|string',
            'username' => 'required|string',
            'identity_file' => 'required|string',
            'path' => 'required|string',
            'new_name' => 'required|string',
            'port' => 'nullable|integer',
        ]);

        $p = $this->exploreRequestParams($request);
        $err = $this->exploreCheckParams($p);
        if ($err) return $err;

        $path = rtrim($request->input('path'), '/');
        $newName = trim($request->input('new_name'));
        if (!$this->validateExplorePath($path) || !$this->validateExploreName($newName)) {
            return response()->json(['success' => false, 'message' => 'Invalid path or name requested'], 422);
        }

        $parent = dirname($path);
        $target = ($parent === '/' ? '' : $parent) . '/' . $newName;
        if (!$this->validateExplorePath($target)) {
            return response()->json(['success' => false, 'message' => 'Invalid target path'], 422);
        }

        $base = $this->buildExploreSshBase($p['username'], $p['hostname'], $p['port'], $p['identity_file']);
        $cmd = 'if [ -d ' . $this->remoteQuote($path) . ' ]; then echo __IS_DIR__; exit 3; fi; '
            . 'if [ -e ' . $this->remoteQuote($target) . ' ]; then echo __TARGET_EXISTS__; exit 2; fi; '
            . 'cp -p -- ' . $this->remoteQuote($path) . ' ' . $this->remoteQuote($target) . ' 2>/dev/null'
            . ' && stat -c "%s|%Y" ' . $this->remoteQuote($target) . ' 2>/dev/null';
        [$rc, $out] = $this->exploreRun($base, $this->exploreAsRoot($cmd), 60);

        if ($rc !== 0) {
            $first = !empty($out) ? trim($out[0]) : '';
            if ($first === '__IS_DIR__') {
                return response()->json(['success' => false, 'message' => 'Duplicate is only available for files, not folders.']);
            }
            if ($first === '__TARGET_EXISTS__') {
                return response()->json(['success' => false, 'message' => 'A file named "' . $newName . '" already exists in this directory.']);
            }
            return response()->json(['success' => false, 'message' => 'Could not duplicate "' . basename($path) . '" — the server denied the operation.']);
        }
        [$sizeRaw, $mtimeRaw] = array_pad(explode('|', trim($out[0]), 2), 2, '');
        return response()->json([
            'success' => true,
            'message' => 'Duplicated to "' . $newName . '" successfully',
            'path' => $target,
            'size' => (int) $sizeRaw,
            'modified_ts' => $this->exploreTs($mtimeRaw),
        ]);
    }

    /**
     * Upload a file into the currently browsed directory. The bytes travel
     * over the existing SSH channel via stdin (no scp/SFTP needed) and the
     * write runs as root when sudo allows. Non-destructive: an existing
     * file with the same name is never overwritten.
     */
    public function exploreUpload(Request $request): JsonResponse
    {
        $request->validate([
            'host' => 'required|string',
            'hostname' => 'required|string',
            'username' => 'required|string',
            'identity_file' => 'required|string',
            'path' => 'required|string',
            'upload' => 'required|file|max:10240',
            'port' => 'nullable|integer',
        ]);

        $p = $this->exploreRequestParams($request);
        $err = $this->exploreCheckParams($p);
        if ($err) return $err;

        $parent = rtrim($request->input('path'), '/');
        if (!$this->validateExplorePath($parent)) {
            return response()->json(['success' => false, 'message' => 'Invalid path requested'], 422);
        }

        $upload = $request->file('upload');
        $name = basename((string) $upload->getClientOriginalName());
        if (!$this->validateExploreName($name)) {
            return response()->json(['success' => false, 'message' => 'Invalid file name: "' . $name . '"'], 422);
        }
        $target = ($parent === '/' ? '' : $parent) . '/' . $name;
        if (!$this->validateExplorePath($target)) {
            return response()->json(['success' => false, 'message' => 'Invalid upload path'], 422);
        }

        // Stage the bytes locally first so they can be piped via stdin
        $tmp = tempnam(sys_get_temp_dir(), 'sshup_');
        if ($tmp === false || !move_uploaded_file($upload->getRealPath(), $tmp)) {
            @unlink($tmp);
            return response()->json(['success' => false, 'message' => 'Could not stage the upload locally.']);
        }

        $base = $this->buildExploreSshBase($p['username'], $p['hostname'], $p['port'], $p['identity_file']);
        $cmd = 'if [ -e ' . $this->remoteQuote($target) . ' ]; then echo __TARGET_EXISTS__; exit 2; fi; '
            . 'cat > ' . $this->remoteQuote($target) . ' 2>/dev/null'
            . ' && chmod 644 -- ' . $this->remoteQuote($target) . ' 2>/dev/null'
            . ' && stat -c "%s|%Y" ' . $this->remoteQuote($target) . ' 2>/dev/null';
        $out = [];
        exec('timeout 120 ' . $base . ' ' . escapeshellarg($this->exploreAsRoot($cmd)) . ' < ' . escapeshellarg($tmp) . ' 2>/dev/null', $out, $rc);
        @unlink($tmp);

        if ($rc !== 0 || empty($out)) {
            if (!empty($out) && trim($out[0]) === '__TARGET_EXISTS__') {
                return response()->json(['success' => false, 'message' => 'A file named "' . $name . '" already exists in this directory — rename it first. Nothing was overwritten.']);
            }
            return response()->json(['success' => false, 'message' => 'Could not upload "' . $name . '" — the server denied the write.']);
        }
        [$sizeRaw, $mtimeRaw] = array_pad(explode('|', trim($out[0]), 2), 2, '');
        return response()->json([
            'success' => true,
            'message' => 'Uploaded "' . $name . '" (' . $this->formatBytes((int) $sizeRaw) . ') successfully',
            'path' => $target,
            'size' => (int) $sizeRaw,
            'modified_ts' => $this->exploreTs($mtimeRaw),
        ]);
    }

    public function exploreDownload(Request $request)
    {
        $request->validate([
            'host' => 'required|string',
            'hostname' => 'required|string',
            'username' => 'required|string',
            'identity_file' => 'required|string',
            'path' => 'required|string',
            'port' => 'nullable|integer',
        ]);

        $p = $this->exploreRequestParams($request);
        if (empty($p['host']) || empty($p['hostname']) || empty($p['username']) || empty($p['identity_file'])) {
            abort(422, 'Missing server connection details');
        }
        if (!file_exists($p['identity_file'])) {
            abort(422, 'Identity file not found');
        }

        $path = $request->input('path');
        if (!$this->validateExplorePath($path)) abort(422, 'Invalid path requested');

        $base = $this->buildExploreSshBase($p['username'], $p['hostname'], $p['port'], $p['identity_file']);
        $tmp = tempnam(sys_get_temp_dir(), 'sshfile_');
        $remoteCmd = 'cat ' . $this->remoteQuote($path) . ' 2>/dev/null';
        exec('timeout 300 ' . $base . ' ' . escapeshellarg($remoteCmd) . ' > ' . escapeshellarg($tmp) . ' 2>/dev/null', $output, $returnCode);

        $size = @filesize($tmp);
        if ($returnCode !== 0 || $size === false || $size <= 0) {
            @unlink($tmp);
            abort(500, 'Failed to download the file. It may not exist or is not readable.');
        }

        return response()->download($tmp, basename($path))->deleteFileAfterSend(true);
    }

    public function exploreZip(Request $request)
    {
        $request->validate([
            'host' => 'required|string',
            'hostname' => 'required|string',
            'username' => 'required|string',
            'identity_file' => 'required|string',
            'path' => 'required|string',
            'port' => 'nullable|integer',
        ]);

        $p = $this->exploreRequestParams($request);
        if (empty($p['host']) || empty($p['hostname']) || empty($p['username']) || empty($p['identity_file'])) {
            abort(422, 'Missing server connection details');
        }
        if (!file_exists($p['identity_file'])) {
            abort(422, 'Identity file not found');
        }

        $path = $request->input('path');
        if (!$this->validateExplorePath($path)) abort(422, 'Invalid path requested');

        $parent = dirname($path);
        $name = basename($path);
        if ($name === '' || $parent === '' || $parent === '.') {
            abort(422, 'Invalid directory requested');
        }

        $base = $this->buildExploreSshBase($p['username'], $p['hostname'], $p['port'], $p['identity_file']);
        $tmp = tempnam(sys_get_temp_dir(), 'sshzip_');

        $zipCmd = 'cd ' . $this->remoteQuote($parent) . ' && zip -rq - ' . $this->remoteQuote($name) . ' 2>/dev/null';
        exec('timeout 600 ' . $base . ' ' . escapeshellarg($zipCmd) . ' > ' . escapeshellarg($tmp) . ' 2>/dev/null', $output, $returnCode);

        $usedTar = false;
        $size = @filesize($tmp);
        if ($returnCode !== 0 || $size === false || $size <= 0) {
            // zip not installed / produced no output -> fall back to tar.gz
            $tarCmd = 'cd ' . $this->remoteQuote($parent) . ' && tar -czf - ' . $this->remoteQuote($name) . ' 2>/dev/null';
            exec('timeout 600 ' . $base . ' ' . escapeshellarg($tarCmd) . ' > ' . escapeshellarg($tmp) . ' 2>/dev/null', $output2, $returnCode2);
            $size = @filesize($tmp);
            if ($returnCode2 !== 0 || $size === false || $size <= 0) {
                @unlink($tmp);
                abort(500, 'Failed to create archive. Ensure the directory is readable, or install zip/tar on the server.');
            }
            $usedTar = true;
        }

        $filename = $name . ($usedTar ? '.tar.gz' : '.zip');
        $contentType = $usedTar ? 'application/gzip' : 'application/zip';

        return response()->download($tmp, $filename, ['Content-Type' => $contentType])->deleteFileAfterSend(true);
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

        $server = SshServer::where('host', $request->host)->first();

        if (!$server) {
            return response()->json([
                'success' => false,
                'message' => 'Server not found'
            ], 404);
        }

        $server->is_favorite = !$server->is_favorite;
        $server->save();

        return response()->json([
            'success' => true,
            'is_favorite' => $server->is_favorite,
            'favorites' => SshServer::where('is_favorite', true)->pluck('host')->toArray()
        ]);
    }
    
    /**
     * Get favorite servers
     */
    public function getFavorites()
    {
        $favorites = SshServer::where('is_favorite', true)->pluck('host')->toArray();
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

    /**
     * Get Proxy Server Health Information
     * Returns basic system and proxy health info for the server
     */
    public function getProxyServerHealth($host)
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
            
            $health = [
                'server' => $host,
                'hostname' => $targetHost['hostname'],
                'user' => $targetHost['user'],
                'overall_status' => 'unknown',
                'details' => []
            ];
            
            $keyPath = $this->expandPath($targetHost['identity_file']);
            $keyExists = file_exists($keyPath);
            $health['details']['key_exists'] = $keyExists;
            $health['details']['key_path'] = $keyPath;
            if ($keyExists) {
                $perms = substr(sprintf('%o', fileperms($keyPath)), -4);
                $health['details']['key_permissions'] = $perms;
                $health['details']['key_size_bytes'] = filesize($keyPath);
            }
            
            try {
                $sshConfig = [
                    'host' => $targetHost['host'],
                    'hostname' => $targetHost['hostname'],
                    'user' => $targetHost['user'],
                    'port' => $targetHost['port'] ?? 22,
                    'identity_file' => $keyPath,
                ];
                
                $ssh = $this->connectToServer($sshConfig);
                
                if ($ssh && $ssh->isAuthenticated()) {
                    $health['details']['uptime'] = trim($ssh->exec('uptime 2>/dev/null || echo N/A'));
                    $health['details']['load_average'] = trim($ssh->exec('cat /proc/loadavg 2>/dev/null || echo N/A'));
                    $health['details']['cpu_usage'] = trim($ssh->exec('top -bn1 2>/dev/null | grep "Cpu(s)" | head -1 || echo N/A'));
                    $health['details']['memory'] = trim($ssh->exec('free -h 2>/dev/null | grep Mem || echo N/A'));
                    $health['details']['disk_usage'] = trim($ssh->exec('df -h / 2>/dev/null | tail -1 || echo N/A'));
                    $health['details']['cpu_info'] = trim($ssh->exec('lscpu 2>/dev/null | grep "Model name:" | head -1 || echo N/A'));
                    $health['details']['cpu_count'] = trim($ssh->exec('nproc 2>/dev/null || echo N/A'));
                    $health['details']['kernel'] = trim($ssh->exec('uname -r 2>/dev/null || echo N/A'));
                    $health['details']['os'] = trim($ssh->exec('cat /etc/os-release 2>/dev/null | grep PRETTY_NAME | head -1 | cut -d= -f2 || echo N/A'));
                    $health['details']['architecture'] = trim($ssh->exec('uname -m 2>/dev/null || echo N/A'));
                    $health['details']['hostname_resolved'] = trim($ssh->exec('hostname 2>/dev/null || echo N/A'));
                    $health['details']['current_user'] = trim($ssh->exec('whoami 2>/dev/null || echo N/A'));
                    $health['details']['home_directory'] = trim($ssh->exec('echo $HOME 2>/dev/null || echo N/A'));
                    $health['details']['ssh_service'] = trim($ssh->exec('systemctl is-active sshd 2>/dev/null || systemctl is-active ssh 2>/dev/null || echo inactive'));
                    $health['details']['fail2ban'] = trim($ssh->exec('systemctl is-active fail2ban 2>/dev/null || echo inactive'));
                    $health['details']['ufw'] = trim($ssh->exec('ufw status 2>/dev/null | head -1 || echo N/A'));
                    $health['details']['swap'] = trim($ssh->exec('free -h 2>/dev/null | grep Swap || echo N/A'));
                    $health['details']['cpu_usage_top'] = trim($ssh->exec('top -bn1 2>/dev/null | head -20 || echo N/A'));
                    $health['details']['memory_usage_top'] = trim($ssh->exec('ps aux --sort=-%mem 2>/dev/null | head -11 || echo N/A'));
                    $health['details']['cpu_usage_ps'] = trim($ssh->exec('ps aux --sort=-%cpu 2>/dev/null | head -11 || echo N/A'));
                    $health['details']['disk_io'] = trim($ssh->exec('iostat -d -x 1 2 2>/dev/null | head -20 || echo N/A'));
                    $health['details']['zombie_processes'] = trim($ssh->exec('ps aux 2>/dev/null | awk "{print \$8}" | grep -c "Z" || echo N/A'));
                    $health['details']['total_processes'] = trim($ssh->exec('ps aux 2>/dev/null | wc -l || echo N/A'));
                    $health['details']['open_ports'] = trim($ssh->exec('ss -tlnp 2>/dev/null | grep LISTEN | head -10 || netstat -tlnp 2>/dev/null | grep LISTEN | head -10 || echo N/A'));
                    $health['details']['established_connections'] = trim($ssh->exec('ss -tnp 2>/dev/null | grep ESTAB | wc -l || netstat -tnp 2>/dev/null | grep ESTAB | wc -l || echo N/A'));
                    $health['details']['outbound_connections'] = trim($ssh->exec('ss -tnp state established 2>/dev/null | grep -v "127.0.0.1\|::1" | head -15 || echo N/A'));
                    $health['details']['open_fd'] = trim($ssh->exec('cat /proc/sys/fs/file-nr 2>/dev/null || echo N/A'));
                    $health['details']['fd_limit'] = trim($ssh->exec('ulimit -n 2>/dev/null || echo N/A'));
                    $health['details']['inodes'] = trim($ssh->exec('df -i / 2>/dev/null | tail -1 || echo N/A'));
                    $health['details']['ntp_sync'] = trim($ssh->exec('timedatectl 2>/dev/null | grep -i "ntp\|synchronized" || chronyc tracking 2>/dev/null | head -5 || echo N/A'));
                    $health['details']['timezone'] = trim($ssh->exec('timedatectl 2>/dev/null | grep "Time zone" | awk "{print \$3}" || echo N/A'));
                    $health['details']['language'] = trim($ssh->exec('locale 2>/dev/null | grep LANG | head -1 || echo N/A'));
                    $health['details']['last_reboot'] = trim($ssh->exec('last reboot 2>/dev/null | head -3 || who -b 2>/dev/null || echo N/A'));
                    $health['details']['sshd_failed_logins'] = trim($ssh->exec("journalctl -u sshd --since '24 hours ago' 2>/dev/null | grep -ci 'failed password' || grep -ci 'failed password' /var/log/auth.log 2>/dev/null || echo N/A"));
                    $health['details']['systemd_failed_services'] = trim($ssh->exec('systemctl --failed --no-pager --plain 2>/dev/null | head -20 || echo N/A'));
                    $health['details']['cron_status'] = trim($ssh->exec('systemctl is-active cron 2>/dev/null || systemctl is-active crond 2>/dev/null || echo N/A'));
                    $health['details']['anacron_status'] = trim($ssh->exec('systemctl is-active anacron 2>/dev/null || echo N/A'));
                    $health['details']['ssl_cert_check'] = trim($ssh->exec("echo | timeout 5 openssl s_client -connect 127.0.0.1:443 -servername \$(hostname) 2>/dev/null | openssl x509 -noout -startdate -enddate -issuer -subject -ext subjectAltName,signatureAlgorithm,serialNumber 2>/dev/null || echo N/A"));
                    $health['details']['ssl_cert_check_raw'] = trim($ssh->exec("echo Q | timeout 5 openssl s_client -connect 127.0.0.1:443 -servername \$(hostname) 2>/dev/null | grep -E 'Protocol  |Cipher  ' || echo N/A"));
                    
                    if (!empty($targetHost['domains']) && is_array($targetHost['domains'])) {
                        foreach ($targetHost['domains'] as $domain) {
                            try {
                                $safeDomain = escapeshellarg($domain);
                                $sslRaw = trim($ssh->exec("echo | timeout 5 openssl s_client -connect 127.0.0.1:443 -servername {$safeDomain} 2>/dev/null | openssl x509 -noout -startdate -enddate -issuer -subject -ext subjectAltName,signatureAlgorithm,serialNumber 2>/dev/null || echo N/A"));
                                if ($sslRaw && $sslRaw !== 'N/A') {
                                    $key = 'ssl_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $domain);
                                    $health['details'][$key] = $sslRaw;
                                }
                                $sslRaw2 = trim($ssh->exec("echo Q | timeout 5 openssl s_client -connect 127.0.0.1:443 -servername {$safeDomain} 2>/dev/null | grep -E 'Protocol  |Cipher  ' || echo N/A"));
                                if ($sslRaw2 && $sslRaw2 !== 'N/A') {
                                    $key2 = 'ssl_raw_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $domain);
                                    $health['details'][$key2] = $sslRaw2;
                                }
                            } catch (\Throwable $e) {
                                continue;
                            }
                        }
                    }

                    $sslConfigPaths = [
                        '/etc/apache2/sites-enabled/000-default-le-ssl.conf',
                        '/etc/apache2/sites-enabled/default-ssl.conf',
                        '/etc/apache2/sites-enabled/001-ssl.conf',
                        '/etc/httpd/conf.d/ssl.conf',
                        '/etc/httpd/conf.d/default-ssl.conf',
                        '/etc/apache2/sites-available/000-default-le-ssl.conf',
                        '/etc/apache2/sites-available/default-ssl.conf',
                    ];

                    $sslConfigContent = null;
                    foreach ($sslConfigPaths as $path) {
                        $content = $ssh->exec("cat " . escapeshellarg($path) . " 2>/dev/null");
                        if (!empty($content) && str_contains($content, 'VirtualHost')) {
                            $sslConfigContent = $content;
                            break;
                        }
                    }

                    if (!$sslConfigContent) {
                        $sslFiles = trim($ssh->exec("ls /etc/apache2/sites-enabled/ 2>/dev/null | grep -i ssl"));
                        if (!empty($sslFiles)) {
                            $first = explode("\n", $sslFiles)[0];
                            $sslConfigContent = $ssh->exec("cat /etc/apache2/sites-enabled/" . escapeshellarg($first) . " 2>/dev/null");
                        }
                    }

                    if ($sslConfigContent) {
                        $sslDomains = [];
                        preg_match_all('/ServerName\s+(\S+)/i', $sslConfigContent, $serverNameMatches);
                        preg_match_all('/ServerAlias\s+(\S+)/i', $sslConfigContent, $serverAliasMatches);
                        $sslDomains = array_unique(array_merge($serverNameMatches[1], $serverAliasMatches[1]));

                        $existingKeys = array_keys($health['details']);
                        $existingDomains = [];
                        foreach ($existingKeys as $ek) {
                            if (preg_match('/^ssl(?:_raw_)?(.+)$/', $ek, $m)) {
                                $existingDomains[] = str_replace('_', '.', $m[1]);
                            }
                        }

                        foreach ($sslDomains as $sslDomain) {
                            $safeDomain = escapeshellarg($sslDomain);
                            $key = 'ssl_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $sslDomain);
                            if (isset($health['details'][$key])) continue;

                            try {
                                $sslRaw = trim($ssh->exec("echo | timeout 5 openssl s_client -connect 127.0.0.1:443 -servername {$safeDomain} 2>/dev/null | openssl x509 -noout -startdate -enddate -issuer -subject -ext subjectAltName,signatureAlgorithm,serialNumber 2>/dev/null || echo N/A"));
                                if (!empty($sslRaw) && $sslRaw !== 'N/A') {
                                    $health['details'][$key] = $sslRaw;
                                }
                            } catch (\Throwable $e) {
                                continue;
                            }

                            try {
                                $sslRaw2 = trim($ssh->exec("echo Q | timeout 5 openssl s_client -connect 127.0.0.1:443 -servername {$safeDomain} 2>/dev/null | grep -E 'Protocol  |Cipher  ' || echo N/A"));
                                if (!empty($sslRaw2) && $sslRaw2 !== 'N/A') {
                                    $key2 = 'ssl_raw_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $sslDomain);
                                    $health['details'][$key2] = $sslRaw2;
                                }
                            } catch (\Throwable $e) {
                                continue;
                            }
                        }
                    }
                    
                    $os = strtolower($health['details']['os']);
                    $pkgCmd = '';
                    if (str_contains($os, 'debian') || str_contains($os, 'ubuntu')) {
                        $pkgCmd = 'apt list --upgradable 2>/dev/null | wc -l';
                    } elseif (str_contains($os, 'centos') || str_contains($os, 'rhel') || str_contains($os, 'fedora')) {
                        $pkgCmd = 'yum check-update 2>/dev/null | grep "^\\\\. " | wc -l';
                    } else {
                        $pkgCmd = 'true';
                    }
                    $health['details']['pending_updates'] = trim($ssh->exec($pkgCmd));
                    
                    $updates = intval($health['details']['pending_updates'] ?? 0);
                    $overall = 'healthy';
                    
                    if (!empty($health['details']['ssh_service']) && $health['details']['ssh_service'] !== 'active') {
                        $overall = 'error';
                    } elseif ($updates > 50) {
                        $overall = 'warning';
                    }
                    
                    $health['overall_status'] = $overall;
                } else {
                    $health['overall_status'] = 'error';
                    $health['details']['connection'] = false;
                    $health['details']['error'] = 'SSH authentication failed';
                }
            } catch (\Exception $e) {
                $health['overall_status'] = 'error';
                $health['details']['error'] = $e->getMessage();
            }
            
            return response()->json([
                'success' => true,
                'host' => $host,
                'health' => $health
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

    /* =====================================================================
     |  SSL INSTALLATION — Let's Encrypt (free) & Paid SSL
     |  Every remote operation runs through the explorer SSH base with the
     |  same non-interactive root escalation used by the file explorer
     |  (exploreAsRoot), so it can never hang waiting for a password.
     * ===================================================================== */

    /**
     * Install a FREE Let's Encrypt certificate on the remote server.
     *
     * Flow (each step is reported back to the UI):
     *  1. Detect the server's public IP (curl ifconfig.me style, ON the server).
     *  2. Resolve the domain's A record ON the server and compare it with the
     *     server IP — abort with an error when the domain is not pointed here.
     *  3. Verify the domain appears in the enabled Apache VirtualHosts — when
     *     missing, a port-80 VirtualHost is created with the chosen directory.
     *  4. Ensure certbot + the Apache plugin are installed (auto-install).
     *  5. Abort ONLY when SSL is ACTIVELY configured for the domain in the
     *     enabled Apache vhosts (000-default-le-ssl.conf etc.). A stale
     *     /etc/letsencrypt/renewal/<domain>.conf lineage alone is NOT a
     *     blocker — the cert is re-issued and re-deployed instead.
     *  6. Run certbot non-interactively WITHOUT an email:
     *       certbot --apache --non-interactive --agree-tos
     *               --register-unsafely-without-email --redirect -d <domain>
     *  7. Verify OUR standard config — the enabled vhost must reference
     *       SSLCertificateFile /etc/letsencrypt/live/<domain>/fullchain.pem
     *       SSLCertificateKeyFile /etc/letsencrypt/live/<domain>/privkey.pem
     *     When certbot issued the cert but did not deploy the vhost (e.g. the
     *     previous SSL vhost was removed), the standard block is appended here
     *     — exactly these two lines plus the standard Include, nothing else.
     */
    public function installLetsEncryptSsl(Request $request): JsonResponse
    {
        @set_time_limit(0);

        $request->validate([
            'host' => 'required|string',
            'hostname' => 'required|string',
            'username' => 'required|string',
            'identity_file' => 'required|string',
            'port' => 'nullable|integer',
            'domain' => 'required|string',
        ]);

        $p = $this->exploreRequestParams($request);
        $err = $this->exploreCheckParams($p);
        if ($err) return $err;

        $domain = $this->sslNormalizeDomain((string) $request->input('domain'));
        if ($domain === null) {
            return response()->json([
                'success' => false,
                'message' => 'Please enter a valid domain name (e.g. example.com).',
            ]);
        }

        $docroot = $this->sslNormalizeDocroot((string) $request->input('docroot', ''));
        if ($docroot === null) {
            return response()->json([
                'success' => false,
                'message' => 'Please enter a valid absolute directory path (e.g. /var/www/your-project/public).',
            ]);
        }

        $steps = [];
        $base = $this->buildExploreSshBase($p['username'], $p['hostname'], $p['port'], $p['identity_file']);

        // ---- Step 1: public IP of the server (detected ON the server itself)
        $steps[] = ['name' => "Detecting public IP of {$p['hostname']}", 'status' => 'running', 'output' => ''];
        [$rc, $out] = $this->sslRun($base,
            'curl -s --max-time 10 https://api.ipify.org 2>/dev/null'
            . ' || curl -s --max-time 10 https://ifconfig.me 2>/dev/null'
            . ' || curl -s --max-time 10 https://icanhazip.com 2>/dev/null'
            . ' || curl -s --max-time 10 http://checkip.amazonaws.com 2>/dev/null',
            45);
        $serverIp = $this->sslFirstIp(implode("\n", $out));
        if ($serverIp === null) {
            $steps[0]['status'] = 'error';
            $steps[0]['output'] = trim(implode("\n", $out)) ?: 'No output';
            return response()->json([
                'success' => false,
                'message' => "Could not detect the public IP of {$p['hostname']}. Check that the server has internet access (curl).",
                'steps' => $steps,
            ]);
        }
        $steps[0]['status'] = 'ok';
        $steps[0]['output'] = $serverIp;

        // ---- Step 2: DNS check — the domain must point to this server
        $steps[] = ['name' => "Verifying DNS: {$domain} → server", 'status' => 'running', 'output' => ''];
        $dnsCmd = '(dig +short A ' . $domain . ' 2>/dev/null; '
            . 'getent ahostsv4 ' . $domain . ' 2>/dev/null | awk \'{print $1}\'; '
            . 'host -t A ' . $domain . ' 2>/dev/null | awk \'/has address/{print $4}\') '
            . '| grep -E \'^[0-9]{1,3}(\\.[0-9]{1,3}){3}$\' | sort -u';
        [$rc, $out] = $this->sslRun($base, $dnsCmd, 45);
        $resolvedIps = array_values(array_filter(array_map('trim', $out), function ($l) {
            return filter_var($l, FILTER_VALIDATE_IP) !== false;
        }));
        $steps[1]['output'] = !empty($resolvedIps)
            ? 'Domain resolves to: ' . implode(', ', $resolvedIps)
            : 'The domain did not resolve to any IP address';
        if (empty($resolvedIps)) {
            $steps[1]['status'] = 'error';
            return response()->json([
                'success' => false,
                'message' => "DNS check failed: '{$domain}' does not resolve to any IP address yet. Add an A record pointing to {$serverIp} and try again.",
                'steps' => $steps,
            ]);
        }
        if (!in_array($serverIp, $resolvedIps, true)) {
            $steps[1]['status'] = 'error';
            return response()->json([
                'success' => false,
                'message' => "Domain '{$domain}' points to " . implode(', ', $resolvedIps) . " but the server IP is {$serverIp}. Point the domain to this server first, then retry.",
                'steps' => $steps,
            ]);
        }
        $steps[1]['status'] = 'ok';
        $steps[1]['output'] .= " — matches server IP {$serverIp}";

        // ---- Step 3: ensure a port-80 VirtualHost exists (created with the chosen
        //      directory when missing) and that the required Apache modules are on
        $steps[] = ['name' => 'Checking Apache VirtualHost for the domain', 'status' => 'running', 'output' => ''];

        [$rc, $out] = $this->sslRun($base,
            'if apache2ctl -M 2>/dev/null | grep -q rewrite_module; then echo MOD_REWRITE_OK; else a2enmod rewrite >/dev/null 2>&1 && echo MOD_REWRITE_ENABLED || echo MOD_REWRITE_SKIPPED; fi; '
            . 'if apache2ctl -M 2>/dev/null | grep -q ssl_module; then echo MOD_SSL_OK; else a2enmod ssl >/dev/null 2>&1 && echo MOD_SSL_ENABLED || echo MOD_SSL_SKIPPED; fi',
            60);
        $modNotes = [trim(implode("\n", $out))];

        [$rc, $out] = $this->sslRun($base, "grep -ils '{$domain}' /etc/apache2/sites-enabled/* 2>/dev/null | head -5", 30);
        $vhostFiles = array_values(array_filter(array_map('trim', $out)));

        if (!empty($vhostFiles)) {
            // Domain already configured — existing VirtualHosts are never modified
            $steps[2]['status'] = 'ok';
            $steps[2]['output'] = implode("\n", $modNotes)
                . "\nDomain already configured in:\n" . implode("\n", $vhostFiles)
                . "\nNote: the entered directory was not applied — existing VirtualHosts are left untouched";
        } else {
            $port80Path = null;
            $port80Content = '';
            foreach (['/etc/apache2/sites-enabled/000-default.conf', '/etc/apache2/sites-available/000-default.conf'] as $cand) {
                [$rc, $out] = $this->sslRun($base, 'cat ' . $cand . ' 2>/dev/null', 30);
                $content = implode("\n", $out);
                if ($rc === 0 && trim($content) !== '') {
                    $port80Path = $cand;
                    $port80Content = $content;
                    break;
                }
            }

            $block = $this->sslBuildPort80Vhost($domain, $docroot);
            if ($port80Path !== null) {
                $this->sslRun($base, 'cp -a ' . $port80Path . ' ' . $port80Path . '.bak-' . date('YmdHis') . ' && echo BACKUP_OK', 30);
                $newPort80Content = rtrim($port80Content) . "\n\n" . $block . "\n";
                $writeNote = "Added a port-80 VirtualHost for {$domain} to {$port80Path} (DocumentRoot: {$docroot})";
            } else {
                $port80Path = '/etc/apache2/sites-enabled/000-default.conf';
                $newPort80Content = $block . "\n";
                $writeNote = "Created {$port80Path} with a port-80 VirtualHost for {$domain} (DocumentRoot: {$docroot})";
            }

            $writeLog = '';
            if (!$this->sslUploadFile($base, $port80Path, $newPort80Content, 0644, $writeLog)) {
                $steps[2]['status'] = 'error';
                $steps[2]['output'] = implode("\n", $modNotes) . "\nFailed to write {$port80Path}\n" . $writeLog;
                return response()->json([
                    'success' => false,
                    'message' => "Could not create the Apache VirtualHost for {$domain} — review the output below.",
                    'steps' => $steps,
                ]);
            }
            $modNotes[] = $writeNote;
            $steps[2]['status'] = 'ok';
            $steps[2]['output'] = implode("\n", $modNotes);
        }

        // ---- Step 4: certbot present? auto-install when missing
        $steps[] = ['name' => 'Checking / installing certbot', 'status' => 'running', 'output' => ''];
        $certbotEnsureCmd =
            'if command -v certbot >/dev/null 2>&1; then echo CERTBOT_PRESENT $(certbot --version 2>&1 | head -n1); '
            . 'else echo CERTBOT_MISSING; '
            . 'if command -v apt-get >/dev/null 2>&1; then export DEBIAN_FRONTEND=noninteractive; apt-get update -y >/dev/null 2>&1; apt-get install -y certbot python3-certbot-apache; '
            . 'elif command -v dnf >/dev/null 2>&1; then dnf install -y certbot python3-certbot-apache || dnf install -y certbot; '
            . 'elif command -v yum >/dev/null 2>&1; then yum install -y certbot python3-certbot-apache || yum install -y certbot; '
            . 'elif command -v apk >/dev/null 2>&1; then apk add --no-cache certbot certbot-apache; '
            . 'else echo NO_KNOWN_PACKAGE_MANAGER; fi; fi; '
            . 'if command -v certbot >/dev/null 2>&1; then echo CERTBOT_READY; else echo CERTBOT_INSTALL_FAILED; fi';
        [$rc, $out] = $this->sslRun($base, $certbotEnsureCmd, 600);
        $ensureOut = trim(implode("\n", $out));
        $steps[3]['output'] = $ensureOut !== '' ? $ensureOut : 'no output';
        if (strpos($ensureOut, 'CERTBOT_READY') === false) {
            $steps[3]['status'] = 'error';
            return response()->json([
                'success' => false,
                'message' => 'certbot could not be installed automatically on the server. Install certbot + the Apache plugin manually and retry.',
                'steps' => $steps,
            ]);
        }
        $steps[3]['status'] = 'ok';

        // ---- Step 5: is SSL ACTIVELY configured for the domain in Apache?
        // The decision is based on the enabled vhosts (000-default.conf /
        // 000-default-le-ssl.conf etc.) — NOT on the certbot lineage alone:
        // a stale /etc/letsencrypt/renewal/<domain>.conf from a previous
        // install must NOT block a re-install.
        // NOTE: glob on purpose (not grep -r) — sites-enabled entries are
        // usually SYMLINKS to sites-available and `grep -r` does not follow
        // them, which silently skipped e.g. 000-default-le-ssl.conf.
        $steps[] = ['name' => 'Checking the Apache SSL configuration for the domain', 'status' => 'running', 'output' => ''];

        [$rc, $out] = $this->sslRun($base,
            'if [ -f /etc/letsencrypt/renewal/' . $domain . '.conf ]; then echo LE_RENEWAL_CONF_EXISTS; fi; '
            . 'echo RENEWAL_CHECK_DONE',
            20);
        $renewalConfExists = strpos(implode("\n", $out), 'LE_RENEWAL_CONF_EXISTS') !== false;

        [$rc, $out] = $this->sslRun($base,
            "grep -ils 'SSLCertificateFile' /etc/apache2/sites-enabled/* 2>/dev/null | xargs -r grep -ils 'servername " . $domain . "' 2>/dev/null",
            30);
        $activeSslFiles = array_values(array_filter(array_map('trim', $out)));

        if (!empty($activeSslFiles)) {
            // The domain is genuinely served over SSL — nothing to install
            $steps[4]['status'] = 'error';
            $steps[4]['output'] = "SSL is actively configured for {$domain} in:\n" . implode("\n", $activeSslFiles);
            return response()->json([
                'success' => false,
                'message' => "SSL is already installed and actively configured for {$domain}. Nothing was changed.",
                'steps' => $steps,
            ]);
        }

        $steps[4]['status'] = 'ok';
        $steps[4]['output'] = $renewalConfExists
            ? "No active SSL VirtualHost for {$domain} in the enabled Apache configs — proceeding. A stale Let's Encrypt lineage exists (/etc/letsencrypt/renewal/{$domain}.conf); the certificate will be re-issued and re-deployed."
            : 'No SSL configured for this domain — proceeding with certbot';

        // ---- Step 6: run certbot (no email, exact domain only, auto redirect).
        // With a stale lineage (cert exists but no vhost uses it) a plain run
        // would answer "Certificate not yet due for renewal; no action taken"
        // and deploy NOTHING — force the re-issue + vhost deployment then.
        $steps[] = ['name' => "Running certbot for {$domain} (may take a few minutes)", 'status' => 'running', 'output' => ''];
        $certbotCmd = 'certbot --apache --non-interactive --agree-tos --register-unsafely-without-email --redirect '
            . ($renewalConfExists ? '--force-renewal ' : '')
            . '-d ' . $domain . ' 2>&1';
        [$rc, $out] = $this->sslRun($base, $certbotCmd, 600);
        $certbotOut = trim(implode("\n", $out));
        $steps[5]['output'] = $certbotOut !== '' ? $certbotOut : '(no output)';
        if ($rc !== 0) {
            $steps[5]['status'] = 'error';
            return response()->json([
                'success' => false,
                'message' => 'certbot failed to issue the certificate — review the output below. Nothing else was changed.',
                'steps' => $steps,
            ]);
        }
        $steps[5]['status'] = 'ok';

        // ---- Step 7: verify OUR standard config — the enabled vhost must use
        //      exactly these paths (nothing else is accepted):
        //        SSLCertificateFile /etc/letsencrypt/live/<domain>/fullchain.pem
        //        SSLCertificateKeyFile /etc/letsencrypt/live/<domain>/privkey.pem
        $steps[] = ['name' => 'Verifying the standard SSL configuration', 'status' => 'running', 'output' => ''];
        $stdCertLine = 'SSLCertificateFile /etc/letsencrypt/live/' . $domain . '/fullchain.pem';
        $stdKeyLine = 'SSLCertificateKeyFile /etc/letsencrypt/live/' . $domain . '/privkey.pem';
        $verifyCmd =
            'if [ -f /etc/letsencrypt/live/' . $domain . '/fullchain.pem ]; then echo VERIFY_CERT_OK; else echo VERIFY_CERT_MISSING; fi; '
            . 'if grep -ils "SSLCertificateFile" /etc/apache2/sites-enabled/* 2>/dev/null | xargs -r grep -ils "servername ' . $domain . '" 2>/dev/null | grep -q .; then echo VERIFY_VHOST_OK; else echo VERIFY_VHOST_MISSING; fi; '
            . 'if grep -ils "' . $stdCertLine . '" /etc/apache2/sites-enabled/* 2>/dev/null | grep -q .; then echo VERIFY_STDCERT_OK; else echo VERIFY_STDCERT_MISSING; fi; '
            . 'if grep -ils "' . $stdKeyLine . '" /etc/apache2/sites-enabled/* 2>/dev/null | grep -q .; then echo VERIFY_STDKEY_OK; else echo VERIFY_STDKEY_MISSING; fi; '
            . 'echo VERIFY_DONE';
        [$rc, $out] = $this->sslRun($base, $verifyCmd, 60);
        $verifyOut = trim(implode("\n", $out));
        $steps[6]['output'] = $verifyOut !== '' ? $verifyOut : 'no output';

        if (strpos($verifyOut, 'VERIFY_CERT_MISSING') !== false) {
            $steps[6]['status'] = 'error';
            return response()->json([
                'success' => false,
                'message' => "certbot reported success but the certificate files were not found under /etc/letsencrypt/live/{$domain}/ — review the output above.",
                'steps' => $steps,
            ]);
        }

        if (strpos($verifyOut, 'VERIFY_VHOST_OK') !== false && strpos($verifyOut, 'VERIFY_STDCERT_MISSING') !== false) {
            // An SSL vhost exists but does NOT use our standard paths
            $steps[6]['status'] = 'error';
            return response()->json([
                'success' => false,
                'message' => "The SSL VirtualHost for {$domain} exists but does not use our standard certificate path ({$stdCertLine}). Fix it manually or remove that vhost block and reinstall.",
                'steps' => $steps,
            ]);
        }

        // ---- Normalize the -le-ssl conf BEFORE the final checks: certbot
        // leaves commented-out redirect rules ("disabled on your HTTPS site …
        // redirection loops") and sometimes misplaced *:80 copies of the
        // vhost. Strip that noise, drop broken duplicates of our domain and
        // make sure exactly ONE standard *:443 block exists — other domains
        // are never touched.
        $notes = [];
        $sslEnabledPath = '/etc/apache2/sites-enabled/000-default-le-ssl.conf';
        $sslAvailablePath = '/etc/apache2/sites-available/000-default-le-ssl.conf';
        $sslConfPath = null;
        $sslConfContent = null;
        foreach ([$sslEnabledPath, $sslAvailablePath] as $cand) {
            [$rc, $out] = $this->sslRun($base, 'cat ' . $cand . ' 2>/dev/null', 30);
            $content = implode("\n", $out);
            if ($rc === 0 && trim($content) !== '') { $sslConfPath = $cand; $sslConfContent = $content; break; }
        }

        $flags = ['noise_removed' => false, 'http_block_removed' => false, 'broken_block_removed' => false];
        $normalized = '';
        if ($sslConfContent !== null) {
            [$normalized, $flags] = $this->sslNormalizeLeSslConf($sslConfContent, $domain, $stdCertLine);
        }

        // our standard block must be present exactly once
        if ($normalized === '' || strpos($normalized, $stdCertLine) === false || strpos($normalized, $stdKeyLine) === false) {
            $docrootDeploy = $docroot;
            foreach (['/etc/apache2/sites-enabled/000-default.conf', '/etc/apache2/sites-available/000-default.conf'] as $cand) {
                [$rc, $out] = $this->sslRun($base, 'cat ' . $cand . ' 2>/dev/null', 30);
                $content = implode("\n", $out);
                if ($rc === 0 && trim($content) !== '') {
                    foreach ($this->parseVirtualHosts($content) as $v) {
                        if (in_array($domain, $v['domains'], true) && !empty($v['document_root'])) { $docrootDeploy = $v['document_root']; }
                    }
                    break;
                }
            }
            [$rc, $out] = $this->sslRun($base, '[ -f /etc/letsencrypt/options-ssl-apache.conf ] && echo OPTS_EXISTS || echo OPTS_MISSING', 20);
            $block = $this->sslBuildSslVhostBlock(
                $domain,
                $docrootDeploy,
                '/etc/letsencrypt/live/' . $domain . '/fullchain.pem',
                '/etc/letsencrypt/live/' . $domain . '/privkey.pem',
                null,
                strpos(implode("\n", $out), 'OPTS_EXISTS') !== false
            );
            $normalized = ($normalized !== '' ? rtrim($normalized) . "\n\n" : '') . $block . "\n";
        }

        $stamp = date('YmdHis');
        $wrote = false;
        $leBackupPath = null;
        $leCreated = false;
        if ($sslConfContent === null || rtrim($sslConfContent) !== rtrim($normalized)) {
            if ($sslConfPath !== null) {
                $leBackupPath = $sslConfPath . '.bak-' . $stamp;
                $this->sslRun($base, 'cp -a ' . $sslConfPath . ' ' . $leBackupPath . ' && echo BACKUP_OK', 30);
            } else {
                $sslConfPath = $sslEnabledPath;
                $leCreated = true;
            }

            $writeLog = '';
            if (!$this->sslUploadFile($base, $sslConfPath, $normalized, 0644, $writeLog)) {
                $steps[6]['status'] = 'error';
                $steps[6]['output'] = "Failed to write the cleaned SSL configuration to {$sslConfPath}\n" . $writeLog;
                return response()->json(['success' => false, 'message' => "Failed to write the cleaned SSL configuration to {$sslConfPath}.", 'steps' => $steps]);
            }
            if ($sslConfPath === $sslAvailablePath) {
                $this->sslRun($base, '[ -e ' . $sslEnabledPath . ' ] || ln -sf ' . $sslAvailablePath . ' ' . $sslEnabledPath . '; echo LINK_OK', 30);
            }
            $wrote = true;
            if ($flags['noise_removed']) { $notes[] = "Removed certbot's commented-out rewrite noise from {$sslConfPath}"; }
            if ($flags['http_block_removed']) { $notes[] = 'Removed the misplaced *:80 vhost copy from the SSL config (the real one lives in the port-80 config)'; }
            if ($flags['broken_block_removed']) { $notes[] = 'Removed a broken/duplicate vhost for the domain (non-standard paths)'; }
            if ($leCreated) { $notes[] = "Created {$sslConfPath}"; }
        }

        // the port-80 → https redirect must be ACTIVE in 000-default.conf
        $p80Path = null;
        $p80BackupPath = null;
        $p80Content = '';
        foreach (['/etc/apache2/sites-enabled/000-default.conf', '/etc/apache2/sites-available/000-default.conf'] as $cand) {
            [$rc, $out] = $this->sslRun($base, 'cat ' . $cand . ' 2>/dev/null', 30);
            $content = implode("\n", $out);
            if ($rc === 0 && trim($content) !== '') { $p80Path = $cand; $p80Content = $content; break; }
        }
        if ($p80Path !== null) {
            [$newP80, $rewriteMsg] = $this->sslInjectHttpsRewrite($p80Content, $domain);
            if ($newP80 !== null) {
                $p80BackupPath = $p80Path . '.bak-' . $stamp;
                $this->sslRun($base, 'cp -a ' . $p80Path . ' ' . $p80BackupPath . ' && echo BACKUP_OK', 30);
                $writeLog = '';
                if ($this->sslUploadFile($base, $p80Path, $newP80, 0644, $writeLog)) {
                    $wrote = true;
                    $notes[] = $rewriteMsg;
                } else {
                    $notes[] = 'Could not update the port-80 redirect: ' . $writeLog;
                }
            } else {
                $notes[] = $rewriteMsg;
            }
        }

        if ($wrote) {
            [$rc, $out] = $this->sslRun($base, 'apache2ctl configtest 2>&1 || apachectl configtest 2>&1', 60);
            if (strpos(implode("\n", $out), 'Syntax OK') === false) {
                $steps[6]['status'] = 'error';
                $steps[6]['output'] = "Apache configuration test failed after normalization:\n" . trim(implode("\n", $out));
                if ($leBackupPath !== null) {
                    $this->sslRun($base, 'cp -a ' . $leBackupPath . ' ' . $sslConfPath . ' && echo RESTORED', 30);
                    $steps[6]['output'] .= "\nRestored {$sslConfPath} from backup.";
                } elseif ($leCreated) {
                    $this->sslRun($base, 'rm -f ' . $sslConfPath, 30);
                    $steps[6]['output'] .= "\nRemoved the created file {$sslConfPath} again.";
                }
                if ($p80BackupPath !== null) {
                    $this->sslRun($base, 'cp -a ' . $p80BackupPath . ' ' . $p80Path . ' && echo RESTORED', 30);
                    $steps[6]['output'] .= "\nRestored {$p80Path} from backup.";
                }
                return response()->json(['success' => false, 'message' => 'The Apache configuration test failed after normalization — changes were rolled back.', 'steps' => $steps]);
            }
            [$rc, $out] = $this->sslRun($base,
                'systemctl reload apache2 2>/dev/null || systemctl reload httpd 2>/dev/null || service apache2 reload 2>/dev/null || service httpd reload 2>/dev/null || apache2ctl -k graceful 2>/dev/null; '
                . 'systemctl is-active apache2 2>/dev/null || systemctl is-active httpd 2>/dev/null || echo APACHE_STATUS_UNKNOWN',
                60);
            $notes[] = 'Apache reloaded';
        }

        // refresh the verification AFTER normalization
        [$rc, $out] = $this->sslRun($base, $verifyCmd, 60);
        $verifyOut = trim(implode("\n", $out));
        $steps[6]['output'] = (!empty($notes) ? implode("\n", $notes) . "\n" : '') . ($verifyOut !== '' ? $verifyOut : 'no output');

        if (strpos($verifyOut, 'VERIFY_VHOST_MISSING') !== false) {
            // certbot issued the certificate but did not deploy the vhost
            // (typical when the previous SSL vhost was removed) — append the
            // standard block ourselves now.
            $steps[6]['status'] = 'ok';
            $steps[6]['output'] = 'Certificate issued, but certbot did not deploy the SSL VirtualHost — deploying our standard block';

            [$rc, $out] = $this->sslRun($base, '[ -f /etc/letsencrypt/options-ssl-apache.conf ] && echo OPTS_EXISTS || echo OPTS_MISSING', 20);
            $includeSslOptions = strpos(implode("\n", $out), 'OPTS_EXISTS') !== false;

            $sslEnabledPath = '/etc/apache2/sites-enabled/000-default-le-ssl.conf';
            $sslAvailablePath = '/etc/apache2/sites-available/000-default-le-ssl.conf';
            $sslConfPath = null;
            $sslConfContent = null;
            foreach ([$sslEnabledPath, $sslAvailablePath] as $cand) {
                [$rc, $out] = $this->sslRun($base, 'cat ' . $cand . ' 2>/dev/null', 30);
                $content = implode("\n", $out);
                if ($rc === 0 && trim($content) !== '') { $sslConfPath = $cand; $sslConfContent = $content; break; }
            }

            // DocumentRoot: the port-80 vhost of this domain (created/verified in step 3)
            $docrootDeploy = $docroot;
            foreach (['/etc/apache2/sites-enabled/000-default.conf', '/etc/apache2/sites-available/000-default.conf'] as $cand) {
                [$rc, $out] = $this->sslRun($base, 'cat ' . $cand . ' 2>/dev/null', 30);
                $content = implode("\n", $out);
                if ($rc === 0 && trim($content) !== '') {
                    foreach ($this->parseVirtualHosts($content) as $v) {
                        if (in_array($domain, $v['domains'], true) && !empty($v['document_root'])) {
                            $docrootDeploy = $v['document_root'];
                        }
                    }
                    break;
                }
            }

            // Our standard Let's Encrypt block — exactly these two certificate
            // lines plus the standard Include, nothing else:
            //   SSLCertificateFile /etc/letsencrypt/live/<domain>/fullchain.pem
            //   SSLCertificateKeyFile /etc/letsencrypt/live/<domain>/privkey.pem
            $block = $this->sslBuildSslVhostBlock(
                $domain,
                $docrootDeploy,
                '/etc/letsencrypt/live/' . $domain . '/fullchain.pem',
                '/etc/letsencrypt/live/' . $domain . '/privkey.pem',
                null,
                $includeSslOptions
            );

            $createdConf = false;
            $backupPath = null;
            if ($sslConfPath !== null) {
                $backupPath = $sslConfPath . '.bak-' . date('YmdHis');
                $this->sslRun($base, 'cp -a ' . $sslConfPath . ' ' . $backupPath . ' && echo BACKUP_OK', 30);
                $newSslContent = rtrim($sslConfContent) . "\n\n" . $block . "\n";
            } else {
                $sslConfPath = $sslEnabledPath;
                $newSslContent = $block . "\n";
                $createdConf = true;
            }
            $deployIdx = count($steps) - 1;

            $writeLog = '';
            if (!$this->sslUploadFile($base, $sslConfPath, $newSslContent, 0644, $writeLog)) {
                $steps[$deployIdx]['status'] = 'error';
                $steps[$deployIdx]['output'] = "Failed to write {$sslConfPath}\n" . $writeLog;
                return response()->json(['success' => false, 'message' => "Failed to deploy the standard SSL VirtualHost to {$sslConfPath}.", 'steps' => $steps]);
            }
            if ($sslConfPath === $sslAvailablePath) {
                $this->sslRun($base, '[ -e ' . $sslEnabledPath . ' ] || ln -sf ' . $sslAvailablePath . ' ' . $sslEnabledPath . '; echo LINK_OK', 30);
            }

            // syntax test — roll back when it fails
            [$rc, $out] = $this->sslRun($base, 'apache2ctl configtest 2>&1 || apachectl configtest 2>&1', 60);
            if (strpos(implode("\n", $out), 'Syntax OK') === false) {
                $steps[$deployIdx]['status'] = 'error';
                $steps[$deployIdx]['output'] = "Apache configuration test failed after deploying:\n" . trim(implode("\n", $out));
                if ($createdConf) {
                    $this->sslRun($base, 'rm -f ' . $sslConfPath, 30);
                    $steps[$deployIdx]['output'] .= "\nRemoved the created file {$sslConfPath} again.";
                } elseif ($backupPath !== null) {
                    $this->sslRun($base, 'cp -a ' . $backupPath . ' ' . $sslConfPath . ' && echo RESTORED', 30);
                    $steps[$deployIdx]['output'] .= "\nRestored {$sslConfPath} from backup.";
                }
                return response()->json(['success' => false, 'message' => 'The Apache configuration test failed after deploying the SSL VirtualHost — changes were rolled back.', 'steps' => $steps]);
            }

            // reload Apache
            [$rc, $out] = $this->sslRun($base,
                'systemctl reload apache2 2>/dev/null || systemctl reload httpd 2>/dev/null || service apache2 reload 2>/dev/null || service httpd reload 2>/dev/null || apache2ctl -k graceful 2>/dev/null; '
                . 'systemctl is-active apache2 2>/dev/null || systemctl is-active httpd 2>/dev/null || echo APACHE_STATUS_UNKNOWN',
                60);
            $reloadOut = trim(implode("\n", $out));

            // final check: the standard lines must now be present
            [$rc, $out] = $this->sslRun($base,
                'if grep -ils "' . $stdCertLine . '" /etc/apache2/sites-enabled/* 2>/dev/null | grep -q .; then echo VERIFY_STDCERT_OK; else echo VERIFY_STDCERT_MISSING; fi; '
                . 'if grep -ils "' . $stdKeyLine . '" /etc/apache2/sites-enabled/* 2>/dev/null | grep -q .; then echo VERIFY_STDKEY_OK; else echo VERIFY_STDKEY_MISSING; fi; '
                . 'echo VERIFY_DONE',
                30);
            $finalOut = implode("\n", $out);
            if (strpos($finalOut, 'VERIFY_STDCERT_MISSING') !== false || strpos($finalOut, 'VERIFY_STDKEY_MISSING') !== false) {
                $steps[$deployIdx]['status'] = 'error';
                $steps[$deployIdx]['output'] = "Deployed to {$sslConfPath} but the standard certificate lines are still not present in the enabled config.\n" . $reloadOut;
                return response()->json(['success' => false, 'message' => "The SSL VirtualHost was deployed to {$sslConfPath} but the standard certificate paths are still missing. Check the server setup.", 'steps' => $steps]);
            }

            $steps[$deployIdx]['status'] = 'ok';
            $steps[$deployIdx]['output'] = "Deployed the standard SSL VirtualHost to {$sslConfPath} (" . ($createdConf ? 'file created' : 'appended, backup kept') . ")\n"
                . '  ' . $stdCertLine . "\n"
                . '  ' . $stdKeyLine . "\n"
                . $reloadOut;
        } else {
            $steps[6]['status'] = 'ok';
        }

        return response()->json([
            'success' => true,
            'message' => "Let's Encrypt SSL installed successfully for {$domain} on {$p['hostname']} — standard paths verified ({$stdCertLine}).",
            'steps' => $steps,
            'domain' => $domain,
        ]);
    }

    /**
     * Install a PAID SSL certificate on the remote server.
     *
     * Flow (each step is reported back to the UI):
     *  1. Verify the certificate / private-key pair LOCALLY — nothing is
     *     uploaded and nothing changes when they do not match.
     *  2. Read the Apache configs. When SSL is already installed for the
     *     domain the new files get a version suffix (…1, …2, …) and a new
     *     vhost block is appended. The chosen directory becomes the
     *     DocumentRoot / <Directory> of the new block.
     *  3. Upload public cert / private key / chain to /etc/pki/tls/ with
     *     domain-based file names (private key chmod 600).
     *  4. APPEND a new <IfModule mod_ssl.c><VirtualHost *:443> block to the
     *     -le-ssl.conf (created when missing) — existing domains are never
     *     modified or removed.
     *  5. Insert the port-80 → https rewrite rules for this domain.
     *  6. apache2ctl configtest — every change is rolled back when it fails.
     *  7. Reload Apache.
     */
    public function installPaidSsl(Request $request): JsonResponse
    {
        @set_time_limit(0);

        $request->validate([
            'host' => 'required|string',
            'hostname' => 'required|string',
            'username' => 'required|string',
            'identity_file' => 'required|string',
            'port' => 'nullable|integer',
            'domain' => 'required|string',
        ]);

        $p = $this->exploreRequestParams($request);
        $err = $this->exploreCheckParams($p);
        if ($err) return $err;

        $domain = $this->sslNormalizeDomain((string) $request->input('domain'));
        if ($domain === null) {
            return response()->json([
                'success' => false,
                'message' => 'Please enter a valid domain name (e.g. example.com).',
            ]);
        }

        $docroot = $this->sslNormalizeDocroot((string) $request->input('docroot', ''));
        if ($docroot === null) {
            return response()->json([
                'success' => false,
                'message' => 'Please enter a valid absolute directory path (e.g. /var/www/your-project/public).',
            ]);
        }

        $cert = $this->sslFieldContent($request, 'cert');
        $key = $this->sslFieldContent($request, 'key');
        $chain = $this->sslFieldContent($request, 'chain'); // optional

        if ($cert === null || $key === null) {
            return response()->json([
                'success' => false,
                'message' => 'Public certificate and private key are required (upload a file or paste the text).',
            ]);
        }
        if (strpos($cert, 'BEGIN CERTIFICATE') === false) {
            return response()->json([
                'success' => false,
                'message' => 'The public certificate does not look like a PEM certificate (missing "-----BEGIN CERTIFICATE-----").',
            ]);
        }
        if (strpos($key, 'PRIVATE KEY') === false) {
            return response()->json([
                'success' => false,
                'message' => 'The private key does not look like a PEM private key (missing "-----...PRIVATE KEY-----").',
            ]);
        }

        $steps = [];
        $base = $this->buildExploreSshBase($p['username'], $p['hostname'], $p['port'], $p['identity_file']);
        $slug = $this->sslSlug($domain);
        $backupSuffix = date('YmdHis');
        $backups = [];      // original path => backup path (files that get modified)
        $createdFiles = []; // files created by this run (removed again on rollback)

        // ---- Step 1: key-pair verification (BEFORE anything is uploaded)
        $steps[] = ['name' => 'Verifying certificate / private-key pair', 'status' => 'running', 'output' => ''];
        if (!$this->sslCertKeyMatch($cert, $key)) {
            $steps[0]['status'] = 'error';
            $steps[0]['output'] = 'The public certificate and the private key DO NOT match.';
            return response()->json([
                'success' => false,
                'message' => 'Certificate and private key do not match — nothing was uploaded and nothing was changed on the server.',
                'steps' => $steps,
            ]);
        }
        $steps[0]['status'] = 'ok';
        $steps[0]['output'] = 'Key pair matches — safe to upload';

        // ---- Step 2: read the Apache configuration + duplicate checks
        $steps[] = ['name' => 'Reading the Apache configuration', 'status' => 'running', 'output' => ''];

        $sslEnabledPath = '/etc/apache2/sites-enabled/000-default-le-ssl.conf';
        $sslAvailablePath = '/etc/apache2/sites-available/000-default-le-ssl.conf';
        $sslConfPath = null;
        $sslConfContent = null;
        foreach ([$sslEnabledPath, $sslAvailablePath] as $cand) {
            [$rc, $out] = $this->sslRun($base, 'cat ' . $cand . ' 2>/dev/null', 30);
            $content = implode("\n", $out);
            if ($rc === 0 && trim($content) !== '') {
                $sslConfPath = $cand;
                $sslConfContent = $content;
                break;
            }
        }

        $port80Path = null;
        $port80Content = '';
        foreach (['/etc/apache2/sites-enabled/000-default.conf', '/etc/apache2/sites-available/000-default.conf'] as $cand) {
            [$rc, $out] = $this->sslRun($base, 'cat ' . $cand . ' 2>/dev/null', 30);
            $content = implode("\n", $out);
            if ($rc === 0 && trim($content) !== '') {
                $port80Path = $cand;
                $port80Content = $content;
                break;
            }
        }

        // Is SSL already installed for this domain anywhere in sites-enabled?
        // (glob on purpose — entries are symlinks and `grep -r` skips them)
        [$rc, $out] = $this->sslRun($base,
            "grep -ils 'SSLCertificateFile' /etc/apache2/sites-enabled/* 2>/dev/null | xargs -r grep -ils 'servername " . $domain . "' 2>/dev/null",
            30);
        $dupFiles = array_values(array_filter(array_map('trim', $out)));
        $sslAlreadyInstalled = !empty($dupFiles);

        // File naming version: base names on the first install, then
        // '<slug>1...', '<slug>2...' etc. — existing files are never overwritten
        $certExt = 'crt';
        if ($request->hasFile('cert_file')) {
            $ext = strtolower($request->file('cert_file')->getClientOriginalExtension());
            if (in_array($ext, ['csr', 'crt', 'pem', 'cer'], true)) { $certExt = $ext; }
        }
        $chainBase = 'chain';
        $chainExt = 'crt';
        if ($chain !== null && $request->hasFile('chain_file')) {
            $chainBase = $this->sslChainBase($request->file('chain_file')->getClientOriginalName());
            $ext = strtolower($request->file('chain_file')->getClientOriginalExtension());
            if (in_array($ext, ['crt', 'pem', 'cer'], true)) { $chainExt = $ext; }
        }

        $minN = $sslAlreadyInstalled ? 1 : 0;
        [$rc, $out] = $this->sslRun($base,
            'N=' . $minN . '; while [ $N -le 500 ]; do S=""; [ $N -gt 0 ] && S=$N; '
            . 'if [ ! -e "/etc/pki/tls/' . $slug . '${S}.' . $certExt . '" ] '
            . '&& [ ! -e "/etc/pki/tls/' . $slug . '_private_key${S}.txt" ] '
            . '&& [ ! -e "/etc/pki/tls/' . $slug . '_' . $chainBase . '${S}.' . $chainExt . '" ]; then echo FREE_N=$N; break; fi; '
            . 'N=$((N+1)); done; echo COUNTER_DONE',
            60);
        $freeN = null;
        foreach ($out as $line) {
            if (strpos($line, 'FREE_N=') === 0) { $freeN = (int) substr($line, 7); break; }
        }
        if ($freeN === null) {
            $steps[1]['status'] = 'error';
            $steps[1]['output'] = "Could not determine a free file version in /etc/pki/tls for {$domain}.";
            return response()->json([
                'success' => false,
                'message' => 'Could not determine a free certificate file version on the server. Nothing was changed.',
                'steps' => $steps,
            ]);
        }
        $suffix = $freeN === 0 ? '' : (string) $freeN;

        $steps[1]['status'] = 'ok';
        $steps[1]['output'] = ($sslConfPath ?? ($sslEnabledPath . ' (will be created)')) . "\n"
            . 'DocumentRoot: ' . $docroot . "\n"
            . ($sslAlreadyInstalled
                ? "SSL is already installed for {$domain} — new files get version suffix '{$suffix}' and a new VirtualHost block is appended\nExisting: " . implode(', ', $dupFiles)
                : 'No existing SSL for this domain — base file names will be used');

        // ---- Step 3: upload the certificate files to /etc/pki/tls
        $steps[] = ['name' => 'Uploading the certificate files to /etc/pki/tls', 'status' => 'running', 'output' => ''];
        [$rc, $out] = $this->sslRun($base, 'mkdir -p /etc/pki/tls && echo PKI_DIR_OK', 30);
        if (strpos(implode("\n", $out), 'PKI_DIR_OK') === false) {
            $steps[2]['status'] = 'error';
            $steps[2]['output'] = 'Could not create /etc/pki/tls on the server.';
            return response()->json([
                'success' => false,
                'message' => 'Could not create /etc/pki/tls on the server (permission denied?). Nothing was changed.',
                'steps' => $steps,
            ]);
        }

        $certPath = '/etc/pki/tls/' . $slug . $suffix . '.' . $certExt;
        $keyPath = '/etc/pki/tls/' . $slug . '_private_key' . $suffix . '.txt';
        $chainPath = '/etc/pki/tls/' . $slug . '_' . $chainBase . $suffix . '.' . $chainExt;

        $uploadLog = '';
        if (!$this->sslUploadFile($base, $certPath, $cert, 0644, $uploadLog)) {
            $steps[2]['status'] = 'error';
            $steps[2]['output'] = "Failed to upload {$certPath}\n" . $uploadLog;
            return response()->json(['success' => false, 'message' => "Failed to upload the public certificate to {$certPath}. Nothing else was changed.", 'steps' => $steps]);
        }
        if (!$this->sslUploadFile($base, $keyPath, $key, 0600, $uploadLog)) {
            $this->sslRun($base, 'rm -f ' . $certPath, 20);
            $steps[2]['status'] = 'error';
            $steps[2]['output'] = "Failed to upload {$keyPath}\n" . $uploadLog;
            return response()->json(['success' => false, 'message' => "Failed to upload the private key to {$keyPath}. The certificate file was removed again.", 'steps' => $steps]);
        }
        $chainNote = 'Chain not provided — SSLCertificateChainFile omitted';
        if ($chain !== null) {
            if (!$this->sslUploadFile($base, $chainPath, $chain, 0644, $uploadLog)) {
                $this->sslRun($base, 'rm -f ' . $certPath . ' ' . $keyPath, 20);
                $steps[2]['status'] = 'error';
                $steps[2]['output'] = "Failed to upload {$chainPath}\n" . $uploadLog;
                return response()->json(['success' => false, 'message' => "Failed to upload the chain to {$chainPath}. The uploaded files were removed again.", 'steps' => $steps]);
            }
            $chainNote = $chainPath;
        }
        $createdFiles[] = $certPath;
        $createdFiles[] = $keyPath;
        if ($chain !== null) { $createdFiles[] = $chainPath; }
        $steps[2]['status'] = 'ok';
        $steps[2]['output'] = $certPath . "\n" . $keyPath . "\n" . $chainNote;

        // ---- Step 4: APPEND the SSL VirtualHost (existing content untouched)
        $steps[] = ['name' => 'Writing the SSL VirtualHost', 'status' => 'running', 'output' => ''];

        // make sure mod_ssl + mod_rewrite are enabled (Debian) — harmless when already active
        [$rc, $out] = $this->sslRun($base,
            'if apache2ctl -M 2>/dev/null | grep -q ssl_module; then echo MOD_SSL_OK; else a2enmod ssl >/dev/null 2>&1 && echo MOD_SSL_ENABLED || echo MOD_SSL_SKIPPED; fi; '
            . 'if apache2ctl -M 2>/dev/null | grep -q rewrite_module; then echo MOD_REWRITE_OK; else a2enmod rewrite >/dev/null 2>&1 && echo MOD_REWRITE_ENABLED || echo MOD_REWRITE_SKIPPED; fi',
            60);
        $modSslOut = trim(implode("\n", $out));

        [$rc, $out] = $this->sslRun($base, '[ -f /etc/letsencrypt/options-ssl-apache.conf ] && echo OPTS_EXISTS || echo OPTS_MISSING', 20);
        $includeSslOptions = strpos(implode("\n", $out), 'OPTS_EXISTS') !== false;

        $block = $this->sslBuildSslVhostBlock($domain, $docroot, $certPath, $keyPath, $chain !== null ? $chainPath : null, $includeSslOptions);

        if ($sslConfPath !== null) {
            $this->sslRun($base, 'cp -a ' . $sslConfPath . ' ' . $sslConfPath . '.bak-' . $backupSuffix . ' && echo BACKUP_OK', 30);
            $backups[$sslConfPath] = $sslConfPath . '.bak-' . $backupSuffix;
            $newSslContent = rtrim($sslConfContent) . "\n\n" . $block . "\n";
            $sslWriteNote = ' (appended — existing content untouched)';
        } else {
            $sslConfPath = $sslEnabledPath;
            $newSslContent = $block . "\n";
            $createdFiles[] = $sslConfPath;
            $sslWriteNote = ' (created)';
        }

        $writeLog = '';
        if (!$this->sslUploadFile($base, $sslConfPath, $newSslContent, 0644, $writeLog)) {
            $steps[3]['status'] = 'error';
            $steps[3]['output'] = "Failed to write {$sslConfPath}\n" . $writeLog;
            $this->sslRollback($base, $backups, $createdFiles, $rollbackLog);
            $steps[] = ['name' => 'Rollback', 'status' => 'error', 'output' => $rollbackLog];
            return response()->json(['success' => false, 'message' => "Failed to write {$sslConfPath}. All changes were rolled back.", 'steps' => $steps]);
        }
        // When writing to sites-available, make sure the site is enabled
        if ($sslConfPath === $sslAvailablePath) {
            $this->sslRun($base, '[ -e ' . $sslEnabledPath . ' ] || ln -sf ' . $sslAvailablePath . ' ' . $sslEnabledPath . '; echo LINK_OK', 30);
        }
        $steps[3]['status'] = 'ok';
        $steps[3]['output'] = $modSslOut . "\n" . $sslConfPath . $sslWriteNote;
        if ($sslAlreadyInstalled) {
            $steps[3]['output'] .= "\nNote: a previous SSL VirtualHost for {$domain} already exists — the new block was APPENDED. Apache serves the FIRST matching vhost, so comment out / remove the old block to activate the new certificate.";
        }

        // ---- Step 5: port-80 → https redirect (insert-only, existing untouched)
        $steps[] = ['name' => 'Configuring the port-80 HTTPS redirect', 'status' => 'running', 'output' => ''];
        [$newPort80Content, $rewriteMsg] = $this->sslInjectHttpsRewrite($port80Content, $domain);
        if ($newPort80Content !== null) {
            $this->sslRun($base, 'cp -a ' . $port80Path . ' ' . $port80Path . '.bak-' . $backupSuffix . ' && echo BACKUP_OK', 30);
            $backups[$port80Path] = $port80Path . '.bak-' . $backupSuffix;
            $writeLog = '';
            if ($this->sslUploadFile($base, $port80Path, $newPort80Content, 0644, $writeLog)) {
                $steps[4]['status'] = 'ok';
                $steps[4]['output'] = $rewriteMsg;
            } else {
                $steps[4]['status'] = 'error';
                $steps[4]['output'] = "Failed to update {$port80Path}\n" . $writeLog;
                $this->sslRollback($base, $backups, $createdFiles, $rollbackLog);
                $steps[] = ['name' => 'Rollback', 'status' => 'error', 'output' => $rollbackLog];
                return response()->json(['success' => false, 'message' => "Failed to update {$port80Path}. All changes were rolled back.", 'steps' => $steps]);
            }
        } else {
            $steps[4]['status'] = 'ok';
            $steps[4]['output'] = $rewriteMsg;
        }

        // ---- Step 6: syntax test — roll everything back when it fails
        $steps[] = ['name' => 'Testing the Apache configuration', 'status' => 'running', 'output' => ''];
        [$rc, $out] = $this->sslRun($base, 'apache2ctl configtest 2>&1 || apachectl configtest 2>&1', 60);
        $testOut = trim(implode("\n", $out));
        $steps[5]['output'] = $testOut !== '' ? $testOut : 'no output';
        if (strpos($testOut, 'Syntax OK') === false) {
            $steps[5]['status'] = 'error';
            $this->sslRollback($base, $backups, $createdFiles, $rollbackLog);
            $steps[] = ['name' => 'Rollback (config test failed)', 'status' => 'error', 'output' => $rollbackLog];
            return response()->json([
                'success' => false,
                'message' => 'The Apache configuration test failed — every change was rolled back automatically. Review the errors above.',
                'steps' => $steps,
            ]);
        }
        $steps[5]['status'] = 'ok';

        // ---- Step 7: reload Apache
        $steps[] = ['name' => 'Reloading Apache', 'status' => 'running', 'output' => ''];
        [$rc, $out] = $this->sslRun($base,
            'systemctl reload apache2 2>/dev/null || systemctl reload httpd 2>/dev/null || service apache2 reload 2>/dev/null || service httpd reload 2>/dev/null || apache2ctl -k graceful 2>/dev/null; '
            . 'systemctl is-active apache2 2>/dev/null || systemctl is-active httpd 2>/dev/null || echo APACHE_STATUS_UNKNOWN',
            60);
        $reloadOut = trim(implode("\n", $out));
        $steps[6]['output'] = $reloadOut !== '' ? $reloadOut : 'reload command issued';
        if (stripos($reloadOut, 'failed') !== false) {
            $steps[6]['status'] = 'error';
            return response()->json(['success' => false, 'message' => 'The Apache reload reported a failure — check the server.', 'steps' => $steps]);
        }
        $steps[6]['status'] = 'ok';

        return response()->json([
            'success' => true,
            'message' => "Paid SSL installed successfully for {$domain} on {$p['hostname']}.",
            'steps' => $steps,
            'domain' => $domain,
            'files' => [
                'certificate' => $certPath,
                'key' => $keyPath,
                'chain' => $chain !== null ? $chainPath : null,
            ],
            'ssl_vhost' => $sslConfPath,
        ]);
    }

    /* ------------------------- SSL helpers ------------------------- */

    /**
     * Run a remote command as root (when non-interactive sudo is available)
     * through the explorer SSH base. Returns [exitCode, outputLines] with
     * stderr included and the SSH "Warning: ..." noise lines filtered out.
     */
    private function sslRun(string $base, string $remoteCmd, int $timeoutSec = 60): array
    {
        $out = [];
        exec('timeout ' . (int) $timeoutSec . ' ' . $base . ' ' . escapeshellarg($this->exploreAsRoot($remoteCmd)) . ' 2>&1', $out, $rc);
        $out = array_values(array_filter($out, function ($l) {
            return !preg_match('/^Warning: /', $l);
        }));
        return [$rc, $out];
    }

    /**
     * Normalize a pasted domain: strip scheme / path / port, lowercase.
     * Returns null when the result is not a plain hostname.
     */
    private function sslNormalizeDomain(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') return null;

        if (preg_match('#^[a-z][a-z0-9+.\-]*://#i', $raw)) {
            // With a scheme, parse_url extracts the host reliably
            $host = parse_url($raw, PHP_URL_HOST);
            $raw = is_string($host) && $host !== '' ? $host : $raw;
        } else {
            // No scheme: cut anything after the first '/', then try to parse
            $slash = strpos($raw, '/');
            if ($slash !== false) { $raw = substr($raw, 0, $slash); }
            $host = parse_url($raw, PHP_URL_HOST);
            if (is_string($host) && $host !== '') { $raw = $host; }
        }

        $raw = strtolower(rtrim($raw, '.'));
        if (strpos($raw, ':') !== false) { $raw = explode(':', $raw)[0]; }
        if (preg_match('/^([a-z0-9]([a-z0-9\-]*[a-z0-9])?)(\.[a-z0-9]([a-z0-9\-]*[a-z0-9])?)+$/', $raw)) {
            return $raw;
        }
        return null;
    }

    /** First valid IP token found in free text (null when none). */
    private function sslFirstIp(string $text): ?string
    {
        foreach (preg_split('/[\s,;]+/', trim($text)) as $token) {
            $token = trim($token);
            if ($token !== '' && filter_var($token, FILTER_VALIDATE_IP) !== false) {
                return $token;
            }
        }
        return null;
    }

    /** Domain → filesystem slug: uatpayout.wegofin.com → uatpayout_wegofin_com */
    private function sslSlug(string $domain): string
    {
        return (string) preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($domain)));
    }

    /**
     * Certificate / key content from either the uploaded file or the pasted text.
     */
    private function sslFieldContent(Request $request, string $field): ?string
    {
        if ($request->hasFile($field . '_file')) {
            $file = $request->file($field . '_file');
            if (!$file->isValid()) { return null; }
            $content = @file_get_contents($file->getRealPath());
            return ($content !== false && trim($content) !== '') ? $content : null;
        }
        $text = trim((string) $request->input($field . '_text', ''));
        return $text !== '' ? $text : null;
    }

    /**
     * Verify a PEM certificate and a PEM private key belong together by
     * comparing the derived public keys. Falls back to the local openssl
     * binary (modulus comparison) when the PHP OpenSSL extension cannot
     * parse one of the inputs. Runs entirely locally — the server is not
     * touched until this passes.
     */
    private function sslCertKeyMatch(string $certPem, string $keyPem): bool
    {
        $certPub = @openssl_pkey_get_public($certPem);
        $keyPub = @openssl_pkey_get_public($keyPem);
        if ($certPub !== false && $keyPub !== false) {
            $a = openssl_pkey_get_details($certPub);
            $b = openssl_pkey_get_details($keyPub);
            if (is_array($a) && is_array($b) && isset($a['key'], $b['key'])) {
                return hash_equals($a['key'], $b['key']);
            }
        }

        // Fallback: local openssl binary (modulus comparison)
        $certTmp = tempnam(sys_get_temp_dir(), 'sslcrt_');
        $keyTmp = tempnam(sys_get_temp_dir(), 'sslkey_');
        if ($certTmp === false || $keyTmp === false) { return false; }
        file_put_contents($certTmp, $certPem);
        file_put_contents($keyTmp, $keyPem);
        $certMod = @shell_exec('openssl x509 -noout -modulus -in ' . escapeshellarg($certTmp) . ' 2>/dev/null');
        $keyMod = @shell_exec('openssl rsa -noout -modulus -in ' . escapeshellarg($keyTmp) . ' 2>/dev/null');
        @unlink($certTmp);
        @unlink($keyTmp);
        if (is_string($certMod) && is_string($keyMod)) {
            $certMod = strtoupper(trim(preg_replace('/^Modulus=/im', '', trim($certMod))));
            $keyMod = strtoupper(trim(preg_replace('/^Modulus=/im', '', trim($keyMod))));
            if ($certMod !== '' && $keyMod !== '') {
                return hash_equals($certMod, $keyMod);
            }
        }
        return false;
    }

    /**
     * Upload file content to an absolute path on the remote server as root
     * (content piped over SSH stdin — same mechanism as the file explorer).
     * $log receives the raw output for diagnostics.
     */
    private function sslUploadFile(string $base, string $targetPath, string $content, int $chmod, ?string &$log = null): bool
    {
        $tmp = tempnam(sys_get_temp_dir(), 'sslup_');
        if ($tmp === false || file_put_contents($tmp, $content) === false) {
            $log = 'Could not stage the file locally.';
            return false;
        }
        $cmd = 'cat > ' . $this->remoteQuote($targetPath) . ' 2>/dev/null'
            . ' && chmod ' . decoct($chmod) . ' ' . $this->remoteQuote($targetPath) . ' 2>/dev/null'
            . ' && echo UPLOAD_OK';
        $out = [];
        exec('timeout 120 ' . $base . ' ' . escapeshellarg($this->exploreAsRoot($cmd)) . ' < ' . escapeshellarg($tmp) . ' 2>&1', $out, $rc);
        @unlink($tmp);
        $log = trim(implode("\n", $out));
        return $rc === 0 && strpos($log, 'UPLOAD_OK') !== false;
    }

    /**
     * Build the <VirtualHost *:443> block in OUR standard format. It is
     * APPENDED to the conf file, so existing domains are never modified or
     * removed. Standard paths:
     *   Let's Encrypt: /etc/letsencrypt/live/<domain>/fullchain.pem + privkey.pem
     *   Paid SSL:      /etc/pki/tls/<files>
     * $chainPath (paid only) adds SSLCertificateChainFile; $includeSslOptions
     * adds the standard Include /etc/letsencrypt/options-ssl-apache.conf.
     */
    private function sslBuildSslVhostBlock(string $domain, string $docRoot, string $certPath, string $keyPath, ?string $chainPath, bool $includeSslOptions): string
    {
        $tpl = <<<'TXT'
<IfModule mod_ssl.c>
<VirtualHost *:443>
        ServerName {DOMAIN}
        ServerAlias www.{DOMAIN}
        ServerAdmin webmaster@localhost
        DocumentRoot {DOCROOT}

        <Directory {DOCROOT}/>
                Options Indexes FollowSymLinks MultiViews
                AllowOverride All
                Require all granted
        </Directory>

        ErrorLog ${APACHE_LOG_DIR}/error.log
        CustomLog ${APACHE_LOG_DIR}/access.log combined

{INCLUDE_OPTIONS}

{SSL_LINES}
</VirtualHost>
</IfModule>
TXT;

        $sslLines = '        SSLCertificateFile ' . $certPath . "\n"
            . '        SSLCertificateKeyFile ' . $keyPath;
        if ($chainPath !== null) {
            $sslLines .= "\n" . '        SSLCertificateChainFile ' . $chainPath;
        }

        return strtr($tpl, [
            '{DOMAIN}' => $domain,
            '{DOCROOT}' => $docRoot,
            '{INCLUDE_OPTIONS}' => $includeSslOptions ? '        Include /etc/letsencrypt/options-ssl-apache.conf' : '',
            '{SSL_LINES}' => $sslLines,
        ]);
    }

    /**
     * Insert the certbot-style HTTPS rewrite rules into the port-80
     * VirtualHost serving $domain. Existing lines are never modified — the
     * rules are only INSERTED before that vhost's closing </VirtualHost>.
     *
     * @return array{0:?string,1:string} [updated content (null = nothing to change), message]
     */
    private function sslInjectHttpsRewrite(string $content, string $domain): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $content);
        $n = count($lines);
        $blocks = [];
        for ($i = 0; $i < $n; $i++) {
            if (preg_match('/<VirtualHost[^>]*>/i', $lines[$i])) {
                $start = $i;
                for ($j = $i + 1; $j < $n; $j++) {
                    if (preg_match('/<\/VirtualHost\s*>/i', $lines[$j])) {
                        $blocks[] = [$start, $j];
                        $i = $j;
                        break;
                    }
                }
            }
        }

        foreach ($blocks as [$s, $e]) {
            $blockText = implode("\n", array_slice($lines, $s, $e - $s + 1));
            if (preg_match('/^\s*ServerName\s+' . preg_quote($domain, '/') . '\s*$/im', $blockText)) {
                if (stripos($blockText, 'RewriteRule') !== false) {
                    return [null, 'HTTPS redirect already present in the port-80 VirtualHost — left untouched'];
                }
                $insert = [
                    'RewriteEngine on',
                    'RewriteCond %{SERVER_NAME} =' . $domain . ' [OR]',
                    'RewriteCond %{SERVER_NAME} =www.' . $domain,
                    'RewriteRule ^ https://%{SERVER_NAME}%{REQUEST_URI} [END,NE,R=permanent]',
                ];
                array_splice($lines, $e, 0, $insert);
                return [implode("\n", $lines), 'Added the HTTPS rewrite rules to the existing port-80 VirtualHost for ' . $domain];
            }
        }

        return [null, "No port-80 VirtualHost found for {$domain} — no redirect rule was added (the https site itself is fully configured)"];
    }

    /**
     * Clean the -le-ssl conf: certbot leaves commented-out redirect rules
     * ("disabled on your HTTPS site … redirection loops") and sometimes
     * misplaced *:80 copies of the vhost in it. This removes:
     *   - certbot's comment noise (the two explanatory lines + commented
     *     Rewrite* directives)
     *   - *:80 VirtualHost blocks that belong to $domain (they live in the
     *     port-80 config — a copy here is useless and confusing)
     *   - any other vhost of $domain that does NOT use the standard
     *     certificate path (broken certbot copies — replaced by the standard
     *     block by the caller)
     * EVERYTHING else (other domains!) is preserved untouched.
     *
     * @return array{0:string,1:array<string,bool>} [cleaned content, flags]
     */
    private function sslNormalizeLeSslConf(string $content, string $domain, string $stdCertLine): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $content);
        $n = count($lines);
        $out = [];
        $flags = ['noise_removed' => false, 'http_block_removed' => false, 'broken_block_removed' => false];

        $isNoise = function (string $t): bool {
            return (bool) (preg_match('/^#\s*Some rewrite rules in this file were disabled/i', $t)
                || preg_match('/^#\s*because they have the potential/i', $t)
                || preg_match('/^#\s*Rewrite(Engine|Cond|Rule)\b/i', $t));
        };

        for ($i = 0; $i < $n; $i++) {
            $trimmed = trim($lines[$i]);

            if ($isNoise($trimmed)) {
                $flags['noise_removed'] = true;
                continue;
            }

            if (preg_match('/<VirtualHost[^>]*>/i', $trimmed)) {
                $end = $i;
                for ($j = $i + 1; $j < $n; $j++) {
                    if (preg_match('/<\/VirtualHost\s*>/i', trim($lines[$j]))) { $end = $j; break; }
                }
                $blockLines = array_slice($lines, $i, $end - $i + 1);
                $blockText = implode("\n", $blockLines);

                $isOurDomain = (bool) preg_match('/^\s*Server(Name|Alias)\s+' . preg_quote($domain, '/') . '\s*$/im', $blockText);
                $isPort80 = (bool) preg_match('/<VirtualHost\s*\*:80\s*>/i', $blockText);

                if ($isOurDomain && $isPort80) {
                    $flags['http_block_removed'] = true;
                } elseif ($isOurDomain && strpos($blockText, $stdCertLine) === false) {
                    $flags['broken_block_removed'] = true;
                } else {
                    foreach ($blockLines as $bl) { $out[] = $bl; }
                }
                $i = $end;
                continue;
            }

            $out[] = $lines[$i];
        }

        // collapse consecutive blank lines
        $cleaned = [];
        $blankRun = 0;
        foreach ($out as $l) {
            if (trim($l) === '') {
                $blankRun++;
                if ($blankRun > 1) continue;
            } else {
                $blankRun = 0;
            }
            $cleaned[] = $l;
        }

        return [implode("\n", $cleaned), $flags];
    }

    /**
     * Normalize a DocumentRoot / <Directory> path: must be absolute, no
     * whitespace, no '..', trailing slash removed. Returns null when invalid.
     */
    private function sslNormalizeDocroot(string $raw): ?string
    {
        $raw = rtrim(trim($raw), '/');
        if ($raw === '' || $raw[0] !== '/' || strpos($raw, '..') !== false) { return null; }
        if (preg_match('#\s#', $raw)) { return null; }
        if (!preg_match('#^/[A-Za-z0-9._\-]+(?:/[A-Za-z0-9._\-]+)*$#', $raw)) { return null; }
        return $raw;
    }

    /**
     * Base name for the uploaded chain file: 'gd_bundle-g2-g1.crt' becomes
     * 'gd_bundle-g2-g1' (path + extension stripped, unsafe characters replaced).
     */
    private function sslChainBase(?string $originalName): string
    {
        $base = $originalName === null ? '' : (string) pathinfo(basename($originalName), PATHINFO_FILENAME);
        $base = preg_replace('/[^A-Za-z0-9._\-]+/', '_', $base);
        $base = trim((string) $base, '._-');
        return $base !== '' ? $base : 'chain';
    }

    /**
     * Build a port-80 VirtualHost block (certbot-style, with the https
     * rewrite rules) — used when a domain has no VirtualHost yet so certbot
     * can find it and the DocumentRoot / <Directory> point to the chosen
     * project directory.
     */
    private function sslBuildPort80Vhost(string $domain, string $docRoot): string
    {
        $tpl = <<<'TXT'
<VirtualHost *:80>
        ServerName {DOMAIN}
        ServerAlias www.{DOMAIN}
        ServerAdmin webmaster@localhost
        DocumentRoot {DOCROOT}

<Directory {DOCROOT}/>
        Options Indexes FollowSymLinks MultiViews
        AllowOverride All
        Require all granted
</Directory>

        ErrorLog ${APACHE_LOG_DIR}/error.log
        CustomLog ${APACHE_LOG_DIR}/access.log combined

RewriteEngine on
RewriteCond %{SERVER_NAME} ={DOMAIN} [OR]
RewriteCond %{SERVER_NAME} =www.{DOMAIN}
RewriteRule ^ https://%{SERVER_NAME}%{REQUEST_URI} [END,NE,R=permanent]
</VirtualHost>
TXT;

        return strtr($tpl, ['{DOMAIN}' => $domain, '{DOCROOT}' => $docRoot]);
    }

    /**
     * Undo everything: remove the files created by the run and restore the
     * backed-up originals of every modified config file.
     */
    private function sslRollback(string $base, array $backups, array $createdFiles, ?string &$log = null): void
    {
        $logs = [];
        foreach ($createdFiles as $f) {
            $this->sslRun($base, 'rm -f ' . $f . ' 2>/dev/null; echo REMOVED', 30);
            $logs[] = 'Removed ' . $f;
        }
        foreach ($backups as $original => $bakPath) {
            $this->sslRun($base, 'cp -a ' . $bakPath . ' ' . $original . ' && echo RESTORED', 30);
            $logs[] = 'Restored ' . $original;
        }
        $log = implode("\n", $logs);
    }
}