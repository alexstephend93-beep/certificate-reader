<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Command;
use App\Services\SshConfigParser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    protected $sshParser;
    
    public function __construct(SshConfigParser $sshParser)
    {
        $this->sshParser = $sshParser;
    }
    
    public function index()
    {
        // Get statistics for dashboard
        $totalCommands = Command::count();
        $favoriteCommands = Command::where('is_favorite', true)->count();
        $recentCommands = Command::orderBy('usage_count', 'desc')->take(5)->get();
        
        // Tool usage statistics
        $toolStats = Cache::get('tool_stats', [
            'certificate' => 1240,
            'chain_validator' => 890,
            'hash_toolbox' => 2100,
            'jwt_analyzer' => 1560,
            'hmac_signature' => 430,
            'api_tester' => 980,
            'base64_codec' => 3420,
            'command_storage' => 560,
        ]);
        
        // Generate bcrypt hashes for common passwords using Laravel Hash
        $commonPasswords = [
            'admin@123',
            'Admin@123', 
            'user@123',
            'User@123',
            'password123',
            'Password123',
            'secret@123',
            'Secret@123',
            'demo@123',
            'Demo@123'
        ];
        
        $bcryptHashes = [];
        foreach ($commonPasswords as $password) {
            $bcryptHashes[] = [
                'password' => $password,
                'hash' => Hash::make($password)
            ];
        }
        
        // Get SSH servers from config
        $sshConfig = $this->sshParser->parseConfig();
        $sshServers = $sshConfig['hosts'] ?? [];
        
        // Get connection history
        $connectionHistory = Cache::get('ssh_connection_history', []);
        
        // Add last connected info to servers
        foreach ($sshServers as &$server) {
            $server['last_connected'] = $connectionHistory[$server['host']] ?? null;
        }
        
        // Get recent connections (last 5 connected servers)
        $recentConnections = array_filter($sshServers, function($server) {
            return !empty($server['last_connected']);
        });
        usort($recentConnections, function($a, $b) {
            return strtotime($b['last_connected']) - strtotime($a['last_connected']);
        });
        $recentConnections = array_slice($recentConnections, 0, 5);
        
        // Get total servers count
        $totalServers = count($sshServers);
        
        return view('dashboard', compact(
            'totalCommands', 
            'favoriteCommands', 
            'recentCommands', 
            'toolStats', 
            'bcryptHashes',
            'sshServers',
            'recentConnections',
            'totalServers'
        ));
    }

    public function theme(Request $request, $themeName)
    {
        return redirect()->back()->cookie('selected_theme', $themeName, 30);
    }

    public function startNumLockToggle(Request $request)
    {
        $pidFile = storage_path('numlock_toggle.pid');
        $countFile = storage_path('numlock_count.txt');
        
        // Check if already running
        if (file_exists($pidFile)) {
            $pid = trim(file_get_contents($pidFile));
            if ($pid && $this->isProcessRunning($pid)) {
                $count = file_exists($countFile) ? intval(file_get_contents($countFile)) : 0;
                return response()->json([
                    'success' => false, 
                    'message' => 'Already running',
                    'is_running' => true,
                    'count' => $count
                ]);
            }
            @unlink($pidFile);
        }
        
        // Reset count file
        file_put_contents($countFile, '0');
        
        // Create a simple PHP script instead of bash (more reliable)
        $scriptPath = storage_path('scripts/numlock_toggle.php');
        $scriptDir = dirname($scriptPath);
        if (!file_exists($scriptDir)) {
            mkdir($scriptDir, 0755, true);
        }
        
        // Create PHP script
        $phpScript = '<?php
$countFile = "' . $countFile . '";
$pidFile = "' . $pidFile . '";
file_put_contents($pidFile, getmypid());
$i = 0;
while (true) {
    $i++;
    file_put_contents($countFile, $i);
    // Try to toggle Num Lock using different methods
    if (function_exists("shell_exec")) {
        shell_exec("xdotool key Num_Lock 2>/dev/null");
        usleep(100000);
        shell_exec("xdotool key Num_Lock 2>/dev/null");
    }
    sleep(5);
}
';
        
        file_put_contents($scriptPath, $phpScript);
        
        // Run the PHP script
        $command = "nohup php {$scriptPath} > /dev/null 2>&1 & echo $!";
        $pid = shell_exec($command);
        $pid = trim($pid);
        
        if ($pid && is_numeric($pid)) {
            file_put_contents($pidFile, $pid);
            return response()->json([
                'success' => true, 
                'message' => 'Num Lock toggling started',
                'is_running' => true,
                'count' => 0
            ]);
        } else {
            return response()->json([
                'success' => false, 
                'message' => 'Failed to start script',
                'is_running' => false,
                'count' => 0
            ]);
        }
    }

    public function stopNumLockToggle(Request $request)
    {
        $pidFile = storage_path('numlock_toggle.pid');
        $countFile = storage_path('numlock_count.txt');
        
        $stopped = false;
        
        if (file_exists($pidFile)) {
            $pid = trim(file_get_contents($pidFile));
            if ($pid && $this->isProcessRunning($pid)) {
                exec("kill -9 {$pid} 2>/dev/null");
                $stopped = true;
            }
            @unlink($pidFile);
        }
        
        // Reset count
        if (file_exists($countFile)) {
            file_put_contents($countFile, '0');
        }
        
        return response()->json([
            'success' => true, 
            'message' => $stopped ? 'Num Lock toggling stopped' : 'No running process found',
            'is_running' => false,
            'count' => 0
        ]);
    }

    public function getNumLockStatus(Request $request)
    {
        $pidFile = storage_path('numlock_toggle.pid');
        $countFile = storage_path('numlock_count.txt');
        
        $isRunning = false;
        $count = 0;
        
        // Check if process is running
        if (file_exists($pidFile)) {
            $pid = trim(file_get_contents($pidFile));
            if ($pid && $this->isProcessRunning($pid)) {
                $isRunning = true;
            } else {
                @unlink($pidFile);
            }
        }
        
        // Get count from file
        if (file_exists($countFile)) {
            $count = intval(file_get_contents($countFile));
        }
        
        return response()->json([
            'is_running' => $isRunning,
            'count' => $count
        ]);
    }

    private function isProcessRunning($pid)
    {
        if (!$pid) return false;
        exec("ps -p {$pid} 2>/dev/null", $output, $return_var);
        return $return_var === 0;
    }
}