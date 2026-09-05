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

require_once __DIR__ . '/../Support/StandaloneTest.php';
require_once __DIR__ . '/../Support/SqlCallAnalyzer.php';

$source = <<<'PHP'
<?php
db_execute('DELETE FROM example');
db_fetch_assoc("SELECT * FROM example WHERE id = $id");
db_fetch_cell($query);
db_execute_prepared('DELETE FROM example WHERE id = ?', [$id]);
db_execute_prepared('DELETE FROM example WHERE id IN (' . $placeholders . ')', $ids);
\db_execute("DELETE FROM example WHERE id = $id");
DB_FETCH_CELL($query);
\DB_EXECUTE_PREPARED('DELETE FROM example WHERE id IN (' . $placeholders . ')', $ids);
PHP;

$counts = MactrackSqlCallAnalyzer::count($source);
MactrackStandaloneTest::assertSame(5, $counts['raw'], 'the analyzer counts qualified and case-insensitive non-prepared DB calls');
MactrackStandaloneTest::assertSame(4, $counts['dynamic_raw'], 'the analyzer detects variables and interpolation in every raw SQL spelling');
MactrackStandaloneTest::assertSame(2, $counts['dynamic_prepared'], 'the analyzer detects dynamic SQL structure passed to prepared helpers');

$threw = false;

try {
	MactrackSqlCallAnalyzer::count(false);
} catch (InvalidArgumentException $exception) {
	$threw = true;
}

MactrackStandaloneTest::assertTrue($threw, 'the analyzer rejects unreadable non-string source');
MactrackStandaloneTest::finish('MacTrack SQL-call analyzer');
