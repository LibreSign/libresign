<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

require_once __DIR__ . '/../../scripts/release/CommandRunner.php';
require_once __DIR__ . '/../../scripts/release/GitHubReleaseManager.php';
require_once __DIR__ . '/FakeCommandRunner.php';

use LibreSign\Release\GitHubReleaseManager;
use LibreSign\Release\Tests\FakeCommandRunner;
use PHPUnit\Framework\TestCase;

final class GitHubReleaseManagerTest extends TestCase {
	public function testUpdatesExistingDraftInsteadOfCreatingAnotherRelease(): void {
		$runner = new FakeCommandRunner();
		$runner->expect(
			['gh', 'api', 'repos/LibreSign/libresign/releases?per_page=100'],
			'[{"id":123,"tag_name":"v15.0.1","draft":true}]',
		);
		$runner->expect([
			'gh',
			'api',
			'--method',
			'PATCH',
			'repos/LibreSign/libresign/releases/123',
			'-f',
			'tag_name=v15.0.1',
			'-f',
			'target_commitish=abc123',
			'-f',
			'name=v15.0.1',
			'-F',
			'draft=true',
			'-F',
			'prerelease=false',
			'-F',
			'body=@release-notes.md',
		]);

		(new GitHubReleaseManager($runner))->createOrUpdateDraft(
			'LibreSign/libresign',
			'v15.0.1',
			'abc123',
			'release-notes.md',
		);

		$runner->assertComplete();
	}

	public function testRejectsExistingPublishedRelease(): void {
		$runner = new FakeCommandRunner();
		$runner->expect(
			['gh', 'api', 'repos/LibreSign/libresign/releases?per_page=100'],
			'[{"id":123,"tag_name":"v15.0.1","draft":false}]',
		);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('is not a draft');

		(new GitHubReleaseManager($runner))->createOrUpdateDraft(
			'LibreSign/libresign',
			'v15.0.1',
			'abc123',
			'release-notes.md',
		);
	}
}
