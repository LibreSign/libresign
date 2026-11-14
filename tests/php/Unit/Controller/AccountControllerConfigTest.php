<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Controller;

use OC\Authentication\Login\Chain;
use OCA\Libresign\Controller\AccountController;
use OCA\Libresign\Handler\SignEngine\Pkcs12Handler;
use OCA\Libresign\Service\AccountService;
use OCA\Libresign\Service\SessionService;
use OCA\Libresign\Service\SignerElementsService;
use OCA\Libresign\Service\SignFileService;
use OCP\Accounts\IAccountManager;
use OCP\AppFramework\Http;
use OCP\Config\IUserConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class AccountControllerConfigTest extends TestCase {
	public static function configValues(): array {
		return [
			'string' => ['date', 'date'],
			'boolean true' => [true, '1'],
			'boolean false' => [false, '0'],
			'integer' => [4, '4'],
			'fractional number' => [1.25, '1.25'],
			'empty list' => [[], '[]'],
			'nested JSON' => [['advanced' => true, 'filters' => ['ids' => [1, null, 'a'], 'hidden' => false]], '{"advanced":true,"filters":{"ids":[1,null,"a"],"hidden":false}}'],
		];
	}

	#[DataProvider('configValues')]
	public function testConfigValuesAreStoredAsStrings(mixed $input, string $stored): void {
		$config = $this->createMock(IUserConfig::class);
		$config->expects($this->once())
			->method('setValueString')
			->with('account-test', 'libresign', 'setting', $stored)
			->willReturn(true);
		$response = $this->controller($config)->setConfig('setting', $input);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(['key' => 'setting', 'value' => $stored], $response->getData());
	}

	public function testExplicitNullDoesNotWriteAConfigValue(): void {
		$config = $this->createMock(IUserConfig::class);
		$config->expects($this->never())->method('setValueString');
		$response = $this->controller($config)->setConfig('setting', null);
		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	private function controller(IUserConfig $config): AccountController {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('account-test');
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);

		return new AccountController(
			$this->createMock(IRequest::class),
			$this->createMock(IL10N::class),
			$this->createMock(IAccountManager::class),
			$this->createMock(AccountService::class),
			$this->createMock(SignFileService::class),
			$this->createMock(SignerElementsService::class),
			$this->createMock(Pkcs12Handler::class),
			$this->createMock(Chain::class),
			$this->createMock(IURLGenerator::class),
			$this->createMock(LoggerInterface::class),
			$userSession,
			$this->createMock(SessionService::class),
			$config,
		);
	}
}
