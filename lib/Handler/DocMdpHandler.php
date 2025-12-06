<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Handler;

use OCA\Libresign\Db\File;
use OCA\Libresign\Enum\DocMdpLevel;
use OCP\IL10N;

class DocMdpHandler {
	public function __construct(
		private IL10N $l10n,
	) {
	}

	public function extractDocMdpData($resource): array {
		if (!is_resource($resource)) {
			return [];
		}

		$signatureIndex = 0;
		$docmdpLevel = $this->extractDocMdpLevel($resource, $signatureIndex);

		$result = [
			'docmdp' => [
				'level' => $docmdpLevel->value,
				'label' => $docmdpLevel->getLabel($this->l10n),
				'description' => $docmdpLevel->getDescription($this->l10n),
				'isCertifying' => $docmdpLevel->isCertifying(),
			],
		];

		$modificationInfo = $this->detectModifications($resource);
		$result['modifications'] = $modificationInfo;

		if ($modificationInfo['modified'] || $docmdpLevel->isCertifying()) {
			$validation = $this->validateModifications($docmdpLevel, $modificationInfo, $resource);
			$result['modification_validation'] = $validation;
		}

		return $result;
	}

	private function extractDocMdpLevel($pdfResource, int $signatureIndex = 0): DocMdpLevel {
		rewind($pdfResource);
		$content = stream_get_contents($pdfResource);

		$pattern = '/\/Reference\s*\[\s*<<.*?\/TransformMethod\s*\/DocMDP.*?\/TransformParams\s*<<.*?\/P\s*(\d+).*?>>.*?>>.*?\]/s';

		if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
			if (isset($matches[$signatureIndex][1])) {
				$pValue = (int)$matches[$signatureIndex][1];
				return DocMdpLevel::tryFrom($pValue) ?? DocMdpLevel::NONE;
			}
		}

		return DocMdpLevel::NONE;
	}

	private function detectModifications($pdfResource): array {
		rewind($pdfResource);
		$content = stream_get_contents($pdfResource);
		$fileSize = strlen($content);

		preg_match_all(
			'/ByteRange\s*\[\s*(?<offset1>\d+)\s+(?<length1>\d+)\s+(?<offset2>\d+)\s+(?<length2>\d+)\s*\]/',
			$content,
			$byteRanges,
			PREG_SET_ORDER
		);

		if (empty($byteRanges)) {
			return [
				'modified' => false,
				'revisionCount' => 0,
				'details' => [],
			];
		}

		$modifications = [];
		foreach ($byteRanges as $index => $range) {
			$coveredEnd = (int)$range['offset2'] + (int)$range['length2'];
			$hasModifications = $coveredEnd < $fileSize;

			$modifications[] = [
				'signatureIndex' => $index,
				'modified' => $hasModifications,
				'coveredBytes' => $coveredEnd,
				'totalBytes' => $fileSize,
				'extraBytes' => $hasModifications ? ($fileSize - $coveredEnd) : 0,
			];
		}

		$isModified = array_reduce($modifications, fn($carry, $item) => $carry || $item['modified'], false);

		return [
			'modified' => $isModified,
			'revisionCount' => count($byteRanges),
			'details' => $modifications,
		];
	}

	private function validateModifications(DocMdpLevel $docmdpLevel, array $modificationInfo, $pdfResource): array {
		if (!$modificationInfo['modified']) {
			return [
				'valid' => true,
				'status' => File::MODIFICATION_UNMODIFIED,
				'message' => $this->l10n->t('Document has not been modified after signing'),
			];
		}

		if ($docmdpLevel === DocMdpLevel::NONE) {
			return [
				'valid' => true,
				'status' => File::MODIFICATION_ALLOWED,
				'message' => $this->l10n->t('Document was modified after signing'),
			];
		}

		if ($docmdpLevel === DocMdpLevel::NO_CHANGES) {
			return [
				'valid' => false,
				'status' => File::MODIFICATION_VIOLATION,
				'message' => $this->l10n->t('Invalid: Document was modified after signing (DocMDP violation - no changes allowed)'),
			];
		}

		return [
			'valid' => false,
			'status' => File::MODIFICATION_VIOLATION,
			'message' => $this->l10n->t('Invalid: Document was modified after signing (DocMDP violation)'),
		];
	}
}
