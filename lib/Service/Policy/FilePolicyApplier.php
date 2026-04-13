<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\Policy\Provider\DocMdp\FilePolicy\DocMdpFilePolicyApplier;
use OCA\Libresign\Service\Policy\Provider\Footer\FilePolicy\FooterFilePolicyApplier;
use OCA\Libresign\Service\Policy\Provider\Signature\FilePolicy\SignatureFlowFilePolicyApplier;
use OCP\IL10N;

class FilePolicyApplier {
	private readonly SignatureFlowFilePolicyApplier $signatureFlowApplier;
	private readonly DocMdpFilePolicyApplier $docMdpApplier;
	private readonly FooterFilePolicyApplier $footerApplier;

	public function __construct(
		private readonly PolicyService $policyService,
		private readonly FileService $fileService,
		private readonly IL10N $l10n,
	) {
		$this->signatureFlowApplier = new SignatureFlowFilePolicyApplier($this->policyService, $this->fileService, $this->l10n);
		$this->docMdpApplier = new DocMdpFilePolicyApplier($this->policyService, $this->fileService);
		$this->footerApplier = new FooterFilePolicyApplier($this->policyService, $this->fileService, $this->l10n);
	}

	/**
	 * Apply all policies to a freshly built FileEntity before the first insert.
	 */
	public function applyAll(FileEntity $file, array $data): void {
		$this->applySignatureFlow($file, $data);
		$this->applyDocMdpLevel($file, $data);
		$this->applyFooterPolicy($file, $data);
	}

	/**
	 * Re-evaluate and persist signature_flow + docmdp on an existing file.
	 * Use this when updating a file located by UUID.
	 */
	public function syncCoreFlowPolicies(FileEntity $file, array $data): void {
		$this->syncSignatureFlow($file, $data);
		$this->syncDocMdpLevel($file, $data);
	}

	/**
	 * Re-evaluate and persist all three policies on an existing file.
	 * Use this when updating a file located by node ID.
	 */
	public function syncAllPolicies(FileEntity $file, array $data): void {
		$this->syncSignatureFlow($file, $data);
		$this->syncDocMdpLevel($file, $data);
		$this->syncFooterPolicy($file, $data);
	}

	private function applySignatureFlow(FileEntity $file, array $data): void {
		$this->signatureFlowApplier->apply($file, $data);
	}

	private function syncSignatureFlow(FileEntity $file, array $data): void {
		$this->signatureFlowApplier->sync($file, $data);
	}

	private function applyDocMdpLevel(FileEntity $file, array $data): void {
		$this->docMdpApplier->apply($file, $data);
	}

	private function syncDocMdpLevel(FileEntity $file, array $data): void {
		$this->docMdpApplier->sync($file, $data);
	}

	private function applyFooterPolicy(FileEntity $file, array $data): void {
		$this->footerApplier->apply($file, $data);
	}

	private function syncFooterPolicy(FileEntity $file, array $data): void {
		$this->footerApplier->sync($file, $data);
	}
}
