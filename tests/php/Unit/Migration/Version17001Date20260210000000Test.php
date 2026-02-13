<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Migration;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Migration\Version17001Date20260210000000;
use OCP\IAppConfig;
use OCP\Migration\IOutput;
use PHPUnit\Framework\MockObject\MockObject;

final class Version17001Date20260210000000Test extends \OCA\Libresign\Tests\Unit\TestCase {
	private IAppConfig $appConfig;
	private IOutput&MockObject $output;
	private Version17001Date20260210000000 $migration;

	public function setUp(): void {
		parent::setUp();
		$this->appConfig = self::getMockAppConfigWithReset();
		$this->output = $this->createMock(IOutput::class);
		$this->migration = new Version17001Date20260210000000($this->appConfig);
	}

	public function testConvertsLegacySignatureMethodNames(): void {
		$legacyData = [
			[
				'name' => 'sms',
				'signatureMethods' => [
					'sms' => ['enabled' => true],
					'signal' => ['enabled' => false],
				],
				'signatureMethodEnabled' => ['sms', 'signal'],
				'availableSignatureMethods' => ['sms', 'signal', 'telegram'],
			],
		];

		$this->appConfig->setValueArray(Application::APP_ID, 'identify_methods', $legacyData);

		$this->output
			->expects($this->once())
			->method('info')
			->with('Updated signature method names to new format with Token suffix');

		$this->migration->postSchemaChange($this->output, fn () => null, []);

		$result = $this->appConfig->getValueArray(Application::APP_ID, 'identify_methods', []);

		$this->assertArrayHasKey(0, $result);
		$this->assertArrayHasKey('signatureMethods', $result[0]);

		$methods = $result[0]['signatureMethods'];
		$this->assertArrayHasKey('smsToken', $methods);
		$this->assertArrayHasKey('signalToken', $methods);
		$this->assertArrayNotHasKey('sms', $methods);
		$this->assertArrayNotHasKey('signal', $methods);
	}

	public function testConvertsAllLegacyMethods(): void {
		$legacyData = [
			[
				'name' => 'whatsapp',
				'signatureMethods' => [
					'signal' => ['enabled' => true],
					'sms' => ['enabled' => true],
					'telegram' => ['enabled' => true],
					'whatsapp' => ['enabled' => true],
					'xmpp' => ['enabled' => true],
				],
			],
		];

		$this->appConfig->setValueArray(Application::APP_ID, 'identify_methods', $legacyData);

		$this->migration->postSchemaChange($this->output, fn () => null, []);

		$result = $this->appConfig->getValueArray(Application::APP_ID, 'identify_methods', []);
		$methods = $result[0]['signatureMethods'];

		$this->assertArrayHasKey('signalToken', $methods);
		$this->assertArrayHasKey('smsToken', $methods);
		$this->assertArrayHasKey('telegramToken', $methods);
		$this->assertArrayHasKey('whatsappToken', $methods);
		$this->assertArrayHasKey('xmppToken', $methods);

		$this->assertArrayNotHasKey('signal', $methods);
		$this->assertArrayNotHasKey('sms', $methods);
		$this->assertArrayNotHasKey('telegram', $methods);
		$this->assertArrayNotHasKey('whatsapp', $methods);
		$this->assertArrayNotHasKey('xmpp', $methods);
	}

	public function testPreservesNonLegacyMethods(): void {
		$data = [
			[
				'name' => 'account',
				'signatureMethods' => [
					'clickToSign' => ['enabled' => true],
					'emailToken' => ['enabled' => true],
					'password' => ['enabled' => false],
				],
				'signatureMethodEnabled' => ['clickToSign', 'emailToken'],
			],
		];

		$this->appConfig->setValueArray(Application::APP_ID, 'identify_methods', $data);
		$dataBefore = $this->appConfig->getValueArray(Application::APP_ID, 'identify_methods', []);

		$this->output
			->expects($this->never())
			->method('info');

		$this->migration->postSchemaChange($this->output, fn () => null, []);

		$dataAfter = $this->appConfig->getValueArray(Application::APP_ID, 'identify_methods', []);
		$this->assertEquals($dataBefore, $dataAfter);
	}

	public function testMixedLegacyAndNewMethods(): void {
		$mixedData = [
			[
				'name' => 'account',
				'signatureMethods' => [
					'clickToSign' => ['enabled' => true],
					'sms' => ['enabled' => true],  // Legacy
					'emailToken' => ['enabled' => true],
				],
			],
		];

		$this->appConfig->setValueArray(Application::APP_ID, 'identify_methods', $mixedData);

		$this->migration->postSchemaChange($this->output, fn () => null, []);

		$result = $this->appConfig->getValueArray(Application::APP_ID, 'identify_methods', []);
		$methods = $result[0]['signatureMethods'];

		$this->assertArrayHasKey('clickToSign', $methods);
		$this->assertArrayHasKey('smsToken', $methods);
		$this->assertArrayHasKey('emailToken', $methods);
		$this->assertArrayNotHasKey('sms', $methods);
	}

	public function testIdempotency(): void {
		$alreadyMigratedData = [
			[
				'name' => 'sms',
				'signatureMethods' => [
					'smsToken' => ['enabled' => true],
					'signalToken' => ['enabled' => false],
				],
			],
		];

		$this->appConfig->setValueArray(Application::APP_ID, 'identify_methods', $alreadyMigratedData);
		$dataBefore = $this->appConfig->getValueArray(Application::APP_ID, 'identify_methods', []);

		$this->output
			->expects($this->never())
			->method('info');

		$this->migration->postSchemaChange($this->output, fn () => null, []);

		$dataAfter = $this->appConfig->getValueArray(Application::APP_ID, 'identify_methods', []);
		$this->assertEquals($dataBefore, $dataAfter);
	}

	public function testHandlesEmptySignatureMethods(): void {
		$data = [
			[
				'name' => 'account',
				'signatureMethods' => [],
			],
		];

		$this->appConfig->setValueArray(Application::APP_ID, 'identify_methods', $data);

		$this->output
			->expects($this->never())
			->method('info');

		$this->migration->postSchemaChange($this->output, fn () => null, []);
	}

	public function testHandlesMissingArrays(): void {
		$data = [
			[
				'name' => 'account',
				// No signatureMethods key
			],
		];

		$this->appConfig->setValueArray(Application::APP_ID, 'identify_methods', $data);

		$this->output
			->expects($this->never())
			->method('info');

		$this->migration->postSchemaChange($this->output, fn () => null, []);
	}

	public function testConvertsSignatureMethodEnabledArray(): void {
		$legacyData = [
			[
				'name' => 'sms',
				'signatureMethodEnabled' => ['sms', 'whatsapp'],
			],
		];

		$this->appConfig->setValueArray(Application::APP_ID, 'identify_methods', $legacyData);

		$this->migration->postSchemaChange($this->output, fn () => null, []);

		$result = $this->appConfig->getValueArray(Application::APP_ID, 'identify_methods', []);
		$enabled = $result[0]['signatureMethodEnabled'];

		$this->assertContains('smsToken', $enabled);
		$this->assertContains('whatsappToken', $enabled);
		$this->assertNotContains('sms', $enabled);
		$this->assertNotContains('whatsapp', $enabled);
	}

	public function testConvertsAvailableSignatureMethodsArray(): void {
		$legacyData = [
			[
				'name' => 'telegram',
				'availableSignatureMethods' => ['telegram', 'xmpp', 'signal'],
			],
		];

		$this->appConfig->setValueArray(Application::APP_ID, 'identify_methods', $legacyData);

		$this->migration->postSchemaChange($this->output, fn () => null, []);

		$result = $this->appConfig->getValueArray(Application::APP_ID, 'identify_methods', []);
		$available = $result[0]['availableSignatureMethods'];

		$this->assertContains('telegramToken', $available);
		$this->assertContains('xmppToken', $available);
		$this->assertContains('signalToken', $available);
		$this->assertNotContains('telegram', $available);
		$this->assertNotContains('xmpp', $available);
		$this->assertNotContains('signal', $available);
	}
}
