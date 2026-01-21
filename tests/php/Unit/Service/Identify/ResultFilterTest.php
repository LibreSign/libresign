<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Identify;

use OCA\Libresign\Service\Identify\ResultFilter;
use PHPUnit\Framework\TestCase;

class ResultFilterTest extends TestCase {
	private ResultFilter $filter;

	protected function setUp(): void {
		parent::setUp();
		$this->filter = new ResultFilter();
	}

	public function testUnifyRemovesDuplicatesByShareWith(): void {
		$input = [
			[
				['value' => ['shareWith' => 'user1'], 'label' => 'User 1'],
				['value' => ['shareWith' => 'user2'], 'label' => 'User 2'],
			],
			[
				['value' => ['shareWith' => 'user1'], 'label' => 'User 1 Duplicate'],
				['value' => ['shareWith' => 'user3'], 'label' => 'User 3'],
			],
		];

		$result = $this->filter->unify($input);

		$this->assertCount(3, $result);
		$this->assertEquals('user1', $result[0]['value']['shareWith']);
		$this->assertEquals('user2', $result[1]['value']['shareWith']);
		$this->assertEquals('user3', $result[2]['value']['shareWith']);
	}

	public function testUnifyWithEmptyInput(): void {
		$result = $this->filter->unify([]);
		$this->assertCount(0, $result);
	}

	public function testExcludeEmptyRemovesBlankShareWith(): void {
		$input = [
			['value' => ['shareWith' => 'user1']],
			['value' => ['shareWith' => '']],
			['value' => ['shareWith' => 'user2']],
		];

		$result = $this->filter->excludeEmpty($input);

		$this->assertCount(2, $result);
		$resultValues = array_values($result);
		$this->assertEquals('user1', $resultValues[0]['value']['shareWith']);
		$this->assertEquals('user2', $resultValues[1]['value']['shareWith']);
	}

	public function testExcludeNotAllowedRemovesNoMethod(): void {
		$input = [
			['method' => 'email'],
			['method' => ''],
			['method' => 'sms'],
			[],
			['method' => 'whatsapp'],
		];

		$result = $this->filter->excludeNotAllowed($input);

		$this->assertCount(3, $result);
		$resultValues = array_values($result);
		$this->assertEquals('email', $resultValues[0]['method']);
		$this->assertEquals('sms', $resultValues[1]['method']);
		$this->assertEquals('whatsapp', $resultValues[2]['method']);
	}

	public function testSequentialFilteringRealisticScenario(): void {
		// Realistic case: mixed valid and invalid data
		$input = [
			['value' => ['shareWith' => 'user1'], 'method' => 'email'],
			['value' => ['shareWith' => ''], 'method' => 'sms'],
			['value' => ['shareWith' => '+5521987776666'], 'method' => ''],
			['value' => ['shareWith' => 'test@example.com'], 'method' => 'email'],
		];

		// Step 1: Remove empty shareWith
		$result = $this->filter->excludeEmpty($input);
		$this->assertCount(3, $result);

		// Step 2: Remove results without method
		$result = $this->filter->excludeNotAllowed($result);
		$this->assertCount(2, $result);

		// Verify final results
		$resultValues = array_values($result);
		$this->assertEquals('user1', $resultValues[0]['value']['shareWith']);
		$this->assertEquals('email', $resultValues[0]['method']);
		$this->assertEquals('test@example.com', $resultValues[1]['value']['shareWith']);
		$this->assertEquals('email', $resultValues[1]['method']);
	}
}
