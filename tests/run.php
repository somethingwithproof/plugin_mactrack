<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

if (PHP_SAPI !== 'cli') {
	exit(1);
}

require_once __DIR__ . '/Support/ProcessRunner.php';
require_once __DIR__ . '/Support/TestInventory.php';

$groups = [
	'unit' => [
		__DIR__ . '/Unit/test_*.php',
	],
	'integration' => [
		__DIR__ . '/Integration/test_*.php',
	],
	'security' => [
		__DIR__ . '/Security/*Test.php',
	],
	'e2e-static' => [
		__DIR__ . '/e2e/test_*.php',
	],
];

if (getenv('MACTRACK_RUNNER_SELF_TEST') === '1') {
	$groups['empty-fixture'] = [
		__DIR__ . '/fixtures/test_noop.php',
		__DIR__ . '/fixtures/no-tests/test_*.php',
	];
	$groups['silent-fixture'] = [__DIR__ . '/fixtures/test_silent.php'];
	$groups['stderr-fixture'] = [__DIR__ . '/fixtures/test_large_stderr.php'];
	$groups['warning-fixture'] = [__DIR__ . '/fixtures/test_warning.php'];
}

$claimed_files = [];

foreach (array_slice($groups, 0, 4) as $patterns) {
	foreach ($patterns as $pattern) {
		$matches = glob($pattern);

		if ($matches !== false) {
			$claimed_files = array_merge($claimed_files, $matches);
		}
	}
}

$docker_tests = [
	__DIR__ . '/e2e/mactrack_scanning_functions.php',
	__DIR__ . '/e2e/mactrack_schema_idempotency.php',
	__DIR__ . '/e2e/mactrack_smoke.php',
];
$auxiliary_files = [
	__DIR__ . '/Support/CactiStubs.php',
	__DIR__ . '/Support/CliGuard.php',
	__DIR__ . '/Support/E2eDatabaseGuard.php',
	__DIR__ . '/Support/Php74Scanner.php',
	__DIR__ . '/Support/ProcessRunner.php',
	__DIR__ . '/Support/ProductionPhpManifest.php',
	__DIR__ . '/Support/SchemaManifest.php',
	__DIR__ . '/Support/SqlCallAnalyzer.php',
	__DIR__ . '/Support/StandaloneTest.php',
	__DIR__ . '/Support/TestInventory.php',
	__DIR__ . '/Support/TrackedPhpFiles.php',
	__DIR__ . '/fixtures/inventory/Security/ClaimedTest.php',
	__DIR__ . '/fixtures/inventory/Security/test_orphan.php',
	__DIR__ . '/fixtures/inventory/Unit/OrphanTest.php',
	__DIR__ . '/fixtures/inventory/Unit/test_claimed.php',
	__DIR__ . '/fixtures/test_large_stderr.php',
	__DIR__ . '/fixtures/test_noop.php',
	__DIR__ . '/fixtures/test_silent.php',
	__DIR__ . '/fixtures/test_warning.php',
];
$unclaimed = MactrackTestInventory::findUnclaimed(
	[__DIR__ . '/Unit', __DIR__ . '/Integration', __DIR__ . '/Security', __DIR__ . '/e2e', __DIR__ . '/Support', __DIR__ . '/fixtures'],
	array_merge($claimed_files, $auxiliary_files),
	$docker_tests
);

if ($unclaimed) {
	fwrite(STDERR, "Unclaimed PHP test files:\n" . implode("\n", $unclaimed) . "\n");
	exit(2);
}

$requested = array_slice($argv, 1);

if (!$requested || $requested === ['all']) {
	$requested = array_keys($groups);
}

$unknown = array_diff($requested, array_keys($groups));

if ($unknown) {
	fwrite(STDERR, 'Unknown test group(s): ' . implode(', ', $unknown) . PHP_EOL);
	fwrite(STDERR, 'Available groups: ' . implode(', ', array_keys($groups)) . PHP_EOL);
	exit(2);
}

$failures = 0;
$files_run = 0;

foreach ($requested as $group) {
	$files = [];
	print "\n[$group]\n";

	foreach ($groups[$group] as $pattern) {
		$matches = glob($pattern);

		if ($matches === false) {
			fwrite(STDERR, "Unable to enumerate test group: $group\n");
			exit(2);
		}

		if (!$matches) {
			$failures++;
			fwrite(STDERR, "No test files matched pattern: $pattern\n");
			continue;
		}

		$files = array_merge($files, $matches);
	}

	$files = array_values(array_unique($files));
	sort($files);

	foreach ($files as $file) {
		$files_run++;
		$command = [PHP_BINARY, '-d', 'error_reporting=E_ALL', '-d', 'display_errors=stderr', $file];
		$result    = MactrackProcessRunner::run($command);

		if (!$result['started']) {
			$failures++;
			fwrite(STDERR, 'Unable to start ' . basename($file) . ': ' . $result['error'] . "\n");
			continue;
		}

		$output = $result['output'];
		$error  = $result['error'];
		$status = $result['status'];

		print $output;

		if ($error !== '') {
			fwrite(STDERR, $error);
		}

		if ($status !== 0) {
			$failures++;
			fwrite(STDERR, basename($file) . " failed with status $status\n");
		} elseif ($error !== '') {
			$failures++;
			fwrite(STDERR, basename($file) . " wrote to stderr\n");
		} elseif (!preg_match('/[1-9][0-9]* assertions? passed(\R|$)/', $output)) {
			$failures++;
			fwrite(STDERR, basename($file) . " did not report any completed assertions\n");
		}
	}
}

print "\n$files_run test files, $failures failures\n";
exit($failures === 0 ? 0 : 1);
