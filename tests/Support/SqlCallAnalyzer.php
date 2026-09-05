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

final class MactrackSqlCallAnalyzer {
	public static function count($source) {
		if (!is_string($source)) {
			throw new InvalidArgumentException('SQL call analysis requires PHP source text');
		}

		$counts = ['raw' => 0, 'dynamic_raw' => 0, 'dynamic_prepared' => 0];
		$tokens = token_get_all($source);
		$token_count = count($tokens);
		$name_tokens = [T_STRING];

		if (defined('T_NAME_FULLY_QUALIFIED')) {
			$name_tokens[] = constant('T_NAME_FULLY_QUALIFIED');
		}

		for ($index = 0; $index < $token_count; $index++) {
			$token = $tokens[$index];

			if (!is_array($token) || !in_array($token[0], $name_tokens, true)) {
				continue;
			}

			$name = strtolower(ltrim($token[1], '\\'));

			if (!preg_match('/^db_(?:execute|fetch_row|fetch_assoc|fetch_cell)(?:_prepared)?$/', $name)) {
				continue;
			}

			$is_prepared = substr($name, -9) === '_prepared';
			$next = $index + 1;

			while ($next < $token_count && is_array($tokens[$next]) && in_array($tokens[$next][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
				$next++;
			}

			if (($tokens[$next] ?? null) !== '(') {
				continue;
			}

			if (!$is_prepared) {
				$counts['raw']++;
			}

			$depth      = 1;
			$is_dynamic = false;

			for ($next++; $next < $token_count && $depth > 0; $next++) {
				$argument_token = $tokens[$next];

				if ($argument_token === '(') {
					$depth++;
				} elseif ($argument_token === ')') {
					$depth--;
				} elseif ($depth === 1 && $argument_token === ',') {
					break;
				} elseif ($argument_token === '.' || (is_array($argument_token) && in_array($argument_token[0], [T_VARIABLE, T_ENCAPSED_AND_WHITESPACE], true))) {
					$is_dynamic = true;
				}
			}

			if ($is_dynamic) {
				$counts[$is_prepared ? 'dynamic_prepared' : 'dynamic_raw']++;
			}
		}

		return $counts;
	}
}
