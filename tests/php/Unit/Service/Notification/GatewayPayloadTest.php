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
	#[DataProvider('validDeliveries')]
	public function testValidGatewayDeliveriesRetainTheirOptions(array $options): void {
		$payload = new GatewayPayload('whatsappbusiness', 'Synthetic notification', $options);
		$this->assertSame($options, $payload->extra);
	}

	public static function validDeliveries(): array {
		return [
			'plain text delivery' => [[]],
			'one positional value' => [['body_parameters' => ['First']]],
			'two ordered values' => [['body_parameters' => ['First', 'Second'], 'expected_body_parameter_count' => 2]],
			'template selection' => [[
				'template_name' => 'synthetic_utility',
				'template_language' => 'pt_BR',
				'template_category' => 'UTILITY',
				'body_parameters' => ['Document', 'https://example.invalid/sign'],
				'expected_body_parameter_count' => 2,
			]],
			'unicode text' => [['body_parameters' => ['Documento assinado']]],
		];
	}

	#[DataProvider('invalidDeliveries')]
	public function testInvalidDeliveryOptionsCannotReachEitherTransportMode(array $options): void {
		$secret = 'private-value-synthetic';
		try {
			new GatewayPayload('whatsappbusiness', 'Synthetic notification', $options);
			$this->fail('Invalid gateway options must not be accepted');
		} catch (\InvalidArgumentException $exception) {
			$this->assertStringNotContainsString($secret, $exception->getMessage());
		}
	}

	public static function invalidDeliveries(): array {
		return [
			'unknown option' => [['code' => 'private-value-synthetic']],
			'unknown option with valid body' => [['body_parameters' => ['First'], 'access_token' => 'private-value-synthetic']],
			'empty parameter list' => [['body_parameters' => []]],
			'missing parameter list' => [['expected_body_parameter_count' => 1]],
			'null parameter list' => [['body_parameters' => null]],
			'string instead of list' => [['body_parameters' => 'private-value-synthetic']],
			'associative list' => [['body_parameters' => ['name' => 'First']]],
			'out-of-order numeric keys' => [['body_parameters' => [1 => 'Second', 0 => 'First']]],
			'list with missing index' => [['body_parameters' => [0 => 'First', 2 => 'Third']]],
			'null parameter' => [['body_parameters' => ['First', null]]],
			'boolean parameter' => [['body_parameters' => [true]]],
			'integer parameter' => [['body_parameters' => [42]]],
			'object parameter' => [['body_parameters' => [new \stdClass()]]],
			'empty parameter' => [['body_parameters' => ['']]],
			'blank parameter' => [['body_parameters' => [" \t "]]],
			'invalid unicode' => [['body_parameters' => ["\xFF"]]],
			'overlong parameter' => [['body_parameters' => [str_repeat('x', 4097)]]],
			'excessive parameter count' => [['body_parameters' => array_fill(0, 33, 'value')]],
			'zero expected count' => [['body_parameters' => ['value'], 'expected_body_parameter_count' => 0]],
			'negative expected count' => [['body_parameters' => ['value'], 'expected_body_parameter_count' => -1]],
			'null expected count' => [['body_parameters' => ['value'], 'expected_body_parameter_count' => null]],
			'string expected count' => [['body_parameters' => ['value'], 'expected_body_parameter_count' => '1']],
			'mismatched expected count' => [['body_parameters' => ['value'], 'expected_body_parameter_count' => 2]],
			'non-string template name' => [['body_parameters' => ['value'], 'template_name' => []]],
			'blank template name' => [['body_parameters' => ['value'], 'template_name' => ' ']],
			'oversized template name' => [['body_parameters' => ['value'], 'template_name' => str_repeat('n', 257)]],
			'control character in template name' => [['body_parameters' => ['value'], 'template_name' => "name\nother"]],
			'invalid template language' => [['body_parameters' => ['value'], 'template_language' => null]],
			'invalid template category' => [['body_parameters' => ['value'], 'template_category' => ['UTILITY']]],
			'blank template category' => [['body_parameters' => ['value'], 'template_category' => '']],
			'invalid category unicode' => [['body_parameters' => ['value'], 'template_category' => "\xFF"]],
		];
	}
}
