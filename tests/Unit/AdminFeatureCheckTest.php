<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AdminFeatureCheckTest extends TestCase
{
    public function testProceduralAdminFilesIncludeFeatureCheck(): void
    {
        // Determine project root. Prefer PROJECT_ROOT from bootstrap if available.
        if (defined('PROJECT_ROOT')) {
            $root = PROJECT_ROOT;
        } else {
            // __DIR__ is tests/Unit -> go up two levels to project root
            $root = dirname(__DIR__, 2);
        }

        $adminDir = $root . '/public/admin';

        $this->assertDirectoryExists($adminDir, 'public/admin directory must exist');

        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($adminDir));
        $missing = [];

        foreach ($rii as $file) {
            if ($file->isDir()) {
                continue;
            }
            if ($file->getExtension() !== 'php') {
                continue;
            }
            // skip the feature file itself
            if ($file->getBasename() === '_feature_check.php') {
                continue;
            }

            $content = file_get_contents($file->getPathname());
            if ($content === false) {
                continue;
            }

            // if file declares a class, skip (class-based controllers handled by AdminControllerBase)
            if (preg_match('/^\s*class\s+/m', $content)) {
                continue;
            }

            // procedural file must include feature check
            if (strpos($content, "_feature_check.php") === false) {
                $missing[] = $file->getPathname();
            }
        }

        $this->assertEmpty(
            $missing,
            'The following procedural admin files are missing require of _feature_check.php:' . PHP_EOL . implode(PHP_EOL, $missing)
        );
    }
}
