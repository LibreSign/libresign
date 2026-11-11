<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Integration\Service;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Service\IdDocRejectionEmailTemplateBuilder;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Mail\IMailer;
use OCP\Server;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Renders with the actual Nextcloud mail template; no SMTP is contacted. */
final class IdDocRejectionEmailRenderingTest extends TestCase {
	#[DataProvider('recipients')]
	public function testRenderedMessageKeepsTranslatedCopyAndEscapesFreeText(
		?string $userId,
		?string $userLanguage,
		string $prefix,
	): void {
		$users = $this->createMock(IUserManager::class);
		$factory = $this->createMock(IFactory::class);
		$urls = $this->createMock(IURLGenerator::class);
		if ($userId !== null) {
			$recipient = $this->createMock(IUser::class);
			$users->method('get')->with($userId)->willReturn($recipient);
			$factory->method('getUserLanguage')->with($recipient)->willReturn($userLanguage);
		} else {
			$users->expects($this->never())->method('get');
		}
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('getLanguageCode')->willReturn($userLanguage ?? 'en');
		$l10n->method('t')->willReturnCallback(
			static fn (string $message, array $params = []): string => $prefix . vsprintf($message, $params),
		);
		$factory->method('get')->with('libresign', $userLanguage)->willReturn($l10n);
		$urls->method('linkToRouteAbsolute')->willReturn('https://example.test/sign/synthetic/pdf');

		$request = new SignRequest();
		$request->setUuid('synthetic');
		$file = new File();
		$file->setName('ID <private>.pdf');
		$reason = "Unreadable <script>alert(1)</script> & try again.\nPlease upload a new copy.";
		$mailer = Server::get(IMailer::class);
		$builder = new IdDocRejectionEmailTemplateBuilder($users, $factory, $mailer, $urls);

		$template = $builder->build($request, $file, $reason, false, $userId);
		$html = $template->renderHtml();
		$text = $template->renderText();

		$this->assertSame($prefix . 'LibreSign: Identification document rejected', $template->renderSubject());
		$this->assertStringContainsString($prefix . 'Please replace your identification document', $text);
		$this->assertStringContainsString($prefix . 'The reviewer provided the following reason:', $text);
		$this->assertStringContainsString($reason, $text);
		$this->assertStringContainsString('https://example.test/sign/synthetic/pdf', $text);
		$this->assertStringContainsString('ID &lt;private&gt;.pdf', $html);
		$this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
		$this->assertStringNotContainsString('<script>', $html);
		$this->assertStringNotContainsString('ID <private>.pdf', $html);
	}

	public static function recipients(): array {
		return [
			'external signer with application default language' => [null, null, ''],
			'account signer with Portuguese translation' => ['signer', 'pt_BR', 'PT: '],
		];
	}
}
