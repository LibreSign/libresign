<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Db\IdentifyMethod;
use OCA\Libresign\Service\IdentifyMethod\IdentifyService;
use OCA\Libresign\Service\IdentifyMethod\SignatureMethod\EmailToken;
use OCA\Libresign\Service\IdentifyMethod\SignatureMethod\TokenService;
use OCP\L10N\IFactory as IL10NFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

final class EmailTokenTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private IdentifyService&MockObject $identifyService;
	private TokenService&MockObject $tokenService;

	public function setUp(): void {
		$identifyService = $this->createMock(IdentifyService::class);
		$identifyService = $this->getMockBuilder(IdentifyService::class)
			->disableOriginalConstructor()
			->onlyMethods(['getL10n'])
			->getMock();
		$identifyService->method('getL10n')->willReturn(
			\OCP\Server::get(IL10NFactory::class)->get(\OCA\Libresign\AppInfo\Application::APP_ID)
		);
		$this->identifyService = $identifyService;
		$this->tokenService = $this->createMock(TokenService::class);
	}

	private function getClass(): EmailToken {
		return new EmailToken(
			$this->identifyService,
			$this->tokenService,
		);
	}

	#[DataProvider('providerVaidateEmail')]
	public function testVaidateEmail(string $email, string $blurred, string $hash): void {
		$instance = $this->getClass();
		$identifyMethod = new IdentifyMethod();
		$entity['identifierKey'] = 'email';
		$entity['identifierValue'] = $email;
		$identifyMethod = $identifyMethod->fromParams($entity);
		$instance->setEntity($identifyMethod);
		$actual = $instance->toArray();

		$this->assertArrayHasKey('blurredEmail', $actual);
		$this->assertIsString($actual['blurredEmail']);
		$this->assertEquals($blurred, $actual['blurredEmail']);

		$this->assertArrayHasKey('hashOfEmail', $actual);
		$this->assertIsString($actual['hashOfEmail']);
		$this->assertEquals($hash, $actual['hashOfEmail']);
	}

	public static function providerVaidateEmail(): array {
		return [
			['valid@domain.coop', 'val***@***.coop', md5('valid@domain.coop')],
			['valiD@Domain.coop', 'val***@***.coop', md5('valid@domain.coop')],
			['VALID@DOMAIN.COOP', 'val***@***.coop', md5('valid@domain.coop')],
		];
	}

	#[DataProvider('providerToArrayWithValidData')]
	public function testToArrayWithValidData(array $entity, ?string $codeSentByUser, array $expected): void {
		$instance = $this->getClass();
		$identifyMethod = new IdentifyMethod();
		$entity['identifierKey'] = 'email';
		$entity['identifierValue'] = 'valid@domain.coop';
		$identifyMethod = $identifyMethod->fromParams($entity);
		$instance->setEntity($identifyMethod);
		$instance->setCodeSentByUser($codeSentByUser);

		$actual = $instance->toArray();

		$this->assertArrayHasKey('hashOfEmail', $actual);
		$this->assertEquals(md5(strtolower('valid@domain.coop')), $actual['hashOfEmail']);
		unset($actual['hashOfEmail']);

		$this->assertArrayHasKey('blurredEmail', $actual);
		$this->assertEquals('val***@***.coop', $actual['blurredEmail']);
		unset($actual['blurredEmail']);

		$this->assertArrayHasKey('label', $actual);
		$this->assertEquals('Email token', $actual['label']);
		unset($actual['label']);

		$this->assertArrayHasKey('identifyMethod', $actual);
		$this->assertEquals('email', $actual['identifyMethod']);
		unset($actual['identifyMethod']);

		$this->assertEquals($expected, $actual);
	}

	public static function providerToArrayWithValidData(): array {
		return [
			'case_01' => [['code' => null,     'identifiedAtDate' => null],         '',    ['needCode' => true,  'hasConfirmCode' => false]],
			'case_02' => [['code' => null,     'identifiedAtDate' => null],         '0',   ['needCode' => true,  'hasConfirmCode' => false]],
			'case_03' => [['code' => null,     'identifiedAtDate' => null],         'abc', ['needCode' => true,  'hasConfirmCode' => false]],
			'case_04' => [['code' => null,     'identifiedAtDate' => ''],           '',    ['needCode' => true,  'hasConfirmCode' => false]],
			'case_05' => [['code' => null,     'identifiedAtDate' => ''],           '0',   ['needCode' => true,  'hasConfirmCode' => false]],
			'case_06' => [['code' => null,     'identifiedAtDate' => ''],           'abc', ['needCode' => true,  'hasConfirmCode' => false]],
			'case_07' => [['code' => null,     'identifiedAtDate' => '0'],          '',    ['needCode' => true,  'hasConfirmCode' => false]],
			'case_08' => [['code' => null,     'identifiedAtDate' => '0'],          '0',   ['needCode' => true,  'hasConfirmCode' => false]],
			'case_09' => [['code' => null,     'identifiedAtDate' => '0'],          'abc', ['needCode' => true,  'hasConfirmCode' => false]],
			'case_10' => [['code' => null,     'identifiedAtDate' => '2025-08-11'], '',    ['needCode' => true,  'hasConfirmCode' => false]],
			'case_11' => [['code' => null,     'identifiedAtDate' => '2025-08-11'], '0',   ['needCode' => true,  'hasConfirmCode' => false]],
			'case_12' => [['code' => null,     'identifiedAtDate' => '2025-08-11'], 'abc', ['needCode' => true,  'hasConfirmCode' => false]],

			'case_13' => [['code' => '',       'identifiedAtDate' => null],         '',    ['needCode' => true,  'hasConfirmCode' => false]],
			'case_14' => [['code' => '',       'identifiedAtDate' => null],         '0',   ['needCode' => true,  'hasConfirmCode' => false]],
			'case_15' => [['code' => '',       'identifiedAtDate' => null],         'abc', ['needCode' => true,  'hasConfirmCode' => false]],
			'case_16' => [['code' => '',       'identifiedAtDate' => ''],           '',    ['needCode' => true,  'hasConfirmCode' => false]],
			'case_17' => [['code' => '',       'identifiedAtDate' => ''],           '0',   ['needCode' => true,  'hasConfirmCode' => false]],
			'case_18' => [['code' => '',       'identifiedAtDate' => ''],           'abc', ['needCode' => true,  'hasConfirmCode' => false]],
			'case_19' => [['code' => '',       'identifiedAtDate' => '0'],          '',    ['needCode' => true,  'hasConfirmCode' => false]],
			'case_20' => [['code' => '',       'identifiedAtDate' => '0'],          '0',   ['needCode' => true,  'hasConfirmCode' => false]],
			'case_21' => [['code' => '',       'identifiedAtDate' => '0'],          'abc', ['needCode' => true,  'hasConfirmCode' => false]],
			'case_22' => [['code' => '',       'identifiedAtDate' => '2025-08-11'], '',    ['needCode' => true,  'hasConfirmCode' => false]],
			'case_23' => [['code' => '',       'identifiedAtDate' => '2025-08-11'], '0',   ['needCode' => true,  'hasConfirmCode' => false]],
			'case_24' => [['code' => '',       'identifiedAtDate' => '2025-08-11'], 'abc', ['needCode' => true,  'hasConfirmCode' => false]],

			'case_25' => [['code' => '0',      'identifiedAtDate' => null],         '',    ['needCode' => true,  'hasConfirmCode' => false]],
			'case_26' => [['code' => '0',      'identifiedAtDate' => null],         '0',   ['needCode' => true,  'hasConfirmCode' => false]],
			'case_27' => [['code' => '0',      'identifiedAtDate' => null],         'abc', ['needCode' => true,  'hasConfirmCode' => false]],
			'case_28' => [['code' => '0',      'identifiedAtDate' => ''],           '',    ['needCode' => true,  'hasConfirmCode' => false]],
			'case_29' => [['code' => '0',      'identifiedAtDate' => ''],           '0',   ['needCode' => true,  'hasConfirmCode' => false]],
			'case_30' => [['code' => '0',      'identifiedAtDate' => ''],           'abc', ['needCode' => true,  'hasConfirmCode' => false]],
			'case_31' => [['code' => '0',      'identifiedAtDate' => '0'],          '',    ['needCode' => true,  'hasConfirmCode' => false]],
			'case_32' => [['code' => '0',      'identifiedAtDate' => '0'],          '0',   ['needCode' => true,  'hasConfirmCode' => false]],
			'case_33' => [['code' => '0',      'identifiedAtDate' => '0'],          'abc', ['needCode' => true,  'hasConfirmCode' => false]],
			'case_34' => [['code' => '0',      'identifiedAtDate' => '2025-08-11'], '',    ['needCode' => true,  'hasConfirmCode' => false]],
			'case_35' => [['code' => '0',      'identifiedAtDate' => '2025-08-11'], '0',   ['needCode' => true,  'hasConfirmCode' => false]],
			'case_36' => [['code' => '0',      'identifiedAtDate' => '2025-08-11'], 'abc', ['needCode' => true,  'hasConfirmCode' => false]],

			'case_37' => [['code' => '123456', 'identifiedAtDate' => null],         '',    ['needCode' => true,  'hasConfirmCode' => true]],
			'case_38' => [['code' => '123456', 'identifiedAtDate' => null],         '0',   ['needCode' => true,  'hasConfirmCode' => true]],
			'case_39' => [['code' => '123456', 'identifiedAtDate' => null],         'abc', ['needCode' => true,  'hasConfirmCode' => true]],
			'case_40' => [['code' => '123456', 'identifiedAtDate' => ''],           '',    ['needCode' => true,  'hasConfirmCode' => true]],
			'case_41' => [['code' => '123456', 'identifiedAtDate' => ''],           '0',   ['needCode' => true,  'hasConfirmCode' => true]],
			'case_42' => [['code' => '123456', 'identifiedAtDate' => ''],           'abc', ['needCode' => true,  'hasConfirmCode' => true]],
			'case_43' => [['code' => '123456', 'identifiedAtDate' => '0'],          '',    ['needCode' => true,  'hasConfirmCode' => true]],
			'case_44' => [['code' => '123456', 'identifiedAtDate' => '0'],          '0',   ['needCode' => true,  'hasConfirmCode' => true]],
			'case_45' => [['code' => '123456', 'identifiedAtDate' => '0'],          'abc', ['needCode' => true,  'hasConfirmCode' => true]],
			'case_46' => [['code' => '123456', 'identifiedAtDate' => '2025-08-11'], '',    ['needCode' => true,  'hasConfirmCode' => true]],
			'case_47' => [['code' => '123456', 'identifiedAtDate' => '2025-08-11'], '0',   ['needCode' => true,  'hasConfirmCode' => true]],
			'case_48' => [['code' => '123456', 'identifiedAtDate' => '2025-08-11'], 'abc', ['needCode' => false, 'hasConfirmCode' => true]],
		];
	}
}
