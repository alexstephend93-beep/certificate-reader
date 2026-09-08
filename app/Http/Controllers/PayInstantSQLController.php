<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PayInstantSQLController extends Controller
{
    /**
     * Display the SQL Generator page with dropdown
     */
    public function index()
    {
        // Get all unique gateways for dropdown (distinct gateway identifiers)
        $gateways = DB::table('payinstant_accounts')
            ->select('bank as value', 'bank as gateway_identifier')
            ->where('status', 1)
            ->groupBy('bank')
            ->orderBy('bank', 'asc')
            ->get();
        
        // Get all gateways grouped by prefix (for Quick Templates - shows all gateways)
        $quickTemplates = DB::table('payinstant_accounts as pa')
            ->leftJoin('payinstant_accounts_live as pal', 'pa.bank', '=', 'pal.bank')
            ->leftJoin('virtual_accounts as va', function ($join) {
                // Per-account join (NOT bank-only): matches the account name and accepts both
                // bank conventions — va.bank = ADM gateway name (new data) or main gateway
                // name (legacy data). A bank-only join mixes unrelated accounts that share
                // the same gateway.
                $join->on('va.account_name', '=', 'pa.name')
                    ->where(function ($q) {
                        $q->whereColumn('va.bank', 'pa.bank')
                            ->orWhereRaw("va.bank = REPLACE(pa.bank, '_ADM_', '_')");
                    });
            })
            ->select(
                'pa.bank as gateway_identifier',
                'pa.name as company_name',
                'pa.key as merchant_key',
                'pa.salt as merchant_salt',
                'pa.endpoint as proxy_endpoint',
                'pa.ac_number as account_number',
                'pa.user_id',
                'pa.client_id',
                'pa.admin_gateway',
                'pa.queue_status',
                'pa.daily_limit',
                'pa.balance as account_balance',
                'pa.bank as bank',
                'pal.endpoint as admin_endpoint',
                'pal.balance as live_balance',
                DB::raw("COALESCE(va.account_name, pa.name) as virtual_account_name"),
                'va.name as virtual_display_name',
                'va.ifsc',
                'va.full_acc_no as virtual_account_number',
                'va.debit_account',
                'va.account_id as virtual_account_id',
                'va.vpa',
                'va.tran_id',
                'va.serial_no',
                DB::raw("SUBSTRING_INDEX(pa.bank, '_', 1) as gateway_prefix"),
                DB::raw("IF(LOCATE('_ADM_', pa.bank) > 0, REPLACE(pa.bank, '_ADM_', '_'), pa.bank) as main_gateway_name"),
                DB::raw("pa.bank as adm_gateway_name"),
                DB::raw("SUBSTRING_INDEX(SUBSTRING_INDEX(pa.endpoint, '://', -1), '/', 1) as proxy_host"),
                'pa.created_at as created_at',
                'pa.status as status',
                'va.status as virtual_status'
            )
                        ->where('pa.status', 1)
            ->orderBy('pa.created_at', 'desc')
            ->orderBy('va.id', 'desc')
            ->get()
            ->unique('gateway_prefix')
            ->values(); // Get all, not just 7
    
        // no-store: the page ships inline JS — a stale cached copy would run old code
        // (e.g. missing helper functions after an update) and break the UI
        return response()
            ->view('database.payinstant-sql', compact('gateways', 'quickTemplates'))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    /**
     * Get all accounts for a specific gateway (for dropdown selection)
     */
    public function getGatewayAccounts($gatewayIdentifier)
    {
        try {
            $accounts = DB::table('payinstant_accounts as pa')
                ->leftJoin('payinstant_accounts_live as pal', 'pa.bank', '=', 'pal.bank')
                ->leftJoin('virtual_accounts as va', function ($join) {
                    // Per-account join (NOT bank-only): matches the account name and accepts
                    // both bank conventions (ADM gateway name / main gateway name).
                    $join->on('va.account_name', '=', 'pa.name')
                        ->where(function ($q) {
                            $q->whereColumn('va.bank', 'pa.bank')
                                ->orWhereRaw("va.bank = REPLACE(pa.bank, '_ADM_', '_')");
                        });
                })
                ->select(
                    'pa.bank as gateway_identifier',
                    'pa.name as company_name',
                    'pa.key as merchant_key',
                    'pa.salt as merchant_salt',
                    'pa.endpoint as proxy_endpoint',
                    'pa.ac_number as account_number',
                    'pa.user_id',
                    'pa.client_id',
                    'pa.admin_gateway',
                    'pa.queue_status',
                    'pa.daily_limit',
                    'pa.balance as account_balance',
                    'pa.bank as bank',
                    'pal.endpoint as admin_endpoint',
                    'pal.balance as live_balance',
                    DB::raw("COALESCE(va.account_name, pa.name) as virtual_account_name"),
                    'va.name as virtual_display_name',
                    'va.ifsc',
                    'va.full_acc_no as virtual_account_number',
                    'va.debit_account',
                    'va.account_id as virtual_account_id',
                    'va.vpa',
                    'va.tran_id',
                    'va.serial_no',
                    DB::raw("SUBSTRING_INDEX(pa.bank, '_', 1) as gateway_prefix"),
                    DB::raw("IF(LOCATE('_ADM_', pa.bank) > 0, REPLACE(pa.bank, '_ADM_', '_'), pa.bank) as main_gateway_name"),
                    DB::raw("pa.bank as adm_gateway_name"),
                    DB::raw("SUBSTRING_INDEX(SUBSTRING_INDEX(pa.endpoint, '://', -1), '/', 1) as proxy_host"),
                    'pa.created_at as created_at',
                    'pa.status as status',
                    'va.status as virtual_status'
                )
                ->where('pa.bank', $gatewayIdentifier)
                ->where('pa.status', 1)
                ->orderBy('pa.created_at', 'desc')
                ->orderBy('va.id', 'desc')
                ->get()
                ->unique(function ($account) {
                    // One card per credential entry (pa row). With multi-key accounts the same
                    // account name exists several times — the merchant key keeps them apart.
                    // (va.id desc makes each entry pair with its newest virtual_accounts row.)
                    return $account->gateway_identifier.'|'.$account->merchant_key;
                })
                ->values();

            // Lowercase endpoints + attach numeric proxy IP for each account
            $accounts = $accounts->map(function ($account) {
                return $this->decorateGateway($account);
            })->values();
            
            if ($accounts->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No accounts found for this gateway'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $accounts
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get gateway configuration by identifier (specific account)
     */
    public function getGateway($identifier)
    {
        try {
            $gateway = DB::table('payinstant_accounts as pa')
                ->leftJoin('payinstant_accounts_live as pal', 'pa.bank', '=', 'pal.bank')
                ->leftJoin('virtual_accounts as va', function ($join) {
                    // Per-account join (NOT bank-only): matches the account name and accepts
                    // both bank conventions (ADM gateway name / main gateway name).
                    $join->on('va.account_name', '=', 'pa.name')
                        ->where(function ($q) {
                            $q->whereColumn('va.bank', 'pa.bank')
                                ->orWhereRaw("va.bank = REPLACE(pa.bank, '_ADM_', '_')");
                        });
                })
                ->select(
                    'pa.bank as gateway_identifier',
                    'pa.name as company_name',
                    'pa.key as merchant_key',
                    'pa.salt as merchant_salt',
                    'pa.endpoint as proxy_endpoint',
                    'pa.ac_number as account_number',
                    'pa.user_id',
                    'pa.client_id',
                    'pa.admin_gateway',
                    'pa.queue_status',
                    'pa.daily_limit',
                    'pa.balance as account_balance',
                    'pa.bank as bank',
                    'pal.endpoint as admin_endpoint',
                    'pal.balance as live_balance',
                    DB::raw("COALESCE(va.account_name, pa.name) as virtual_account_name"),
                    'va.name as virtual_display_name',
                    'va.ifsc',
                    'va.full_acc_no as virtual_account_number',
                    'va.debit_account',
                    'va.account_id as virtual_account_id',
                    'va.vpa',
                    'va.tran_id',
                    'va.serial_no',
                    DB::raw("SUBSTRING_INDEX(pa.bank, '_', 1) as gateway_prefix"),
                    DB::raw("IF(LOCATE('_ADM_', pa.bank) > 0, REPLACE(pa.bank, '_ADM_', '_'), pa.bank) as main_gateway_name"),
                    DB::raw("pa.bank as adm_gateway_name"),
                    DB::raw("SUBSTRING_INDEX(SUBSTRING_INDEX(pa.endpoint, '://', -1), '/', 1) as proxy_host"),
                    'pa.created_at as created_at',
                    'pa.status as status',
                    'va.status as virtual_status'
                )
                ->where('pa.bank', $identifier)
                ->where('pa.status', 1)
                ->orderBy('pa.created_at', 'desc')
                ->orderBy('va.id', 'desc')
                ->first();

            // Lowercase endpoints + attach numeric proxy IP
            $gateway = $this->decorateGateway($gateway);
            
            if (!$gateway) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gateway not found'
                ], 404);
            }
            
            // Generate env params for this gateway
            $envParams = $this->generateEnvParamsFromGateway($gateway);
            
            return response()->json([
                'success' => true,
                'data' => $gateway,
                'env_params' => $envParams
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get gateway configuration by virtual account name (specific account)
     */
    public function getGatewayByVirtualName($virtualAccountName, Request $request)
    {
        try {
            $gateway = DB::table('payinstant_accounts as pa')
                ->leftJoin('payinstant_accounts_live as pal', 'pa.bank', '=', 'pal.bank')
                ->leftJoin('virtual_accounts as va', function ($join) {
                    // Per-account join (NOT bank-only): matches the account name and accepts
                    // both bank conventions (ADM gateway name / main gateway name).
                    $join->on('va.account_name', '=', 'pa.name')
                        ->where(function ($q) {
                            $q->whereColumn('va.bank', 'pa.bank')
                                ->orWhereRaw("va.bank = REPLACE(pa.bank, '_ADM_', '_')");
                        });
                })
                ->select(
                    'pa.bank as gateway_identifier',
                    'pa.name as company_name',
                    'pa.key as merchant_key',
                    'pa.salt as merchant_salt',
                    'pa.endpoint as proxy_endpoint',
                    'pa.ac_number as account_number',
                    'pa.user_id',
                    'pa.client_id',
                    'pa.admin_gateway',
                    'pa.queue_status',
                    'pa.daily_limit',
                    'pa.balance as account_balance',
                    'pa.bank as bank',
                    'pal.endpoint as admin_endpoint',
                    'pal.balance as live_balance',
                    DB::raw("COALESCE(va.account_name, pa.name) as virtual_account_name"),
                    'va.name as virtual_display_name',
                    'va.ifsc',
                    'va.full_acc_no as virtual_account_number',
                    'va.debit_account',
                    'va.account_id as virtual_account_id',
                    'va.vpa',
                    'va.tran_id',
                    'va.serial_no',
                    DB::raw("SUBSTRING_INDEX(pa.bank, '_', 1) as gateway_prefix"),
                    DB::raw("IF(LOCATE('_ADM_', pa.bank) > 0, REPLACE(pa.bank, '_ADM_', '_'), pa.bank) as main_gateway_name"),
                    DB::raw("pa.bank as adm_gateway_name"),
                    DB::raw("SUBSTRING_INDEX(SUBSTRING_INDEX(pa.endpoint, '://', -1), '/', 1) as proxy_host"),
                    'pa.created_at as created_at',
                    'pa.status as status',
                    'va.status as virtual_status'
                )
                ->where('pa.status', 1)
                ->where(DB::raw("COALESCE(va.account_name, pa.name)"), $virtualAccountName)
                // When a merchant key is given (multi-key accounts share one name), load
                // exactly that credential entry
                ->when($request->query('key'), function ($q, $key) {
                    return $q->where('pa.key', $key);
                })
                ->orderBy('pa.created_at', 'desc')
                ->orderBy('va.id', 'desc')
                ->first();

            // Lowercase endpoints + attach numeric proxy IP
            $gateway = $this->decorateGateway($gateway);
            
            if (!$gateway) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account not found with name: ' . $virtualAccountName
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $gateway
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get latest gateway by prefix (for Quick Templates)
     */
    public function getGatewayByPrefix($prefix)
    {
        try {
            $gateway = DB::table('payinstant_accounts as pa')
                ->leftJoin('payinstant_accounts_live as pal', 'pa.bank', '=', 'pal.bank')
                ->leftJoin('virtual_accounts as va', function ($join) {
                    // Per-account join (NOT bank-only): matches the account name and accepts
                    // both bank conventions (ADM gateway name / main gateway name).
                    $join->on('va.account_name', '=', 'pa.name')
                        ->where(function ($q) {
                            $q->whereColumn('va.bank', 'pa.bank')
                                ->orWhereRaw("va.bank = REPLACE(pa.bank, '_ADM_', '_')");
                        });
                })
                ->select(
                    'pa.bank as gateway_identifier',
                    'pa.name as company_name',
                    'pa.key as merchant_key',
                    'pa.salt as merchant_salt',
                    'pa.endpoint as proxy_endpoint',
                    'pa.ac_number as account_number',
                    'pa.user_id',
                    'pa.client_id',
                    'pa.admin_gateway',
                    'pa.queue_status',
                    'pa.daily_limit',
                    'pa.balance as account_balance',
                    'pa.bank as bank',
                    'pal.endpoint as admin_endpoint',
                    'pal.balance as live_balance',
                    DB::raw("COALESCE(va.account_name, pa.name) as virtual_account_name"),
                    'va.name as virtual_display_name',
                    'va.ifsc',
                    'va.full_acc_no as virtual_account_number',
                    'va.debit_account',
                    'va.account_id as virtual_account_id',
                    'va.vpa',
                    'va.tran_id',
                    'va.serial_no',
                    DB::raw("SUBSTRING_INDEX(pa.bank, '_', 1) as gateway_prefix"),
                    DB::raw("IF(LOCATE('_ADM_', pa.bank) > 0, REPLACE(pa.bank, '_ADM_', '_'), pa.bank) as main_gateway_name"),
                    DB::raw("pa.bank as adm_gateway_name"),
                    DB::raw("SUBSTRING_INDEX(SUBSTRING_INDEX(pa.endpoint, '://', -1), '/', 1) as proxy_host"),
                    'pa.created_at as created_at',
                    'pa.status as status',
                    'va.status as virtual_status'
                )
                ->where(DB::raw("SUBSTRING_INDEX(pa.bank, '_', 1)"), $prefix)
                ->where('pa.status', 1)
                ->orderBy('pa.created_at', 'desc')
                ->orderBy('va.id', 'desc')
                ->first();

            // Lowercase endpoints + attach numeric proxy IP
            $gateway = $this->decorateGateway($gateway);
            
            if (!$gateway) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gateway not found for prefix: ' . $prefix
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $gateway
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete an account belonging to a gateway (removes from all related tables)
     */
    public function deleteAccount(Request $request)
    {
        $request->validate([
            'bank' => 'required|string',
            'name' => 'required|string',
            'key' => 'nullable|string|max:100',
        ]);

        $bank = $request->input('bank');
        $name = $request->input('name');
        $key = $request->input('key');

        try {
            DB::transaction(function () use ($bank, $name, $key) {
                // Main-form of the bank name (ADM suffix stripped) — legacy virtual_accounts
                // rows store the main gateway name instead of the ADM gateway name.
                $mainFormBank = str_replace('_ADM_', '_', $bank);

                // Remove from payinstant_accounts + payinstant_accounts_live. When a merchant
                // key is given, only that credential entry is removed (multi-key accounts
                // share the same account name).
                $paQuery = DB::table('payinstant_accounts')->where('bank', $bank)->where('name', $name);
                $palQuery = DB::table('payinstant_accounts_live')->where('bank', $bank)->where('name', $name);
                if ($key) {
                    $paQuery->where('key', $key);
                    $palQuery->where('key', $key);
                }
                $paQuery->delete();
                $palQuery->delete();

                // Remove the virtual_accounts rows only when no credential rows remain for
                // this account (i.e. its last entry was just deleted)
                $remaining = DB::table('payinstant_accounts')
                    ->where('bank', $bank)
                    ->where('name', $name)
                    ->count();

                if ($remaining === 0) {
                    DB::table('virtual_accounts')
                        ->where(function ($q) use ($bank, $mainFormBank) {
                            $q->where('bank', $bank)->orWhere('bank', $mainFormBank);
                        })
                        ->where(function ($q) use ($name) {
                            $q->where('account_name', $name)->orWhere('name', $name);
                        })
                        ->delete();
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Account "' . $name . '" deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete account: ' . $e->getMessage()
            ], 500);
        }
        }

    /**
     * Save gateway configuration to database (insert or update).
     * Uses merchant_key + admin gateway name (bank) as the upsert key.
     */
    public function saveToDatabase(Request $request)
    {
        $request->validate([
            'full_display_name' => 'required|string|max:255',
            'merchant_key' => 'nullable|string|max:100',
            'merchant_salt' => 'nullable|string|max:100',
            'bank' => 'required|string|max:100',
            'ifsc' => 'nullable|string|max:50',
            'proxy_endpoint' => 'required|string|max:500',
            'admin_endpoint' => 'required|string|max:500',
            'account_number' => 'required|string|max:50',
            'client_id' => 'nullable|string|max:100',
            'user_id' => 'required|integer',
            'account_id' => 'required|integer',
            'virtual_account_name' => 'required|string|max:255',
            'debit_account' => 'nullable|string|max:50',
            'daily_limit' => 'nullable|string|max:50',
            'admin_gateway' => 'nullable|string|max:50',
            'tran_id' => 'nullable|string|max:50',
            'serial_no' => 'nullable|string|max:50',
            'status' => 'nullable|boolean',
            'queue_status' => 'nullable|boolean',
            'vpa' => 'nullable|string|max:100',
            'balance' => 'nullable|string|max:50',
            'main_gateway_name' => 'required|string|max:100',
            'adm_gateway_name' => 'required|string|max:100',
                        'key_generation_type' => 'required|in:static,dynamic_mysql_32,dynamic_mysql_16,custom',
            'salt_generation_type' => 'required|in:static,dynamic_mysql_16,custom',
            'original_bank' => 'nullable|string|max:100',
            'original_key' => 'nullable|string|max:100',
            'original_virtual_account_name' => 'nullable|string|max:255',
        ]);

        try {
            $now = now()->format('Y-m-d H:i:s');

            // Normalize values
            $fullDisplayName = strtoupper($request->full_display_name);
            $bank = strtoupper($request->bank);
            $ifsc = strtoupper($request->ifsc ?? '');
            $proxyEndpoint = strtolower($this->formatEndpoint($request->proxy_endpoint));
            $adminEndpoint = strtolower($this->formatEndpoint($request->admin_endpoint));
            $accountNumber = $request->account_number;
            $clientId = strtoupper($request->client_id ?? '');
            $userId = $request->user_id;
            $accountId = $request->account_id;
            $virtualAccountName = strtoupper($request->virtual_account_name);
            // If Debit Account is empty, use the Account Number (usually the same, but they
            // can legitimately differ — only fill when one side is blank)
            $debitAccount = $request->filled('debit_account') ? $request->debit_account : $accountNumber;
            $dailyLimit = $request->daily_limit;
            $adminGateway = strtoupper($request->admin_gateway ?? '');
            $tranId = $request->tran_id ?? '0000';
            $serialNo = $request->serial_no ?? '000';
            $status = $request->status ? 1 : 0;
            $queueStatus = $request->queue_status ? 1 : 0;
            $vpa = $request->vpa ?? '';
            $balance = $request->balance ?? '0.00';
            $mainGateway = strtoupper($request->main_gateway_name);
            $admGateway = strtoupper($request->adm_gateway_name);

            // Original values from the loaded account (for comparison / row location)
            $originalBank = $request->input('original_bank') ? strtoupper($request->input('original_bank')) : null;
            $originalKey = $request->input('original_key') ? strtoupper($request->input('original_key')) : null;
            $originalVirtualAccountName = $request->input('original_virtual_account_name')
                ? strtoupper($request->input('original_virtual_account_name'))
                : null;

            // Key / salt handling
            $generateKeyViaSQL = in_array($request->key_generation_type, ['dynamic_mysql_32', 'dynamic_mysql_16']);
            $generateSaltViaSQL = $request->salt_generation_type === 'dynamic_mysql_16';

            if ($generateKeyViaSQL) {
                $keyLength = $request->key_generation_type === 'dynamic_mysql_32' ? 32 : 16;
                $merchantKey = strtoupper(substr(str_replace('-', '', Str::uuid()), 0, $keyLength));
            } elseif (in_array($request->key_generation_type, ['static', 'custom'])) {
                $merchantKey = strtoupper($request->merchant_key);
            } else {
                $merchantKey = $this->generateKey();
            }

            if ($generateSaltViaSQL) {
                $merchantSalt = strtoupper(substr(str_replace('-', '', Str::uuid()), 0, 16));
            } elseif (in_array($request->salt_generation_type, ['static', 'custom'])) {
                $merchantSalt = strtoupper($request->merchant_salt);
            } else {
                $merchantSalt = $this->generateSalt();
            }

            // Determine insert vs update strategy:
            // 1. Admin Gateway Name (bank) changed -> INSERT new rows in all 3 tables (brand-new gateway)
            // 2. Key changed (same gateway)        -> INSERT new rows in payinstant_accounts + _live;
            //                                         the virtual_accounts row is reused/updated
            // 3. Neither changed (account loaded)  -> UPDATE the existing rows in all 3 tables

            $bankChanged = $originalBank && $admGateway !== $originalBank;
            $keyChanged = $originalKey && $merchantKey !== $originalKey;

            if (!$bankChanged && !$keyChanged && $originalBank && $originalKey) {
                // Admin Gateway unchanged, Key unchanged -> UPDATE existing account
                $action = 'update';
                $matchKey = $originalKey;
                $matchBank = $originalBank;
            } else {
                // Admin Gateway changed OR Key changed -> INSERT new rows
                $action = 'insert';
                $matchKey = $merchantKey;
                $matchBank = $admGateway;
            }

            // Per-table affected/inserted row counts — returned to the UI so a save that
            // matches nothing is reported instead of silently doing nothing.
            $tableResults = DB::transaction(function () use (
                $fullDisplayName, $merchantKey, $merchantSalt, $bank, $proxyEndpoint,
                $adminEndpoint, $status, $accountNumber, $clientId, $balance, $now,
                $userId, $adminGateway, $queueStatus, $dailyLimit, $ifsc,
                $virtualAccountName, $mainGateway, $admGateway, $accountId,
                $debitAccount, $tranId, $serialNo, $vpa, $action, $matchKey, $matchBank,
                $originalVirtualAccountName
            ) {
                $results = [];

                // Legacy virtual_accounts rows store the main gateway name (ADM suffix
                // stripped); newer rows store the ADM gateway name. Accept both forms.
                $mainFormBank = str_replace('_ADM_', '_', $matchBank);

                if ($action === 'update') {
                    // ---- UPDATE the existing account ----

                    // payinstant_accounts — located by the ORIGINAL merchant_key + original bank
                    $results['payinstant_accounts'] = DB::table('payinstant_accounts')
                        ->where('key', $matchKey)
                        ->where('bank', $matchBank)
                        ->update([
                            'name' => $fullDisplayName, 'salt' => $merchantSalt,
                            'endpoint' => $proxyEndpoint, 'status' => $status,
                            'ac_number' => $accountNumber, 'client_id' => $clientId ?: null,
                            'va_balance' => null, 'va_bal_updated_at' => null,
                            'balance' => $balance, 'bal_updated_at' => $now,
                            'user_id' => $userId,
                            'min_balance' => '0.00', 'max_txn_count' => '0',
                            'admin_gateway' => $adminGateway ?: null,
                            'queue_status' => $queueStatus,
                            'daily_limit' => $dailyLimit ?: null,
                        ]);

                    // payinstant_accounts_live — located by the ORIGINAL merchant_key + original bank
                    $liveUpdated = DB::table('payinstant_accounts_live')
                        ->where('key', $matchKey)
                        ->where('bank', $matchBank)
                        ->update([
                            'name' => $fullDisplayName, 'salt' => $merchantSalt,
                            'endpoint' => $adminEndpoint, 'status' => $status,
                            'ac_number' => $accountNumber, 'client_id' => $clientId ?: null,
                            'balance' => $balance, 'bal_updated_at' => $now,
                        ]);
                    if ($liveUpdated === 0) {
                        // Live row missing for this account — create it so both tables stay in sync
                        DB::table('payinstant_accounts_live')->insert([
                            'name' => $fullDisplayName, 'key' => $matchKey, 'salt' => $merchantSalt,
                            'bank' => $matchBank, 'endpoint' => $adminEndpoint, 'status' => $status,
                            'ac_number' => $accountNumber, 'client_id' => $clientId ?: null,
                            'balance' => $balance, 'bal_updated_at' => $now,
                        ]);
                        $results['payinstant_accounts_live'] = 1;
                    } else {
                        $results['payinstant_accounts_live'] = $liveUpdated;
                    }

                    // virtual_accounts — MUST be located by the ORIGINAL virtual account name +
                    // ORIGINAL bank (the new values are the payload, not the locator). This
                    // also makes renaming the virtual account work.
                    $vaUpdated = DB::table('virtual_accounts')
                        ->where('account_name', $originalVirtualAccountName ?: $virtualAccountName)
                        ->where(function ($q) use ($matchBank, $mainFormBank) {
                            $q->where('bank', $matchBank)->orWhere('bank', $mainFormBank);
                        })
                        ->update([
                            'user_id' => $userId, 'name' => $fullDisplayName,
                            'account_name' => $virtualAccountName,
                            'ifsc' => $ifsc ?: null, 'full_acc_no' => $accountNumber,
                            'vpa' => $vpa ?: null, 'tran_id' => $tranId,
                            'serial_no' => $serialNo, 'status' => $status,
                            'updated_at' => $now,
                            'debit_account' => $debitAccount, 'account_id' => $accountId,
                        ]);
                    if ($vaUpdated === 0) {
                        // No virtual_accounts row exists for this account yet — create one so
                        // the account is complete in all 3 tables
                        DB::table('virtual_accounts')->insert([
                            'user_id' => $userId, 'account_name' => $virtualAccountName,
                            'name' => $fullDisplayName, 'bank' => $admGateway,
                            'ifsc' => $ifsc ?: null, 'full_acc_no' => $accountNumber,
                            'vpa' => $vpa ?: null, 'tran_id' => $tranId,
                            'serial_no' => $serialNo, 'status' => $status,
                            'created_at' => $now, 'updated_at' => $now,
                            'debit_account' => $debitAccount, 'account_id' => $accountId,
                        ]);
                        $results['virtual_accounts'] = 1;
                    } else {
                        $results['virtual_accounts'] = $vaUpdated;
                    }
                } else {
                    // ---- INSERT new rows (duplicate-guarded so re-saving is safe) ----

                    // payinstant_accounts — upsert on bank + key
                    $paUpdated = DB::table('payinstant_accounts')
                        ->where('bank', $admGateway)
                        ->where('key', $merchantKey)
                        ->update([
                            'name' => $fullDisplayName, 'salt' => $merchantSalt,
                            'endpoint' => $proxyEndpoint, 'status' => $status,
                            'ac_number' => $accountNumber, 'client_id' => $clientId ?: null,
                            'va_balance' => null, 'va_bal_updated_at' => null,
                            'balance' => $balance, 'bal_updated_at' => $now,
                            'user_id' => $userId,
                            'min_balance' => '0.00', 'max_txn_count' => '0',
                            'admin_gateway' => $adminGateway ?: null,
                            'queue_status' => $queueStatus,
                            'daily_limit' => $dailyLimit ?: null,
                        ]);
                    if ($paUpdated === 0) {
                        DB::table('payinstant_accounts')->insert([
                            'name' => $fullDisplayName, 'key' => $merchantKey, 'salt' => $merchantSalt,
                            'bank' => $admGateway, 'endpoint' => $proxyEndpoint, 'status' => $status,
                            'ac_number' => $accountNumber, 'client_id' => $clientId ?: null,
                            'va_balance' => null, 'va_bal_updated_at' => null,
                            'balance' => $balance, 'bal_updated_at' => $now,
                            'created_at' => $now, 'user_id' => $userId,
                            'min_balance' => '0.00', 'max_txn_count' => '0',
                            'admin_gateway' => $adminGateway ?: null,
                            'queue_status' => $queueStatus,
                            'daily_limit' => $dailyLimit ?: null,
                        ]);
                    }
                    $results['payinstant_accounts'] = max($paUpdated, 1);

                    // payinstant_accounts_live — upsert on bank + key
                    $palUpdated = DB::table('payinstant_accounts_live')
                        ->where('bank', $admGateway)
                        ->where('key', $merchantKey)
                        ->update([
                            'name' => $fullDisplayName, 'salt' => $merchantSalt,
                            'endpoint' => $adminEndpoint, 'status' => $status,
                            'ac_number' => $accountNumber, 'client_id' => $clientId ?: null,
                            'balance' => $balance, 'bal_updated_at' => $now,
                        ]);
                    if ($palUpdated === 0) {
                        DB::table('payinstant_accounts_live')->insert([
                            'name' => $fullDisplayName, 'key' => $merchantKey, 'salt' => $merchantSalt,
                            'bank' => $admGateway, 'endpoint' => $adminEndpoint, 'status' => $status,
                            'ac_number' => $accountNumber, 'client_id' => $clientId ?: null,
                            'balance' => $balance, 'bal_updated_at' => $now,
                        ]);
                    }
                    $results['payinstant_accounts_live'] = max($palUpdated, 1);

                    // virtual_accounts — a new Admin Gateway Name OR a new Merchant Key means a
                    // NEW entry in every table, so a new row is inserted here as well. bank uses
                    // the ADM gateway name so it joins with payinstant_accounts.bank (legacy
                    // main-gateway-name rows are still found via the fallback match).
                    $vaRow = [
                        'user_id' => $userId, 'account_name' => $virtualAccountName,
                        'name' => $fullDisplayName, 'bank' => $admGateway,
                        'ifsc' => $ifsc ?: null, 'full_acc_no' => $accountNumber,
                        'vpa' => $vpa ?: null, 'tran_id' => $tranId,
                        'serial_no' => $serialNo, 'status' => $status,
                        'debit_account' => $debitAccount, 'account_id' => $accountId,
                    ];
                    if ($paUpdated === 0) {
                        // Genuinely new entry (new key / new gateway) -> new virtual_accounts row
                        $vaRow['created_at'] = $now;
                        $vaRow['updated_at'] = $now;
                        DB::table('virtual_accounts')->insert($vaRow);
                        $results['virtual_accounts'] = 1;
                    } else {
                        // Re-save of an existing bank + key -> keep the matching virtual_accounts
                        // row(s) in sync instead of stacking duplicates
                        $mainFormAdm = str_replace('_ADM_', '_', $admGateway);
                        $vaUpdated = DB::table('virtual_accounts')
                            ->where('account_name', $virtualAccountName)
                            ->where(function ($q) use ($admGateway, $mainFormAdm) {
                                $q->where('bank', $admGateway)->orWhere('bank', $mainFormAdm);
                            })
                            ->update([
                                'user_id' => $vaRow['user_id'], 'name' => $vaRow['name'],
                                'ifsc' => $vaRow['ifsc'], 'full_acc_no' => $vaRow['full_acc_no'],
                                'vpa' => $vaRow['vpa'], 'tran_id' => $vaRow['tran_id'],
                                'serial_no' => $vaRow['serial_no'], 'status' => $vaRow['status'],
                                'updated_at' => $now,
                                'debit_account' => $vaRow['debit_account'], 'account_id' => $vaRow['account_id'],
                            ]);
                        if ($vaUpdated === 0) {
                            $vaRow['created_at'] = $now;
                            $vaRow['updated_at'] = $now;
                            DB::table('virtual_accounts')->insert($vaRow);
                            $results['virtual_accounts'] = 1;
                        } else {
                            $results['virtual_accounts'] = $vaUpdated;
                        }
                    }
                }

                return $results;
            });

            // Build a per-table summary for the UI toast
            $summary = [];
            foreach ($tableResults as $tableName => $rows) {
                $summary[] = $tableName . ' (' . $rows . ' row' . ($rows == 1 ? '' : 's') . ')';
            }
            $message = 'Account ' . ($action === 'insert' ? 'created' : 'updated') . ' in database successfully: '
                . implode(', ', $summary);

            return response()->json([
                'success' => true,
                'message' => $message,
                'action' => $action,
                'tables' => $tableResults,
                'bank' => $bank,
                'main_gateway' => $mainGateway,
                'adm_gateway' => $admGateway,
                'virtual_account_name' => $virtualAccountName,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save to database: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Resolve hostname to numeric IP (Detected Proxy IP)
     */
    public function resolveIp(Request $request)
    {
        $host = $request->query('host', '');

        if (empty($host)) {
            return response()->json([
                'success' => false,
                'message' => 'Host parameter is required'
            ], 400);
        }

        try {
            $ip = $this->extractNumericIP('https://' . $host . '/');

            return response()->json([
                'success' => true,
                'ip' => $ip
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate SQL and Environment Variables
     */
    public function generate(Request $request)
    {
        $request->validate([
            // Common fields
            'full_display_name' => 'required|string|max:255',
            'merchant_key' => 'nullable|string|max:100',
            'merchant_salt' => 'nullable|string|max:100',
            'bank' => 'required|string|max:100',
            'ifsc' => 'nullable|string|max:50',
            'proxy_endpoint' => 'required|string|max:500',
            'admin_endpoint' => 'required|string|max:500',
            'account_number' => 'required|string|max:50',
            'client_id' => 'nullable|string|max:100',
            'user_id' => 'required|integer',
            'account_id' => 'required|integer',
            'virtual_account_name' => 'required|string|max:255',
            
            // Gateway configuration (prefix auto-detected)
            'main_gateway_name' => 'required|string|max:100',
            'adm_gateway_name' => 'required|string|max:100',
            
            // Key generation strategy
            'key_generation_type' => 'required|in:static,dynamic_mysql_32,dynamic_mysql_16,custom',
            'salt_generation_type' => 'required|in:static,dynamic_mysql_16,custom',
            
            // Optional fields
            'daily_limit' => 'nullable|string|max:50',
            'admin_gateway' => 'nullable|string|max:50',
            'tran_id' => 'nullable|string|max:50',
            'serial_no' => 'nullable|string|max:50',
            'status' => 'required|boolean',
            'queue_status' => 'nullable|boolean',
            'vpa' => 'nullable|string|max:100',
            'balance' => 'nullable|string|max:50',
        ]);

        $now = now()->format('Y-m-d H:i:s');
        
        // Determine key/salt generation
        $generateKeyViaSQL = in_array($request->key_generation_type, ['dynamic_mysql_32', 'dynamic_mysql_16']);
        $generateSaltViaSQL = $request->salt_generation_type === 'dynamic_mysql_16';
        
        // Set key/salt values
        $merchantKey = $request->merchant_key;
        $merchantSalt = $request->merchant_salt;
        
        // Build SQL variables
        $sqlVariables = [];
        
        if ($generateKeyViaSQL) {
            $keyLength = $request->key_generation_type === 'dynamic_mysql_32' ? 32 : 16;
            $sqlVariables[] = "SET @key = UPPER(SUBSTRING(REPLACE(UUID(),'-',''),1,{$keyLength}));";
        } elseif (empty($merchantKey)) {
            $merchantKey = $this->generateKey();
        }
        
        if ($generateSaltViaSQL) {
            $sqlVariables[] = "SET @salt = UPPER(SUBSTRING(REPLACE(UUID(),'-',''),1,16));";
        } elseif (empty($merchantSalt)) {
            $merchantSalt = $this->generateSalt();
        }

        // Get values - Convert to lowercase where appropriate
        $fullDisplayName = strtoupper($request->full_display_name);
        $bank = strtoupper($request->bank);
        $ifsc = strtoupper($request->ifsc ?? 'NULL');
        // Lowercase for endpoints
        $proxyEndpoint = strtolower($this->formatEndpoint($request->proxy_endpoint));
        $adminEndpoint = strtolower($this->formatEndpoint($request->admin_endpoint));
        $accountNumber = $request->account_number;
        $clientId = strtoupper($request->client_id ?? 'NULL');
        $userId = $request->user_id;
        $accountId = $request->account_id;
        $virtualAccountName = strtoupper($request->virtual_account_name);
        // If Debit Account is empty, use the Account Number (usually the same, but they
        // can legitimately differ — only fill when one side is blank)
        $debitAccount = $request->filled('debit_account') ? $request->debit_account : $accountNumber;
        $dailyLimit = $request->daily_limit ?? 'NULL';
        $adminGateway = strtoupper($request->admin_gateway ?? 'NULL');
        $tranId = $request->tran_id ?? '0000';
        $serialNo = $request->serial_no ?? '000';
        $status = $request->status ? 1 : 0;
        $queueStatus = $request->queue_status ? 1 : 0;
        $vpa = $request->vpa ?? 'NULL';
        $balance = $request->balance ?? '0.00';

        // Gateway naming (convert to uppercase)
        $mainGateway = strtoupper($request->main_gateway_name);
        $admGateway = strtoupper($request->adm_gateway_name);
        
        // AUTO-DETECT Gateway Prefix from main_gateway_name or adm_gateway_name
        $gatewayPrefix = $this->detectGatewayPrefix($mainGateway, $admGateway);

        // Build all SQL statements
        $sqlStatements = [];
        
        // 1. payinstant_accounts
        $sqlStatements[] = [
            'table' => 'payinstant_accounts',
            'sql' => $this->buildPayInstantAccountsSQL(
                $fullDisplayName, $merchantKey, $merchantSalt, $bank, $proxyEndpoint,
                $status, $accountNumber, $clientId, $balance, $now, $userId,
                $adminGateway, $queueStatus, $dailyLimit, $generateKeyViaSQL, $generateSaltViaSQL
            ),
            'columns' => $this->getAccountColumns($fullDisplayName, $merchantKey, $merchantSalt, $bank, $proxyEndpoint, $status, $accountNumber, $clientId, $balance, $userId, $adminGateway, $queueStatus, $dailyLimit, $generateKeyViaSQL, $generateSaltViaSQL)
        ];

        // 2. payinstant_accounts_live
        $sqlStatements[] = [
            'table' => 'payinstant_accounts_live',
            'sql' => $this->buildPayInstantAccountsLiveSQL(
                $fullDisplayName, $merchantKey, $merchantSalt, $bank, $adminEndpoint,
                $status, $accountNumber, $clientId, $balance, $now, $generateKeyViaSQL, $generateSaltViaSQL
            ),
            'columns' => $this->getAccountLiveColumns($fullDisplayName, $merchantKey, $merchantSalt, $bank, $adminEndpoint, $status, $accountNumber, $clientId, $balance, $generateKeyViaSQL, $generateSaltViaSQL)
        ];

        // 3. virtual_accounts
        $sqlStatements[] = [
            'table' => 'virtual_accounts',
            'sql' => $this->buildVirtualAccountsSQL(
                $userId, $virtualAccountName, $fullDisplayName, $mainGateway, $ifsc,
                $accountNumber, $vpa, $tranId, $serialNo, $status,
                $now, $debitAccount, $accountId
            ),
            'columns' => $this->getVirtualColumns($userId, $virtualAccountName, $fullDisplayName, $mainGateway, $ifsc, $accountNumber, $vpa, $tranId, $serialNo, $status, $debitAccount, $accountId)
        ];

        // Generate Environment Variables
        $envParams = $this->generateEnvParams([
            'gateway_prefix' => $gatewayPrefix,
            'main_gateway' => $mainGateway,
            'adm_gateway' => $admGateway,
            'account_number' => $accountNumber,
            'proxy_ip' => $this->extractNumericIP($proxyEndpoint),
            'bank' => $bank,
        ]);

        // Build full SQL
        $fullSQL = '';
        if (!empty($sqlVariables)) {
            $fullSQL = "-- Auto-generated variables\n" . implode("\n", $sqlVariables) . "\n\n";
        }
        $fullSQL .= collect($sqlStatements)->pluck('sql')->join("\n\n");

        return response()->json([
            'success' => true,
            'statements' => $sqlStatements,
            'full_sql' => $fullSQL,
            'env_params' => $envParams,
            'gateway_config' => [
                'main_gateway' => $mainGateway,
                'adm_gateway' => $admGateway,
                'gateway_prefix' => $gatewayPrefix,
            ],
            'key' => $merchantKey,
            'salt' => $merchantSalt,
            'generated_at' => $now,
            'key_auto_generated' => $generateKeyViaSQL,
            'salt_auto_generated' => $generateSaltViaSQL,
        ]);
    }

    /**
     * Auto-detect Gateway Prefix from gateway names
     */
    private function detectGatewayPrefix($mainGateway, $admGateway)
    {
        // Try to get prefix from main_gateway_name
        if ($mainGateway) {
            $parts = explode('_', $mainGateway);
            if (count($parts) >= 1) {
                return $parts[0];
            }
        }
        
        // Try to get prefix from adm_gateway_name
        if ($admGateway) {
            $parts = explode('_', $admGateway);
            if (count($parts) >= 1) {
                return $parts[0];
            }
        }
        
        // Fallback to 'GATEWAY'
        return 'GATEWAY';
    }

    /**
     * Generate Environment Variables from existing gateway
     */
        private function generateEnvParamsFromGateway($gateway)
    {
        $prefix = $gateway->gateway_prefix
            ?? $this->detectGatewayPrefix($gateway->main_gateway_name, $gateway->adm_gateway_name);

        $proxyIP = $this->extractNumericIP($gateway->proxy_endpoint ?? '');

        return $this->buildEnvParams(
            $prefix,
            $gateway->main_gateway_name,
            $gateway->adm_gateway_name,
            $gateway->account_number,
            $proxyIP
        );
    }

    /**
     * Generate Environment Variables
     */
        private function generateEnvParams($data)
    {
        $prefix = $data['gateway_prefix']
            ?? $this->detectGatewayPrefix($data['main_gateway'] ?? '', $data['adm_gateway'] ?? '');

        return $this->buildEnvParams(
            $prefix,
            $data['main_gateway'],
            $data['adm_gateway'],
            $data['account_number'],
            $data['proxy_ip']
        );
    }

    /**
     * Derive the main gateway name from a bank identifier
     * (e.g. "FIATPE_ADM_HDFC" -> "FIATPE_HDFC").
     */
    private function mainGatewayName(string $bank): string
    {
        if ($bank && strpos($bank, '_ADM_') !== false) {
            return str_replace('_ADM_', '_', $bank);
        }

        return $bank;
    }

    /**
     * Build the full .env parameter set for a gateway.
     *
     * The map-type variables (PROXY_IP, ADMIN_GATEWAYS, MERCHANT_BANKS,
     * PAYOUT_GAT_ACCS) are aggregated from ALL active accounts already stored
     * in the database so the generated .env contains the complete picture,
     * matching the production format. The gateway currently being generated or
     * loaded always overrides any stale DB row.
     *
     * Values already carry the quoting required for a valid .env line:
     *  - gateway names  -> double quoted   (KEY="VALUE")
     *  - map variables  -> single quoted    (KEY='{...json...}')
     *
     * @return array<string,string>
     */
    private function buildEnvParams(string $prefix, string $mainGateway, string $admGateway, string $accountNumber, string $proxyIP): array
    {
        // Only use the current form's gateway data - NOT aggregated from all accounts
        $proxyIpMap = [$mainGateway => $proxyIP];
        $adminMap    = [$admGateway => $admGateway];
        $payoutMap   = [$accountNumber => $mainGateway];

        // MERCHANT_BANKS: Only current gateway
        $merchantBanks = ["1" => $mainGateway];

        $jsonOpts = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

        return [
            $prefix . '_MAIN_GATEWAY'       => '"' . $mainGateway . '"',
            $prefix . '_ADM_GATEWAY'         => '"' . $admGateway . '"',
            'PROXY_IP'                        => "'" . json_encode($proxyIpMap, $jsonOpts) . "'",
            'MERCHANT_BANKS'                  => "'" . json_encode($merchantBanks, $jsonOpts) . "'",
            'ADMIN_GATEWAYS'                  => "'" . json_encode($adminMap, $jsonOpts) . "'",
            'PAYOUT_GAT_ACCS'                 => "'" . json_encode($payoutMap, $jsonOpts) . "'",
        ];
    }

    /**
     * Extract Numeric IP from endpoint URL
     * Returns only the IP address if found, otherwise returns the hostname
     */
        private static $ipCache = [];

    /**
     * Extract Numeric IP from endpoint URL
     * Returns only the IP address if found, otherwise returns the hostname
     */
    private function extractNumericIP($url)
    {
        if (empty($url)) {
            return '';
        }

        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '';

        if ($host === '') {
            return '';
        }

        // Hostname is already an IP — return instantly (no blocking lookup).
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $host;
        }

        // Reuse a cached resolution when this host has been seen before in the
        // current request, so repeated lookups (e.g. when building env params
        // across all accounts) can never trigger repeated blocking DNS calls.
        if (isset(self::$ipCache[$host])) {
            return self::$ipCache[$host];
        }

        // Resolve host to IP (best-effort; result is cached below).
        $ip = @gethostbyname($host);

        if ($ip && $ip !== $host && filter_var($ip, FILTER_VALIDATE_IP)) {
            return self::$ipCache[$host] = $ip;
        }

        // Could not resolve — fall back to the hostname itself.
        return self::$ipCache[$host] = $host;
    }

    /**
     * Normalize gateway/account data: lowercase endpoints and resolve numeric proxy IP
     */
    private function decorateGateway($gateway)
    {
        if (!$gateway) {
            return $gateway;
        }

        $gateway->proxy_endpoint = strtolower($gateway->proxy_endpoint ?? '');
        $gateway->admin_endpoint = strtolower($gateway->admin_endpoint ?? '');
        $gateway->proxy_ip = $this->extractNumericIP($gateway->proxy_endpoint);

        return $gateway;
    }

    /**
     * Format endpoint to ensure https:// and trailing /
     */
    private function formatEndpoint($url)
    {
        if (empty($url)) {
            return $url;
        }
        
        if (!preg_match('/^https?:\/\//i', $url)) {
            $url = 'https://' . $url;
        }
        
        $url = preg_replace('/^http:\/\//i', 'https://', $url);
        
        if (substr($url, -1) !== '/') {
            $url .= '/';
        }
        
        return $url;
    }

    private function generateKey()
    {
        return 'MER' . strtoupper(substr(str_replace('-', '', Str::uuid()), 0, 32));
    }

    private function generateSalt()
    {
        return 'SALT' . strtoupper(substr(str_replace('-', '', Str::uuid()), 0, 16));
    }

    private function buildPayInstantAccountsSQL($name, $key, $salt, $bank, $endpoint, $status, $acNumber, $clientId, $balance, $now, $userId, $adminGateway, $queueStatus, $dailyLimit, $useKeyVar = false, $useSaltVar = false)
    {
        $clientIdValue = $clientId === 'NULL' ? 'NULL' : "'{$clientId}'";
        $keyValue = $useKeyVar ? '@key' : "'{$key}'";
        $saltValue = $useSaltVar ? '@salt' : "'{$salt}'";
        $adminGatewayValue = $adminGateway === 'NULL' ? 'NULL' : "'{$adminGateway}'";
        $dailyLimitValue = $dailyLimit === 'NULL' ? 'NULL' : "'{$dailyLimit}'";
        
        return "INSERT INTO `payinstant_accounts` (\n" .
               "    `id`, `name`, `key`, `salt`, `bank`, `endpoint`, `status`,\n" .
               "    `ac_number`, `client_id`, `va_balance`, `va_bal_updated_at`, `balance`, `bal_updated_at`,\n" .
               "    `created_at`, `user_id`, `min_balance`, `max_txn_count`, `admin_gateway`, `queue_status`, `daily_limit`\n" .
               ") VALUES (\n" .
               "    NULL, '{$name}', {$keyValue}, {$saltValue}, '{$bank}', '{$endpoint}', '{$status}',\n" .
               "    '{$acNumber}', {$clientIdValue}, NULL, NULL, '{$balance}', '{$now}',\n" .
               "    '{$now}', '{$userId}', '0.00', '0', {$adminGatewayValue}, '{$queueStatus}', {$dailyLimitValue}\n" .
               ");";
    }

    private function buildPayInstantAccountsLiveSQL($name, $key, $salt, $bank, $endpoint, $status, $acNumber, $clientId, $balance, $now, $useKeyVar = false, $useSaltVar = false)
    {
        $clientIdValue = $clientId === 'NULL' ? 'NULL' : "'{$clientId}'";
        $keyValue = $useKeyVar ? '@key' : "'{$key}'";
        $saltValue = $useSaltVar ? '@salt' : "'{$salt}'";
        
        return "INSERT INTO `payinstant_accounts_live` (\n" .
               "    `id`, `name`, `key`, `salt`, `bank`, `endpoint`, `status`,\n" .
               "    `ac_number`, `client_id`, `balance`, `bal_updated_at`\n" .
               ") VALUES (\n" .
               "    NULL, '{$name}', {$keyValue}, {$saltValue}, '{$bank}', '{$endpoint}', '{$status}',\n" .
               "    '{$acNumber}', {$clientIdValue}, '{$balance}', '{$now}'\n" .
               ");";
    }

    private function buildVirtualAccountsSQL($userId, $accountName, $name, $bank, $ifsc, $fullAccNo, $vpa, $tranId, $serialNo, $status, $now, $debitAccount, $accountId)
    {
        $vpaValue = $vpa === 'NULL' ? 'NULL' : "'{$vpa}'";
        $ifscValue = $ifsc === 'NULL' ? 'NULL' : "'{$ifsc}'";
        
        return "INSERT INTO `virtual_accounts` (\n" .
               "    `id`, `user_id`, `account_name`, `name`, `bank`, `ifsc`, `full_acc_no`,\n" .
               "    `vpa`, `tran_id`, `serial_no`, `status`, `created_at`, `updated_at`, `debit_account`, `account_id`\n" .
               ") VALUES (\n" .
               "    NULL, '{$userId}', '{$accountName}', '{$name}', '{$bank}', {$ifscValue}, '{$fullAccNo}',\n" .
               "    {$vpaValue}, '{$tranId}', '{$serialNo}', '{$status}',\n" .
               "    '{$now}', '{$now}', '{$debitAccount}', '{$accountId}'\n" .
               ");";
    }

    private function getAccountColumns($name, $key, $salt, $bank, $endpoint, $status, $acNumber, $clientId, $balance, $userId, $adminGateway, $queueStatus, $dailyLimit, $useKeyVar, $useSaltVar)
    {
        return [
            'name' => $name,
            'key' => $useKeyVar ? '@key (auto-generated)' : $key,
            'salt' => $useSaltVar ? '@salt (auto-generated)' : $salt,
            'bank' => $bank,
            'endpoint' => $endpoint,
            'status' => $status ? 'Active (1)' : 'Inactive (0)',
            'ac_number' => $acNumber,
            'client_id' => $clientId === 'NULL' ? 'NULL' : $clientId,
            'balance' => $balance,
            'user_id' => $userId,
            'admin_gateway' => $adminGateway === 'NULL' ? 'NULL' : $adminGateway,
            'queue_status' => $queueStatus ? 'Enabled (1)' : 'Disabled (0)',
            'daily_limit' => $dailyLimit === 'NULL' ? 'NULL' : $dailyLimit,
        ];
    }

    private function getAccountLiveColumns($name, $key, $salt, $bank, $endpoint, $status, $acNumber, $clientId, $balance, $useKeyVar, $useSaltVar)
    {
        return [
            'name' => $name,
            'key' => $useKeyVar ? '@key (auto-generated)' : $key,
            'salt' => $useSaltVar ? '@salt (auto-generated)' : $salt,
            'bank' => $bank,
            'endpoint' => $endpoint,
            'status' => $status ? 'Active (1)' : 'Inactive (0)',
            'ac_number' => $acNumber,
            'client_id' => $clientId === 'NULL' ? 'NULL' : $clientId,
            'balance' => $balance,
        ];
    }

    private function getVirtualColumns($userId, $accountName, $name, $bank, $ifsc, $fullAccNo, $vpa, $tranId, $serialNo, $status, $debitAccount, $accountId)
    {
        return [
            'user_id' => $userId,
            'account_name' => $accountName,
            'name' => $name,
            'bank' => $bank,
            'ifsc' => $ifsc === 'NULL' ? 'NULL' : $ifsc,
            'full_acc_no' => $fullAccNo,
            'vpa' => $vpa === 'NULL' ? 'NULL' : $vpa,
            'tran_id' => $tranId,
            'serial_no' => $serialNo,
            'status' => $status ? 'Active (1)' : 'Inactive (0)',
            'debit_account' => $debitAccount,
            'account_id' => $accountId,
        ];
    }
}