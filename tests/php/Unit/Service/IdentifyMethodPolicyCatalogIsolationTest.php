<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Service\IdentifyMethod\Account;
use OCA\Libresign\Service\IdentifyMethod\Email;
use OCA\Libresign\Service\IdentifyMethod\Signal;
use OCA\Libresign\Service\IdentifyMethod\Sms;
use OCA\Libresign\Service\IdentifyMethod\Telegram;
use OCA\Libresign\Service\IdentifyMethod\Whatsapp;
use OCA\Libresign\Service\IdentifyMethod\Whatsappbusiness;
use OCA\Libresign\Service\IdentifyMethod\Xmpp;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\SubjectAlternativeNameService;
use OCP\IL10N;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;

class AccountWithMutatingDefaults extends Account {
	public bool $defaultSettingsWereApplied = false;

	#[\Override]
	public function getDefaultSettings(): array {
		$this->defaultSettingsWereApplied = true;
		return [
			'name' => 'account',
			'enabled' => true,
			'requirement' => 'required',
			'signatureMethods' => ['clickToSign' => ['enabled' => true, 'name' => 'clickToSign', 'label' => 'Click to sign']],
		];
	}
}

class EmailWithMutatingDefaults extends Email {
	public bool $defaultSettingsWereApplied = false;

	#[\Override]
	public function getDefaultSettings(): array {
		$this->defaultSettingsWereApplied = true;
		return [
			'name' => 'email',
			'enabled' => true,
			'requirement' => 'required',
			'signatureMethods' => ['emailToken' => ['enabled' => true, 'name' => 'emailToken', 'label' => 'Email token']],
		];
	}
}

final class IdentifyMethodPolicyCatalogIsolationTest extends TestCase {
	public function testResolvingPolicyDefaultsCannotMutateLiveIdentifyMethodInstances(): void {
		/** @var AccountWithMutatingDefaults $account */
		$account = (new \ReflectionClass(AccountWithMutatingDefaults::class))->newInstanceWithoutConstructor();
		/** @var EmailWithMutatingDefaults $email */
		$email = (new \ReflectionClass(EmailWithMutatingDefaults::class))->newInstanceWithoutConstructor();

		$disabledGateway = function (string $class): object {
			$gateway = $this->getMockBuilder($class)
				->disableOriginalConstructor()
				->onlyMethods(['isTwofactorGatewayEnabled'])
				->getMock();
			$gateway->method('isTwofactorGatewayEnabled')->willReturn(false);
			return $gateway;
		};

		$identifyMethodService = new IdentifyMethodService(
			$this->createMock(IdentifyMethodMapper::class),
			$this->createMock(IL10N::class),
			$this->createMock(IUserManager::class),
			$account,
			$email,
			$disabledGateway(Signal::class),
			$disabledGateway(Sms::class),
			$disabledGateway(Telegram::class),
			$disabledGateway(Whatsapp::class),
			$disabledGateway(Whatsappbusiness::class),
			$disabledGateway(Xmpp::class),
			$this->createMock(SubjectAlternativeNameService::class),
		);

		self::assertCount(2, $identifyMethodService->getDefaultIdentifyMethodsPolicy());
		self::assertCount(2, $identifyMethodService->getIdentifyMethodsCatalogSettings());
		self::assertFalse($account->defaultSettingsWereApplied);
		self::assertFalse($email->defaultSettingsWereApplied);
	}
}
