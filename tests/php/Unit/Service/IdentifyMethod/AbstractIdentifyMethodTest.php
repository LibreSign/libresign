<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\IdentifyMethod;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\IdentifyMethod;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\IdentifyMethod\AbstractIdentifyMethod;
use OCA\Libresign\Service\IdentifyMethod\IdentifyService;
use OCA\Libresign\Service\SessionService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IAppConfig;
use OCP\IL10N;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

final class AbstractIdentifyMethodTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private IdentifyService&MockObject $identifyService;
	private IAppConfig&MockObject $appConfig;
	private SessionService&MockObject $sessionService;
	private SignRequestMapper&MockObject $signRequestMapper;
	private ITimeFactory&MockObject $timeFactory;

	public function setUp(): void {
		$this->identifyService = $this->createMock(IdentifyService::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->sessionService = $this->createMock(SessionService::class);
		$this->signRequestMapper = $this->createMock(SignRequestMapper::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$l10n = $this->createMock(IL10N::class);

		$l10n->method('t')
			->willReturnCallback(static fn (string $text): string => $text);
		$this->identifyService->method('getL10n')->willReturn($l10n);
		$this->identifyService->method('getAppConfig')->willReturn($this->appConfig);
		$this->identifyService->method('getSessionService')->willReturn($this->sessionService);
		$this->identifyService->method('getSignRequestMapper')->willReturn($this->signRequestMapper);
		$this->identifyService->method('getTimeFactory')->willReturn($this->timeFactory);
	}

	#[DataProvider('providerRuntimeConfigReadPaths')]
	public function testRuntimeConfigIsReadWithCacheRefresh(string $path): void {
		$cacheCleared = false;
		$this->appConfig->expects($this->once())
			->method('clearCache')
			->with(true)
			->willReturnCallback(function () use (&$cacheCleared): void {
				$cacheCleared = true;
			});
		$this->appConfig->expects($this->once())
			->method('getValueInt')
			->with(Application::APP_ID, 'renewal_interval', SessionService::NO_RENEWAL_INTERVAL)
			->willReturnCallback(function () use (&$cacheCleared): int {
				$this->assertTrue($cacheCleared);
				return 10;
			});

		$identifyMethod = $this->newIdentifyMethodEntity(
			signRequestId: 10,
			identifierValue: 'signer@domain.test',
			lastAttemptDate: '2026-02-16T10:00:01+00:00',
			identifiedAtDate: null,
		);

		if ($path === 'renewSession') {
			$this->sessionService->expects($this->once())
				->method('setIdentifyMethodId')
				->with(99);
			$this->sessionService->expects($this->once())
				->method('resetDurationOfSignPage');
			$this->newMethodWithEntity($identifyMethod)->runRenewSession();
			return;
		}

		$this->sessionService->method('getSignStartTime')->willReturn(0);
		$this->signRequestMapper->method('getById')->with(10)->willReturn($this->newSignRequest(
			createdAt: '2026-02-16T10:00:09+00:00',
			uuid: '9f95dc38-c2f8-43e5-a91d-8e191ca9520d',
		));
		$this->timeFactory->method('getDateTime')->willReturn(new \DateTime('2026-02-16T10:00:10+00:00'));

		$this->newMethodWithEntity($identifyMethod)->runThrowIfRenewalIntervalExpired();
	}

	public static function providerRuntimeConfigReadPaths(): array {
		return [
			'renewSession path' => ['renewSession'],
			'throwIfRenewalIntervalExpired path' => ['throwIfRenewalIntervalExpired'],
		];
	}

	#[DataProvider('providerRenewalWindowByLastAction')]
	public function testRenewalWindowUsesIdentifiedAtAsLastAction(?string $identifiedAtDate, bool $mustExpire): void {
		$this->appConfig->method('clearCache');
		$this->appConfig->method('getValueInt')
			->with(Application::APP_ID, 'renewal_interval', SessionService::NO_RENEWAL_INTERVAL)
			->willReturn(10);

		$this->sessionService->method('getSignStartTime')->willReturn(0);
		$this->signRequestMapper->method('getById')->with(10)->willReturn($this->newSignRequest(
			createdAt: '2026-02-16T10:00:00+00:00',
			uuid: '903c8fa8-f140-4213-a2fd-f435eea3492d',
		));
		$this->timeFactory->method('getDateTime')->willReturn(new \DateTime('2026-02-16T10:00:12+00:00'));

		$identifyMethod = $this->newIdentifyMethodEntity(
			signRequestId: 10,
			identifierValue: 'signer@domain.test',
			lastAttemptDate: '2026-02-16T10:00:01+00:00',
			identifiedAtDate: $identifiedAtDate,
		);

		$method = $this->newMethodWithEntity($identifyMethod);
		$method->forceName('email');

		if ($mustExpire) {
			$this->expectException(LibresignException::class);
			$this->expectExceptionMessageMatches('/.*Link expired.*/');
			$method->runThrowIfRenewalIntervalExpired();
			return;
		}

		$method->runThrowIfRenewalIntervalExpired();
		$this->assertSame(10, $method->getEntity()->getSignRequestId());
	}

	public static function providerRenewalWindowByLastAction(): array {
		return [
			'without identifiedAt expires by older lastAttempt' => [null, true],
			'with recent identifiedAt keeps renewal valid' => ['2026-02-16T10:00:05+00:00', false],
		];
	}

	private function newMethodWithEntity(IdentifyMethod $entity): AbstractIdentifyMethodForTest {
		$method = new AbstractIdentifyMethodForTest($this->identifyService);
		$method->setEntity($entity);
		return $method;
	}

	private function newIdentifyMethodEntity(
		int $signRequestId,
		string $identifierValue,
		?string $lastAttemptDate,
		?string $identifiedAtDate,
	): IdentifyMethod {
		$identifyMethod = new IdentifyMethod();
		$identifyMethod->setId(99);
		$identifyMethod->setSignRequestId($signRequestId);
		$identifyMethod->setIdentifierValue($identifierValue);
		$identifyMethod->setLastAttemptDate($lastAttemptDate);
		$identifyMethod->setIdentifiedAtDate($identifiedAtDate);
		return $identifyMethod;
	}

	private function newSignRequest(string $createdAt, string $uuid): SignRequest {
		$signRequest = new SignRequest();
		$signRequest->setCreatedAt(new \DateTime($createdAt));
		$signRequest->setUuid($uuid);
		return $signRequest;
	}
}

final class AbstractIdentifyMethodForTest extends AbstractIdentifyMethod {
	public function runRenewSession(): void {
		$this->renewSession();
	}

	public function runThrowIfRenewalIntervalExpired(): void {
		$this->throwIfRenewalIntervalExpired();
	}

	public function forceName(string $name): void {
		$this->name = $name;
	}
}
