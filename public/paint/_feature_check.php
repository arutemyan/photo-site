<?php
// Paint feature gate - centralized via App\Utils\FeatureGate
require_once __DIR__ . '/../../vendor/autoload.php';
use App\Utils\FeatureGate;

FeatureGate::ensureEnabled('paint');

?>
