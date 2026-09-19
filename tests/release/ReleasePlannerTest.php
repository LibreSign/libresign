<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

require_once __DIR__ . '/../../scripts/release/ReleasePlanner.php';

use LibreSign\Release\ReleasePlanner;
use PHPUnit\Framework\TestCase;

final class ReleasePlannerTest extends TestCase {
	private ReleasePlanner $planner;

	protected function setUp(): void {
		$this->planner = new ReleasePlanner();
	}

	public function testFixOnlyReleaseBumpsPatch(): void {
		$plan = $this->planner->plan('15.0.0', [
			$this->pr(1, 'fix: repair signing', ['fix']),
		], '2026-09-19');

		self::assertSame('patch', $plan['bump']);
		self::assertSame('15.0.1', $plan['nextVersion']);
		self::assertStringContainsString('### Fixed', $plan['changelog']);
	}

	public function testFeatureReleaseBumpsMinor(): void {
		$plan = $this->planner->plan('15.0.3', [
			$this->pr(1, 'fix: repair signing', ['fix']),
			$this->pr(2, 'feat: add observer role', ['feature']),
		], '2026-09-19');

		self::assertSame('minor', $plan['bump']);
		self::assertSame('15.1.0', $plan['nextVersion']);
		self::assertStringContainsString('### Added', $plan['changelog']);
	}

	public function testBreakingChangeIsRejectedOnStableRelease(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Major version bumps are not allowed');

		$this->planner->plan('15.4.2', [
			$this->pr(1, 'feat!: change signing API'),
		], '2026-09-19');
	}

	public function testSkippedAndBotPullRequestsAreIgnored(): void {
		$plan = $this->planner->plan('15.0.0', [
			$this->pr(1, 'chore: release housekeeping', ['skip-changelog']),
			$this->pr(2, 'chore: bump dependency', ['dependencies'], 'dependabot[bot]'),
			$this->pr(3, 'fix: real fix', ['fix']),
		], '2026-09-19');

		self::assertSame('15.0.1', $plan['nextVersion']);
		self::assertStringNotContainsString('housekeeping', $plan['changelog']);
		self::assertStringNotContainsString('dependency', $plan['changelog']);
	}

	public function testDependenciesAreCollapsed(): void {
		$plan = $this->planner->plan('15.0.0', [
			$this->pr(1, 'chore: bump js dependencies', ['dependencies']),
			$this->pr(2, 'chore: bump php dependencies', ['dependencies']),
		], '2026-09-19');

		self::assertSame(1, substr_count($plan['changelog'], 'Bump dependencies'));
	}

	public function testBackportAndStablePrefixesAreRemoved(): void {
		$plan = $this->planner->plan('15.0.0', [
			$this->pr(42, '[stable35] Backport: fix: Keep signature state', ['fix']),
		], '2026-09-19');

		self::assertStringContainsString('- keep signature state [#42]', $plan['changelog']);
		self::assertStringNotContainsString('Backport:', $plan['changelog']);
		self::assertStringNotContainsString('[stable35]', $plan['changelog']);
	}

	public function testTranslationsEntryIsAlwaysIncluded(): void {
		$plan = $this->planner->plan('15.0.0', [
			$this->pr(1, 'fix: repair signing', ['fix']),
		], '2026-09-19');

		self::assertStringContainsString('### Changed', $plan['changelog']);
		self::assertStringContainsString('- Update translations', $plan['changelog']);
	}

	public function testNoReleasablePullRequestsFails(): void {
		$this->expectException(InvalidArgumentException::class);

		$this->planner->plan('15.0.0', [
			$this->pr(1, 'chore: release housekeeping', ['skip-changelog']),
		], '2026-09-19');
	}

	/**
	 * @param array<int,string> $labels
	 * @return array{number:int,title:string,url:string,labels:array<int,string>,author:string}
	 */
	private function pr(int $number, string $title, array $labels = [], string $author = 'contributor'): array {
		return [
			'number' => $number,
			'title' => $title,
			'url' => "https://github.com/LibreSign/libresign/pull/{$number}",
			'labels' => $labels,
			'author' => $author,
		];
	}
}
