<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Command;
use App\Services\SshConfigParser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

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
}