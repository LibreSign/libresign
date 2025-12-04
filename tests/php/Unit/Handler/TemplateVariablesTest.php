<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Handler;

use OCA\Libresign\Handler\TemplateVariables;
use OCA\Libresign\Tests\Unit\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @internal
 */
final class TemplateVariablesTest extends TestCase {
	private TemplateVariables $variables;

	public function setUp(): void {
		parent::setUp();
		$this->variables = new TemplateVariables();
	}

	public static function provideValidValues(): array {
		return [
			'uuid' => ['uuid', 'test-uuid-123'],
			'signers' => ['signers', [['name' => 'John']]],
			'signedBy' => ['signedBy', 'LibreSign'],
			'direction' => ['direction', 'ltr'],
			'linkToSite' => ['linkToSite', 'https://example.com'],
			'validationSite' => ['validationSite', 'https://validate.com'],
			'validateIn' => ['validateIn', 'Validate in %s'],
			'qrcode' => ['qrcode', 'base64string'],
			'qrcodeSize' => ['qrcodeSize', 108],
		];
	}

	#[DataProvider('provideValidValues')]
	public function testSettersAndGetters(string $variable, mixed $value): void {
		$setter = 'set' . ucfirst($variable);
		$getter = 'get' . ucfirst($variable);

		$result = $this->variables->$setter($value);
		$this->assertSame($this->variables, $result);

		$this->assertSame($value, $this->variables->$getter());

		$this->assertTrue($this->variables->has($variable));
	}

	public function testGettersReturnNullForUnsetVariables(): void {
		$this->assertNull($this->variables->getUuid());
		$this->assertNull($this->variables->getQrcodeSize());
	}

	public function testToArrayAndMerge(): void {
		$this->variables->setUuid('uuid-123')->setSignedBy('Signer');

		$array = $this->variables->toArray();
		$this->assertSame(['uuid' => 'uuid-123', 'signedBy' => 'Signer'], $array);

		$result = $this->variables->merge(['uuid' => 'new-uuid', 'direction' => 'rtl']);
		$this->assertSame($this->variables, $result);
		$this->assertSame('new-uuid', $this->variables->getUuid());
		$this->assertSame('rtl', $this->variables->getDirection());
	}

	public static function provideInvalidVariables(): array {
		return [
			['setInvalidVar'],
			['getInvalidVar'],
		];
	}

	#[DataProvider('provideInvalidVariables')]
	public function testRejectsInvalidVariables(string $method): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage("Template variable 'invalidVar' is not allowed");

		$this->variables->$method('value');
	}

	public function testMergeRejectsInvalidVariables(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage("Template variable 'invalidVar' is not allowed");

		$this->variables->merge(['invalidVar' => 'value']);
	}

	public static function provideInvalidTypes(): array {
		return [
			'uuid expects string' => ['uuid', 123, 'string', 'integer'],
			'signers expects array' => ['signers', 'string', 'array', 'string'],
			'qrcodeSize expects integer' => ['qrcodeSize', '108', 'integer', 'string'],
		];
	}

	#[DataProvider('provideInvalidTypes')]
	public function testRejectsInvalidTypes(string $variable, mixed $value, string $expected, string $actual): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage("must be of type {$expected}, got {$actual}");

		$method = 'set' . ucfirst($variable);
		$this->variables->$method($value);
	}

	#[DataProvider('provideInvalidTypes')]
	public function testMergeRejectsInvalidTypes(string $variable, mixed $value, string $expected, string $actual): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage("must be of type {$expected}, got {$actual}");

		$this->variables->merge([$variable => $value]);
	}

	public function testRejectsInvalidMethod(): void {
		$this->expectException(\BadMethodCallException::class);
		$this->expectExceptionMessage('Method invalidMethod does not exist');

		$this->variables->invalidMethod();
	}
}
