#!/usr/bin/env php
<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

require_once __DIR__ . '/CommandRunner.php';
require_once __DIR__ . '/NativeCommandRunner.php';
require_once __DIR__ . '/ReleasePlanner.php';
require_once __DIR__ . '/ReleaseFiles.php';
require_once __DIR__ . '/ReleaseRepository.php';
require_once __DIR__ . '/GitHubReleaseManager.php';
require_once __DIR__ . '/MilestoneManager.php';

use LibreSign\Release\GitHubReleaseManager;
use LibreSign\Release\MilestoneManager;
use LibreSign\Release\NativeCommandRunner;
use LibreSign\Release\ReleaseFiles;
use LibreSign\Release\ReleasePlanner;
use LibreSign\Release\ReleaseRepository;

$command = $argv[1] ?? 'help';
$args = array_slice($argv, 2);

try {
	match ($command) {
		'inspect' => inspectRepository($args),
		'draft-state' => draftState($args),
		'pending-backports' => pendingBackports($args),
		'milestone' => resolveMilestone($args),
		'collect-prs' => collectPullRequests($args),
		'plan' => plan($args),
		'apply' => apply($args),
		'validate-files' => validateFiles($args),
		'nextcloud-min-version' => nextcloudMinVersion($args),
		'release-notes' => releaseNotes($args),
		'check-pr-scope' => checkPullRequestScope($args),
		'draft' => createOrUpdateDraft($args),
		'finalize-milestone' => finalizeMilestone($args),
		'assert-milestone-closed' => assertMilestoneClosed($args),
		'summary' => summary($args),
		'outputs' => outputs($args),
		default => usage($command === 'help' ? 0 : 2),
	};
} catch (Throwable $e) {
	fwrite(STDERR, $e->getMessage() . "\n");
	exit(1);
}

/** @param list<string> $args */
function inspectRepository(array $args): never {
	$options = parseOptions($args);
	$root = $options['root'] ?? '.';
	$result = repository()->inspect($root);
	writeJson($result);
}

/** @param list<string> $args */
function draftState(array $args): never {
	$options = parseOptions($args);
	$root = $options['root'] ?? '.';
	writeJson(repository()->draftState($root));
}

/** @param list<string> $args */
function pendingBackports(array $args): never {
	$options = parseOptions($args);
	$repository = required($options, 'repository');
	$items = repository()->pendingBackports($repository);

	if ($items !== []) {
		$lines = array_map(
			static fn (array $item): string => sprintf('#%d %s', $item['number'], $item['title']),
			$items,
		);
		throw new RuntimeException(
			"Pending backport requests must be resolved before preparing a release:\n" . implode("\n", $lines),
		);
	}

	writeJson([]);
}

/** @param list<string> $args */
function resolveMilestone(array $args): never {
	$options = parseOptions($args);
	$repositoryName = required($options, 'repository');
	$branch = required($options, 'branch');
	$stableNumber = stableNumber($branch);
	writeJson(repository()->nextPatchMilestone($repositoryName, $stableNumber));
}

/** @param list<string> $args */
function collectPullRequests(array $args): never {
	$options = parseOptions($args);
	$root = $options['root'] ?? '.';
	$repositoryName = required($options, 'repository');
	$branch = required($options, 'branch');
	$previousTag = required($options, 'previous-tag');

	writeJson(repository()->collectPullRequests(
		$root,
		$repositoryName,
		$branch,
		$previousTag,
	));
}

/** @param list<string> $args */
function plan(array $args): never {
	$options = parseOptions($args);
	$currentVersion = required($options, 'current-version');
	$prsFile = required($options, 'prs');
	$date = $options['date'] ?? gmdate('Y-m-d');

	$pullRequests = readJsonFile($prsFile);
	if (!array_is_list($pullRequests)) {
		throw new RuntimeException('Pull request input must be a JSON array');
	}

	$result = (new ReleasePlanner())->plan($currentVersion, $pullRequests, $date);
	writeJson($result);
}

/** @param list<string> $args */
function apply(array $args): never {
	$options = parseOptions($args);
	$root = $options['root'] ?? '.';
	$plan = readJsonFile(required($options, 'plan'));
	if (!isset($plan['nextVersion'], $plan['changelog'])) {
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
function nextcloudMinVersion(array $args): never {
	$options = parseOptions($args);
	$root = $options['root'] ?? '.';
	echo (new ReleaseFiles())->readNextcloudMinVersion($root) . "\n";
	exit(0);
}

/** @param list<string> $args */
function releaseNotes(array $args): never {
	$options = parseOptions($args);
	$root = $options['root'] ?? '.';
	$version = required($options, 'version');
	$previous = required($options, 'previous');
	$target = required($options, 'target');
	$repositoryName = required($options, 'repository');

	$section = (new ReleaseFiles())->changelogSection($root, $version);
	echo "## What's Changed\n";
	echo $section . "\n\n";
	echo "**Full Changelog:** https://github.com/{$repositoryName}/compare/{$previous}...{$target}\n";
	exit(0);
}

/** @param list<string> $args */
function checkPullRequestScope(array $args): never {
	$options = parseOptions($args);
	$repositoryName = required($options, 'repository');
	$pullRequest = (int)required($options, 'pr');
	if ($pullRequest <= 0) {
		throw new InvalidArgumentException('--pr must be a positive integer');
	}

	manager()->assertPullRequestScope($repositoryName, $pullRequest);
	exit(0);
}

/** @param list<string> $args */
function createOrUpdateDraft(array $args): never {
	$options = parseOptions($args);
	manager()->createOrUpdateDraft(
		required($options, 'repository'),
		required($options, 'tag'),
		required($options, 'target'),
		required($options, 'notes-file'),
	);
	exit(0);
}

/** @param list<string> $args */
function outputs(array $args): never {
	$options = parseOptions($args);
	$data = readJsonFile(required($options, 'json'));
	$mapping = required($options, 'map');
	$outputFile = getenv('GITHUB_OUTPUT');

	if (!is_string($outputFile) || $outputFile === '') {
		throw new RuntimeException('GITHUB_OUTPUT is not available');
	}

	$lines = [];
	foreach (explode(',', $mapping) as $entry) {
		[$source, $target] = array_pad(explode(':', $entry, 2), 2, null);
		if ($source === null || $source === '' || $target === null || $target === '') {
			throw new InvalidArgumentException('Output mapping must use source:target');
		}
		if (!array_key_exists($source, $data) || !is_scalar($data[$source])) {
			throw new RuntimeException("Missing scalar JSON key: {$source}");
		}
		$lines[] = $target . '=' . (string)$data[$source];
	}

	file_put_contents($outputFile, implode("\n", $lines) . "\n", FILE_APPEND);
	exit(0);
}

/** @param list<string> $args */
function finalizeMilestone(array $args): never {
	$options = parseOptions($args);
	$root = $options['root'] ?? '.';
	$repositoryName = required($options, 'repository');
	$branch = required($options, 'branch');
	$final = required($options, 'final');

	if (!in_array($final, ['true', 'false'], true)) {
		throw new InvalidArgumentException('--final must be true or false');
	}

	$version = (new ReleaseFiles())->readVersion($root);
	(new MilestoneManager(new NativeCommandRunner()))->finalize(
		$repositoryName,
		stableNumber($branch),
		$version,
		$final === 'true',
	);
	exit(0);
}

/** @param list<string> $args */
function assertMilestoneClosed(array $args): never {
	$options = parseOptions($args);
	$repositoryName = required($options, 'repository');
	$title = required($options, 'title');
	(new MilestoneManager(new NativeCommandRunner()))->assertClosed($repositoryName, $title);
	exit(0);
}

/** @param list<string> $args */
function summary(array $args): never {
	$options = parseOptions($args);
	$state = readJsonFile(required($options, 'state'));
	$plan = readJsonFile(required($options, 'plan'));
	$milestone = readJsonFile(required($options, 'milestone'));
	$branch = required($options, 'branch');

	printf("## Release preparation plan\n\n");
	printf("- Branch: %s\n", $branch);
	printf("- Previous release: %s\n", $state['previousTag'] ?? '');
	printf("- Proposed version: %s\n", $plan['nextVersion'] ?? '');
	printf("- Bump: %s\n", $plan['bump'] ?? '');
	printf("- Target commit: %s\n", $state['headSha'] ?? '');
	printf("- Milestone: %s\n\n", $milestone['title'] ?? '');
	printf("### Generated changelog\n\n%s", $plan['changelog'] ?? '');
	exit(0);
}

function repository(): ReleaseRepository {
	$runner = new NativeCommandRunner();
	return new ReleaseRepository($runner);
}

function manager(): GitHubReleaseManager {
	return new GitHubReleaseManager(new NativeCommandRunner());
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

/** @return array<mixed> */
function readJsonFile(string $path): array {
	$data = json_decode((string)file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
	if (!is_array($data)) {
		throw new RuntimeException("Invalid JSON file: {$path}");
	}
	return $data;
}

/** @param array<mixed> $value */
function writeJson(array $value): never {
	echo json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
	exit(0);
}

function stableNumber(string $branch): int {
	if (!preg_match('/^stable(?<number>\d+)$/', $branch, $matches)) {
		throw new InvalidArgumentException('Branch must match stableNN');
	}
	return (int)$matches['number'];
}

function usage(int $exitCode): never {
	echo "LibreSign release CLI\n\n";
	echo "Commands:\n";
	echo "  inspect [--root PATH]\n";
	echo "  draft-state [--root PATH]\n";
	echo "  pending-backports --repository OWNER/REPO\n";
	echo "  milestone --repository OWNER/REPO --branch stableNN\n";
	echo "  collect-prs --repository OWNER/REPO --branch stableNN --previous-tag TAG [--root PATH]\n";
	echo "  plan --current-version X.Y.Z --prs prs.json [--date YYYY-MM-DD]\n";
	echo "  apply --plan release-plan.json [--root PATH]\n";
	echo "  validate-files --version X.Y.Z [--root PATH]\n";
	echo "  nextcloud-min-version [--root PATH]\n";
	echo "  release-notes --version X.Y.Z --previous TAG --target SHA --repository OWNER/REPO [--root PATH]\n";
	echo "  check-pr-scope --repository OWNER/REPO --pr NUMBER\n";
	echo "  draft --repository OWNER/REPO --tag TAG --target SHA --notes-file FILE\n";
	echo "  finalize-milestone --repository OWNER/REPO --branch stableNN --final true|false [--root PATH]\n";
	echo "  assert-milestone-closed --repository OWNER/REPO --title TITLE\n";
	echo "  summary --state state.json --plan release-plan.json --milestone milestone.json --branch stableNN\n";
	echo "  outputs --json FILE --map source:target[,source:target...]\n";
	exit($exitCode);
}
