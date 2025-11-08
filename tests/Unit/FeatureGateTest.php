<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Utils\FeatureGate;

final class FeatureGateTest extends TestCase
{
    private string $configPath;
    private string $backupPath;

    protected function setUp(): void
    {
        // tests/Unit -> project root is two levels up
        $projectRoot = dirname(__DIR__, 2);
        $this->configPath = $projectRoot . '/config/config.php';
        $this->backupPath = $this->configPath . '.bak_featuregate_test';

        if (file_exists($this->configPath)) {
            copy($this->configPath, $this->backupPath);
        } else {
            // ensure config dir exists
            @mkdir(dirname($this->configPath), 0755, true);
        }
    }

    protected function tearDown(): void
    {
        // restore original config if backup exists
        if (file_exists($this->backupPath)) {
            rename($this->backupPath, $this->configPath);
        } else {
            if (file_exists($this->configPath)) {
                unlink($this->configPath);
            }
        }
    }

    public function testIsEnabledReturnsTrueWhenNotSpecified(): void
    {
        file_put_contents($this->configPath, "<?php\nreturn [];\n");
        $this->assertTrue(FeatureGate::isEnabled('paint'));
    }

    public function testIsEnabledRespectsFlag(): void
    {
        file_put_contents($this->configPath, "<?php\nreturn ['paint' => ['enabled' => false]];\n");
        $this->assertFalse(FeatureGate::isEnabled('paint'));

        file_put_contents($this->configPath, "<?php\nreturn ['paint' => ['enabled' => true]];\n");
        $this->assertTrue(FeatureGate::isEnabled('paint'));
    }

    public function testIsEnabledHandlesNonArrayConfig(): void
    {
        // write a config file that doesn't return an array
        file_put_contents($this->configPath, "<?php\nreturn 'invalid';\n");
        $this->assertTrue(FeatureGate::isEnabled('paint'));
    }
}
