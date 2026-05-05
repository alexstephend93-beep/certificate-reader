<?php

namespace Database\Seeders;

use App\Models\AdminDashboard;
use App\Models\AdminCredential;
use Illuminate\Database\Seeder;

class AdminDashboardSeeder extends Seeder
{
    public function run()
    {
        $dashboards = [
            [
                'name' => 'WordPress Admin',
                'integration_name' => 'WordPress',
                'url' => 'https://yourdomain.com/wp-admin',
                'icon' => 'wordpress',
                'color' => '#21759b',
                'description' => 'WordPress Content Management System',
                'credentials' => [
                    [
                        'email' => 'admin@example.com',
                        'username' => 'admin',
                        'password' => 'Admin@123456',
                        'role' => 'Super Admin',
                        'is_default' => true
                    ],
                    [
                        'email' => 'editor@example.com',
                        'username' => 'editor',
                        'password' => 'Editor@123456',
                        'role' => 'Editor',
                        'is_default' => false
                    ]
                ]
            ],
            [
                'name' => 'Laravel Admin Panel',
                'integration_name' => 'Laravel',
                'url' => 'https://yourapp.com/admin/login',
                'icon' => 'laravel',
                'color' => '#ff2d20',
                'description' => 'Laravel Admin Dashboard',
                'credentials' => [
                    [
                        'email' => 'superadmin@example.com',
                        'username' => 'superadmin',
                        'password' => 'SuperAdmin@123',
                        'role' => 'Super Admin',
                        'is_default' => true
                    ],
                    [
                        'email' => 'manager@example.com',
                        'username' => 'manager',
                        'password' => 'Manager@123',
                        'role' => 'Manager',
                        'is_default' => false
                    ]
                ]
            ],
            [
                'name' => 'cPanel Control Panel',
                'integration_name' => 'cPanel',
                'url' => 'https://yourdomain.com:2083',
                'icon' => 'server',
                'color' => '#ff6c2c',
                'description' => 'cPanel Web Hosting Control Panel',
                'credentials' => [
                    [
                        'email' => 'root@localhost',
                        'username' => 'root',
                        'password' => 'Root@123456',
                        'role' => 'Root Admin',
                        'is_default' => true
                    ]
                ]
            ],
            [
                'name' => 'phpMyAdmin',
                'integration_name' => 'phpMyAdmin',
                'url' => 'https://yourdomain.com/phpmyadmin',
                'icon' => 'database',
                'color' => '#0074a8',
                'description' => 'MySQL Database Management',
                'credentials' => [
                    [
                        'email' => 'root@localhost',
                        'username' => 'root',
                        'password' => 'RootDB@123',
                        'role' => 'Database Admin',
                        'is_default' => true
                    ]
                ]
            ],
            [
                'name' => 'GitHub Repository',
                'integration_name' => 'GitHub',
                'url' => 'https://github.com/login',
                'icon' => 'github',
                'color' => '#24292e',
                'description' => 'GitHub Code Repository',
                'credentials' => [
                    [
                        'email' => 'developer@example.com',
                        'username' => 'devuser',
                        'password' => 'GitHub@123456',
                        'role' => 'Developer',
                        'is_default' => true
                    ]
                ]
            ],
            [
                'name' => 'AWS Management Console',
                'integration_name' => 'AWS',
                'url' => 'https://console.aws.amazon.com',
                'icon' => 'cloud',
                'color' => '#ff9900',
                'description' => 'Amazon Web Services Console',
                'credentials' => [
                    [
                        'email' => 'admin@example.com',
                        'username' => 'aws-admin',
                        'password' => 'AWS@123456',
                        'role' => 'Root Account',
                        'is_default' => true
                    ]
                ]
            ],
            [
                'name' => 'Mailchimp Dashboard',
                'integration_name' => 'Mailchimp',
                'url' => 'https://login.mailchimp.com',
                'icon' => 'envelope',
                'color' => '#ffe01b',
                'description' => 'Email Marketing Platform',
                'credentials' => [
                    [
                        'email' => 'marketing@example.com',
                        'username' => 'marketing',
                        'password' => 'Mailchimp@123',
                        'role' => 'Marketing Admin',
                        'is_default' => true
                    ]
                ]
            ],
            [
                'name' => 'Stripe Dashboard',
                'integration_name' => 'Stripe',
                'url' => 'https://dashboard.stripe.com/login',
                'icon' => 'credit-card',
                'color' => '#635bff',
                'description' => 'Payment Processing Dashboard',
                'credentials' => [
                    [
                        'email' => 'finance@example.com',
                        'username' => 'finance',
                        'password' => 'Stripe@123456',
                        'role' => 'Account Admin',
                        'is_default' => true
                    ]
                ]
            ],
            [
                'name' => 'Google Analytics',
                'integration_name' => 'Google',
                'url' => 'https://analytics.google.com',
                'icon' => 'graph-up',
                'color' => '#4285f4',
                'description' => 'Website Analytics Dashboard',
                'credentials' => [
                    [
                        'email' => 'analytics@example.com',
                        'username' => 'analytics',
                        'password' => 'Google@123456',
                        'role' => 'Analyst',
                        'is_default' => true
                    ]
                ]
            ],
            [
                'name' => 'Cloudflare Dashboard',
                'integration_name' => 'Cloudflare',
                'url' => 'https://dash.cloudflare.com/login',
                'icon' => 'cloud-lightning',
                'color' => '#f38020',
                'description' => 'CDN & Security Dashboard',
                'credentials' => [
                    [
                        'email' => 'security@example.com',
                        'username' => 'security',
                        'password' => 'Cloudflare@123',
                        'role' => 'Admin',
                        'is_default' => true
                    ]
                ]
            ]
        ];

        foreach ($dashboards as $dashboardData) {
            $credentials = $dashboardData['credentials'];
            unset($dashboardData['credentials']);
            
            $dashboard = AdminDashboard::create($dashboardData);
            
            foreach ($credentials as $credData) {
                $dashboard->credentials()->create($credData);
            }
        }
        
        $this->command->info('✅ ' . count($dashboards) . ' dashboards seeded successfully!');
    }
}