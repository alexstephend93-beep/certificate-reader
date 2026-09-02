<?php
/**
 * SSH Config Cleanup Script
 * 
 * Removes all duplicated Host * global defaults blocks from SSH config,
 * keeping only ONE at the very end of the file.
 * 
 * Pattern to remove (appears after each host entry):
 *     # ============================================
 * # ============================================
 * # Global Defaults (applies to all hosts above)
 * # ============================================
 * Host *
 *     IdentitiesOnly yes
 *     PreferredAuthentications publickey
 *     PasswordAuthentication no
 *     PubkeyAcceptedKeyTypes +ssh-rsa
 *     HostKeyAlgorithms +ssh-rsa
 *     ServerAliveInterval 60
 *     Compression yes
 *     StrictHostKeyChecking accept-new
 *     ConnectTimeout 15
 *     ConnectionAttempts 2
 */

$configPath = getenv('HOME') . '/.ssh/config';

if (!file_exists($configPath)) {
    echo "ERROR: Config file not found at $configPath\n";
    exit(1);
}

$content = file_get_contents($configPath);
$originalLines = file($configPath, FILE_IGNORE_NEW_LINES);
$totalLines = count($originalLines);

echo "Original file: $totalLines lines\n";

// The pattern to identify the start of a Host * global defaults block
// We need to match lines like:
//     # ============================================
// OR
// # ============================================
// # Global Defaults (applies to all hosts above)
// # ============================================
// Host *
//     IdentitiesOnly yes
//     ...

// Strategy: Iterate through lines, when we find "Host *" followed by known global defaults,
// skip them. Keep track of whether we've seen the global defaults pattern.

$cleanedLines = [];
$skipBlock = false;
$foundHostStar = false;
$globalDefaultsCount = 0;
$lastHostStarStart = -1;
$lastHostStarLines = [];

// First pass: find all Host * blocks and mark them for removal (except the last one)
$lineCount = count($originalLines);
$i = 0;

while ($i < $lineCount) {
    $line = $originalLines[$i];
    $trimmed = trim($line);
    
    // Check if this line starts the global defaults section
    // Pattern: look for "Host *" 
    if (preg_match('/^Host\s+\*$/i', $trimmed)) {
        // Check if this is a global defaults block (followed by IdentitiesOnly, etc.)
        if (($i + 1 < $lineCount && preg_match('/^\s*IdentitiesOnly\s+/i', $originalLines[$i + 1])) ||
            ($i + 2 < $lineCount && preg_match('/^\s*IdentitiesOnly\s+/i', $originalLines[$i + 2]))) {
            
            // This is a Host * global defaults block
            $globalDefaultsCount++;
            $lastHostStarStart = count($cleanedLines);
            $lastHostStarLines = [];
            
            // Capture the entire block to check if it's the last one
            $blockStart = $i;
            while ($i < $lineCount) {
                $l = trim($originalLines[$i]);
                $lastHostStarLines[] = $originalLines[$i];
                $i++;
                // Stop when we hit a non-empty line that starts with "Host " (next host) 
                // or end of file
                if ($i < $lineCount && preg_match('/^Host\s+/i', trim($originalLines[$i])) && !preg_match('/^Host\s+\*$/i', trim($originalLines[$i]))) {
                    break;
                }
                // Also break if we hit an empty line after the block
                if ($i < $lineCount && empty(trim($originalLines[$i])) && 
                    $i + 1 < $lineCount && preg_match('/^Host\s+/i', trim($originalLines[$i + 1])) && 
                    !preg_match('/^Host\s+\*$/i', trim($originalLines[$i + 1]))) {
                    $i++;
                    $lastHostStarLines[] = $originalLines[$i - 1];
                    break;
                }
                if ($i >= $lineCount) break;
            }
            continue;
        }
    }
    
    $cleanedLines[] = $line;
    $i++;
}

echo "Found $globalDefaultsCount Host * global defaults blocks\n";

// Now check if the very last entries are the global defaults Host * block
// If they are, we need to add them back at the end
$trimmed = array_map('trim', $cleanedLines);
$endBlockStart = -1;

// Search from the end for Host *
for ($j = count($cleanedLines) - 1; $j >= 0; $j--) {
    if (preg_match('/^Host\s+\*$/i', trim($cleanedLines[$j]))) {
        // Found a Host * in the cleaned content - this shouldn't happen if all were removed
        // But just in case, let's check
        break;
    }
}

// If we found any Host * blocks, add ONE at the end
if ($globalDefaultsCount > 0 && $lastHostStarLines) {
    // Make sure there's a blank line before the final Host * block
    if (count($cleanedLines) > 0 && trim(end($cleanedLines)) !== '') {
        $cleanedLines[] = '';
    }
    
    // Add the header comment
    $cleanedLines[] = '# ============================================';
    $cleanedLines[] = '# Global Defaults (applies to all hosts above)';
    $cleanedLines[] = '# ============================================';
    $cleanedLines[] = 'Host *';
    $cleanedLines[] = '    IdentitiesOnly yes';
    $cleanedLines[] = '    PreferredAuthentications publickey';
    $cleanedLines[] = '    PasswordAuthentication no';
    $cleanedLines[] = '    PubkeyAcceptedKeyTypes +ssh-rsa';
    $cleanedLines[] = '    HostKeyAlgorithms +ssh-rsa';
    $cleanedLines[] = '    ServerAliveInterval 60';
    $cleanedLines[] = '    Compression yes';
    $cleanedLines[] = '    StrictHostKeyChecking accept-new';
    $cleanedLines[] = '    ConnectTimeout 15';
    $cleanedLines[] = '    ConnectionAttempts 2';
}

// Remove trailing empty lines
while (count($cleanedLines) > 0 && trim(end($cleanedLines)) === '') {
    array_pop($cleanedLines);
}

// Add a single trailing newline
$cleanedLines[] = '';

$newContent = implode("\n", $cleanedLines);
$newLineCount = count($cleanedLines) - 1; // Subtract the trailing empty line

// Create backup
$backupPath = $configPath . '.bak.' . date('Ymd_His');
file_put_contents($backupPath, $content);
echo "Backup created at: $backupPath\n";

// Write cleaned file
file_put_contents($configPath, $newContent);

echo "Cleaned file: $newLineCount lines\n";
echo "Removed approximately " . ($totalLines - $newLineCount) . " redundant lines\n";
echo "Done!\n";

