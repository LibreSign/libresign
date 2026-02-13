<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Db\IdentifyMethod;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Service\IdentifyMethod\Account;
use OCA\Libresign\Service\IdentifyMethod\Email;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCA\Libresign\Service\IdentifyMethod\Signal;
use OCA\Libresign\Service\IdentifyMethod\Sms;
use OCA\Libresign\Service\IdentifyMethod\Telegram;
use OCA\Libresign\Service\IdentifyMethod\Whatsapp;
use OCA\Libresign\Service\IdentifyMethod\Xmpp;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\SubjectAlternativeNameService;
use OCP\IL10N;
use OCP\IUserManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @internal
 */
final class IdentifyMethodServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private IdentifyMethodService $service;
	private IdentifyMethodMapper&MockObject $identifyMethodMapper;
	private IL10N&MockObject $l10n;
	private IUserManager&MockObject $userManager;
	private Account&MockObject $account;
	private Email&MockObject $email;
	private Signal&MockObject $signal;
	private Sms&MockObject $sms;
	private Telegram&MockObject $telegram;
	private Whatsapp&MockObject $whatsapp;
	private Xmpp&MockObject $xmpp;
	private SubjectAlternativeNameService&MockObject $subjectAlternativeNameService;

	public function setUp(): void {
		parent::setUp();
		$this->identifyMethodMapper = $this->createMock(IdentifyMethodMapper::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')->willReturnArgument(0);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->account = $this->createMock(Account::class);
		$this->email = $this->createMock(Email::class);
		$this->signal = $this->createMock(Signal::class);
		$this->sms = $this->createMock(Sms::class);
		$this->telegram = $this->createMock(Telegram::class);
		$this->whatsapp = $this->createMock(Whatsapp::class);
		$this->xmpp = $this->createMock(Xmpp::class);
		$this->subjectAlternativeNameService = $this->createMock(SubjectAlternativeNameService::class);

		$this->service = new IdentifyMethodService(
			$this->identifyMethodMapper,
			$this->l10n,
			$this->userManager,
			$this->account,
			$this->email,
			$this->signal,
			$this->sms,
			$this->telegram,
			$this->whatsapp,
			$this->xmpp,
			$this->subjectAlternativeNameService,
		);
	}

	#[DataProvider('providerFindMethodsInMatrix')]
	public function testFindMethodsInMatrix(
		?string $expectedIdentifiedKey,
		?string $expectedFirstKey,
		array $methodsData,
	): void {
		$matrix = $this->buildMatrix($methodsData);
		[$identifiedMethod, $firstMethod] = self::invokePrivate($this->service, 'findMethodsInMatrix', [$matrix]);

		if ($expectedIdentifiedKey === null) {
			$this->assertNull($identifiedMethod);
		} else {
			$this->assertNotNull($identifiedMethod);
			$this->assertEquals($expectedIdentifiedKey, $identifiedMethod->getEntity()->getIdentifierKey());
		}

		if ($expectedFirstKey === null) {
			$this->assertNull($firstMethod);
		} else {
			$this->assertNotNull($firstMethod);
			$this->assertEquals($expectedFirstKey, $firstMethod->getEntity()->getIdentifierKey());
		}
	}

	public static function providerFindMethodsInMatrix(): array {
		return [
			'No methods' => [
				null, null,
				[],
			],
			'Single unidentified method' => [
				null, 'email',
				[
					['key' => 'email', 'value' => 'user@example.com', 'identified' => false],
				],
			],
			'Single identified method' => [
				'email', 'email',
				[
					['key' => 'email', 'value' => 'user@example.com', 'identified' => true],
				],
			],
			'First unidentified, second identified' => [
				'account', 'email',
				[
					['key' => 'email', 'value' => 'user@example.com', 'identified' => false],
					['key' => 'account', 'value' => 'john', 'identified' => true],
				],
			],
			'Multiple identified returns first identified' => [
				'email', 'email',
				[
					['key' => 'email', 'value' => 'user@example.com', 'identified' => true],
					['key' => 'account', 'value' => 'john', 'identified' => true],
				],
			],
		];
	}

	#[DataProvider('providerFindIdentifiedMethod')]
	public function testFindIdentifiedMethod(?string $expectedKey, array $methodsData): void {
		$matrix = $this->buildMatrix($methodsData);
		[$result, $_] = self::invokePrivate($this->service, 'findMethodsInMatrix', [$matrix]);

		if ($expectedKey === null) {
			$this->assertNull($result);
		} else {
			$this->assertNotNull($result);
			$this->assertEquals($expectedKey, $result->getEntity()->getIdentifierKey());
		}
	}

	public static function providerFindIdentifiedMethod(): array {
		return [
			'No identified method' => [
				null,
				[
					['key' => 'email', 'value' => 'user@example.com', 'identified' => false],
					['key' => 'account', 'value' => 'john', 'identified' => false],
				],
			],
			'First method identified' => [
				'email',
				[
					['key' => 'email', 'value' => 'user@example.com', 'identified' => true],
					['key' => 'account', 'value' => 'john', 'identified' => false],
				],
			],
			'Second method identified' => [
				'account',
				[
					['key' => 'email', 'value' => 'user@example.com', 'identified' => false],
					['key' => 'account', 'value' => 'john', 'identified' => true],
				],
			],
			'Multiple identified returns first' => [
				'email',
				[
					['key' => 'email', 'value' => 'user@example.com', 'identified' => true],
					['key' => 'account', 'value' => 'john', 'identified' => true],
				],
			],
			'Empty matrix' => [
				null,
				[],
			],
		];
	}

	#[DataProvider('providerGetFirstAvailableMethod')]
	public function testGetFirstAvailableMethod(?string $expectedKey, array $methodsData): void {
		$matrix = $this->buildMatrix($methodsData);
		[$_, $result] = self::invokePrivate($this->service, 'findMethodsInMatrix', [$matrix]);

		if ($expectedKey === null) {
			$this->assertNull($result);
		} else {
			$this->assertNotNull($result);
			$this->assertEquals($expectedKey, $result->getEntity()->getIdentifierKey());
		}
	}

	public static function providerGetFirstAvailableMethod(): array {
		return [
			'Single method' => [
				'email',
				[
					['key' => 'email', 'value' => 'user@example.com', 'identified' => false],
				],
			],
			'Multiple methods returns first' => [
				'email',
				[
					['key' => 'email', 'value' => 'user@example.com', 'identified' => false],
					['key' => 'account', 'value' => 'john', 'identified' => false],
				],
			],
			'Empty matrix' => [
				null,
				[],
			],
		];
	}

	public function testGetIdentifiedMethodWithIdentifiedMethod(): void {
		$entity1 = $this->createIdentifyMethodEntity(1, 'email', 'user@example.com', '2024-01-14 10:00:00');
		$identifyMethod1 = $this->createMock(IIdentifyMethod::class);
		$identifyMethod1->method('getEntity')->willReturn($entity1);

		$entity2 = $this->createIdentifyMethodEntity(2, 'account', 'john', null);
		$identifyMethod2 = $this->createMock(IIdentifyMethod::class);
		$identifyMethod2->method('getEntity')->willReturn($entity2);

		$this->identifyMethodMapper
			->method('getIdentifyMethodsFromSignRequestId')
			->willReturn([$entity1, $entity2]);

		// Mock the internal call to prevent real object instantiation
		$service = $this->getMockBuilder(IdentifyMethodService::class)
			->setConstructorArgs([
				$this->identifyMethodMapper,
				$this->l10n,
				$this->userManager,
				$this->account,
				$this->email,
				$this->signal,
				$this->sms,
				$this->telegram,
				$this->whatsapp,
				$this->xmpp,
				$this->subjectAlternativeNameService,
			])
			->onlyMethods(['getIdentifyMethodsFromSignRequestId'])
			->getMock();

		$matrix = [
			'email' => [$identifyMethod1],
			'account' => [$identifyMethod2],
		];

		$service->method('getIdentifyMethodsFromSignRequestId')
			->willReturn($matrix);

		$result = self::invokePrivate($service, 'getIdentifiedMethod', [1]);

		$this->assertNotNull($result);
		$this->assertEquals('email', $result->getEntity()->getIdentifierKey());
	}

	#[DataProvider('providerIdentifyMethodsSettings')]
	public function testGetIdentifyMethodsSettings(
		array $settingsData,
		bool $isTwofactorGatewayEnabled,
		?array $expectedSettings,
	): void {
		$methodName = $settingsData['name'];

		$this->assertObjectHasProperty($methodName, $this);
		$mock = $this->{$methodName};
		$mock->method('isTwofactorGatewayEnabled')->willReturn($isTwofactorGatewayEnabled);
		if ($isTwofactorGatewayEnabled) {
			$mock->method('getSettings')->willReturn($settingsData);
		}

		$result = $this->service->getIdentifyMethodsSettings();
		$byName = array_column($result, null, 'name');

		if ($expectedSettings === null) {
			$this->assertArrayNotHasKey($methodName, $byName);
		} else {
			$this->assertArrayHasKey($methodName, $byName);
			$this->assertEquals($expectedSettings, $byName[$methodName]);
		}
	}

	public static function providerIdentifyMethodsSettings(): array {
		$whatsappSettingsData = [
			'name' => 'whatsapp',
			'friendly_name' => 'WhatsApp',
			'enabled' => true,
			'mandatory' => true,
			'signatureMethods' => [
				'clickToSign' => ['name' => 'clickToSign', 'enabled' => false],
				'whatsappToken' => ['name' => 'whatsappToken', 'enabled' => true],
			],
			'test_url' => '/settings/user/security',
			'signatureMethodEnabled' => 'whatsappToken',
		];

		$smsSettingsData = [
			'name' => 'sms',
			'friendly_name' => 'SMS',
			'enabled' => true,
			'mandatory' => true,
			'signatureMethods' => [
				'clickToSign' => ['name' => 'clickToSign', 'enabled' => false],
				'smsToken' => ['name' => 'smsToken', 'enabled' => true],
			],
			'test_url' => '/settings/user/security',
			'signatureMethodEnabled' => 'smsToken',
		];

		return [
			'whatsapp twofactor enabled' => [
				$whatsappSettingsData,
				true,
				$whatsappSettingsData,
			],
			'whatsapp twofactor disabled' => [
				$whatsappSettingsData,
				false,
				null,
			],
			'sms twofactor enabled' => [
				$smsSettingsData,
				true,
				$smsSettingsData,
			],
			'sms twofactor disabled' => [
				$smsSettingsData,
				false,
				null,
			],
		];
	}

	private function buildMatrix(array $methodsData): array {
		$matrix = [];

		foreach ($methodsData as $data) {
			$entity = $this->createIdentifyMethodEntity(
				count($matrix) + 1,
				$data['key'],
				$data['value'],
				$data['identified'] ? '2024-01-14 10:00:00' : null,
			);

			$identifyMethod = $this->createMock(IIdentifyMethod::class);
			$identifyMethod->method('getEntity')->willReturn($entity);

			$matrix[$data['key']][] = $identifyMethod;
		}

		return $matrix;
	}

	private function createIdentifyMethodEntity(
		int $id,
		string $key,
		string $value,
		?string $identifiedAt,
	): IdentifyMethod {
		$entity = new IdentifyMethod();
		$entity->setId($id);
		$entity->setIdentifierKey($key);
		$entity->setIdentifierValue($value);
		if ($identifiedAt) {
			$entity->setIdentifiedAtDate($identifiedAt);
		}

		return $entity;
	}
}
