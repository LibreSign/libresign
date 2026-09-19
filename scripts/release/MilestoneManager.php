<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace LibreSign\Release;

final class MilestoneManager {
	public function __construct(private readonly CommandRunner $runner) {
	}

	public function assertClosed(string $repository, string $title): void {
		$milestone = $this->findByTitle($this->milestones($repository, 'all'), $title);
		if ($milestone === null) {
			throw new \RuntimeException("Milestone {$title} was not found");
		}
		if (($milestone['state'] ?? null) !== 'closed') {
			throw new \RuntimeException("Milestone {$title} is not closed");
		}
	}

	public function finalize(
		string $repository,
		int $stableNumber,
		string $version,
		bool $finalStableRelease,
	): void {
		$milestones = $this->milestones($repository, 'all');
		$releaseMilestone = $this->findByTitle($milestones, 'v' . $version);

		if ($releaseMilestone === null) {
			$releaseMilestone = $this->findOpenNextPatch($milestones, $stableNumber);
			$this->patchMilestone(
				$repository,
				(int)$releaseMilestone['number'],
				['title' => 'v' . $version],
			);
			$releaseMilestone['title'] = 'v' . $version;
		}

		$releaseNumber = (int)$releaseMilestone['number'];
		$openItems = $this->openMilestoneItems($repository, $releaseNumber);

		if ($finalStableRelease && $openItems !== []) {
			throw new \RuntimeException(sprintf(
				'Final stable release still has open milestone items: %s',
				implode(', ', array_map(
					static fn (array $item): string => '#' . $item['number'],
					$openItems,
				)),
			));
		}

		if (!$finalStableRelease) {
			$nextMilestone = $this->findNextPatchExcluding(
				$this->milestones($repository, 'open'),
				$stableNumber,
				$releaseNumber,
			);

			if ($nextMilestone === null) {
				$nextMilestone = $this->createNextPatchMilestone($repository, $stableNumber);
			}

			$nextNumber = (int)$nextMilestone['number'];
			foreach ($openItems as $item) {
				$this->runner->run([
					'gh',
					'api',
					'--method',
					'PATCH',
					sprintf('repos/%s/issues/%d', $repository, $item['number']),
					'-F',
					'milestone=' . $nextNumber,
				]);
			}
		}

		if (($releaseMilestone['state'] ?? 'open') !== 'closed') {
			$this->patchMilestone($repository, $releaseNumber, ['state' => 'closed']);
		}
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	private function milestones(string $repository, string $state): array {
		$json = $this->runner->run([
			'gh',
			'api',
			sprintf('repos/%s/milestones?state=%s&per_page=100', $repository, $state),
		]);
		$data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
		return is_array($data) ? array_values(array_filter($data, 'is_array')) : [];
	}

	/**
	 * @param list<array<string,mixed>> $milestones
	 * @return array<string,mixed>|null
	 */
	private function findByTitle(array $milestones, string $title): ?array {
		foreach ($milestones as $milestone) {
			if (($milestone['title'] ?? null) === $title) {
				return $milestone;
			}
		}
		return null;
	}

	/**
	 * @param list<array<string,mixed>> $milestones
	 * @return array<string,mixed>
	 */
	private function findOpenNextPatch(array $milestones, int $stableNumber): array {
		$milestone = $this->findNextPatchExcluding($milestones, $stableNumber, null);
		if ($milestone === null) {
			throw new \RuntimeException("Open Next Patch ({$stableNumber}) milestone was not found");
		}
		return $milestone;
	}

	/**
	 * @param list<array<string,mixed>> $milestones
	 * @return array<string,mixed>|null
	 */
	private function findNextPatchExcluding(array $milestones, int $stableNumber, ?int $excludedNumber): ?array {
		$suffix = sprintf('Next Patch (%d)', $stableNumber);
		foreach ($milestones as $milestone) {
			if (($milestone['state'] ?? null) !== 'open') {
				continue;
			}
			if ($excludedNumber !== null && (int)($milestone['number'] ?? 0) === $excludedNumber) {
				continue;
			}
			$title = (string)($milestone['title'] ?? '');
			if (str_ends_with($title, $suffix)) {
				return $milestone;
			}
		}
		return null;
	}

	/**
	 * @return list<array{number:int,title:string}>
	 */
	private function openMilestoneItems(string $repository, int $milestone): array {
		$json = $this->runner->run([
			'gh',
			'api',
			'--paginate',
			sprintf('repos/%s/issues?state=open&milestone=%d&per_page=100', $repository, $milestone),
		]);
		$data = json_decode($json === '' ? '[]' : $json, true, flags: JSON_THROW_ON_ERROR);

		$result = [];
		foreach (is_array($data) ? $data : [] as $item) {
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
	 * @return array<string,mixed>
	 */
	private function createNextPatchMilestone(string $repository, int $stableNumber): array {
		$title = sprintf('💚 Next Patch (%d)', $stableNumber);
		$dueOn = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
			->modify('+28 days')
			->format('Y-m-d\TH:i:s\Z');

		$json = $this->runner->run([
			'gh',
			'api',
			'--method',
			'POST',
			sprintf('repos/%s/milestones', $repository),
			'-f',
			'title=' . $title,
			'-f',
			'due_on=' . $dueOn,
		]);
		$data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
		if (!is_array($data)) {
			throw new \RuntimeException('Invalid milestone response from GitHub');
		}
		return $data;
	}

	/**
	 * @param array<string,string> $fields
	 */
	private function patchMilestone(string $repository, int $number, array $fields): void {
		$command = [
			'gh',
			'api',
			'--method',
			'PATCH',
			sprintf('repos/%s/milestones/%d', $repository, $number),
		];
		foreach ($fields as $name => $value) {
			$command[] = '-f';
			$command[] = $name . '=' . $value;
		}
		$this->runner->run($command);
	}
}
