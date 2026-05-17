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

	public function getDefaultTemplate(): string {
		return $this->footerHandler->getDefaultTemplate();
	}

	/** @return array{preview_width: int, preview_height: int, preview_zoom: int} */
	public function getPreviewSettings(): array {
		$footerPolicy = $this->getEffectiveFooterPolicy();

		return [
			'preview_width' => (int)$footerPolicy['previewWidth'],
			'preview_height' => (int)$footerPolicy['previewHeight'],
			'preview_zoom' => (int)$footerPolicy['previewZoom'],
		];
	}

	public function saveTemplate(string $template = '', ?int $previewWidth = null, ?int $previewHeight = null): void {
		$defaultTemplate = $this->footerHandler->getDefaultTemplate();
		$currentPolicy = $this->getEffectiveFooterPolicy();
		$normalizedPolicy = FooterPolicyValue::normalize($currentPolicy);

		if ($previewWidth !== null) {
			$normalizedPolicy['previewWidth'] = $previewWidth;
		}

		if ($previewHeight !== null) {
			$normalizedPolicy['previewHeight'] = $previewHeight;
		}

		if (empty($template)) {
			$normalizedPolicy['customizeFooterTemplate'] = false;
			$normalizedPolicy['footerTemplate'] = '';
			$this->saveSystemFooterPolicy($normalizedPolicy);
			return;
		}

		$isProvidedTemplateEqualsDefault = $template === $defaultTemplate;

		if ($isProvidedTemplateEqualsDefault) {
			$normalizedPolicy['customizeFooterTemplate'] = false;
			$normalizedPolicy['footerTemplate'] = '';
		} else {
			$normalizedPolicy['customizeFooterTemplate'] = true;
			$normalizedPolicy['footerTemplate'] = $template;
		}

		$this->saveSystemFooterPolicy($normalizedPolicy);
	}

	/** @param array{enabled: bool, writeQrcodeOnFooter: bool, validationSite: string, customizeFooterTemplate: bool, footerTemplate: string, previewWidth: int, previewHeight: int, previewZoom: int} $normalizedPolicy */
	private function saveSystemFooterPolicy(array $normalizedPolicy): void {
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
		$previewUuid = sprintf(
			'preview-%04x-%04x-%04x-%012x',
			random_int(0, 0xffff),
			random_int(0, 0xffff),
			random_int(0, 0xffff),
			random_int(0, 0xffffffffffff)
		);

		$handler = $this->footerHandler
			->setTemplateOverride($template !== '' ? $template : null)
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

	public function isPreviewAllowed(): bool {
		$footerPolicy = $this->getEffectiveFooterPolicy();
		return (bool)$footerPolicy['enabled'];
	}
}
