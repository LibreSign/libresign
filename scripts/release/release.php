#!/usr/bin/env php
<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

require_once __DIR__ . '/ReleasePlanner.php';
require_once __DIR__ . '/ReleaseFiles.php';

use LibreSign\Release\ReleaseFiles;
use LibreSign\Release\ReleasePlanner;

$command = $argv[1] ?? 'help';
$args = array_slice($argv, 2);

try {
	match ($command) {
		'plan' => plan($args),
		'apply' => apply($args),
		'validate-files' => validateFiles($args),
		'release-notes' => releaseNotes($args),
		default => usage($command === 'help' ? 0 : 2),
	};
} catch (Throwable $e) {
	fwrite(STDERR, $e->getMessage() . "\n");
	exit(1);
}

/** @param list<string> $args */
function plan(array $args): never {
	$options = parseOptions($args);
	$currentVersion = required($options, 'current-version');
	$prsFile = required($options, 'prs');
	$date = $options['date'] ?? gmdate('Y-m-d');

	$pullRequests = json_decode((string)file_get_contents($prsFile), true, flags: JSON_THROW_ON_ERROR);
	if (!is_array($pullRequests)) {
		throw new RuntimeException('Pull request input must be a JSON array');
	}

	$result = (new ReleasePlanner())->plan($currentVersion, $pullRequests, $date);
	echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
	exit(0);
}

/** @param list<string> $args */
function apply(array $args): never {
	$options = parseOptions($args);
	$root = $options['root'] ?? '.';
	$planFile = required($options, 'plan');
	$plan = json_decode((string)file_get_contents($planFile), true, flags: JSON_THROW_ON_ERROR);
	if (!is_array($plan) || !isset($plan['nextVersion'], $plan['changelog'])) {
		throw new RuntimeException('Invalid release plan');
	}

	(new ReleaseFiles())->apply($root, (string)$plan['nextVersion'], (string)$plan['changelog']);
	exit(0);
}

/** @param list<string> $args */
function validateFiles(array $args): never {
	$options = parseOptions($args);
	$root = $options['root'] ?? '.';
	$version = required($options, 'version');

	$files = new ReleaseFiles();
	$files->assertVersions($root, $version);
	$files->changelogSection($root, $version);
	exit(0);
}

/** @param list<string> $args */
function releaseNotes(array $args): never {
	$options = parseOptions($args);
	$root = $options['root'] ?? '.';
	$version = required($options, 'version');
	$previous = required($options, 'previous');
	$target = required($options, 'target');
	$repository = required($options, 'repository');

	$section = (new ReleaseFiles())->changelogSection($root, $version);
	echo "## What's Changed\n";
	echo $section . "\n\n";
	echo "**Full Changelog:** https://github.com/{$repository}/compare/{$previous}...{$target}\n";
	exit(0);
}

/**
 * @param list<string> $args
 * @return array<string,string>
 */
function parseOptions(array $args): array {
	$result = [];
	for ($i = 0, $count = count($args); $i < $count; $i++) {
		if (!str_starts_with($args[$i], '--')) {
			throw new InvalidArgumentException("Unexpected argument: {$args[$i]}");
		}
		$key = substr($args[$i], 2);
		$value = $args[$i + 1] ?? null;
		if ($value === null || str_starts_with($value, '--')) {
			throw new InvalidArgumentException("Missing value for --{$key}");
		}
		$result[$key] = $value;
		$i++;
	}
	return $result;
}

/** @param array<string,string> $options */
function required(array $options, string $name): string {
	if (!isset($options[$name]) || $options[$name] === '') {
		throw new InvalidArgumentException("Missing --{$name}");
	}
	return $options[$name];
}

function usage(int $exitCode): never {
	echo "LibreSign release CLI\n\n";
	echo "Commands:\n";
	echo "  plan --current-version X.Y.Z --prs prs.json [--date YYYY-MM-DD]\n";
	echo "  apply --plan release-plan.json [--root PATH]\n";
	echo "  validate-files --version X.Y.Z [--root PATH]\n";
	echo "  release-notes --version X.Y.Z --previous TAG --target SHA --repository OWNER/REPO [--root PATH]\n";
	exit($exitCode);
}
