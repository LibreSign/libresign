<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Notification;

use OCA\Libresign\Service\Notification\GatewayPayload;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GatewayPayloadTest extends TestCase {
	public function testTemplateOptionsAreAcceptedWithoutProviderDependencies(): void {
		$extra = [
			'template_name' => 'synthetic_utility',
			'template_language' => 'pt_BR',
			'template_category' => 'UTILITY',
			'body_parameters' => ['Name', 'https://example.invalid/sign'],
			'expected_body_parameter_count' => 2,
		];
		$payload = new GatewayPayload('whatsappbusiness', 'Text', $extra);
		$this->assertSame($extra, $payload->extra);
	}

	#[DataProvider('malformedOptions')]
	public function testOptionsAreRejectedBeforeSyncOrQueuedDelivery(array $extra): void {
		$this->expectException(\InvalidArgumentException::class);
		new GatewayPayload('whatsappbusiness', 'Text', $extra);
	}

	public static function malformedOptions(): array {
		return [
			'unsupported field' => [['code' => '123456']],
			'empty parameters' => [['body_parameters' => []]],
			'unordered indexes' => [['body_parameters' => [2 => 'second', 1 => 'first']]],
			'missing value' => [['body_parameters' => [null]]],
			'count mismatch' => [['body_parameters' => ['first'], 'expected_body_parameter_count' => 2]],
			'invalid count' => [['body_parameters' => ['first'], 'expected_body_parameter_count' => '1']],
			'invalid category' => [['body_parameters' => ['first'], 'template_category' => []]],
			'empty template name' => [['body_parameters' => ['first'], 'template_name' => '']],
		];
	}
}
