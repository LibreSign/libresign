<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Listener;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Enum\CRLReason;
use OCA\Libresign\Events\SignedEvent;
use OCA\Libresign\Listener\RevokeClickToSignCertificateListener;
use OCA\Libresign\Service\Crl\CrlService;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCP\EventDispatcher\Event;
use OCP\Files\File;
use OCP\IUser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class RevokeClickToSignCertificateListenerTest extends TestCase {
	private CrlService&MockObject $crlService;
	private LoggerInterface&MockObject $logger;
	private RevokeClickToSignCertificateListener $listener;

	protected function setUp(): void {
		parent::setUp();

		$this->crlService = $this->createMock(CrlService::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->listener = new RevokeClickToSignCertificateListener(
			$this->crlService,
			$this->logger
		);
	}

	public static function provideScenariosThatShouldNotRevokeCertificate(): array {
		return [
			'non-SignedEvent instance' => [
				null,
				false,
			],
			'signed with password (permanent certificate)' => [
				['signedWithoutPassword' => false, 'certificateSerialHex' => 'ABC123'],
				false,
			],
			'missing serial number in certificate info' => [
				['signedWithoutPassword' => true, 'certificateSerialHex' => null],
				true,
			],
		];
	}

	#[DataProvider('provideScenariosThatShouldNotRevokeCertificate')]
	public function testDoNotRevokeCertificate($eventConfig, bool $expectWarning): void {
		if ($eventConfig === null) {
			$event = $this->createMock(Event::class);
		} else {
			$event = $this->createSignedEvent(
				$eventConfig['signedWithoutPassword'],
				$eventConfig['certificateSerialHex']
			);
		}

		if ($expectWarning) {
			$this->logger->expects($this->once())->method('warning');
		}

		$this->crlService->expects($this->never())->method('revokeCertificate');

		$this->listener->handle($event);
	}

	public function testRevokeClickToSignCertificateUsingSupersededReasonCode(): void {
		$serialNumber = '1A2B3C4D5E6F';
		$event = $this->createSignedEvent(true, $serialNumber);

		$this->crlService->expects($this->once())
			->method('revokeCertificate')
			->with(
				$serialNumber,
				CRLReason::SUPERSEDED,
				$this->anything(),
				$this->anything()
			)
			->willReturn(true);

		$this->listener->handle($event);
	}

	public function testRevocationMustBeAttributedToSystemUser(): void {
		$event = $this->createSignedEvent(true, 'ABC123');

		$this->crlService->expects($this->once())
			->method('revokeCertificate')
			->with(
				$this->anything(),
				$this->anything(),
				$this->anything(),
				'system'
			)
			->willReturn(true);

		$this->listener->handle($event);
	}

	public function testRevocationMessageMustExplainTemporaryCertificateForAuditPurposes(): void {
		$event = $this->createSignedEvent(true, 'XYZ789');

		$this->crlService->expects($this->once())
			->method('revokeCertificate')
			->with(
				$this->anything(),
				$this->anything(),
				$this->logicalAnd(
					$this->stringContains('Temporary certificate'),
					$this->stringContains('click-to-sign'),
					$this->stringContains('revoked after document signing')
				),
				$this->anything()
			)
			->willReturn(true);

		$this->listener->handle($event);
	}

	public function testLogWarningAndContinueOperationWhenRevocationFails(): void {
		$event = $this->createSignedEvent(true, 'FAILED_REV');

		$this->crlService->method('revokeCertificate')->willReturn(false);

		$this->logger->expects($this->once())
			->method('warning')
			->with('Failed to revoke click-to-sign certificate', $this->anything());

		$this->listener->handle($event);
	}

	public function testLogDebugWhenRevocationSucceeds(): void {
		$serialNumber = 'SUCCESS_123';
		$event = $this->createSignedEvent(true, $serialNumber);

		$this->crlService->method('revokeCertificate')->willReturn(true);

		$this->logger->expects($this->once())
			->method('debug')
			->with(
				'Successfully revoked click-to-sign certificate',
				$this->callback(fn ($ctx) => $ctx['serial'] === $serialNumber && isset($ctx['signRequestId']))
			);

		$this->listener->handle($event);
	}

	private function createSignedEvent(bool $signedWithoutPassword, ?string $certificateSerialHex): SignedEvent {
		$signRequest = new SignRequest();
		$signRequest->setId(123);

		$libreSignFile = new FileEntity();
		$identifyMethod = $this->createMock(IIdentifyMethod::class);
		$user = $this->createMock(IUser::class);
		$signedFile = $this->createMock(File::class);

		return new SignedEvent(
			$signRequest,
			$libreSignFile,
			$identifyMethod,
			$user,
			$signedFile,
			$signedWithoutPassword,
			$certificateSerialHex
		);
	}
}
