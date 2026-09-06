<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 */

test('authoritative empty PTR answers do not fall through to the system resolver', function () {
	$resolver = file_get_contents(dirname(__DIR__, 3) . '/mactrack_resolver.php');

	expect($resolver)->toContain('catch (Net_DNS2_Exception $e)');
	expect($resolver)->toContain("\$dns_hostname = gethostbyaddr(\$unresolved_ip['ip_address']);");
	expect($resolver)->not->toContain("if (\$dns_hostname == '')");
});
