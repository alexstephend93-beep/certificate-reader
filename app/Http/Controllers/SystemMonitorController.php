<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SystemMonitorController extends Controller
{
    public function index()
    {
        return view('system-monitor.index');
    }

    public function getData(Request $request)
    {
        try {
            set_time_limit(30);

            $filter = $request->input('filter', 'both');

            $systemInfo = $this->getSystemInfo();

            if ($filter === 'both') {
                $combined = $this->getCombinedMemoryInfo($systemInfo);
                $systemInfo['combined']              = $combined;
                $systemInfo['total_combined']        = $combined['total_gb'];
                $systemInfo['used_combined']         = $combined['used_gb'];
                $systemInfo['free_combined']         = $combined['free_gb'];
                $systemInfo['used_combined_percent'] = $combined['used_percent'];
                $systemInfo['free_combined_percent'] = $combined['free_percent'];
            }

            $processes    = $this->getTopProcesses(100, $filter);
            $appProcesses = $this->getApplicationProcesses($filter);
            $healthStatus = $this->getHealthStatus($systemInfo, $filter);

            return response()->json([
                'success'      => true,
                'systemInfo'   => $systemInfo,
                'processes'    => $processes,
                'appProcesses' => $appProcesses,
                'healthStatus' => $healthStatus,
                'filter'       => $filter,
            ]);
        } catch (\Exception $e) {
            Log::error('System monitor data error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching system data: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  ENHANCED CACHE CLEARING WITH ALL SAFE CLEANUPS
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Enhanced cache clearing - includes all safe cleanups
     * Returns array with detailed breakdown
     */
    private function clearSystemCacheEnhanced(): array
    {
        $result = [
            'cache_freed_mb' => 0,
            'shared_freed_mb' => 0,
            'zombies_reaped' => 0,
            'locks_cleaned' => 0,
            'total_freed_mb' => 0,
            'details' => []
        ];

        // 1. Clear standard system caches (page cache, dentries, inodes)
        $dropCachesPath = '/proc/sys/vm/drop_caches';
        if (is_writable($dropCachesPath)) {
            $cacheBefore = $this->getCacheSizeMb();
            shell_exec('sync 2>/dev/null');
            shell_exec('echo 3 > /proc/sys/vm/drop_caches 2>/dev/null');
            usleep(200000);
            $cacheAfter = $this->getCacheSizeMb();
            $result['cache_freed_mb'] = round(max(0, $cacheBefore - $cacheAfter), 1);
            $result['details'][] = 'Cache cleared: ' . $result['cache_freed_mb'] . ' MB';
        } else {
            $result['details'][] = 'Cache clear skipped (no write access to /proc/sys/vm/drop_caches)';
        }

        // 2. Clear orphaned shared memory segments
        $sharedFreed = $this->clearSharedMemory();
        $result['shared_freed_mb'] = $sharedFreed;
        if ($sharedFreed > 0) {
            $result['details'][] = 'Shared memory cleaned: ' . $sharedFreed . ' MB';
        }

        // 3. Reap zombie processes
        $zombiesReaped = $this->reapZombies();
        $result['zombies_reaped'] = $zombiesReaped;
        if ($zombiesReaped > 0) {
            $result['details'][] = 'Reaped ' . $zombiesReaped . ' zombie processes';
        }

        // 4. Clean stale file locks
        $locksCleaned = $this->clearStaleLocks();
        $result['locks_cleaned'] = $locksCleaned;
        if ($locksCleaned > 0) {
            $result['details'][] = 'Cleaned ' . $locksCleaned . ' stale locks';
        }

        // 5. Compact memory
        shell_exec('echo 1 > /proc/sys/vm/compact_memory 2>/dev/null');

        $result['total_freed_mb'] = round(
            $result['cache_freed_mb'] + 
            $result['shared_freed_mb'], 
            1
        );

        return $result;
    }

    /**
     * Clear orphaned shared memory segments
     */
    private function clearSharedMemory(): float
    {
        $freedMb = 0;
        
        // List all shared memory segments
        $output = shell_exec('ipcs -m 2>/dev/null | grep -v "^------" | grep -v "shmid" | awk \'{print $2, $6}\'');
        if (!$output) return 0;
        
        $lines = explode("\n", trim($output));
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) < 2) continue;
            
            $shmid = (int)$parts[0];
            $nattch = (int)$parts[1];
            
            // Only remove if no processes are attached
            if ($nattch == 0) {
                shell_exec("ipcrm -m $shmid 2>/dev/null");
                $freedMb += 0.1; // Approximate
            }
        }
        
        return round($freedMb, 1);
    }

    /**
     * Reap zombie processes
     */
    private function reapZombies(): int
    {
        $count = 0;
        $output = shell_exec('ps aux 2>/dev/null | grep -E "^Z" | awk \'{print $2}\'');
        if (!$output) return 0;
        
        $pids = explode("\n", trim($output));
        foreach ($pids as $pid) {
            if (empty($pid)) continue;
            // Wait for the zombie to be reaped by its parent
            shell_exec("wait $pid 2>/dev/null");
            $count++;
        }
        
        return $count;
    }

    /**
     * Clean stale file locks (only in /tmp and /var/run)
     */
    private function clearStaleLocks(): int
    {
        $count = 0;
        
        // Check lock files older than 1 hour
        $output = shell_exec('find /tmp /var/run -name "*.lock" -type f -mmin +60 2>/dev/null');
        if (!$output) return 0;
        
        $locks = explode("\n", trim($output));
        foreach ($locks as $lock) {
            if (empty($lock)) continue;
            // Check if any process is using this lock
            $using = shell_exec("lsof $lock 2>/dev/null | wc -l");
            if ((int)$using == 0) {
                shell_exec("rm -f $lock 2>/dev/null");
                $count++;
            }
        }
        
        return $count;
    }

    /**
     * Get current cache size in MB
     */
    private function getCacheSizeMb(): float
    {
        $freeOutput = shell_exec('free -m 2>/dev/null');
        if (!$freeOutput) return 0;

        foreach (explode("\n", $freeOutput) as $line) {
            if (strpos($line, 'Mem:') !== false) {
                $parts = preg_split('/\s+/', $line);
                return (float)($parts[5] ?? 0);
            }
        }
        return 0;
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  AUTOCLEAN  –  Chrome low-priority
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Clear low-priority Chrome processes + enhanced system cache
     */
    public function clearChromeLowPriority(Request $request)
    {
        $thresholds = [
            'cpu'        => 0.5,
            'memory'     => 100,
            'keep_count' => 3,
        ];

        $processes        = $this->getTopProcesses(200);
        $chromeProcesses  = [];

        foreach ($processes as $p) {
            $cmd = strtolower($p['command']);
            if (
                strpos($cmd, 'chrome') === false &&
                strpos($cmd, 'chromium') === false &&
                strpos($cmd, 'google-chrome') === false
            ) {
                continue;
            }
            if ($p['user'] === 'root' || $p['user'] === 'systemd') {
                continue;
            }
            $chromeProcesses[] = $p;
        }

        if (empty($chromeProcesses)) {
            // Still try to clear everything
            $cleanupResult = $this->clearSystemCacheEnhanced();
            return response()->json([
                'success'          => true,
                'count'            => 0,
                'memory_freed_mb'  => $cleanupResult['total_freed_mb'],
                'message'          => 'No Chrome processes found. ' . implode('; ', $cleanupResult['details']),
                'cache_freed_mb'   => $cleanupResult['cache_freed_mb'],
                'shared_freed_mb'  => $cleanupResult['shared_freed_mb'],
                'zombies_reaped'   => $cleanupResult['zombies_reaped'],
                'locks_cleaned'    => $cleanupResult['locks_cleaned'],
                'cleanup_details'  => $cleanupResult['details'],
            ]);
        }

        usort($chromeProcesses, fn($a, $b) => $b['cpu'] <=> $a['cpu']);

        $keepCount = min($thresholds['keep_count'], count($chromeProcesses));
        $toKill    = array_slice($chromeProcesses, $keepCount);

        $finalKill = [];
        foreach ($toKill as $p) {
            if ($p['cpu'] < $thresholds['cpu'] && $p['mem_mb'] > $thresholds['memory']) {
                $finalKill[] = $p;
            } elseif ($p['mem_mb'] > 300) {
                $finalKill[] = $p;
            }
        }

        $result = $this->killProcessList($finalKill, 'Chrome processes');
        $resultData = $result->getData(true);

        // Enhanced cleanup
        $cleanupResult = $this->clearSystemCacheEnhanced();
        $resultData['cache_freed_mb'] = $cleanupResult['cache_freed_mb'];
        $resultData['shared_freed_mb'] = $cleanupResult['shared_freed_mb'];
        $resultData['zombies_reaped'] = $cleanupResult['zombies_reaped'];
        $resultData['locks_cleaned'] = $cleanupResult['locks_cleaned'];
        $resultData['cleanup_details'] = $cleanupResult['details'];
        $resultData['memory_freed_mb'] = round(($resultData['memory_freed_mb'] ?? 0) + $cleanupResult['total_freed_mb'], 1);
        $resultData['message'] .= ' + ' . implode('; ', $cleanupResult['details']);

        return response()->json($resultData);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  AUTOCLEAN  –  Standard (safe, non-system) + Enhanced Cleanup
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Safe, non-blocking kill of idle / low-priority background processes + enhanced cleanup.
     */
    public function clearLowPriority(Request $request)
    {
        $thresholds = [
            'cpu'        => 0.5,
            'memory'     => 100,
            'keep_count' => 3,
        ];

        $importantApps = [
            'VS Code', 'Chrome', 'Firefox', 'Slack', 'Docker',
            'MySQL', 'PostgreSQL', 'Redis', 'Nginx', 'Apache', 'System',
        ];

        $processes = $this->getTopProcesses(200);
        $toKill    = [];

        // Group by app, keep top N per app
        $appGroups = [];
        foreach ($processes as $p) {
            if (in_array($p['user'], ['root', 'systemd', 'daemon'], true)) {
                continue;
            }
            $appName = $this->getAppNameFromCommand($p['command']);
            if (in_array($appName, $importantApps, true)) {
                continue;
            }
            $appGroups[$appName][] = $p;
        }

        foreach ($appGroups as $procs) {
            usort($procs, fn($a, $b) => $b['cpu'] <=> $a['cpu']);
            $keepCount = min($thresholds['keep_count'], count($procs));
            $toRemove  = array_slice($procs, $keepCount);
            foreach ($toRemove as $p) {
                if ($p['cpu'] < $thresholds['cpu'] && $p['mem_mb'] > $thresholds['memory']) {
                    $toKill[] = $p;
                }
            }
        }

        // Also catch idle background processes
        $existingPids = array_column($toKill, 'pid');
        foreach ($processes as $p) {
            if (in_array($p['pid'], $existingPids, true)) {
                continue;
            }
            if (in_array($p['user'], ['root', 'systemd'], true)) {
                continue;
            }
            if ($p['cpu'] > 0.8) {
                continue;
            }
            $cmd    = strtolower($p['command']);
            $isIdle = strpos($cmd, 'sleep') !== false
                   || strpos($cmd, 'idle') !== false
                   || strpos($cmd, 'wait') !== false
                   || strpos($cmd, 'background') !== false;

            if ($isIdle && $p['mem_mb'] > 120 && $p['cpu'] < 0.3) {
                $toKill[] = $p;
            }
        }

        $result = $this->killProcessList($toKill, 'low-priority processes');
        $resultData = $result->getData(true);

        // Enhanced cleanup
        $cleanupResult = $this->clearSystemCacheEnhanced();
        $resultData['cache_freed_mb'] = $cleanupResult['cache_freed_mb'];
        $resultData['shared_freed_mb'] = $cleanupResult['shared_freed_mb'];
        $resultData['zombies_reaped'] = $cleanupResult['zombies_reaped'];
        $resultData['locks_cleaned'] = $cleanupResult['locks_cleaned'];
        $resultData['cleanup_details'] = $cleanupResult['details'];
        $resultData['memory_freed_mb'] = round(($resultData['memory_freed_mb'] ?? 0) + $cleanupResult['total_freed_mb'], 1);
        $resultData['message'] .= ' + ' . implode('; ', $cleanupResult['details']);

        return response()->json($resultData);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  KILL HELPERS
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Shared kill logic used by both autoclean endpoints.
     */
    private function killProcessList(array $processList, string $label): \Illuminate\Http\JsonResponse
    {
        $byPid = [];
        foreach ($processList as $p) {
            $byPid[(int)$p['pid']] = $p;
        }

        $killed          = [];
        $failed          = [];
        $memoryFreedMb   = 0.0;

        foreach ($byPid as $pid => $p) {
            $check = shell_exec("ps -p $pid -o pid= 2>/dev/null");
            if (empty(trim($check))) {
                continue;
            }

            [$allow] = $this->shouldAllowKillPid($pid);
            if (!$allow) {
                continue;
            }

            $rssBefore = $this->getRssKb($pid);

            shell_exec("kill -15 $pid 2>/dev/null");
            usleep(200_000);

            if (empty(trim(shell_exec("ps -p $pid -o pid= 2>/dev/null")))) {
                $killed[]         = $pid;
                $memoryFreedMb   += $rssBefore / 1024;
                continue;
            }

            shell_exec("kill -9 $pid 2>/dev/null");
            usleep(200_000);

            if (empty(trim(shell_exec("ps -p $pid -o pid= 2>/dev/null")))) {
                $killed[]         = $pid;
                $memoryFreedMb   += $rssBefore / 1024;
                continue;
            }

            $pgid = trim(shell_exec("ps -p $pid -o pgid= 2>/dev/null"));
            if ($pgid && (int)$pgid > 0) {
                shell_exec("kill -9 -$pgid 2>/dev/null");
                usleep(200_000);
                if (empty(trim(shell_exec("ps -p $pid -o pid= 2>/dev/null")))) {
                    $killed[]         = $pid;
                    $memoryFreedMb   += $rssBefore / 1024;
                    continue;
                }
            }

            $failed[] = $pid;
        }

        $freedRounded = round($memoryFreedMb, 1);
        $failStr      = count($failed) > 0
            ? '. Failed to kill ' . count($failed) . ' process(es).'
            : '';

        return response()->json([
            'success'         => true,
            'killed'          => $killed,
            'failed'          => $failed,
            'count'           => count($killed),
            'memory_freed_mb' => $freedRounded,
            'message'         => "Killed " . count($killed) . " {$label}, freed ~{$freedRounded} MB" . $failStr,
        ]);
    }

    private function getRssKb(int $pid): int
    {
        $statusPath = "/proc/{$pid}/status";
        if (file_exists($statusPath)) {
            $content = @file_get_contents($statusPath);
            if ($content && preg_match('/VmRSS:\s+(\d+)\s+kB/i', $content, $m)) {
                return (int)$m[1];
            }
        }
        $rss = trim(shell_exec("ps -p $pid -o rss= 2>/dev/null"));
        return $rss ? (int)$rss : 0;
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  KILL INDIVIDUAL / MULTIPLE / APPLICATION
    // ──────────────────────────────────────────────────────────────────────────

    public function killProcess(Request $request)
    {
        $request->validate(['pid' => 'required|integer|min:1']);
        $pid = (int)$request->pid;

        $check = shell_exec("ps -p $pid -o pid= 2>/dev/null");
        if (empty(trim($check))) {
            return response()->json(['success' => false, 'message' => 'Process not found']);
        }

        $procName = trim(shell_exec("ps -p $pid -o comm= 2>/dev/null")) ?: 'unknown';
        $pgid     = trim(shell_exec("ps -p $pid -o pgid= 2>/dev/null"));

        $children = array_filter(explode("\n", trim(shell_exec("ps --ppid $pid -o pid= 2>/dev/null"))));
        $toKill   = array_unique(array_merge([$pid], $children));

        if ($pgid && (int)$pgid > 0) {
            $groupProcs = array_filter(explode("\n", trim(shell_exec("ps -g $pgid -o pid= 2>/dev/null"))));
            $toKill     = array_unique(array_merge($toKill, $groupProcs));
        }

        $killed  = [];
        $failed  = [];
        $skipped = [];

        foreach ($toKill as $p) {
            [$allow] = $this->shouldAllowKillPid((int)$p);
            if (!$allow) {
                $skipped[] = (int)$p;
                continue;
            }
            shell_exec("kill -15 $p 2>/dev/null");
        }

        usleep(500_000);

        foreach ($toKill as $p) {
            if (in_array((int)$p, $skipped, true)) {
                continue;
            }
            if (empty(trim(shell_exec("ps -p $p -o pid= 2>/dev/null")))) {
                $killed[] = $p;
            } else {
                shell_exec("kill -9 $p 2>/dev/null");
                usleep(100_000);
                if (empty(trim(shell_exec("ps -p $p -o pid= 2>/dev/null")))) {
                    $killed[] = $p;
                } else {
                    $failed[] = $p;
                }
            }
        }

        if (!empty($failed) && $pgid && (int)$pgid > 0) {
            shell_exec("kill -9 -$pgid 2>/dev/null");
            usleep(300_000);
            foreach ($failed as $p) {
                if (empty(trim(shell_exec("ps -p $p -o pid= 2>/dev/null")))) {
                    $killed[] = $p;
                }
            }
            $failed = array_diff($failed, $killed);
        }

        $skipped = array_values(array_unique(array_map('intval', $skipped)));

        if (!empty($killed)) {
            $msg = "Killed process PID: $pid ($procName) and " . (count($killed) - 1) . " child processes";
            if (!empty($skipped)) {
                $msg .= ". Skipped " . count($skipped) . " restart-sensitive process(es).";
            }
            return response()->json(['success' => true, 'message' => $msg, 'skipped' => $skipped]);
        }

        return response()->json([
            'success' => false,
            'message' => "Failed to kill process PID: $pid" . (!empty($skipped) ? ". Skipped " . count($skipped) . " restart-sensitive process(es)." : ''),
            'skipped' => $skipped,
        ]);
    }

    public function killMultipleProcesses(Request $request)
    {
        $request->validate(['pids' => 'required|array', 'pids.*' => 'integer|min:1']);

        $killed = [];
        $failed = [];

        foreach ($request->pids as $pid) {
            $pid = (int)$pid;
            if (empty(trim(shell_exec("ps -p $pid -o pid= 2>/dev/null")))) {
                $failed[] = $pid;
                continue;
            }

            $pgid     = trim(shell_exec("ps -p $pid -o pgid= 2>/dev/null"));
            $children = array_filter(explode("\n", trim(shell_exec("ps --ppid $pid -o pid= 2>/dev/null"))));
            $toKill   = array_unique(array_merge([$pid], $children));

            if ($pgid && (int)$pgid > 0) {
                $groupProcs = array_filter(explode("\n", trim(shell_exec("ps -g $pgid -o pid= 2>/dev/null"))));
                $toKill     = array_unique(array_merge($toKill, $groupProcs));
            }

            foreach ($toKill as $p) {
                [$allow] = $this->shouldAllowKillPid((int)$p);
                if (!$allow) continue;
                shell_exec("kill -15 $p 2>/dev/null");
            }
            usleep(300_000);

            foreach ($toKill as $p) {
                [$allow] = $this->shouldAllowKillPid((int)$p);
                if (!$allow) continue;
                if (empty(trim(shell_exec("ps -p $p -o pid= 2>/dev/null")))) {
                    $killed[] = $p;
                } else {
                    shell_exec("kill -9 $p 2>/dev/null");
                    usleep(100_000);
                    if (empty(trim(shell_exec("ps -p $p -o pid= 2>/dev/null")))) {
                        $killed[] = $p;
                    } else {
                        $failed[] = $p;
                    }
                }
            }
        }

        $killed = array_values(array_unique($killed));
        $failed = array_values(array_diff(array_unique($failed), $killed));

        return response()->json([
            'success'      => true,
            'killed'       => $killed,
            'failed'       => $failed,
            'killed_count' => count($killed),
            'failed_count' => count($failed),
            'skipped'      => [],
            'message'      => "Killed " . count($killed) . " processes, " . count($failed) . " failed",
        ]);
    }

    public function killApplication(Request $request)
    {
        $request->validate(['app_name' => 'required|string|max:100']);

        $appName  = $request->app_name;
        $patterns = $this->getAppPatterns($appName);
        $allPids  = $this->collectAllProcessPids($patterns);

        if (empty($allPids)) {
            return response()->json(['success' => false, 'message' => "No processes found for application: $appName"]);
        }

        $processTree = $this->buildProcessTree($allPids);
        $killed      = [];
        $failed      = [];

        foreach ($processTree as $pids) {
            foreach ($pids as $pid) {
                if (in_array($pid, $killed)) continue;

                [$allow] = $this->shouldAllowKillPid((int)$pid);
                if (!$allow) {
                    $failed[] = $pid;
                    continue;
                }

                $check = shell_exec("ps -p $pid -o pid= 2>/dev/null");
                if (empty(trim($check))) {
                    $killed[] = $pid;
                    continue;
                }

                $pgid = trim(shell_exec("ps -p $pid -o pgid= 2>/dev/null"));

                shell_exec("kill -15 $pid 2>/dev/null");
                usleep(300_000);

                if (empty(trim(shell_exec("ps -p $pid -o pid= 2>/dev/null")))) {
                    $killed[] = $pid;
                    continue;
                }

                shell_exec("kill -9 $pid 2>/dev/null");
                usleep(200_000);

                if (empty(trim(shell_exec("ps -p $pid -o pid= 2>/dev/null")))) {
                    $killed[] = $pid;
                    continue;
                }

                if ($pgid && (int)$pgid > 0) {
                    shell_exec("kill -9 -$pgid 2>/dev/null");
                    usleep(200_000);
                    if (empty(trim(shell_exec("ps -p $pid -o pid= 2>/dev/null")))) {
                        $killed[] = $pid;
                        continue;
                    }
                }

                $failed[] = $pid;
            }
        }

        $remaining = $this->collectAllProcessPids($patterns);
        if (!empty($remaining)) {
            foreach ($remaining as $pid) {
                $pgid = trim(shell_exec("ps -p $pid -o pgid= 2>/dev/null"));
                if ($pgid && (int)$pgid > 0) shell_exec("kill -9 -$pgid 2>/dev/null");
                shell_exec("kill -9 $pid 2>/dev/null");
                usleep(100_000);
            }
            $remaining = $this->collectAllProcessPids($patterns);
            foreach ($remaining as $pid) {
                if (!in_array($pid, $failed)) $failed[] = $pid;
            }
        }

        $killed = array_values(array_unique($killed));
        $failed = array_values(array_diff(array_unique($failed), $killed));

        return response()->json([
            'success'      => true,
            'app_name'     => $appName,
            'killed'       => $killed,
            'failed'       => $failed,
            'killed_count' => count($killed),
            'failed_count' => count($failed),
            'message'      => empty($failed)
                ? "✅ Killed all " . count($killed) . " processes for $appName"
                : "✅ Killed " . count($killed) . " processes for $appName. " . count($failed) . " process(es) could not be terminated.",
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  SAFE KILL LOW PRIORITY (explicit button)
    // ──────────────────────────────────────────────────────────────────────────

    public function killLowPriorityOnly(Request $request)
    {
        return $this->clearLowPriority($request);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  FREE MEMORY - Enhanced with all cleanups
    // ──────────────────────────────────────────────────────────────────────────

    public function freeMemory()
    {
        $cleanupResult = $this->clearSystemCacheEnhanced();
        
        $systemInfo = $this->getSystemInfo();
        $message = "Cleaned: " . implode('; ', $cleanupResult['details']) 
                    . ". Total freed: " . $cleanupResult['total_freed_mb'] . " MB. "
                    . "Free RAM: {$systemInfo['free_ram']}, "
                    . "Available RAM: {$systemInfo['available_ram']}, "
                    . "Buffer/Cache: {$systemInfo['buff_cache']}. "
                    . "Use Kill App / Clear Low Priority to free application RAM.";

        return response()->json([
            'success'    => true,
            'message'    => $message,
            'systemInfo' => $systemInfo,
            'details'    => $cleanupResult['details'],
            'cache_freed_mb' => $cleanupResult['cache_freed_mb'],
            'shared_freed_mb' => $cleanupResult['shared_freed_mb'],
            'zombies_reaped' => $cleanupResult['zombies_reaped'],
            'locks_cleaned' => $cleanupResult['locks_cleaned'],
            'total_freed_mb' => $cleanupResult['total_freed_mb'],
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  PROCESS HELPERS
    // ──────────────────────────────────────────────────────────────────────────

    private function getProcessSwapUsage(int $pid): float
    {
        $statusPath = "/proc/{$pid}/status";
        if (!file_exists($statusPath)) return 0.0;
        $content = @file_get_contents($statusPath);
        if ($content && preg_match('/VmSwap:\s+(\d+)\s+kB/i', $content, $m)) {
            return (int)$m[1] / 1024;
        }
        return 0.0;
    }

    private function getTopProcesses(int $limit = 100, string $filter = 'both'): array
    {
        $output = shell_exec("ps aux --sort=-%mem 2>/dev/null | head -500");
        if (!$output) return [];

        $lines     = explode("\n", trim($output));
        $processes = [];

        for ($i = 1; $i < count($lines); $i++) {
            if (empty(trim($lines[$i]))) continue;
            $parts = preg_split('/\s+/', $lines[$i], 11);
            if (count($parts) < 11) continue;

            $pid    = (int)$parts[1];
            $rssKB  = (int)$parts[5];
            $memMB  = round($rssKB / 1024, 2);
            $swapMB = $this->getProcessSwapUsage($pid);
            $cpu    = $this->normalizeCpuPercent((float)$parts[2]);

            $process = [
                'user'        => $parts[0],
                'pid'         => $pid,
                'cpu'         => $cpu,
                'mem'         => (float)$parts[3],
                'mem_mb'      => $memMB,
                'swap_mb'     => $swapMB,
                'combined_mb' => $memMB + $swapMB,
                'vsz'         => $parts[4],
                'rss'         => $parts[5],
                'stat'        => $parts[7],
                'start'       => $parts[8],
                'time'        => $parts[9],
                'command'     => $parts[10],
            ];

            if ($this->isCriticalProcess($process)) continue;

            $processes[] = $process;
        }

        usort($processes, function ($a, $b) use ($filter) {
            return match ($filter) {
                'ram'   => $b['mem_mb']      <=> $a['mem_mb'],
                'swap'  => $b['swap_mb']     <=> $a['swap_mb'],
                default => $b['combined_mb'] <=> $a['combined_mb'],
            };
        });

        return array_slice($processes, 0, $limit);
    }

    private function getApplicationProcesses(string $filter = 'both'): array
    {
        $apps = [
            'Chrome'       => ['chrome', 'chromium', 'google-chrome'],
            'Slack'        => ['slack'],
            'Firefox'      => ['firefox'],
            'VS Code'      => ['code', 'vscode', 'code-insiders', 'electron'],
            'Docker'       => ['docker', 'containerd', 'dockerd'],
            'Node'         => ['node', 'npm', 'yarn'],
            'Python'       => ['python', 'python3'],
            'Java'         => ['java'],
            'PHP'          => ['php', 'php-fpm'],
            'Nginx'        => ['nginx'],
            'Apache'       => ['apache', 'httpd'],
            'MySQL'        => ['mysql', 'mysqld'],
            'PostgreSQL'   => ['postgres', 'postgresql'],
            'Redis'        => ['redis-server'],
            'Elasticsearch'=> ['elasticsearch'],
            'Kibana'       => ['kibana'],
            'Grafana'      => ['grafana'],
            'Prometheus'   => ['prometheus'],
            'Zoom'         => ['zoom'],
            'Teams'        => ['teams'],
            'Discord'      => ['discord'],
            'Spotify'      => ['spotify'],
            'Thunderbird'  => ['thunderbird'],
            'LibreOffice'  => ['soffice', 'libreoffice'],
            'GIMP'         => ['gimp'],
            'VLC'          => ['vlc'],
            'File Manager' => ['nautilus', 'dolphin', 'thunar', 'nemo'],
            'Terminal'     => ['gnome-terminal', 'konsole', 'kitty', 'alacritty', 'tilix'],
            'System'       => ['systemd', 'init', 'kernel'],
        ];

        $appProcesses = [];

        foreach ($apps as $appName => $patterns) {
            $processes    = [];
            $totalRam     = 0;
            $totalSwap    = 0;
            $totalCombined = 0;
            $pids         = [];

            foreach ($patterns as $pattern) {
                $output = shell_exec("ps aux | grep -i '$pattern' | grep -v grep 2>/dev/null");
                if (!$output) continue;

                foreach (explode("\n", trim($output)) as $line) {
                    if (empty($line)) continue;
                    $parts = preg_split('/\s+/', $line, 11);
                    if (count($parts) < 11) continue;

                    $pid   = (int)$parts[1];
                    $memMB = round((int)$parts[5] / 1024, 2);
                    $cpu   = $this->normalizeCpuPercent((float)$parts[2]);
                    $swapMB = $this->getProcessSwapUsage($pid);

                    if (in_array($pid, $pids)) continue;
                    if ($cpu < 0.1 && $memMB < 1) continue;

                    $pids[]       = $pid;
                    $totalRam    += $memMB;
                    $totalSwap   += $swapMB;
                    $totalCombined += ($memMB + $swapMB);

                    $processes[] = [
                        'pid'         => $pid,
                        'cpu'         => $cpu,
                        'mem_mb'      => $memMB,
                        'swap_mb'     => $swapMB,
                        'combined_mb' => $memMB + $swapMB,
                        'command'     => substr($parts[10], 0, 80),
                    ];
                }
            }

            if (empty($processes)) continue;

            usort($processes, function ($a, $b) use ($filter) {
                return match ($filter) {
                    'ram'   => $b['mem_mb']      <=> $a['mem_mb'],
                    'swap'  => $b['swap_mb']     <=> $a['swap_mb'],
                    default => $b['combined_mb'] <=> $a['combined_mb'],
                };
            });

            $appProcesses[] = [
                'app'              => $appName,
                'processes'        => $processes,
                'total_cpu'        => round(array_sum(array_column($processes, 'cpu')), 1),
                'total_ram_mb'     => round($totalRam, 0),
                'total_swap_mb'    => round($totalSwap, 0),
                'total_combined_mb'=> round($totalCombined, 0),
                'process_count'    => count($processes),
                'pids'             => $pids,
            ];
        }

        usort($appProcesses, function ($a, $b) use ($filter) {
            return match ($filter) {
                'ram'   => $b['total_ram_mb']      <=> $a['total_ram_mb'],
                'swap'  => $b['total_swap_mb']     <=> $a['total_swap_mb'],
                default => $b['total_combined_mb'] <=> $a['total_combined_mb'],
            };
        });

        return array_slice($appProcesses, 0, 20);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  PROCESS TREE / PATTERN HELPERS
    // ──────────────────────────────────────────────────────────────────────────

    private function collectAllProcessPids(array $patterns): array
    {
        $allPids = [];
        $selfPid = getmypid();

        foreach ($patterns as $pattern) {
            $safePattern = escapeshellarg($pattern);

            $pgrepOutput = shell_exec("pgrep -f $safePattern 2>/dev/null");
            if ($pgrepOutput) {
                $allPids = array_merge($allPids, array_filter(explode("\n", trim($pgrepOutput))));
            }

            $psOutput = shell_exec("ps aux 2>/dev/null | grep -i -- $safePattern | grep -v grep | awk '{print \$2}'");
            if ($psOutput) {
                $allPids = array_merge($allPids, array_filter(explode("\n", trim($psOutput))));
            }

            $psOutput2 = shell_exec("ps -ef 2>/dev/null | grep -i -- $safePattern | grep -v grep | awk '{print \$2}'");
            if ($psOutput2) {
                $allPids = array_merge($allPids, array_filter(explode("\n", trim($psOutput2))));
            }

            $basePattern = basename(str_replace(['"', "'"], '', $pattern));
            if (strlen($basePattern) > 3) {
                $psOutput3 = shell_exec("ps aux 2>/dev/null | grep -i -- " . escapeshellarg($basePattern) . " | grep -v grep | awk '{print \$2}'");
                if ($psOutput3) {
                    $allPids = array_merge($allPids, array_filter(explode("\n", trim($psOutput3))));
                }
            }
        }

        $allPids = array_values(array_filter(array_unique(array_map('intval', $allPids)), fn($p) => $p > 1 && $p !== $selfPid));

        $expandedPids = $allPids;
        foreach ($allPids as $pid) {
            $ppid = (int)trim(shell_exec("ps -p $pid -o ppid= 2>/dev/null"));
            if ($ppid > 1) $expandedPids[] = $ppid;
            $pgid = (int)trim(shell_exec("ps -p $pid -o pgid= 2>/dev/null"));
            if ($pgid > 1 && $pgid !== $pid) $expandedPids[] = $pgid;
        }

        $expandedPids = array_values(array_filter(array_unique($expandedPids), fn($p) => $p > 1 && $p !== $selfPid));

        $finalPids = [];
        foreach ($expandedPids as $pid) {
            $cmd = shell_exec("ps -p $pid -o cmd= 2>/dev/null");
            if ($cmd) {
                $cmdLower = strtolower($cmd);
                foreach ($patterns as $pattern) {
                    if (strpos($cmdLower, strtolower($pattern)) !== false) {
                        $finalPids[] = $pid;
                        break;
                    }
                }
            }
        }

        $expandedPids = array_values(array_filter(array_unique(array_merge($expandedPids, $finalPids)), fn($p) => $p > 1 && $p !== $selfPid));

        return $expandedPids;
    }

    private function buildProcessTree(array $pids): array
    {
        if (empty($pids)) return [];

        $processes = [];
        foreach ($pids as $pid) {
            $ppid = (int)trim(shell_exec("ps -p $pid -o ppid= 2>/dev/null"));
            $processes[$pid] = ['pid' => $pid, 'ppid' => $ppid, 'children' => []];
        }

        foreach ($processes as $pid => &$proc) {
            if ($proc['ppid'] > 0 && isset($processes[$proc['ppid']])) {
                $processes[$proc['ppid']]['children'][] = $pid;
            }
        }

        $roots = [];
        foreach ($processes as $pid => $proc) {
            if ($proc['ppid'] <= 1 || !isset($processes[$proc['ppid']])) {
                $roots[] = $pid;
            }
        }

        $levels       = [];
        $currentLevel = $roots;
        $visited      = [];
        $levelIndex   = 0;

        while (!empty($currentLevel)) {
            $levels[$levelIndex] = $currentLevel;
            $nextLevel = [];
            foreach ($currentLevel as $pid) {
                if (!isset($visited[$pid])) {
                    $visited[$pid] = true;
                    foreach ($processes[$pid]['children'] ?? [] as $child) {
                        if (!isset($visited[$child])) $nextLevel[] = $child;
                    }
                }
            }
            $currentLevel = array_unique($nextLevel);
            $levelIndex++;
        }

        return $levels;
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  SYSTEM INFO
    // ──────────────────────────────────────────────────────────────────────────

    private function getSystemInfo(): array
    {
        $os = PHP_OS;
        if (stripos($os, 'linux') !== false || stripos($os, 'darwin') !== false) {
            return $this->getUnixSystemInfo();
        }
        if (stripos($os, 'win') !== false) {
            return $this->getWindowsSystemInfo();
        }
        return $this->getFallbackSystemInfo();
    }

    private function getUnixSystemInfo(): array
    {
        $totalRam     = $this->getNumericMemory('total');
        $usedRam      = $this->getNumericMemory('used');
        $freeRam      = $this->getNumericMemory('free');
        $buffCache    = $this->getNumericMemory('buff_cache');
        $availableRam = $this->getNumericMemory('available');
        $swapTotal    = $this->getNumericMemory('swap_total');
        $swapFree     = $this->getNumericMemory('swap_free');
        $swapUsed     = $this->getNumericMemory('swap_used');

        $total            = $totalRam ?: 15;
        $available        = $availableRam ?: ($total - $usedRam);
        $freePercent      = $total > 0 ? round(($freeRam / $total) * 100)      : 0;
        $usedPercent      = $total > 0 ? round(($usedRam / $total) * 100)      : 0;
        $availablePercent = $total > 0 ? round(($available / $total) * 100)    : 0;
        $swapFreePercent  = $swapTotal > 0 ? round(($swapFree / $swapTotal) * 100) : 0;
        $swapUsedPercent  = $swapTotal > 0 ? round(($swapUsed / $swapTotal) * 100) : 0;

        return [
            'os'               => PHP_OS,
            'hostname'         => gethostname(),
            'uptime'           => $this->getUptime(),
            'load_average'     => $this->getLoadAverage(),

            'total_ram'        => $this->formatBytes($total * 1024 * 1024),
            'total_ram_gb'     => $total,
            'used_ram'         => $this->formatBytes($usedRam * 1024 * 1024),
            'used_ram_gb'      => $usedRam,
            'free_ram'         => $this->formatBytes($freeRam * 1024 * 1024),
            'free_ram_gb'      => $freeRam,
            'free_percent'     => $freePercent,
            'used_percent'     => $usedPercent,
            'buff_cache'       => $this->formatBytes($buffCache * 1024 * 1024),
            'buff_cache_gb'    => $buffCache,
            'available_ram'    => $this->formatBytes($available * 1024 * 1024),
            'available_ram_gb' => $available,
            'available_percent'=> $availablePercent,

            'swap_total'       => $swapTotal > 0 ? $this->formatBytes($swapTotal * 1024 * 1024) : 'N/A',
            'swap_used'        => $swapUsed  > 0 ? $this->formatBytes($swapUsed  * 1024 * 1024) : 'N/A',
            'swap_free'        => $swapFree  > 0 ? $this->formatBytes($swapFree  * 1024 * 1024) : 'N/A',
            'swap_free_percent'=> $swapFreePercent,
            'swap_used_percent'=> $swapUsedPercent,

            'is_memory_issue'  => $availablePercent < 20,
            'is_swap_issue'    => $swapFreePercent  < 20,
            'is_critical'      => $availablePercent < 10,
        ];
    }

    private function getNumericMemory(string $key): float
    {
        $freeOutput = shell_exec('free -m 2>/dev/null');
        if (!$freeOutput) return 0.0;

        foreach (explode("\n", $freeOutput) as $line) {
            if (strpos($line, 'Mem:') !== false) {
                $parts  = preg_split('/\s+/', $line);
                $memMap = [
                    'total'      => $parts[1] ?? 0,
                    'used'       => $parts[2] ?? 0,
                    'free'       => $parts[3] ?? 0,
                    'shared'     => $parts[4] ?? 0,
                    'buff_cache' => $parts[5] ?? 0,
                    'available'  => $parts[6] ?? 0,
                ];
                if (isset($memMap[$key])) return (float)$memMap[$key];
            }
            if (strpos($line, 'Swap:') !== false) {
                $parts   = preg_split('/\s+/', $line);
                $swapMap = [
                    'swap_total' => $parts[1] ?? 0,
                    'swap_used'  => $parts[2] ?? 0,
                    'swap_free'  => $parts[3] ?? 0,
                ];
                if (isset($swapMap[$key])) return (float)$swapMap[$key];
            }
        }
        return 0.0;
    }

    private function getUptime(): string
    {
        if (stripos(PHP_OS, 'linux') !== false) {
            $uptime = shell_exec('cat /proc/uptime 2>/dev/null');
            if ($uptime) {
                return $this->formatUptime((int)explode(' ', $uptime)[0]);
            }
        }
        return 'N/A';
    }

    private function formatUptime(int $seconds): string
    {
        $days    = floor($seconds / 86400);
        $hours   = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $parts   = [];
        if ($days)    $parts[] = $days    . 'd';
        if ($hours)   $parts[] = $hours   . 'h';
        if ($minutes) $parts[] = $minutes . 'm';
        return implode(' ', $parts) ?: '0m';
    }

    private function getLoadAverage(): array
    {
        if (stripos(PHP_OS, 'linux') !== false) {
            $load = shell_exec('cat /proc/loadavg 2>/dev/null');
            if ($load) {
                $parts = explode(' ', $load);
                return ['1min' => $parts[0] ?? '0', '5min' => $parts[1] ?? '0', '15min' => $parts[2] ?? '0'];
            }
        }
        return ['1min' => 'N/A', '5min' => 'N/A', '15min' => 'N/A'];
    }

    private function getCombinedMemoryInfo(array $info): array
    {
        $totalRamGb = $info['total_ram_gb'] ?? 0;
        $usedRamGb  = $info['used_ram_gb']  ?? 0;
        $freeRamGb  = $info['free_ram_gb']  ?? 0;

        $swapTotal = $info['swap_total'] !== 'N/A' ? $this->getNumericMemory('swap_total') : 0;
        $swapUsed  = $info['swap_used']  !== 'N/A' ? $this->getNumericMemory('swap_used')  : 0;
        $swapFree  = $info['swap_free']  !== 'N/A' ? $this->getNumericMemory('swap_free')  : 0;

        $totalCombined = $totalRamGb + ($swapTotal / 1024);
        $usedCombined  = $usedRamGb  + ($swapUsed  / 1024);
        $freeCombined  = $freeRamGb  + ($swapFree  / 1024);

        return [
            'total_gb'     => $totalCombined,
            'used_gb'      => $usedCombined,
            'free_gb'      => $freeCombined,
            'used_percent' => $totalCombined > 0 ? round(($usedCombined / $totalCombined) * 100) : 0,
            'free_percent' => $totalCombined > 0 ? round(($freeCombined / $totalCombined) * 100) : 0,
        ];
    }

    private function getHealthStatus(array $info, string $filter = 'both'): array
    {
        $status = [];

        $status['ram_free'] = $info['free_percent'] >= 50
            ? ['status' => '✅ Good',     'class' => 'success']
            : ($info['free_percent'] >= 20
                ? ['status' => '⚠️ Warning', 'class' => 'warning']
                : ['status' => '❌ Critical', 'class' => 'danger']);

        $status['ram_available'] = $info['available_percent'] >= 40
            ? ['status' => '✅ Excellent', 'class' => 'success']
            : ($info['available_percent'] >= 20
                ? ['status' => '⚠️ Warning', 'class' => 'warning']
                : ['status' => '❌ Critical', 'class' => 'danger']);

        $status['swap_free'] = ($info['swap_total'] === 'N/A' || $info['swap_free_percent'] >= 70)
            ? ['status' => '✅ Excellent', 'class' => 'success']
            : ($info['swap_free_percent'] >= 40
                ? ['status' => '⚠️ Warning', 'class' => 'warning']
                : ['status' => '❌ Critical', 'class' => 'danger']);

        $critical = $warning = false;
        if ($filter !== 'swap') {
            if ($info['available_percent'] < 10) $critical = true;
            elseif ($info['available_percent'] < 20) $warning = true;
        }
        if ($filter !== 'ram') {
            if ($info['swap_free_percent'] < 10) $critical = true;
            elseif ($info['swap_free_percent'] < 25) $warning = true;
        }

        $status['overall'] = $critical
            ? ['status' => '🔴 Critical', 'class' => 'danger']
            : ($warning
                ? ['status' => '🟡 Warning', 'class' => 'warning']
                : ['status' => '🟢 Healthy', 'class' => 'success']);

        $issues = [];
        if ($filter !== 'swap') {
            if (($info['available_ram_gb'] ?? 99) < 2)            $issues[] = 'Low available RAM';
            if (($info['buff_cache_gb'] ?? 0) > 2 && ($info['free_ram_gb'] ?? 99) < 5) $issues[] = 'Cache using significant RAM';
        }
        if ($filter !== 'ram') {
            if ($info['swap_free_percent'] < 25)  $issues[] = 'Low swap space';
            if ($info['swap_used_percent']  > 50)  $issues[] = 'High swap usage';
        }
        $status['real_issue'] = !empty($issues) ? implode('; ', $issues) : 'No significant issues detected';

        return $status;
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  SAFETY GUARDS
    // ──────────────────────────────────────────────────────────────────────────

    private function isCriticalProcess(array $process): bool
    {
        $cmd  = strtolower($process['command'] ?? '');
        $user = strtolower($process['user']    ?? '');
        $pid  = (int)($process['pid']          ?? 0);

        if ($pid <= 2) return true;

        if (in_array($user, ['root','daemon','messagebus','systemd-network','systemd-resolve','polkitd','mysql','redis','postgres'], true)) {
            return true;
        }

        $criticalPatterns = [
            'systemd','init','kthreadd','kworker','migration','watchdog','rcu_',
            'dbus','networkmanager','nm-applet','xorg','xwayland','wayland',
            'gnome-shell','plasmashell','kwin','gdm','lightdm','sddm','login',
            'sshd','cron','rsyslog','udevd','snapd','containerd','dockerd',
            'php-fpm','nginx','apache2','httpd','mysqld','postgres','redis-server',
        ];

        foreach ($criticalPatterns as $p) {
            if (strpos($cmd, $p) !== false) return true;
        }

        return false;
    }

    private function isRestartSensitiveProcess(array $process): bool
    {
        if ($this->isCriticalProcess($process)) return true;

        $cmd  = strtolower($process['command'] ?? '');
        $user = strtolower($process['user']    ?? '');
        $pid  = (int)($process['pid']          ?? 0);

        if ($pid <= 2) return true;

        $sensitivePatterns = [
            'systemd','systemd --','user@','dbus-daemon','dbus-launch','polkitd',
            'gdm','lightdm','sddm','login','gnome-shell','plasmashell','kwin',
            'xorg','xwayland','wayland','networkmanager','wpa_supplicant','avahi-daemon',
            'containerd','dockerd','sshd','nginx','apache2','httpd','php-fpm',
            'mysqld','postgres','redis-server',
        ];

        foreach ($sensitivePatterns as $p) {
            if ($p !== '' && strpos($cmd, $p) !== false) return true;
        }

        if (in_array($user, ['root','daemon','messagebus','polkitd'], true)) return true;

        return false;
    }

    private function getProcessSnapshot(int $pid): array
    {
        return [
            'pid'     => $pid,
            'user'    => trim(shell_exec("ps -p $pid -o user= 2>/dev/null")) ?: '',
            'command' => trim(shell_exec("ps -p $pid -o cmd= 2>/dev/null"))  ?: '',
        ];
    }

    private function shouldAllowKillPid(int $pid, array $snapshot = null): array
    {
        $snapshot = $snapshot ?? $this->getProcessSnapshot($pid);
        if ($this->isRestartSensitiveProcess($snapshot)) {
            return [false, 'skip: restart-sensitive/system-managed process'];
        }
        return [true, 'ok'];
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  MISC HELPERS
    // ──────────────────────────────────────────────────────────────────────────

    private function getCpuCoreCount(): int
    {
        static $cores = null;
        if ($cores !== null) return $cores;

        $nproc = trim((string)shell_exec('nproc 2>/dev/null'));
        if ($nproc !== '' && (int)$nproc > 0) return $cores = (int)$nproc;

        $cpuinfo = trim((string)shell_exec('grep -c ^processor /proc/cpuinfo 2>/dev/null'));
        if ($cpuinfo !== '' && (int)$cpuinfo > 0) return $cores = (int)$cpuinfo;

        return $cores = 1;
    }

    private function normalizeCpuPercent(float $raw): float
    {
        return round($raw / $this->getCpuCoreCount(), 1);
    }

    private function formatBytes(float $bytes, int $precision = 2): string
    {
        if ($bytes === 0.0) return '0 B';
        $units = ['B','KB','MB','GB','TB'];
        $i     = floor(log($bytes) / log(1024));
        return round($bytes / pow(1024, $i), $precision) . ' ' . $units[$i];
    }

    private function getAppNameFromCommand(string $command): string
    {
        $cmd    = strtolower($command);
        $appMap = [
            'code'                 => 'VS Code',
            'vscode'               => 'VS Code',
            'visual-studio-code'   => 'VS Code',
            'electron'             => 'VS Code',
            'code-insiders'        => 'VS Code',
            'slack'                => 'Slack',
            'chrome'               => 'Chrome',
            'chromium'             => 'Chrome',
            'google-chrome'        => 'Chrome',
            'chrome-stable'        => 'Chrome',
            'firefox'              => 'Firefox',
            'docker'               => 'Docker',
            'containerd'           => 'Docker',
            'node'                 => 'Node.js',
            'npm'                  => 'Node.js',
            'python'               => 'Python',
            'python3'              => 'Python',
            'java'                 => 'Java',
            'php'                  => 'PHP',
            'php-fpm'              => 'PHP',
            'nginx'                => 'Nginx',
            'apache'               => 'Apache',
            'httpd'                => 'Apache',
            'mysql'                => 'MySQL',
            'mysqld'               => 'MySQL',
            'postgres'             => 'PostgreSQL',
            'redis'                => 'Redis',
            'elasticsearch'        => 'Elasticsearch',
            'gnome-shell'          => 'GNOME Shell',
            'spotify'              => 'Spotify',
            'vlc'                  => 'VLC',
            'zoom'                 => 'Zoom',
            'teams'                => 'Teams',
            'discord'              => 'Discord',
        ];

        foreach ($appMap as $key => $value) {
            if (strpos($cmd, $key) !== false) return $value;
        }

        $parts    = explode('/', $command);
        $lastPart = end($parts);
        if ($lastPart && strpos($lastPart, ' ') === false) {
            return substr($lastPart, 0, 20);
        }

        return 'Other';
    }

    private function getAppPatterns(string $appName): array
    {
        $patterns = [
            'Slack'        => ['slack', '/usr/bin/slack', 'slack --', 'slack-desktop'],
            'VS Code'      => ['code', 'vscode', 'visual-studio-code', 'code-insiders', 'electron', '/usr/share/code'],
            'Chrome'       => ['chrome', 'chromium', 'google-chrome', 'chrome-stable', '/opt/google/chrome'],
            'Firefox'      => ['firefox', 'firefox-bin', '/usr/lib/firefox'],
            'Docker'       => ['docker', 'containerd', 'dockerd', 'docker-desktop'],
            'Node'         => ['node', 'npm', 'yarn', 'nodemon', 'nodejs'],
            'Python'       => ['python', 'python3', 'pip', 'pip3', 'python3.'],
            'Java'         => ['java', 'javaw', 'jdk', 'java -'],
            'PHP'          => ['php', 'php-fpm', 'composer', 'php7', 'php8'],
            'MySQL'        => ['mysql', 'mysqld', 'mariadb', 'mariadbd'],
            'PostgreSQL'   => ['postgres', 'postgresql', 'postmaster'],
            'Redis'        => ['redis-server', 'redis-cli', 'redis-sentinel'],
            'Nginx'        => ['nginx', 'nginx:'],
            'Apache'       => ['apache', 'httpd', 'apache2'],
            'Elasticsearch'=> ['elasticsearch', 'elastic'],
            'Kibana'       => ['kibana', 'kibana-'],
            'Grafana'      => ['grafana', 'grafana-'],
            'Prometheus'   => ['prometheus', 'prometheus-'],
            'Zoom'         => ['zoom', 'zoom.us', 'Zoom'],
            'Teams'        => ['teams', 'msteams', 'teams-for-linux'],
            'Discord'      => ['discord', 'Discord', 'discord-'],
            'Spotify'      => ['spotify', 'Spotify'],
            'VLC'          => ['vlc', 'VLC'],
            'GIMP'         => ['gimp', 'GIMP'],
            'LibreOffice'  => ['soffice', 'libreoffice', 'openoffice'],
            'Thunderbird'  => ['thunderbird', 'thunderbird-bin'],
            'GNOME Shell'  => ['gnome-shell', 'gnome-shell-'],
            'Terminal'     => ['gnome-terminal', 'konsole', 'kitty', 'alacritty', 'tilix', 'xterm', 'terminal'],
            'File Manager' => ['nautilus', 'dolphin', 'thunar', 'nemo', 'caja'],
            'System'       => ['systemd', 'init', 'kernel', 'kthreadd'],
        ];

        return $patterns[$appName] ?? [$appName, strtolower($appName)];
    }

    private function getWindowsSystemInfo(): array
    {
        return [
            'os' => 'Windows', 'hostname' => gethostname(),
            'total_ram' => 'N/A', 'used_ram' => 'N/A', 'free_ram' => 'N/A', 'available_ram' => 'N/A',
            'free_percent' => 0, 'used_percent' => 0, 'available_percent' => 0,
            'uptime' => 'N/A', 'load_average' => ['1min' => 'N/A', '5min' => 'N/A', '15min' => 'N/A'],
            'is_memory_issue' => false, 'is_swap_issue' => false, 'is_critical' => false,
            'swap_total' => 'N/A', 'swap_used' => 'N/A', 'swap_free' => 'N/A',
            'swap_free_percent' => 0, 'swap_used_percent' => 0,
            'buff_cache' => 'N/A', 'buff_cache_gb' => 0,
            'total_ram_gb' => 0,
        ];
    }

    private function getFallbackSystemInfo(): array
    {
        return array_merge($this->getWindowsSystemInfo(), ['os' => PHP_OS]);
    }
}