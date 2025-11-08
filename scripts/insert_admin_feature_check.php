<?php
// scripts/insert_admin_feature_check.php
// Usage:
//   php insert_admin_feature_check.php --preview   # show files and preview of insertion
//   php insert_admin_feature_check.php --apply     # apply changes (creates .bak backups)

declare(strict_types=1);

$root = realpath(__DIR__ . '/..');
$adminDir = $root . '/public/admin';

if (!is_dir($adminDir)) {
    fwrite(STDERR, "admin directory not found: {$adminDir}\n");
    exit(1);
}

$args = array_slice($argv, 1);
$mode = in_array('--apply', $args, true) ? 'apply' : 'preview';

// collect php files
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($adminDir));
$candidates = [];
foreach ($rii as $file) {
    if ($file->isDir()) continue;
    $path = $file->getPathname();
    if (pathinfo($path, PATHINFO_EXTENSION) !== 'php') continue;
    // skip the feature file itself
    if (basename($path) === '_feature_check.php') continue;
    $candidates[] = $path;
}

$toChange = [];
foreach ($candidates as $file) {
    $content = file_get_contents($file);
    if ($content === false) continue;

    // already contains feature check?
    if (strpos($content, "_feature_check.php") !== false) continue;

    // if file declares a class, skip (class-based controllers handled elsewhere)
    if (preg_match('/^\s*class\s+/m', $content)) continue;

    // procedural file -> prepare insertion
    $lines = preg_split("/\r?\n/", $content);

    // find last require/include within the top 40 lines
    $insertLine = 0;
    $maxCheck = min(40, count($lines));
    for ($i = 0; $i < $maxCheck; $i++) {
        if (preg_match('/\b(require_once|require|include_once|include)\b/', $lines[$i])) {
            $insertLine = $i + 1;
        }
    }

    // fallback: after <?php and optional declare line
    if ($insertLine === 0) {
        if (isset($lines[0]) && preg_match('/<\?php/', $lines[0])) {
            $insertLine = 1;
            if (isset($lines[1]) && strpos($lines[1], 'declare(') !== false) {
                $insertLine = 2;
            }
        }
    }

    if ($insertLine === 0) {
        // give up (unusual file)
        continue;
    }

    $snippet = "require_once(__DIR__ . '/_feature_check.php');";
    $toChange[] = [
        'file' => $file,
        'insert_after' => $insertLine,
        'snippet' => $snippet,
        'orig' => $content,
    ];
}

if (empty($toChange)) {
    echo "No procedural admin files need insertion.\n";
    exit(0);
}

// Preview
echo "Found " . count($toChange) . " procedural admin files to modify:\n\n";
foreach ($toChange as $item) {
    $file = $item['file'];
    $insertAfter = $item['insert_after'];
    echo "- {$file} (insert after line {$insertAfter})\n";
}

if ($mode === 'preview') {
    echo "\nPreview snippets (first match per file):\n\n";
    foreach ($toChange as $item) {
        $file = $item['file'];
        $lines = preg_split("/\r?\n/", $item['orig']);
        $i = $item['insert_after'];
        $before = implode("\n", array_slice($lines, max(0, $i-3), 6));
        echo "--- {$file} ---\n";
        echo $before . "\n";
        echo "+ " . $item['snippet'] . "\n\n";
    }
    echo "Run with --apply to modify files (backups will be created with .bak.TIMESTAMP).\n";
    exit(0);
}

// apply mode
foreach ($toChange as $item) {
    $file = $item['file'];
    $lines = preg_split("/\r?\n/", $item['orig']);
    $i = $item['insert_after'];
    array_splice($lines, $i, 0, $item['snippet']);
    $new = implode("\n", $lines);

    // backup
    $bak = $file . '.bak.' . time();
    if (!copy($file, $bak)) {
        fwrite(STDERR, "Failed to backup {$file} -> {$bak}\n");
        continue;
    }

    if (file_put_contents($file, $new) === false) {
        fwrite(STDERR, "Failed to write {$file}\n");
        // attempt restore
        copy($bak, $file);
        continue;
    }

    echo "Patched: {$file} (backup: {$bak})\n";
}

echo "Done.\n";
