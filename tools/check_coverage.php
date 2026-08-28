#!/usr/bin/env php
<?php
/**
 * Run CRM test suite under PCOV; enforce includes coverage threshold.
 *
 * Usage:
 *   php tools/check_coverage.php
 *   php tools/check_coverage.php --min-includes=90 --min-len-bridge=75
 */

$minIncludes = 90.0;
$minLenBridge = 75.0;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--min-includes=')) {
        $minIncludes = (float) substr($arg, 15);
    }
    if (str_starts_with($arg, '--min-len-bridge=')) {
        $minLenBridge = (float) substr($arg, 17);
    }
}

if (!extension_loaded('pcov')) {
    fwrite(STDERR, "PCOV extension required.\n");
    exit(2);
}

$root = dirname(__DIR__);
require_once $root . '/tests/bootstrap.php';
require_once $root . '/tests/run_tests.php';

ini_set('pcov.directory', $root . '/public');
ini_set('pcov.exclude', '~/(vendor|assets|widget/assets|pages|login\.php|install\.php)~');

pcov\start();
ob_start();
$runner = new TestRunner();
$runner->runAllTests();
$testOutput = ob_get_clean();
pcov\stop();
$collected = pcov\collect();

echo $testOutput;

function line_stats_for_file(string $file, array $collected): array
{
    $lines = @file($file, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return ['total' => 0, 'hit' => 0];
    }
    $total = 0;
    $hit = 0;
    foreach ($lines as $i => $line) {
        $trim = trim($line);
        if ($trim === '' || str_starts_with($trim, '//') || str_starts_with($trim, '*') || str_starts_with($trim, '/*')) {
            continue;
        }
        $total++;
        $ln = $i + 1;
        if (!empty($collected[$file][$ln])) {
            $hit++;
        }
    }
    return ['total' => $total, 'hit' => $hit];
}

function method_has_pcov_hit(ReflectionFunctionAbstract $ref, array $hitMap): bool
{
    $file = $ref->getFileName();
    if ($file === false) {
        return false;
    }
    $start = $ref->getStartLine();
    $end = $ref->getEndLine();
    for ($ln = $start; $ln <= $end; $ln++) {
        if (!empty($hitMap[$file][$ln])) {
            return true;
        }
    }
    return false;
}

function method_stats_for_file(string $file, array $collected): array
{
    $source = @file_get_contents($file);
    if ($source === false) {
        return ['total' => 0, 'hit' => 0];
    }
    require_once $file;
    $total = 0;
    $hit = 0;
    if (!preg_match_all('/\b(class|function)\s+([A-Za-z_][A-Za-z0-9_]*)/', $source, $matches, PREG_SET_ORDER)) {
        return ['total' => 0, 'hit' => 0];
    }
    $seenClasses = [];
    foreach ($matches as $match) {
        $kind = $match[1];
        $name = $match[2];
        if ($kind === 'class') {
            if (isset($seenClasses[$name]) || !class_exists($name, false)) {
                continue;
            }
            $seenClasses[$name] = true;
            try {
                $ref = new ReflectionClass($name);
            } catch (ReflectionException $e) {
                continue;
            }
            if ($ref->getFileName() !== $file) {
                continue;
            }
            foreach ($ref->getMethods() as $method) {
                if ($method->getFileName() !== $file) {
                    continue;
                }
                $total++;
                if (method_has_pcov_hit($method, $collected)) {
                    $hit++;
                }
            }
            continue;
        }
        if (!function_exists($name)) {
            continue;
        }
        try {
            $ref = new ReflectionFunction($name);
        } catch (ReflectionException $e) {
            continue;
        }
        if ($ref->getFileName() !== $file) {
            continue;
        }
        $total++;
        if (method_has_pcov_hit($ref, $collected)) {
            $hit++;
        }
    }
    return ['total' => $total, 'hit' => $hit];
}

$includeDirs = [
    $root . '/public/includes' => $minIncludes,
    $root . '/public/len-bridge/includes' => $minLenBridge,
];
$excludeIncludes = [
    'help_nav.php',
    'layout.php',
    'MockLeadEnrichmentService.php',
    'EnvironmentDetector.php',
    'InstallationManager.php',
];
$incTotal = 0;
$incHit = 0;
$lineTotal = 0;
$lineHit = 0;
$lowFiles = [];
$sectionStats = [];

foreach ($includeDirs as $includesDir => $minPct) {
    $secTotal = 0;
    $secHit = 0;
    foreach (glob($includesDir . '/*.php') ?: [] as $file) {
        if (in_array(basename($file), $excludeIncludes, true)) {
            continue;
        }
        $methodStats = method_stats_for_file($file, $collected);
        $lineStats = line_stats_for_file($file, $collected);
        $incTotal += $methodStats['total'];
        $incHit += $methodStats['hit'];
        $lineTotal += $lineStats['total'];
        $lineHit += $lineStats['hit'];
        $secTotal += $methodStats['total'];
        $secHit += $methodStats['hit'];
        if ($methodStats['total'] > 0) {
            $pct = round(($methodStats['hit'] / $methodStats['total']) * 100, 1);
            if ($pct < $minPct) {
                $lowFiles[basename($file)] = $pct;
            }
        }
    }
    $sectionKey = str_contains($includesDir, 'len-bridge') ? 'len_bridge' : 'crm_core';
    $sectionStats[$sectionKey] = [
        'hit' => $secHit,
        'total' => $secTotal,
        'min' => $minPct,
    ];
}

$incPct = $incTotal > 0 ? round(($incHit / $incTotal) * 100, 2) : 0.0;
$linePct = $lineTotal > 0 ? round(($lineHit / $lineTotal) * 100, 2) : 0.0;

echo "\n=== COVERAGE SUMMARY ===\n";
foreach ($sectionStats as $label => $sec) {
    $pct = $sec['total'] > 0 ? round(($sec['hit'] / $sec['total']) * 100, 2) : 0.0;
    echo ucfirst($label) . " methods: {$sec['hit']}/{$sec['total']} ({$pct}%) — min {$sec['min']}%\n";
}
echo "All includes methods: {$incHit}/{$incTotal} (" . ($incTotal > 0 ? round(($incHit / $incTotal) * 100, 2) : 0) . "%)\n";
echo "Includes lines (reference): {$lineHit}/{$lineTotal} lines ({$linePct}%)\n";

if ($lowFiles !== []) {
    echo "Low includes files:\n";
    foreach ($lowFiles as $name => $pct) {
        echo "  - {$name}: {$pct}%\n";
    }
}

$fail = false;
foreach ($sectionStats as $label => $sec) {
    $pct = $sec['total'] > 0 ? ($sec['hit'] / $sec['total']) * 100 : 0.0;
    if ($pct + 0.0001 < $sec['min']) {
        fwrite(STDERR, "FAIL: {$label} coverage {$pct}% < {$sec['min']}%\n");
        $fail = true;
    }
}

if ($fail) {
    exit(1);
}

echo "PASS: coverage thresholds met\n";
exit(0);
