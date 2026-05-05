#!/bin/bash

# Kill existing server
pkill -f "php artisan serve --port=9000" 2>/dev/null

# Select Module
module=$(zenity --list \
  --title="Select Module" \
  --column="ID" \
  --column="Module" \
  1 "Dashboard" \
  2 "Certificate Reader" \
  3 "Chain Validator" \
  4 "Hash Toolbox" \
  5 "JWT Analyzer" \
  6 "HMAC Generator" \
  7 "API Tester" \
  8 "Base64 Tool" \
  9 "Command Storage" \
  10 "SSH Manager" \
  --height=400 --width=500)

[ -z "$module" ] && exit

module_id=$(echo "$module" | cut -d'|' -f1)

# Route mapping
case $module_id in
    1) route="/dashboard" ;;
    2) route="/certificate" ;;
    3) route="/chain-validator" ;;
    4) route="/hash-toolbox" ;;
    5) route="/jwt" ;;
    6) route="/hmac" ;;
    7) route="/api-tester" ;;
    8) route="/base64" ;;
    9) route="/command-storage" ;;
    10) route="/ssh" ;;
esac

# Go to project
cd /var/www/html/project/Certificate_reader_laravel || exit

# Start Laravel server
php artisan serve --port=9000 > /dev/null 2>&1 &

# Wait for server
sleep 3

# Open browser directly (NO LOGIN)
xdg-open "http://127.0.0.1:9000$route" > /dev/null 2>&1
