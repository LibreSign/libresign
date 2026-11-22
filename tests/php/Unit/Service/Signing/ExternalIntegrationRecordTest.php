<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Signing;

use OCA\Libresign\Service\Signing\ExternalIntegrationRecord;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ExternalIntegrationRecordTest extends TestCase {
	/** @return array<string, array{array<string, mixed>}> */
	public static function configurations(): array {
		return [
			'empty configuration' => [[]],
			'configured endpoint' => [['serviceUrl' => 'https://example.invalid']],
		];
	}

	#[DataProvider('configurations')]
	public function testConfigurationSerializesAsAnObject(array $configuration): void {
		$record = new ExternalIntegrationRecord(
			str_repeat('a', 32), 'csc', 'CSC instance', $configuration, false, true, 1,
			'2026-11-22T00:00:00+00:00', '2026-11-22T00:00:00+00:00',
		);
		$payload = json_decode(json_encode($record, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
		$this->assertInstanceOf(\stdClass::class, $payload->configuration);
		$this->assertSame($configuration, (array)$payload->configuration);
		$this->assertTrue($payload->hasSecrets);
		$this->assertFalse(isset($payload->secrets_enc));
	}
}
