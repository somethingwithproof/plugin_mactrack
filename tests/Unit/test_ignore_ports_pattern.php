<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

require_once __DIR__ . '/../Support/CactiStubs.php';
require_once __DIR__ . '/../../lib/mactrack_functions.php';

$default = '(Vlan|Loopback|Null)';
$failed  = 0;

foreach ([$default, '^(Gi|Te)[0-9/]+$', 'Port~Channel', 'Vlan\\~Trunk'] as $valid) {
	if (mactrack_validate_ignore_ports_pattern($valid) !== $valid) {
		fwrite(STDERR, 'Valid ignore-ports pattern changed: ' . $valid . "\n");
		$failed++;
	}
}

foreach (['', null, '(Vlan', '[a-'] as $invalid) {
	if (mactrack_validate_ignore_ports_pattern($invalid) !== $default) {
		fwrite(STDERR, 'Invalid ignore-ports pattern did not fall back: ' . var_export($invalid, true) . "\n");
		$failed++;
	}
}

$source = file_get_contents(__DIR__ . '/../../mactrack_view_interfaces.php');

if ($source === false ||
	strpos($source, 'mactrack_get_ignore_ports_pattern()') === false ||
	strpos($source, 'db_qstr($match)') === false) {
	fwrite(STDERR, "The Interfaces query must validate and preserve the configured RLIKE pattern\n");
	$failed++;
}

$GLOBALS['mactrack_test_config_options']['mt_ignorePorts'] = '(Vlan';
$GLOBALS['mactrack_test_db_calls'] = [];

if (mactrack_get_ignore_ports_pattern() !== $default || $GLOBALS['mactrack_test_db_calls'] !== []) {
	fwrite(STDERR, "A viewer fallback must not overwrite a non-empty administrator setting\n");
	$failed++;
}

$GLOBALS['mactrack_test_config_options']['mt_ignorePorts'] = '';
$GLOBALS['mactrack_test_db_calls'] = [];

if (mactrack_get_ignore_ports_pattern() !== $default || count($GLOBALS['mactrack_test_db_calls']) !== 1) {
	fwrite(STDERR, "An empty ignore-ports setting must be initialized once\n");
	$failed++;
}

if ($failed) {
	exit(1);
}

print "OK\n";
