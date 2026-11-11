<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\SignatureRejection;

use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Events\SignatureRejectedEvent;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\SequentialSigningService;
use OCA\Libresign\Service\SignatureRejection\CommittedRejection;
use OCA\Libresign\Service\SignatureRejection\SignatureRejectionNotifier;
use OCA\Libresign\Service\Webhook\NativeWebhookEventPublisher;
use OCP\EventDispatcher\IEventDispatcher;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class SignatureRejectionNotifierTest extends TestCase {
	use SignatureRejectionSamples;

	private NativeWebhookEventPublisher&MockObject $webhooks;
	private IdentifyMethodService&MockObject $identification;
	private IEventDispatcher&MockObject $events;
	private SequentialSigningService&MockObject $sequential;
	private LoggerInterface&MockObject $logger;
	private SignatureRejectionNotifier $notifier;

	protected function setUp(): void {
		$this->webhooks = $this->createMock(NativeWebhookEventPublisher::class);
		$this->identification = $this->createMock(IdentifyMethodService::class);
		$this->events = $this->createMock(IEventDispatcher::class);
		$this->sequential = $this->createMock(SequentialSigningService::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->notifier = new SignatureRejectionNotifier(
			$this->webhooks, $this->identification, $this->events, $this->sequential, $this->logger,
		);
	}

	public function testOneNativeEventAndLegacyEventPerIdentificationMethod(): void {
		$file = $this->file(FileStatus::CANCELED->value);
		$signer = $this->signer();
		$next = $this->signer(id: 99);
		$methods = [$this->createMock(IIdentifyMethod::class), $this->createMock(IIdentifyMethod::class)];
		$this->identification->method('getIdentifyMethodsFromSignRequestId')->willReturn(['account' => $methods]);
		$this->webhooks->expects($this->once())->method('signerRejected')
			->with($file, $signer, FileStatus::ABLE_TO_SIGN->value);
		$legacy = [];
		$this->events->method('dispatchTyped')->willReturnCallback(
			static function ($event) use (&$legacy): void {
				$legacy[] = $event;
			},
		);
		$this->sequential->expects($this->once())->method('notifyActivatedSigners')->with([$next]);

		$this->notifier->afterCommit($file, $signer, new CommittedRejection(FileStatus::ABLE_TO_SIGN->value, [$next]));

		$this->assertCount(2, $legacy);
		foreach ($legacy as $event) {
			$this->assertInstanceOf(SignatureRejectedEvent::class, $event);
			$this->assertTrue($event->wasWorkflowCanceled());
		}
	}

	public function testListenerFailureDoesNotPreventPostCommitSignerNotification(): void {
		$this->identification->method('getIdentifyMethodsFromSignRequestId')->willReturn([
			'account' => [$this->createMock(IIdentifyMethod::class)],
		]);
		$this->events->method('dispatchTyped')->willThrowException(new \RuntimeException('listener failed'));
		$this->logger->expects($this->once())->method('error')
			->with('Error dispatching SignatureRejectedEvent', ['errorType' => \RuntimeException::class]);
		$next = $this->signer(id: 77);
		$this->sequential->expects($this->once())->method('notifyActivatedSigners')->with([$next]);

		$this->notifier->afterCommit($this->file(), $this->signer(), new CommittedRejection(1, [$next]));
	}

	public function testLegacyListenerTypeErrorDoesNotInterruptCommittedRejection(): void {
		$this->identification->method('getIdentifyMethodsFromSignRequestId')->willReturn([
			'account' => [$this->createMock(IIdentifyMethod::class)],
		]);
		$this->events->method('dispatchTyped')->willThrowException(new \TypeError('private listener error'));
		$this->logger->expects($this->once())->method('error')->with(
			'Error dispatching SignatureRejectedEvent',
			['errorType' => \TypeError::class],
		);
		$next = $this->signer(id: 77);
		$this->sequential->expects($this->once())->method('notifyActivatedSigners')->with([$next]);

		$this->notifier->afterCommit(
			$this->file(),
			$this->signer(),
			new CommittedRejection(1, [$next]),
		);
	}

	public function testFailureInOneLegacyMethodDoesNotBlockOtherMethods(): void {
		$this->identification->method('getIdentifyMethodsFromSignRequestId')->willReturn([
			'account' => [
				$this->createMock(IIdentifyMethod::class),
				$this->createMock(IIdentifyMethod::class),
			],
		]);
		$calls = 0;
		$this->events->method('dispatchTyped')->willReturnCallback(
			static function () use (&$calls): void {
				if (++$calls === 1) {
					throw new \TypeError('legacy listener error');
				}
			},
		);
		$this->logger->expects($this->once())->method('error')
			->with('Error dispatching SignatureRejectedEvent', ['errorType' => \TypeError::class]);
		$this->sequential->expects($this->once())->method('notifyActivatedSigners')->with([]);

		$this->notifier->afterCommit($this->file(), $this->signer(), new CommittedRejection(1, []));

		$this->assertSame(2, $calls);
	}

	public function testNotificationFailureAfterCommitRemainsVisible(): void {
		$this->identification->method('getIdentifyMethodsFromSignRequestId')->willReturn([]);
		$this->sequential->method('notifyActivatedSigners')
			->willThrowException(new \RuntimeException('notification failure'));
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('notification failure');

		$this->notifier->afterCommit($this->file(), $this->signer(), new CommittedRejection(1, []));
	}

	public function testNativeWebhookIsAttemptedBeforeLegacyListeners(): void {
		$calls = [];
		$this->webhooks->method('signerRejected')->willReturnCallback(
			static function () use (&$calls): void {
				$calls[] = 'native';
			},
		);
		$this->identification->method('getIdentifyMethodsFromSignRequestId')->willReturn([
			'account' => [$this->createMock(IIdentifyMethod::class)],
		]);
		$this->events->method('dispatchTyped')->willReturnCallback(
			static function () use (&$calls): void {
				$calls[] = 'legacy';
			},
		);
		$this->sequential->method('notifyActivatedSigners')->willReturnCallback(
			static function () use (&$calls): void {
				$calls[] = 'notify';
			},
		);

		$this->notifier->afterCommit($this->file(), $this->signer(), new CommittedRejection(1, []));
		$this->assertSame(['native', 'legacy', 'notify'], $calls);
	}

	public function testUnexpectedNotificationProgrammingErrorIsNotHidden(): void {
		$this->identification->method('getIdentifyMethodsFromSignRequestId')->willReturn([]);
		$this->sequential->method('notifyActivatedSigners')
			->willThrowException(new \TypeError('incorrect notification dependency'));
		$this->expectException(\TypeError::class);

		$this->notifier->afterCommit($this->file(), $this->signer(), new CommittedRejection(1, []));
	}

	public function testContinuingWorkflowIsNotReportedCanceled(): void {
		$signer = $this->signer();
		$this->identification->method('getIdentifyMethodsFromSignRequestId')->willReturn([
			'account' => [$this->createMock(IIdentifyMethod::class)],
		]);
		$legacy = null;
		$this->events->method('dispatchTyped')->willReturnCallback(
			static function ($event) use (&$legacy): void {
				$legacy = $event;
			},
		);

		$this->notifier->afterCommit($this->file(), $signer, new CommittedRejection(1, []));

		$this->assertInstanceOf(SignatureRejectedEvent::class, $legacy);
		$this->assertFalse($legacy->wasWorkflowCanceled());
	}
}
