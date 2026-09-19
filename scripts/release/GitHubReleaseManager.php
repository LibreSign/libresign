<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace LibreSign\Release;

final class GitHubReleaseManager {
	public function __construct(private readonly CommandRunner $runner) {
	}

	public function assertPullRequestScope(string $repository, int $pullRequest): void {
		$json = $this->runner->run([
			'gh',
			'api',
			sprintf('repos/%s/pulls/%d/files?per_page=100', $repository, $pullRequest),
		]);
		$files = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
		$allowed = [
			'CHANGELOG.md',
			'appinfo/info.xml',
			'package.json',
			'package-lock.json',
		];

		$unexpected = [];
		foreach (is_array($files) ? $files : [] as $file) {
			$name = is_array($file) ? (string)($file['filename'] ?? '') : '';
			if ($name !== '' && !in_array($name, $allowed, true)) {
				$unexpected[] = $name;
			}
		}

		if ($unexpected !== []) {
			throw new \RuntimeException(
				'Release PR changed files outside the allowed release scope: ' . implode(', ', $unexpected),
			);
		}
	}

	public function createOrUpdateDraft(
		string $repository,
		string $tag,
		string $targetSha,
		string $notesFile,
	): void {
		$json = $this->runner->run([
			'gh',
			'api',
			sprintf('repos/%s/releases?per_page=100', $repository),
		]);
		$releases = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

		$existing = null;
		foreach (is_array($releases) ? $releases : [] as $release) {
			if (is_array($release) && ($release['tag_name'] ?? null) === $tag) {
				$existing = $release;
				break;
			}
		}

		if ($existing !== null) {
			if (($existing['draft'] ?? false) !== true) {
				throw new \RuntimeException("Release {$tag} already exists and is not a draft");
			}
			$this->runner->run([
				'gh',
				'api',
				'--method',
				'PATCH',
				sprintf('repos/%s/releases/%d', $repository, (int)$existing['id']),
				'-f',
				'tag_name=' . $tag,
				'-f',
				'target_commitish=' . $targetSha,
				'-f',
				'name=' . $tag,
				'-F',
				'draft=true',
				'-F',
				'prerelease=false',
				'-F',
				'body=@' . $notesFile,
			]);
			return;
		}

		$this->runner->run([
			'gh',
			'release',
			'create',
			$tag,
			'--repo',
			$repository,
			'--target',
			$targetSha,
			'--title',
			$tag,
			'--notes-file',
			$notesFile,
			'--draft',
		]);
	}
}
