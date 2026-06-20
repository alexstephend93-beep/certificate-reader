<?php

namespace Database\Seeders;

use App\Models\Command;
use Illuminate\Database\Seeder;

class CommandSeeder extends Seeder
{
    public function run()
    {
        $commands = [
            // ============ 1. PROXY & APACHE CONFIGURATION ============
            [
                'name' => 'Fix Directory Permissions for Current User',
                'category' => 'apache',
                'sub_category' => 'permissions',
                'command' => 'sudo chown -R $USER:$USER .',
                'description' => 'Changes ownership of current directory and all subdirectories to the current user. Useful when working in proxy server environments.',
                'alternate_commands' => json_encode([
                    'sudo chown -R www-data:www-data .',
                    'sudo chmod -R 755 .'
                ]),
                'example_usage' => 'cd /var/www/project && sudo chown -R $USER:$USER .',
                'notes' => 'Run this when you get permission denied errors. Replace $USER with actual username if needed.',
                'tags' => 'proxy,permission,chown,ownership',
                'os' => 'linux',
                'danger_level' => 'medium',
                'icon' => 'folder-symlink'
            ],
            [
                'name' => 'Edit Apache Virtual Host Configuration',
                'category' => 'apache',
                'sub_category' => 'config',
                'command' => 'sudo nano /etc/apache2/sites-enabled/000-default.conf',
                'description' => 'Edit the main Apache virtual host configuration file for proxy settings.',
                'alternate_commands' => json_encode([
                    'sudo nano /etc/apache2/sites-available/000-default.conf',
                    'sudo vim /etc/apache2/sites-enabled/000-default-le-ssl.conf'
                ]),
                'example_usage' => 'sudo nano /etc/apache2/sites-enabled/000-default.conf',
                'notes' => 'Always backup config before editing. Use -le-ssl.conf for SSL configurations.',
                'tags' => 'apache,proxy,virtualhost,config',
                'os' => 'linux',
                'danger_level' => 'high',
                'icon' => 'file-text'
            ],
            [
                'name' => 'Restart Apache Server',
                'category' => 'apache',
                'sub_category' => 'service',
                'command' => 'sudo systemctl restart apache2',
                'description' => 'Restarts Apache web server to apply configuration changes.',
                'alternate_commands' => json_encode([
                    'sudo service apache2 restart',
                    'sudo systemctl reload apache2',
                    'sudo apache2ctl graceful'
                ]),
                'example_usage' => 'sudo systemctl restart apache2',
                'notes' => 'Use "reload" instead of "restart" to avoid downtime.',
                'tags' => 'apache,restart,service,systemctl',
                'os' => 'linux',
                'danger_level' => 'medium',
                'icon' => 'arrow-repeat'
            ],
            [
                'name' => 'Check Apache Configuration Syntax',
                'category' => 'apache',
                'sub_category' => 'debug',
                'command' => 'sudo apachectl configtest',
                'description' => 'Tests Apache configuration files for syntax errors before restarting.',
                'alternate_commands' => json_encode([
                    'sudo apache2ctl -t',
                    'sudo apache2ctl configtest'
                ]),
                'example_usage' => 'sudo apachectl configtest',
                'notes' => 'ALWAYS run this before restarting Apache to avoid server crash!',
                'tags' => 'apache,config,test,syntax',
                'os' => 'linux',
                'danger_level' => 'low',
                'icon' => 'check-circle'
            ],
            [
                'name' => 'List Apache Enabled Sites',
                'category' => 'apache',
                'sub_category' => 'config',
                'command' => 'ls /etc/apache2/sites-enabled/',
                'description' => 'Lists all enabled Apache virtual host configurations.',
                'alternate_commands' => json_encode([
                    'ls -la /etc/apache2/sites-available/',
                    'apache2ctl -S'
                ]),
                'example_usage' => 'cd /etc/apache2/sites-enabled/ && ls',
                'notes' => 'Use -la to see permissions and symlinks.',
                'tags' => 'apache,list,sites,enabled',
                'os' => 'linux',
                'danger_level' => 'low',
                'icon' => 'list'
            ],

            // ============ 2. SSL CERTIFICATE MANAGEMENT ============
            [
                'name' => 'Install Certbot for SSL',
                'category' => 'ssl',
                'sub_category' => 'installation',
                'command' => 'sudo snap install certbot --classic',
                'description' => 'Installs Certbot SSL certificate management tool using Snap.',
                'alternate_commands' => json_encode([
                    'sudo apt install certbot python3-certbot-apache',
                    'sudo apt install certbot python3-certbot-nginx'
                ]),
                'example_usage' => 'sudo snap install certbot --classic',
                'notes' => 'Use --apache or --nginx flag based on your web server.',
                'tags' => 'ssl,certbot,install,https',
                'os' => 'linux',
                'danger_level' => 'low',
                'icon' => 'shield-lock'
            ],
            [
                'name' => 'Generate SSL Certificate with Certbot',
                'category' => 'ssl',
                'sub_category' => 'generation',
                'command' => 'sudo certbot certonly --apache --rsa-key-size 4096',
                'description' => 'Generates free SSL certificate using Let\'s Encrypt with 4096-bit RSA key.',
                'alternate_commands' => json_encode([
                    'sudo certbot --apache -d example.com',
                    'sudo certbot certonly --standalone -d example.com',
                    'sudo certbot --nginx -d example.com'
                ]),
                'example_usage' => 'sudo certbot certonly --apache --rsa-key-size 4096',
                'notes' => 'Select domain number from the list. Key size 4096 is more secure but slower.',
                'tags' => 'ssl,certbot,certificate,letsencrypt',
                'os' => 'linux',
                'danger_level' => 'low',
                'icon' => 'shield-check'
            ],
            [
                'name' => 'Generate Private Key and CSR',
                'category' => 'ssl',
                'sub_category' => 'generation',
                'command' => 'openssl req -new -newkey rsa:4096 -nodes -keyout domain.key -out domain.csr',
                'description' => 'Generates a new private key and Certificate Signing Request (CSR) for SSL certificate.',
                'alternate_commands' => json_encode([
                    'openssl req -new -newkey rsa:2048 -nodes -keyout domain.key -out domain.csr',
                    'openssl genrsa -aes128 -out domain.key 2048'
                ]),
                'example_usage' => 'mkdir ssl_2024 && cd ssl_2024 && openssl req -new -newkey rsa:4096 -nodes -keyout mydomain.key -out mydomain.csr',
                'notes' => 'Will prompt for company details. Common Name must be the domain name.',
                'tags' => 'ssl,openssl,csr,privatekey',
                'os' => 'all',
                'danger_level' => 'low',
                'icon' => 'key'
            ],
            [
                'name' => 'View CSR Content',
                'category' => 'ssl',
                'sub_category' => 'view',
                'command' => 'cat domain.csr',
                'description' => 'Displays the content of Certificate Signing Request file.',
                'alternate_commands' => json_encode([
                    'openssl req -in domain.csr -noout -text',
                    'less domain.csr'
                ]),
                'example_usage' => 'cat mydomain.csr',
                'notes' => 'Copy the output including BEGIN and END lines to submit to CA.',
                'tags' => 'ssl,csr,view,cat',
                'os' => 'all',
                'danger_level' => 'low',
                'icon' => 'file-text'
            ],
            [
                'name' => 'Verify Public and Private Key Match',
                'category' => 'ssl',
                'sub_category' => 'validation',
                'command' => 'openssl x509 -noout -modulus -in certificate.crt | openssl md5',
                'description' => 'Checks if public certificate and private key match by comparing their modulus hashes.',
                'alternate_commands' => json_encode([
                    'openssl rsa -noout -modulus -in private.key | openssl md5',
                    'openssl req -noout -modulus -in domain.csr | openssl md5'
                ]),
                'example_usage' => '# For certificate and private key match check:',
                'notes' => 'Both commands should output the same hash value.',
                'tags' => 'ssl,verify,match,certificate',
                'os' => 'all',
                'danger_level' => 'low',
                'icon' => 'check-circle'
            ],
            [
                'name' => 'Create PFX/PKCS12 File',
                'category' => 'ssl',
                'sub_category' => 'conversion',
                'command' => 'sudo openssl pkcs12 -export -in public.pem -inkey private.pem -out certificate.pfx -passout pass:password',
                'description' => 'Converts PEM certificate and private key to PKCS12/PFX format for Windows/IIS.',
                'alternate_commands' => json_encode([
                    'openssl pkcs12 -export -in certificate.crt -inkey private.key -out certificate.pfx'
                ]),
                'example_usage' => 'sudo openssl pkcs12 -export -in server.pem -inkey server.key -out server.pfx -passout pass:MyPassword123',
                'notes' => 'Remember the password you set, it will be needed to use the PFX file.',
                'tags' => 'ssl,pfx,pkcs12,conversion',
                'os' => 'all',
                'danger_level' => 'low',
                'icon' => 'box'
            ],
            [
                'name' => 'View PFX File Content',
                'category' => 'ssl',
                'sub_category' => 'view',
                'command' => 'sudo openssl pkcs12 -info -in certificate.pfx -passin pass:password',
                'description' => 'Displays information about PKCS12/PFX file including certificates and keys.',
                'alternate_commands' => json_encode([
                    'openssl pkcs12 -in certificate.pfx -nodes -out output.pem'
                ]),
                'example_usage' => 'sudo openssl pkcs12 -info -in server.pfx -passin pass:MyPassword123',
                'notes' => 'Use correct password to view the contents.',
                'tags' => 'ssl,pfx,view,info',
                'os' => 'all',
                'danger_level' => 'low',
                'icon' => 'eye'
            ],
            [
                'name' => 'Extract Certificates from Bundle',
                'category' => 'ssl',
                'sub_category' => 'extraction',
                'command' => 'csplit -f cert- -b %02d.pem bundle.ca-bundle \'/-----BEGIN CERTIFICATE-----/\' \'{\*}\'',
                'description' => 'Splits a certificate bundle file into individual certificate files.',
                'alternate_commands' => json_encode([
                    'awk \'/BEGIN CERTIFICATE/,/END CERTIFICATE/ {print > "cert" NR ".pem"}\' bundle.crt'
                ]),
                'example_usage' => 'csplit -f cert- -b %02d.pem gd_bundle-g2-g1.crt \'/-----BEGIN CERTIFICATE-----/\' \'{\*}\'',
                'notes' => 'Creates files like cert-00.pem, cert-01.pem, etc.',
                'tags' => 'ssl,bundle,extract,csplit',
                'os' => 'linux',
                'danger_level' => 'low',
                'icon' => 'files'
            ],
            [
                'name' => 'Decrypt Encrypted Private Key',
                'category' => 'ssl',
                'sub_category' => 'encryption',
                'command' => 'openssl rsa -in encrypted.key -out decrypted.key',
                'description' => 'Removes password encryption from a private key file.',
                'alternate_commands' => json_encode([
                    'openssl rsa -in encrypted.key -out decrypted.key -passin pass:password'
                ]),
                'example_usage' => 'openssl rsa -in private_encrypted.key -out private_decrypted.key',
                'notes' => 'Will prompt for password if key is encrypted.',
                'tags' => 'ssl,decrypt,privatekey,openssl',
                'os' => 'all',
                'danger_level' => 'medium',
                'icon' => 'unlock'
            ],
            [
                'name' => 'Check Certificate Expiry Date',
                'category' => 'ssl',
                'sub_category' => 'validation',
                'command' => 'openssl x509 -in certificate.crt -noout -dates',
                'description' => 'Shows the validity period (notBefore and notAfter) of an SSL certificate.',
                'alternate_commands' => json_encode([
                    'openssl x509 -in certificate.crt -noout -enddate',
                    'echo | openssl s_client -servername example.com -connect example.com:443 2>/dev/null | openssl x509 -noout -dates'
                ]),
                'example_usage' => 'openssl x509 -in server.crt -noout -dates',
                'notes' => 'Check before certificate expires to avoid downtime.',
                'tags' => 'ssl,expiry,check,validate',
                'os' => 'all',
                'danger_level' => 'low',
                'icon' => 'calendar'
            ],

            // ============ 3. PHP & COMPOSER COMMANDS ============
            [
                'name' => 'Install PHP with Extensions (Ubuntu)',
                'category' => 'php',
                'sub_category' => 'installation',
                'command' => 'sudo apt install -y php8.2-cli php8.2-fpm php8.2-mysql php8.2-xml php8.2-mbstring php8.2-curl php8.2-zip php8.2-intl php8.2-gd',
                'description' => 'Installs PHP 8.2 with common extensions needed for Laravel and web applications.',
                'alternate_commands' => json_encode([
                    'sudo apt install -y php8.1-cli php8.1-common php8.1-mysql php8.1-zip php8.1-gd php8.1-mbstring php8.1-curl php8.1-xml php8.1-bcmath',
                    'sudo apt install -y php8.3-cli php8.3-fpm php8.3-mysql'
                ]),
                'example_usage' => 'sudo apt update && sudo apt install -y php8.2-cli php8.2-fpm php8.2-mysql',
                'notes' => 'Adjust PHP version number based on your requirement.',
                'tags' => 'php,install,extensions,ubuntu',
                'os' => 'linux',
                'danger_level' => 'low',
                'icon' => 'filetype-php'
            ],
            [
                'name' => 'Find PHP.ini Location',
                'category' => 'php',
                'sub_category' => 'config',
                'command' => 'php -i | grep "Loaded Configuration File"',
                'description' => 'Shows the location of the loaded php.ini configuration file.',
                'alternate_commands' => json_encode([
                    'php --ini',
                    'find /etc/php -name php.ini'
                ]),
                'example_usage' => 'php -i | grep "Loaded Configuration File"',
                'notes' => 'Different SAPIs (CLI, Apache, FPM) may use different php.ini files.',
                'tags' => 'php,phpini,config,location',
                'os' => 'all',
                'danger_level' => 'low',
                'icon' => 'file-text'
            ],
            [
                'name' => 'Install PHP MySQL Extension',
                'category' => 'php',
                'sub_category' => 'extensions',
                'command' => 'sudo apt install php-mysql',
                'description' => 'Installs PHP MySQL extension to fix "could not find driver" error.',
                'alternate_commands' => json_encode([
                    'sudo apt install php7.4-mysql',
                    'sudo apt install php8.2-mysql'
                ]),
                'example_usage' => 'sudo apt install php8.2-mysql',
                'notes' => 'Match the PHP version number with your installed PHP version.',
                'tags' => 'php,mysql,driver,extension',
                'os' => 'linux',
                'danger_level' => 'low',
                'icon' => 'database'
            ],
            [
                'name' => 'Switch PHP Version',
                'category' => 'php',
                'sub_category' => 'version',
                'command' => 'sudo update-alternatives --config php',
                'description' => 'Switches between installed PHP versions on Ubuntu system.',
                'alternate_commands' => json_encode([
                    'sudo a2dismod php7.4 && sudo a2enmod php8.2',
                    'sudo service apache2 restart'
                ]),
                'example_usage' => 'sudo update-alternatives --config php',
                'notes' => 'Select the desired PHP version from the list.',
                'tags' => 'php,version,switch,update-alternatives',
                'os' => 'linux',
                'danger_level' => 'medium',
                'icon' => 'arrow-repeat'
            ],
            [
                'name' => 'Install Composer with Platform Ignore',
                'category' => 'composer',
                'sub_category' => 'installation',
                'command' => 'composer install --ignore-platform-req=php',
                'description' => 'Installs Composer dependencies ignoring PHP version requirements.',
                'alternate_commands' => json_encode([
                    'composer install --ignore-platform-reqs',
                    'composer update --ignore-platform-req=php'
                ]),
                'example_usage' => 'composer install --ignore-platform-req=php',
                'notes' => 'Useful when PHP version mismatch occurs on server.',
                'tags' => 'composer,install,platform,ignore',
                'os' => 'all',
                'danger_level' => 'medium',
                'icon' => 'box'
            ],
            [
                'name' => 'Install Composer Globally',
                'category' => 'composer',
                'sub_category' => 'installation',
                'command' => 'sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer',
                'description' => 'Installs Composer globally so it can be run from any directory.',
                'alternate_commands' => json_encode([
                    'curl -sS https://getcomposer.org/installer | php',
                    'sudo mv composer.phar /usr/local/bin/composer'
                ]),
                'example_usage' => 'sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer',
                'notes' => 'Run after downloading composer-setup.php',
                'tags' => 'composer,install,global',
                'os' => 'all',
                'danger_level' => 'low',
                'icon' => 'box'
            ],

            // ============ 4. LARAVEL COMMANDS ============
            [
                'name' => 'Start Laravel Development Server',
                'category' => 'laravel',
                'sub_category' => 'development',
                'command' => 'php artisan serve --host 192.168.1.102 --port=8000',
                'description' => 'Starts Laravel development server on specific IP and port.',
                'alternate_commands' => json_encode([
                    'php artisan serve',
                    'php artisan serve --host=0.0.0.0 --port=8080'
                ]),
                'example_usage' => 'php artisan serve --host 0.0.0.0 --port=8000',
                'notes' => 'Use 0.0.0.0 to allow external access.',
                'tags' => 'laravel,serve,development,server',
                'os' => 'all',
                'danger_level' => 'low',
                'icon' => 'server'
            ],
            [
                'name' => 'Kill PHP Server by Port',
                'category' => 'laravel',
                'sub_category' => 'debug',
                'command' => 'sudo lsof -i :8080',
                'description' => 'Finds which process is using a specific port.',
                'alternate_commands' => json_encode([
                    'sudo netstat -tulpn | grep :8080',
                    'sudo ss -tulpn | grep :8080'
                ]),
                'example_usage' => 'sudo lsof -i :8000',
                'notes' => 'Use the PID from output to kill: kill -9 PID',
                'tags' => 'laravel,port,kill,process',
                'os' => 'linux',
                'danger_level' => 'medium',
                'icon' => 'x-circle'
            ],
            [
                'name' => 'Get Project Directory from Port',
                'category' => 'laravel',
                'sub_category' => 'debug',
                'command' => 'sudo ls -l /proc/PID/cwd',
                'description' => 'Shows the working directory of a process by its PID.',
                'alternate_commands' => json_encode([
                    'pwdx PID',
                    'readlink /proc/PID/cwd'
                ]),
                'example_usage' => '# First find PID: sudo lsof -i :8080\n# Then: sudo ls -l /proc/187226/cwd',
                'notes' => 'Replace PID with the actual process ID from lsof command.',
                'tags' => 'laravel,process,directory,pwd',
                'os' => 'linux',
                'danger_level' => 'low',
                'icon' => 'folder'
            ],
            [
                'name' => 'Fix Laravel Storage Permissions',
                'category' => 'laravel',
                'sub_category' => 'permissions',
                'command' => 'sudo chown -R www-data:www-data storage bootstrap/cache && sudo chmod -R 775 storage bootstrap/cache',
                'description' => 'Fixes storage and bootstrap/cache permissions for Laravel application.',
                'alternate_commands' => json_encode([
                    'sudo chgrp -R www-data storage bootstrap/cache',
                    'sudo chmod -R ug+rwx storage bootstrap/cache'
                ]),
                'example_usage' => 'cd /var/www/project && sudo chown -R www-data:www-data storage bootstrap/cache',
                'notes' => 'Run after deploying Laravel application on server.',
                'tags' => 'laravel,permissions,storage,cache',
                'os' => 'linux',
                'danger_level' => 'medium',
                'icon' => 'folder-symlink'
            ],
            [
                'name' => 'Restart Laravel Queue Workers',
                'category' => 'laravel',
                'sub_category' => 'queue',
                'command' => 'sudo php artisan queue:restart',
                'description' => 'Restarts all queue workers by signaling them to exit gracefully.',
                'alternate_commands' => json_encode([
                    'php artisan queue:work --tries=3',
                    'php artisan queue:listen'
                ]),
                'example_usage' => 'sudo php artisan queue:restart',
                'notes' => 'Run after code changes to apply updates to queue workers.',
                'tags' => 'laravel,queue,restart,worker',
                'os' => 'all',
                'danger_level' => 'low',
                'icon' => 'arrow-repeat'
            ],

            // ============ 5. SUPERVISOR & CRON JOBS ============
            [
                'name' => 'Create Supervisor Job Configuration',
                'category' => 'supervisor',
                'sub_category' => 'configuration',
                'command' => 'sudo nano /etc/supervisor/conf.d/jobname.conf',
                'description' => 'Creates or edits a Supervisor configuration file for process monitoring.',
                'alternate_commands' => json_encode([
                    'sudo vim /etc/supervisor/conf.d/jobname.conf'
                ]),
                'example_usage' => 'sudo nano /etc/supervisor/conf.d/laravel-worker.conf',
                'notes' => 'See template in notes for proper configuration format.',
                'tags' => 'supervisor,config,job,process',
                'os' => 'linux',
                'danger_level' => 'medium',
                'icon' => 'file-text'
            ],
            [
                'name' => 'Reload Supervisor Configuration',
                'category' => 'supervisor',
                'sub_category' => 'management',
                'command' => 'sudo supervisorctl reread && sudo supervisorctl update',
                'description' => 'Reloads Supervisor configuration and applies changes.',
                'alternate_commands' => json_encode([
                    'sudo supervisorctl reload',
                    'sudo service supervisor restart'
                ]),
                'example_usage' => 'sudo supervisorctl reread && sudo supervisorctl update',
                'notes' => 'Run after adding or modifying supervisor config files.',
                'tags' => 'supervisor,reload,update,config',
                'os' => 'linux',
                'danger_level' => 'medium',
                'icon' => 'arrow-repeat'
            ],
            [
                'name' => 'Start Supervisor Job',
                'category' => 'supervisor',
                'sub_category' => 'management',
                'command' => 'sudo supervisorctl start jobname:*',
                'description' => 'Starts a Supervisor managed process or group.',
                'alternate_commands' => json_encode([
                    'sudo supervisorctl start jobname',
                    'sudo supervisorctl start all'
                ]),
                'example_usage' => 'sudo supervisorctl start laravel-worker:*',
                'notes' => 'Use :* to start all processes in a group.',
                'tags' => 'supervisor,start,job,process',
                'os' => 'linux',
                'danger_level' => 'low',
                'icon' => 'play-fill'
            ],
            [
                'name' => 'Check Supervisor Status',
                'category' => 'supervisor',
                'sub_category' => 'management',
                'command' => 'sudo supervisorctl status',
                'description' => 'Shows status of all Supervisor managed processes.',
                'alternate_commands' => json_encode([
                    'sudo supervisorctl status jobname'
                ]),
                'example_usage' => 'sudo supervisorctl status',
                'notes' => 'Shows RUNNING, STOPPED, or FATAL status.',
                'tags' => 'supervisor,status,check',
                'os' => 'linux',
                'danger_level' => 'low',
                'icon' => 'info-circle'
            ],
            [
                'name' => 'Stop All Supervisor Jobs',
                'category' => 'supervisor',
                'sub_category' => 'management',
                'command' => 'sudo supervisorctl stop all',
                'description' => 'Stops all Supervisor managed processes.',
                'alternate_commands' => json_encode([
                    'sudo supervisorctl stop jobname'
                ]),
                'example_usage' => 'sudo supervisorctl stop all',
                'notes' => 'Use with caution in production.',
                'tags' => 'supervisor,stop,all,jobs',
                'os' => 'linux',
                'danger_level' => 'high',
                'icon' => 'stop-fill'
            ],
            [
                'name' => 'Edit Crontab',
                'category' => 'cron',
                'sub_category' => 'management',
                'command' => 'crontab -e',
                'description' => 'Opens the crontab file for editing scheduled tasks.',
                'alternate_commands' => json_encode([
                    'sudo crontab -e'
                ]),
                'example_usage' => 'crontab -e',
                'notes' => 'Use sudo for system-wide cron jobs.',
                'tags' => 'cron,crontab,schedule,edit',
                'os' => 'linux',
                'danger_level' => 'medium',
                'icon' => 'calendar'
            ],
            [
                'name' => 'List Crontab Jobs',
                'category' => 'cron',
                'sub_category' => 'management',
                'command' => 'crontab -l',
                'description' => 'Lists all scheduled cron jobs for the current user.',
                'alternate_commands' => json_encode([
                    'sudo crontab -l'
                ]),
                'example_usage' => 'crontab -l',
                'notes' => 'Use -u username to see other users\' crontab.',
                'tags' => 'cron,crontab,list,schedule',
                'os' => 'linux',
                'danger_level' => 'low',
                'icon' => 'list'
            ],
            [
                'name' => 'Add Laravel Scheduler to Crontab',
                'category' => 'cron',
                'sub_category' => 'laravel',
                'command' => '* * * * * cd /var/www/project && php artisan schedule:run >> /dev/null 2>&1',
                'description' => 'Adds Laravel scheduler to crontab to run every minute.',
                'alternate_commands' => json_encode([
                    '* * * * * php /var/www/project/artisan schedule:run >> /dev/null 2>&1'
                ]),
                'example_usage' => '# Add this line to crontab -e\n* * * * * cd /var/www/laravel && php artisan schedule:run >> /dev/null 2>&1',
                'notes' => 'Required for Laravel scheduled tasks to work.',
                'tags' => 'cron,laravel,schedule,artisan',
                'os' => 'linux',
                'danger_level' => 'low',
                'icon' => 'calendar-check'
            ],

            // ============ 6. GIT COMMANDS ============
            [
                'name' => 'Configure Git User Email',
                'category' => 'git',
                'sub_category' => 'config',
                'command' => 'git config user.email "email@example.com"',
                'description' => 'Sets Git user email for the current repository.',
                'alternate_commands' => json_encode([
                    'git config --global user.email "email@example.com"',
                    'git config --local user.email "email@example.com"'
                ]),
                'example_usage' => 'git config user.email "developer@company.com"',
                'notes' => 'Use --global for all repositories, --local for current only.',
                'tags' => 'git,config,email,user',
                'os' => 'all',
                'danger_level' => 'low',
                'icon' => 'envelope'
            ],
            [
                'name' => 'Configure Git User Name',
                'category' => 'git',
                'sub_category' => 'config',
                'command' => 'git config user.name "Your Name"',
                'description' => 'Sets Git user name for the current repository.',
                'alternate_commands' => json_encode([
                    'git config --global user.name "Your Name"'
                ]),
                'example_usage' => 'git config user.name "John Doe"',
                'notes' => 'Name appears in commit history.',
                'tags' => 'git,config,name,user',
                'os' => 'all',
                'danger_level' => 'low',
                'icon' => 'person'
            ],
            [
                'name' => 'Reset Branch to Remote State',
                'category' => 'git',
                'sub_category' => 'reset',
                'command' => 'git reset --hard origin/branch-name',
                'description' => 'Resets current branch to match remote branch exactly.',
                'alternate_commands' => json_encode([
                    'git fetch origin && git reset --hard origin/main',
                    'git clean -fd'
                ]),
                'example_usage' => 'git reset --hard origin/development',
                'notes' => 'WARNING: This discards all local changes!',
                'tags' => 'git,reset,hard,remote',
                'os' => 'all',
                'danger_level' => 'high',
                'icon' => 'exclamation-triangle'
            ],
            [
                'name' => 'Abort Git Rebase',
                'category' => 'git',
                'sub_category' => 'rebase',
                'command' => 'git rebase --abort',
                'description' => 'Aborts an in-progress git rebase operation.',
                'alternate_commands' => json_encode([
                    'git rebase --skip',
                    'git rebase --continue'
                ]),
                'example_usage' => 'git rebase --abort',
                'notes' => 'Use when stuck in rebase loop.',
                'tags' => 'git,rebase,abort',
                'os' => 'all',
                'danger_level' => 'medium',
                'icon' => 'arrow-left'
            ],
            [
                'name' => 'View Git Configuration',
                'category' => 'git',
                'sub_category' => 'config',
                'command' => 'git config --local --list',
                'description' => 'Lists all Git configuration for the current repository.',
                'alternate_commands' => json_encode([
                    'git config --global --list',
                    'git config --list'
                ]),
                'example_usage' => 'git config --local --list',
                'notes' => 'Shows user.email, user.name, and other settings.',
                'tags' => 'git,config,list,view',
                'os' => 'all',
                'danger_level' => 'low',
                'icon' => 'list'
            ],
            [
                'name' => 'Add Git Remote',
                'category' => 'git',
                'sub_category' => 'remote',
                'command' => 'git remote add origin https://github.com/username/repo.git',
                'description' => 'Adds a new remote repository.',
                'alternate_commands' => json_encode([
                    'git remote add origin git@github.com:username/repo.git',
                    'git remote set-url origin new-url.git'
                ]),
                'example_usage' => 'git remote add origin3 https://bitbucket.org/team/project.git',
                'notes' => 'Use different names for multiple remotes (origin, origin2, etc.)',
                'tags' => 'git,remote,add,origin',
                'os' => 'all',
                'danger_level' => 'low',
                'icon' => 'cloud'
            ],

            // ============ 7. SSH & REMOTE SERVER ============
            [
                'name' => 'SSH Connect with PEM Key',
                'category' => 'ssh',
                'sub_category' => 'connection',
                'command' => 'sudo ssh -i /path/to/key.pem ubuntu@server-ip',
                'description' => 'Connects to remote server using PEM private key file.',
                'alternate_commands' => json_encode([
                    'ssh -i ~/.ssh/key.pem user@host',
                    'ssh -i key.pem -p 2222 user@host'
                ]),
                'example_usage' => 'sudo ssh -i /home/user/Documents/SSH/server_common.pem ubuntu@13.235.240.118',
                'notes' => 'Ensure key has proper permissions: chmod 400 key.pem',
                'tags' => 'ssh,connect,pem,key',
                'os' => 'all',
                'danger_level' => 'low',
                'icon' => 'server'
            ],
            [
                'name' => 'Set PEM Key Permissions',
                'category' => 'ssh',
                'sub_category' => 'security',
                'command' => 'sudo chmod -R 400 /path/to/key.pem',
                'description' => 'Sets restrictive permissions on PEM key file for security.',
                'alternate_commands' => json_encode([
                    'chmod 600 ~/.ssh/id_rsa',
                    'chmod 644 ~/.ssh/id_rsa.pub'
                ]),
                'example_usage' => 'sudo chmod -R 400 ~/Documents/SSH/server_common.pem',
                'notes' => 'SSH requires 400 or 600 permissions on private keys.',
                'tags' => 'ssh,chmod,permissions,key',
                'os' => 'linux',
                'danger_level' => 'medium',
                'icon' => 'lock'
            ],

            // ============ 8. NODE.JS & PM2 ============
            [
                'name' => 'Start PM2 Process',
                'category' => 'nodejs',
                'sub_category' => 'pm2',
                'command' => 'pm2 start app.js --name myApp',
                'description' => 'Starts a Node.js application with PM2 process manager.',
                'alternate_commands' => json_encode([
                    'pm2 start npm -- start',
                    'pm2 start ecosystem.config.js'
                ]),
                'example_usage' => 'pm2 start server.js --name api-server',
                'notes' => 'Use --name for easy identification.',
                'tags' => 'nodejs,pm2,start,process',
                'os' => 'all',
                'danger_level' => 'low',
                'icon' => 'play-fill'
            ],
            [
                'name' => 'List PM2 Processes',
                'category' => 'nodejs',
                'sub_category' => 'pm2',
                'command' => 'pm2 list',
                'description' => 'Shows all running PM2 managed processes.',
                'alternate_commands' => json_encode([
                    'pm2 status',
                    'pm2 ls'
                ]),
                'example_usage' => 'pm2 list',
                'notes' => 'Shows process ID, status, and resource usage.',
                'tags' => 'nodejs,pm2,list,status',
                'os' => 'all',
                'danger_level' => 'low',
                'icon' => 'list'
            ],
            [
                'name' => 'Restart PM2 Process',
                'category' => 'nodejs',
                'sub_category' => 'pm2',
                'command' => 'pm2 restart myApp',
                'description' => 'Restarts a specific PM2 managed application.',
                'alternate_commands' => json_encode([
                    'pm2 restart all',
                    'pm2 reload myApp'
                ]),
                'example_usage' => 'pm2 restart api-server',
                'notes' => 'Use reload for zero-downtime restarts.',
                'tags' => 'nodejs,pm2,restart,reload',
                'os' => 'all',
                'danger_level' => 'low',
                'icon' => 'arrow-repeat'
            ],
            [
                'name' => 'Stop PM2 Process',
                'category' => 'nodejs',
                'sub_category' => 'pm2',
                'command' => 'pm2 stop myApp',
                'description' => 'Stops a specific PM2 managed application.',
                'alternate_commands' => json_encode([
                    'pm2 stop all',
                    'pm2 delete myApp'
                ]),
                'example_usage' => 'pm2 stop api-server',
                'notes' => 'Use delete to remove from PM2 list completely.',
                'tags' => 'nodejs,pm2,stop,delete',
                'os' => 'all',
                'danger_level' => 'low',
                'icon' => 'stop-fill'
            ],
            [
                'name' => 'View PM2 Logs',
                'category' => 'nodejs',
                'sub_category' => 'pm2',
                'command' => 'pm2 logs myApp',
                'description' => 'Shows logs for a specific PM2 managed application.',
                'alternate_commands' => json_encode([
                    'pm2 logs',
                    'pm2 logs --lines 100'
                ]),
                'example_usage' => 'pm2 logs api-server --lines 50',
                'notes' => 'Use --lines to limit output.',
                'tags' => 'nodejs,pm2,logs,debug',
                'os' => 'all',
                'danger_level' => 'low',
                'icon' => 'file-text'
            ],
            [
                'name' => 'PM2 Monitoring Dashboard',
                'category' => 'nodejs',
                'sub_category' => 'pm2',
                'command' => 'pm2 monit',
                'description' => 'Opens real-time monitoring dashboard for PM2 processes.',
                'alternate_commands' => json_encode([]),
                'example_usage' => 'pm2 monit',
                'notes' => 'Shows CPU, memory, and logs in real-time.',
                'tags' => 'nodejs,pm2,monitor,dashboard',
                'os' => 'all',
                'danger_level' => 'low',
                'icon' => 'graph-up'
            ],

            // ============ 9. SYSTEM & UTILITIES ============
            [
                'name' => 'Check Running PHP Processes',
                'category' => 'system',
                'sub_category' => 'process',
                'command' => 'ps -ef | grep php',
                'description' => 'Lists all running PHP processes.',
                'alternate_commands' => json_encode([
                    'ps aux | grep php',
                    'pgrep -l php'
                ]),
                'example_usage' => 'ps -ef | grep php',
                'notes' => 'Use kill -9 PID to terminate a process.',
                'tags' => 'system,php,process,ps',
                'os' => 'linux',
                'danger_level' => 'low',
                'icon' => 'terminal'
            ],
            [
                'name' => 'Kill Process by PID',
                'category' => 'system',
                'sub_category' => 'process',
                'command' => 'kill -9 PID',
                'description' => 'Force kills a process by its Process ID.',
                'alternate_commands' => json_encode([
                    'sudo kill -9 PID',
                    'pkill -f process-name'
                ]),
                'example_usage' => 'kill -9 40718',
                'notes' => 'Use -9 for force kill, -15 for graceful termination.',
                'tags' => 'system,kill,process,PID',
                'os' => 'linux',
                'danger_level' => 'high',
                'icon' => 'x-octagon'
            ],
            [
                'name' => 'Check Disk Space',
                'category' => 'system',
                'sub_category' => 'storage',
                'command' => 'df -h',
                'description' => 'Shows disk space usage in human-readable format.',
                'alternate_commands' => json_encode([
                    'df -h --total',
                    'du -sh *'
                ]),
                'example_usage' => 'df -h',
                'notes' => 'Watch for 100% usage on root partition.',
                'tags' => 'system,disk,storage,df',
                'os' => 'linux',
                'danger_level' => 'low',
                'icon' => 'hdd-stack'
            ],
            [
                'name' => 'Check Folder Permission in Octal',
                'category' => 'system',
                'sub_category' => 'permissions',
                'command' => 'stat -c %a directoryName',
                'description' => 'Shows folder permissions in octal format (e.g., 755).',
                'alternate_commands' => json_encode([
                    'ls -ld directoryName',
                    'stat directoryName'
                ]),
                'example_usage' => 'stat -c %a storage',
                'notes' => 'Output shows numeric permissions like 755.',
                'tags' => 'system,permissions,stat,octal',
                'os' => 'linux',
                'danger_level' => 'low',
                'icon' => 'key'
            ],
            [
                'name' => 'Copy All Files from Directory',
                'category' => 'system',
                'sub_category' => 'file',
                'command' => 'cp -a source/. destination/',
                'description' => 'Copies all files including hidden ones from source to destination.',
                'alternate_commands' => json_encode([
                    'rsync -av source/ destination/',
                    'cp -r source/* destination/'
                ]),
                'example_usage' => 'cp -a /var/www/project1/. /var/www/project2/',
                'notes' => 'Trailing slash is important for correct copying.',
                'tags' => 'system,copy,cp,file',
                'os' => 'linux',
                'danger_level' => 'medium',
                'icon' => 'files'
            ],
            [
                'name' => 'Install DEB Package',
                'category' => 'system',
                'sub_category' => 'package',
                'command' => 'sudo dpkg -i package.deb',
                'description' => 'Installs a .deb package file on Ubuntu/Debian.',
                'alternate_commands' => json_encode([
                    'sudo apt install ./package.deb',
                    'sudo dpkg --install package.deb'
                ]),
                'example_usage' => 'sudo dpkg -i dbeaver-ce_24.0.4_amd64.deb',
                'notes' => 'Run sudo apt-get install -f if dependency issues occur.',
                'tags' => 'system,dpkg,install,deb',
                'os' => 'linux',
                'danger_level' => 'medium',
                'icon' => 'box'
            ],
            [
                'name' => 'View Last Commands History',
                'category' => 'system',
                'sub_category' => 'history',
                'command' => 'history | tail -10',
                'description' => 'Shows the last 10 commands from shell history.',
                'alternate_commands' => json_encode([
                    'history 10',
                    'fc -l -10'
                ]),
                'example_usage' => 'history | tail -20',
                'notes' => 'Use !number to re-run a command.',
                'tags' => 'system,history,commands',
                'os' => 'linux',
                'danger_level' => 'low',
                'icon' => 'clock-history'
            ],

            // ============ 10. DATABASE COMMANDS ============
            [
                'name' => 'Install SQLite3 for PHP',
                'category' => 'database',
                'sub_category' => 'sqlite',
                'command' => 'sudo apt-get install php8.2-sqlite3',
                'description' => 'Installs SQLite3 extension for PHP to fix driver errors.',
                'alternate_commands' => json_encode([
                    'sudo apt install php-sqlite3',
                    'sudo apt install php7.4-sqlite3'
                ]),
                'example_usage' => 'sudo apt-get install php8.2-sqlite3',
                'notes' => 'Match PHP version number with your installation.',
                'tags' => 'database,sqlite,php,extension',
                'os' => 'linux',
                'danger_level' => 'low',
                'icon' => 'database'
            ],
            [
                'name' => 'Check SQLite Extension Status',
                'category' => 'database',
                'sub_category' => 'sqlite',
                'command' => 'ls /etc/php/8.2/cli/conf.d/ | grep sqlite',
                'description' => 'Checks if SQLite extension is enabled in PHP.',
                'alternate_commands' => json_encode([
                    'php -m | grep sqlite',
                    'php --ini | grep sqlite'
                ]),
                'example_usage' => 'ls /etc/php/8.2/cli/conf.d/ | grep sqlite',
                'notes' => 'Look for sqlite3.ini in output.',
                'tags' => 'database,sqlite,check,extension',
                'os' => 'linux',
                'danger_level' => 'low',
                'icon' => 'search'
            ],

            // ============ 11. APACHE PERFORMANCE ============
            [
                'name' => 'Configure Apache MPM Prefork',
                'category' => 'apache',
                'sub_category' => 'performance',
                'command' => 'sudo nano /etc/apache2/mods-enabled/mpm_prefork.conf',
                'description' => 'Edits Apache MPM Prefork configuration for performance tuning.',
                'alternate_commands' => json_encode([
                    'sudo nano /etc/apache2/mods-available/mpm_prefork.conf'
                ]),
                'example_usage' => 'sudo nano /etc/apache2/mods-enabled/mpm_prefork.conf',
                'notes' => 'Adjust StartServers, MinSpareServers, MaxRequestWorkers based on server RAM.',
                'tags' => 'apache,performance,mpm,prefork',
                'os' => 'linux',
                'danger_level' => 'high',
                'icon' => 'speedometer'
            ],
            [
                'name' => 'Enable Apache Rewrite Module',
                'category' => 'apache',
                'sub_category' => 'modules',
                'command' => 'sudo a2enmod rewrite',
                'description' => 'Enables Apache rewrite module for URL rewriting.',
                'alternate_commands' => json_encode([
                    'sudo a2enmod rewrite && sudo systemctl restart apache2'
                ]),
                'example_usage' => 'sudo a2enmod rewrite',
                'notes' => 'Required for Laravel and many PHP frameworks.',
                'tags' => 'apache,rewrite,module,enable',
                'os' => 'linux',
                'danger_level' => 'medium',
                'icon' => 'arrow-repeat'
            ],

            // ============ 12. LARAVEL QUEUE JOB TEMPLATES ============
            [
                'name' => 'Supervisor Job Template - Laravel Queue',
                'category' => 'supervisor',
                'sub_category' => 'template',
                'command' => '[program:job_name]\nprocess_name=%(program_name)s_%(process_num)02d\ncommand=php /var/www/project/artisan queue:work --tries=3 --queue=queue_name\nautostart=true\nautorestart=true\nstopasgroup=true\nkillasgroup=true\nuser=www-data\nnumprocs=3\nredirect_stderr=true\nstdout_logfile=/etc/supervisor/logs/job_name.log\nstopwaitsecs=3600',
                'description' => 'Template configuration for Laravel queue worker with Supervisor.',
                'alternate_commands' => json_encode([]),
                'example_usage' => '# Save as /etc/supervisor/conf.d/laravel-worker.conf',
                'notes' => 'Replace job_name, queue_name, and project path accordingly.',
                'tags' => 'supervisor,template,laravel,queue',
                'os' => 'linux',
                'danger_level' => 'low',
                'icon' => 'file-code'
            ],
            
            // ============ 13. VS CODE & DEVELOPMENT ============
            [
                'name' => 'VS Code Blade Comment Shortcut',
                'category' => 'development',
                'sub_category' => 'vscode',
                'command' => 'Ctrl + Alt + /',
                'description' => 'VS Code shortcut for Blade comment alignment in Laravel views.',
                'alternate_commands' => json_encode([
                    'Ctrl + /',
                    'Shift + Alt + A'
                ]),
                'example_usage' => 'Press Ctrl + Alt + / to comment/uncomment Blade code',
                'notes' => 'Works in .blade.php files.',
                'tags' => 'vscode,shortcut,blade,comment',
                'os' => 'all',
                'danger_level' => 'low',
                'icon' => 'keyboard'
            ],
            [
                'name' => 'JavaScript Console Styling',
                'category' => 'development',
                'sub_category' => 'debug',
                'command' => "console.log('%cYour Text', 'background: color; color: text-color; padding: 5px; border-radius: 20px;', 'Message');",
                'description' => 'Styled console messages for better debugging visibility.',
                'alternate_commands' => json_encode([
                    "console.error('%cERROR', 'background:red; color:white;', 'Error message');",
                    "console.warn('%cWARNING', 'background:yellow; color:black;', 'Warning message');"
                ]),
                'example_usage' => "console.log('%cIMMANUEL PRABHU', 'background:blue; color: white; border-radius: 20px; padding: 5px;', 'Test');",
                'notes' => 'Use different colors for different log levels.',
                'tags' => 'javascript,console,debug,styling',
                'os' => 'all',
                'danger_level' => 'low',
                'icon' => 'bug'
            ],

            // ============ 14. PHPMYADMIN & AWS ============
            [
                'name' => 'Check phpMyAdmin Alias in Apache',
                'category' => 'phpmyadmin',
                'sub_category' => 'apache',
                'command' => 'cat /etc/phpmyadmin/apache.conf | grep Alias',
                'description' => 'Shows the phpMyAdmin URL alias configured in Apache. Look for "Alias /xxxxx /usr/share/phpmyadmin" to find the actual URL path.',
                'alternate_commands' => json_encode([
                    'grep -i "Alias" /etc/phpmyadmin/apache.conf',
                    'sudo nano /etc/phpmyadmin/apache.conf',
                    'ls -la /etc/apache2/conf-available/ | grep phpmyadmin'
                ]),
                'example_usage' => 'cat /etc/phpmyadmin/apache.conf | grep Alias',
                'notes' => 'Example output: Alias /payConsAdmin /usr/share/phpmyadmin → URL would be https://domain.com/payConsAdmin',
                'tags' => 'phpmyadmin,apache,alias,url',
                'os' => 'linux',
                'danger_level' => 'low',
                'icon' => 'globe'
            ],
            [
                'name' => 'Find phpMyAdmin Configuration File',
                'category' => 'phpmyadmin',
                'sub_category' => 'config',
                'command' => 'sudo find / -name "config.inc.php" 2>/dev/null | grep phpmyadmin',
                'description' => 'Locates the phpMyAdmin configuration file on the server.',
                'alternate_commands' => json_encode([
                    'ls -la /etc/phpmyadmin/config.inc.php',
                    'locate phpmyadmin/config.inc.php',
                    'sudo updatedb && locate config.inc.php'
                ]),
                'example_usage' => 'sudo find / -name "config.inc.php" 2>/dev/null | grep phpmyadmin',
                'notes' => 'Main config file is usually at /etc/phpmyadmin/config.inc.php',
                'tags' => 'phpmyadmin,config,find,location',
                'os' => 'linux',
                'danger_level' => 'low',
                'icon' => 'file-text'
            ],
            [
                'name' => 'Edit phpMyAdmin Config to Allow Any User',
                'category' => 'phpmyadmin',
                'sub_category' => 'config',
                'command' => 'sudo sed -i "s/AllowAnyUser = false/AllowAnyUser = true/" /etc/phpmyadmin/config.inc.php',
                'description' => 'Modifies phpMyAdmin config to allow login with any MySQL user (not just those in controluser table).',
                'alternate_commands' => json_encode([
                    'sudo nano /etc/phpmyadmin/config.inc.php',
                    '# Find and change: $cfg[\'Servers\'][$i][\'AllowAnyUser\'] = true;',
                    'echo "\$cfg[\'Servers\'][\$i][\'AllowAnyUser\'] = true;" | sudo tee -a /etc/phpmyadmin/config.inc.php'
                ]),
                'example_usage' => 'sudo sed -i "s/false/true/" /etc/phpmyadmin/config.inc.php',
                'notes' => 'After change, you can login with any valid MySQL credentials.',
                'tags' => 'phpmyadmin,config,allow,anyuser',
                'os' => 'linux',
                'danger_level' => 'high',
                'icon' => 'unlock'
            ],
            [
                'name' => 'Check phpMyAdmin Login Page URL',
                'category' => 'phpmyadmin',
                'sub_category' => 'apache',
                'command' => 'grep -E "Alias.*phpmyadmin" /etc/apache2/conf-available/phpmyadmin.conf',
                'description' => 'Finds the exact URL path for phpMyAdmin access on the server.',
                'alternate_commands' => json_encode([
                    'grep -r "Alias" /etc/apache2/ | grep phpmyadmin',
                    'sudo apache2ctl -S | grep phpmyadmin',
                    'ls /etc/apache2/conf-enabled/ | grep phpmyadmin'
                ]),
                'example_usage' => 'grep Alias /etc/apache2/conf-available/phpmyadmin.conf',
                'notes' => 'If Alias is /phpmyadmin, access at https://your-domain.com/phpmyadmin',
                'tags' => 'phpmyadmin,url,apache,alias',
                'os' => 'linux',
                'danger_level' => 'low',
                'icon' => 'search'
            ],
            [
                'name' => 'Restart phpMyAdmin Service',
                'category' => 'phpmyadmin',
                'sub_category' => 'service',
                'command' => 'sudo systemctl restart apache2',
                'description' => 'Restarts Apache to apply phpMyAdmin configuration changes.',
                'alternate_commands' => json_encode([
                    'sudo service apache2 restart',
                    'sudo systemctl reload apache2',
                    'sudo apache2ctl graceful'
                ]),
                'example_usage' => 'sudo systemctl restart apache2',
                'notes' => 'Always test config before restart: sudo apache2ctl configtest',
                'tags' => 'phpmyadmin,apache,restart,service',
                'os' => 'linux',
                'danger_level' => 'medium',
                'icon' => 'arrow-repeat'
            ],
            [
                'name' => 'Check If phpMyAdmin is Installed',
                'category' => 'phpmyadmin',
                'sub_category' => 'installation',
                'command' => 'dpkg -l | grep phpmyadmin',
                'description' => 'Checks if phpMyAdmin is installed on the server.',
                'alternate_commands' => json_encode([
                    'apt list --installed | grep phpmyadmin',
                    'which phpmyadmin',
                    'ls /usr/share/phpmyadmin'
                ]),
                'example_usage' => 'dpkg -l | grep phpmyadmin',
                'notes' => 'Shows version if installed, empty output if not installed.',
                'tags' => 'phpmyadmin,check,installed,version',
                'os' => 'linux',
                'danger_level' => 'low',
                'icon' => 'check-circle'
            ],
            [
                'name' => 'Install phpMyAdmin on Ubuntu',
                'category' => 'phpmyadmin',
                'sub_category' => 'installation',
                'command' => 'sudo apt update && sudo apt install -y phpmyadmin',
                'description' => 'Installs phpMyAdmin on Ubuntu/Debian server. Prompts for web server selection during installation.',
                'alternate_commands' => json_encode([
                    'sudo apt install phpmyadmin php-mbstring php-zip php-gd php-json php-curl',
                    'sudo apt install --no-install-recommends phpmyadmin'
                ]),
                'example_usage' => 'sudo apt update && sudo apt install -y phpmyadmin',
                'notes' => 'Select apache2 when prompted. You will also be asked to configure database for phpmyadmin.',
                'tags' => 'phpmyadmin,install,ubuntu,apt',
                'os' => 'linux',
                'danger_level' => 'medium',
                'icon' => 'download'
            ],
            [
                'name' => 'Secure phpMyAdmin with .htaccess',
                'category' => 'phpmyadmin',
                'sub_category' => 'security',
                'command' => 'sudo nano /usr/share/phpmyadmin/.htaccess',
                'description' => 'Creates/edits .htaccess file to add additional password protection to phpMyAdmin.',
                'alternate_commands' => json_encode([
                    "echo 'AuthType Basic\nAuthName \"Restricted Access\"\nAuthUserFile /etc/phpmyadmin/.htpasswd\nRequire valid-user' | sudo tee /usr/share/phpmyadmin/.htaccess",
                    'sudo htpasswd -c /etc/phpmyadmin/.htpasswd admin'
                ]),
                'example_usage' => 'sudo nano /usr/share/phpmyadmin/.htaccess',
                'notes' => 'Adds basic authentication layer before phpMyAdmin login page.',
                'tags' => 'phpmyadmin,security,htaccess,basicauth',
                'os' => 'linux',
                'danger_level' => 'medium',
                'icon' => 'shield-lock'
            ],
            [
                'name' => 'Check AWS EC2 Instance Metadata',
                'category' => 'aws',
                'sub_category' => 'ec2',
                'command' => 'curl -s http://169.254.169.254/latest/meta-data/',
                'description' => 'Fetches EC2 instance metadata including instance ID, region, and more from within the instance.',
                'alternate_commands' => json_encode([
                    'curl -s http://169.254.169.254/latest/meta-data/instance-id',
                    'curl -s http://169.254.169.254/latest/meta-data/public-ipv4',
                    'curl -s http://169.254.169.254/latest/meta-data/local-ipv4'
                ]),
                'example_usage' => 'curl -s http://169.254.169.254/latest/meta-data/instance-id',
                'notes' => 'Works only from within EC2 instances. Great for getting instance info without AWS CLI.',
                'tags' => 'aws,ec2,metadata,instance',
                'os' => 'all',
                'danger_level' => 'low',
                'icon' => 'cloud'
            ],
            [
                'name' => 'Get AWS EC2 Instance ID',
                'category' => 'aws',
                'sub_category' => 'ec2',
                'command' => 'curl -s http://169.254.169.254/latest/meta-data/instance-id',
                'description' => 'Retrieves the unique Instance ID of the current EC2 instance.',
                'alternate_commands' => json_encode([
                    'TOKEN=$(curl -X PUT "http://169.254.169.254/latest/api/token" -H "X-aws-ec2-metadata-token-ttl-seconds: 21600") && curl -H "X-aws-ec2-metadata-token: $TOKEN" -s http://169.254.169.254/latest/meta-data/instance-id',
                    'wget -q -O - http://169.254.169.254/latest/meta-data/instance-id'
                ]),
                'example_usage' => 'INSTANCE_ID=$(curl -s http://169.254.169.254/latest/meta-data/instance-id) && echo $INSTANCE_ID',
                'notes' => 'Useful for scripts that need to know which instance they are running on.',
                'tags' => 'aws,ec2,instance-id,metadata',
                'os' => 'all',
                'danger_level' => 'low',
                'icon' => 'server'
            ],
            [
                'name' => 'Get AWS EC2 Region',
                'category' => 'aws',
                'sub_category' => 'ec2',
                'command' => 'curl -s http://169.254.169.254/latest/meta-data/placement/region',
                'description' => 'Retrieves the AWS region where the current EC2 instance is running.',
                'alternate_commands' => json_encode([
                    'TOKEN=$(curl -X PUT "http://169.254.169.254/latest/api/token" -H "X-aws-ec2-metadata-token-ttl-seconds: 21600") && curl -H "X-aws-ec2-metadata-token: $TOKEN" -s http://169.254.169.254/latest/meta-data/placement/region',
                    'ec2metadata --region'
                ]),
                'example_usage' => 'AWS_REGION=$(curl -s http://169.254.169.254/latest/meta-data/placement/region) && echo $AWS_REGION',
                'notes' => 'Returns region like us-east-1, ap-south-1, etc.',
                'tags' => 'aws,ec2,region,metadata',
                'os' => 'all',
                'danger_level' => 'low',
                'icon' => 'map'
            ],
            [
                'name' => 'Get AWS EC2 Public IP',
                'category' => 'aws',
                'sub_category' => 'ec2',
                'command' => 'curl -s http://checkip.amazonaws.com',
                'description' => 'Gets the public IP address of the EC2 instance (from external service).',
                'alternate_commands' => json_encode([
                    'curl -s http://169.254.169.254/latest/meta-data/public-ipv4',
                    'curl -s https://api.ipify.org',
                    'dig +short myip.opendns.com @resolver1.opendns.com'
                ]),
                'example_usage' => 'PUBLIC_IP=$(curl -s http://checkip.amazonaws.com) && echo $PUBLIC_IP',
                'notes' => 'Works from any server, not just AWS.',
                'tags' => 'aws,ec2,public-ip,ip',
                'os' => 'all',
                'danger_level' => 'low',
                'icon' => 'globe'
            ],
            [
                'name' => 'List AWS S3 Buckets',
                'category' => 'aws',
                'sub_category' => 's3',
                'command' => 'aws s3 ls',
                'description' => 'Lists all S3 buckets in the configured AWS account.',
                'alternate_commands' => json_encode([
                    'aws s3api list-buckets --query "Buckets[].Name"',
                    'aws s3 ls s3://bucket-name/path/ --recursive'
                ]),
                'example_usage' => 'aws s3 ls',
                'notes' => 'Requires AWS CLI installed and configured with credentials.',
                'tags' => 'aws,s3,buckets,list',
                'os' => 'all',
                'danger_level' => 'low',
                'icon' => 'cloud-upload'
            ],
            [
                'name' => 'Sync Files to AWS S3',
                'category' => 'aws',
                'sub_category' => 's3',
                'command' => 'aws s3 sync /local/directory/ s3://bucket-name/path/ --delete',
                'description' => 'Syncs local directory with S3 bucket. --delete removes files in S3 that are not in local.',
                'alternate_commands' => json_encode([
                    'aws s3 cp /local/file.txt s3://bucket-name/path/',
                    'aws s3 mv /local/file.txt s3://bucket-name/path/',
                    'aws s3 sync s3://bucket-name/path/ /local/directory/'
                ]),
                'example_usage' => 'aws s3 sync /var/www/html/uploads/ s3://myapp-backup/uploads/ --delete',
                'notes' => 'Use --dryrun first to see what will be synced.',
                'tags' => 'aws,s3,sync,backup',
                'os' => 'all',
                'danger_level' => 'medium',
                'icon' => 'arrow-repeat'
            ],
            [
                'name' => 'Check AWS RDS Connection',
                'category' => 'aws',
                'sub_category' => 'rds',
                'command' => 'nc -zv database-hostname.rds.amazonaws.com 3306',
                'description' => 'Tests connectivity to AWS RDS database instance on specified port.',
                'alternate_commands' => json_encode([
                    'telnet database-hostname.rds.amazonaws.com 3306',
                    'mysql -h database-hostname.rds.amazonaws.com -u username -p',
                    'psql -h database-hostname.rds.amazonaws.com -U username -d database'
                ]),
                'example_usage' => 'nc -zv mydb.cxxxxxx.ap-south-1.rds.amazonaws.com 3306',
                'notes' => 'Port 3306 for MySQL/MariaDB, 5432 for PostgreSQL.',
                'tags' => 'aws,rds,database,connection',
                'os' => 'linux',
                'danger_level' => 'low',
                'icon' => 'database'
            ],
            [
                'name' => 'Get AWS CloudWatch Logs',
                'category' => 'aws',
                'sub_category' => 'cloudwatch',
                'command' => 'aws logs tail /aws/lambda/function-name --since 1h',
                'description' => 'Tails CloudWatch logs for a Lambda function or other AWS service.',
                'alternate_commands' => json_encode([
                    'aws logs describe-log-groups',
                    'aws logs describe-log-streams --log-group-name /aws/lambda/function-name',
                    'aws logs get-log-events --log-group-name /aws/lambda/function-name --log-stream-name stream-name'
                ]),
                'example_usage' => 'aws logs tail /aws/lambda/my-function --since 30m --follow',
                'notes' => 'Use --follow to watch logs in real-time.',
                'tags' => 'aws,cloudwatch,logs,monitoring',
                'os' => 'all',
                'danger_level' => 'low',
                'icon' => 'file-text'
            ],
            [
                'name' => 'Check AWS System Manager (SSM) Status',
                'category' => 'aws',
                'sub_category' => 'ssm',
                'command' => 'sudo systemctl status amazon-ssm-agent',
                'description' => 'Checks if AWS Systems Manager agent is running on the EC2 instance.',
                'alternate_commands' => json_encode([
                    'sudo systemctl start amazon-ssm-agent',
                    'sudo systemctl stop amazon-ssm-agent',
                    'sudo systemctl restart amazon-ssm-agent'
                ]),
                'example_usage' => 'sudo systemctl status amazon-ssm-agent',
                'notes' => 'SSM agent required for Session Manager and Run Command features.',
                'tags' => 'aws,ssm,agent,status',
                'os' => 'linux',
                'danger_level' => 'low',
                'icon' => 'terminal'
            ],
            [
                'name' => 'List AWS EC2 Instances via CLI',
                'category' => 'aws',
                'sub_category' => 'ec2',
                'command' => 'aws ec2 describe-instances --query "Reservations[*].Instances[*].[InstanceId,State.Name,Tags[?Key==`Name`].Value|[0]]" --output table',
                'description' => 'Lists all EC2 instances with their ID, state, and name tag in table format.',
                'alternate_commands' => json_encode([
                    'aws ec2 describe-instances --filters "Name=instance-state-name,Values=running"',
                    'aws ec2 describe-instances --region us-east-1',
                    'aws ec2 describe-instance-status'
                ]),
                'example_usage' => 'aws ec2 describe-instances --query "Reservations[*].Instances[*].[InstanceId,State.Name,Tags[?Key==`Name`].Value|[0]]" --output table',
                'notes' => 'Requires AWS CLI configured with appropriate permissions.',
                'tags' => 'aws,ec2,instances,list',
                'os' => 'all',
                'danger_level' => 'low',
                'icon' => 'server'
            ],
            [
                'name' => 'Check AWS Security Group Rules',
                'category' => 'aws',
                'sub_category' => 'ec2',
                'command' => 'aws ec2 describe-security-groups --group-ids sg-xxxxxxxxxx --query "SecurityGroups[*].IpPermissions[*]"',
                'description' => 'Shows inbound and outbound rules for a specific AWS security group.',
                'alternate_commands' => json_encode([
                    'aws ec2 describe-security-groups',
                    'aws ec2 authorize-security-group-ingress --group-id sg-xxx --protocol tcp --port 22 --cidr 0.0.0.0/0',
                    'aws ec2 revoke-security-group-ingress --group-id sg-xxx --protocol tcp --port 22 --cidr 0.0.0.0/0'
                ]),
                'example_usage' => 'aws ec2 describe-security-groups --group-ids sg-12345678',
                'notes' => 'Check for overly permissive rules (0.0.0.0/0) that may be security risks.',
                'tags' => 'aws,security-group,firewall,rules',
                'os' => 'all',
                'danger_level' => 'low',
                'icon' => 'shield-lock'
            ],
            [
                'name' => 'Check Apache2 Error Logs',
                'category' => 'apache',
                'sub_category' => 'logs',
                'command' => 'sudo tail -f /var/log/apache2/error.log',
                'description' => 'Tails Apache error log in real-time to debug issues.',
                'alternate_commands' => json_encode([
                    'sudo tail -100 /var/log/apache2/error.log',
                    'sudo grep -i "error" /var/log/apache2/error.log',
                    'sudo less /var/log/apache2/error.log'
                ]),
                'example_usage' => 'sudo tail -f /var/log/apache2/error.log',
                'notes' => 'Very useful for debugging 500 errors and PHP issues.',
                'tags' => 'apache,logs,error,debug',
                'os' => 'linux',
                'danger_level' => 'low',
                'icon' => 'file-text'
            ],
            [
                'name' => 'Check Apache2 Access Logs',
                'category' => 'apache',
                'sub_category' => 'logs',
                'command' => 'sudo tail -f /var/log/apache2/access.log',
                'description' => 'Tails Apache access log to see incoming requests in real-time.',
                'alternate_commands' => json_encode([
                    'sudo tail -100 /var/log/apache2/access.log',
                    'sudo cat /var/log/apache2/access.log | grep "404"',
                    'sudo cat /var/log/apache2/access.log | awk \'{print $1}\' | sort | uniq -c | sort -rn'
                ]),
                'example_usage' => 'sudo tail -f /var/log/apache2/access.log',
                'notes' => 'Useful for monitoring traffic and debugging request issues.',
                'tags' => 'apache,logs,access,monitoring',
                'os' => 'linux',
                'danger_level' => 'low',
                'icon' => 'eye'
            ],

            // ============ 15. VPN - FIRST FINANCE ============
            [
                'name' => 'Install OpenVPN Client',
                'category' => 'vpn',
                'sub_category' => 'installation',
                'command' => 'sudo apt update && sudo apt install openvpn -y',
                'description' => 'Installs OpenVPN client on Ubuntu system to connect to VPN server.',
                'alternate_commands' => json_encode([
                    'sudo apt-get install openvpn -y',
                    'sudo yum install openvpn -y',
                    'sudo dnf install openvpn -y'
                ]),
                'example_usage' => 'sudo apt update && sudo apt install openvpn -y',
                'notes' => 'Run this first before connecting to VPN.',
                'tags' => 'vpn,openvpn,install,ubuntu',
                'os' => 'linux',
                'danger_level' => 'low',
                'icon' => 'download'
            ],
            [
                'name' => 'Connect to First Finance VPN',
                'category' => 'vpn',
                'sub_category' => 'connection',
                'command' => 'sudo openvpn --config client.ovpn',
                'description' => 'Connects to First Finance VPN using the downloaded OVPN configuration file.',
                'alternate_commands' => json_encode([
                    'sudo openvpn --config /path/to/client.ovpn',
                    'sudo openvpn client.ovpn'
                ]),
                'example_usage' => 'cd ~/Downloads && sudo openvpn --config client.ovpn',
                'notes' => 'Run from the directory where client.ovpn file is located.',
                'tags' => 'vpn,openvpn,connect,first_finance',
                'os' => 'linux',
                'danger_level' => 'low',
                'icon' => 'shield-lock'
            ],
            [
                'name' => 'Access OpenVPN Admin UI',
                'category' => 'vpn',
                'sub_category' => 'access',
                'command' => 'https://43.204.55.60:943/',
                'description' => 'OpenVPN Client UI URL for downloading configuration file and managing VPN settings.',
                'alternate_commands' => json_encode([
                    'https://43.204.55.60:943/admin',
                    'https://43.204.55.60:943/connect'
                ]),
                'example_usage' => 'Open in browser: https://43.204.55.60:943/',
                'notes' => 'Username: first_finance, Password: bFenbW3Dud7I',
                'tags' => 'vpn,openvpn,admin,ui',
                'os' => 'all',
                'danger_level' => 'low',
                'icon' => 'globe'
            ],
            [
                'name' => 'Download OVPN Config File',
                'category' => 'vpn',
                'sub_category' => 'download',
                'command' => 'wget --no-check-certificate --user=first_finance --password=bFenbW3Dud7I https://43.204.55.60:943/rest/getconfig -O client.ovpn',
                'description' => 'Downloads the OpenVPN configuration file directly from the command line.',
                'alternate_commands' => json_encode([
                    'curl -k -u first_finance:bFenbW3Dud7I https://43.204.55.60:943/rest/getconfig -o client.ovpn',
                    'scp user@server:/path/to/client.ovpn .'
                ]),
                'example_usage' => 'curl -k -u first_finance:bFenbW3Dud7I https://43.204.55.60:943/rest/getconfig -o client.ovpn',
                'notes' => 'Use --no-check-certificate if SSL certificate is self-signed.',
                'tags' => 'vpn,openvpn,download,config',
                'os' => 'linux',
                'danger_level' => 'low',
                'icon' => 'download'
            ],
            [
                'name' => 'Check VPN Connection Status',
                'category' => 'vpn',
                'sub_category' => 'status',
                'command' => 'curl ifconfig.me',
                'description' => 'Checks your public IP address to verify if VPN is connected successfully.',
                'alternate_commands' => json_encode([
                    'curl ipinfo.io/ip',
                    'curl icanhazip.com',
                    'dig +short myip.opendns.com @resolver1.opendns.com'
                ]),
                'example_usage' => 'curl ifconfig.me',
                'notes' => 'If IP matches VPN server IP, connection is successful.',
                'tags' => 'vpn,check,ip,status',
                'os' => 'all',
                'danger_level' => 'low',
                'icon' => 'eye'
            ],
            [
                'name' => 'Disconnect OpenVPN',
                'category' => 'vpn',
                'sub_category' => 'disconnect',
                'command' => 'sudo pkill openvpn',
                'description' => 'Terminates the OpenVPN connection process.',
                'alternate_commands' => json_encode([
                    'sudo killall openvpn',
                    'sudo systemctl stop openvpn@client'
                ]),
                'example_usage' => 'sudo pkill openvpn',
                'notes' => 'Run this to disconnect from VPN.',
                'tags' => 'vpn,openvpn,disconnect,kill',
                'os' => 'linux',
                'danger_level' => 'low',
                'icon' => 'power'
            ],
            [
                'name' => 'View OpenVPN Logs',
                'category' => 'vpn',
                'sub_category' => 'logs',
                'command' => 'sudo tail -f /var/log/openvpn.log',
                'description' => 'Shows real-time OpenVPN connection logs for debugging.',
                'alternate_commands' => json_encode([
                    'sudo journalctl -u openvpn -f',
                    'sudo tail -50 /var/log/openvpn.log'
                ]),
                'example_usage' => 'sudo tail -f /var/log/openvpn.log',
                'notes' => 'Useful for troubleshooting connection issues.',
                'tags' => 'vpn,openvpn,logs,debug',
                'os' => 'linux',
                'danger_level' => 'low',
                'icon' => 'file-text'
            ],
            [
                'name' => 'Test VPN Latency',
                'category' => 'vpn',
                'sub_category' => 'test',
                'command' => 'ping -c 4 43.204.55.60',
                'description' => 'Tests latency to the VPN server.',
                'alternate_commands' => json_encode([
                    'mtr 43.204.55.60',
                    'traceroute 43.204.55.60'
                ]),
                'example_usage' => 'ping -c 4 43.204.55.60',
                'notes' => 'Check response time for VPN server connectivity.',
                'tags' => 'vpn,ping,latency,test',
                'os' => 'all',
                'danger_level' => 'low',
                'icon' => 'graph-up'
            ],
            [
                'name' => 'Auto-Connect VPN on Boot',
                'category' => 'vpn',
                'sub_category' => 'autostart',
                'command' => 'sudo systemctl enable openvpn@client',
                'description' => 'Enables OpenVPN to start automatically on system boot.',
                'alternate_commands' => json_encode([
                    'sudo update-rc.d openvpn enable',
                    'sudo systemctl enable openvpn'
                ]),
                'example_usage' => 'sudo cp client.ovpn /etc/openvpn/client.conf && sudo systemctl enable openvpn@client',
                'notes' => 'Copy OVPN file to /etc/openvpn/client.conf first.',
                'tags' => 'vpn,openvpn,autostart,boot',
                'os' => 'linux',
                'danger_level' => 'medium',
                'icon' => 'play-circle'
            ],
            [
                'name' => 'Fix All PEM File Permissions to 400',
                'category' => 'ssh',
                'sub_category' => 'security',
                'command' => 'find ~/Documents/SSH -name "*.pem" -exec chmod 400 {} \;',
                'description' => 'Sets 400 permissions (read-only for owner) to all PEM files in the SSH directory.',
                'alternate_commands' => json_encode([
                    'chmod 400 ~/Documents/SSH/*.pem',
                    'for file in ~/Documents/SSH/*.pem; do chmod 400 "$file"; done'
                ]),
                'example_usage' => 'find ~/Documents/SSH -name "*.pem" -exec chmod 400 {} \\;',
                'notes' => 'SSH requires 400 or 600 permissions on private keys. Run after uploading new keys.',
                'tags' => 'ssh,pem,permissions,chmod,security',
                'os' => 'linux',
                'danger_level' => 'medium',
                'icon' => 'shield-lock'
            ],
            [
                'name' => 'Fix SSH Permissions & Agent',
                'category' => 'ssh',
                'sub_category' => 'security',
                'command' => 'chmod 700 ~/.ssh && chmod 600 ~/.ssh/config && find ~/Documents/SSH -name "*.pem" -exec chmod 400 {} \\; && eval `ssh-agent -s` && find ~/Documents/SSH -name "*.pem" -exec ssh-add {} \\;',
                'description' => 'Fixes all SSH key permissions, restarts SSH agent, and adds all keys to agent.',
                'alternate_commands' => json_encode([
                    'chmod 700 ~/.ssh',
                    'chmod 600 ~/.ssh/config',
                    'find ~/Documents/SSH -name "*.pem" -exec chmod 400 {} \\;',
                    'ssh-add -D && ssh-add ~/.ssh/id_rsa'
                ]),
                'example_usage' => 'chmod 700 ~/.ssh && chmod 600 ~/.ssh/config && find ~/Documents/SSH -name "*.pem" -exec chmod 400 {} \\;',
                'notes' => 'Run this when getting "Permission denied (publickey)" errors',
                'tags' => 'ssh,permissions,agent,fix',
                'os' => 'linux',
                'danger_level' => 'medium',
                'icon' => 'bug'
            ],

            [
                'name' => 'Kill All Laravel Development Servers',
                'category' => 'laravel',
                'sub_category' => 'serve',
                'command' => 'kill -9 $(lsof -ti:8000,8001,8002,8003,8080,9000 2>/dev/null) 2>/dev/null',
                'description' => 'Kills all Laravel development servers running on common ports (8000, 8001, 8002, 8003, 8080, 9000).',
                'alternate_commands' => json_encode([
                    'for port in 8000 8001 8002 8003 8080 9000; do kill -9 $(lsof -ti:$port) 2>/dev/null; done',
                    'pkill -f "php artisan serve"',
                    'killall -9 php 2>/dev/null'
                ]),
                'example_usage' => 'kill -9 $(lsof -ti:8000,8001,8002,8003,8080,9000 2>/dev/null) 2>/dev/null',
                'notes' => '⚠️ Kills all PHP processes running on these common development ports.',
                'tags' => 'laravel,serve,kill,all,ports',
                'os' => 'linux,macos',
                'danger_level' => 'high',
                'icon' => 'x-octagon'
            ],
            [
                'name' => 'Kill All PHP Artisan Serve Processes',
                'category' => 'laravel',
                'sub_category' => 'serve',
                'command' => 'pkill -f "php artisan serve"',
                'description' => 'Kills all PHP processes running the "php artisan serve" command.',
                'alternate_commands' => json_encode([
                    'killall -9 artisan',
                    'ps aux | grep "artisan serve" | grep -v grep | awk "{print \$2}" | xargs kill -9'
                ]),
                'example_usage' => 'pkill -f "php artisan serve"',
                'notes' => 'Kills ALL artisan serve instances regardless of port.',
                'tags' => 'laravel,artisan,serve,kill,all',
                'os' => 'linux,macos',
                'danger_level' => 'high',
                'icon' => 'x-octagon'
            ],
            [
                'name' => 'Kill All PHP Processes (Laravel + Queue)',
                'category' => 'laravel',
                'sub_category' => 'process',
                'command' => 'pkill -9 php && pkill -9 "php artisan"',
                'description' => 'Kills all PHP processes including Laravel queue workers and development servers.',
                'alternate_commands' => json_encode([
                    'killall -9 php',
                    'ps aux | grep php | awk "{print \$2}" | xargs kill -9 2>/dev/null'
                ]),
                'example_usage' => 'pkill -9 php || killall -9 php',
                'notes' => '⚠️ This will kill ALL PHP processes including queue workers!',
                'tags' => 'php,kill,laravel,queue,all',
                'os' => 'linux,macos',
                'danger_level' => 'high',
                'icon' => 'x-octagon'
            ],
            [
                'name' => 'Kill Laravel Queue Workers',
                'category' => 'laravel',
                'sub_category' => 'queue',
                'command' => 'pkill -f "php artisan queue:work"',
                'description' => 'Kills all running Laravel queue worker processes.',
                'alternate_commands' => json_encode([
                    'php artisan queue:restart',
                    'pkill -f "queue:work"'
                ]),
                'example_usage' => 'pkill -f "php artisan queue:work"',
                'notes' => 'Use "php artisan queue:restart" for graceful restart instead of force kill.',
                'tags' => 'laravel,queue,worker,kill',
                'os' => 'linux,macos',
                'danger_level' => 'high',
                'icon' => 'x-octagon'
            ],
            [
                'name' => 'Kill All Node.js Servers',
                'category' => 'nodejs',
                'sub_category' => 'process',
                'command' => 'pkill -9 node',
                'description' => 'Kills all running Node.js processes including npm, vite, and webpack dev servers.',
                'alternate_commands' => json_encode([
                    'killall -9 node',
                    'ps aux | grep node | awk "{print \$2}" | xargs kill -9'
                ]),
                'example_usage' => 'pkill -9 node',
                'notes' => '⚠️ Kills ALL Node.js processes including npm run dev, vite, etc.',
                'tags' => 'nodejs,kill,process,npm,vite',
                'os' => 'linux,macos',
                'danger_level' => 'high',
                'icon' => 'x-octagon'
            ],
            [
                'name' => 'Kill Process by Port Range',
                'category' => 'system',
                'sub_category' => 'process',
                'command' => "for port in 8000 8001 8002 8003 8080 9000 3000 5173; do kill -9 \$(lsof -ti:\$port) 2>/dev/null; done",
                'description' => 'Kills processes running on common web development ports (Laravel, React, Vue, Vite).',
                'alternate_commands' => json_encode([
                    'fuser -k 8000-9000/tcp',
                    'lsof -ti:8000-9000 | xargs kill -9'
                ]),
                'example_usage' => 'for port in 8000 8001 8002 8080 9000; do kill -9 $(lsof -ti:$port); done',
                'notes' => 'Kills Laravel (8000), Vite (5173), React (3000), and other dev servers.',
                'tags' => 'kill,port,range,dev,servers',
                'os' => 'linux,macos',
                'danger_level' => 'high',
                'icon' => 'x-octagon'
            ],
            [
                'name' => 'Kill All Laravel Related Processes',
                'category' => 'laravel',
                'sub_category' => 'process',
                'command' => "pkill -9 php && pkill -9 composer && pkill -f 'artisan' && kill -9 \$(lsof -ti:8000,8001,8002,8003,8080,9000 2>/dev/null) 2>/dev/null",
                'description' => 'Kills all Laravel related processes including PHP, Artisan commands, and dev servers.',
                'alternate_commands' => json_encode([
                    'killall -9 php composer artisan 2>/dev/null',
                    'ps aux | grep -E "(php|artisan|composer)" | grep -v grep | awk "{print \$2}" | xargs kill -9'
                ]),
                'example_usage' => 'pkill -f "php artisan" && kill -9 $(lsof -ti:8000)',
                'notes' => '⚠️ Complete Laravel cleanup - kills PHP, composer, artisan, and all dev ports.',
                'tags' => 'laravel,kill,all,php,artisan,composer',
                'os' => 'linux,macos',
                'danger_level' => 'high',
                'icon' => 'x-octagon'
            ],
            [
                'name' => 'Check Running Laravel Servers',
                'category' => 'laravel',
                'sub_category' => 'debug',
                'command' => 'ps aux | grep -E "(artisan serve|php -S)" | grep -v grep',
                'description' => 'Lists all running Laravel development servers.',
                'alternate_commands' => json_encode([
                    'lsof -i :8000,8001,8002,8003,8080,9000',
                    'netstat -tlnp | grep -E ":(8000|8001|8002|8003|8080|9000)"'
                ]),
                'example_usage' => 'ps aux | grep "artisan serve"',
                'notes' => 'Shows PID, port, and command for each running Laravel server.',
                'tags' => 'laravel,serve,check,list',
                'os' => 'linux,macos',
                'danger_level' => 'low',
                'icon' => 'search'
            ],
            [
                'name' => 'Gracefully Stop Laravel Server',
                'category' => 'laravel',
                'sub_category' => 'serve',
                'command' => 'pkill -15 -f "php artisan serve"',
                'description' => 'Gracefully stops Laravel development server (SIGTERM instead of SIGKILL).',
                'alternate_commands' => json_encode([
                    'kill -15 $(lsof -ti:8000)',
                    'pkill -f "artisan serve"'
                ]),
                'example_usage' => 'pkill -15 -f "php artisan serve"',
                'notes' => 'Use -15 for graceful termination instead of -9.',
                'tags' => 'laravel,serve,stop,graceful',
                'os' => 'linux,macos',
                'danger_level' => 'medium',
                'icon' => 'stop-circle'
            ],
            [
                'name' => 'Check CSR and Private Key Modulus Match',
                'category' => 'ssl',
                'sub_category' => 'validation',
                'command' => 'openssl req -noout -modulus -in domain.csr | openssl md5',
                'description' => 'Shows the modulus hash of the Certificate Signing Request (CSR).',
                'alternate_commands' => json_encode([
                    'openssl req -noout -modulus -in certificate.csr | openssl md5'
                ]),
                'example_usage' => 'openssl req -noout -modulus -in payfunds.weaveswonders.com.csr | openssl md5',
                'notes' => 'Run this on the CSR file to get its modulus hash.',
                'tags' => 'ssl,csr,modulus,check',
                'os' => 'all',
                'danger_level' => 'low',
                'icon' => 'check-circle'
            ],
            [
                'name' => 'Check Private Key Modulus',
                'category' => 'ssl',
                'sub_category' => 'validation',
                'command' => 'openssl rsa -noout -modulus -in private.key | openssl md5',
                'description' => 'Shows the modulus hash of the private key.',
                'alternate_commands' => json_encode([
                    'openssl rsa -noout -modulus -in domain.key | openssl md5',
                    'openssl pkey -noout -modulus -in private.key | openssl md5'
                ]),
                'example_usage' => 'openssl rsa -noout -modulus -in payfunds.weaveswonders.com_decrypted.key | openssl md5',
                'notes' => 'Run this on the private key file. Use .key extension, not .key.key',
                'tags' => 'ssl,private,key,modulus',
                'os' => 'all',
                'danger_level' => 'low',
                'icon' => 'key'
            ],
            [
                'name' => 'Verify CSR and Private Key Match (One-liner)',
                'category' => 'ssl',
                'sub_category' => 'validation',
                'command' => 'if [ "$(openssl req -noout -modulus -in domain.csr | openssl md5)" = "$(openssl rsa -noout -modulus -in private.key | openssl md5)" ]; then echo "✅ MATCH"; else echo "❌ NO MATCH"; fi',
                'description' => 'Compares CSR and private key modulus hashes and shows if they match.',
                'alternate_commands' => json_encode([
                    'test "$(openssl req -noout -modulus -in csr.pem | openssl md5)" = "$(openssl rsa -noout -modulus -in key.pem | openssl md5)" && echo "MATCH" || echo "NO MATCH"'
                ]),
                'example_usage' => 'if [ "$(openssl req -noout -modulus -in payfunds.weaveswonders.com.csr | openssl md5)" = "$(openssl rsa -noout -modulus -in payfunds.weaveswonders.com_decrypted.key | openssl md5)" ]; then echo "✅ MATCH"; else echo "❌ NO MATCH"; fi',
                'notes' => 'Make sure file paths are correct. Use .key extension, not .key.key',
                'tags' => 'ssl,csr,key,match,verify',
                'os' => 'linux,macos',
                'danger_level' => 'low',
                'icon' => 'check-circle'
            ],
            [
                'name' => 'Check Certificate and Private Key Match',
                'category' => 'ssl',
                'sub_category' => 'validation',
                'command' => 'if [ "$(openssl x509 -noout -modulus -in certificate.crt | openssl md5)" = "$(openssl rsa -noout -modulus -in private.key | openssl md5)" ]; then echo "✅ MATCH"; else echo "❌ NO MATCH"; fi',
                'description' => 'Compares SSL certificate and private key modulus hashes.',
                'alternate_commands' => json_encode([
                    'test "$(openssl x509 -noout -modulus -in cert.crt | openssl md5)" = "$(openssl rsa -noout -modulus -in key.key | openssl md5)" && echo "MATCH" || echo "NO MATCH"'
                ]),
                'example_usage' => 'if [ "$(openssl x509 -noout -modulus -in payfunds.weaveswonders.com.crt | openssl md5)" = "$(openssl rsa -noout -modulus -in payfunds.weaveswonders.com.key | openssl md5)" ]; then echo "✅ MATCH"; else echo "❌ NO MATCH"; fi',
                'notes' => 'Essential check before SSL installation.',
                'tags' => 'ssl,certificate,key,match',
                'os' => 'linux,macos',
                'danger_level' => 'low',
                'icon' => 'check-circle'
            ],
            [
                'name' => 'Fix for "Could not open file or uri" Error',
                'category' => 'ssl',
                'sub_category' => 'debug',
                'command' => 'ls -la *.csr *.key *.crt 2>/dev/null',
                'description' => 'Lists all CSR, KEY, and CRT files in current directory to check file existence.',
                'alternate_commands' => json_encode([
                    'find . -name "*.csr" -o -name "*.key" -o -name "*.crt"',
                    'file *.csr *.key *.crt'
                ]),
                'example_usage' => 'ls -la | grep -E "\.(csr|key|crt)$"',
                'notes' => 'Use this to verify your files exist and have correct names before running modulus commands.',
                'tags' => 'ssl,debug,file,check',
                'os' => 'linux,macos',
                'danger_level' => 'low',
                'icon' => 'search'
            ],
            [
                'name' => 'Decrypt Encrypted Private Key',
                'category' => 'ssl',
                'sub_category' => 'encryption',
                'command' => 'openssl rsa -in encrypted.key -out decrypted.key',
                'description' => 'Removes password encryption from a private key file.',
                'alternate_commands' => json_encode([
                    'openssl rsa -in domain_encrypted.key -out domain_decrypted.key',
                    'openssl pkey -in encrypted.key -out decrypted.key'
                ]),
                'example_usage' => 'openssl rsa -in payfunds.weaveswonders.com_encrypted.key -out payfunds.weaveswonders.com.key',
                'notes' => 'Will prompt for password. Use consistent naming: .key extension, not .key.key',
                'tags' => 'ssl,decrypt,key,password',
                'os' => 'all',
                'danger_level' => 'medium',
                'icon' => 'unlock'
            ],
            [
                'name' => 'Check Top Memory Consuming Processes',
                'category' => 'system',
                'sub_category' => 'monitoring',
                'command' => 'ps aux --sort=-%mem | head -11',
                'description' => 'Shows top 10 processes consuming the most memory on the system, sorted by memory usage (highest to lowest).',
                'alternate_commands' => json_encode([
                    'ps aux --sort=-%mem | head -20',
                    'ps aux --sort=-%cpu | head -11',
                    'top -o %MEM -b -n 1 | head -17',
                    'htop'
                ]),
                'example_usage' => 'ps aux --sort=-%mem | head -11',
                'notes' => 'The first line is the header, followed by the top 10 memory-consuming processes.',
                'tags' => 'memory,process,top,monitoring,ps',
                'os' => 'linux,macos',
                'danger_level' => 'low',
                'icon' => 'memory'
            ],
            [
                'name' => 'Check Top CPU Consuming Processes',
                'category' => 'system',
                'sub_category' => 'monitoring',
                'command' => 'ps aux --sort=-%cpu | head -11',
                'description' => 'Shows top 10 processes consuming the most CPU on the system, sorted by CPU usage (highest to lowest).',
                'alternate_commands' => json_encode([
                    'ps aux --sort=-%cpu | head -20',
                    'top -o %CPU -b -n 1 | head -17'
                ]),
                'example_usage' => 'ps aux --sort=-%cpu | head -11',
                'notes' => 'Useful for identifying CPU-intensive processes.',
                'tags' => 'cpu,process,top,monitoring,ps',
                'os' => 'linux,macos',
                'danger_level' => 'low',
                'icon' => 'cpu'
            ],
            [
                'name' => 'Check Memory Usage Summary',
                'category' => 'system',
                'sub_category' => 'monitoring',
                'command' => 'free -h && echo "---" && vmstat -s | head -10',
                'description' => 'Shows memory usage summary including total, used, free, and swap memory.',
                'alternate_commands' => json_encode([
                    'free -m',
                    'cat /proc/meminfo',
                    'top -b -n 1 | grep "MiB Mem"'
                ]),
                'example_usage' => 'free -h',
                'notes' => 'Use -h for human-readable format (GB/MB).',
                'tags' => 'memory,free,vmstat,monitoring',
                'os' => 'linux',
                'danger_level' => 'low',
                'icon' => 'hdd-stack'
            ],
            [
                'name' => 'Monitor Real-time Memory Usage',
                'category' => 'system',
                'sub_category' => 'monitoring',
                'command' => 'watch -n 2 "ps aux --sort=-%mem | head -11"',
                'description' => 'Real-time monitoring of top memory-consuming processes, updating every 2 seconds.',
                'alternate_commands' => json_encode([
                    'watch -n 1 "ps aux --sort=-%mem | head -15"',
                    'top -o %MEM',
                    'htop'
                ]),
                'example_usage' => 'watch -n 2 "ps aux --sort=-%mem | head -11"',
                'notes' => 'Press Ctrl+C to exit watch mode.',
                'tags' => 'memory,monitoring,realtime,watch,ps',
                'os' => 'linux,macos',
                'danger_level' => 'low',
                'icon' => 'activity'
            ]
        ];

        foreach ($commands as $command) {
            Command::updateOrCreate(
                ['name' => $command['name']],
                $command
            );
        }

        $this->command->info('✅ ' . count($commands) . ' commands seeded successfully!');
    }
}