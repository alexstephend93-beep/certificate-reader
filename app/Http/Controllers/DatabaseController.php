<?php

namespace App\Http\Controllers;

use App\Models\DatabaseCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use PDO;

class DatabaseController extends Controller
{
    public function index()
    {
        $databases = DatabaseCredential::orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();
        
        return view('database.index', compact('databases'));
    }
    
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:database_credentials,name',
                'connection_name' => 'required|in:mysql,pgsql,sqlite',
                'host' => 'required_if:connection_name,mysql,pgsql|string|max:255',
                'port' => 'nullable|integer',
                'database' => 'required|string|max:255',
                'username' => 'required_unless:connection_name,sqlite|string|max:255',
                'password' => 'required|string', // password required for new connection
                'notes' => 'nullable|string',
                'is_default' => 'boolean',
                'phpmyadmin_url' => 'nullable|string|max:255'
            ]);
            
            // Set default port if not provided
            if (empty($validated['port'])) {
                $validated['port'] = $validated['connection_name'] === 'mysql' ? 3306 : 5432;
            }
            
            // Test connection BEFORE creating record (15-second timeout)
            $testCredential = new DatabaseCredential([
                'connection_name' => $validated['connection_name'],
                'host' => $validated['host'],
                'port' => $validated['port'],
                'database' => $validated['database'],
                'username' => $validated['username'],
                'password' => $validated['password'],
            ]);
            
            $isActive = $this->testDatabaseConnection($testCredential, 15);
            
            if (!$isActive) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot connect to database. Please check credentials (host, port, database, username, password) and ensure the database server is reachable.'
                ], 422);
            }
            
            $validated['is_active'] = true;
            
            // If this is set as default, remove default from others
            if (!empty($validated['is_default'])) {
                DatabaseCredential::where('is_default', true)->update(['is_default' => false]);
            } else {
                $validated['is_default'] = false;
            }
            
            // Handle phpMyAdmin URL logic
            if (!empty($validated['username']) && 
                (strtoupper($validated['username']) === 'PAYMENTS_ADMIN' || 
                 strtoupper($validated['username']) === 'PAYTEST_ADMIN')) {
                $validated['phpmyadmin_url'] = 'https://admin.paytest.in/phpmyadmin';
            } elseif (!empty($validated['host']) && $validated['host'] === '127.0.0.1') {
                $validated['phpmyadmin_url'] = null;
            }
            
            $database = DatabaseCredential::create($validated);
            
            return response()->json([
                'success' => true,
                'message' => 'Database connection created and tested successfully',
                'connection' => $database
            ]);
        } catch (Exception $e) {
            Log::error('Error creating database connection: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create database connection: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function update(Request $request, $id)
    {
        try {
            $database = DatabaseCredential::findOrFail($id);
            
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:database_credentials,name,' . $id,
                'connection_name' => 'required|in:mysql,pgsql,sqlite',
                'host' => 'required_if:connection_name,mysql,pgsql|string|max:255',
                'port' => 'nullable|integer',
                'database' => 'required|string|max:255',
                'username' => 'required_unless:connection_name,sqlite|string|max:255',
                'password' => 'nullable|string',
                'notes' => 'nullable|string',
                'is_default' => 'boolean',
                'phpmyadmin_url' => 'nullable|string|max:255'
            ]);
            
            // Set default port if not provided
            if (empty($validated['port'])) {
                $validated['port'] = $validated['connection_name'] === 'mysql' ? 3306 : 5432;
            }
            
            // Remove password from validated if it's empty (don't update)
            if (empty($validated['password'])) {
                unset($validated['password']);
            }
            
            // If this is set as default, remove default from others
            if (!empty($validated['is_default'])) {
                DatabaseCredential::where('is_default', true)
                    ->where('id', '!=', $id)
                    ->update(['is_default' => false]);
            } else {
                $validated['is_default'] = false;
            }
            
            // Handle phpMyAdmin URL logic
            if (!empty($validated['username']) && 
                (strtoupper($validated['username']) === 'PAYMENTS_ADMIN' || 
                 strtoupper($validated['username']) === 'PAYTEST_ADMIN')) {
                $validated['phpmyadmin_url'] = 'https://admin.paytest.in/phpmyadmin';
            } elseif (!empty($validated['host']) && $validated['host'] === '127.0.0.1') {
                // For localhost, we'll need to get the alias from the SSH server later
                // For now, we'll leave it null and it can be updated later
                $validated['phpmyadmin_url'] = null;
            }
            
            $database->update($validated);
            
            return response()->json([
                'success' => true,
                'message' => 'Database connection updated successfully',
                'connection' => $database
            ]);
        } catch (Exception $e) {
            Log::error('Error updating database connection: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update database connection: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function destroy($id)
    {
        try {
            $database = DatabaseCredential::findOrFail($id);
            
            // Prevent deletion if it's the only connection
            if (DatabaseCredential::count() === 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete the only database connection'
                ], 400);
            }
            
            // If deleting default, set another as default
            if ($database->is_default) {
                $anotherConnection = DatabaseCredential::where('id', '!=', $id)->first();
                if ($anotherConnection) {
                    $anotherConnection->is_default = true;
                    $anotherConnection->save();
                }
            }
            
            $database->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Database connection deleted successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete database connection: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function getConnection($id)
    {
        try {
            $connection = DatabaseCredential::findOrFail($id);
            
            // Return connection data without exposing decrypted password
            $connectionData = $connection->toArray();
            $connectionData['has_password'] = !empty($connection->password);
            
            return response()->json([
                'success' => true,
                'connection' => $connectionData
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection not found'
            ], 404);
        }
    }
    
    public function setDefault($id)
    {
        try {
            // Remove default from all connections
            DatabaseCredential::where('is_default', true)->update(['is_default' => false]);
            
            // Set new default
            $connection = DatabaseCredential::findOrFail($id);
            $connection->is_default = true;
            $connection->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Default connection set successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to set default connection'
            ], 500);
        }
    }
    
    
    public function testConnectionDynamic(Request $request)
    {
        $request->validate([
            'host' => 'required_if:connection_name,mysql,pgsql|string',
            'port' => 'nullable|string',
            'database' => 'required|string',
            'username' => 'required_unless:connection_name,sqlite|string',
            'connection_name' => 'required|in:mysql,pgsql,sqlite',
            'password' => 'nullable|string'
        ]);
        
        $startTime = microtime(true);
        
        try {
            // Create a temporary connection object for testing
            $tempConnection = new DatabaseCredential();
            $tempConnection->connection_name = $request->connection_name;
            $tempConnection->host = $request->host;
            $tempConnection->port = $request->port ?? ($request->connection_name === 'mysql' ? '3306' : '5432');
            $tempConnection->database = $request->database;
            $tempConnection->username = $request->username;
            $tempConnection->password = $request->password;
            
            \Log::info('Testing dynamic connection', [
                'type' => $tempConnection->connection_name,
                'host' => $tempConnection->host,
                'port' => $tempConnection->port,
                'database' => $tempConnection->database,
                'user' => $tempConnection->username
            ]);
            
            $result = $this->performConnectionTest($tempConnection);
            
            if ($result['success']) {
                $endTime = microtime(true);
                $responseTime = round(($endTime - $startTime) * 1000);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Connection successful',
                    'response_time_ms' => $responseTime,
                    'details' => $result['details'] ?? null
                ]);
            } else {
                throw new Exception($result['error']);
            }
            
        } catch (\Exception $e) {
            \Log::error('Dynamic connection test failed', [
                'error' => $e->getMessage(),
                'type' => $request->connection_name,
                'host' => $request->host
            ]);
            
            $endTime = microtime(true);
            $responseTime = round(($endTime - $startTime) * 1000);
            
            $friendlyMessage = $this->getUserFriendlyError(
                $e->getMessage(),
                $result['error_type'] ?? null
            );
            
            return response()->json([
                'success' => false,
                'message' => $friendlyMessage,
                'response_time_ms' => $responseTime
            ]);
        }
    }


    private function getUserFriendlyError($error, $errorType = null)
    {
        if ($errorType === 'Connection timeout / Host unreachable') {
            return 'Cannot connect to database server. Please check host, port, and network connectivity.';
        }
        
        if ($errorType === 'Authentication failed') {
            return 'Access denied. Please check your username and password.';
        }
        
        if ($errorType === 'Unknown database') {
            return 'Database not found. Please check the database name.';
        }
        
        if ($errorType === 'MySQL server has gone away') {
            return 'Connection lost. Please check server stability and network.';
        }
        
        if ($errorType === 'Connection refused / Host unreachable') {
            return 'Cannot reach PostgreSQL server. Please check host, port, and firewall settings.';
        }
        
        // Fallback to generic message
        $error = strtolower($error);
        
        if (str_contains($error, '1045') || str_contains($error, 'access denied')) {
            return 'Access denied. Please check your username and password.';
        }
        
        if (str_contains($error, '1049') || str_contains($error, 'unknown database')) {
            return 'Database not found. Please check the database name.';
        }
        
        if (str_contains($error, '2002') || str_contains($error, 'connection refused')) {
            return 'Cannot connect to database server. Please check if the server is running and the host/port are correct.';
        }
        
        if (str_contains($error, 'timeout')) {
            return 'Connection timeout. The server is taking too long to respond.';
        }
        
        return $error;
    }


    private function testMysqlConnection($connection)
    {
        $host = $connection->host;
        $port = $connection->port ?: 3306;
        $database = $connection->database;
        $username = $connection->username;
        $password = $connection->decrypted_password;

        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";

            $pdo = new \PDO($dsn, $username, $password, [
                \PDO::ATTR_TIMEOUT => 10,
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);

            // ✅ Use single-line query (avoids hidden formatting issues)
            $query = "SELECT 1 AS test, NOW() AS `current_time`, DATABASE() AS db_name";

            $stmt = $pdo->query($query);
            $row = $stmt->fetch();

            // Close connection
            $pdo = null;

            return [
                'success' => true,
                'error' => null,
                'details' => [
                    'host' => $host,
                    'port' => $port,
                    'database' => $row['db_name'] ?? $database,
                    'server_time' => $row['current_time'] ?? null,
                ]
            ];

        } catch (\PDOException $e) {

            $message = $e->getMessage();

            // 🔍 Error classification (useful for UI/logs)
            if (str_contains($message, '2002')) {
                $type = 'Connection timeout / Host unreachable';
            } elseif (str_contains($message, '1045')) {
                $type = 'Authentication failed';
            } elseif (str_contains($message, '1049')) {
                $type = 'Unknown database';
            } elseif (str_contains($message, '2006')) {
                $type = 'MySQL server has gone away';
            } else {
                $type = 'Database error';
            }

            return [
                'success' => false,
                'error' => $message,
                'error_type' => $type,
                'details' => [
                    'host' => $host,
                    'port' => $port,
                    'database' => $database,
                ]
            ];

        } catch (\Exception $e) {

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'error_type' => 'General error'
            ];
        }
    }


    private function testSqliteConnection($connection)
    {
        $database = $connection->database;
        
        if (!file_exists($database)) {
            return [
                'success' => false,
                'error' => "SQLite database file not found: {$database}",
                'error_type' => 'File not found'
            ];
        }
        
        if (!is_readable($database)) {
            return [
                'success' => false,
                'error' => "SQLite database file is not readable: {$database}",
                'error_type' => 'Permission denied'
            ];
        }
        
        try {
            $pdo = new \PDO("sqlite:{$database}");
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            
            $query = "SELECT 1 AS test, datetime('now') AS current_time";
            $stmt = $pdo->query($query);
            $row = $stmt->fetch();
            
            $pdo = null;
            
            return [
                'success' => true,
                'error' => null,
                'details' => [
                    'database' => $database,
                    'database_size' => round(filesize($database) / 1024, 2) . ' KB',
                    'server_time' => $row['current_time'] ?? null
                ]
            ];
            
        } catch (\PDOException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'error_type' => 'PDO Exception'
            ];
        }
    }



    private function performConnectionTest($connection)
    {
        // Handle SQLite
        if ($connection->connection_name === 'sqlite') {
            return $this->testSqliteConnection($connection);
        }
        
        // Handle MySQL
        if ($connection->connection_name === 'mysql') {
            return $this->testMysqlConnection($connection);
        }
        
        // Handle PostgreSQL
        if ($connection->connection_name === 'pgsql') {
            return $this->testPgsqlConnection($connection);
        }
        
        return [
            'success' => false,
            'error' => 'Unsupported database type: ' . $connection->connection_name,
            'error_type' => 'Configuration error'
        ];
    }



    private function testPgsqlConnection($connection)
    {
        $host = $connection->host;
        $port = $connection->port ?: 5432;
        $database = $connection->database;
        $username = $connection->username;
        $password = $connection->decrypted_password;
        
        try {
            $dsn = "pgsql:host={$host};port={$port};dbname={$database}";
            
            $pdo = new \PDO($dsn, $username, $password, [
                \PDO::ATTR_TIMEOUT => 10,
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);
            
            $query = "SELECT 1 AS test, NOW() AS current_time, current_database() AS db_name";
            $stmt = $pdo->query($query);
            $row = $stmt->fetch();
            
            $pdo = null;
            
            return [
                'success' => true,
                'error' => null,
                'details' => [
                    'host' => $host,
                    'port' => $port,
                    'database' => $row['db_name'] ?? $database,
                    'server_time' => $row['current_time'] ?? null
                ]
            ];
            
        } catch (\PDOException $e) {
            $message = $e->getMessage();
            
            if (str_contains($message, '7')) {
                $type = 'Connection refused / Host unreachable';
            } elseif (str_contains($message, '28')) {
                $type = 'Authentication failed';
            } elseif (str_contains($message, '3')) {
                $type = 'Unknown database';
            } else {
                $type = 'Database error';
            }
            
            return [
                'success' => false,
                'error' => $message,
                'error_type' => $type,
                'details' => [
                    'host' => $host,
                    'port' => $port,
                    'database' => $database,
                ]
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'error_type' => 'General error'
            ];
        }
    }


    public function testConnection(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:database_credentials,id'
        ]);
        
        $startTime = microtime(true);
        
        try {
            $connection = DatabaseCredential::findOrFail($request->id);
            
            \Log::info('Testing database connection', [
                'name' => $connection->name,
                'type' => $connection->connection_name,
                'host' => $connection->host,
                'database' => $connection->database,
                'user' => $connection->username
            ]);
            
            $result = $this->performConnectionTest($connection);
            
            if ($result['success']) {
                $connection->is_active = true;
                $connection->save();
                
                $endTime = microtime(true);
                $responseTime = round(($endTime - $startTime) * 1000);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Connection successful',
                    'response_time_ms' => $responseTime,
                    'details' => $result['details'] ?? null
                ]);
            } else {
                throw new Exception($result['error']);
            }
            
        } catch (\Exception $e) {
            if (isset($connection)) {
                $connection->is_active = false;
                $connection->save();
            }
            
            \Log::error('Connection test failed', [
                'error' => $e->getMessage(),
                'connection' => $connection->name ?? 'unknown'
            ]);
            
            $endTime = microtime(true);
            $responseTime = round(($endTime - $startTime) * 1000);
            
            $friendlyMessage = $this->getUserFriendlyError(
                $e->getMessage(),
                $result['error_type'] ?? null
            );
            
            return response()->json([
                'success' => false,
                'message' => $friendlyMessage,
                'response_time_ms' => $responseTime
            ]);
        }
    }


    
    private function getDbConnection($connection)
    {
        if ($connection->connection_name === 'sqlite') {
            if (!file_exists($connection->database)) {
                throw new Exception('SQLite database file not found: ' . $connection->database);
            }
            
            $pdo = new \PDO('sqlite:' . $connection->database);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            return new \Illuminate\Database\Connection($pdo);
        } else {
            $config = [
                'driver' => $connection->connection_name,
                'host' => $connection->host,
                'port' => $connection->port,
                'database' => $connection->database,
                'username' => $connection->username,
                'password' => $connection->decrypted_password,
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
                'engine' => null,
            ];
            
            DB::purge('dynamic');
            config(['database.connections.dynamic' => $config]);
            
            return DB::connection('dynamic');
        }
    }
    
    public function getTables($id)
    {
        try {
            $connection = DatabaseCredential::findOrFail($id);
            $db = $this->getDbConnection($connection);
            
            $tables = [];
            
            if ($connection->connection_name === 'mysql') {
                $results = $db->select('SHOW TABLE STATUS');
                foreach ($results as $table) {
                    $tables[] = [
                        'name' => $table->Name,
                        'rows' => $table->Rows ?? 0,
                        'size' => $table->Data_length ?? 0,
                        'engine' => $table->Engine ?? 'N/A',
                        'collation' => $table->Collation ?? 'N/A'
                    ];
                }
            } elseif ($connection->connection_name === 'pgsql') {
                $results = $db->select("
                    SELECT 
                        tablename as name,
                        (SELECT COUNT(*) FROM " . $db->getTablePrefix() . "pg_stat_user_tables WHERE relname = tablename) as rows
                    FROM pg_tables 
                    WHERE schemaname = 'public'
                ");
                foreach ($results as $table) {
                    $tables[] = [
                        'name' => $table->name,
                        'rows' => $table->rows ?? 0,
                        'size' => 0,
                        'engine' => 'PostgreSQL',
                        'collation' => 'N/A'
                    ];
                }
            } elseif ($connection->connection_name === 'sqlite') {
                $results = $db->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
                foreach ($results as $table) {
                    try {
                        $rowCount = $db->select("SELECT COUNT(*) as count FROM " . $table->name);
                        $tables[] = [
                            'name' => $table->name,
                            'rows' => $rowCount[0]->count ?? 0,
                            'size' => 0,
                            'engine' => 'SQLite',
                            'collation' => 'N/A'
                        ];
                    } catch (Exception $e) {
                        $tables[] = [
                            'name' => $table->name,
                            'rows' => 0,
                            'size' => 0,
                            'engine' => 'SQLite',
                            'collation' => 'N/A'
                        ];
                    }
                }
            }
            
            // Sort tables by name
            usort($tables, function($a, $b) {
                return strcmp($a['name'], $b['name']);
            });
            
            return response()->json([
                'success' => true,
                'tables' => $tables
            ]);
        } catch (Exception $e) {
            Log::error('Failed to fetch tables: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch tables: ' . $e->getMessage()
            ]);
        }
    }
    
    public function getTableStructure($id, $table)
    {
        try {
            $connection = DatabaseCredential::findOrFail($id);
            $db = $this->getDbConnection($connection);
            
            $columns = [];
            
            if ($connection->connection_name === 'mysql') {
                $results = $db->select("DESCRIBE `{$table}`");
                $columns = $results;
            } elseif ($connection->connection_name === 'pgsql') {
                $results = $db->select("
                    SELECT 
                        column_name as Field,
                        data_type as Type,
                        is_nullable as 'Null',
                        column_default as 'Default',
                        'NO' as 'Key'
                    FROM information_schema.columns 
                    WHERE table_name = '{$table}'
                    ORDER BY ordinal_position
                ");
                $columns = $results;
            } elseif ($connection->connection_name === 'sqlite') {
                $results = $db->select("PRAGMA table_info({$table})");
                $columns = array_map(function($col) {
                    return (object)[
                        'Field' => $col->name,
                        'Type' => $col->type,
                        'Null' => $col->notnull ? 'NO' : 'YES',
                        'Key' => $col->pk ? 'PRI' : '',
                        'Default' => $col->dflt_value
                    ];
                }, $results);
            }
            
            // Get indexes for MySQL
            $indexes = [];
            if ($connection->connection_name === 'mysql') {
                $indexResults = $db->select("SHOW INDEX FROM `{$table}`");
                foreach ($indexResults as $index) {
                    if (!isset($indexes[$index->Key_name])) {
                        $indexes[$index->Key_name] = [
                            'name' => $index->Key_name,
                            'columns' => [],
                            'unique' => $index->Non_unique == 0
                        ];
                    }
                    $indexes[$index->Key_name]['columns'][] = $index->Column_name;
                }
            }
            
            return response()->json([
                'success' => true,
                'structure' => $columns,
                'indexes' => array_values($indexes),
                'table' => $table
            ]);
        } catch (Exception $e) {
            Log::error('Failed to fetch table structure: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch table structure: ' . $e->getMessage()
            ]);
        }
    }
    
    public function getTableDetails($id, $table)
    {
        try {
            $connection = DatabaseCredential::findOrFail($id);
            $db = $this->getDbConnection($connection);
            
            // Get row count
            try {
                $rowCountResult = $db->select("SELECT COUNT(*) as count FROM `{$table}`");
                $rowCount = $rowCountResult[0]->count ?? 0;
            } catch (Exception $e) {
                $rowCount = 0;
            }
            
            // Get columns
            $columns = $this->getTableColumns($db, $connection->connection_name, $table);
            
            // Get indexes
            $indexes = $this->getTableIndexes($db, $connection->connection_name, $table);
            
            // Get additional table metadata (engine, collation, etc.)
            $engine = null;
            $collation = null;
            $createTime = null;
            $comment = null;
            
            if ($connection->connection_name === 'mysql') {
                try {
                    $statusResult = $db->select("SHOW TABLE STATUS LIKE ?", [$table]);
                    if (isset($statusResult[0])) {
                        $engine = $statusResult[0]->Engine ?? null;
                        $collation = $statusResult[0]->Collation ?? null;
                        $createTime = $statusResult[0]->Create_time ?? null;
                        $comment = $statusResult[0]->Comment ?? null;
                    }
                } catch (Exception $e) {
                    \Log::warning('Failed to fetch table status: ' . $e->getMessage());
                }
            } elseif ($connection->connection_name === 'pgsql') {
                try {
                    $pgResult = $db->select("
                        SELECT 
                            table_name,
                            table_type,
                            pg_catalog.obj_description('public.' || quote_ident(table_name)::regclass, 'pg_class') as comment
                        FROM information_schema.tables 
                        WHERE table_name = ?
                    ", [$table]);
                    if (isset($pgResult[0])) {
                        $engine = $pgResult[0]->table_type ?? 'PostgreSQL';
                        $comment = $pgResult[0]->comment ?? null;
                        $collation = 'UTF8';
                    }
                } catch (Exception $e) {
                    \Log::warning('Failed to fetch PostgreSQL table info: ' . $e->getMessage());
                }
            } elseif ($connection->connection_name === 'sqlite') {
                try {
                    $sqliteResult = $db->select("SELECT sql FROM sqlite_master WHERE type='table' AND name=?", [$table]);
                    if (isset($sqliteResult[0])) {
                        $engine = 'SQLite';
                        $collation = 'N/A';
                    }
                } catch (Exception $e) {
                    \Log::warning('Failed to fetch SQLite table info: ' . $e->getMessage());
                }
            }
            
            return response()->json([
                'success' => true,
                'table_name' => $table,
                'row_count' => $rowCount,
                'column_count' => count($columns),
                'index_count' => count($indexes),
                'engine' => $engine,
                'collation' => $collation,
                'create_time' => $createTime,
                'comment' => $comment,
                'columns' => $columns,
                'indexes' => $indexes
            ]);
        } catch (Exception $e) {
            Log::error('Failed to fetch table details: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch table details: ' . $e->getMessage()
            ]);
        }
    }
    
    private function getTableColumns($db, $driver, $table)
    {
        if ($driver === 'mysql') {
            return $db->select("DESCRIBE `{$table}`");
        } elseif ($driver === 'pgsql') {
            return $db->select("
                SELECT 
                    column_name as Field,
                    data_type as Type,
                    is_nullable as 'Null',
                    column_default as 'Default'
                FROM information_schema.columns 
                WHERE table_name = '{$table}'
                ORDER BY ordinal_position
            ");
        } else {
            $results = $db->select("PRAGMA table_info({$table})");
            return array_map(function($col) {
                return (object)[
                    'Field' => $col->name,
                    'Type' => $col->type,
                    'Null' => $col->notnull ? 'NO' : 'YES',
                    'Default' => $col->dflt_value
                ];
            }, $results);
        }
    }
    
    private function getTableIndexes($db, $driver, $table)
    {
        if ($driver === 'mysql') {
            $results = $db->select("SHOW INDEX FROM `{$table}`");
            $indexes = [];
            foreach ($results as $index) {
                if (!isset($indexes[$index->Key_name])) {
                    $indexes[$index->Key_name] = (object)[
                        'Index_name' => $index->Key_name,
                        'Column_name' => $index->Column_name,
                        'Non_unique' => $index->Non_unique,
                        'Index_type' => $index->Index_type
                    ];
                }
            }
            return array_values($indexes);
        } elseif ($driver === 'pgsql') {
            return $db->select("
                SELECT 
                    indexname as Index_name,
                    indexdef
                FROM pg_indexes 
                WHERE tablename = '{$table}'
            ");
        } else {
            return $db->select("PRAGMA index_list({$table})");
        }
    }
    
    public function runQuery(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|exists:database_credentials,id',
                'query' => 'required|string'
            ]);
            
            $connection = DatabaseCredential::findOrFail($request->id);
            $db = $this->getDbConnection($connection);
            $query = trim($request->input('query'));
            
            // Log query for debugging (optional)
            Log::info('Executing query on database: ' . $connection->name, [
                'query' => substr($query, 0, 500)
            ]);
            
            // Determine if it's a SELECT query
            $isSelect = stripos($query, 'select') === 0;
            $isShow = stripos($query, 'show') === 0;
            $isDescribe = stripos($query, 'describe') === 0;
            $isExplain = stripos($query, 'explain') === 0;
            
            if ($isSelect || $isShow || $isDescribe || $isExplain) {
                $results = $db->select($query);
                return response()->json([
                    'success' => true,
                    'results' => $results,
                    'count' => count($results)
                ]);
            } else {
                // For INSERT, UPDATE, DELETE, etc.
                $affectedRows = $db->affectingStatement($query);
                
                // Return affected rows count
                return response()->json([
                    'success' => true,
                    'affected_rows' => $affectedRows,
                    'message' => 'Query executed successfully'
                ]);
            }
        } catch (Exception $e) {
            Log::error('Query execution failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
    
    public function getDbHealth($id)
    {
        try {
            $connection = DatabaseCredential::findOrFail($id);
            $db = $this->getDbConnection($connection);
            
            $health = [
                'size_mb' => 'N/A',
                'table_count' => 'N/A',
                'uptime' => 'N/A',
                'active_connections' => 'N/A'
            ];
            
            if ($connection->connection_name === 'mysql') {
                // Get database size
                try {
                    $sizeResult = $db->select("
                        SELECT 
                            ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb
                        FROM information_schema.tables 
                        WHERE table_schema = DATABASE()
                    ");
                    $health['size_mb'] = $sizeResult[0]->size_mb ?? 0;
                } catch (Exception $e) {
                    $health['size_mb'] = 'N/A';
                }
                
                // Get table count
                try {
                    $tableResult = $db->select("
                        SELECT COUNT(*) as count 
                        FROM information_schema.tables 
                        WHERE table_schema = DATABASE()
                    ");
                    $health['table_count'] = $tableResult[0]->count ?? 0;
                } catch (Exception $e) {
                    $health['table_count'] = 'N/A';
                }
                
                // Get uptime
                try {
                    $uptimeResult = $db->select("SHOW STATUS LIKE 'Uptime'");
                    $uptimeSeconds = $uptimeResult[0]->Value ?? 0;
                    $health['uptime'] = $this->formatUptime($uptimeSeconds);
                } catch (Exception $e) {
                    $health['uptime'] = 'N/A';
                }
                
                // Get active connections
                try {
                    $connResult = $db->select("SHOW STATUS LIKE 'Threads_connected'");
                    $health['active_connections'] = $connResult[0]->Value ?? 0;
                } catch (Exception $e) {
                    $health['active_connections'] = 'N/A';
                }
            } elseif ($connection->connection_name === 'pgsql') {
                try {
                    // Get table count for PostgreSQL
                    $tableResult = $db->select("
                        SELECT COUNT(*) as count 
                        FROM information_schema.tables 
                        WHERE table_schema = 'public'
                    ");
                    $health['table_count'] = $tableResult[0]->count ?? 0;
                    
                    // Get database size
                    $sizeResult = $db->select("
                        SELECT pg_database_size(current_database()) as size_bytes
                    ");
                    $health['size_mb'] = round(($sizeResult[0]->size_bytes ?? 0) / 1024 / 1024, 2);
                    
                    // Get active connections
                    $connResult = $db->select("
                        SELECT COUNT(*) as count 
                        FROM pg_stat_activity 
                        WHERE state = 'active'
                    ");
                    $health['active_connections'] = $connResult[0]->count ?? 0;
                } catch (Exception $e) {
                    // Keep defaults
                }
            } elseif ($connection->connection_name === 'sqlite') {
                try {
                    // Get table count for SQLite
                    $tableResult = $db->select("
                        SELECT COUNT(*) as count 
                        FROM sqlite_master 
                        WHERE type='table' AND name NOT LIKE 'sqlite_%'
                    ");
                    $health['table_count'] = $tableResult[0]->count ?? 0;
                    
                    // Get database file size
                    if (file_exists($connection->database)) {
                        $health['size_mb'] = round(filesize($connection->database) / 1024 / 1024, 2);
                    }
                } catch (Exception $e) {
                    // Keep defaults
                }
            }
            
            return response()->json([
                'success' => true,
                'health' => $health
            ]);
        } catch (Exception $e) {
            Log::error('Failed to get database health: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get database health: ' . $e->getMessage()
            ]);
        }
    }
    
    public function getRunningQueries($id, Request $request)
    {
        try {
            $connection = DatabaseCredential::findOrFail($id);
            $minSeconds = $request->get('min_seconds', 60);
            
            if ($connection->connection_name !== 'mysql') {
                return response()->json([
                    'success' => false,
                    'message' => 'Running queries monitoring only available for MySQL databases'
                ]);
            }
            
            $db = $this->getDbConnection($connection);
            $queries = $db->select("
                SELECT 
                    id,
                    user,
                    host,
                    db,
                    time,
                    state,
                    info as query
                FROM information_schema.processlist
                WHERE command != 'Sleep'
                    AND time > ?
                    AND db = ?
                ORDER BY time DESC
            ", [$minSeconds, $connection->database]);
            
            return response()->json([
                'success' => true,
                'queries' => $queries
            ]);
        } catch (Exception $e) {
            Log::error('Failed to get running queries: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get running queries: ' . $e->getMessage()
            ]);
        }
    }
    
    public function killQuery(Request $request)
    {
        try {
            $request->validate([
                'db_id' => 'required|exists:database_credentials,id',
                'process_id' => 'required|integer'
            ]);
            
            $connection = DatabaseCredential::findOrFail($request->db_id);
            
            if ($connection->connection_name !== 'mysql') {
                return response()->json([
                    'success' => false,
                    'message' => 'Kill query only available for MySQL databases'
                ]);
            }
            
            $db = $this->getDbConnection($connection);
            $db->statement("KILL {$request->process_id}");
            
            return response()->json([
                'success' => true,
                'message' => 'Query killed successfully'
            ]);
        } catch (Exception $e) {
            Log::error('Failed to kill query: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to kill query: ' . $e->getMessage()
            ]);
        }
    }
    
    public function killLongRunningQueries(Request $request)
    {
        try {
            $request->validate([
                'db_id' => 'required|exists:database_credentials,id',
                'min_seconds' => 'required|integer|min:1'
            ]);
            
            $connection = DatabaseCredential::findOrFail($request->db_id);
            
            if ($connection->connection_name !== 'mysql') {
                return response()->json([
                    'success' => false,
                    'message' => 'Kill queries only available for MySQL databases'
                ]);
            }
            
            $db = $this->getDbConnection($connection);
            $queries = $db->select("
                SELECT id 
                FROM information_schema.processlist
                WHERE command != 'Sleep'
                    AND time > ?
            ", [$request->min_seconds]);
            
            $killedCount = 0;
            $errors = [];
            
            foreach ($queries as $query) {
                try {
                    $db->statement("KILL {$query->id}");
                    $killedCount++;
                } catch (Exception $e) {
                    $errors[] = "Failed to kill query {$query->id}: " . $e->getMessage();
                }
            }
            
            return response()->json([
                'success' => true,
                'killed_count' => $killedCount,
                'errors' => $errors,
                'message' => "Killed {$killedCount} out of " . count($queries) . " long-running queries"
            ]);
        } catch (Exception $e) {
            Log::error('Failed to kill long-running queries: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to kill queries: ' . $e->getMessage()
            ]);
        }
    }
    
    public function getActiveConnections($id)
    {
        try {
            $connection = DatabaseCredential::findOrFail($id);
            
            if ($connection->connection_name !== 'mysql') {
                return response()->json([
                    'success' => false,
                    'message' => 'Active connections monitoring only available for MySQL databases'
                ]);
            }
            
            $db = $this->getDbConnection($connection);
            $connections = $db->select("
                SELECT 
                    id,
                    user,
                    host,
                    db as database_name,
                    command,
                    time,
                    state,
                    info as query
                FROM information_schema.processlist
                WHERE db = ?
                ORDER BY time DESC
            ", [$connection->database]);
            
            $summary = [
                'total_connections' => count($connections),
                'unique_users' => count(array_unique(array_column($connections, 'user'))),
                'sleep_connections' => count(array_filter($connections, fn($c) => $c->command === 'Sleep')),
                'active_queries' => count(array_filter($connections, fn($c) => $c->command !== 'Sleep')),
                'max_duration' => !empty($connections) ? max(array_column($connections, 'time')) : 0
            ];
            
            return response()->json([
                'success' => true,
                'connections' => $connections,
                'summary' => $summary
            ]);
        } catch (Exception $e) {
            Log::error('Failed to get active connections: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get active connections: ' . $e->getMessage()
            ]);
        }
    }
    
    public function killIdleConnections(Request $request)
    {
        try {
            $request->validate([
                'db_id' => 'required|exists:database_credentials,id'
            ]);
            
            $connection = DatabaseCredential::findOrFail($request->db_id);
            
            if ($connection->connection_name !== 'mysql') {
                return response()->json([
                    'success' => false,
                    'message' => 'Kill idle connections only available for MySQL databases'
                ]);
            }
            
            $db = $this->getDbConnection($connection);
            $idleConnections = $db->select("
                SELECT id, user, time 
                FROM information_schema.processlist
                WHERE command = 'Sleep'
                    AND user != 'system user'
            ");
            
            $killedCount = 0;
            foreach ($idleConnections as $conn) {
                try {
                    $db->statement("KILL {$conn->id}");
                    $killedCount++;
                } catch (Exception $e) {
                    // Skip if cannot kill
                    Log::warning("Could not kill idle connection {$conn->id}: " . $e->getMessage());
                }
            }
            
            return response()->json([
                'success' => true,
                'killed_count' => $killedCount,
                'message' => "Killed {$killedCount} idle connections"
            ]);
        } catch (Exception $e) {
            Log::error('Failed to kill idle connections: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to kill idle connections: ' . $e->getMessage()
            ]);
        }
    }
    
    public function killConnectionsByUser(Request $request)
    {
        try {
            $request->validate([
                'db_id' => 'required|exists:database_credentials,id',
                'username' => 'required|string'
            ]);
            
            $connection = DatabaseCredential::findOrFail($request->db_id);
            
            if ($connection->connection_name !== 'mysql') {
                return response()->json([
                    'success' => false,
                    'message' => 'Kill by user only available for MySQL databases'
                ]);
            }
            
            $db = $this->getDbConnection($connection);
            $userConnections = $db->select("
                SELECT id, db, time 
                FROM information_schema.processlist
                WHERE user = ?
            ", [$request->username]);
            
            $killedCount = 0;
            foreach ($userConnections as $conn) {
                try {
                    $db->statement("KILL {$conn->id}");
                    $killedCount++;
                } catch (Exception $e) {
                    Log::warning("Could not kill connection {$conn->id} for user {$request->username}: " . $e->getMessage());
                }
            }
            
            return response()->json([
                'success' => true,
                'killed_count' => $killedCount,
                'message' => "Killed {$killedCount} connections for user {$request->username}"
            ]);
        } catch (Exception $e) {
            Log::error('Failed to kill connections by user: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to kill connections: ' . $e->getMessage()
            ]);
        }
    }
    
    public function exportData(Request $request)
    {
        try {
            $id = $request->get('id');
            $format = $request->get('format', 'sql');

            if (!$id) {
                throw new Exception('Database ID is required');
            }

            $connection = DatabaseCredential::findOrFail($id);
            $db = $this->getDbConnection($connection);

            // Get all tables
            $tables = [];
            if ($connection->connection_name === 'mysql') {
                $results = $db->select('SHOW TABLES');
                foreach ($results as $result) {
                    $tables[] = reset($result);
                }
            } elseif ($connection->connection_name === 'pgsql') {
                $results = $db->select("SELECT tablename FROM pg_tables WHERE schemaname = 'public'");
                foreach ($results as $result) {
                    $tables[] = $result->tablename;
                }
            } else {
                $results = $db->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
                foreach ($results as $result) {
                    $tables[] = $result->name;
                }
            }

            $exportData = [];
            foreach ($tables as $table) {
                try {
                    $data = $db->select("SELECT * FROM `{$table}`");
                    $exportData[$table] = $data;
                } catch (Exception $e) {
                    Log::warning("Could not export table {$table}: " . $e->getMessage());
                    $exportData[$table] = [];
                }
            }

            // Generate output based on format
            if ($format === 'sql') {
                $content = $this->generateSQLExport($exportData);
                $filename = "database_export_" . $connection->name . "_" . date('Y-m-d_H-i-s') . ".sql";
                $mimeType = "text/plain";
            } elseif ($format === 'csv') {
                $content = $this->generateCSVExport($exportData);
                $filename = "database_export_" . $connection->name . "_" . date('Y-m-d_H-i-s') . ".csv";
                $mimeType = "text/csv";
            } else {
                $content = json_encode($exportData, JSON_PRETTY_PRINT);
                $filename = "database_export_" . $connection->name . "_" . date('Y-m-d_H-i-s') . ".json";
                $mimeType = "application/json";
            }

            return response($content)
                ->header('Content-Type', $mimeType)
                ->header('Content-Disposition', "attachment; filename=\"{$filename}\"")
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0')
                ->header('Cache-Control', 'must-revalidate, post-check=0, pre-check=0');

        } catch (Exception $e) {
            Log::error('Export failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Export failed: ' . $e->getMessage()
            ], 500); // Changed: Return proper error status code
        }
    }
    
    private function generateSQLExport($exportData)
    {
        $output = "-- Database Export\n";
        $output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $output .= "-- Format: SQL\n\n";
        
        foreach ($exportData as $table => $rows) {
            if (empty($rows)) {
                $output .= "-- Table: {$table} (empty)\n\n";
                continue;
            }
            
            $output .= "-- Table: {$table}\n";
            
            // Get column names from first row
            $firstRow = (array)$rows[0];
            $columns = array_keys($firstRow);
            $columnsList = implode(', ', array_map(fn($col) => "`{$col}`", $columns));
            
            // Generate INSERT statements in batches
            $batchSize = 100;
            for ($i = 0; $i < count($rows); $i += $batchSize) {
                $batch = array_slice($rows, $i, $batchSize);
                foreach ($batch as $row) {
                    $values = array_map(function($value) {
                        if ($value === null) return 'NULL';
                        if (is_bool($value)) return $value ? '1' : '0';
                        if (is_numeric($value)) return $value;
                        if (is_string($value)) {
                            $escaped = addslashes($value);
                            $escaped = str_replace(["\n", "\r"], ['\\n', '\\r'], $escaped);
                            return "'" . $escaped . "'";
                        }
                        if (is_object($value)) return "'" . addslashes(json_encode($value)) . "'";
                        if (is_array($value)) return "'" . addslashes(json_encode($value)) . "'";
                        return "'" . addslashes((string)$value) . "'";
                    }, (array)$row);
                    
                    $valuesList = implode(', ', $values);
                    $output .= "INSERT INTO `{$table}` ({$columnsList}) VALUES ({$valuesList});\n";
                }
                $output .= "\n";
            }
            
            $output .= "\n";
        }
        
        return $output;
    }
    
    private function generateCSVExport($exportData)
    {
        $output = fopen('php://temp', 'r+');
        
        foreach ($exportData as $table => $rows) {
            fputcsv($output, ["-- Table: {$table}"]);
            
            if (!empty($rows)) {
                // Write headers
                $headers = array_keys((array)$rows[0]);
                fputcsv($output, $headers);
                
                // Write data
                foreach ($rows as $row) {
                    $rowArray = (array)$row;
                    // Convert objects/arrays to JSON
                    array_walk($rowArray, function(&$value) {
                        if (is_object($value) || is_array($value)) {
                            $value = json_encode($value);
                        }
                    });
                    fputcsv($output, $rowArray);
                }
            }
            
            fputcsv($output, []); // Empty line between tables
        }
        
        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);
        
        return $content;
    }
    
    private function formatUptime($seconds)
    {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        
        if ($days > 0) {
            return "{$days}d {$hours}h";
        } elseif ($hours > 0) {
            return "{$hours}h {$minutes}m";
        } elseif ($minutes > 0) {
            return "{$minutes}m";
        } else {
            return "{$seconds}s";
        }
    }
    
    // Additional helper method for getting table data preview
    public function getTableData($id, $table)
    {
        try {
            $connection = DatabaseCredential::findOrFail($id);
            $db = $this->getDbConnection($connection);
            
            $data = $db->select("SELECT * FROM `{$table}` LIMIT 100");
            
            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch table data: ' . $e->getMessage()
            ]);
        }
    }

    private function testDatabaseConnection($connection)
    {
        if ($connection->connection_name === 'sqlite') {
            if (!file_exists($connection->database)) {
                throw new Exception('SQLite database file not found: ' . $connection->database);
            }
            
            // Test SQLite connection
            try {
                $pdo = new \PDO('sqlite:' . $connection->database);
                $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                $pdo->query('SELECT 1');
            } catch (\PDOException $e) {
                throw new Exception('SQLite connection failed: ' . $e->getMessage());
            }
        } else {
            $config = [
                'driver' => $connection->connection_name,
                'host' => $connection->host,
                'port' => $connection->port,
                'database' => $connection->database,
                'username' => $connection->username,
                'password' => $connection->decrypted_password,
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
                'engine' => null,
            ];
            
            DB::purge('dynamic');
            config(['database.connections.dynamic' => $config]);
            
            try {
                DB::connection('dynamic')->getPdo();
            } catch (\PDOException $e) {
                throw new Exception($e->getMessage());
            }
        }
    }
}