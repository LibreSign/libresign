<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Notification;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\NotificationDelivery;
use OCA\Libresign\Db\NotificationDeliveryMapper;
use OCA\Libresign\Enum\NotificationDeliveryState;
use OCA\Libresign\Events\NotificationDeliveryStatusChangedEvent;
use OCA\Libresign\Service\Notification\NotificationDeliveryEventPublisher;
use OCP\EventDispatcher\IEventDispatcher;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class NotificationDeliveryEventPublisherTest extends TestCase {
	private NotificationDeliveryMapper&MockObject $deliveries;
	private FileMapper&MockObject $files;
	private IEventDispatcher&MockObject $dispatcher;
	private LoggerInterface&MockObject $logger;
	private NotificationDeliveryEventPublisher $publisher;

	protected function setUp(): void {
		$this->deliveries = $this->createMock(NotificationDeliveryMapper::class);
		$this->files = $this->createMock(FileMapper::class);
		$this->dispatcher = $this->createMock(IEventDispatcher::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->publisher = new NotificationDeliveryEventPublisher(
			$this->deliveries, $this->files, $this->dispatcher, $this->logger,
		);
	}

	private function delivery(string $state = 'accepted'): NotificationDelivery {
		$delivery = new NotificationDelivery();
		$delivery->setId(9);
		$delivery->setFileId(25);
		$delivery->setState($state);
		$delivery->setAttemptCount(1);
		$delivery->setChannel('mail');
		$delivery->setRecipientEnc('PRIVATE_RECIPIENT');
		return $delivery;
	}

	public function testPublishedOutcomeUsesRootOfEnvelopeWithoutDisclosingRecipient(): void {
		$this->deliveries->method('findById')->with(9)->willReturn($this->delivery());
		$child = new File();
		$child->setParentFileId(10);
		$root = new File();
		$root->setUuid('root-uuid');
		$root->setNodeType('envelope');
		$this->files->method('getById')->willReturnMap([[25, $child], [10, $root]]);
		$this->dispatcher->expects($this->once())->method('dispatchTyped')
			->with($this->callback(static function ($event): bool {
				if (!$event instanceof NotificationDeliveryStatusChangedEvent) {
					return false;
				}
				$payload = $event->getWebhookSerializable();
				return $payload['requestUuid'] === 'root-uuid'
					&& $payload['requestType'] === 'envelope'
					&& !str_contains(json_encode($payload), 'PRIVATE_RECIPIENT');
			}));
		$this->publisher->publish(9);
	}

	public function testPendingDeliveryCannotPublishAnOutcome(): void {
		$this->deliveries->method('findById')->willReturn($this->delivery(NotificationDeliveryState::PENDING->value));
		$root = new File();
		$root->setUuid('root-uuid');
		$this->files->method('getById')->willReturn($root);
		$this->dispatcher->expects($this->never())->method('dispatchTyped');
		$this->publisher->publish(9);
	}

	public function testExpectedRepositoryFailureIsLoggedWithoutSensitiveData(): void {
		$this->deliveries->method('findById')
			->willThrowException(new \RuntimeException('PRIVATE_RECIPIENT'));
		$this->dispatcher->expects($this->never())->method('dispatchTyped');
		$this->logger->expects($this->once())->method('warning')
			->with($this->anything(), $this->callback(static function (array $context): bool {
				return !str_contains(json_encode($context), 'PRIVATE_RECIPIENT')
					&& $context['deliveryId'] === 9;
			}));
		$this->publisher->publish(9);
	}

	public function testListenerProgrammingErrorCannotInterruptCommittedDelivery(): void {
		$this->deliveries->method('findById')->willReturn($this->delivery());
		$root = new File();
		$root->setUuid('root-uuid');
		$this->files->method('getById')->willReturn($root);
		$this->dispatcher->method('dispatchTyped')->willThrowException(
			new \TypeError('PRIVATE_WEBHOOK_DETAILS'),
		);
		$this->logger->expects($this->once())->method('warning')->with(
			'Unable to publish persisted notification outcome',
			$this->callback(static fn (array $context): bool
				=> ($context['deliveryId'] ?? null) === 9
				&& ($context['errorType'] ?? null) === \TypeError::class
				&& !str_contains(json_encode($context), 'PRIVATE_WEBHOOK_DETAILS')
			),
		);

		$this->publisher->publish(9);
	}
}
