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

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../Support/StandaloneTest.php';

function api_plugin_register_hook() {
}

function api_plugin_register_realm() {
}

function api_plugin_is_enabled() {
	return false;
}

function api_plugin_enable_hooks() {
}

function get_current_page() {
	return 'plugins.php';
}

$root = realpath(__DIR__ . '/../..');
$fixture_root = sys_get_temp_dir() . '/mactrack-setup-' . bin2hex(random_bytes(8));
mkdir($fixture_root . '/plugins', 0777, true);
symlink($root, $fixture_root . '/plugins/mactrack');
$config = ['base_path' => $fixture_root];

require_once $root . '/setup.php';

$GLOBALS['database_default'] = 'mactrack_unit';
$GLOBALS['__test_db_fetch_cell_prepared'] = function () {
	return '0';
};
$GLOBALS['__test_db_execute_prepared'] = function () {
	return false;
};
$GLOBALS['__test_db_fetch_assoc'] = function ($sql) {
	if (strpos($sql, 'SHOW COLUMNS FROM mac_track_ips') !== false) {
		return [['Field' => 'port_number', 'Type' => 'varchar(30)']];
	}

	return [];
};

MactrackStandaloneTest::assertSame(false, plugin_mactrack_install(), 'plugin installation returns a failed Default-site seed to the CLI caller');
MactrackStandaloneTest::assertSame([], $GLOBALS['__test_messages'], 'CLI installation does not attempt a web message');

$GLOBALS['__test_messages'] = [];
mactrack_check_upgrade();
MactrackStandaloneTest::assertSame([], $GLOBALS['__test_messages'], 'the CLI upgrade path does not attempt a web message');
MactrackStandaloneTest::assertTrue(count($GLOBALS['__test_logs']) >= 2, 'the CLI install and upgrade failures are recorded in the Cacti log');

unlink($fixture_root . '/plugins/mactrack');
rmdir($fixture_root . '/plugins');
rmdir($fixture_root);

MactrackStandaloneTest::finish('MacTrack CLI setup failure propagation');
