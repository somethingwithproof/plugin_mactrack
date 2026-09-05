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

function mactrack_db_key_exists($table, $key) {
	return $GLOBALS['__test_mactrack_key_exists'] ?? false;
}

function mactrack_add_column($table, $column, $sql) {
}

function mactrack_add_index($table, $index, $sql) {
}

function mactrack_execute_sql($message, $sql) {
}

require_once __DIR__ . '/../../includes/database.php';

$sites = [];
$queries = [];
$site_count = function ($sql, $params) use (&$sites, &$queries) {
	$queries[] = ['sql' => $sql, 'params' => $params];

	return (string) count($sites);
};
$GLOBALS['__test_db_fetch_cell_prepared'] = $site_count;
$insert_result = true;
$insert_changes_table = true;
$GLOBALS['__test_db_execute_prepared'] = function ($sql, $params) use (&$sites, &$insert_result, &$insert_changes_table) {
	if (strpos($sql, 'INSERT INTO mac_track_sites') !== false) {
		if ($insert_result && $insert_changes_table && !$sites) {
			$sites[] = ['site_name' => $params[0], 'site_info' => $params[1]];
		}

		return $insert_result;
	}

	return true;
};
$column_type = 'varchar(30)';
$GLOBALS['__test_db_fetch_assoc'] = function ($sql) use (&$column_type) {
	if (strpos($sql, 'SHOW COLUMNS FROM mac_track_ips') !== false) {
		return [['Field' => 'port_number', 'Type' => $column_type]];
	}

	return [];
};

MactrackStandaloneTest::assertSame(
	['port_number' => 'int(10)'],
	array_rekey([['Field' => 'port_number', 'Type' => 'int(10)']], 'Field', 'Type'),
	'the array_rekey double preserves Cacti key/value semantics'
);

mactrack_seed_default_site();
MactrackStandaloneTest::assertSame(
	[['site_name' => 'Default', 'site_info' => 'Default site']],
	$sites,
	'an empty sites table receives the Default site'
);
$insert_queries = array_values(array_filter($GLOBALS['__test_db_calls'], function ($query) {
	return $query['fn'] === 'db_execute_prepared' && strpos($query['sql'], 'INTO mac_track_sites') !== false;
}));
MactrackStandaloneTest::assertContains('WHERE NOT EXISTS', $insert_queries[0]['sql'], 'the helper uses one statement-scoped conditional insert');
MactrackStandaloneTest::assertSame(['Default', 'Default site'], $insert_queries[0]['params'], 'the conditional insert binds the Default-site values');

mactrack_seed_default_site();
MactrackStandaloneTest::assertSame(1, count($sites), 'repeated seeding does not duplicate Default');
$GLOBALS['__test_messages'] = [];
MactrackStandaloneTest::assertSame(true, mactrack_ensure_default_site(), 'successful Default-site assurance returns success');
MactrackStandaloneTest::assertSame([], $GLOBALS['__test_messages'], 'successful Default-site assurance raises no operator error');
$ensure_signature = new ReflectionFunction('mactrack_ensure_default_site');
MactrackStandaloneTest::assertSame('bool', (string) $ensure_signature->getReturnType(), 'the notification helper has an explicit boolean contract');

$sites = [];
$insert_result = false;
MactrackStandaloneTest::assertSame(false, mactrack_seed_default_site(), 'a failed Default-site insert is returned to the caller');
MactrackStandaloneTest::assertSame([], $sites, 'a failed insert does not change simulated table state');
$insert_result = true;

$sites = [['site_name' => 'Custom', 'site_info' => 'Administrator site']];
mactrack_seed_default_site();
MactrackStandaloneTest::assertSame(
	[['site_name' => 'Custom', 'site_info' => 'Administrator site']],
	$sites,
	'a custom-only site table does not resurrect Default'
);

$sites = [['site_name' => 'Default', 'site_info' => 'Default site']];
mactrack_seed_default_site();
MactrackStandaloneTest::assertSame(1, count($sites), 'an existing Default site remains singular');

foreach ([false, null, ''] as $failed_count) {
	$sites = [];
	$insert_result = false;
	$GLOBALS['__test_logs'] = [];
	$GLOBALS['__test_db_fetch_cell_prepared'] = function () use ($failed_count) {
		return $failed_count;
	};
	$result = mactrack_seed_default_site();
	MactrackStandaloneTest::assertSame(0, count($sites), 'a failed insert and invalid verification query do not seed a site');
	MactrackStandaloneTest::assertSame(false, $result, 'a failed or invalid count query returns failure');
	MactrackStandaloneTest::assertSame('MACTRACK', $GLOBALS['__test_logs'][0]['type'], 'a failed count query is logged for MacTrack operators');
}

$GLOBALS['__test_db_fetch_cell_prepared'] = $site_count;
$insert_result = false;
$sites = [['site_name' => 'Default', 'site_info' => 'Concurrent winner']];
$GLOBALS['__test_logs'] = [];
MactrackStandaloneTest::assertSame(true, mactrack_seed_default_site(), 'a failed racing insert succeeds when another caller established the postcondition');
MactrackStandaloneTest::assertSame([], $GLOBALS['__test_logs'], 'a benign lost insert race does not raise an operator error');

$sites = [];
$insert_result = true;
$insert_changes_table = false;
$GLOBALS['__test_logs'] = [];
MactrackStandaloneTest::assertSame(false, mactrack_seed_default_site(), 'an executed insert without a resulting site fails postcondition verification');
MactrackStandaloneTest::assertSame('MACTRACK', $GLOBALS['__test_logs'][0]['type'], 'an unmet seed postcondition is logged');
$insert_changes_table = true;

$sites = [];
$insert_result = false;
$GLOBALS['__test_logs'] = [];
$GLOBALS['__test_messages'] = [];
MactrackStandaloneTest::assertSame(false, mactrack_setup_database(), 'database setup returns a failed Default-site seed to CLI callers');
MactrackStandaloneTest::assertContains('Unable to insert', $GLOBALS['__test_logs'][0]['message'], 'database setup surfaces a failed Default-site seed');
MactrackStandaloneTest::assertSame([], $GLOBALS['__test_messages'], 'database setup does not attempt a web message in CLI mode');
$GLOBALS['__test_messages'] = [];
MactrackStandaloneTest::assertSame(false, mactrack_database_upgrade(), 'database upgrade returns a failed Default-site seed to CLI callers');
MactrackStandaloneTest::assertSame([], $GLOBALS['__test_messages'], 'database upgrade does not attempt a web message in CLI mode');
$insert_result = true;

$sites = [['site_name' => 'Custom', 'site_info' => 'Administrator site']];
mactrack_database_upgrade();
MactrackStandaloneTest::assertSame(
	[['site_name' => 'Custom', 'site_info' => 'Administrator site']],
	$sites,
	'the database upgrade path does not resurrect Default in a populated table'
);

$column_type = 'int(10)';
$GLOBALS['__test_db_calls'] = [];
mactrack_database_upgrade();
$executed_sql = array_column(array_filter($GLOBALS['__test_db_calls'], function ($call) {
	return $call['fn'] === 'db_execute';
}), 'sql');
MactrackStandaloneTest::assertTrue(
	in_array("ALTER TABLE mac_track_ips MODIFY COLUMN port_number varchar(20) NOT NULL default ''", $executed_sql, true),
	'the upgrade converts integer port numbers to varchar(20)'
);

$column_type = 'varchar(20)';
$GLOBALS['__test_db_calls'] = [];
mactrack_database_upgrade();
$executed_sql = array_column(array_filter($GLOBALS['__test_db_calls'], function ($call) {
	return $call['fn'] === 'db_execute';
}), 'sql');
MactrackStandaloneTest::assertTrue(
	in_array("ALTER TABLE mac_track_ports MODIFY COLUMN port_number varchar(30) NOT NULL default ''", $executed_sql, true),
	'the upgrade expands varchar(20) port numbers to varchar(30)'
);

MactrackStandaloneTest::finish('MacTrack Default-site idempotency');
