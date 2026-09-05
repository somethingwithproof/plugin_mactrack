<?php

if (PHP_SAPI !== 'cli') {
	exit(1);
}

require_once __DIR__ . '/../Support/StandaloneTest.php';
require_once __DIR__ . '/../Support/E2eDatabaseGuard.php';

MactrackStandaloneTest::assertTrue(MactrackE2eDatabaseGuard::isDisposable('mactrack_e2e', 'mactrack_e2e'), 'the dedicated default E2E database is accepted');
MactrackStandaloneTest::assertTrue(MactrackE2eDatabaseGuard::isDisposable('mactrack_e2e_42', 'mactrack_e2e_42'), 'a dedicated per-run E2E database is accepted');
MactrackStandaloneTest::assertTrue(!MactrackE2eDatabaseGuard::isDisposable('cacti', 'cacti'), 'a matching production-style database is rejected');
MactrackStandaloneTest::assertTrue(!MactrackE2eDatabaseGuard::isDisposable('cacti', 'mactrack_e2e'), 'a configured/expected mismatch is rejected');
MactrackStandaloneTest::assertTrue(!MactrackE2eDatabaseGuard::isDisposable('mactrack_e2e', ''), 'an empty expected database is rejected');
MactrackStandaloneTest::finish('MacTrack disposable-database guard');
