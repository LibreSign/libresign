<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Libresign\Tests\Unit\Service\IdentifyMethod\SignatureMethod;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\IdentifyMethod;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\IdentifyMethod\Account;
use OCA\Libresign\Service\IdentifyMethod\IdentifyService;
use OCA\Libresign\Service\IdentifyMethod\SignatureMethod\ISignatureMethod;
use OCA\Libresign\Service\MailService;
use OCA\Libresign\Service\SessionService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\L10N\IFactory as IL10NFactory;
use OCP\Security\IHasher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

class AccountTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private IdentifyService&MockObject $identifyService;
	private IUserManager&MockObject $userManager;
	private IEventDispatcher&MockObject $eventDispatcher;
	private IdentifyMethodMapper&MockObject $identifyMethodMapper;
	private IUserSession&MockObject $userSession;
	private IURLGenerator&MockObject $urlGenerator;
	private IRootFolder&MockObject $root;
	private IHasher&MockObject $hasher;
	private ITimeFactory&MockObject $timeFactory;
	private LoggerInterface&MockObject $logger;
	private SessionService&MockObject $sessionService;
	private MailService&MockObject $mailService;
	private IL10N $l10n;

	public function setUp(): void {
		$this->identifyService = $this->createMock(IdentifyService::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
		$this->identifyMethodMapper = $this->createMock(IdentifyMethodMapper::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->root = $this->createMock(IRootFolder::class);
		$this->hasher = $this->createMock(IHasher::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->sessionService = $this->createMock(SessionService::class);
		$this->mailService = $this->createMock(MailService::class);

		$this->l10n = \OCP\Server::get(IL10NFactory::class)->get(Application::APP_ID);
		$this->identifyService->method('getL10n')->willReturn($this->l10n);
		$this->identifyService->method('getAppConfig')->willReturn($this->getMockAppConfig());
	}

	private function getClass(): Account {
		return new Account(
			$this->identifyService,
			$this->userManager,
			$this->eventDispatcher,
			$this->identifyMethodMapper,
			$this->userSession,
			$this->urlGenerator,
			$this->root,
			$this->hasher,
			$this->timeFactory,
			$this->logger,
			$this->sessionService,
			$this->mailService,
		);
	}

	public function testValidateToRequestThrowsWhenUserNotFound(): void {
		$entity = new IdentifyMethod();
		$entity->setIdentifierValue('nonexistent_user');

		// Mock userManager to return null for both get() and getByEmail()
		$this->userManager->method('get')->with('nonexistent_user')->willReturn(null);
		$this->userManager->method('getByEmail')->with('nonexistent_user')->willReturn([]);

		$account = $this->getClass();
		$account->setEntity($entity);

		$this->expectException(LibresignException::class);
		$this->expectExceptionMessageMatches('/.*Invalid user.*/');

		$account->validateToRequest();
	}

	#[DataProvider('providerValidateToRequestEmailToken')]
	public function testValidateToRequestWithEmailToken(string $userEmail, ?string $expectedException, ?string $expectedMessage): void {
		$entity = new IdentifyMethod();
		$entity->setIdentifierValue('valid_user');

		$user = $this->createMock(IUser::class);
		$user->method('getEMailAddress')->willReturn($userEmail);

		// Mock userManager to return user directly (found by username/id)
		$this->userManager->method('get')->with('valid_user')->willReturn($user);
		$this->userManager->method('getByEmail')->willReturn([]);

		$signatureMethod = $this->createSignatureMethod(ISignatureMethod::SIGNATURE_METHOD_EMAIL_TOKEN, true);
		$account = $this->createAccountWithMockSignatureMethods([$signatureMethod]);
		$account->setEntity($entity);

		if ($expectedException) {
			$this->expectException($expectedException);
			if ($expectedMessage) {
				$this->expectExceptionMessage($expectedMessage);
			}
		}

		$account->validateToRequest();

		if (!$expectedException) {
			$this->assertTrue(true);
		}
	}

	public static function providerValidateToRequestEmailToken(): array {
		return [
			'Valid email' => [
				'userEmail' => 'user@example.com',
				'expectedException' => null,
				'expectedMessage' => null,
			],
			'Empty email' => [
				'userEmail' => '',
				'expectedException' => LibresignException::class,
				'expectedMessage' => 'Signer without valid email address',
			],
			'Invalid email' => [
				'userEmail' => 'invalid-email',
				'expectedException' => LibresignException::class,
				'expectedMessage' => 'Signer without valid email address',
			],
		];
	}

	#[DataProvider('providerValidateToRequestSuccess')]
	public function testValidateToRequestSuccess(array $signatureMethods): void {
		$entity = new IdentifyMethod();
		$entity->setIdentifierValue('valid_user');

		$user = $this->createMock(IUser::class);
		$user->method('getEMailAddress')->willReturn('user@example.com');

		// Mock userManager to return user directly (found by username/id)
		$this->userManager->method('get')->with('valid_user')->willReturn($user);
		$this->userManager->method('getByEmail')->willReturn([]);

		$signatureMethodMocks = [];
		foreach ($signatureMethods as $methodConfig) {
			$signatureMethodMocks[] = $this->createSignatureMethod($methodConfig['name'], $methodConfig['enabled']);
		}

		$account = $this->createAccountWithMockSignatureMethods($signatureMethodMocks);
		$account->setEntity($entity);

		$account->validateToRequest();

		$this->assertTrue(true);
	}

	public static function providerValidateToRequestSuccess(): array {
		return [
			'No signature methods' => [
				'signatureMethods' => [],
			],
			'Email token method disabled' => [
				'signatureMethods' => [
					['name' => ISignatureMethod::SIGNATURE_METHOD_EMAIL_TOKEN, 'enabled' => false],
				],
			],
			'Click to sign enabled' => [
				'signatureMethods' => [
					['name' => ISignatureMethod::SIGNATURE_METHOD_CLICK_TO_SIGN, 'enabled' => true],
				],
			],
		];
	}

	private function createSignatureMethod(string $name, bool $enabled): ISignatureMethod {
		return new class($name, $enabled) implements ISignatureMethod {
			private string $name;
			private bool $enabled;

			public function __construct(string $name, bool $enabled) {
				$this->name = $name;
				$this->enabled = $enabled;
			}

			public function getName(): string {
				return $this->name;
			}

			public function enable(): void {
				$this->enabled = true;
			}

			public function isEnabled(): bool {
				return $this->enabled;
			}

			public function toArray(): array {
				return ['name' => $this->name, 'enabled' => $this->enabled];
			}
		};
	}

	private function createAccountWithMockSignatureMethods(array $signatureMethods): Account {
		$accountMock = new class($this->identifyService, $this->userManager, $this->eventDispatcher, $this->identifyMethodMapper, $this->userSession, $this->urlGenerator, $this->root, $this->hasher, $this->timeFactory, $this->logger, $this->sessionService, $this->mailService, ) extends Account {
			private array $mockSignatureMethods = [];

			public function setMockSignatureMethods(array $methods): void {
				$this->mockSignatureMethods = $methods;
			}

			public function getSignatureMethods(): array {
				return $this->mockSignatureMethods;
			}
		};

		$accountMock->setMockSignatureMethods($signatureMethods);
		return $accountMock;
	}
}
