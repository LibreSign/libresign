<?php

declare(strict_types=1);

namespace OCA\Libresign\Tests\Unit\Service;

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\SignatureTextTemplate;
use OCP\L10N\IFactory as IL10NFactory;

final class SignatureTextTemplateTest extends \OCA\Libresign\Tests\Unit\TestCase {
	public function testTranslatedTemplateIncludesMetadataPlaceholdersWhenEnabled(): void {
		$l10n = \OCP\Server::get(IL10NFactory::class)->get(Application::APP_ID);

		$template = SignatureTextTemplate::translated($l10n, true);

		$this->assertStringContainsString('{{SignerCommonName}}', $template);
		$this->assertStringContainsString('{{IssuerCommonName}}', $template);
		$this->assertStringContainsString('{{ServerSignatureDate}}', $template);
		$this->assertStringContainsString('{{SignerIP}}', $template);
		$this->assertStringContainsString('{{SignerUserAgent}}', $template);
	}

	public function testTranslatedTemplateOmitsMetadataPlaceholdersWhenDisabled(): void {
		$l10n = \OCP\Server::get(IL10NFactory::class)->get(Application::APP_ID);

		$template = SignatureTextTemplate::translated($l10n, false);

		$this->assertStringContainsString('{{SignerCommonName}}', $template);
		$this->assertStringContainsString('{{IssuerCommonName}}', $template);
		$this->assertStringContainsString('{{ServerSignatureDate}}', $template);
		$this->assertStringNotContainsString('{{SignerIP}}', $template);
		$this->assertStringNotContainsString('{{SignerUserAgent}}', $template);
	}
}
