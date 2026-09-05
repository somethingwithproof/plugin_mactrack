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

require_once __DIR__ . '/../../includes/database.php';

$manifest = require __DIR__ . '/../Support/SchemaManifest.php';

$GLOBALS['__test_db_fetch_cell_prepared'] = function () {
	return '0';
};
mactrack_setup_database();

$definitions = $GLOBALS['__test_table_definitions'];
$actual      = array_keys($definitions);
$expected    = $manifest['tables'];

sort($actual);
sort($expected);

MactrackStandaloneTest::assertSame($expected, $actual, 'schema builder creates exactly the documented MacTrack tables');

$site_table_event = null;
$site_insert_event = null;

foreach ($GLOBALS['__test_db_calls'] as $index => $call) {
	if ($call['fn'] === 'api_plugin_db_table_create' && $call['table'] === 'mac_track_sites') {
		$site_table_event = $index;
	}

	if ($call['fn'] === 'db_execute_prepared' && strpos($call['sql'], 'INTO mac_track_sites') !== false) {
		$site_insert_event = $index;
	}
}

MactrackStandaloneTest::assertTrue($site_table_event !== null, 'schema setup creates the sites table');
MactrackStandaloneTest::assertTrue($site_insert_event !== null, 'schema setup seeds the Default site');
MactrackStandaloneTest::assertTrue($site_table_event < $site_insert_event, 'schema setup creates the sites table before seeding it');

foreach ($definitions as $table => $definition) {
	MactrackStandaloneTest::assertSame('InnoDB', $definition['type'], "$table uses InnoDB");
	MactrackStandaloneTest::assertTrue(!empty($definition['columns']), "$table declares columns");
	MactrackStandaloneTest::assertTrue(isset($definition['primary']), "$table declares a primary key");

	$column_names = array_column($definition['columns'], 'name');

	MactrackStandaloneTest::assertSame(count($column_names), count(array_unique($column_names)), "$table does not declare duplicate columns");
}

foreach ($manifest['critical_columns'] as $table => $columns) {
	$column_names = array_column($definitions[$table]['columns'], 'name');

	foreach ($columns as $column) {
		MactrackStandaloneTest::assertTrue(in_array($column, $column_names, true), "$table defines critical column $column");
	}
}

foreach ($manifest['critical_indexes'] as $table => $indexes) {
	$declared_indexes = ['PRIMARY'];

	foreach (array_merge($definitions[$table]['keys'] ?? [], $definitions[$table]['unique_keys'] ?? []) as $index) {
		$declared_indexes[] = $index['name'];
	}

	foreach ($indexes as $index) {
		MactrackStandaloneTest::assertTrue(in_array($index, $declared_indexes, true), "$table defines critical index $index");
	}
}

MactrackStandaloneTest::finish('MacTrack schema definition');
