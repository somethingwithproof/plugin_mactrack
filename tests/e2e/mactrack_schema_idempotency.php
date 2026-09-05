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

if (getenv('MACTRACK_E2E') !== '1') {
	fwrite(STDERR, "This database-mutating check is restricted to the disposable E2E environment\n");
	exit(2);
}

require_once __DIR__ . '/../../../../include/cli_check.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../Support/E2eDatabaseGuard.php';

global $database_default;

if (!MactrackE2eDatabaseGuard::isDisposable($database_default, getenv('DB_NAME'))) {
	fwrite(STDERR, "Refusing destructive schema checks outside the dedicated mactrack_e2e database\n");
	exit(2);
}

$before = db_fetch_cell_prepared(
	'SELECT COUNT(*) FROM mac_track_sites WHERE site_name = ?',
	['Default']
);

if ((int) $before !== 1) {
	fwrite(STDERR, "Plugin install did not seed exactly one Default site (found $before)\n");
	exit(1);
}

mactrack_setup_database();
$after = db_fetch_cell_prepared(
	'SELECT COUNT(*) FROM mac_track_sites WHERE site_name = ?',
	['Default']
);

if ((int) $after !== (int) $before) {
	fwrite(STDERR, "Schema setup is not idempotent: Default site count changed from $before to $after\n");
	exit(1);
}

db_execute_prepared(
	'INSERT INTO mac_track_sites (site_name, site_info) VALUES (?, ?)',
	['MacTrack E2E custom site', 'Default-site resurrection guard']
);
db_execute_prepared('DELETE FROM mac_track_sites WHERE site_name = ?', ['Default']);
mactrack_setup_database();
$resurrected = db_fetch_cell_prepared(
	'SELECT COUNT(*) FROM mac_track_sites WHERE site_name = ?',
	['Default']
);

db_execute_prepared('DELETE FROM mac_track_sites WHERE site_name = ?', ['MacTrack E2E custom site']);
db_execute_prepared('DELETE FROM mac_track_sites WHERE site_name = ?', ['Default']);
db_execute_prepared(
	'INSERT INTO mac_track_sites (site_name, site_info) VALUES (?, ?)',
	['Default', 'Default site']
);

if ((int) $resurrected !== 0) {
	fwrite(STDERR, "Schema setup resurrected the deleted Default site\n");
	exit(1);
}

print "Mactrack schema idempotency passed\n";
