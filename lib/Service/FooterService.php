<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\Handler\FooterHandler;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\Footer\FooterPolicy;
use OCA\Libresign\Service\Policy\Provider\Footer\FooterPolicyValue;

class FooterService {
	public function __construct(
		private PolicyService $policyService,
		private FooterHandler $footerHandler,
	) {
	}

	public function isDefaultTemplate(): bool {
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
			$this->syncFooterPolicyTemplate('', false);
			return;
		}

		$isProvidedTemplateEqualsDefault = $template === $defaultTemplateFromPolicy;

		if ($isProvidedTemplateEqualsDefault) {
			$this->syncFooterPolicyTemplate('', false);
		} else {
			$this->syncFooterPolicyTemplate($template, true);
		}
	}

	private function syncFooterPolicyTemplate(string $template, bool $customizeFooterTemplate): void {
		$currentPolicy = $this->getEffectiveFooterPolicy();
		$normalizedPolicy = FooterPolicyValue::normalize($currentPolicy);

		$normalizedPolicy['customizeFooterTemplate'] = $customizeFooterTemplate;
		$normalizedPolicy['footerTemplate'] = $customizeFooterTemplate ? $template : '';

		$allowChildOverride = $this->policyService->getSystemPolicy(FooterPolicy::KEY)?->isAllowChildOverride() ?? false;
		$this->policyService->saveSystem(
			FooterPolicy::KEY,
			FooterPolicyValue::encode($normalizedPolicy),
			$allowChildOverride,
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
