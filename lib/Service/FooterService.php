<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Handler\FooterHandler;
use OCA\Libresign\Service\Policy\Provider\Footer\FooterPolicy;
use OCA\Libresign\Service\Policy\Provider\Footer\FooterPolicyValue;
use OCP\IAppConfig;

class FooterService {
	public function __construct(
		private IAppConfig $appConfig,
		private FooterHandler $footerHandler,
	) {
	}

	public function isDefaultTemplate(): bool {
		$legacyCustomTemplate = $this->appConfig->getValueString(Application::APP_ID, 'footer_template', '');
		if ($legacyCustomTemplate !== '') {
			return false;
		}

		$footerPolicy = $this->getEffectiveFooterPolicy();
		return !$footerPolicy['customizeFooterTemplate'];
	}

	public function getTemplate(): string {
		return $this->footerHandler->getTemplate();
	}

	public function saveTemplate(string $template = ''): void {
		$currentPolicy = $this->getEffectiveFooterPolicy();
		$defaultTemplateFromPolicy = $currentPolicy['footerTemplate'];

		if (empty($template)) {
			$this->appConfig->deleteKey(Application::APP_ID, 'footer_template');
			$this->syncFooterPolicyTemplate('', false);
			return;
		}

		$isProvidedTemplateEqualsDefault = $template === $defaultTemplateFromPolicy;

		if ($isProvidedTemplateEqualsDefault) {
			$this->appConfig->deleteKey(Application::APP_ID, 'footer_template');
			$this->syncFooterPolicyTemplate('', false);
		} else {
			$this->appConfig->setValueString(Application::APP_ID, 'footer_template', $template);
			$this->syncFooterPolicyTemplate($template, true);
		}
	}

	private function syncFooterPolicyTemplate(string $template, bool $customizeFooterTemplate): void {
		$currentPolicy = $this->getEffectiveFooterPolicy();
		$defaultTemplate = $currentPolicy['footerTemplate'];

		$normalizedPolicy = FooterPolicyValue::normalize(
			$this->appConfig->getValueString(Application::APP_ID, FooterPolicy::KEY, ''),
			$defaultTemplate
		);

		$normalizedPolicy['customizeFooterTemplate'] = $customizeFooterTemplate;
		$normalizedPolicy['footerTemplate'] = $customizeFooterTemplate ? $template : '';

		$this->appConfig->setValueString(
			Application::APP_ID,
			FooterPolicy::KEY,
			FooterPolicyValue::encode($normalizedPolicy)
		);
	}

	private function getEffectiveFooterPolicy(): array {
		$policyJson = $this->footerHandler->getEffectiveFooterPolicyAsJson();
		return FooterPolicyValue::normalize($policyJson, '');
	}

	public function renderPreviewPdf(string $template = '', int $width = 595, int $height = 50, ?bool $writeQrcodeOnFooter = null): string {
		if (!empty($template)) {
			$this->saveTemplate($template);
		}

		$previewUuid = sprintf(
			'preview-%04x-%04x-%04x-%012x',
			random_int(0, 0xffff),
			random_int(0, 0xffff),
			random_int(0, 0xffff),
			random_int(0, 0xffffffffffff)
		);

		$handler = $this->footerHandler
			->setTemplateVar('uuid', $previewUuid)
			->setTemplateVar('signers', [
				[
					'displayName' => 'Preview Signer',
					'signed' => date('c'),
				],
			]);

		if ($writeQrcodeOnFooter !== null) {
			$handler->setWriteQrcodeOnFooterOverride($writeQrcodeOnFooter);
		}

		return $handler->getFooter([['w' => $width, 'h' => $height]], true);
	}

	public function getTemplateVariablesMetadata(): array {
		return $this->footerHandler->getTemplateVariablesMetadata();
	}
}
