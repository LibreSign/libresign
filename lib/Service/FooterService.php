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
			return;
		}

		if ($template === $this->footerHandler->getDefaultTemplate()) {
			$this->appConfig->deleteKey(Application::APP_ID, 'footer_template');
		} else {
			$this->appConfig->setValueString(Application::APP_ID, 'footer_template', $template);
		}
	}

	public function renderPreviewPdf(string $template = '', int $width = 595, int $height = 50): string {
		if (!empty($template)) {
			$this->saveTemplate($template);
		}

		// Generate a realistic UUID format for preview (36 chars with hyphens, same as real UUIDs)
		// This ensures QR code size matches the final document
		$previewUuid = sprintf(
			'preview-%04x-%04x-%04x-%012x',
			random_int(0, 0xffff),
			random_int(0, 0xffff),
			random_int(0, 0xffff),
			random_int(0, 0xffffffffffff)
		);

		return $this->footerHandler
			->setTemplateVar('uuid', $previewUuid)
			->setTemplateVar('signers', [
				[
					'displayName' => 'Preview Signer',
					'signed' => date('c'),
				],
			])
			->getFooter([['w' => $width, 'h' => $height]]);
	}
}
