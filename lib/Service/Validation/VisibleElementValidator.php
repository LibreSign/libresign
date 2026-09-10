<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Validation;

use OCA\Libresign\Db\FileElement;
use OCA\Libresign\Db\FileElementMapper;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Db\UserElementMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\SignerElementsService;
use OCP\IL10N;
use OCP\IUser;

class VisibleElementValidator {
	public function __construct(
		private IL10N $l10n,
		private SignRequestMapper $signRequestMapper,
		private FileMapper $fileMapper,
		private FileElementMapper $fileElementMapper,
		private UserElementMapper $userElementMapper,
		private SignerElementsService $signerElementsService,
		private FileInputValidator $fileInputValidator,
	) {
	}

	public function validateVisibleElements(?array $visibleElements, int $type): void {
		if (!is_array($visibleElements)) {
			throw new LibresignException($this->l10n->t('Visible elements need to be an array'));
		}
		if ($visibleElements && !$this->signerElementsService->isSignElementsAvailable()) {
			throw new LibresignException($this->l10n->t('Visible elements are disabled.'));
		}
		foreach ($visibleElements as $element) {
			$this->validateVisibleElement($element, $type);
		}
	}

	public function validateVisibleElement(array $element, int $type): void {
		$this->validateElementType($element);
		$this->validateElementSignRequestId($element, $type);
		$this->fileInputValidator->validateFile($element, $type);
		$this->validateElementCoordinates($element);
	}

	public function validateElementSignRequestId(array $element, int $type): void {
		if ($type !== FileInputValidator::TYPE_VISIBLE_ELEMENT_PDF) {
			return;
		}
		if (!array_key_exists('signRequestId', $element) && !array_key_exists('uuid', $element)) {
			throw new LibresignException($this->l10n->t('Element must be associated with a user'));
		}
		$getter = array_key_exists('signRequestId', $element)
			? fn () => $this->signRequestMapper->getById($element['signRequestId'])
			: fn () => $this->signRequestMapper->getByUuid($element['uuid']);
		try {
			$getter();
		} catch (\Throwable) {
			throw new LibresignException($this->l10n->t('User not found for element.'));
		}
	}

	public function validateElementCoordinates(array $element): void {
		if (!array_key_exists('coordinates', $element)) {
			return;
		}
		$this->validateElementPage($element);
		foreach ($element['coordinates'] as $type => $value) {
			if (!in_array($type, ['llx', 'lly', 'urx', 'ury', 'width', 'height', 'left', 'top'], true)) {
				continue;
			}
			if (!is_int($value)) {
				throw new LibresignException($this->l10n->t('Coordinate %s must be an integer', [$type]));
			}
			if ($value < 0) {
				throw new LibresignException($this->l10n->t('Object outside the page margin'));
			}
		}
	}

	public function validateElementPage(array $element): void {
		if (!array_key_exists('page', $element['coordinates'])) {
			return;
		}
		if (!is_int($element['coordinates']['page'])) {
			throw new LibresignException($this->l10n->t('Page number must be an integer'));
		}
		if ($element['coordinates']['page'] < 1) {
			throw new LibresignException($this->l10n->t('Page must be equal to or greater than 1'));
		}
	}

	public function validateElementType(array $element): void {
		if (!array_key_exists('type', $element)) {
			if (!array_key_exists('elementId', $element)) {
				throw new LibresignException($this->l10n->t('Element needs a type'));
			}
			return;
		}
		if (!in_array($element['type'], ['signature', 'initial', 'date', 'datetime', 'text'], true)) {
			throw new LibresignException($this->l10n->t('Invalid element type'));
		}
	}

	public function validateVisibleElementsRelation(array $list, SignRequest $signRequest, ?IUser $user): void {
		$canCreateSignature = $this->signerElementsService->canCreateSignature();
		$childSignRequests = $this->getEnvelopeChildSignRequests($signRequest);
		$childSignRequestIds = array_map(fn (SignRequest $sr) => $sr->getId(), $childSignRequests);
		foreach ($list as $elements) {
			if (!array_key_exists('documentElementId', $elements)) {
				throw new LibresignException($this->l10n->t('Field %s not found', ['documentElementId']));
			}
			if ($canCreateSignature && !array_key_exists('profileNodeId', $elements)) {
				throw new LibresignException($this->l10n->t('Field %s not found', ['profileNodeId']));
			}
			$this->validateSignerIsOwnerOfPdfVisibleElement($elements['documentElementId'], $signRequest, $childSignRequestIds);
			if ($canCreateSignature && $user instanceof IUser) {
				try {
					$this->userElementMapper->findOne(['node_id' => $elements['profileNodeId'], 'user_id' => $user->getUID()]);
				} catch (\Throwable) {
					throw new LibresignException($this->l10n->t('Field %s does not belong to user', $elements['profileNodeId']));
				}
			}
		}
		$this->validateUserHasNecessaryElements($signRequest, $user, $list, $childSignRequests);
	}

	public function validateAuthenticatedUserIsOwnerOfPdfVisibleElement(int $documentElementId, string $uid): void {
		try {
			$documentElement = $this->fileElementMapper->getById($documentElementId);
			$signRequest = $this->signRequestMapper->getById($documentElement->getSignRequestId());
			$file = $this->fileMapper->getById($signRequest->getFileId());
			if ($file->getUserId() !== $uid) {
				throw new \RuntimeException();
			}
		} catch (\Throwable) {
			throw new LibresignException($this->l10n->t('Field %s does not belong to user', (string)$documentElementId));
		}
	}

	/** @return SignRequest[] */
	private function getEnvelopeChildSignRequests(SignRequest $signRequest): array {
		$file = $this->fileMapper->getById($signRequest->getFileId());
		if (!$file->isEnvelope()) {
			return [];
		}
		return $this->signRequestMapper->getByEnvelopeChildrenAndIdentifyMethod($file->getId(), $signRequest->getId());
	}

	/** @param SignRequest[] $childSignRequests */
	private function validateUserHasNecessaryElements(SignRequest $signRequest, ?IUser $user, array $list = [], array $childSignRequests = []): void {
		$fileElements = $this->fileElementMapper->getByFileIdAndSignRequestId($signRequest->getFileId(), $signRequest->getId());
		if (empty($fileElements) && !empty($childSignRequests)) {
			foreach ($childSignRequests as $childSr) {
				$fileElements = array_merge($fileElements, $this->fileElementMapper->getByFileIdAndSignRequestId($childSr->getFileId(), $childSr->getId()));
			}
		}
		$total = array_filter($fileElements, function (FileElement $fileElement) use ($list, $user): bool {
			$found = array_filter($list, fn ($item): bool => $item['documentElementId'] === $fileElement->getId());
			if (!$found && $this->signerElementsService->canCreateSignature()) {
				try {
					if (!$user instanceof IUser) {
						throw new \RuntimeException();
					}
					$this->userElementMapper->findMany(['user_id' => $user->getUID(), 'type' => $fileElement->getType()]);
				} catch (\Throwable) {
					throw new LibresignException($this->l10n->t('You need to define a visible signature or initials to sign this document.'));
				}
			}
			return true;
		});
		if (count($total) !== count($fileElements)) {
			throw new LibresignException($this->l10n->t('You need to define a visible signature or initials to sign this document.'));
		}
	}

	/** @param int[] $childSignRequestIds */
	private function validateSignerIsOwnerOfPdfVisibleElement(int $documentElementId, SignRequest $signRequest, array $childSignRequestIds = []): void {
		$documentElement = $this->fileElementMapper->getById($documentElementId);
		if ($documentElement->getSignRequestId() === $signRequest->getId()) {
			return;
		}
		if (!empty($childSignRequestIds) && in_array($documentElement->getSignRequestId(), $childSignRequestIds, true)) {
			return;
		}
		throw new LibresignException($this->l10n->t('Invalid data to sign file'), 1);
	}
}
