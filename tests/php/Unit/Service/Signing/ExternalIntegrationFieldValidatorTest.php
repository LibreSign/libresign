<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Signing;

use OCA\Libresign\Service\Signing\ExternalIntegrationFieldValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ExternalIntegrationFieldValidatorTest extends TestCase {
	/** @return list<array<string, mixed>> */
	private static function fields(): array {
		return [
			['key' => 'baseUrl', 'type' => 'url', 'required' => true, 'secret' => false, 'validation' => ['schemes' => ['https'], 'allowUserInfo' => false]],
			['key' => 'accessToken', 'type' => 'password', 'required' => false, 'secret' => true],
		];
	}

	public function testCreateAcceptsValidMetadataWithoutLeakingOrRequiringOptionalSecret(): void {
		$validator = new ExternalIntegrationFieldValidator();
		$validator->validateCreate(self::fields(), ['baseUrl' => 'https://csc.example.invalid/v1'], []);
		$validator->validateCreate(self::fields(), ['baseUrl' => 'https://csc.example.invalid'], ['accessToken' => 'synthetic-test-credential']);
		$this->addToAssertionCount(2);
	}

	public function testOptionalSecretMetadataMayOmitValidation(): void {
		(new ExternalIntegrationFieldValidator())->validateCreate(self::fields(), ['baseUrl' => 'https://example.org'], ['accessToken' => 'synthetic-token']);
		$this->addToAssertionCount(1);
	}

	public function testUpdateAllowsOmittedSecretAndExplicitOptionalRemoval(): void {
		$validator = new ExternalIntegrationFieldValidator();
		$validator->validateUpdate(self::fields(), ['baseUrl' => 'https://example.org'], []);
		$validator->validateUpdate(self::fields(), ['baseUrl' => 'https://example.org'], ['accessToken' => null]);
		$this->addToAssertionCount(2);
	}

	/** @return array<string, array{array<mixed, mixed>, array<mixed, mixed>}> */
	public static function invalidInputs(): array {
		return [
			'unknown public key' => [['baseUrl' => 'https://example.org', 'unexpected' => 'value'], []],
			'secret in public configuration' => [['baseUrl' => 'https://example.org', 'accessToken' => 'leak'], []],
			'public field in secret configuration' => [['baseUrl' => 'https://example.org'], ['baseUrl' => 'leak']],
			'unknown secret key' => [['baseUrl' => 'https://example.org'], ['unknownSecret' => 'leak']],
			'missing required URL' => [[], []],
			'numeric public key' => [[5 => 'value', 'baseUrl' => 'https://example.org'], []],
			'empty token' => [['baseUrl' => 'https://example.org'], ['accessToken' => '']],
			'null on creation' => [['baseUrl' => 'https://example.org'], ['accessToken' => null]],
		];
	}

	#[DataProvider('invalidInputs')]
	public function testCreateRejectsWrongFieldPlacementAndMissingValues(array $configuration, array $secrets): void {
		$this->expectException(\InvalidArgumentException::class);
		(new ExternalIntegrationFieldValidator())->validateCreate(self::fields(), $configuration, $secrets);
	}

	/** @return array<string, array{string}> */
	public static function unsafeUrls(): array {
		return [
			'plain HTTP' => ['http://example.org'],
			'empty URL' => [''],
			'userinfo' => ['https://admin:pass@example.org'],
			'fragment' => ['https://example.org/#token'],
			'localhost' => ['https://localhost'],
			'localhost trailing dot' => ['https://localhost.'],
			'loopback IPv4' => ['https://127.0.0.1'],
			'private IPv4' => ['https://192.168.1.1'],
			'link-local IPv4' => ['https://169.254.169.254'],
			'CGNAT IPv4' => ['https://100.64.0.1'],
			'public IPv4 without hostname' => ['https://8.8.8.8'],
			'IPv6 loopback' => ['https://[::1]'],
			'IPv6 documentation range' => ['https://[2001:db8::1]'],
			'local hostname' => ['https://csc.local'],
			'local trailing dot' => ['https://csc.local.'],
			'internal hostname' => ['https://csc.internal'],
			'embedded newline' => ["https://example.org\n"],
			'credential in query string' => ['https://example.org/v1?token=synthetic-private-value'],
			'noncanonical numeric host' => ['https://0177.0.0.1'],
		];
	}

	#[DataProvider('unsafeUrls')]
	public function testUnsafeUrlsAreRejected(string $url): void {
		$this->expectException(\InvalidArgumentException::class);
		(new ExternalIntegrationFieldValidator())->validateCreate(self::fields(), ['baseUrl' => $url], []);
	}

	public function testRequiredSecretCannotBeCleared(): void {
		$fields = self::fields();
		$fields[1]['required'] = true;
		$this->expectException(\InvalidArgumentException::class);
		(new ExternalIntegrationFieldValidator())->validateUpdate($fields, ['baseUrl' => 'https://example.org'], ['accessToken' => null]);
	}

	public function testRequiredSecretMustBePresentOnCreation(): void {
		$fields = self::fields();
		$fields[1]['required'] = true;
		$this->expectException(\InvalidArgumentException::class);
		(new ExternalIntegrationFieldValidator())->validateCreate($fields, ['baseUrl' => 'https://example.org'], []);
	}

	/** @return array<string, array{array<string, mixed>}> */
	public static function misplacedConfidentialInputs(): array {
		return [
			'credential in public field' => [['baseUrl' => 'https://example.org', 'accessToken' => 'synthetic-private-value']],
			'credential in URL userinfo' => [['baseUrl' => 'https://synthetic-private-value@example.org']],
		];
	}

	#[DataProvider('misplacedConfidentialInputs')]
	public function testMalformedPublicConfigurationCannotLeakViaExceptionArguments(array $configuration): void {
		$previous = ini_get('zend.exception_ignore_args');
		$this->assertNotFalse($previous);
		try {
			$this->assertNotFalse(ini_set('zend.exception_ignore_args', '0'));
			try {
				(new ExternalIntegrationFieldValidator())->validateCreate(self::fields(), $configuration, []);
				$this->fail('Malformed configuration must be rejected');
			} catch (\InvalidArgumentException $e) {
				$validatorFrames = array_values(array_filter(
					$e->getTrace(),
					static fn (array $frame): bool => ($frame['class'] ?? '') === ExternalIntegrationFieldValidator::class,
				));
				$this->assertNotEmpty($validatorFrames);
				$this->assertStringNotContainsString('synthetic-private-value', var_export($validatorFrames, true));
			}
		} finally {
			ini_set('zend.exception_ignore_args', $previous);
		}
	}
}
