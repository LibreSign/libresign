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
			[
				'key' => 'baseUrl',
				'type' => 'url',
				'required' => true,
				'secret' => false,
				'validation' => ['schemes' => ['https'], 'allowUserInfo' => false],
			],
			['key' => 'accessToken', 'type' => 'password', 'required' => false, 'secret' => true],
		];
	}

	public function testCreateAcceptsValidMetadataWithoutLeakingOrRequiringOptionalSecret(): void {
		$validator = new ExternalIntegrationFieldValidator();
		$validator->validateCreate(self::fields(), ['baseUrl' => 'https://csc.example.invalid/v1'], []);
		$validator->validateCreate(
			self::fields(),
			['baseUrl' => 'https://csc.example.invalid'],
			['accessToken' => 'synthetic-test-credential'],
		);
		$this->addToAssertionCount(2);
	}

	public function testOptionalSecretMetadataMayOmitValidation(): void {
		(new ExternalIntegrationFieldValidator())->validateCreate(
			self::fields(),
			['baseUrl' => 'https://example.org'],
			['accessToken' => 'synthetic-token'],
		);
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
			'hexadecimal loopback' => ['https://0x7f.1/'],
			'hexadecimal metadata address' => ['https://0xa9.0xfe.0xa9.0xfe/'],
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
		(new ExternalIntegrationFieldValidator())->validateUpdate(
			$fields,
			['baseUrl' => 'https://example.org'],
			['accessToken' => null],
		);
	}

	public function testRequiredSecretMustBePresentOnCreation(): void {
		$fields = self::fields();
		$fields[1]['required'] = true;
		$this->expectException(\InvalidArgumentException::class);
		(new ExternalIntegrationFieldValidator())->validateCreate($fields, ['baseUrl' => 'https://example.org'], []);
	}

	/**
	 * @param array<string, mixed> $changes
	 * @param list<string> $removed
	 * @return list<array<string, mixed>>
	 */
	private static function changedField(int $index, array $changes = [], array $removed = []): array {
		$fields = self::fields();
		$fields[$index] = array_replace($fields[$index], $changes);
		foreach ($removed as $key) {
			unset($fields[$index][$key]);
		}
		return $fields;
	}

	/** @return array<string, array{array<mixed, mixed>}> */
	public static function invalidFieldDefinitions(): array {
		$duplicate = self::fields();
		$duplicate[] = $duplicate[0];
		return [
			'non-array field entry' => [[42]],
			'empty field key' => [self::changedField(0, ['key' => ''])],
			'numeric field key' => [self::changedField(0, ['key' => 10])],
			'duplicate field key' => [$duplicate],
			'missing field type' => [self::changedField(0, [], ['type'])],
			'missing secret flag' => [self::changedField(0, [], ['secret'])],
			'missing required flag' => [self::changedField(0, [], ['required'])],
			'non-boolean secret flag' => [self::changedField(0, ['secret' => 'false'])],
			'non-boolean required flag' => [self::changedField(0, ['required' => 1])],
			'non-array URL validation' => [self::changedField(0, ['validation' => 'https'])],
			'missing URL validation' => [self::changedField(0, [], ['validation'])],
			'URL allows HTTP' => [self::changedField(0, ['validation' => [
				'schemes' => ['http', 'https'], 'allowUserInfo' => false,
			]])],
			'URL missing HTTPS scheme' => [self::changedField(0, ['validation' => [
				'allowUserInfo' => false,
			]])],
			'URL allows userinfo' => [self::changedField(0, ['validation' => [
				'schemes' => ['https'], 'allowUserInfo' => true,
			]])],
			'URL missing userinfo restriction' => [self::changedField(0, ['validation' => [
				'schemes' => ['https'],
			]])],
			'public field has password type' => [self::changedField(0, ['type' => 'password'])],
			'secret field has URL type' => [self::changedField(1, ['type' => 'url'])],
			'secret field has non-array validation' => [self::changedField(1, ['validation' => 123])],
		];
	}

	#[DataProvider('invalidFieldDefinitions')]
	public function testInvalidDriverMetadataIsRejected(array $fields): void {
		$this->expectException(\InvalidArgumentException::class);
		(new ExternalIntegrationFieldValidator())->validateCreate(
			$fields,
			['baseUrl' => 'https://example.org'],
			[],
		);
	}

	/** @return array<string, array{string}> */
	public static function allowedHttpsEndpoints(): array {
		return [
			'HTTPS with path' => ['https://sign.example.org/csc/v1'],
			'uppercase scheme' => ['HTTPS://sign.example.org/csc/v1'],
			'uppercase hostname' => ['https://SIGN.EXAMPLE.ORG/csc'],
			'explicit HTTPS port' => ['https://sign.example.org:8443/csc'],
			'fully qualified hostname' => ['https://sign.example.org./csc'],
			'hyphenated DNS label' => ['https://sign-api.example.org'],
		];
	}

	#[DataProvider('allowedHttpsEndpoints')]
	public function testAllowedHttpsEndpointsRemainUsable(string $url): void {
		(new ExternalIntegrationFieldValidator())->validateCreate(
			self::fields(),
			['baseUrl' => $url],
			[],
		);
		$this->addToAssertionCount(1);
	}

	/** @return array<string, array{mixed}> */
	public static function invalidEndpointTypesAndBoundaries(): array {
		return [
			'null URL' => [null],
			'numeric URL' => [123],
			'boolean URL' => [true],
			'array URL' => [['https://example.org']],
			'URL exceeds 2048 bytes' => ['https://example.org/' . str_repeat('a', 2029)],
			'FTP scheme' => ['ftp://example.org'],
			'missing scheme' => ['example.org'],
			'scheme relative URL' => ['//example.org'],
			'missing host' => ['https:///v1'],
			'userinfo without password' => ['https://user@example.org'],
			'empty query' => ['https://example.org/v1?'],
			'empty fragment' => ['https://example.org/v1#'],
			'embedded space in path' => ['https://example.org/a b'],
			'embedded tab' => ["https://example.org/\t"],
			'DEL character' => ["https://example.org/\x7f"],
			'non-local single-label host' => ['https://signature'],
			'localhost subdomain' => ['https://sign.localhost'],
			'internal trailing dot' => ['https://sign.internal.'],
			'all-numeric domain' => ['https://01234567.89'],
			'invalid DNS label' => ['https://bad-.example.org'],
		];
	}

	#[DataProvider('invalidEndpointTypesAndBoundaries')]
	public function testInvalidEndpointTypesAndBoundariesAreRejected(mixed $url): void {
		$this->expectException(\InvalidArgumentException::class);
		(new ExternalIntegrationFieldValidator())->validateCreate(
			self::fields(),
			['baseUrl' => $url],
			[],
		);
	}

	/** @return array<string, array{array<mixed, mixed>, array<mixed, mixed>}> */
	public static function invalidUpdates(): array {
		return [
			'unknown public field' => [['baseUrl' => 'https://example.org', 'unsupported' => 'value'], []],
			'secret placed in public config' => [['baseUrl' => 'https://example.org', 'accessToken' => 'leak'], []],
			'public field placed in secret patch' => [['baseUrl' => 'https://example.org'], ['baseUrl' => 'wrong']],
			'unknown secret patch field' => [['baseUrl' => 'https://example.org'], ['unexpected' => 'wrong']],
			'missing required URL on update' => [[], []],
			'numeric public field on update' => [[42 => 'wrong', 'baseUrl' => 'https://example.org'], []],
			'numeric secret field on update' => [['baseUrl' => 'https://example.org'], [42 => 'wrong']],
			'empty secret replacement' => [['baseUrl' => 'https://example.org'], ['accessToken' => '']],
			'boolean secret replacement' => [['baseUrl' => 'https://example.org'], ['accessToken' => true]],
			'array secret replacement' => [['baseUrl' => 'https://example.org'], ['accessToken' => []]],
			'oversized secret replacement' => [['baseUrl' => 'https://example.org'], [
				'accessToken' => str_repeat('x', 8193),
			]],
		];
	}

	#[DataProvider('invalidUpdates')]
	public function testInvalidUpdatePayloadsAreRejected(array $configuration, array $secretChanges): void {
		$this->expectException(\InvalidArgumentException::class);
		(new ExternalIntegrationFieldValidator())->validateUpdate(
			self::fields(),
			$configuration,
			$secretChanges,
		);
	}

	public function testSecretMaximumLengthIsAcceptedAtCreateAndUpdate(): void {
		$token = str_repeat('t', 8192);
		$validator = new ExternalIntegrationFieldValidator();
		$validator->validateCreate(
			self::fields(),
			['baseUrl' => 'https://example.org'],
			['accessToken' => $token],
		);
		$validator->validateUpdate(
			self::fields(),
			['baseUrl' => 'https://example.org'],
			['accessToken' => $token],
		);
		$this->addToAssertionCount(2);
	}

	public function testRequiredSecretIsAcceptedWhenProvided(): void {
		$fields = self::changedField(1, ['required' => true]);
		(new ExternalIntegrationFieldValidator())->validateCreate(
			$fields,
			['baseUrl' => 'https://example.org'],
			['accessToken' => 'synthetic-credential'],
		);
		$this->addToAssertionCount(1);
	}

	public function testOptionalUrlMayBeOmitted(): void {
		$fields = self::changedField(0, ['required' => false]);
		(new ExternalIntegrationFieldValidator())->validateCreate($fields, [], []);
		$this->addToAssertionCount(1);
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
