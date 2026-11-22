<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Integration\Notification;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Notification\Notifier;
use OCP\IURLGenerator;
use OCP\Notification\IManager;
use OCP\Notification\UnknownNotificationException;
use OCP\Server;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Validates the actual Nextcloud notification shown to a rejected signer. */
final class IdDocRejectionNotificationTest extends TestCase {
	#[DataProvider('rejectionMessages')]
	public function testRejectedSignerSeesOriginalReasonAndReplacementLink(string $filename, string $reason): void {
		$notification = Server::get(IManager::class)->createNotification()
			->setApp(Application::APP_ID)
			->setUser('synthetic-signer')
			->setObject('identificationDocument', '1234')
			->setSubject('identification_document_rejected', [
				'documentName' => $filename,
				'reason' => $reason,
			]);

		$prepared = Server::get(Notifier::class)->prepare($notification, 'en');

		$this->assertSame($notification, $prepared);
		$this->assertSame(Application::APP_ID, $prepared->getApp());
		$this->assertSame('synthetic-signer', $prepared->getUser());
		$this->assertSame('Your identification document was rejected', $prepared->getParsedSubject());
		$this->assertSame('Replace "' . $filename . '". Reason: ' . $reason, $prepared->getParsedMessage());
		$this->assertSame(
			Server::get(IURLGenerator::class)->linkToRouteAbsolute('libresign.page.indexF'),
			$prepared->getLink(),
		);
	}

	public static function rejectionMessages(): array {
		return [
			'expired document' => ['Expired passport.pdf', 'The document has expired.'],
			'Unicode reviewer feedback' => ['Identificação.pdf', 'Documento ilegível — envie uma cópia mais nítida.'],
			'reviewer text preserved as submitted' => ['ID <synthetic>.pdf', 'Unreadable <scan> & missing signature'],
			'placeholder-shaped user text remains literal' => ['ID {reason}.pdf', 'The printed label says {document}'],
		];
	}

	public function testOtherAppCannotPresentLibreSignRejection(): void {
		$notification = Server::get(IManager::class)->createNotification()
			->setApp('some-other-app')
			->setUser('synthetic-signer')
			->setSubject('identification_document_rejected', ['reason' => 'Expired']);
		$this->expectException(UnknownNotificationException::class);
		Server::get(Notifier::class)->prepare($notification, 'en');
	}
}
