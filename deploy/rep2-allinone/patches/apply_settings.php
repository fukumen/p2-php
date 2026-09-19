<?php
// Simple script to apply replacements based on a custom text format.
// This script utilizes str_replace which is binary safe, preserving encodings like Shift-JIS.

$settings_file = $argv[1] ?? 'settings.txt';
$base_dir = $argv[2] ?? '.';

if (!file_exists($settings_file)) {
    echo "Settings file not found: $settings_file\n";
    exit(1);
}

$lines = file($settings_file, FILE_IGNORE_NEW_LINES);
if ($lines === false) {
    echo "Failed to read settings file.\n";
    exit(1);
}

$current_file = '';
$search = '';
$replacements = []; // Format: [filepath => [ ['search' => ..., 'replace' => ...] ] ]

foreach ($lines as $line) {
    $line_trimmed = trim($line);
    if ($line_trimmed === '' || strpos($line_trimmed, '#') === 0) {
        continue; // skip empty and comment lines
    }

    if (preg_match('/^\[(.*?)\]$/', $line_trimmed, $matches)) {
        $current_file = $matches[1];
        if (!isset($replacements[$current_file])) {
            $replacements[$current_file] = [];
        }
    } elseif (strpos($line, '- ') === 0) {
        $search = substr($line, 2);
    } elseif (strpos($line, '+ ') === 0) {
        $replace = substr($line, 2);
        if ($current_file && $search !== '') {
            $replacements[$current_file][] = [
                'search' => $search,
                'replace' => $replace
            ];
            $search = ''; // reset
        }
    }
}

$all_success = true;

foreach ($replacements as $filepath => $rules) {
    $full_path = $base_dir . '/' . $filepath;
    if (!file_exists($full_path)) {
        echo "File not found: $full_path\n";
        $all_success = false;
        continue;
    }

    $content = file_get_contents($full_path);
    if ($content === false) {
        echo "Failed to read file: $full_path\n";
        $all_success = false;
        continue;
    }

    $modified = false;
    foreach ($rules as $rule) {
        $new_content = str_replace($rule['search'], $rule['replace'], $content);
        if ($new_content !== $content) {
            $content = $new_content;
            $modified = true;
        } else {
            echo "Warning: Search string not found in $filepath: " . $rule['search'] . "\n";
        }
    }

    if ($modified) {
        if (file_put_contents($full_path, $content) !== false) {
            echo "Updated: $filepath\n";
        } else {
            echo "Failed to write to file: $full_path\n";
            $all_success = false;
        }
    }
}

if (!$all_success) {
    exit(1);
}
