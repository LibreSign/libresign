<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Listener;

use OCA\Libresign\Enum\CRLReason;
use OCA\Libresign\Events\SignedEvent;
use OCA\Libresign\Service\Crl\CrlService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Override;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IEventListener<SignedEvent>
 */
class RevokeClickToSignCertificateListener implements IEventListener {
	public function __construct(
		private CrlService $crlService,
		private LoggerInterface $logger,
	) {
	}

	#[Override]
	public function handle(Event $event): void {
		if (!($event instanceof SignedEvent)) {
			return;
		}

		if (!$event->wasSignedWithoutPassword()) {
			return;
		}

		$serialNumber = $event->getCertificateSerialNumber();
		if ($serialNumber === null || $serialNumber === '') {
			$this->logger->warning('Unable to revoke click-to-sign certificate: serial number not found');
			return;
		}

		$success = $this->crlService->revokeCertificate(
			$serialNumber,
			CRLReason::SUPERSEDED,
			'Temporary certificate issued for click-to-sign. Automatically revoked after document signing.',
			'system'
		);

		if ($success) {
			$this->logger->debug('Successfully revoked click-to-sign certificate', [
				'serial' => $serialNumber,
				'signRequestId' => $event->getSignRequest()->getId(),
			]);
		} else {
			$this->logger->warning('Failed to revoke click-to-sign certificate', [
				'serial' => $serialNumber,
				'signRequestId' => $event->getSignRequest()->getId(),
			]);
		}
	}
}
