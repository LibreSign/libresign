<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\IdentifyMethod\SignatureMethod;

use OCA\Libresign\Db\IdentifyMethod;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\IdentifyMethod\IdentifyService;
use OCA\Libresign\Service\IdentifyMethod\SignatureMethod\ISignatureMethod;
use OCA\Libresign\Service\IdentifyMethod\SignatureMethod\SignalToken;
use OCA\Libresign\Service\IdentifyMethod\SignatureMethod\SmsToken;
use OCA\Libresign\Service\IdentifyMethod\SignatureMethod\TelegramToken;
use OCA\Libresign\Service\IdentifyMethod\SignatureMethod\TokenService;
use OCA\Libresign\Service\IdentifyMethod\SignatureMethod\WhatsappToken;
use OCA\Libresign\Service\IdentifyMethod\SignatureMethod\XmppToken;
use OCP\L10N\IFactory as IL10NFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class TokenClassesTest extends TestCase {
	private IdentifyService&MockObject $identifyService;
	private TokenService&MockObject $tokenService;

	public function setUp(): void {
		$identifyService = $this->getMockBuilder(IdentifyService::class)
			->disableOriginalConstructor()
			->onlyMethods(['getL10n', 'getSignRequestMapper', 'save'])
			->getMock();
		$identifyService->method('getL10n')->willReturn(
			\OCP\Server::get(IL10NFactory::class)->get(\OCA\Libresign\AppInfo\Application::APP_ID)
		);
		$this->identifyService = $identifyService;
		$this->tokenService = $this->createMock(TokenService::class);
	}

	public function testSmsTokenGetFriendlyName(): void {
		$smsToken = new SmsToken(
			$this->identifyService,
			$this->tokenService,
		);

		$this->assertEquals('SMS token', $smsToken->getFriendlyName());
	}

	public function testSmsTokenConstant(): void {
		$this->assertEquals('smsToken', ISignatureMethod::SIGNATURE_METHOD_SMS);
	}

	public function testSignalTokenGetFriendlyName(): void {
		$signalToken = new SignalToken(
			$this->identifyService,
			$this->tokenService,
		);

		$this->assertEquals('Signal token', $signalToken->getFriendlyName());
	}

	public function testSignalTokenConstant(): void {
		$this->assertEquals('signalToken', ISignatureMethod::SIGNATURE_METHOD_SIGNAL);
	}

	public function testTelegramTokenGetFriendlyName(): void {
		$telegramToken = new TelegramToken(
			$this->identifyService,
			$this->tokenService,
		);

		$this->assertEquals('Telegram token', $telegramToken->getFriendlyName());
	}

	public function testTelegramTokenConstant(): void {
		$this->assertEquals('telegramToken', ISignatureMethod::SIGNATURE_METHOD_TELEGRAM);
	}

	public function testWhatsappTokenGetFriendlyName(): void {
		$whatsappToken = new WhatsappToken(
			$this->identifyService,
			$this->tokenService,
		);

		$this->assertEquals('WhatsApp token', $whatsappToken->getFriendlyName());
	}

	public function testWhatsappTokenConstant(): void {
		$this->assertEquals('whatsappToken', ISignatureMethod::SIGNATURE_METHOD_WHATSAPP);
	}

	public function testXmppTokenGetFriendlyName(): void {
		$xmppToken = new XmppToken(
			$this->identifyService,
			$this->tokenService,
		);

		$this->assertEquals('XMPP token', $xmppToken->getFriendlyName());
	}

	public function testXmppTokenConstant(): void {
		$this->assertEquals('xmppToken', ISignatureMethod::SIGNATURE_METHOD_XMPP);
	}

	#[DataProvider('providerGatewayRequestCode')]
	public function testRequestCodeUsesPersistedGatewayDestination(
		string $className,
		string $identifyMethodName,
		string $gatewayName,
	): void {
		$signRequest = new SignRequest();
		$signRequest->setDisplayName('Signer');

		$signRequestMapper = $this->createMock(SignRequestMapper::class);
		$signRequestMapper->method('getById')
			->with(171)
			->willReturn($signRequest);

		$this->identifyService
			->method('getSignRequestMapper')
			->willReturn($signRequestMapper);

		$this->tokenService->expects($this->once())
			->method('sendCodeByGateway')
			->with('+5511999999999', $gatewayName)
			->willReturn('hashed-code');

		$token = new $className(
			$this->identifyService,
			$this->tokenService,
		);

		$identifyMethod = (new IdentifyMethod())->fromParams([
			'identifierKey' => $identifyMethodName,
			'identifierValue' => '+5511999999999',
			'signRequestId' => 171,
		]);

		$token->setEntity($identifyMethod);

		$this->identifyService->expects($this->once())
			->method('save')
			->with($identifyMethod);

		$token->requestCode();

		$this->assertSame('hashed-code', $identifyMethod->getCode());
	}

	public static function providerGatewayRequestCode(): array {
		return [
			'sms' => [SmsToken::class, 'sms', 'sms'],
			'signal' => [SignalToken::class, 'signal', 'signal'],
			'telegram' => [TelegramToken::class, 'telegram', 'telegram'],
			'whatsapp' => [WhatsappToken::class, 'whatsapp', 'gowhatsapp'],
			'whatsapp business' => [WhatsappToken::class, 'whatsappbusiness', 'whatsappbusiness'],
			'xmpp' => [XmppToken::class, 'xmpp', 'xmpp'],
		];
	}

	public function testRequestCodeFailsClosedWithEmptyGatewayDestination(): void {
		$this->tokenService->expects($this->never())
			->method('sendCodeByGateway');

		$token = new SmsToken(
			$this->identifyService,
			$this->tokenService,
		);

		$token->setEntity(
			(new IdentifyMethod())->fromParams([
				'identifierKey' => 'sms',
				'identifierValue' => '',
				'signRequestId' => 171,
			])
		);

		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('Unable to send verification code.');

		$token->requestCode();
	}

	public function testRequestCodeFailsClosedWithWhitespaceOnlyGatewayDestination(): void {
		$this->tokenService->expects($this->never())
			->method('sendCodeByGateway');

		$token = new SmsToken(
			$this->identifyService,
			$this->tokenService,
		);

		$token->setEntity(
			(new IdentifyMethod())->fromParams([
				'identifierKey' => 'sms',
				'identifierValue' => " \t ",
				'signRequestId' => 171,
			])
		);

		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('Unable to send verification code.');

		$token->requestCode();
	}

	#[DataProvider('providerTokenClasses')]
	public function testAllTokenClassesExtendTwofactorGatewayToken(string $className): void {
		$reflection = new \ReflectionClass($className);
		$parent = $reflection->getParentClass();

		$this->assertNotFalse($parent);
		$this->assertEquals('OCA\Libresign\Service\IdentifyMethod\SignatureMethod\TwofactorGatewayToken', $parent->getName());
	}

	public static function providerTokenClasses(): array {
		return [
			[SignalToken::class],
			[SmsToken::class],
			[TelegramToken::class],
			[WhatsappToken::class],
			[XmppToken::class],
		];
	}

	#[DataProvider('providerFriendlyNames')]
	public function testFriendlyNamesIncludeTokenSuffix(string $className, string $expectedName): void {
		$instance = new $className(
			$this->identifyService,
			$this->tokenService,
		);

		$friendlyName = $instance->getFriendlyName();

		$this->assertStringContainsString('token', strtolower($friendlyName));
		$this->assertEquals($expectedName, $friendlyName);
	}

	public static function providerFriendlyNames(): array {
		return [
			[SignalToken::class, 'Signal token'],
			[SmsToken::class, 'SMS token'],
			[TelegramToken::class, 'Telegram token'],
			[WhatsappToken::class, 'WhatsApp token'],
			[XmppToken::class, 'XMPP token'],
		];
	}

	#[DataProvider('providerConstantValues')]
	public function testConstantValuesMatchClassName(string $constant, string $expectedValue): void {
		$this->assertEquals($expectedValue, $constant);
		$this->assertStringEndsWith('Token', $expectedValue);
	}

	public static function providerConstantValues(): array {
		return [
			[ISignatureMethod::SIGNATURE_METHOD_SIGNAL, 'signalToken'],
			[ISignatureMethod::SIGNATURE_METHOD_SMS, 'smsToken'],
			[ISignatureMethod::SIGNATURE_METHOD_TELEGRAM, 'telegramToken'],
			[ISignatureMethod::SIGNATURE_METHOD_WHATSAPP, 'whatsappToken'],
			[ISignatureMethod::SIGNATURE_METHOD_XMPP, 'xmppToken'],
		];
	}
}
