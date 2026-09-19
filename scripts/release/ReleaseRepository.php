<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace LibreSign\Release;

final class ReleaseRepository {
	public function __construct(
		private readonly CommandRunner $runner,
		private readonly ReleaseFiles $files = new ReleaseFiles(),
	) {
	}

	/**
	 * @return array{previousTag:string,currentVersion:string,headSha:string}
	 */
	public function inspect(string $root): array {
		$previousTag = $this->runner->run(
			['git', 'describe', '--tags', '--match', 'v[0-9]*', '--abbrev=0', 'HEAD'],
			$root,
		);
		$currentVersion = ltrim($previousTag, 'v');
		$headSha = $this->runner->run(['git', 'rev-parse', 'HEAD'], $root);

		$this->files->assertVersions($root, $currentVersion);

		return [
			'previousTag' => $previousTag,
			'currentVersion' => $currentVersion,
			'headSha' => $headSha,
		];
	}

	/**
	 * @return array{version:string,tag:string,previousTag:string,targetSha:string}
	 */
	public function draftState(string $root): array {
		$version = $this->files->readVersion($root);
		if (preg_match('/-(?:dev|alpha|beta|rc)/i', $version)) {
			throw new \RuntimeException("Development or prerelease version cannot be released: {$version}");
		}
		$this->files->assertVersions($root, $version);

		$tag = 'v' . $version;
		if ($this->runner->run(['git', 'tag', '--list', $tag], $root) !== '') {
			throw new \RuntimeException("Tag {$tag} already exists");
		}

		$previousTag = $this->runner->run(
			['git', 'describe', '--tags', '--match', 'v[0-9]*', '--abbrev=0', 'HEAD'],
			$root,
		);
		$previousVersion = ltrim($previousTag, 'v');
		$currentMajor = explode('.', $version)[0] ?? '';
		$previousMajor = explode('.', $previousVersion)[0] ?? '';
		if ($currentMajor === '' || $currentMajor !== $previousMajor) {
			throw new \RuntimeException(
				"Previous tag {$previousTag} does not match release major {$currentMajor}",
			);
		}

		$targetSha = $this->runner->run(['git', 'rev-parse', 'HEAD'], $root);

		return [
			'version' => $version,
			'tag' => $tag,
			'previousTag' => $previousTag,
			'targetSha' => $targetSha,
		];
	}

	/**
	 * @return list<array{number:int,title:string,url:string,author:string,labels:list<string>}>
	 */
	public function collectPullRequests(string $root, string $repository, string $branch, string $previousTag): array {
		$commits = preg_split(
			'/\R+/',
			$this->runner->run(['git', 'rev-list', '--reverse', $previousTag . '..HEAD'], $root),
			-1,
			PREG_SPLIT_NO_EMPTY,
		) ?: [];

		$pullRequests = [];
		foreach ($commits as $commit) {
			$json = $this->runner->run([
				'gh',
				'api',
				'-H',
				'Accept: application/vnd.github+json',
				sprintf('repos/%s/commits/%s/pulls', $repository, $commit),
			], $root);
			$items = json_decode($json === '' ? '[]' : $json, true, flags: JSON_THROW_ON_ERROR);
			if (!is_array($items)) {
				throw new \RuntimeException('Invalid pull request response from GitHub');
			}

			foreach ($items as $item) {
				if (!is_array($item) || ($item['merged_at'] ?? null) === null) {
					continue;
				}
				if (($item['base']['ref'] ?? null) !== $branch) {
					continue;
				}

				$number = (int)($item['number'] ?? 0);
				if ($number <= 0) {
					continue;
				}

				$labels = [];
				foreach (($item['labels'] ?? []) as $label) {
					if (is_array($label) && isset($label['name'])) {
						$labels[] = (string)$label['name'];
					}
				}

				$pullRequests[$number] = [
					'number' => $number,
					'title' => (string)($item['title'] ?? ''),
					'url' => (string)($item['html_url'] ?? ''),
					'author' => (string)($item['user']['login'] ?? ''),
					'labels' => $labels,
				];
			}
		}

		ksort($pullRequests);
		return array_values($pullRequests);
	}

	/**
	 * @return list<array{number:int,title:string}>
	 */
	public function pendingBackports(string $repository): array {
		$query = sprintf('repo:%s is:pr is:open label:backport-request', $repository);
		$json = $this->runner->run([
			'gh',
			'api',
			'-H',
			'Accept: application/vnd.github+json',
			'/search/issues',
			'-f',
			'q=' . $query,
		]);
		$data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
		$items = is_array($data) ? ($data['items'] ?? []) : [];

		$result = [];
		foreach ($items as $item) {
			if (is_array($item)) {
				$result[] = [
					'number' => (int)($item['number'] ?? 0),
					'title' => (string)($item['title'] ?? ''),
				];
			}
		}
		return $result;
	}

	/**
	 * @return array{number:int,title:string}
	 */
	public function nextPatchMilestone(string $repository, int $stableNumber): array {
		$json = $this->runner->run([
			'gh',
			'api',
			sprintf('repos/%s/milestones?state=open&per_page=100', $repository),
		]);
		$items = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
		if (!is_array($items)) {
			throw new \RuntimeException('Invalid milestones response from GitHub');
		}

		$suffix = sprintf('Next Patch (%d)', $stableNumber);
		foreach ($items as $item) {
			if (!is_array($item)) {
				continue;
			}
			$title = (string)($item['title'] ?? '');
			if (str_ends_with($title, $suffix)) {
				return [
					'number' => (int)$item['number'],
					'title' => $title,
				];
			}
		}

		throw new \RuntimeException("Open {$suffix} milestone was not found");
	}
}
