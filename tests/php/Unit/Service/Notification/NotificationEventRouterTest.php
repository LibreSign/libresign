<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Notification;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Events\SendSignNotificationEvent;
use OCA\Libresign\Events\SignRequestCanceledEvent;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Exception\NotificationTransportException;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCA\Libresign\Service\Notification\NotificationEventRouter;
use OCA\Libresign\Service\Notification\NotificationModule;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class NotificationEventRouterTest extends TestCase {
	private function event(?string $id = null): SendSignNotificationEvent {
		return new SendSignNotificationEvent(
			new SignRequest(), new File(), $this->createMock(IIdentifyMethod::class), $id,
		);
	}

	public function testFactoryRoutesNewModuleOnlyToItsAdvertisedEvents(): void {
		$module = new class implements NotificationModule {
			public int $invocations = 0;

			public function transport(): \OCA\Libresign\Service\Notification\NotificationChannel {
				return new class implements \OCA\Libresign\Service\Notification\NotificationChannel {
					public function supports(string $channel): bool {
						return $channel === 'synthetic';
					}
					public function deliver(\OCA\Libresign\Service\Notification\NotificationMessage $message): void {
					}
				};
			}

			public static function supportedEvents(): array {
				return [SendSignNotificationEvent::class];
			}

			public function handle(Event $event): void {
				$this->invocations++;
			}
		};
		$router = NotificationEventRouter::fromModules([$module], $this->createMock(LoggerInterface::class));
		$router->handle($this->event('sign-request-write:synthetic'));
		$router->handle(new SignRequestCanceledEvent(
			new SignRequest(), new File(), $this->createMock(IIdentifyMethod::class),
		));
		$this->assertSame(1, $module->invocations);
	}

	public function testInvalidModuleEventIsRejectedDuringRegistration(): void {
		$module = new class implements NotificationModule {
			public function transport(): \OCA\Libresign\Service\Notification\NotificationChannel {
				return new class implements \OCA\Libresign\Service\Notification\NotificationChannel {
					public function supports(string $channel): bool {
						return $channel === 'synthetic';
					}
					public function deliver(\OCA\Libresign\Service\Notification\NotificationMessage $message): void {
					}
				};
			}

			public static function supportedEvents(): array {
				return ['not-an-event'];
			}

			public function handle(Event $event): void {
			}
		};
		$this->expectException(\InvalidArgumentException::class);
		NotificationEventRouter::fromModules([$module], $this->createMock(LoggerInterface::class));
	}

	public function testAutomaticMailFailureDoesNotPreventOtherChannels(): void {
		$calls = [];
		$mail = $this->createMock(IEventListener::class);
		$mail->method('handle')->willReturnCallback(static function () use (&$calls): void {
			$calls[] = 'mail';
			throw new \RuntimeException('synthetic SMTP failure');
		});
		$gateway = $this->createMock(IEventListener::class);
		$gateway->method('handle')->willReturnCallback(static function () use (&$calls): void {
			$calls[] = 'gateway';
		});
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())->method('warning')->with(
			'A notification channel did not confirm submission',
			$this->callback(static fn (array $data): bool => $data['exceptionType'] === \RuntimeException::class
				&& !array_key_exists('exception', $data)),
		);
		(new NotificationEventRouter([SendSignNotificationEvent::class => [$mail, $gateway]], $logger))->handle($this->event('sign-request-write:fixture'));
		$this->assertSame(['mail', 'gateway'], $calls);
	}

	public function testManualPartialFailureIsReportedAfterOtherChannelsRun(): void {
		$gatewayCalled = false;
		$mail = $this->createMock(IEventListener::class);
		$mail->method('handle')->willThrowException(new \RuntimeException('secret server detail'));
		$gateway = $this->createMock(IEventListener::class);
		$gateway->expects($this->once())->method('handle')->willReturnCallback(
			static function () use (&$gatewayCalled): void {
				$gatewayCalled = true;
			},
		);
		$router = new NotificationEventRouter([SendSignNotificationEvent::class => [$mail, $gateway]], $this->createMock(LoggerInterface::class));
		try {
			$router->handle($this->event('manual:synthetic'));
			$this->fail('Partial failure must reach the manual API');
		} catch (NotificationTransportException $e) {
			$this->assertTrue($gatewayCalled);
			$this->assertSame('notification_channels_not_confirmed', $e->reasonCode);
			$this->assertStringNotContainsString('secret server detail', $e->getMessage());
		}
	}

	public function testCancellationRunsOnlyHandlersThatSupportCancellation(): void {
		$requests = $this->createMock(IEventListener::class);
		$requests->expects($this->once())->method('handle');
		$mail = $this->createMock(IEventListener::class);
		$mail->expects($this->once())->method('handle');
		$gateway = $this->createMock(IEventListener::class);
		$gateway->expects($this->never())->method('handle');

		$method = $this->createMock(IIdentifyMethod::class);
		$router = new NotificationEventRouter([
			SendSignNotificationEvent::class => [$requests, $mail, $gateway],
			SignRequestCanceledEvent::class => [$requests, $mail],
		], $this->createMock(LoggerInterface::class));

		$router->handle(new SignRequestCanceledEvent(new SignRequest(), new File(), $method));
	}

	public function testNewHandlerCanBeAddedWithoutChangingOtherHandlers(): void {
		$calls = [];
		$handlers = [];
		foreach (['nextcloud', 'mail', 'third-party'] as $name) {
			$handler = $this->createMock(IEventListener::class);
			$handler->method('handle')->willReturnCallback(static function () use (&$calls, $name): void {
				$calls[] = $name;
			});
			$handlers[] = $handler;
		}
		(new NotificationEventRouter([SendSignNotificationEvent::class => $handlers], $this->createMock(LoggerInterface::class)))
			->handle($this->event('sign-request-write:synthetic'));
		$this->assertSame(['nextcloud', 'mail', 'third-party'], $calls);
	}

	public function testInvariantFailureIsReportedAfterRemainingChannelsRun(): void {
		$first = $this->createMock(IEventListener::class);
		$first->method('handle')->willThrowException(new \LogicException('open transaction'));
		$second = $this->createMock(IEventListener::class);
		$second->expects($this->once())->method('handle');
		$router = new NotificationEventRouter([SendSignNotificationEvent::class => [$first, $second]], $this->createMock(LoggerInterface::class));
		$this->expectException(\LogicException::class);
		$router->handle($this->event('sign-request-write:synthetic'));
	}

	public function testManualValidationFailureKeepsDomainError(): void {
		$original = new LibresignException('Cannot notify a completed signer', 422);
		$first = $this->createMock(IEventListener::class);
		$first->method('handle')->willThrowException($original);
		$second = $this->createMock(IEventListener::class);
		$second->expects($this->once())->method('handle');
		$router = new NotificationEventRouter([
			SendSignNotificationEvent::class => [$first, $second],
		], $this->createMock(LoggerInterface::class));
		$this->expectExceptionObject($original);
		$router->handle($this->event('manual:synthetic'));
	}

	public function testProgrammingErrorsAreNotHiddenAsTransportFailures(): void {
		$handler = $this->createMock(IEventListener::class);
		$handler->method('handle')->willThrowException(new \TypeError('programming error'));
		$router = new NotificationEventRouter([SendSignNotificationEvent::class => [$handler]], $this->createMock(LoggerInterface::class));
		$this->expectException(\TypeError::class);
		$router->handle($this->event('sign-request-write:synthetic'));
	}
}
