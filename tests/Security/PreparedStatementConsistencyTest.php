<?php

if (PHP_SAPI !== 'cli') {
	exit(1);
}
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/* Existing raw and dynamically-built DB-call debt is explicit and may only decrease. */

require_once __DIR__ . '/../Support/StandaloneTest.php';
require_once __DIR__ . '/../Support/SqlCallAnalyzer.php';
require_once __DIR__ . '/../Support/TrackedPhpFiles.php';

	$raw_baseline = [
		'includes/database.php'         => 141,
		'lib/mactrack_3com.php'         => 1,
		'lib/mactrack_aruba_oscx.php'   => 1,
		'lib/mactrack_cisco.php'        => 5,
		'lib/mactrack_enterasys_N7.php' => 1,
		'lib/mactrack_extreme.php'      => 1,
		'lib/mactrack_functions.php'    => 25,
		'lib/mactrack_h3c_3com.php'     => 1,
		'mactrack_actions.php'          => 19,
		'mactrack_convert.php'          => 8,
		'mactrack_device_types.php'     => 11,
		'mactrack_devices.php'          => 7,
		'mactrack_macauth.php'          => 2,
		'mactrack_macwatch.php'         => 2,
		'mactrack_resolver.php'         => 3,
		'mactrack_scanner.php'          => 1,
		'mactrack_sites.php'            => 3,
		'mactrack_snmp.php'             => 4,
		'mactrack_utilities.php'        => 25,
		'mactrack_vendormacs.php'       => 2,
		'mactrack_view_arp.php'         => 7,
		'mactrack_view_devices.php'     => 4,
		'mactrack_view_dot1x.php'       => 6,
		'mactrack_view_graphs.php'      => 2,
		'mactrack_view_interfaces.php'  => 5,
		'mactrack_view_ips.php'         => 3,
		'mactrack_view_macs.php'        => 9,
		'mactrack_view_sites.php'       => 3,
		'poller_mactrack.php'           => 35,
		'setup.php'                     => 22,
	];
	$dynamic_baseline = [
		'includes/database.php'         => 2,
		'lib/mactrack_aruba_oscx.php'   => 1,
		'lib/mactrack_cisco.php'        => 5,
		'lib/mactrack_enterasys_N7.php' => 1,
		'lib/mactrack_extreme.php'      => 1,
		'lib/mactrack_functions.php'    => 9,
		'lib/mactrack_h3c_3com.php'     => 1,
		'mactrack_actions.php'          => 19,
		'mactrack_convert.php'          => 1,
		'mactrack_device_types.php'     => 8,
		'mactrack_devices.php'          => 5,
		'mactrack_macauth.php'          => 2,
		'mactrack_macwatch.php'         => 2,
		'mactrack_resolver.php'         => 1,
		'mactrack_sites.php'            => 3,
		'mactrack_snmp.php'             => 4,
		'mactrack_vendormacs.php'       => 2,
		'mactrack_view_arp.php'         => 3,
		'mactrack_view_devices.php'     => 2,
		'mactrack_view_dot1x.php'       => 3,
		'mactrack_view_interfaces.php'  => 4,
		'mactrack_view_ips.php'         => 2,
		'mactrack_view_macs.php'        => 6,
		'mactrack_view_sites.php'       => 3,
		'setup.php'                     => 12,
	];
	$dynamic_prepared_baseline = [
		'mactrack_devices.php'  => 3,
		'mactrack_view_macs.php' => 1,
		'poller_mactrack.php'   => 2,
	];
	$actual_raw     = [];
	$actual_dynamic = [];
	$actual_dynamic_prepared = [];
	$root        = realpath(__DIR__ . '/../..');

	foreach (MactrackTrackedPhpFiles::listRelative($root) as $relative) {
		if (preg_match('#(^|/)(tests|vendor)(/|$)#', $relative)) {
			continue;
		}

		$path = $root . '/' . $relative;
		$source = file_get_contents($path);
		MactrackStandaloneTest::assertTrue($source !== false, "$relative is readable for SQL analysis");
		$counts = MactrackSqlCallAnalyzer::count($source);
		$raw_count = $counts['raw'];
		$dynamic_count = $counts['dynamic_raw'];
		$dynamic_prepared_count = $counts['dynamic_prepared'];

		if ($raw_count) {
			$actual_raw[$relative] = $raw_count;
		}

		if ($dynamic_count) {
			$actual_dynamic[$relative] = $dynamic_count;
		}

		if ($dynamic_prepared_count) {
			$actual_dynamic_prepared[$relative] = $dynamic_prepared_count;
		}
	}

$matches_baseline = function ($actual, $baseline) {
	return $actual === $baseline;
};

MactrackStandaloneTest::assertTrue(!$matches_baseline(0, 1), 'a reduced SQL-debt count cannot bank baseline headroom');

foreach ($actual_raw as $file => $count) {
	MactrackStandaloneTest::assertTrue(isset($raw_baseline[$file]), "$file introduced raw DB calls without a baseline");
	MactrackStandaloneTest::assertTrue(isset($raw_baseline[$file]) && $matches_baseline($count, $raw_baseline[$file]), "$file raw DB-call count changed; update the baseline in the same reviewed change");
}

foreach ($actual_dynamic as $file => $count) {
	MactrackStandaloneTest::assertTrue(isset($dynamic_baseline[$file]), "$file introduced dynamically-built raw SQL without a baseline");
	MactrackStandaloneTest::assertTrue(isset($dynamic_baseline[$file]) && $matches_baseline($count, $dynamic_baseline[$file]), "$file dynamically-built raw SQL count changed; update the baseline in the same reviewed change");
}

foreach ($actual_dynamic_prepared as $file => $count) {
	MactrackStandaloneTest::assertTrue(isset($dynamic_prepared_baseline[$file]), "$file introduced dynamically-built prepared SQL without a baseline");
	MactrackStandaloneTest::assertTrue(isset($dynamic_prepared_baseline[$file]) && $matches_baseline($count, $dynamic_prepared_baseline[$file]), "$file dynamically-built prepared SQL count changed; update the baseline in the same reviewed change");
}

foreach ([
	'raw' => [$raw_baseline, $actual_raw],
	'dynamically-built raw' => [$dynamic_baseline, $actual_dynamic],
	'dynamically-built prepared' => [$dynamic_prepared_baseline, $actual_dynamic_prepared],
] as $kind => $maps) {
	foreach ($maps[0] as $file => $count) {
		MactrackStandaloneTest::assertTrue(isset($maps[1][$file]), "$file has a stale $kind SQL baseline; delete the entry");
	}
}

MactrackStandaloneTest::finish('MacTrack SQL construction ratchets');
