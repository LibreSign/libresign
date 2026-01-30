<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\SignRequest;

use OCA\Libresign\Service\SignRequest\ProgressPollDecisionPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ProgressPollDecisionPolicyTest extends TestCase {
	private ProgressPollDecisionPolicy $policy;

	protected function setUp(): void {
		parent::setUp();
		$this->policy = new ProgressPollDecisionPolicy();
	}

	#[DataProvider('normalizeStatusProvider')]
	public function testNormalizeCachedStatus(mixed $cachedStatus, ?int $expected): void {
		$this->assertSame($expected, $this->policy->normalizeCachedStatus($cachedStatus));
	}

	public static function normalizeStatusProvider(): array {
		return [
			'null stays null' => [null, null],
			'false stays null' => [false, null],
			'int stays int' => [3, 3],
			'string coerces' => ['5', 5],
		];
	}

	#[DataProvider('initialStatusProvider')]
	public function testInitialStatusFromCache(?int $cachedStatus, int $initialStatus, ?int $expected): void {
		$this->assertSame($expected, $this->policy->initialStatusFromCache($cachedStatus, $initialStatus));
	}

	public static function initialStatusProvider(): array {
		return [
			'no cache' => [null, 1, null],
			'cache matches initial' => [2, 2, null],
			'cache differs from initial' => [3, 1, 3],
		];
	}

	#[DataProvider('cacheChangeProvider')]
	public function testStatusFromCacheChange(?int $previous, ?int $current, ?int $expected): void {
		$this->assertSame($expected, $this->policy->statusFromCacheChange($previous, $current));
	}

	public static function cacheChangeProvider(): array {
		return [
			'no current status' => [1, null, null],
			'no change' => [2, 2, null],
			'change detected' => [1, 3, 3],
		];
	}

	#[DataProvider('progressChangeProvider')]
	public function testStatusFromProgressChange(?int $current, ?int $previous, int $initial, int $expected): void {
		$this->assertSame($expected, $this->policy->statusFromProgressChange($current, $previous, $initial));
	}

	public static function progressChangeProvider(): array {
		return [
			'prefer current cache status' => [5, 1, 0, 5],
			'use previous cache when current cleared' => [null, 1, 0, 1],
			'fallback to initial when no cache' => [null, null, 2, 2],
		];
	}
}
