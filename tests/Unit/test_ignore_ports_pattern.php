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

foreach ([$default, '^(Gi|Te)[0-9/]+$', 'Port~Channel'] as $valid) {
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
	strpos($source, 'mactrack_validate_ignore_ports_pattern($stored_match)') === false ||
	strpos($source, 'db_qstr($match)') === false) {
	fwrite(STDERR, "The Interfaces query must validate and preserve the configured RLIKE pattern\n");
	$failed++;
}

if ($failed) {
	exit(1);
}

print "OK\n";
