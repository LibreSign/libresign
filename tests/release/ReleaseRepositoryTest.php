<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

require_once __DIR__ . '/../../scripts/release/CommandRunner.php';
require_once __DIR__ . '/../../scripts/release/ReleaseFiles.php';
require_once __DIR__ . '/../../scripts/release/ReleaseRepository.php';
require_once __DIR__ . '/FakeCommandRunner.php';

use LibreSign\Release\ReleaseRepository;
use LibreSign\Release\Tests\FakeCommandRunner;
use PHPUnit\Framework\TestCase;

final class ReleaseRepositoryTest extends TestCase {
	public function testCollectPullRequestsDeduplicatesCommitsAndFiltersBranch(): void {
		$runner = new FakeCommandRunner();
		$runner->expect(['git', 'rev-list', '--reverse', 'v15.0.0..HEAD'], "aaa\nbbb");
		$runner->expect(
			['gh', 'api', '-H', 'Accept: application/vnd.github+json', 'repos/LibreSign/libresign/commits/aaa/pulls'],
			json_encode([
				$this->pullRequest(10, 'stable35'),
				$this->pullRequest(11, 'main'),
			], JSON_THROW_ON_ERROR),
		);
		$runner->expect(
			['gh', 'api', '-H', 'Accept: application/vnd.github+json', 'repos/LibreSign/libresign/commits/bbb/pulls'],
			json_encode([$this->pullRequest(10, 'stable35')], JSON_THROW_ON_ERROR),
		);

		$result = (new ReleaseRepository($runner))->collectPullRequests(
			'.',
			'LibreSign/libresign',
			'stable35',
			'v15.0.0',
		);

		self::assertCount(1, $result);
		self::assertSame(10, $result[0]['number']);
		$runner->assertComplete();
	}

	public function testPendingBackportsUsesRepositorySearch(): void {
		$runner = new FakeCommandRunner();
		$runner->expect(
			[
				'gh',
				'api',
				'-H',
				'Accept: application/vnd.github+json',
				'/search/issues',
				'-f',
				'q=repo:LibreSign/libresign is:pr is:open label:backport-request',
			],
			'{"items":[{"number":42,"title":"Backport fix"}]}',
		);

		$result = (new ReleaseRepository($runner))->pendingBackports('LibreSign/libresign');

		self::assertSame([['number' => 42, 'title' => 'Backport fix']], $result);
		$runner->assertComplete();
	}

	/** @return array<string,mixed> */
	private function pullRequest(int $number, string $base): array {
		return [
			'number' => $number,
			'title' => 'fix: example',
			'html_url' => 'https://github.com/LibreSign/libresign/pull/' . $number,
			'merged_at' => '2026-09-19T10:00:00Z',
			'base' => ['ref' => $base],
			'user' => ['login' => 'contributor'],
			'labels' => [['name' => 'fix']],
		];
	}
}
