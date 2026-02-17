<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\File;

use DateTimeInterface;
use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Service\FileElementService;
use OCA\Libresign\Service\IdentifyMethodService;
use OCP\Files\IRootFolder;
use Psr\Log\LoggerInterface;

class EnvelopeAssembler {
	public function __construct(
		private SignRequestMapper $signRequestMapper,
		private IdentifyMethodService $identifyMethodService,
		private FileMapper $fileMapper,
		private IRootFolder $root,
		private SignersLoader $signersLoader,
		private ?CertificateChainService $certificateChainService,
		private \OCA\Libresign\Handler\SignEngine\Pkcs12Handler $pkcs12Handler,
		private LoggerInterface $logger,
		private FileElementService $fileElementService,
	) {
	}

	public function buildEnvelopeChildData(File $childFile, \OCA\Libresign\Service\File\FileResponseOptions $options): \stdClass {
		$fileData = new \stdClass();
		$fileData->id = $childFile->getId();
		$fileData->uuid = $childFile->getUuid();
		$fileData->name = $childFile->getName();
		$fileData->status = $childFile->getStatus();
		$fileData->statusText = $this->fileMapper->getTextOfStatus($childFile->getStatus());
		$fileData->nodeId = $childFile->getNodeId();
		$fileData->metadata = $childFile->getMetadata();
		$childMetadata = $childFile->getMetadata() ?? [];
		$fileData->totalPages = (int)($childMetadata['p'] ?? 0);
		$fileData->pdfVersion = (string)($childMetadata['pdfVersion'] ?? '');

		$nodeId = $childFile->getSignedNodeId() ?: $childFile->getNodeId();
		$fileNode = $this->root->getUserFolder($childFile->getUserId())->getFirstNodeById($nodeId);
		if ($fileNode instanceof \OCP\Files\File) {
			if (method_exists($fileNode, 'getSize')) {
				$fileData->size = $fileNode->getSize();
			}
			if (method_exists($fileNode, 'getMimeType')) {
				$fileData->mime = $fileNode->getMimeType();
			}
		}

		$fileData->signers = [];
		$fileData->visibleElements = [];

		$signRequests = $this->signRequestMapper->getByFileId($childFile->getId());
		if (empty($signRequests)) {
			return $fileData;
		}
		$signRequestIds = array_column(array_map(fn ($sr) => ['id' => $sr->getId()], $signRequests), 'id');
		$identifyMethodsBatch = $this->identifyMethodService
			->setIsRequest(false)
			->getIdentifyMethodsFromSignRequestIds($signRequestIds);

		foreach ($signRequests as $signRequest) {
			$identifyMethods = $identifyMethodsBatch[$signRequest->getId()] ?? [];
			$identifyMethodsArray = [];
			$signerUid = null;
			foreach ($identifyMethods as $methods) {
				foreach ($methods as $identifyMethod) {
					$entity = $identifyMethod->getEntity();
					$identifyMethodsArray[] = [
						'method' => $entity->getIdentifierKey(),
						'value' => $entity->getIdentifierValue(),
						'mandatory' => $entity->getMandatory(),
					];
					$signerUid ??= $entity->getUniqueIdentifier();
				}
			}

			$email = '';
			foreach ($identifyMethods[IdentifyMethodService::IDENTIFY_EMAIL] ?? [] as $identifyMethod) {
				$entity = $identifyMethod->getEntity();
				if ($entity->getIdentifierKey() === IdentifyMethodService::IDENTIFY_EMAIL) {
					$email = $entity->getIdentifierValue();
					break;
				}
			}

			$signed = null;
			if ($signRequest->getSigned()) {
				$signed = $signRequest->getSigned()->format(DateTimeInterface::ATOM);
			}

			$displayName = $signRequest->getDisplayName();
			if ($displayName === '' && $email !== '') {
				$displayName = $email;
			}

			$signer = new \stdClass();
			$signer->signRequestId = $signRequest->getId();
			$signer->displayName = $displayName;
			$signer->email = $email;
			$signer->uid = $signerUid;
			$signer->signed = $signed;
			$signer->status = $signRequest->getStatus();
			$signer->statusText = $this->signRequestMapper->getTextOfSignerStatus($signRequest->getStatus());
			$signer->identifyMethods = $identifyMethodsArray;
			$signer->metadata = $signRequest->getMetadata();
			$fileData->signers[] = $signer;
		}

		if ($options->isShowVisibleElements()) {
			$childMetadata = $childFile->getMetadata();
			foreach ($this->signRequestMapper->getVisibleElementsFromSigners($signRequests) as $row) {
				if (empty($row)) {
					continue;
				}
				$fileData->visibleElements = array_merge(
					$this->fileElementService->formatVisibleElements($row, $childMetadata),
					$fileData->visibleElements
				);
			}
		}

		if ($options->isValidateFile() && $childFile->getSignedNodeId()) {
			try {
				$fileNode = $this->root->getUserFolder($childFile->getUserId())->getFirstNodeById($childFile->getSignedNodeId());
				if ($fileNode instanceof \OCP\Files\File) {
					if ($this->certificateChainService !== null) {
						$certData = $this->certificateChainService->getCertificateChain($fileNode, $childFile, $options);
					} else {
						$resource = $fileNode->fopen('rb');
						$sha256 = $this->getSha256FromResource($resource);
						rewind($resource);
						if ($sha256 === $childFile->getSignedHash()) {
							$this->pkcs12Handler->setIsLibreSignFile();
						}
						$certData = $this->pkcs12Handler->getCertificateChain($resource);
						fclose($resource);
					}
					if (!empty($certData)) {
						$this->signersLoader->loadSignersFromCertData($fileData, $certData, $options->getHost());
					}
				}
			} catch (\Exception $e) {
				$this->logger->warning('Failed to load envelope child certificate chain: ' . $e->getMessage());
			}
		}

		return $fileData;
	}

	private function getSha256FromResource($resource): string {
		$hashContext = hash_init('sha256');
		while (!feof($resource)) {
			$buffer = fread($resource, 8192);
			hash_update($hashContext, $buffer);
		}
		return hash_final($hashContext);
	}

}
