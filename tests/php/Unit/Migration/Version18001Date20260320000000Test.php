<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Migration;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Migration\Version18001Date20260320000000;
use OCA\Libresign\Service\Policy\Provider\ExpirationRules\ExpirationRulesPolicy;
use OCA\Libresign\Service\Policy\Provider\Footer\FooterPolicyValue;
use OCA\Libresign\Service\Policy\Provider\SignatureText\SignatureTextPolicy;
use OCA\Libresign\Service\Policy\Provider\Tsa\TsaPolicy;
use OCA\Libresign\Service\Policy\Provider\Tsa\TsaPolicyValue;
use OCP\Exceptions\AppConfigTypeConflictException;
use OCP\IAppConfig;
use OCP\Migration\IOutput;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class Version18001Date20260320000000Test extends TestCase {
	private IAppConfig&MockObject $appConfig;

	#[\Override]
	protected function setUp(): void {
		parent::setUp();
		$this->appConfig = $this->createMock(IAppConfig::class);
	}

	public function testMigratesLegacyFooterSettingsIntoStructuredPayload(): void {
		$this->appConfig
			->method('getValueString')
			->willReturnCallback(static function (string $app, string $key, string $default): string {
				if ($app !== Application::APP_ID) {
					return $default;
				}
				$map = [
					'add_footer' => '1',
					'write_qrcode_on_footer' => '0',
					'validation_site' => 'https://validator.example/base/',
					'footer_template_is_default' => '0',
					'docmdp_level' => '',
					'groups_request_sign' => '',
					'policy.signature_flow.system' => '',
					'signature_flow' => '',
					'template_font_size' => '',
					'signature_width' => '',
					'signature_height' => '',
					'signature_font_size' => '',
					'signature_render_mode' => '',
					'collect_metadata' => '',
					'identification_documents' => '',
					'identify_methods' => '',
				];
				return $map[$key] ?? $default;
			});

		$deletedKeys = [];

		$this->appConfig
			->expects($this->atLeastOnce())
			->method('deleteKey')
			->willReturnCallback(static function (string $app, string $key) use (&$deletedKeys): void {
				$deletedKeys[] = [$app, $key];
			});

		$this->appConfig
			->expects($this->atLeastOnce())
			->method('setValueString')
			->willReturnCallback(static function (string $app, string $key, string $value): bool {
				if ($key === 'add_footer') {
					TestCase::assertSame(Application::APP_ID, $app);
					TestCase::assertSame(
						FooterPolicyValue::encode([
							'enabled' => true,
							'writeQrcodeOnFooter' => false,
							'validationSite' => 'https://validator.example/base/',
							'customizeFooterTemplate' => true,
						]),
						$value,
					);
				}

				return true;
			});

		$migration = new Version18001Date20260320000000($this->appConfig);
		$migration->preSchemaChange($this->createMock(IOutput::class), static fn () => null, []);

		self::assertContains([Application::APP_ID, 'add_footer'], $deletedKeys);
	}

	public function testMigratesLegacyTsaSettingsIntoCanonicalPolicyAndDeletesLegacyKeys(): void {
		$this->appConfig
			->method('getValueString')
			->willReturnCallback(static function (string $app, string $key, string $default): string {
				if ($app !== Application::APP_ID) {
					return $default;
				}

				$map = [
					'add_footer' => '',
					'write_qrcode_on_footer' => '',
					'validation_site' => '',
					'footer_template_is_default' => '',
					'collect_metadata' => '',
					'identification_documents' => '',
					'docmdp_level' => '',
					'groups_request_sign' => '',
					'policy.signature_flow.system' => '',
					'signature_flow' => '',
					'template_font_size' => '',
					'signature_width' => '',
					'signature_height' => '',
					'signature_font_size' => '',
					'signature_render_mode' => '',
					'identify_methods' => '',
					TsaPolicy::SYSTEM_APP_CONFIG_KEY => '',
					'tsa_url' => 'https://freetsa.org/tsr',
					'tsa_policy_oid' => '1.2.840.113549.1.9.16.1.4',
					'tsa_auth_type' => 'basic',
					'tsa_username' => 'tsa-user',
				];

				return $map[$key] ?? $default;
			});

		$deletedKeys = [];
		$savedStrings = [];

		$this->appConfig
			->method('deleteKey')
			->willReturnCallback(static function (string $app, string $key) use (&$deletedKeys): void {
				$deletedKeys[] = [$app, $key];
			});

		$this->appConfig
			->method('setValueString')
			->willReturnCallback(static function (string $app, string $key, string $value) use (&$savedStrings): bool {
				$savedStrings[] = [$app, $key, $value];
				return true;
			});

		$migration = new Version18001Date20260320000000($this->appConfig);
		$migration->preSchemaChange($this->createMock(IOutput::class), static fn () => null, []);

		$expectedTsaPayload = TsaPolicyValue::encode([
			'url' => 'https://freetsa.org/tsr',
			'policy_oid' => '1.2.840.113549.1.9.16.1.4',
			'auth_type' => 'basic',
			'username' => 'tsa-user',
		]);

		self::assertContains([Application::APP_ID, TsaPolicy::SYSTEM_APP_CONFIG_KEY, $expectedTsaPayload], $savedStrings);
		self::assertContains([Application::APP_ID, 'tsa_url'], $deletedKeys);
		self::assertContains([Application::APP_ID, 'tsa_policy_oid'], $deletedKeys);
		self::assertContains([Application::APP_ID, 'tsa_auth_type'], $deletedKeys);
		self::assertContains([Application::APP_ID, 'tsa_username'], $deletedKeys);
	}

	public function testReadsLegacyBooleanWhenAddFooterHasTypedBoolValue(): void {
		$this->appConfig
			->method('getValueString')
			->willReturnCallback(static function (string $app, string $key, string $default): string {
				if ($app !== Application::APP_ID) {
					return $default;
				}

				if ($key === 'add_footer') {
					throw new AppConfigTypeConflictException('bool stored');
				}

				if ($key === 'write_qrcode_on_footer') {
					return '1';
				}

				if ($key === 'validation_site') {
					return '';
				}

				if ($key === 'footer_template_is_default') {
					return '1';
				}

				if ($key === 'docmdp_level' || $key === 'identify_methods') {
					return '';
				}

				if ($key === 'policy.signature_flow.system' || $key === 'signature_flow') {
					return '';
				}

				if ($key === 'collect_metadata' || $key === 'identification_documents') {
					return '';
				}

				if (in_array($key, ['template_font_size', 'signature_width', 'signature_height', 'signature_font_size', 'signature_render_mode'], true)) {
					return '';
				}

				if ($key === 'groups_request_sign') {
					return '';
				}

				return $default;
			});

		$this->appConfig
			->expects($this->once())
			->method('getValueBool')
			->with(Application::APP_ID, 'add_footer', true)
			->willReturn(false);

		$deletedKeys = [];

		$this->appConfig
			->expects($this->atLeastOnce())
			->method('deleteKey')
			->willReturnCallback(static function (string $app, string $key) use (&$deletedKeys): void {
				$deletedKeys[] = [$app, $key];
			});

		$this->appConfig
			->expects($this->atLeastOnce())
			->method('setValueString')
			->willReturnCallback(static function (string $app, string $key, string $value): bool {
				if ($key === 'add_footer') {
					TestCase::assertSame(Application::APP_ID, $app);
					TestCase::assertSame(
						FooterPolicyValue::encode([
							'enabled' => false,
							'writeQrcodeOnFooter' => true,
							'validationSite' => '',
							'customizeFooterTemplate' => false,
						]),
						$value,
					);
				}

				return true;
			});

		$migration = new Version18001Date20260320000000($this->appConfig);
		$migration->preSchemaChange($this->createMock(IOutput::class), static fn () => null, []);

		self::assertContains([Application::APP_ID, 'add_footer'], $deletedKeys);
	}

	public function testMigratesGroupsRequestSignFromTypedArrayToCanonicalString(): void {
		$this->appConfig
			->method('getValueString')
			->willReturnCallback(static function (string $app, string $key, string $default): string {
				if ($app !== Application::APP_ID) {
					return $default;
				}

				if ($key === 'groups_request_sign') {
					throw new AppConfigTypeConflictException('array stored');
				}

				return '';
			});

		$this->appConfig
			->expects($this->once())
			->method('getValueArray')
			->with(Application::APP_ID, 'groups_request_sign', ['admin'])
			->willReturn(['finance', 'admin']);

		$deletedKeys = [];
		$this->appConfig
			->expects($this->atLeastOnce())
			->method('deleteKey')
			->willReturnCallback(static function (string $app, string $key) use (&$deletedKeys): void {
				$deletedKeys[] = [$app, $key];
			});

		$this->appConfig
			->expects($this->atLeastOnce())
			->method('setValueString')
			->willReturnCallback(static function (string $app, string $key, string $value): bool {
				if ($key === 'groups_request_sign') {
					TestCase::assertSame('["admin","finance"]', $value);
				}

				return true;
			});

		$migration = new Version18001Date20260320000000($this->appConfig);
		$migration->preSchemaChange($this->createMock(IOutput::class), static fn () => null, []);

		self::assertContains([Application::APP_ID, 'groups_request_sign'], $deletedKeys);
	}

	public function testMigratesSignatureTextFloatSettingsFromLegacyStrings(): void {
		$this->appConfig
			->method('getValueString')
			->willReturnCallback(static function (string $app, string $key, string $default): string {
				if ($app !== Application::APP_ID) {
					return $default;
				}
				$map = [
					'add_footer' => '',
					'write_qrcode_on_footer' => '',
					'validation_site' => '',
					'footer_template_is_default' => '',
					'collect_metadata' => '',
					'identification_documents' => '',
					'docmdp_level' => '',
					'groups_request_sign' => '',
					'policy.signature_flow.system' => '',
					'signature_flow' => '',
					SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY_TEMPLATE_FONT_SIZE => '11.5',
					SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY_SIGNATURE_WIDTH => '350',
					SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY_SIGNATURE_HEIGHT => '100.25',
					SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY_SIGNATURE_FONT_SIZE => '18',
					SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY_RENDER_MODE => 'default',
					'identify_methods' => '',
				];
				return $map[$key] ?? $default;
			});

		$deleted = [];
		$this->appConfig
			->method('deleteKey')
			->willReturnCallback(static function (string $app, string $key) use (&$deleted): void {
				$deleted[] = [$app, $key];
			});

		$savedStrings = [];
		$this->appConfig
			->method('setValueString')
			->willReturnCallback(static function (string $app, string $key, string $value) use (&$savedStrings): bool {
				$savedStrings[$key] = $value;
				return true;
			});

		$migration = new Version18001Date20260320000000($this->appConfig);
		$migration->preSchemaChange($this->createMock(IOutput::class), static fn () => null, []);

		self::assertArrayHasKey(SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY, $savedStrings);
		$decoded = json_decode($savedStrings[SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY], true);
		self::assertIsArray($decoded);
		self::assertSame(11.5, $decoded['template_font_size']);
		self::assertEquals(350.0, $decoded['signature_width']);
		self::assertSame(100.25, $decoded['signature_height']);
		self::assertEquals(18.0, $decoded['signature_font_size']);
		self::assertContains([Application::APP_ID, SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY_TEMPLATE_FONT_SIZE], $deleted);
		self::assertContains([Application::APP_ID, SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY_SIGNATURE_WIDTH], $deleted);
		self::assertContains([Application::APP_ID, SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY_SIGNATURE_HEIGHT], $deleted);
		self::assertContains([Application::APP_ID, SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY_SIGNATURE_FONT_SIZE], $deleted);
		self::assertArrayNotHasKey(SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY_RENDER_MODE, $savedStrings);
	}

	public function testNormalizesSignatureTextRenderModeToCanonicalPolicyValue(): void {
		$this->appConfig
			->method('getValueString')
			->willReturnCallback(static function (string $app, string $key, string $default): string {
				if ($app !== Application::APP_ID) {
					return $default;
				}
				$map = [
					'add_footer' => '',
					'write_qrcode_on_footer' => '',
					'validation_site' => '',
					'footer_template_is_default' => '',
					'collect_metadata' => '',
					'identification_documents' => '',
					'docmdp_level' => '',
					'groups_request_sign' => '',
					'policy.signature_flow.system' => '',
					'signature_flow' => '',
					SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY_TEMPLATE_FONT_SIZE => '',
					SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY_SIGNATURE_WIDTH => '',
					SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY_SIGNATURE_HEIGHT => '',
					SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY_SIGNATURE_FONT_SIZE => '',
					SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY_RENDER_MODE => 'GRAPHIC_AND_DESCRIPTION',
					'identify_methods' => '',
				];
				return $map[$key] ?? $default;
			});

		$deleted = [];

		$this->appConfig
			->method('deleteKey')
			->willReturnCallback(static function (string $app, string $key) use (&$deleted): void {
				$deleted[] = [$app, $key];
			});

		$renderModeWasNormalized = false;
		$this->appConfig
			->method('setValueString')
			->willReturnCallback(static function (string $app, string $key, string $value) use (&$renderModeWasNormalized): bool {
				if ($key === SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY) {
					TestCase::assertSame(Application::APP_ID, $app);
					$decoded = json_decode($value, true);
					TestCase::assertIsArray($decoded);
					TestCase::assertSame('default', $decoded['render_mode'] ?? null);
					$renderModeWasNormalized = true;
				}
				return true;
			});

		$migration = new Version18001Date20260320000000($this->appConfig);
		$migration->preSchemaChange($this->createMock(IOutput::class), static fn () => null, []);

		self::assertTrue($renderModeWasNormalized);
		self::assertContains([Application::APP_ID, SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY_RENDER_MODE], $deleted);
	}

	public function testMigratesPendingBooleanPoliciesFromLegacyStrings(): void {
		$this->appConfig
			->method('getValueString')
			->willReturnCallback(static function (string $app, string $key, string $default): string {
				if ($app !== Application::APP_ID) {
					return $default;
				}
				$map = [
					'add_footer' => '',
					'write_qrcode_on_footer' => '',
					'validation_site' => '',
					'footer_template_is_default' => '',
					'collect_metadata' => '1',
					'identification_documents' => 'false',
					'docmdp_level' => '',
					'groups_request_sign' => '',
					'policy.signature_flow.system' => '',
					'signature_flow' => '',
					SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY_TEMPLATE_FONT_SIZE => '',
					SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY_SIGNATURE_WIDTH => '',
					SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY_SIGNATURE_HEIGHT => '',
					SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY_SIGNATURE_FONT_SIZE => '',
					SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY_RENDER_MODE => '',
					'identify_methods' => '',
				];
				return $map[$key] ?? $default;
			});

		$savedBools = [];
		$this->appConfig
			->method('setValueBool')
			->willReturnCallback(static function (string $app, string $key, bool $value) use (&$savedBools): bool {
				$savedBools[$key] = $value;
				return true;
			});

		$deleted = [];
		$this->appConfig
			->method('deleteKey')
			->willReturnCallback(static function (string $app, string $key) use (&$deleted): void {
				$deleted[] = [$app, $key];
			});

		$migration = new Version18001Date20260320000000($this->appConfig);
		$migration->preSchemaChange($this->createMock(IOutput::class), static fn () => null, []);

		self::assertSame(true, $savedBools['collect_metadata']);
		self::assertSame(false, $savedBools['identification_documents']);
		self::assertContains([Application::APP_ID, 'collect_metadata'], $deleted);
		self::assertContains([Application::APP_ID, 'identification_documents'], $deleted);
	}

	public function testMigratesLegacySignatureFlowKeyToSystemPolicyKey(): void {
		$this->appConfig
			->method('getValueString')
			->willReturnCallback(static function (string $app, string $key, string $default): string {
				if ($app !== Application::APP_ID) {
					return $default;
				}
				$map = [
					'add_footer' => '',
					'write_qrcode_on_footer' => '',
					'validation_site' => '',
					'footer_template_is_default' => '',
					'collect_metadata' => '',
					'identification_documents' => '',
					'docmdp_level' => '',
					'groups_request_sign' => '',
					'policy.signature_flow.system' => '',
					'signature_flow' => '2',
					SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY_TEMPLATE_FONT_SIZE => '',
					SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY_SIGNATURE_WIDTH => '',
					SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY_SIGNATURE_HEIGHT => '',
					SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY_SIGNATURE_FONT_SIZE => '',
					SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY_RENDER_MODE => '',
					'identify_methods' => '',
				];
				return $map[$key] ?? $default;
			});

		$savedStrings = [];
		$this->appConfig
			->method('setValueString')
			->willReturnCallback(static function (string $app, string $key, string $value) use (&$savedStrings): bool {
				$savedStrings[$key] = $value;
				return true;
			});

		$deleted = [];
		$this->appConfig
			->method('deleteKey')
			->willReturnCallback(static function (string $app, string $key) use (&$deleted): void {
				$deleted[] = [$app, $key];
			});

		$migration = new Version18001Date20260320000000($this->appConfig);
		$migration->preSchemaChange($this->createMock(IOutput::class), static fn () => null, []);

		self::assertSame('ordered_numeric', $savedStrings['policy.signature_flow.system']);
		self::assertContains([Application::APP_ID, 'signature_flow'], $deleted);
	}

	public function testMigratesExpirationRulesFromStringToInt(): void {
		$this->appConfig
			->method('getValueString')
			->willReturnCallback(static function (string $app, string $key, string $default): string {
				if ($app !== Application::APP_ID) {
					return $default;
				}

				$map = [
					'add_footer' => '',
					'write_qrcode_on_footer' => '',
					'validation_site' => '',
					'footer_template_is_default' => '',
					'collect_metadata' => '',
					'identification_documents' => '',
					'docmdp_level' => '',
					'groups_request_sign' => '',
					'policy.signature_flow.system' => '',
					'signature_flow' => '',
					SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY_TEMPLATE_FONT_SIZE => '',
					SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY_SIGNATURE_WIDTH => '',
					SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY_SIGNATURE_HEIGHT => '',
					SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY_SIGNATURE_FONT_SIZE => '',
					SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY_RENDER_MODE => '',
					'identify_methods' => '',
					ExpirationRulesPolicy::KEY_MAXIMUM_VALIDITY => '600',
					ExpirationRulesPolicy::KEY_RENEWAL_INTERVAL => '300',
					ExpirationRulesPolicy::KEY_EXPIRY_IN_DAYS => '0',
				];

				return $map[$key] ?? $default;
			});

		$savedInts = [];
		$this->appConfig
			->method('setValueInt')
			->willReturnCallback(static function (string $app, string $key, int $value) use (&$savedInts): bool {
				$savedInts[$key] = $value;
				return true;
			});

		$deleted = [];
		$this->appConfig
			->method('deleteKey')
			->willReturnCallback(static function (string $app, string $key) use (&$deleted): void {
				$deleted[] = [$app, $key];
			});

		$migration = new Version18001Date20260320000000($this->appConfig);
		$migration->preSchemaChange($this->createMock(IOutput::class), static fn () => null, []);

		self::assertSame(600, $savedInts[ExpirationRulesPolicy::KEY_MAXIMUM_VALIDITY]);
		self::assertSame(300, $savedInts[ExpirationRulesPolicy::KEY_RENEWAL_INTERVAL]);
		self::assertSame(ExpirationRulesPolicy::DEFAULT_EXPIRY_IN_DAYS, $savedInts[ExpirationRulesPolicy::KEY_EXPIRY_IN_DAYS]);
		self::assertContains([Application::APP_ID, ExpirationRulesPolicy::KEY_MAXIMUM_VALIDITY], $deleted);
		self::assertContains([Application::APP_ID, ExpirationRulesPolicy::KEY_RENEWAL_INTERVAL], $deleted);
		self::assertContains([Application::APP_ID, ExpirationRulesPolicy::KEY_EXPIRY_IN_DAYS], $deleted);
	}

	public function testMigratesIdentifyMethodsStringToCanonicalArrayPayload(): void {
		$this->appConfig
			->method('getValueString')
			->willReturnCallback(static function (string $app, string $key, string $default): string {
				if ($app !== Application::APP_ID) {
					return $default;
				}

				$map = [
					'add_footer' => '',
					'write_qrcode_on_footer' => '',
					'validation_site' => '',
					'footer_template_is_default' => '',
					'collect_metadata' => '',
					'identification_documents' => '',
					'docmdp_level' => '',
					'groups_request_sign' => '',
					'policy.signature_flow.system' => '',
					'signature_flow' => '',
					SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY_TEMPLATE_FONT_SIZE => '',
					SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY_SIGNATURE_WIDTH => '',
					SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY_SIGNATURE_HEIGHT => '',
					SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY_SIGNATURE_FONT_SIZE => '',
					SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY_RENDER_MODE => '',
					'identify_methods' => '[{"name":"email","enabled":true,"mandatory":true,"signatureMethods":["emailToken"],"signatureMethodEnabled":["emailToken"]}]',
				];

				return $map[$key] ?? $default;
			});

		$deleted = [];
		$this->appConfig
			->method('deleteKey')
			->willReturnCallback(static function (string $app, string $key) use (&$deleted): void {
				$deleted[] = [$app, $key];
			});

		$savedArrays = [];
		$this->appConfig
			->method('setValueArray')
			->willReturnCallback(static function (string $app, string $key, array $value) use (&$savedArrays): bool {
				$savedArrays[$key] = $value;
				return true;
			});

		$migration = new Version18001Date20260320000000($this->appConfig);
		$migration->preSchemaChange($this->createMock(IOutput::class), static fn () => null, []);

		self::assertSame([
			[
				'name' => 'email',
				'enabled' => true,
				'signatureMethods' => [
					'emailToken' => ['enabled' => false],
				],
				'requirement' => 'required',
				'mandatory' => true,
				'signatureMethodEnabled' => 'emailToken',
			],
		], $savedArrays['identify_methods']);
		self::assertContains([Application::APP_ID, 'identify_methods'], $deleted);
	}

	public function testSkipsIdentifyMethodsMigrationWhenPayloadIsInvalidJson(): void {
		$this->appConfig
			->method('getValueString')
			->willReturnCallback(static function (string $app, string $key, string $default): string {
				if ($app !== Application::APP_ID) {
					return $default;
				}

				$map = [
					'add_footer' => '',
					'write_qrcode_on_footer' => '',
					'validation_site' => '',
					'footer_template_is_default' => '',
					'collect_metadata' => '',
					'identification_documents' => '',
					'docmdp_level' => '',
					'groups_request_sign' => '',
					'policy.signature_flow.system' => '',
					'signature_flow' => '',
					SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY_TEMPLATE_FONT_SIZE => '',
					SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY_SIGNATURE_WIDTH => '',
					SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY_SIGNATURE_HEIGHT => '',
					SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY_SIGNATURE_FONT_SIZE => '',
					SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY_RENDER_MODE => '',
					'identify_methods' => 'invalid-json',
				];

				return $map[$key] ?? $default;
			});

		$deleted = [];
		$this->appConfig
			->method('deleteKey')
			->willReturnCallback(static function (string $app, string $key) use (&$deleted): void {
				$deleted[] = [$app, $key];
			});

		$savedArrays = [];
		$this->appConfig
			->method('setValueArray')
			->willReturnCallback(static function (string $app, string $key, array $value) use (&$savedArrays): bool {
				$savedArrays[$key] = $value;
				return true;
			});

		$migration = new Version18001Date20260320000000($this->appConfig);
		$migration->preSchemaChange($this->createMock(IOutput::class), static fn () => null, []);

		self::assertArrayNotHasKey('identify_methods', $savedArrays);
		self::assertNotContains([Application::APP_ID, 'identify_methods'], $deleted);
	}
}
