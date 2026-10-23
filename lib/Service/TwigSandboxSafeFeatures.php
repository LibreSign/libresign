<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * GENERATED FILE: use tools/generate-twig-sandbox-profile.php, not manual edits.
 * The pinned Twig 4.x metadata is a reviewed compatibility reference for Twig 3.30.
 */

namespace OCA\Libresign\Service;

final class TwigSandboxSafeFeatures {
	public const SOURCE_COMMIT = '92fc7b14903529462615b23166c02862d2dcff52';

	/** @var list<string> */
	public const TAGS = [
		'apply',
		'block',
		'do',
		'for',
		'if',
		'macro',
		'set',
		'types',
		'with',
	];

	/** @var list<string> */
	public const FILTERS = [
		'abs',
		'batch',
		'capitalize',
		'convert_encoding',
		'default',
		'e',
		'escape',
		'first',
		'format',
		'join',
		'keys',
		'last',
		'length',
		'lower',
		'merge',
		'nl2br',
		'number_format',
		'replace',
		'reverse',
		'round',
		'slice',
		'split',
		'striptags',
		'title',
		'trim',
		'upper',
		'url_encode',
	];

	/** @var list<string> */
	public const FUNCTIONS = [
		'cycle',
		'max',
		'min',
	];

	/** @var list<string> */
	public const TESTS = [
		'defined',
		'divisible by',
		'empty',
		'even',
		'iterable',
		'mapping',
		'none',
		'null',
		'odd',
		'same as',
		'sequence',
		'true',
	];
}
