<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Handler\FooterHandler;
use OCP\IAppConfig;

class FooterService {
	public function __construct(
		private IAppConfig $appConfig,
		private FooterHandler $footerHandler,
	) {
	}

	public function isDefaultTemplate(): bool {
		$customTemplate = $this->appConfig->getValueString(Application::APP_ID, 'footer_template', '');
		return empty($customTemplate);
	}

	public function getTemplate(): string {
		return $this->footerHandler->getTemplate();
	}

	public function saveTemplate(string $template = ''): void {
		if (empty($template)) {
			$this->appConfig->deleteKey(Application::APP_ID, 'footer_template');
		} else {
			$this->appConfig->setValueString(Application::APP_ID, 'footer_template', $template);
		}
	}

	public function renderPreviewPdf(string $template = '', int $width = 595, int $height = 50): string {
		if (!empty($template)) {
			$this->saveTemplate($template);
		}

		return $this->footerHandler
			->setTemplateVar('uuid', 'preview-' . bin2hex(random_bytes(8)))
			->setTemplateVar('signers', [
				[
					'displayName' => 'Preview Signer',
					'signed' => date('c'),
				],
			])
			->getFooter([['w' => $width, 'h' => $height]]);
	}
}
