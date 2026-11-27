<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\BackgroundJob\DeliverNotification;
use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Enum\NotificationDeliveryState;
use OCA\Libresign\Service\Notification\MailChannel;
use OCA\Libresign\Service\Notification\MailPayload;
use OCA\Libresign\Service\Notification\NotificationDeliveryRegistrar;
use OCA\Libresign\Service\Notification\NotificationEnvelopeCodec;
use OCA\Libresign\Service\Notification\NotificationMessage;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\SignedCopyMail\SignedCopyMailPolicy;
use OCA\Libresign\Service\Policy\Provider\SignedCopyMail\SignedCopyMailPolicyValue;
use OCP\BackgroundJob\IJobList;
use OCP\IUser;
use OCP\IUserManager;

/**
 * Queues the signed copy mails of a completed request in the notification
 * delivery outbox: one delivery per recipient. A signer receives the
 * documents they signed and was chosen for, an archive address receives
 * every document of the request.
 *
 * The delivery key is derived from the request and the recipient, so a
 * repeated completion never queues a second mail for the same recipient.
 */
class SignedCopyMailService {
	public const EVENT_TYPE = 'signed_copy';
	/** Sign request metadata flag set by the requester for each signer. */
	public const METADATA_KEY = 'sendSignedCopy';

	public function __construct(
		private FileMapper $fileMapper,
		private SignRequestMapper $signRequestMapper,
		private IdentifyMethodMapper $identifyMethodMapper,
		private IUserManager $userManager,
		private PolicyService $policyService,
		private NotificationDeliveryRegistrar $registrar,
		private NotificationEnvelopeCodec $codec,
		private MailChannel $mailChannel,
		private IJobList $jobList,
	) {
	}

	/** @return int Number of deliveries queued by this call. */
	public function queue(FileEntity $file): int {
		if ($file->getStatus() !== FileStatus::SIGNED->value || $file->hasParent()) {
			return 0;
		}
		$policy = $this->resolvePolicy($file);
		if (!$policy['enabled']) {
			return 0;
		}

		$documents = $file->isEnvelope()
			? $this->fileMapper->getChildrenFiles($file->getId())
			: [$file];

		$queued = 0;
		foreach ($this->collectRecipients($documents, $policy['archive_addresses']) as $recipient) {
			$message = new NotificationMessage(
				// One stable source per request: the delivery key then only
				// differs by recipient, which makes a repeated completion a no-op.
				self::EVENT_TYPE . ':' . $file->getUuid(),
				self::EVENT_TYPE,
				'mail',
				$file,
				$recipient['signRequest'],
				$recipient['email'],
				new MailPayload($recipient['displayName'], documentIds: $recipient['documentIds']),
			);
			$delivery = $this->registrar->register($message, $this->mailChannel, $this->codec->encode($message));
			if ($delivery->getState() !== NotificationDeliveryState::PENDING->value) {
				continue;
			}
			$argument = ['id' => $delivery->getId()];
			if (!$this->jobList->has(DeliverNotification::class, $argument)) {
				$this->jobList->add(DeliverNotification::class, $argument);
				$queued++;
			}
		}
		return $queued;
	}

	/** @return array{enabled: bool, archive_addresses: list<string>} */
	private function resolvePolicy(FileEntity $file): array {
		$userId = $file->getUserId();
		$value = $this->policyService
			->resolveForUserId(SignedCopyMailPolicy::KEY, $userId !== '' ? $userId : null)
			->getEffectiveValue();
		return SignedCopyMailPolicyValue::normalize($value);
	}

	/**
	 * @param FileEntity[] $documents
	 * @param list<string> $archiveAddresses
	 * @return array<string, array{email: string, displayName: string, signRequest: SignRequest, documentIds: list<int>}>
	 */
	private function collectRecipients(array $documents, array $archiveAddresses): array {
		$recipients = [];
		foreach ($documents as $document) {
			foreach ($this->signRequestMapper->getByFileId($document->getId()) as $signRequest) {
				if (!$this->wantsSignedCopy($signRequest)) {
					continue;
				}
				$email = $this->resolveEmail($signRequest);
				if ($email !== null) {
					$this->addRecipient($recipients, $email, $signRequest->getDisplayName(), $signRequest, $document->getId());
				}
			}
		}
		foreach ($archiveAddresses as $address) {
			foreach ($documents as $document) {
				// An archive address belongs to no sign request.
				$this->addRecipient($recipients, $address, '', new SignRequest(), $document->getId());
			}
		}
		return $recipients;
	}

	private function wantsSignedCopy(SignRequest $signRequest): bool {
		$metadata = $signRequest->getMetadata() ?? [];
		return ($metadata[self::METADATA_KEY] ?? false) === true
			&& $signRequest->getSigned() !== null;
	}

	private function resolveEmail(SignRequest $signRequest): ?string {
		foreach ($this->identifyMethodMapper->getIdentifyMethodsFromSignRequestId($signRequest->getId()) as $identifyMethod) {
			if ($identifyMethod->isDeletedAccount()) {
				continue;
			}
			$value = $identifyMethod->getIdentifierValue();
			if ($identifyMethod->getIdentifierKey() === IdentifyMethodService::IDENTIFY_ACCOUNT) {
				$user = $this->userManager->get($value);
				$value = $user instanceof IUser ? (string)$user->getEMailAddress() : '';
			}
			if (filter_var($value, FILTER_VALIDATE_EMAIL) !== false) {
				return $value;
			}
		}
		return null;
	}

	/**
	 * @param array<string, array{email: string, displayName: string, signRequest: SignRequest, documentIds: list<int>}> $recipients
	 */
	private function addRecipient(array &$recipients, string $email, string $displayName, SignRequest $signRequest, int $documentId): void {
		$key = strtolower($email);
		$recipients[$key] ??= ['email' => $email, 'displayName' => $displayName, 'signRequest' => $signRequest, 'documentIds' => []];
		if (!in_array($documentId, $recipients[$key]['documentIds'], true)) {
			$recipients[$key]['documentIds'][] = $documentId;
		}
	}
}
