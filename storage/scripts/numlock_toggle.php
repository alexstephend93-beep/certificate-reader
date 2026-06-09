<?php
$countFile = "/var/www/html/project/Certificate_reader_laravel/storage/numlock_count.txt";
$pidFile = "/var/www/html/project/Certificate_reader_laravel/storage/numlock_toggle.pid";
file_put_contents($pidFile, getmypid());
$i = 0;
while (true) {
    $i++;
    file_put_contents($countFile, $i);
    // Try to toggle Num Lock using different methods
    if (function_exists("shell_exec")) {
        shell_exec("xdotool key Num_Lock 2>/dev/null");
        usleep(100000);
        shell_exec("xdotool key Num_Lock 2>/dev/null");
    }
    sleep(5);
}
