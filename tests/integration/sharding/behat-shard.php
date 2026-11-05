<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

use LibreSign\Behat\Sharding\FeatureShardSelector;

require_once __DIR__ . '/FeatureShardSelector.php';

$usage = <<<'TEXT'
Select whole Behat features without starting Nextcloud.

SHARD_INDEX=0 SHARD_TOTAL=4 php sharding/behat-shard.php --list
SHARD_INDEX=0 SHARD_TOTAL=4 php sharding/behat-shard.php --verify
SHARD_INDEX=0 SHARD_TOTAL=4 php sharding/behat-shard.php --run -- -f pretty

SHARD_INDEX is zero-based (0 to SHARD_TOTAL - 1). Both variables are required.
--list (default): print feature paths relative to tests/integration.
--verify: check the union and disjointness of all shards, without Behat.
--run: run only the assigned features with the current Behat installation.

TEXT;

$mode = $argv[1] ?? '--list';
if ($mode === '--help' || $mode === '-h') {
	echo $usage;
	exit(0);
}

try {
	if (!in_array($mode, ['--list', '--verify', '--run'], true)) {
		throw new InvalidArgumentException('Unknown option: ' . $mode);
	}
	if ($mode !== '--run' && count($argv) > 2) {
		throw new InvalidArgumentException('Extra arguments are only accepted with --run.');
	}

	$index = getenv('SHARD_INDEX');
	$total = getenv('SHARD_TOTAL');
	$shard = FeatureShardSelector::fromEnvironment([
		'SHARD_INDEX' => $index,
		'SHARD_TOTAL' => $total,
	]);
	$integrationRoot = dirname(__DIR__);
	$features = FeatureShardSelector::discover($integrationRoot . '/features');
	if ($features === []) {
		throw new RuntimeException('No .feature files found; refusing to run an empty suite.');
	}

	if ($mode === '--verify') {
		$seen = [];
		$counts = [];
		for ($worker = 0; $worker < (int)$total; $worker++) {
			$assigned = (new FeatureShardSelector($worker, (int)$total))->select($features);
			$counts[$worker] = count($assigned);
			foreach ($assigned as $file) {
				if (isset($seen[$file])) {
					throw new RuntimeException('Feature assigned twice: ' . $file);
				}
				$seen[$file] = true;
			}
		}
		if (count($seen) !== count($features) || array_diff($features, array_keys($seen)) !== []) {
			throw new RuntimeException('Shards do not cover the complete feature suite.');
		}
		printf("Verified %d unique features across %d shard(s), with no omissions or overlaps.\n", count($features), (int)$total);
		foreach ($counts as $worker => $count) {
			printf("Shard %d: %d features\n", $worker, $count);
		}
		exit(0);
	}

	$selected = array_map(
		static fn (string $path): string => 'features/' . $path,
		$shard->select($features),
	);

	if ($mode === '--list') {
		if ($selected !== []) {
			echo implode("\n", $selected) . "\n";
		}
		exit(0);
	}

	// Never invoke Behat without explicit feature paths: that runs the entire suite.
	if ($selected === []) {
		fwrite(STDERR, "No features assigned to this shard; Behat was not started.\n");
		exit(0);
	}

	$behat = $integrationRoot . '/vendor/bin/behat';
	if (!is_file($behat)) {
		throw new RuntimeException('Behat is not installed. Run composer --working-dir=tests/integration install.');
	}

	$arguments = array_slice($argv, 2);
	if (($arguments[0] ?? null) === '--') {
		array_shift($arguments);
	}
	$command = array_merge([$behat, '--config', 'config/behat.yml'], $arguments, $selected);
	$process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes, $integrationRoot);
	if (!is_resource($process)) {
		throw new RuntimeException('Unable to start Behat.');
	}
	exit(proc_close($process));
} catch (InvalidArgumentException | RuntimeException $e) {
	fwrite(STDERR, "Shard selection error: {$e->getMessage()}\n");
	exit(2);
}
