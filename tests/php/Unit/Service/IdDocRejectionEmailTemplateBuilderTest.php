<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Service\IdDocRejectionEmailTemplateBuilder;
use OCA\Libresign\Tests\Unit\TestCase;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Mail\IEMailTemplate;
use OCP\Mail\IMailer;
use PHPUnit\Framework\Attributes\DataProvider;

final class IdDocRejectionEmailTemplateBuilderTest extends TestCase {
	#[DataProvider('recipientLocalesAndRoutes')]
	public function testComposesRecipientLocalizedEmailWithUnmodifiedReviewerReason(
		?string $userId,
		bool $userExists,
		?string $selectedLocale,
		bool $accountSigner,
		string $expectedUrl,
	): void {
		$users = $this->createMock(IUserManager::class);
		$factory = $this->createMock(IFactory::class);
		$mailer = $this->createMock(IMailer::class);
		$urls = $this->createMock(IURLGenerator::class);
		$recipient = $this->createMock(IUser::class);
		if ($userId !== null) {
			$users->expects($this->once())->method('get')->with($userId)
				->willReturn($userExists ? $recipient : null);
		} else {
			$users->expects($this->never())->method('get');
		}
		if ($userExists) {
			$factory->expects($this->once())->method('getUserLanguage')
				->with($recipient)->willReturn($selectedLocale);
		} else {
			$factory->expects($this->never())->method('getUserLanguage');
		}
		$l10n = $this->createMock(IL10N::class);
		$lang = $selectedLocale ?? 'en';
		$l10n->method('getLanguageCode')->willReturn($lang);
		$prefix = $lang === 'pt_BR' ? 'PT: ' : '';
		$l10n->method('t')->willReturnCallback(
			static fn (string $text, array $args = []): string => $prefix . vsprintf($text, $args),
		);
		$factory->expects($this->once())->method('get')->with('libresign', $selectedLocale)
			->willReturn($l10n);

		$template = $this->createMock(IEMailTemplate::class);
		$sentences = [];
		$template->method('addBodyText')->willReturnCallback(
			static function (string $text) use (&$sentences): void {
				$sentences[] = $text;
			},
		);
		$template->expects($this->once())->method('setLanguage')->with($lang);
		$template->expects($this->once())->method('setSubject')
			->with($prefix . 'LibreSign: Identification document rejected');
		$template->expects($this->once())->method('addHeading')
			->with($prefix . 'Please replace your identification document');
		$template->expects($this->once())->method('addBodyButton')
			->with($prefix . 'Replace identification document', $expectedUrl);
		$template->expects($this->once())->method('addHeader');
		$template->expects($this->once())->method('addFooter');
		$mailer->expects($this->once())->method('createEMailTemplate')
			->with('libresign.IdentificationDocumentRejected')->willReturn($template);
		$requestedRoutes = [];
		$urls->method('linkToRouteAbsolute')->willReturnCallback(
			static function (string $route, array $parameters = []) use (&$requestedRoutes): string {
				$requestedRoutes[] = [$route, $parameters];
				return $route === 'libresign.page.indexF'
					? 'https://example.test/apps/libresign/'
					: 'https://example.test/sign/request-42/pdf';
			},
		);

		$signRequest = new SignRequest();
		$signRequest->setUuid('request-42');
		$file = new File();
		$file->setName('Carteira <privada>.pdf');
		$reason = "The page is unreadable <img src=x onerror=alert(1)>\nPlease scan again.";
		$builder = new IdDocRejectionEmailTemplateBuilder($users, $factory, $mailer, $urls);

		$this->assertSame(
			$template,
			$builder->build($signRequest, $file, $reason, $accountSigner, $userId),
		);
		$this->assertSame($accountSigner
			? [['libresign.page.indexF', []]]
			: [['libresign.page.signFPath', ['uuid' => 'request-42', 'path' => 'pdf']]], $requestedRoutes);
		$this->assertSame([
			$prefix . 'Your identification document "Carteira <privada>.pdf" was not approved.',
			$prefix . 'The reviewer provided the following reason:',
			$reason,
			$prefix . 'Upload a replacement identification document to continue signing.',
		], $sentences);
	}

	public static function recipientLocalesAndRoutes(): array {
		return [
			'registered Portuguese signer' => [
				'signer', true, 'pt_BR', true, 'https://example.test/apps/libresign/',
			],
			'registered English signer' => [
				'signer', true, 'en', true, 'https://example.test/apps/libresign/',
			],
			'external signer uses default locale and guest link' => [
				null, false, null, false, 'https://example.test/sign/request-42/pdf',
			],
			'deleted account uses default locale and existing account link' => [
				'missing', false, null, true, 'https://example.test/apps/libresign/',
			],
		];
	}
}
