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
use OCA\Libresign\Db\FileElement;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdentifyMethod;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\SignatureFlow;
use OCA\Libresign\ResponseDefinitions;
use OCA\Libresign\Service\FileElementService;
use OCA\Libresign\Service\IdentifyMethodService;
use OCP\AppFramework\Db\Entity;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;

/**
 * @psalm-import-type LibresignVisibleElement from ResponseDefinitions
 * @psalm-import-type LibresignFileDetail from ResponseDefinitions
 * @psalm-import-type LibresignFileListItem from ResponseDefinitions
 * @psalm-import-type LibresignNextcloudFile from ResponseDefinitions
 * @psalm-import-type LibresignPagination from ResponseDefinitions
 * @psalm-import-type LibresignSigner from ResponseDefinitions
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

	public function formatSingleFileForSignRequest(File $file, ?SignRequest $currentSignRequest = null): array {
		$signers = $this->signRequestMapper->getByMultipleFileId([$file->getId()]);
		$identifyMethods = $this->signRequestMapper->getIdentifyMethodsFromSigners($signers);
		$visibleElements = $this->signRequestMapper->getVisibleElementsFromSigners($signers);

		return $this->formatSingleFileData(
			$file,
			$signers,
			$identifyMethods,
			$visibleElements,
			null,
			$currentSignRequest?->getId(),
		);
	}

	/**
	 * Format multiple envelope child files for a sign request with preloaded data.
	 * Avoids N+1 queries by reusing the provided sign request collection.
	 *
	 * @param File[] $childFiles
	 * @param SignRequest[] $childSignRequests
	 * @return list<array<string, mixed>>
	 */
	public function formatEnvelopeChildFilesForSignRequest(
		array $childFiles,
		array $childSignRequests,
		?SignRequest $currentSignRequest = null,
	): array {
		$identifyMethods = $this->signRequestMapper->getIdentifyMethodsFromSigners($childSignRequests);
		$visibleElements = $this->signRequestMapper->getVisibleElementsFromSigners($childSignRequests);
		$signRequestsByFileId = [];
		foreach ($childSignRequests as $signRequest) {
			$signRequestsByFileId[$signRequest->getFileId()][] = $signRequest;
		}
		$currentIdentifyKey = null;
		if ($currentSignRequest) {
			if (!isset($identifyMethods[$currentSignRequest->getId()])) {
				$currentIdentifyMethods = $this->signRequestMapper->getIdentifyMethodsFromSigners([$currentSignRequest]);
				$identifyMethods += $currentIdentifyMethods;
			}
			$currentIdentifyMethods = $identifyMethods[$currentSignRequest->getId()] ?? [];
			$currentIdentifyKey = $this->buildIdentifyKey($currentIdentifyMethods);
		}

		$formatted = [];
		foreach ($childFiles as $childFile) {
			$signers = $signRequestsByFileId[$childFile->getId()] ?? [];
			$meSignRequestId = null;
			if ($currentIdentifyKey !== null) {
				foreach ($signers as $signer) {
					$signerIdentifyKey = $this->buildIdentifyKey($identifyMethods[$signer->getId()] ?? []);
					if ($signerIdentifyKey === $currentIdentifyKey) {
						$meSignRequestId = $signer->getId();
						break;
					}
				}
			}

			$formatted[] = $this->formatSingleFileData(
				$childFile,
				$signers,
				$identifyMethods,
				$visibleElements,
				null,
				$meSignRequestId,
			);
		}

		return $formatted;
	}

	/**
	 * @param File[] $files
	 * @param SignRequest[] $signers
	 * @param array<int, array<string, Entity&IdentifyMethod>> $identifyMethods
	 * @param array<int, FileElement[]> $visibleElements
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
	 * @param File $fileEntity
	 * @param SignRequest[] $signers
	 * @param array<int, array<string, Entity&IdentifyMethod>> $identifyMethods
	 * @param array<int, FileElement[]> $visibleElements
	 * @param IUser|null $user
	 * @return LibresignFileDetail
	 */
	private function formatSingleFileData(
		File $fileEntity,
		array $signers,
		array $identifyMethods,
		array $visibleElements,
		?IUser $user,
		?int $meSignRequestId = null,
	): array {
		$file = [
			'id' => $fileEntity->getId(),
			'nodeId' => $fileEntity->getNodeId(),
			'uuid' => $fileEntity->getUuid(),
			'name' => $fileEntity->getName(),
			'status' => $fileEntity->getStatus(),
			'metadata' => $fileEntity->getMetadata() ?? [],
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

		if ($file['nodeType'] === 'envelope') {
			$file['filesCount'] = $file['metadata']['filesCount'] ?? 0;
			$file['files'] = [];
		} else {
			$file['filesCount'] = 1;
			$file['files'] = $this->formatChildFilesResponse([$fileEntity], $signers, $identifyMethods);
		}

		// Remove raw fields not needed in response
		unset($file['userId'], $file['createdAt']);

		$file['signers'] = [];
		foreach ($signers as $signer) {
			if ($signer->getFileId() !== $fileEntity->getId()) {
				continue;
			}
			$signerData = $this->formatSignerData(
				$signer,
				$identifyMethods,
				$visibleElements,
				$file['metadata'],
				$user,
				$meSignRequestId,
			);
			$file['signers'][] = $signerData;
			if (!empty($signerData['me']) && !isset($file['signUuid'])) {
				$file['signUuid'] = $signerData['sign_uuid'];
			}
		}
		if (isset($file['signUuid'])) {
			$file['url'] = $this->urlGenerator->linkToRoute('libresign.page.getPdfFile', ['uuid' => $file['signUuid']]);
		}

		$file['statusText'] = $this->fileMapper->getTextOfStatus((int)$file['status']);

		$file['signersCount'] = count($file['signers']);

		if (count($file['signers']) > 0) {
			usort($file['signers'], function ($a, $b) {
				$orderA = $a['signingOrder'] ?? PHP_INT_MAX;
				$orderB = $b['signingOrder'] ?? PHP_INT_MAX;
				return $orderA <=> $orderB ?: (($a['signRequestId'] ?? 0) <=> ($b['signRequestId'] ?? 0));
			});

			$file['visibleElements'] = [];
			foreach ($file['signers'] as $signer) {
				if (!empty($signer['visibleElements']) && is_array($signer['visibleElements'])) {
					$file['visibleElements'] = array_merge($file['visibleElements'], $signer['visibleElements']);
				}
			}
		} else {
			$file['visibleElements'] = [];
		}

		ksort($file);
		/** @var LibresignFileDetail */
		return $file;
	}

	/**
	 * Format a single signer with its identify methods and visible elements
	 *
	 * @param SignRequest $signer
	 * @param array<int, array<string, Entity&IdentifyMethod>> $identifyMethods
	 * @param array<int, FileElement[]> $visibleElements
	 * @param array $metadata
	 * @param IUser $user
	 * @return LibresignSigner
	 */
	private function formatSignerData(
		SignRequest $signer,
		array $identifyMethods,
		array $visibleElements,
		array $metadata,
		?IUser $user,
		?int $meSignRequestId = null,
	): array {
		$identifyMethodsOfSigner = $identifyMethods[$signer->getId()] ?? [];
		$resolvedDisplayName = $this->resolveSignerDisplayName($signer, $identifyMethodsOfSigner);
		$me = false;
		if ($meSignRequestId !== null) {
			$me = $signer->getId() === $meSignRequestId;
		} elseif ($user) {
			$me = array_reduce($identifyMethodsOfSigner, function (bool $carry, IdentifyMethod $identifyMethod) use ($user): bool {
				if ($identifyMethod->getIdentifierKey() === IdentifyMethodService::IDENTIFY_ACCOUNT) {
					return $user->getUID() === $identifyMethod->getIdentifierValue();
				}
				if ($identifyMethod->getIdentifierKey() === IdentifyMethodService::IDENTIFY_EMAIL && $user->getEMailAddress()) {
					return $user->getEMailAddress() === $identifyMethod->getIdentifierValue();
				}
				return $carry;
			}, false);
		}
		/** @var LibresignSigner */
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
			'displayName' => $resolvedDisplayName,
			'request_sign_date' => $signer->getCreatedAt()->format(DateTimeInterface::ATOM),
			'signed' => null,
			'signRequestId' => $signer->getId(),
			'signingOrder' => $signer->getSigningOrder(),
			'status' => $signer->getStatus(),
			'statusText' => $this->signRequestMapper->getTextOfSignerStatus($signer->getStatus()),
			'me' => $me,
			'visibleElements' => isset($visibleElements[$signer->getId()])
				? $this->fileElementService->formatVisibleElements(
					$visibleElements[$signer->getId()],
					$metadata,
				)
				: [],
			'identifyMethods' => array_map(fn (IdentifyMethod $identifyMethod): array => [
				'method' => $identifyMethod->getIdentifierKey(),
				'value' => $identifyMethod->getIdentifierValue(),
				'mandatory' => $identifyMethod->getMandatory(),
			], array_values($identifyMethodsOfSigner)),
		];

		if ($data['me'] && !empty($identifyMethodsOfSigner)) {
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
		}

		if ($signer->getSigned()) {
			$data['signed'] = $signer->getSigned()->format(DateTimeInterface::ATOM);
		}
		ksort($data);
		return $data;
	}

	/**
	 * Format signer data without user context
	 * Used when $user is null to still include basic signer information
	 * @param SignRequest $signer
	 * @param array<int, array<string, Entity&IdentifyMethod>> $identifyMethods
	 * @param array<int, FileElement[]> $visibleElements
	 * @return array
	 */
	private function formatSignerDataBasic(
		SignRequest $signer,
		array $identifyMethods,
		array $visibleElements,
	): array {
		$identifyMethodsOfSigner = $identifyMethods[$signer->getId()] ?? [];
		$resolvedDisplayName = $this->resolveSignerDisplayName($signer, $identifyMethodsOfSigner);
		/** @var LibresignSigner */
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
			'displayName' => $resolvedDisplayName,
			'request_sign_date' => $signer->getCreatedAt()->format(DateTimeInterface::ATOM),
			'signed' => null,
			'signRequestId' => $signer->getId(),
			'signingOrder' => $signer->getSigningOrder(),
			'status' => $signer->getStatus(),
			'statusText' => $this->signRequestMapper->getTextOfSignerStatus($signer->getStatus()),
			'me' => false,
			'visibleElements' => isset($visibleElements[$signer->getId()])
				? $this->fileElementService->formatVisibleElements(
					$visibleElements[$signer->getId()],
					[],
				)
				: [],
			'identifyMethods' => array_map(fn (IdentifyMethod $identifyMethod): array => [
				'method' => $identifyMethod->getIdentifierKey(),
				'value' => $identifyMethod->getIdentifierValue(),
				'mandatory' => $identifyMethod->getMandatory(),
			], array_values($identifyMethodsOfSigner)),
		];

		if ($signer->getSigned()) {
			$data['signed'] = $signer->getSigned()->format(DateTimeInterface::ATOM);
		}
		ksort($data);
		return $data;
	}

	/**
	 * Prefer the sign request display name, with safe fallbacks from identify methods.
	 *
	 * @param SignRequest $signer
	 * @param IdentifyMethod[] $identifyMethodsOfSigner
	 */
	private function resolveSignerDisplayName(SignRequest $signer, array $identifyMethodsOfSigner): string {
		$displayName = $signer->getDisplayName();
		foreach ($identifyMethodsOfSigner as $identifyMethod) {
			if ($identifyMethod->getIdentifierKey() !== IdentifyMethodService::IDENTIFY_ACCOUNT) {
				continue;
			}
			$identifierValue = $identifyMethod->getIdentifierValue();
			if ($displayName === '' || $displayName === $identifierValue) {
				$user = $this->userManager->get($identifierValue);
				if ($user) {
					return $user->getDisplayName();
				}
			}
			if ($displayName !== '') {
				return $displayName;
			}
		}
		if ($displayName !== '') {
			return $displayName;
		}

		foreach ($identifyMethodsOfSigner as $identifyMethod) {
			if (!$identifyMethod->getMandatory()) {
				continue;
			}
			if ($identifyMethod->getIdentifierKey() === IdentifyMethodService::IDENTIFY_ACCOUNT) {
				$user = $this->userManager->get($identifyMethod->getIdentifierValue());
				if ($user) {
					return $user->getDisplayName();
				}
			}
			return $identifyMethod->getIdentifierValue();
		}

		return '';
	}

	/**
	 * Format file response with child files for envelopes.
	 * Used by controllers to format main entity with its children.
	 *
	 * @param File $mainEntity
	 * @param File[] $childFiles
	 * @return LibresignNextcloudFile Complete formatted response with metadata, signers, and child files
	 * @psalm-suppress MoreSpecificReturnType
	 */
	/**
	 * Format file with children for response
	 *
	 * @param File $mainEntity
	 * @param File[] $childFiles
	 * @param IUser|null $user Optional user for formatting signers
	 * @return LibresignNextcloudFile
	 * @psalm-suppress MoreSpecificReturnType
	 */
	public function formatFileWithChildren(File $mainEntity, array $childFiles, ?IUser $user = null): array {
		$metadata = $mainEntity->getMetadata() ?? [];

		$signRequestEntities = $this->signRequestMapper->getByFileId($mainEntity->getId());
		$identifyMethods = $this->signRequestMapper->getIdentifyMethodsFromSigners($signRequestEntities);
		$childContext = $mainEntity->getNodeType() === 'envelope' && !empty($childFiles)
			? $this->getEnvelopeChildContext($childFiles)
			: null;
		$visibleElementsData = $mainEntity->getNodeType() === 'envelope'
			? []
			: $this->signRequestMapper->getVisibleElementsFromSigners($signRequestEntities);

		$signers = [];
		$signUuid = null;
		foreach ($signRequestEntities as $signer) {
			if ($user) {
				$signerData = $this->formatSignerData($signer, $identifyMethods, $visibleElementsData, $metadata, $user);
				$signers[] = $signerData;

				if ($signUuid === null && !empty($signerData['me']) && isset($signerData['sign_uuid'])) {
					$signUuid = $signerData['sign_uuid'];
				}
			} else {
				$signers[] = $this->formatSignerDataBasic($signer, $identifyMethods, $visibleElementsData);
			}
		}

		$rawFilesCount = $metadata['filesCount'] ?? null;
		$filesCount = is_numeric($rawFilesCount) ? (int)$rawFilesCount : count($childFiles);
		$filesCount = max(0, $filesCount);

		/** @var LibresignNextcloudFile */
		$response = [
			'message' => $this->l10n->t('Success'),
			'id' => $mainEntity->getId(),
			'nodeId' => $mainEntity->getNodeId(),
			'uuid' => $mainEntity->getUuid(),
			'name' => $mainEntity->getName(),
			'status' => $mainEntity->getStatus(),
			'statusText' => $this->fileMapper->getTextOfStatus($mainEntity->getStatus()),
			'nodeType' => $mainEntity->getNodeType(),
			'created_at' => $mainEntity->getCreatedAt()->format(\DateTimeInterface::ATOM),
			'metadata' => $metadata,
			'signatureFlow' => SignatureFlow::fromNumeric($mainEntity->getSignatureFlow())->value,
			'signers' => $signers,
			'signersCount' => count($signers),
			'requested_by' => [
				'userId' => $mainEntity->getUserId(),
				'displayName' => $this->userManager->get($mainEntity->getUserId())?->getDisplayName() ?? $mainEntity->getUserId(),
			],
		];

		if ($mainEntity->getNodeType() === 'envelope' && $user && !empty($childFiles) && count($signers) > 0) {
			$signers = $this->applyEnvelopeVisibleElementsByKey(
				$signers,
				$identifyMethods,
				$childContext['identifyMethods'] ?? [],
				$childContext['visibleElements'] ?? [],
				$childContext['metadataByFileId'] ?? [],
			);
			$response['signers'] = $signers;
		}

		$response['visibleElements'] = $this->collectVisibleElementsFromSigners($signers);

		if ($signUuid !== null) {
			$response['signUuid'] = $signUuid;
			$response['url'] = $this->urlGenerator->linkToRoute('libresign.page.getPdfFile', ['uuid' => $signUuid]);
		}

		if ($mainEntity->getNodeType() === 'envelope') {
			$response['filesCount'] = $filesCount;
			$response['files'] = $this->formatChildFilesResponse(
				$childFiles,
				$childContext['signers'] ?? null,
				$childContext['identifyMethods'] ?? null,
			);
		} else {
			$response['filesCount'] = 1;
			$response['files'] = $this->formatChildFilesResponse([$mainEntity], $signRequestEntities, $identifyMethods);
		}

		/** @psalm-suppress LessSpecificReturnStatement */
		return $response;
	}

	/**
	 * @param array<int|string, IdentifyMethod> $identifyMethods
	 */
	private function buildIdentifyKey(array $identifyMethods): string {
		if (empty($identifyMethods)) {
			return '';
		}
		$pairs = array_map(
			fn (IdentifyMethod $identifyMethod): string => $identifyMethod->getIdentifierKey() . ':' . $identifyMethod->getIdentifierValue(),
			array_values($identifyMethods),
		);
		sort($pairs);
		return implode('|', $pairs);
	}

	/**
	 * @param File[] $childFiles
	 * @return array{
	 *     signers: array<int, SignRequest>,
	 *     identifyMethods: array<int, array<string, IdentifyMethod>>,
	 *     visibleElements: array<int, FileElement[]>,
	 *     metadataByFileId: array<int, array<string, mixed>>
	 * }
	 */
	private function getEnvelopeChildContext(array $childFiles): array {
		$childFileIds = array_map(fn (File $file) => $file->getId(), $childFiles);
		$childSigners = $childFileIds ? $this->signRequestMapper->getByMultipleFileId($childFileIds) : [];
		$childIdentifyMethods = $this->signRequestMapper->getIdentifyMethodsFromSigners($childSigners);
		$childVisibleElements = [];
		$fileElements = $this->fileElementService->getByFileIds($childFileIds);
		foreach ($fileElements as $fileElement) {
			$signRequestId = $fileElement->getSignRequestId();
			if ($signRequestId === null) {
				continue;
			}
			$childVisibleElements[$signRequestId][] = $fileElement;
		}

		$metadataByFileId = [];
		foreach ($childFiles as $childFile) {
			$metadataByFileId[$childFile->getId()] = $childFile->getMetadata() ?? [];
		}

		return [
			'signers' => $childSigners,
			'identifyMethods' => $childIdentifyMethods,
			'visibleElements' => $childVisibleElements,
			'metadataByFileId' => $metadataByFileId,
		];
	}

	private function applyEnvelopeVisibleElementsByKey(
		array $signers,
		array $envelopeIdentifyMethods,
		array $childIdentifyMethods,
		array $childVisibleElements,
		array $metadataByFileId,
	): array {
		if (empty($childVisibleElements)) {
			return $signers;
		}

		$visibleElementsByKey = [];
		foreach ($childVisibleElements as $signRequestId => $elements) {
			if (empty($elements)) {
				continue;
			}
			$identifyMethodsOfSigner = $childIdentifyMethods[$signRequestId] ?? [];
			$signerKey = $this->buildIdentifyKey($identifyMethodsOfSigner);
			if ($signerKey === '') {
				continue;
			}

			$elementsByFileId = [];
			foreach ($elements as $element) {
				$elementsByFileId[$element->getFileId()][] = $element;
			}

			foreach ($elementsByFileId as $fileId => $fileElements) {
				$metadataForFile = $metadataByFileId[$fileId] ?? [];
				$formattedElements = $this->fileElementService->formatVisibleElements($fileElements, $metadataForFile);
				$visibleElementsByKey[$signerKey] = array_merge($visibleElementsByKey[$signerKey] ?? [], $formattedElements);
			}
		}

		foreach ($signers as $index => $signerData) {
			$signRequestId = $signerData['signRequestId'] ?? null;
			if ($signRequestId === null) {
				continue;
			}
			$identifyMethodsOfSigner = $envelopeIdentifyMethods[$signRequestId] ?? [];
			$signerKey = $this->buildIdentifyKey($identifyMethodsOfSigner);
			if ($signerKey === '') {
				continue;
			}
			$elements = $visibleElementsByKey[$signerKey] ?? [];
			if (empty($elements)) {
				continue;
			}
			$existingElements = $signerData['visibleElements'] ?? [];
			$mergedElements = array_merge($existingElements, $elements);
			$signers[$index]['visibleElements'] = $this->uniqueVisibleElements($mergedElements);
		}

		return $signers;
	}

	/**
	 * @param array<int, array<string, mixed>> $elements
	 * @return array<int, array<string, mixed>>
	 */
	private function uniqueVisibleElements(array $elements): array {
		$unique = [];
		foreach ($elements as $element) {
			$elementId = $element['elementId'] ?? null;
			if ($elementId === null) {
				$unique[] = $element;
				continue;
			}
			$unique[$elementId] = $element;
		}
		return array_values($unique);
	}

	/**
	 * @param array<int, array<string, mixed>> $signers
	 * @return array<int, array<string, mixed>>
	 */
	private function collectVisibleElementsFromSigners(array $signers): array {
		$elements = [];
		foreach ($signers as $signer) {
			$signerElements = $signer['visibleElements'] ?? [];
			if (!empty($signerElements)) {
				$elements = array_merge($elements, $signerElements);
			}
		}
		return $this->uniqueVisibleElements($elements);
	}

	/**
	 * Format child files for response with signers
	 *
	 * @param File[] $files
	 * @return list<LibresignFileListItem>
	 * @psalm-suppress MoreSpecificReturnType
	 * @psalm-suppress LessSpecificReturnStatement
	 */
	private function formatChildFilesResponse(
		array $files,
		?array $allSigners = null,
		?array $identifyMethods = null,
	): array {
		$fileIds = array_map(fn (File $file) => $file->getId(), $files);
		$allSigners = $allSigners ?? ($fileIds ? $this->signRequestMapper->getByMultipleFileId($fileIds) : []);
		$identifyMethods = $identifyMethods ?? $this->signRequestMapper->getIdentifyMethodsFromSigners($allSigners);

		$signersByFileId = [];
		foreach ($allSigners as $signer) {
			$signersByFileId[$signer->getFileId()][] = $signer;
		}

		return array_values(array_map(function (File $file) use ($signersByFileId, $identifyMethods) {
			$signers = $signersByFileId[$file->getId()] ?? [];
			$metadata = $file->getMetadata() ?? [];
			$signersFormatted = array_map(function (SignRequest $signer) use ($identifyMethods) {
				$identifyMethodsOfSigner = $identifyMethods[$signer->getId()] ?? [];
				$email = array_reduce($identifyMethodsOfSigner, function (string $carry, IdentifyMethod $identifyMethod): string {
					if ($identifyMethod->getIdentifierKey() === IdentifyMethodService::IDENTIFY_EMAIL) {
						return $identifyMethod->getIdentifierValue();
					}
					if (filter_var($identifyMethod->getIdentifierValue(), FILTER_VALIDATE_EMAIL)) {
						return $identifyMethod->getIdentifierValue();
					}
					return $carry;
				}, '');
				$displayName = array_reduce($identifyMethodsOfSigner, function (string $carry, IdentifyMethod $identifyMethod): string {
					if (!$carry && $identifyMethod->getMandatory()) {
						return $identifyMethod->getIdentifierValue();
					}
					return $carry;
				}, $signer->getDisplayName());

				return [
					'signRequestId' => $signer->getId(),
					'displayName' => $displayName,
					'email' => $email,
					'identifyMethods' => array_map(fn (IdentifyMethod $identifyMethod): array => [
						'method' => $identifyMethod->getIdentifierKey(),
						'value' => $identifyMethod->getIdentifierValue(),
						'mandatory' => $identifyMethod->getMandatory(),
					], array_values($identifyMethodsOfSigner)),
					'signed' => $signer->getSigned()?->format(\DateTimeInterface::ATOM),
					'status' => $signer->getSigned() ? 1 : 0,
					'statusText' => $signer->getSigned() ? $this->l10n->t('Signed') : $this->l10n->t('Pending'),
				];
			}, $signers);

			return [
				'fileId' => $file->getId(),
				'id' => $file->getId(),
				'nodeId' => $file->getNodeId(),
				'uuid' => $file->getUuid(),
				'name' => $file->getName(),
				'status' => $file->getStatus(),
				'statusText' => $this->fileMapper->getTextOfStatus($file->getStatus()),
				'signersCount' => count($signers),
				'file' => $this->urlGenerator->linkToRoute('libresign.page.getPdf', ['uuid' => $file->getUuid()]),
				'metadata' => $metadata,
				'signers' => $signersFormatted,
			];
		}, $files));
	}
}
