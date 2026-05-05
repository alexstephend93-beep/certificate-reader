<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class SshConfigParser
{
    protected $configPath;
    
    public function __construct()
    {
        // Get home directory properly - works on all systems
        $homeDir = $this->getHomeDirectory();
        $this->configPath = $homeDir . '/.ssh/config';
    }
    
    /**
     * Get home directory reliably across different environments
     */
    protected function getHomeDirectory()
    {
        // Try different methods to get home directory
        if (isset($_SERVER['HOME'])) {
            return $_SERVER['HOME'];
        }
        
        if (isset($_SERVER['USERPROFILE'])) {
            return $_SERVER['USERPROFILE'];
        }
        
        if (isset($_ENV['HOME'])) {
            return $_ENV['HOME'];
        }
        
        // For Laravel Artisan commands
        if (function_exists('posix_getuid')) {
            $userInfo = posix_getpwuid(posix_getuid());
            if (isset($userInfo['dir'])) {
                return $userInfo['dir'];
            }
        }
        
        // Fallback to current directory's parent (not ideal but works)
        return base_path();
    }
    
    /**
     * Parse SSH config file and return all hosts
     */
    public function parseConfig()
    {
        if (!File::exists($this->configPath)) {
            return [
                'success' => false,
                'message' => 'SSH config file not found at: ' . $this->configPath,
                'hosts' => []
            ];
        }
        
        $content = File::get($this->configPath);
        $lines = explode("\n", $content);
        
        $hosts = [];
        $currentHost = null;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Check for domain comments
            if ($currentHost && str_contains($line, '#Domain')) {
                $domain = trim(str_replace('#Domain', '', $line));
                if (!empty($domain)) {
                    $currentHost['domains'][] = $domain;
                }
                continue;
            }
            
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }
            
            if (preg_match('/^Host\s+(.+)$/i', $line, $matches)) {
                // Save previous host
                if ($currentHost) {
                    $hosts[] = $currentHost;
                }
                
                // Start new host
                $currentHost = [
                    'host' => trim($matches[1]),
                    'hostname' => null,
                    'user' => null,
                    'identity_file' => null,
                    'port' => 22,
                    'domains' => [],
                    'description' => '',
                    'raw_config' => ''
                ];
                continue;
            }
            
            if ($currentHost) {
                if (preg_match('/^HostName\s+(.+)$/i', $line, $matches)) {
                    $currentHost['hostname'] = trim($matches[1]);
                } elseif (preg_match('/^User\s+(.+)$/i', $line, $matches)) {
                    $currentHost['user'] = trim($matches[1]);
                } elseif (preg_match('/^IdentityFile\s+(.+)$/i', $line, $matches)) {
                    $currentHost['identity_file'] = trim($matches[1]);
                } elseif (preg_match('/^Port\s+(.+)$/i', $line, $matches)) {
                    $currentHost['port'] = (int)trim($matches[1]);
                }
            }
        }
        
        // Add last host
        if ($currentHost) {
            $hosts[] = $currentHost;
        }
        
        // Validate key files and add status
        foreach ($hosts as &$host) {
            if ($host['identity_file']) {
                $fullPath = $this->expandPath($host['identity_file']);
                $host['key_exists'] = File::exists($fullPath);
                $host['key_path'] = $fullPath;
                if ($host['key_exists']) {
                    $perms = fileperms($fullPath);
                    $host['key_permissions'] = substr(sprintf('%o', $perms), -4);
                    $host['key_valid'] = ($host['key_permissions'] === '0600' || $host['key_permissions'] === '0400');
                } else {
                    $host['key_valid'] = false;
                }
            } else {
                $host['key_exists'] = false;
                $host['key_valid'] = false;
            }
            
            // Generate SSH command
            $host['ssh_command'] = $this->generateSshCommand($host);
            
            // Generate VS Code command
            $host['vscode_command'] = $this->generateVSCodeCommand($host);
        }
        
        return [
            'success' => true,
            'message' => 'Config parsed successfully',
            'hosts' => $hosts,
            'config_path' => $this->configPath
        ];
    }
    
    /**
     * Generate SSH command for terminal
     */
    protected function generateSshCommand($host)
    {
        $cmd = "ssh";
        
        if ($host['port'] != 22) {
            $cmd .= " -p {$host['port']}";
        }
        
        if ($host['identity_file']) {
            $cmd .= " -i {$host['identity_file']}";
        }
        
        $cmd .= " {$host['user']}@{$host['hostname']}";
        
        return $cmd;
    }
    
    /**
     * Generate VS Code Remote SSH command
     */
    protected function generateVSCodeCommand($host)
    {
        return "code --remote ssh-remote+{$host['host']}";
    }
    
    /**
     * Add new host configuration
     */
    public function addHost($data)
    {
        try {
            // Check if config directory exists
            $configDir = dirname($this->configPath);
            if (!File::exists($configDir)) {
                File::makeDirectory($configDir, 0700, true);
            }
            
            // Check if config file exists, create if not
            if (!File::exists($this->configPath)) {
                File::put($this->configPath, '# SSH Config file generated by Laravel SSH Manager');
            }
            
            $config = "\n\n";
            if (!empty($data['description'])) {
                $config .= "# {$data['description']}\n";
            }
            $config .= "Host {$data['host']}\n";
            $config .= "  HostName {$data['hostname']}\n";
            $config .= "  User {$data['user']}\n";
            $config .= "  IdentityFile {$data['identity_file']}\n";
            
            if (isset($data['port']) && $data['port'] != 22 && !empty($data['port'])) {
                $config .= "  Port {$data['port']}\n";
            }
            
            if (isset($data['domains']) && !empty($data['domains'])) {
                foreach ($data['domains'] as $domain) {
                    if (!empty($domain)) {
                        $config .= "  #Domain {$domain}\n";
                    }
                }
            }
            
            File::append($this->configPath, $config);
            
            return ['success' => true, 'message' => 'Host added successfully'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Error adding host: ' . $e->getMessage()];
        }
    }
    
    /**
     * Update existing host configuration
     */
    public function updateHost($originalHost, $data)
    {
        try {
            if (!File::exists($this->configPath)) {
                return ['success' => false, 'message' => 'Config file not found'];
            }
            
            $content = File::get($this->configPath);
            $lines = explode("\n", $content);
            
            $newContent = [];
            $inTargetHost = false;
            $hostFound = false;
            $skipUntilNextHost = false;
            
            foreach ($lines as $line) {
                if (preg_match('/^Host\s+' . preg_quote($originalHost, '/') . '$/i', trim($line))) {
                    $inTargetHost = true;
                    $hostFound = true;
                    // Add new host configuration
                    if (!empty($data['description'])) {
                        $newContent[] = "# {$data['description']}";
                    }
                    $newContent[] = "Host {$data['host']}";
                    $newContent[] = "  HostName {$data['hostname']}";
                    $newContent[] = "  User {$data['user']}";
                    $newContent[] = "  IdentityFile {$data['identity_file']}";
                    
                    if (isset($data['port']) && $data['port'] != 22 && !empty($data['port'])) {
                        $newContent[] = "  Port {$data['port']}";
                    }
                    
                    if (isset($data['domains']) && !empty($data['domains'])) {
                        foreach ($data['domains'] as $domain) {
                            if (!empty($domain)) {
                                $newContent[] = "  #Domain {$domain}";
                            }
                        }
                    }
                    
                    $skipUntilNextHost = true;
                    continue;
                }
                
                if ($inTargetHost && (preg_match('/^Host\s+/i', trim($line)) || empty(trim($line)))) {
                    $inTargetHost = false;
                    $skipUntilNextHost = false;
                }
                
                if (!$skipUntilNextHost) {
                    $newContent[] = $line;
                }
            }
            
            if (!$hostFound) {
                return ['success' => false, 'message' => 'Host not found'];
            }
            
            File::put($this->configPath, implode("\n", $newContent));
            
            return ['success' => true, 'message' => 'Host updated successfully'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Error updating host: ' . $e->getMessage()];
        }
    }
    
    /**
     * Test connection to server
     */
    public function testConnection($hostname, $port = 22)
    {
        try {
            $connection = @fsockopen($hostname, $port, $errno, $errstr, 5);
            
            if ($connection) {
                fclose($connection);
                return ['success' => true, 'message' => 'Server is reachable'];
            }
            
            return ['success' => false, 'message' => "Cannot reach server: $errstr"];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Connection test failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Expand tilde path to full path
     */
    protected function expandPath($path)
    {
        if (str_starts_with($path, '~/')) {
            return $this->getHomeDirectory() . substr($path, 1);
        }
        return $path;
    }

    /**
     * Delete host configuration
     */
    public function deleteHost($host)
    {
        try {
            if (!File::exists($this->configPath)) {
                return ['success' => false, 'message' => 'Config file not found'];
            }
            
            $content = File::get($this->configPath);
            $lines = explode("\n", $content);
            
            $newContent = [];
            $inTargetHost = false;
            $hostFound = false;
            $skipLines = 0;
            
            foreach ($lines as $line) {
                // Check if this line starts a host block
                if (preg_match('/^Host\s+' . preg_quote($host, '/') . '$/i', trim($line))) {
                    $inTargetHost = true;
                    $hostFound = true;
                    $skipLines = 0;
                    continue;
                }
                
                // If we're in the target host, check if we've reached the next host or empty line
                if ($inTargetHost) {
                    $skipLines++;
                    // Check if we've reached the next host or after 20 lines (safety)
                    if (preg_match('/^Host\s+/i', trim($line)) || $skipLines > 20) {
                        $inTargetHost = false;
                        // Don't add the current line if it's a new host
                        if (!preg_match('/^Host\s+/i', trim($line))) {
                            $newContent[] = $line;
                        } else {
                            // Add the new host line
                            $newContent[] = $line;
                        }
                    }
                    continue;
                }
                
                // Add lines not in the target host
                $newContent[] = $line;
            }
            
            if (!$hostFound) {
                return ['success' => false, 'message' => 'Host not found'];
            }
            
            // Clean up empty lines at the end
            while (end($newContent) === '') {
                array_pop($newContent);
            }
            
            File::put($this->configPath, implode("\n", $newContent));
            
            return ['success' => true, 'message' => 'Host deleted successfully'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Error deleting host: ' . $e->getMessage()];
        }
    }

    public function deleteKeyFile($identityFile)
    {
        try {
            $fullPath = $this->expandPath($identityFile);
            
            if (!File::exists($fullPath)) {
                return ['success' => false, 'message' => 'Key file not found'];
            }
            
            // Check if this key is used by any other server
            $config = $this->parseConfig();
            $usageCount = 0;
            
            foreach ($config['hosts'] as $host) {
                if ($host['identity_file'] === $identityFile) {
                    $usageCount++;
                }
            }
            
            if ($usageCount > 0) {
                return ['success' => false, 'message' => 'Key file is still used by other servers'];
            }
            
            File::delete($fullPath);
            
            return ['success' => true, 'message' => 'Key file deleted successfully'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Error deleting key file: ' . $e->getMessage()];
        }
    }
}