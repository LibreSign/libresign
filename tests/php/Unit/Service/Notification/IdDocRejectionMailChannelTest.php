<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Notification;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Service\MailService;
use OCA\Libresign\Service\Notification\IdDocRejectionPayload;
use OCA\Libresign\Service\Notification\MailChannel;
use OCA\Libresign\Service\Notification\MailPayload;
use OCA\Libresign\Service\Notification\NotificationMessage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IdDocRejectionMailChannelTest extends TestCase {
	private function request(): SignRequest {
		$request = new SignRequest();
		$request->setId(42);
		$request->setUuid('synthetic-request');
		$request->setDescription('Review document');
		return $request;
	}

	private function file(): File {
		$file = new File();
		$file->setId(31);
		$file->setName('Synthetic.pdf');
		$file->setUuid('synthetic-file');
		$file->setUserId('synthetic-owner');
		return $file;
	}

	#[DataProvider('rejectedDocumentRecipients')]
	public function testMailChannelDeliversRejectionReasonToExpectedSigner(
		bool $accountSigner,
		?string $recipientUserId,
		string $email,
	): void {
		$file = $this->file();
		$request = $this->request();
		$reason = 'Expired document — please replace the front and back scan.';
		$mail = $this->createMock(MailService::class);
		$mail->expects($this->once())->method('notifyIdentificationDocumentRejected')
			->with($request, $email, $file, $reason, $accountSigner, $recipientUserId);
		(new MailChannel($mail))->deliver(new NotificationMessage(
			'id-doc-rejected:31', 'id_doc_rejected', 'mail',
			$file, $request, $email, new IdDocRejectionPayload($reason, $accountSigner, $recipientUserId),
		));
	}

	public static function rejectedDocumentRecipients(): array {
		return [
			'external signer' => [false, null, 'external@example.test'],
			'Nextcloud account' => [true, 'synthetic-owner', 'owner@example.test'],
		];
	}

	public function testMailChannelRejectsIncompatiblePayloadInsteadOfSendingWrongNotification(): void {
		$mail = $this->createMock(MailService::class);
		$mail->expects($this->never())->method('notifyIdentificationDocumentRejected');
		$mail->expects($this->never())->method('notifyUnsignedUser');
		$this->expectException(\InvalidArgumentException::class);
		(new MailChannel($mail))->deliver(new NotificationMessage(
			'id-doc-rejected:31', 'id_doc_rejected', 'mail',
			$this->file(), $this->request(), 'external@example.test', new MailPayload(firstNotification: true),
		));
	}
}
