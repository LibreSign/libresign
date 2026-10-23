<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

// Development-only generator. Twig 4.x is never loaded by LibreSign at runtime.
// Run from a pinned Twig 4.x checkout with composer install --no-dev.
const TWIG4_REFERENCE_SHA = '92fc7b14903529462615b23166c02862d2dcff52';

if ($argc !== 3 || !in_array($argv[2], ['--write', '--check'], true)) {
	fwrite(STDERR, "Usage: php tools/generate-twig-sandbox-profile.php /path/to/twig-4.x --write|--check\n");
	exit(2);
}

$checkout = realpath($argv[1]);
if ($checkout === false || !is_file($checkout . '/vendor/autoload.php')) {
	fwrite(STDERR, "Missing Twig 4.x checkout or Composer autoloader.\n");
	exit(2);
}

$headLines = [];
exec('git -C ' . escapeshellarg($checkout) . ' rev-parse HEAD 2>/dev/null', $headLines, $exitCode);
if ($exitCode !== 0 || ($headLines[0] ?? '') !== TWIG4_REFERENCE_SHA) {
	fwrite(STDERR, "Twig 4.x checkout is not at the reviewed reference commit.\n");
	exit(2);
}

require $checkout . '/vendor/autoload.php';
$root = dirname(__DIR__);
require $root . '/3rdparty/composer/composer/ClassLoader.php';
require $root . '/3rdparty/composer/autoload.php';

$reference = new \Twig\Environment(new \Twig\Loader\ArrayLoader());
$runtime = new \OCA\Libresign\Vendor\Twig\Environment(
	new \OCA\Libresign\Vendor\Twig\Loader\ArrayLoader(),
);
if (version_compare(\OCA\Libresign\Vendor\Twig\Environment::VERSION, '3.30.0', '<')) {
	fwrite(STDERR, "The application's Twig version must be at least 3.30.0.\n");
	exit(2);
}

/** @var array<string, list<string>> $safe */
$safe = [];
foreach ([
	'TAGS' => [$reference->getTokenParsers(), $runtime->getTokenParsers()],
	'FILTERS' => [$reference->getFilters(), $runtime->getFilters()],
	'FUNCTIONS' => [$reference->getFunctions(), $runtime->getFunctions()],
	'TESTS' => [$reference->getTests(), $runtime->getTests()],
] as $category => [$upstream, $installed]) {
	$names = [];
	foreach ($upstream as $name => $capability) {
		if (!isset($installed[$name])) {
			continue; // Do not expose features absent from the bundled Twig 3 runtime.
		}
		if ($capability->isAlwaysAllowedInSandbox()) {
			$names[] = $name;
		}
	}
	sort($names, SORT_STRING);
	$safe[$category] = $names;
}

$contents = <<<'HEADER'
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
HEADER;
$contents .= "\n\tpublic const SOURCE_COMMIT = '" . TWIG4_REFERENCE_SHA . "';\n";
foreach ($safe as $category => $names) {
	$contents .= "\n\t/** @var list<string> */\n\tpublic const {$category} = [\n";
	foreach ($names as $name) {
		$contents .= "\t\t" . var_export($name, true) . ",\n";
	}
	$contents .= "\t];\n";
}
$contents .= "}\n";
$output = $root . '/lib/Service/TwigSandboxSafeFeatures.php';

if ($argv[2] === '--check') {
	if (!is_file($output) || file_get_contents($output) !== $contents) {
		fwrite(STDERR, "Twig sandbox profile differs from pinned upstream metadata. Regenerate and review the changes.\n");
		exit(1);
	}
	echo 'Twig sandbox profile matches ' . TWIG4_REFERENCE_SHA . "\n";
} else {
	if (file_put_contents($output, $contents) === false) {
		throw new \RuntimeException('Unable to write sandbox compatibility profile.');
	}
	foreach ($safe as $category => $names) {
		echo $category . ': ' . count($names) . " safe features\n";
	}
}
