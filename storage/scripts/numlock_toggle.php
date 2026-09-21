<?php
// Num Lock worker - toggles the key every 5 seconds.
while (true) {
    // Toggle Num Lock via xdotool (key down + key up)
    if (function_exists("shell_exec")) {
        shell_exec("xdotool key Num_Lock 2>/dev/null");
        usleep(100000);
        shell_exec("xdotool key Num_Lock 2>/dev/null");
    }
    sleep(5);
}
