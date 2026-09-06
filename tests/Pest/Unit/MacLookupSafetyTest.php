<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 */

require_once dirname(__DIR__, 3) . '/lib/mactrack_functions.php';

test('empty MAC addresses cannot become wildcard lookups', function () {
	expect(db_check_for_ip(''))->toBeFalse();
	expect(db_check_auth(''))->toBeFalse();
});

test('ARP collection skips lookups for empty MAC addresses', function () {
	$functions = file_get_contents(dirname(__DIR__, 3) . '/lib/mactrack_functions.php');
	$h3c       = file_get_contents(dirname(__DIR__, 3) . '/lib/mactrack_h3c_3com.php');

	expect($functions)->toContain("\$atEntry['atNetAddress'] == '' && \$atEntry['atPhysAddress'] !== ''");
	expect($h3c)->toContain("xform_mac_address(\$mac_address[\$key] ?? '')");
	expect($h3c)->toContain("\$tmpmac !== '' ? db_fetch_cell_prepared");
});
