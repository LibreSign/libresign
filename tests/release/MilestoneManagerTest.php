<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

require_once __DIR__ . '/../../scripts/release/CommandRunner.php';
require_once __DIR__ . '/../../scripts/release/MilestoneManager.php';
require_once __DIR__ . '/FakeCommandRunner.php';

use LibreSign\Release\MilestoneManager;
use LibreSign\Release\Tests\FakeCommandRunner;
use PHPUnit\Framework\TestCase;

final class MilestoneManagerTest extends TestCase {
	public function testFinalReleaseFailsWhenMilestoneStillHasOpenItems(): void {
		$runner = new FakeCommandRunner();
		$runner->expect(
			['gh', 'api', 'repos/LibreSign/libresign/milestones?state=all&per_page=100'],
			'[{"number":7,"title":"💚 Next Patch (35)","state":"open"}]',
		);
		$runner->expect(
			['gh', 'api', '--method', 'PATCH', 'repos/LibreSign/libresign/milestones/7', '-f', 'title=v15.0.1'],
		);
		$runner->expect(
			['gh', 'api', '--paginate', 'repos/LibreSign/libresign/issues?state=open&milestone=7&per_page=100'],
			'[{"number":99,"title":"Still open"}]',
		);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('#99');

		(new MilestoneManager($runner))->finalize(
			'LibreSign/libresign',
			35,
			'15.0.1',
			true,
		);
	}

	public function testSupportedStableReusesExistingNextMilestone(): void {
		$runner = new FakeCommandRunner();
		$runner->expect(
			['gh', 'api', 'repos/LibreSign/libresign/milestones?state=all&per_page=100'],
			'[{"number":7,"title":"💚 Next Patch (35)","state":"open"}]',
		);
		$runner->expect(
			['gh', 'api', '--method', 'PATCH', 'repos/LibreSign/libresign/milestones/7', '-f', 'title=v15.0.1'],
		);
		$runner->expect(
			['gh', 'api', '--paginate', 'repos/LibreSign/libresign/issues?state=open&milestone=7&per_page=100'],
			'[{"number":99,"title":"Move me"}]',
		);
		$runner->expect(
			['gh', 'api', 'repos/LibreSign/libresign/milestones?state=open&per_page=100'],
			'[{"number":8,"title":"💚 Next Patch (35)","state":"open"}]',
		);
		$runner->expect(
			['gh', 'api', '--method', 'PATCH', 'repos/LibreSign/libresign/issues/99', '-F', 'milestone=8'],
		);
		$runner->expect(
			['gh', 'api', '--method', 'PATCH', 'repos/LibreSign/libresign/milestones/7', '-f', 'state=closed'],
		);

		(new MilestoneManager($runner))->finalize(
			'LibreSign/libresign',
			35,
			'15.0.1',
			false,
		);

		$runner->assertComplete();
	}
}
