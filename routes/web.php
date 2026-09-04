<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\ChainValidatorController;
use App\Http\Controllers\HashToolboxController;
use App\Http\Controllers\JwtController;
use App\Http\Controllers\HmacController;
use App\Http\Controllers\ApiTesterController;
use App\Http\Controllers\Base64Controller;

Route::get('/', function () {
    return redirect('/dashboard');
});

Route::get('/dashboard', [DashboardController::class, 'index']);
Route::get('/theme/{theme}', [DashboardController::class, 'theme']);
Route::post('/dashboard/numlock/start', [DashboardController::class, 'startNumLockToggle']);
Route::post('/dashboard/numlock/stop', [DashboardController::class, 'stopNumLockToggle']);
Route::get('/dashboard/numlock/status', [DashboardController::class, 'getNumLockStatus']);

Route::group(['prefix' => 'certificate'], function () {
    Route::get('/', [CertificateController::class, 'index']);
    Route::post('/parse', [CertificateController::class, 'parse']);
    Route::get('/download/{action}', [CertificateController::class, 'download']);
    Route::post('/check-domain', [CertificateController::class, 'checkDomain'])->name('certificate.check-domain');
});

Route::group(['prefix' => 'chain-validator'], function () {
    Route::get('/', [ChainValidatorController::class, 'index']);
    Route::post('/parse', [ChainValidatorController::class, 'parse']);
    Route::get('/download/{id}', [ChainValidatorController::class, 'download']);
    Route::get('/download-bundle', [ChainValidatorController::class, 'downloadBundle'])->name('chain-validator.download.bundle');
});

Route::group(['prefix' => 'hash-toolbox'], function () {
    Route::get('/', [HashToolboxController::class, 'index']);
    Route::post('/hash-text', [HashToolboxController::class, 'hashText']);
    Route::post('/hash-file', [HashToolboxController::class, 'hashFile']);
    Route::post('/aes', [HashToolboxController::class, 'aesAction']);
    Route::post('/password', [HashToolboxController::class, 'generatePassword']);
    Route::post('/generate-bcrypt', [HashToolboxController::class, 'generateBcrypt'])->name('generate.bcrypt');
});

Route::group(['prefix' => 'jwt'], function () {
    Route::get('/', [JwtController::class, 'index']);
    Route::post('/analyze', [JwtController::class, 'analyze']);
});

Route::group(['prefix' => 'hmac'], function () {
    Route::get('/', [HmacController::class, 'index']);
    Route::post('/generate', [HmacController::class, 'generate']);
});

Route::group(['prefix' => 'api-tester'], function () {
    Route::get('/', [ApiTesterController::class, 'index'])->name('api-tester.index');
    Route::post('/send', [ApiTesterController::class, 'send'])->name('api-tester.send');
});

Route::group(['prefix' => 'base64'], function () {
    Route::get('/', [Base64Controller::class, 'index']);
    Route::post('/encode-text', [Base64Controller::class, 'encodeText']);
    Route::post('/decode-text', [Base64Controller::class, 'decodeText']);
    Route::post('/encode-file', [Base64Controller::class, 'encodeFile']);
    Route::post('/decode-file', [Base64Controller::class, 'decodeFile']);
});

use App\Http\Controllers\AIChatController;

Route::prefix('api/chat')->group(function () {
    Route::post('/send', [AIChatController::class, 'chat'])->name('api.chat.send');
    Route::get('/conversations', [AIChatController::class, 'getConversations']);
    Route::post('/clear', [AIChatController::class, 'clearConversation']);
    Route::post('/delete-message', [AIChatController::class, 'deleteMessage']);
    Route::get('/export', [AIChatController::class, 'exportConversation']);
    Route::get('/suggest', [AIChatController::class, 'suggestPrompts']);
});

Route::get('/test-ai', [AIChatController::class, 'testConnection']);

use App\Http\Controllers\CommandStorageController;

Route::get('/command-storage', [CommandStorageController::class, 'index'])->name('command-storage.index');
Route::get('/command-storage/{id}', [CommandStorageController::class, 'show']);
Route::post('/command-storage/{id}/favorite', [CommandStorageController::class, 'toggleFavorite']);
Route::post('/command-storage/{id}/increment', [CommandStorageController::class, 'incrementUsage']);
Route::get('/command-storage/categories/list', [CommandStorageController::class, 'getCategories']);


use App\Http\Controllers\SshController;
// SSH Connection Manager Routes
Route::prefix('ssh')->group(function () {
    Route::get('/', [SshController::class, 'index'])->name('ssh.index');
    Route::get('/list', [SshController::class, 'listServers'])->name('ssh.list');
    Route::post('/add', [SshController::class, 'addHost'])->name('ssh.add');
    Route::put('/update/{originalHost}', [SshController::class, 'updateHost'])->name('ssh.update');
    Route::post('/test', [SshController::class, 'testConnectionWithKey'])->name('ssh.test');
    Route::post('/record', [SshController::class, 'recordConnection'])->name('ssh.record');
    Route::get('/get-server/{host}', [SshController::class, 'getServer'])->name('ssh.get-server');
    Route::get('/command/{host}', [SshController::class, 'getSshCommand'])->name('ssh.command');
    Route::get('/diagnose/{host}', [SshController::class, 'diagnoseServer'])->name('ssh.diagnose');
    Route::get('/proxy-health/{host}', [SshController::class, 'getProxyServerHealth'])->name('ssh.proxy-health');
    Route::delete('/delete/{host}', [SshController::class, 'deleteServer'])->name('ssh.delete');
    Route::post('/test-connectivity', [SshController::class, 'testConnectivity'])->name('ssh.test-connectivity');
    Route::get('/ssh-key-files', [SshController::class, 'getSshKeyFiles'])->name('ssh.key-files');
    Route::post('/list-projects', [SshController::class, 'listProjects'])->name('ssh.list-projects');
    Route::post('/open-vscode', [SshController::class, 'openInVSCode'])->name('ssh.open-vscode');
    Route::post('/apache-config', [SshController::class, 'getApacheConfig'])->name('ssh.apache-config');

    // SSL installation (Let's Encrypt / Paid SSL)
    Route::post('/ssl/install-letsencrypt', [SshController::class, 'installLetsEncryptSsl'])->name('ssh.ssl.letsencrypt');
    Route::post('/ssl/install-paid', [SshController::class, 'installPaidSsl'])->name('ssh.ssl.paid');

    // Project Explorer (remote file browser)
    Route::post('/explorer/list', [SshController::class, 'exploreDirectory'])->name('ssh.explorer.list');
    Route::get('/explorer/file', [SshController::class, 'exploreFile'])->name('ssh.explorer.file');
    Route::get('/explorer/download', [SshController::class, 'exploreDownload'])->name('ssh.explorer.download');
    Route::get('/explorer/zip', [SshController::class, 'exploreZip'])->name('ssh.explorer.zip');
    Route::post('/explorer/edit', [SshController::class, 'exploreEditRead'])->name('ssh.explorer.edit');
    Route::post('/explorer/save', [SshController::class, 'exploreSave'])->name('ssh.explorer.save');
    Route::post('/explorer/rename', [SshController::class, 'exploreRename'])->name('ssh.explorer.rename');
    Route::post('/explorer/mkdir', [SshController::class, 'exploreMkdir'])->name('ssh.explorer.mkdir');
    Route::post('/explorer/touch', [SshController::class, 'exploreTouch'])->name('ssh.explorer.touch');
    Route::post('/explorer/delete', [SshController::class, 'exploreDelete'])->name('ssh.explorer.delete');
    Route::post('/explorer/chmod', [SshController::class, 'exploreChmod'])->name('ssh.explorer.chmod');
    Route::post('/explorer/duplicate', [SshController::class, 'exploreDuplicate'])->name('ssh.explorer.duplicate');
    Route::post('/explorer/upload', [SshController::class, 'exploreUpload'])->name('ssh.explorer.upload');

    Route::get('/available-keys', [SshController::class, 'getAvailableKeys']);
    Route::post('/upload-key', [SshController::class, 'uploadPemKey']);
    Route::post('/toggle-favorite', [SshController::class, 'toggleFavorite']);
    Route::get('/get-favorites', [SshController::class, 'getFavorites']);
    Route::post('/test-with-key', [SshController::class, 'testConnectionWithKey']);
    Route::get('/debug-keys', [SshController::class, 'debugKeys']);

    Route::get('/export', [SshController::class, 'exportServers'])->name('ssh.export');
    Route::post('/import', [SshController::class, 'importServers'])->name('ssh.import');
    Route::get('/import-sample', [SshController::class, 'downloadSampleJson'])->name('ssh.import.sample');
    Route::post('/fix-pem-permissions', [SshController::class, 'fixPemPermissions'])->name('ssh.fix.permissions');
    Route::post('/fix-ssh-agent', [SshController::class, 'fixSshAgentAndKeys'])->name('ssh.fix.agent');
    Route::post('/fix-server-config/{host}', [SshController::class, 'fixServerSshConfig'])->name('ssh.fix.server.config');
    Route::post('/fix-all-connections', [SshController::class, 'fixAllServerConnections'])->name('ssh.fix.all');
    Route::post('/add-public-key', [SshController::class, 'addPublicKeyToServer'])->name('ssh.add.public.key');
    Route::post('/ns-lookup', [SshController::class, 'performNsLookup'])->name('ssh.ns.lookup');
    
    // Database Credential Import from SSH
    Route::post('/import-db-single', [SshController::class, 'importDbFromServer'])->name('ssh.import-db.single');
    Route::get('/import-db-status', [SshController::class, 'getImportPendingCount'])->name('ssh.import-db.status');
    Route::get('/list-with-domains', [SshController::class, 'getServersWithDomains'])->name('ssh.list-with-domains');
    
    // phpMyAdmin Info
    Route::get('/phpmyadmin-info/{id}', [SshController::class, 'getPhpMyAdminInfo'])->name('ssh.phpmyadmin.info');
});


use App\Http\Controllers\AdminCredentialController;
use App\Http\Controllers\AutoLoginController;

Route::prefix('admin-credentials')->group(function () {

    Route::get('/', [AdminCredentialController::class, 'index'])->name('admin-credentials.index');

    Route::get('/create', [AdminCredentialController::class, 'create'])->name('admin-credentials.create');

    Route::post('/', [AdminCredentialController::class, 'store'])->name('admin-credentials.store');

    Route::post('/credential/{credentialId}/default', [AdminCredentialController::class, 'setDefaultCredential'])->name('admin-credentials.set-default');

    Route::put('/credential/{credentialId}', [AdminCredentialController::class, 'updateCredential'])->name('admin-credentials.update-credential');

    Route::delete('/credential/{credentialId}', [AdminCredentialController::class, 'deleteCredential'])->name('admin-credentials.delete-credential');

    Route::get('/credential/{id}', [AdminCredentialController::class, 'getCredential'])->name('admin-credentials.get-credential');

    Route::get('/auto-login-page/{credentialId}', [AutoLoginController::class, 'autoLoginPage'])->name('admin-credentials.auto-login-page');

    Route::get('/auto-login/{credentialId}', [AutoLoginController::class, 'login'])->name('admin-credentials.auto-login');

    Route::get('/copy/{credentialId}', [AdminCredentialController::class, 'copyCredentials'])->name('admin-credentials.copy');

    Route::get('/{id}/edit', [AdminCredentialController::class, 'edit'])->name('admin-credentials.edit');

    Route::put('/{id}', [AdminCredentialController::class, 'update'])->name('admin-credentials.update');

    Route::delete('/{id}', [AdminCredentialController::class, 'destroy'])->name('admin-credentials.destroy');

    Route::post('/{dashboardId}/credential', [AdminCredentialController::class, 'addCredential'])->name('admin-credentials.add-credential');

    Route::post('/{id}/favorite', [AdminCredentialController::class, 'toggleFavorite'])->name('admin-credentials.toggle-favorite');
});

Route::get('/auto-login', [AutoLoginController::class, 'autoLogin']);
Route::get('/auto-login/{credentialId}', [AutoLoginController::class, 'login'])->name('auto.login');
Route::get('/auto-dashboard/{credentialId}', [AutoLoginController::class, 'dashboard'])->name('auto.dashboard');



use App\Http\Controllers\DatabaseController;

Route::prefix('database')->name('database.')->group(function () {
    Route::get('/', [DatabaseController::class, 'index'])->name('index');
    Route::post('/store', [DatabaseController::class, 'store'])->name('store');
    Route::put('/update/{id}', [DatabaseController::class, 'update'])->name('update');
    Route::delete('/delete/{id}', [DatabaseController::class, 'destroy'])->name('delete');
    Route::post('/test-connection', [DatabaseController::class, 'testConnection'])->name('test.connection');
    Route::get('/db-health/{id}', [DatabaseController::class, 'getDbHealth'])->name('db.health');
    Route::get('/tables/{id}', [DatabaseController::class, 'getTables'])->name('tables');
    Route::get('/table-structure/{id}/{table}', [DatabaseController::class, 'getTableStructure'])->name('table.structure');
    Route::post('/run-query', [DatabaseController::class, 'runQuery'])->name('run.query');
    Route::post('/truncate-table', [DatabaseController::class, 'truncateTable'])->name('truncate.table');
    Route::post('/add-column', [DatabaseController::class, 'addColumn'])->name('add.column');
    Route::post('/drop-column', [DatabaseController::class, 'dropColumn'])->name('drop.column');
    Route::post('/modify-column', [DatabaseController::class, 'modifyColumn'])->name('modify.column');
    Route::get('/get/{id}', [DatabaseController::class, 'getConnection'])->name('get');
    Route::post('/set-default/{id}', [DatabaseController::class, 'setDefault'])->name('set.default');
    Route::post('/test-connection-dynamic', [DatabaseController::class, 'testConnectionDynamic'])->name('test.dynamic');
    Route::post('/start-tunnel', [DatabaseController::class, 'startTunnel']);
    Route::get('/running-queries/{id}', [DatabaseController::class, 'getRunningQueries']);
    Route::post('/kill-query', [DatabaseController::class, 'killQuery']);
    Route::post('/kill-long-running-queries', [DatabaseController::class, 'killLongRunningQueries']);
    Route::get('/active-connections/{id}', [DatabaseController::class, 'getActiveConnections']);
    Route::post('/kill-idle-connections', [DatabaseController::class, 'killIdleConnections']);
    Route::post('/kill-connections-by-user', [DatabaseController::class, 'killConnectionsByUser']);
    Route::match(['GET', 'POST'], '/export-data', [DatabaseController::class, 'exportData'])->name('database.export.data');
    Route::get('/table-details/{id}/{table}', [DatabaseController::class, 'getTableDetails'])->name('database.table.details');
    Route::get('/table-data/{id}/{table}', [DatabaseController::class, 'getTableData'])->name('database.table.data');
});


use App\Http\Controllers\SslMatcherController;

// SSL Matcher Routes
Route::prefix('ssl-matcher')->name('ssl-matcher.')->group(function () {
    Route::get('/', [SslMatcherController::class, 'index'])->name('index');
    Route::post('/match-cert-key', [SslMatcherController::class, 'matchCertKey'])->name('match.cert.key');
    Route::post('/match-cert-public', [SslMatcherController::class, 'matchCertPublicKey'])->name('match.cert.public');
    Route::post('/match-certs', [SslMatcherController::class, 'matchCerts'])->name('match.certs');
    Route::post('/match-pub-priv', [SslMatcherController::class, 'matchPublicKeyPrivateKey'])->name('match.pub.priv');
    Route::get('/commands', [SslMatcherController::class, 'getCommands'])->name('commands');
    Route::post('/decrypt-key', [SslMatcherController::class, 'decryptKey'])->name('decrypt.key');
    Route::post('/validate-csr', [SSLMatcherController::class, 'validateCSR']);
    Route::post('/match-csr-key', [SSLMatcherController::class, 'matchCSRWithKey']);
    Route::post('/convert-format', [SSLMatcherController::class, 'convertFormat']);
    Route::post('/match-csr-key-cert', [SslMatcherController::class, 'matchCSRKeyCert'])->name('match-csr-key-cert');
});


use App\Http\Controllers\SystemMonitorController;

// System Monitor Routes
Route::prefix('system-monitor')->group(function () {
    Route::get('/', [SystemMonitorController::class, 'index'])->name('system-monitor.index');
    Route::get('/data', [SystemMonitorController::class, 'getData'])->name('system-monitor.data');
    Route::post('/kill-process', [SystemMonitorController::class, 'killProcess'])->name('system-monitor.kill-process');
    Route::post('/kill-multiple', [SystemMonitorController::class, 'killMultipleProcesses'])->name('system-monitor.kill-multiple');
    Route::post('/kill-application', [SystemMonitorController::class, 'killApplication'])->name('system-monitor.kill-application');
    Route::post('/clear-low-priority', [SystemMonitorController::class, 'clearLowPriority'])->name('system-monitor.clear-low-priority');
    Route::post('/free-memory', [SystemMonitorController::class, 'freeMemory'])->name('system-monitor.free-memory');
    Route::post('/kill-low-priority-only', [SystemMonitorController::class, 'killLowPriorityOnly']);
    Route::post('/clear-chrome-low-priority', [SystemMonitorController::class, 'clearChromeLowPriority']);
});
