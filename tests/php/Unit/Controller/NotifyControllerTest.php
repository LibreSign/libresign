<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Controller;

use OCA\Libresign\Controller\NotifyController;
use OCA\Libresign\Enum\NotificationDeliveryState;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Exception\MailTransportException;
use OCA\Libresign\Exception\NotificationTransportException;
use OCA\Libresign\Service\Notification\NotificationDeliveryStatusService;
use OCA\Libresign\Service\NotifyService;
use OCP\AppFramework\Http;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class NotifyControllerTest extends TestCase {
	private NotifyService $service;
	private NotificationDeliveryStatusService $statusService;
	private NotifyController $controller;

	protected function setUp(): void {
		$this->service = $this->createMock(NotifyService::class);
		$this->statusService = $this->createMock(NotificationDeliveryStatusService::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $message): string => $message);
		$this->controller = new NotifyController(
			$this->createMock(IRequest::class), $l10n, $this->service, $this->statusService,
			$this->createMock(IUserSession::class), $this->createMock(LoggerInterface::class),
		);
	}

	public function testManualResendUsesExistingEndpointAndDoesNotClaimDelivery(): void {
		$this->service->expects($this->once())->method('signer')->with(12, 23, null);
		$response = $this->controller->signer(12, 23);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame('Notification request processed. Delivery is not confirmed.', $response->getData()['message']);
	}

	public function testStatusUsesAuthorizedQueryAndNoStoreCache(): void {
		$id = str_repeat('a', 32);
		$this->statusService->expects($this->once())->method('status')
			->with(12, 23, $id)->willReturn([
				'status' => 'completed', 'complete' => true,
				'channels' => [['channel' => 'mail', 'state' => 'accepted']],
			]);
		$response = $this->controller->status(12, 23, $id);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertTrue($response->getData()['complete']);
		$this->assertSame('accepted', $response->getData()['channels'][0]['state']);
	}

	public function testValidationIsNotChangedIntoUnauthorizedTransportFailure(): void {
		$this->service->method('signer')->willThrowException(new LibresignException('cannot notify', 422));
		$this->expectException(LibresignException::class);
		$this->controller->signer(12, 23);
	}

	public function testUnknownTransportOutcomeDoesNotLeakRawError(): void {
		$this->service->method('signer')->willThrowException(new MailTransportException(
			'SMTP credentials and network details must not be exposed',
			NotificationDeliveryState::UNKNOWN, 'smtp_outcome_unknown',
		));
		$response = $this->controller->signer(12, 23);
		$this->assertSame(Http::STATUS_BAD_GATEWAY, $response->getStatus());
		$this->assertStringNotContainsString('credentials', json_encode($response->getData()));
	}

	public function testWrappedMailFailureReturnsTransportFailure(): void {
		$failure = new MailTransportException('Sender timed out', NotificationDeliveryState::UNKNOWN, 'smtp_outcome_unknown');
		$this->service->method('signer')->willThrowException(new LibresignException('Mail failed', 1, $failure));
		$response = $this->controller->signer(12, 23);
		$this->assertSame(Http::STATUS_BAD_GATEWAY, $response->getStatus());
		$this->assertStringNotContainsString('Sender timed out', json_encode($response->getData()));
	}

	public function testGatewayErrorReturnsSanitizedTransportFailure(): void {
		$this->service->method('signer')->willThrowException(new NotificationTransportException(
			'Private external endpoint', NotificationDeliveryState::UNKNOWN, 'gateway_outcome_unknown',
		));
		$response = $this->controller->signer(12, 23);
		$this->assertSame(Http::STATUS_BAD_GATEWAY, $response->getStatus());
		$this->assertStringNotContainsString('Private external endpoint', json_encode($response->getData()));
	}

	public function testUnexpectedErrorsReturnGenericServerResponse(): void {
		$this->service->method('signers')->willThrowException(new \RuntimeException('private server detail'));
		$response = $this->controller->signers(12, [['email' => 'synthetic@example.invalid']]);
		$this->assertSame(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());
		$this->assertStringNotContainsString('private server detail', json_encode($response->getData()));
	}
}
