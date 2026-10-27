<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Notification;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\NotificationDelivery;
use OCA\Libresign\Db\SignRequest;
use OCP\Notification\IManager;
use OCP\Security\ICrypto;

/**
 * Versioned, encrypted transport handoff. Never serialize PHP objects, mail
 * credentials, verification codes, or arbitrary payload classes into the queue.
 * The encrypted snapshot also supports cancellation after a signer is removed.
 */
final class NotificationEnvelopeCodec {
	private const MAX_BODY_BYTES = 16384;

	public function __construct(
		private ICrypto $crypto,
		private IManager $notifications,
	) {
	}

	public function encode(NotificationMessage $message): string {
		$payload = match (true) {
			$message->payload instanceof MailPayload => [
				'type' => 'mail',
				'displayName' => $message->payload->displayName,
				'firstNotification' => $message->payload->firstNotification,
			],
			$message->payload instanceof GatewayPayload => [
				'type' => 'gateway',
				'gatewayName' => $message->payload->gatewayName,
				'body' => $message->payload->body,
				'extra' => $this->gatewayOptions($message->payload->extra),
			],
			$message->payload instanceof InAppPayload => $this->snapshotNotification($message->payload),
			$message->payload instanceof DataPayload => [
				'type' => 'data',
				'data' => $message->payload->data,
			],
			default => throw new \LogicException('Notification payload cannot be queued'),
		};
		$encoded = json_encode([
			'v' => 1,
			'sourceEventId' => $message->sourceEventId,
			'eventType' => $message->eventType,
			'channel' => $message->channel,
			'historyMethod' => $message->historyMethod,
			'file' => [
				'id' => $message->file->getId(),
				'uuid' => $message->file->getUuid(),
				'name' => $message->file->getName(),
				'userId' => $message->file->getUserId(),
				'nodeId' => $message->file->getNodeId(),
			],
			'signRequest' => [
				'id' => $message->signRequest->getId(),
				'fileId' => $message->signRequest->getFileId(),
				'uuid' => $message->signRequest->getUuid(),
				'displayName' => $message->signRequest->getDisplayName(),
				'description' => $message->signRequest->getDescription(),
				'participantRole' => $message->signRequest->getParticipantRole(),
			],
			'payload' => $payload,
		], JSON_THROW_ON_ERROR);
		if (strlen($encoded) > self::MAX_BODY_BYTES) {
			throw new \LogicException('Notification handoff exceeds the allowed size');
		}
		return $this->crypto->encrypt($encoded);
	}

	private function snapshotNotification(InAppPayload $payload): array {
		$notification = $payload->notification;
		return [
			'type' => 'nextcloud',
			'app' => $notification->getApp(),
			'user' => $notification->getUser(),
			'objectType' => $notification->getObjectType(),
			'objectId' => $notification->getObjectId(),
			'dateTime' => $notification->getDateTime()->format(DATE_ATOM),
			'subject' => $notification->getSubject(),
			'subjectParameters' => $notification->getSubjectParameters(),
		];
	}

	public function decode(NotificationDelivery $delivery): NotificationMessage {
		$encrypted = $delivery->getMessageEnc();
		if ($encrypted === null || $encrypted === '' || strlen($encrypted) > 131072) {
			throw new \UnexpectedValueException('Notification handoff is unavailable');
		}
		$data = json_decode($this->crypto->decrypt($encrypted), true, 32, JSON_THROW_ON_ERROR);
		if (!is_array($data) || ($data['v'] ?? null) !== 1
			|| ($data['channel'] ?? null) !== $delivery->getChannel()
			|| ($data['eventType'] ?? null) !== $delivery->getEventType()
			|| ($data['file']['id'] ?? null) !== $delivery->getFileId()
			|| ($data['signRequest']['id'] ?? null) !== $delivery->getSignRequestId()
		) {
			throw new \UnexpectedValueException('Notification handoff does not match its delivery');
		}
		$file = new File();
		$file->setId((int)$data['file']['id']);
		$file->setUuid((string)$data['file']['uuid']);
		$file->setName((string)$data['file']['name']);
		$file->setUserId((string)$data['file']['userId']);
		$file->setNodeId($data['file']['nodeId'] === null ? null : (int)$data['file']['nodeId']);

		$request = new SignRequest();
		$request->setId((int)$data['signRequest']['id']);
		$request->setFileId((int)$data['signRequest']['fileId']);
		$request->setUuid((string)$data['signRequest']['uuid']);
		$request->setDisplayName((string)$data['signRequest']['displayName']);
		$request->setDescription($data['signRequest']['description'] === null ? null : (string)$data['signRequest']['description']);
		$request->setParticipantRole((string)$data['signRequest']['participantRole']);

		$payload = $this->restorePayload($data['payload']);
		return new NotificationMessage(
			(string)$data['sourceEventId'],
			$delivery->getEventType(),
			$delivery->getChannel(),
			$file,
			$request,
			$this->crypto->decrypt($delivery->getRecipientEnc()),
			$payload,
			isset($data['historyMethod']) ? (string)$data['historyMethod'] : null,
		);
	}

	/**
	 * Only approved structural options may be stored in the encrypted queue.
	 * Never persist arbitrary gateway extras (e.g. authentication codes/tokens).
	 * @return array<string, mixed>
	 */
	private function gatewayOptions(mixed $extra): array {
		if ($extra === []) {
			return [];
		}
		if (!is_array($extra)
			|| array_diff(array_keys($extra), ['body_parameters', 'expected_body_parameter_count']) !== []
			|| !isset($extra['body_parameters'])
			|| !is_array($extra['body_parameters'])
			|| !array_is_list($extra['body_parameters'])
			|| $extra['body_parameters'] === []) {
			throw new \UnexpectedValueException('Invalid queued gateway options');
		}
		foreach ($extra['body_parameters'] as $value) {
			if (!is_string($value) || trim($value) === '') {
				throw new \UnexpectedValueException('Invalid queued gateway body parameters');
			}
		}
		if (array_key_exists('expected_body_parameter_count', $extra)
			&& (!is_int($extra['expected_body_parameter_count'])
				|| $extra['expected_body_parameter_count'] !== count($extra['body_parameters']))) {
			throw new \UnexpectedValueException('Invalid queued gateway body parameter count');
		}
		return $extra;
	}

	private function restorePayload(array $payload): NotificationPayload {
		return match ($payload['type'] ?? null) {
			'mail' => new MailPayload(
				isset($payload['displayName']) ? (string)$payload['displayName'] : null,
				$payload['firstNotification'] === true,
			),
			'gateway' => new GatewayPayload(
				(string)$payload['gatewayName'],
				(string)$payload['body'],
				$this->gatewayOptions($payload['extra'] ?? []),
			),
			'data' => new DataPayload(
				is_array($payload['data'] ?? null)
					? $payload['data']
					: throw new \UnexpectedValueException('Invalid extension notification data'),
			),
			'nextcloud' => new InAppPayload(
				$this->notifications->createNotification()
					->setApp((string)$payload['app'])
					->setUser((string)$payload['user'])
					->setObject((string)$payload['objectType'], (string)$payload['objectId'])
					->setDateTime(new \DateTime((string)$payload['dateTime']))
					->setSubject((string)$payload['subject'], $payload['subjectParameters'] ?? []),
			),
			default => throw new \UnexpectedValueException('Unsupported queued notification payload'),
		};
	}
}
