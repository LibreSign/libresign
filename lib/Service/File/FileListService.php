<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\File;

use DateTimeInterface;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdentifyMethod;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\SignatureFlow;
use OCA\Libresign\ResponseDefinitions;
use OCA\Libresign\Service\FileElementService;
use OCA\Libresign\Service\IdentifyMethodService;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;

/**
 * @psalm-import-type LibresignVisibleElement from ResponseDefinitions
 * @psalm-import-type LibresignFileDetail from ResponseDefinitions
 * @psalm-import-type LibresignPagination from ResponseDefinitions
 */
class FileListService {
	public function __construct(
		private SignRequestMapper $signRequestMapper,
		private IdentifyMethodService $identifyMethodService,
		private FileElementService $fileElementService,
		private FileMapper $fileMapper,
		private IURLGenerator $urlGenerator,
		private IAppConfig $appConfig,
		private IL10N $l10n,
		private IUserManager $userManager,
	) {
	}

	/**
	 * @return array{data: list<LibresignFileDetail>, pagination: LibresignPagination}
	 */
	public function listAssociatedFilesOfSignFlow(
		IUser $user,
		$page = null,
		$length = null,
		array $filter = [],
		array $sort = [],
	): array {
		$page ??= 1;
		$length ??= (int)$this->appConfig->getValueInt(Application::APP_ID, 'length_of_page', 100);

		$return = $this->signRequestMapper->getFilesAssociatedFilesWithMe(
			$user,
			$filter,
			$page,
			$length,
			$sort,
		);

		$signers = $this->signRequestMapper->getByMultipleFileId(array_map(fn (File $file) => $file->getId(), $return['data']));
		$identifyMethods = $this->signRequestMapper->getIdentifyMethodsFromSigners($signers);
		$visibleElements = $this->signRequestMapper->getVisibleElementsFromSigners($signers);
		$return['data'] = $this->associateAllAndFormat($user, $return['data'], $signers, $identifyMethods, $visibleElements);

		$return['pagination']->setRouteName('ocs.libresign.File.list');
		return [
			'data' => $return['data'],
			'pagination' => $return['pagination']->getPagination($page, $length, $filter),
		];
	}

	public function formatSingleFile(IUser $user, File $file): array {
		$signers = $this->signRequestMapper->getByMultipleFileId([$file->getId()]);
		$identifyMethods = $this->signRequestMapper->getIdentifyMethodsFromSigners($signers);
		$visibleElements = $this->signRequestMapper->getVisibleElementsFromSigners($signers);

		return $this->formatSingleFileData($file, $signers, $identifyMethods, $visibleElements, $user);
	}

	/**
	 * @return list<LibresignFileDetail>
	 */
	private function associateAllAndFormat(
		IUser $user,
		array $files,
		array $signers,
		array $identifyMethods,
		array $visibleElements,
	): array {
		$formattedFiles = [];
		foreach ($files as $file) {
			$fileSigners = array_filter($signers, fn ($signer) => $signer->getFileId() === $file->getId());
			$formattedFiles[] = $this->formatSingleFileData($file, $fileSigners, $identifyMethods, $visibleElements, $user);
		}
		return $formattedFiles;
	}

	/**
	 * Format a single file with its signers, identifyMethods and visibleElements.
	 * Core formatting used by list and single file operations.
	 *
	 * @return LibresignFileDetail
	 * @psalm-suppress MoreSpecificReturnType
	 */
	private function formatSingleFileData(
		File $fileEntity,
		array $signers,
		array $identifyMethods,
		array $visibleElements,
		IUser $user,
	): array {
		$file = [
			'id' => $fileEntity->getId(),
			'nodeId' => $fileEntity->getNodeId(),
			'uuid' => $fileEntity->getUuid(),
			'name' => $fileEntity->getName(),
			'status' => $fileEntity->getStatus(),
			'metadata' => $fileEntity->getMetadata(),
			'createdAt' => $fileEntity->getCreatedAt(),
			'userId' => $fileEntity->getUserId(),
			'signatureFlow' => $fileEntity->getSignatureFlow(),
			'nodeType' => $fileEntity->getNodeType(),
		];
		$file['signatureFlow'] = SignatureFlow::fromNumeric($file['signatureFlow'])->value;
		$file['statusText'] = $this->fileMapper->getTextOfStatus($file['status']);
		$file['requested_by'] = [
			'userId' => $file['userId'],
			'displayName' => $this->userManager->get($file['userId'])?->getDisplayName(),
		];
		$file['created_at'] = $file['createdAt']->setTimezone(new \DateTimeZone('UTC'))->format(DateTimeInterface::ATOM);
		$file['file'] = $this->urlGenerator->linkToRoute('libresign.page.getPdf', ['uuid' => $file['uuid']]);

		if ($file['nodeType'] === 'envelope') {
			$file['filesCount'] = $file['metadata']['filesCount'] ?? 0;
			$file['files'] = [];
		} else {
			$file['filesCount'] = 1;
			$file['files'] = [[
				'nodeId' => $file['nodeId'],
				'uuid' => $file['uuid'],
				'name' => $file['name'],
				'status' => $file['status'],
				'statusText' => $file['statusText'],
			]];
		}

		// Remove raw fields not needed in response
		unset($file['userId'], $file['createdAt']);

		$file['signers'] = [];
		foreach ($signers as $signer) {
			if ($signer->getFileId() !== $fileEntity->getId()) {
				continue;
			}

			$identifyMethodsOfSigner = $identifyMethods[$signer->getId()] ?? [];
			$data = [
				'email' => array_reduce($identifyMethodsOfSigner, function (string $carry, IdentifyMethod $identifyMethod): string {
					if ($identifyMethod->getIdentifierKey() === IdentifyMethodService::IDENTIFY_EMAIL) {
						return $identifyMethod->getIdentifierValue();
					}
					if (filter_var($identifyMethod->getIdentifierValue(), FILTER_VALIDATE_EMAIL)) {
						return $identifyMethod->getIdentifierValue();
					}
					return $carry;
				}, ''),
				'description' => $signer->getDescription(),
				'displayName' => array_reduce($identifyMethodsOfSigner, function (string $carry, IdentifyMethod $identifyMethod): string {
					if (!$carry && $identifyMethod->getMandatory()) {
						return $identifyMethod->getIdentifierValue();
					}
					return $carry;
				}, $signer->getDisplayName()),
				'request_sign_date' => $signer->getCreatedAt()->format(DateTimeInterface::ATOM),
				'signed' => null,
				'signRequestId' => $signer->getId(),
				'signingOrder' => $signer->getSigningOrder(),
				'status' => $signer->getStatus(),
				'statusText' => $this->signRequestMapper->getTextOfSignerStatus($signer->getStatus()),
				'me' => array_reduce($identifyMethodsOfSigner, function (bool $carry, IdentifyMethod $identifyMethod) use ($user): bool {
					if ($identifyMethod->getIdentifierKey() === IdentifyMethodService::IDENTIFY_ACCOUNT) {
						return $user->getUID() === $identifyMethod->getIdentifierValue();
					}
					if ($identifyMethod->getIdentifierKey() === IdentifyMethodService::IDENTIFY_EMAIL && $user->getEMailAddress()) {
						return $user->getEMailAddress() === $identifyMethod->getIdentifierValue();
					}
					return $carry;
				}, false),
				'visibleElements' => isset($visibleElements[$signer->getId()])
					? $this->fileElementService->formatVisibleElements(
						$visibleElements[$signer->getId()],
						$file['metadata'],
					)
					: [],
				'identifyMethods' => array_map(fn (IdentifyMethod $identifyMethod): array => [
					'method' => $identifyMethod->getIdentifierKey(),
					'value' => $identifyMethod->getIdentifierValue(),
					'mandatory' => $identifyMethod->getMandatory(),
				], array_values($identifyMethodsOfSigner)),
			];

			if ($data['me']) {
				$temp = array_map(function (IdentifyMethod $identifyMethodEntity) use ($signer): array {
					$this->identifyMethodService->setCurrentIdentifyMethod($identifyMethodEntity);
					$identifyMethod = $this->identifyMethodService
						->setIsRequest(false)
						->getInstanceOfIdentifyMethod(
							$identifyMethodEntity->getIdentifierKey(),
							$identifyMethodEntity->getIdentifierValue(),
						);
					$signatureMethods = $identifyMethod->getSignatureMethods();
					$return = [];
					foreach ($signatureMethods as $signatureMethod) {
						if (!$signatureMethod->isEnabled()) {
							continue;
						}
						$signatureMethod->setEntity($identifyMethod->getEntity());
						$return[$signatureMethod->getName()] = $signatureMethod->toArray();
					}
					return $return;
				}, array_values($identifyMethodsOfSigner));
				$data['signatureMethods'] = [];
				foreach ($temp as $methods) {
					$data['signatureMethods'] = array_merge($data['signatureMethods'], $methods);
				}
				$data['sign_uuid'] = $signer->getUuid();
				$file['url'] = $this->urlGenerator->linkToRoute('libresign.page.getPdfFile', ['uuid' => $signer->getuuid()]);
			}

			if ($signer->getSigned()) {
				$data['signed'] = $signer->getSigned()->format(DateTimeInterface::ATOM);
			}
			ksort($data);
			$file['signers'][] = $data;
		}

		if (empty($file['signers'])) {
			$file['statusText'] = $this->l10n->t('no signers');
			$file['visibleElements'] = [];
		} else {
			usort($file['signers'], function ($a, $b) {
				$orderA = $a['signingOrder'] ?? PHP_INT_MAX;
				$orderB = $b['signingOrder'] ?? PHP_INT_MAX;
				return $orderA <=> $orderB ?: (($a['signRequestId'] ?? 0) <=> ($b['signRequestId'] ?? 0));
			});

			$file['statusText'] = $this->fileMapper->getTextOfStatus((int)$file['status']);
			$file['visibleElements'] = [];
			foreach ($file['signers'] as $signer) {
				if (!empty($signer['visibleElements']) && is_array($signer['visibleElements'])) {
					$file['visibleElements'] = array_merge($file['visibleElements'], $signer['visibleElements']);
				}
			}
		}

		ksort($file);
		/** @psalm-suppress LessSpecificReturnStatement,MoreSpecificReturnType */
		return $file;
	}
}
