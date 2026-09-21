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
    /** Cache key holding the Num Lock worker process ID */
    protected const NUMLOCK_PID_KEY = 'numlock_toggle_pid';

    /** Cache key holding the Unix timestamp when the worker was started */
    protected const NUMLOCK_STARTED_AT_KEY = 'numlock_started_at';

    /** Seconds between Num Lock toggles (must match the worker loop) */
    protected const NUMLOCK_INTERVAL_SECONDS = 5;

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
        // Process state lives in the cache - no state files on disk
        $pid = Cache::get(self::NUMLOCK_PID_KEY);

        // Check if already running
        if ($pid && $this->isProcessRunning($pid)) {
            return response()->json([
                'success' => false,
                'message' => 'Already running',
                'is_running' => true,
                'count' => $this->getNumLockToggleCount()
            ]);
        }

        // Clean up stale cache entries from a previous run
        $this->forgetNumLockState();

        // Create a simple PHP script instead of bash (more reliable)
        $scriptPath = storage_path('scripts/numlock_toggle.php');
        $scriptDir = dirname($scriptPath);
        if (!file_exists($scriptDir)) {
            mkdir($scriptDir, 0755, true);
        }
        
        // Create PHP script - stateless: it only toggles the key. The toggle
        // count is derived from the cached start time, so the worker never
        // writes anything to disk.
        $phpScript = '<?php
// Num Lock worker - toggles the key every ' . self::NUMLOCK_INTERVAL_SECONDS . ' seconds.
while (true) {
    // Toggle Num Lock via xdotool (key down + key up)
    if (function_exists("shell_exec")) {
        shell_exec("xdotool key Num_Lock 2>/dev/null");
        usleep(100000);
        shell_exec("xdotool key Num_Lock 2>/dev/null");
    }
    sleep(' . self::NUMLOCK_INTERVAL_SECONDS . ');
}
';
        
        file_put_contents($scriptPath, $phpScript);
        
        // Run the PHP script
        $command = "nohup php {$scriptPath} > /dev/null 2>&1 & echo $!";
        $pid = shell_exec($command);
        $pid = trim($pid);

        if ($pid && is_numeric($pid)) {
            // Track the worker in cache instead of flat files
            Cache::forever(self::NUMLOCK_PID_KEY, $pid);
            Cache::forever(self::NUMLOCK_STARTED_AT_KEY, now()->timestamp);

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
        $stopped = false;
        $pid = Cache::get(self::NUMLOCK_PID_KEY);

        if ($pid && $this->isProcessRunning($pid)) {
            exec("kill -9 {$pid} 2>/dev/null");
            $stopped = true;
        }

        // Remove the tracking state from cache
        $this->forgetNumLockState();

        return response()->json([
            'success' => true,
            'message' => $stopped ? 'Num Lock toggling stopped' : 'No running process found',
            'is_running' => false,
            'count' => 0
        ]);
    }

    public function getNumLockStatus(Request $request)
    {
        $pid = Cache::get(self::NUMLOCK_PID_KEY);
        $isRunning = $pid && $this->isProcessRunning($pid);

        // Self-heal: drop stale cache entries if the worker is gone
        if (!$isRunning) {
            if ($pid) {
                $this->forgetNumLockState();
            }

            return response()->json([
                'is_running' => false,
                'count' => 0
            ]);
        }

        return response()->json([
            'is_running' => true,
            'count' => $this->getNumLockToggleCount()
        ]);
    }

    private function isProcessRunning($pid)
    {
        if (!$pid) return false;
        exec("ps -p {$pid} 2>/dev/null", $output, $return_var);
        return $return_var === 0;
    }

    /**
     * Remove the Num Lock tracking state from cache.
     */
    private function forgetNumLockState(): void
    {
        Cache::forget(self::NUMLOCK_PID_KEY);
        Cache::forget(self::NUMLOCK_STARTED_AT_KEY);
    }

    /**
     * Derive the number of toggles from the worker's cached start time.
     * The worker fires its first toggle immediately and then once every
     * NUMLOCK_INTERVAL_SECONDS, so no counter needs to be persisted anywhere.
     */
    private function getNumLockToggleCount(): int
    {
        $startedAt = Cache::get(self::NUMLOCK_STARTED_AT_KEY);

        if (!$startedAt) {
            return 0;
        }

        return (int) floor((now()->timestamp - (int) $startedAt) / self::NUMLOCK_INTERVAL_SECONDS) + 1;
    }
}