<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Helper\ValidateHelper;
use OCP\IL10N;
use OCP\IUser;

final class RequestSignatureWorkflowService {
	public function __construct(
		private IL10N $l10n,
		private RequestSignatureService $requestSignatureService,
		private ValidateHelper $validateHelper,
		private FileMapper $fileMapper,
	) {
	}

	/**
	 * @param array<string, mixed>|null $policy
	 * @return array{policyOverrides: array<string, mixed>, policyActiveContext: array<string, mixed>|null}
	 */
	public function resolvePolicyPayload(?array $policy): array {
		return [
			'policyOverrides' => $this->extractPolicyOverrides($policy),
			'policyActiveContext' => $this->extractPolicyActiveContext($policy),
		];
	}

	/**
	 * @param array<string, mixed> $file
	 * @param list<array<string, mixed>> $files
	 * @param list<array<string, mixed>> $signers
	 * @param array<string, mixed> $settings
	 * @param array<string, mixed>|null $policy
	 * @param list<array<string, mixed>>|null $visibleElements
	 * @return array{file: FileEntity, children: list<FileEntity>}
	 * @throws LibresignException
	 */
	public function createRequest(
		?IUser $user,
		array $file,
		array $files,
		string $name,
		array $settings,
		array $signers,
		?int $status,
		?string $callback,
		?array $policy = null,
		?array $visibleElements = null,
	): array {
		if ($file === [] && $files === []) {
			throw new LibresignException($this->l10n->t('File or files parameter is required'));
		}

		$resolvedPolicy = $this->resolvePolicyPayload($policy);
		$data = [
			'file' => $file,
			'name' => $name,
			'signers' => $signers,
			'callback' => $callback,
			'userManager' => $user,
			'policyOverrides' => $resolvedPolicy['policyOverrides'],
			'policyActiveContext' => $resolvedPolicy['policyActiveContext'],
			'settings' => $settings !== [] ? $settings : ($file['settings'] ?? []),
		];

		if ($status !== null) {
			$data['status'] = $status;
		}

		if ($files !== []) {
			$data['files'] = $files;
		}

		if ($visibleElements !== null) {
			$data['visibleElements'] = $visibleElements;
		}

		$this->requestSignatureService->validateNewRequestToFile($data);

		if ($files !== []) {
			$result = $this->requestSignatureService->saveFiles($data);
			return [
				'file' => $result['file'],
				'children' => $result['children'] ?? [],
			];
		}

		$fileEntity = $this->requestSignatureService->save($data);

		return [
			'file' => $fileEntity,
			'children' => $this->loadChildFilesIfEnvelope($fileEntity),
		];
	}

	/**
	 * @param list<array<string, mixed>> $signers
	 * @param array<string, mixed> $file
	 * @param array<string, mixed>|null $policy
	 * @param list<array<string, mixed>>|null $visibleElements
	 * @param array<string, mixed> $settings
	 * @return array{file: FileEntity, children: list<FileEntity>}
	 */
	public function updateExistingRequest(
		?IUser $user,
		array $signers,
		string $uuid,
		?array $visibleElements,
		array $file,
		?int $status,
		?array $policy = null,
		?string $name = null,
		array $settings = [],
	): array {
		$resolvedPolicy = $this->resolvePolicyPayload($policy);
		$data = [
			'uuid' => $uuid,
			'file' => $file,
			'signers' => $signers,
			'userManager' => $user,
			'visibleElements' => $visibleElements,
			'policyOverrides' => $resolvedPolicy['policyOverrides'],
			'policyActiveContext' => $resolvedPolicy['policyActiveContext'],
			'name' => $name,
			'settings' => $settings,
		];
		if ($status !== null) {
			$data['status'] = $status;
		}

		$this->validateHelper->validateExistingFile($data);
		$this->validateHelper->validateFileStatus($data);
		$this->validateHelper->validateIdentifySigners($data);
		if (!empty($visibleElements)) {
			$this->validateHelper->validateVisibleElements($visibleElements, ValidateHelper::TYPE_VISIBLE_ELEMENT_PDF);
		}
		$fileEntity = $this->requestSignatureService->save($data);

		return [
			'file' => $fileEntity,
			'children' => $this->loadChildFilesIfEnvelope($fileEntity),
		];
	}

	/** @return list<FileEntity> */
	private function loadChildFilesIfEnvelope(FileEntity $fileEntity): array {
		return $fileEntity->getParentFileId() === null || $fileEntity->isEnvelope()
			? $this->fileMapper->getChildrenFiles($fileEntity->getId())
			: [];
	}

	/** @param array<string, mixed>|null $policy
	 * @return array<string, mixed>
	 */
	private function extractPolicyOverrides(?array $policy): array {
		$overrides = $policy['overrides'] ?? null;

		return is_array($overrides) ? $overrides : [];
	}

	/** @param array<string, mixed>|null $policy
	 * @return array<string, mixed>|null
	 */
	private function extractPolicyActiveContext(?array $policy): ?array {
		$activeContext = $policy['activeContext'] ?? null;

		return is_array($activeContext) ? $activeContext : null;
	}
}
