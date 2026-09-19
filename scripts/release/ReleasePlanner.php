<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace LibreSign\Release;

final class ReleasePlanner {
	private const MINOR_LABELS = ['feature', 'feat', 'enhancement', 'minor'];
	private const SKIP_LABELS = ['skip-changelog'];

	/**
	 * @param array<int, array{number:int,title:string,url:string,labels?:array<int,string>,author?:string}> $pullRequests
	 * @return array{bump:string,nextVersion:string,sections:array<string,array<int,array{number:int,title:string,url:string}>>,changelog:string}
	 */
	public function plan(string $currentVersion, array $pullRequests, string $date): array {
		$included = array_values(array_filter(
			$pullRequests,
			fn (array $pullRequest): bool => !$this->hasAnyLabel($pullRequest, self::SKIP_LABELS)
				&& !in_array(strtolower((string)($pullRequest['author'] ?? '')), ['dependabot[bot]', 'renovate[bot]'], true),
		));

		if ($included === []) {
			throw new \InvalidArgumentException('No releasable pull requests found');
		}

		$bump = $this->resolveBump($included);
		$nextVersion = $this->bumpVersion($currentVersion, $bump);
		$sections = $this->categorize($included);

		return [
			'bump' => $bump,
			'nextVersion' => $nextVersion,
			'sections' => $sections,
			'changelog' => $this->renderChangelog($nextVersion, $date, $sections),
		];
	}

	/**
	 * @param array<int, array{number:int,title:string,url:string,labels?:array<int,string>}> $pullRequests
	 */
	public function resolveBump(array $pullRequests): string {
		foreach ($pullRequests as $pullRequest) {
			if ($this->hasAnyLabel($pullRequest, ['major']) || preg_match('/^[a-z]+(?:\([^)]*\))?!:/i', $this->cleanTitle($pullRequest['title']))) {
				throw new \InvalidArgumentException('Major version bumps are not allowed on stable release preparation');
			}
		}

		foreach ($pullRequests as $pullRequest) {
			if ($this->hasAnyLabel($pullRequest, self::MINOR_LABELS) || preg_match('/^feat(?:\([^)]*\))?:/i', $this->cleanTitle($pullRequest['title']))) {
				return 'minor';
			}
		}

		return 'patch';
	}

	public function bumpVersion(string $version, string $bump): string {
		if (!preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $version, $matches)) {
			throw new \InvalidArgumentException('Version must use MAJOR.MINOR.PATCH');
		}

		[$major, $minor, $patch] = [(int)$matches[1], (int)$matches[2], (int)$matches[3]];

		return match ($bump) {
			'major' => sprintf('%d.0.0', $major + 1),
			'minor' => sprintf('%d.%d.0', $major, $minor + 1),
			'patch' => sprintf('%d.%d.%d', $major, $minor, $patch + 1),
			default => throw new \InvalidArgumentException('Unsupported version bump'),
		};
	}

	/**
	 * @param array<int, array{number:int,title:string,url:string,labels?:array<int,string>}> $pullRequests
	 * @return array<string,array<int,array{number:int,title:string,url:string}>>
	 */
	public function categorize(array $pullRequests): array {
		$sections = [];

		foreach ($pullRequests as $pullRequest) {
			$category = $this->categoryFor($pullRequest);
			if ($category === 'Dependencies') {
				$sections[$category] ??= [[
					'number' => 0,
					'title' => 'Bump dependencies',
					'url' => '',
				]];
				continue;
			}

			$sections[$category][] = [
				'number' => $pullRequest['number'],
				'title' => $this->descriptionFromTitle($pullRequest['title']),
				'url' => $pullRequest['url'],
			];
		}

		$sections['Changed'] ??= [];
		if (!array_filter($sections['Changed'], fn (array $item): bool => strcasecmp($item['title'], 'Update translations') === 0)) {
			array_unshift($sections['Changed'], [
				'number' => 0,
				'title' => 'Update translations',
				'url' => '',
			]);
		}

		$order = ['Added', 'Changed', 'Fixed', 'Removed', 'Security', 'Documentation', 'Dependencies', 'Tests'];
		uksort($sections, fn (string $a, string $b): int => array_search($a, $order, true) <=> array_search($b, $order, true));

		return $sections;
	}

	/**
	 * @param array<string,array<int,array{number:int,title:string,url:string}>> $sections
	 */
	public function renderChangelog(string $version, string $date, array $sections): string {
		$lines = ["## {$version} - {$date}", ''];

		foreach ($sections as $category => $items) {
			if ($items === []) {
				continue;
			}

			$lines[] = "### {$category}";
			$lines[] = '';

			foreach ($items as $item) {
				if ($item['number'] === 0 || $item['url'] === '') {
					$lines[] = '- ' . $item['title'];
				} else {
					$lines[] = sprintf('- %s [#%d](%s)', $item['title'], $item['number'], $item['url']);
				}
			}

			$lines[] = '';
		}

		return rtrim(implode("\n", $lines)) . "\n";
	}

	/**
	 * @param array{title:string,labels?:array<int,string>} $pullRequest
	 */
	private function categoryFor(array $pullRequest): string {
		$title = $this->cleanTitle($pullRequest['title']);

		if ($this->hasAnyLabel($pullRequest, ['security'])) {
			return 'Security';
		}
		if ($this->hasAnyLabel($pullRequest, ['removed', 'deprecated']) || preg_match('/^remove(?:d)?:/i', $title)) {
			return 'Removed';
		}
		if ($this->hasAnyLabel($pullRequest, ['dependencies']) || preg_match('/^(?:chore(?:\([^)]*\))?:\s*)?bump\b/i', $title)) {
			return 'Dependencies';
		}
		if ($this->hasAnyLabel($pullRequest, ['docs']) || preg_match('/^docs(?:\([^)]*\))?:/i', $title)) {
			return 'Documentation';
		}
		if ($this->hasAnyLabel($pullRequest, ['test']) || preg_match('/^test(?:\([^)]*\))?:/i', $title)) {
			return 'Tests';
		}
		if ($this->hasAnyLabel($pullRequest, ['fix', 'bugfix', 'bug', 'patch']) || preg_match('/^fix(?:\([^)]*\))?:/i', $title)) {
			return 'Fixed';
		}
		if ($this->hasAnyLabel($pullRequest, self::MINOR_LABELS) || preg_match('/^feat(?:\([^)]*\))?:/i', $title)) {
			return 'Added';
		}

		return 'Changed';
	}

	/**
	 * @param array{labels?:array<int,string>} $pullRequest
	 * @param array<int,string> $labels
	 */
	private function hasAnyLabel(array $pullRequest, array $labels): bool {
		$actual = array_map('strtolower', $pullRequest['labels'] ?? []);
		foreach ($labels as $label) {
			if (in_array(strtolower($label), $actual, true)) {
				return true;
			}
		}
		return false;
	}

	private function descriptionFromTitle(string $title): string {
		$title = $this->cleanTitle($title);
		$title = preg_replace('/^[a-z]+(?:\([^)]*\))?!?:\s*/i', '', $title) ?? $title;
		return lcfirst(trim($title));
	}

	private function cleanTitle(string $title): string {
		$title = preg_replace('/^\[stable\d+\]\s*/i', '', trim($title)) ?? $title;
		$title = preg_replace('/^backport:\s*/i', '', $title) ?? $title;
		return trim($title);
	}
}
