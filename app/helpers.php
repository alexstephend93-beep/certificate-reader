<?php

if (!function_exists('getCategoryIconHtml')) {
    function getCategoryIconHtml($category) {
        $icons = [
            'apache' => '🐘',
            'ssl' => '🔒',
            'php' => '🐘',
            'composer' => '📦',
            'laravel' => '✨',
            'supervisor' => '👁️',
            'cron' => '⏰',
            'git' => '📝',
            'ssh' => '🔌',
            'nodejs' => '💚',
            'system' => '🖥️',
            'database' => '🗄️',
            'development' => '💻',
            'ubuntu' => '🐧',
            'linux' => '🐧',
            'mysql' => '🗄️',
            'docker' => '🐳'
        ];
        
        return $icons[strtolower($category)] ?? '📁';
    }
}