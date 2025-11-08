<?php
// replace_illust_to_paint.php
// Usage:
//   php scripts/replace_illust_to_paint.php      -> dry-run (list matches)
//   php scripts/replace_illust_to_paint.php --apply -> apply replacements (backups made)

$root = dirname(__DIR__);
$apply = in_array('--apply', $argv, true);

$excludeDirs = [
    $root . '/vendor',
    $root . '/node_modules',
    $root . '/uploads',
    $root . '/logs',
    $root . '/releases',
    $root . '/public/paint/js/*.bundle.js',
    $root . '/.git',
];

// File extensions to process
$exts = ['php','js','md','json','yml','yaml','txt','html','css'];

// Replacement mapping (regex => replacement)
$map = [
    // Class/name level
    '/\bIllust\b/' => 'Paint',
    '/\bIllusts\b/' => 'Paints',
    // lowercase identifiers and table/column names
    '/\billusts\b/' => 'paint',
    '/\billust_id\b/' => 'paint_id',
    '/\billusts_/' => 'paint_'
];

// We intentionally DO NOT replace ".illust" (file extension) or filenames inside uploads.

function isExcluded($path, $excludeDirs) {
    foreach ($excludeDirs as $ex) {
        // allow simple wildcard for bundle.js pattern
        if (strpos($ex, '*.bundle.js') !== false) {
            $prefix = str_replace('/*.bundle.js', '', $ex);
            if (strpos($path, $prefix) === 0 && substr($path, -11) === '.bundle.js') return true;
        }
        if (strpos($path, $ex) === 0) return true;
    }
    return false;
}

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
$files = [];
foreach ($iterator as $file) {
    if (!$file->isFile()) continue;
    $path = $file->getPathname();
    // Skip dotfiles and excluded dirs
    foreach (['/.git', '/vendor', '/node_modules', '/uploads', '/logs', '/releases'] as $skip) {
        if (strpos($path, $skip) !== false) {
            continue 2;
        }
    }
    // Skip built bundles (we do not modify generated bundle files)
    if (preg_match('/\\.bundle\\.js(\\.map)?$/', $path)) {
        continue;
    }
    $ext = pathinfo($path, PATHINFO_EXTENSION);
    if (!in_array($ext, $exts)) continue;
    $files[] = $path;
}

$report = [];
$totalMatches = 0;
foreach ($files as $file) {
    $content = file_get_contents($file);
    $fileMatches = 0;
    foreach ($map as $re => $rep) {
        if (preg_match_all($re, $content, $m)) {
            $count = count($m[0]);
            $fileMatches += $count;
        }
    }
    if ($fileMatches > 0) {
        $report[$file] = $fileMatches;
        $totalMatches += $fileMatches;
    }
}

// Print summary
echo "Scanned files: " . count($files) . PHP_EOL;
echo "Files with matches: " . count($report) . PHP_EOL;
echo "Total matches: " . $totalMatches . PHP_EOL . PHP_EOL;

if (!$apply) {
    echo "--- Matches by file (dry-run) ---\n";
    foreach ($report as $f => $count) {
        echo sprintf("%5d  %s\n", $count, substr($f, strlen($root)+1));
    }
    echo "\nTo apply changes, run: php scripts/replace_illust_to_paint.php --apply\n";
    exit(0);
}

// Apply replacements
echo "Applying replacements (backups will be saved as .bak)\n";
$changedFiles = 0;
foreach ($report as $file => $count) {
    $content = file_get_contents($file);
    $new = $content;
    foreach ($map as $re => $rep) {
        $new = preg_replace($re, $rep, $new);
    }
    if ($new !== $content) {
        // backup
        copy($file, $file . '.bak');
        file_put_contents($file, $new);
        echo "Updated: " . substr($file, strlen($root)+1) . " (matches: $count)\n";
        $changedFiles++;
    }
}

echo "Done. Files changed: $changedFiles\n";
echo "NOTE: Please run 'php -l' on modified PHP files and run your test suite. Backups with .bak are available.\n";

