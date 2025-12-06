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
	/** @var array<string, string[]> Allowed modification types per DocMDP level */
	private const ALLOWED_MODIFICATIONS = [
		'NO_CHANGES' => [],
		'FORM_FILL' => ['form_field', 'template', 'signature'],
		'FORM_FILL_AND_ANNOTATIONS' => ['form_field', 'template', 'annotation', 'signature'],
	];

	public function __construct(
		private IL10N $l10n,
	) {
	}

	public function extractDocMdpData($resource): array {
		if (!is_resource($resource)) {
			return [];
		}

		$docmdpLevel = $this->extractDocMdpLevel($resource);

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

	/**
	 * Extract DocMDP permission level from PDF
	 *
	 * Validates ISO 32000-1 compliance:
	 * - 12.8.2.2.1: Only ONE DocMDP signature allowed
	 * - 12.8.2.2.1: DocMDP must be FIRST certifying signature
	 * - Table 252: Signature dictionary validation (/Type /Sig, /Filter, /ByteRange)
	 * - Table 253: Signature reference validation (/TransformMethod /DocMDP)
	 * - Table 254: TransformParams validation (/P, /V /1.2)
	 *
	 * @return DocMdpLevel Permission level (NONE, NO_CHANGES, FORM_FILL, FORM_FILL_AND_ANNOTATIONS)
	 */
	private function extractDocMdpLevel($pdfResource): DocMdpLevel {
		rewind($pdfResource);
		$content = stream_get_contents($pdfResource);

		if (!$this->validateIsoCompliance($content)) {
			return DocMdpLevel::NONE;
		}

		$pValue = $this->extractPValue($content);
		if ($pValue === null) {
			return DocMdpLevel::NONE;
		}

		return DocMdpLevel::tryFrom($pValue) ?? DocMdpLevel::NONE;
	}

	/**
	 * Validate all ISO 32000-1 DocMDP requirements
	 *
	 * @return bool True if all validations pass
	 */
	private function validateIsoCompliance(string $content): bool {
		return $this->validateSingleDocMdpSignature($content)
			&& $this->validateDocMdpIsFirstSignature($content)
			&& $this->validateSignatureDictionary($content)
			&& $this->validateSignatureReference($content);
	}

	/**
	 * Extract /P value from TransformParams (permission level)
	 * ISO 32000-1 Table 254: /P is optional, default 2
	 *
	 * @return int|null Permission value (1, 2, or 3) or null if not found/invalid
	 */
	private function extractPValue(string $content): ?int {
		if (preg_match('/\/Reference\s*\[\s*(\d+\s+\d+\s+R)/', $content, $refMatch)) {
			$pValue = $this->extractPValueFromIndirectReference($content, $refMatch[1]);
			if ($pValue !== null) {
				return $pValue;
			}
		}

		$inlinePattern = '/\/Reference\s*\[\s*<<.*?\/TransformMethod\s*\/DocMDP.*?\/TransformParams\s*<<.*?\/P\s*(\d+).*?>>.*?>>.*?\]/s';
		if (preg_match($inlinePattern, $content, $matches)) {
			if ($this->validateTransformParamsVersion($content, $matches[0])) {
				return (int)$matches[1];
			}
		}

		return null;
	}

	/**
	 * Extract /P value from indirect reference structure
	 *
	 * @param string $content Full PDF content
	 * @param string $indirectRef Reference like "7 0 R"
	 * @return int|null Permission value or null
	 */
	private function extractPValueFromIndirectReference(string $content, string $indirectRef): ?int {
		$objPattern = '/' . preg_quote($indirectRef, '/') . '.*?obj\s*<<.*?\/TransformMethod\s*\/DocMDP.*?\/TransformParams\s*(\d+\s+\d+\s+R|<<.*?\/P\s*(\d+).*?>>)/s';

		if (!preg_match($objPattern, $content, $objMatch)) {
			return null;
		}

		if (isset($objMatch[2]) && is_numeric($objMatch[2])) {
			if ($this->validateTransformParamsVersion($content, $objMatch[0])) {
				return (int)$objMatch[2];
			}
			return null;
		}

		if (isset($objMatch[1]) && preg_match('/(\d+\s+\d+\s+R)/', $objMatch[1], $paramsRef)) {
			$objNum = preg_replace('/\s+R$/', '', $paramsRef[1]);
			$paramsPattern = '/' . preg_quote($objNum, '/') . '\s+obj\s*(<<.*?>>)\s*endobj/s';
			if (preg_match($paramsPattern, $content, $paramsMatch)) {
				if (preg_match('/\/P\s*(\d+)/', $paramsMatch[1], $pMatch)) {
					if ($this->validateTransformParamsVersion($content, $paramsMatch[0])) {
						return (int)$pMatch[1];
					}
				}
			}
		}

		return null;
	}

	/**
	 * Parse all PDF objects (obj...endobj blocks) from content
	 * Handles multi-line dictionaries with nested angle brackets
	 *
	 * @return array Array of objects with keys: objNum, dict, position
	 */
	private function parsePdfObjects(string $content): array {
		if (!preg_match_all('/(\d+)\s+\d+\s+obj\s*(<<.*?>>)\s*endobj/s', $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
			return [];
		}

		$objects = [];
		foreach ($matches as $match) {
			$objects[] = [
				'objNum' => $match[1][0],
				'dict' => $match[2][0],
				'position' => $match[2][1],
			];
		}
		return $objects;
	}

	/**
	 * ICP-Brasil DOC-ICP-15.03: Validate /V /1.2 in TransformParams
	 * ISO 32000-1 Table 254: /V is optional, default 1.2
	 */
	private function validateTransformParamsVersion(string $content, string $context): bool {
		if (preg_match('/\/TransformParams\s*(\d+\s+\d+\s+R)/', $context, $paramsRef)) {
			$objNum = preg_replace('/\s+R$/', '', $paramsRef[1]);
			$paramsPattern = '/' . preg_quote($objNum, '/') . '\s+obj\s*(<<.*?>>)\s*endobj/s';
			if (preg_match($paramsPattern, $content, $objMatch)) {
				return preg_match('/\/V\s*\/1\.2/', $objMatch[1]) === 1;
			}
			return false;
		}
		return preg_match('/\/V\s*\/1\.2/', $context) === 1;
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

		$isModified = array_reduce($modifications, fn ($carry, $item) => $carry || $item['modified'], false);

		return [
			'modified' => $isModified,
			'revisionCount' => count($byteRanges),
			'details' => $modifications,
		];
	}

	/**
	 * Validate if modifications are allowed by DocMDP level
	 * ISO 32000-1 Table 254: P=1 (no changes), P=2 (form fill), P=3 (form fill + annotations)
	 *
	 * @return array Validation result with keys: valid, status, message
	 */
	private function validateModifications(DocMdpLevel $docmdpLevel, array $modificationInfo, $pdfResource): array {
		if (!$modificationInfo['modified']) {
			return $this->buildValidationResult(
				true,
				File::MODIFICATION_UNMODIFIED,
				'Document has not been modified after signing'
			);
		}

		if ($docmdpLevel === DocMdpLevel::NONE) {
			return $this->buildValidationResult(
				true,
				File::MODIFICATION_ALLOWED,
				'Document was modified after signing'
			);
		}

		$modificationType = $this->analyzeModificationType($pdfResource, $modificationInfo);
		$allowedTypes = self::ALLOWED_MODIFICATIONS[$docmdpLevel->name] ?? null;

		if ($allowedTypes === null) {
			return $this->buildValidationResult(
				false,
				File::MODIFICATION_VIOLATION,
				'Invalid: Document was modified after signing (DocMDP violation)'
			);
		}

		$isAllowed = in_array($modificationType, $allowedTypes, true);

		return $isAllowed
			? $this->buildValidationResult(
				true,
				File::MODIFICATION_ALLOWED,
				$this->getAllowedModificationMessage($docmdpLevel)
			)
			: $this->buildValidationResult(
				false,
				File::MODIFICATION_VIOLATION,
				$this->getViolationMessage($docmdpLevel)
			);
	}

	/**
	 * Build validation result array
	 *
	 * @param bool $valid Whether modification is valid
	 * @param int $status Status constant from File class
	 * @param string $messageKey Translation key
	 * @return array Validation result
	 */
	private function buildValidationResult(bool $valid, int $status, string $messageKey): array {
		return [
			'valid' => $valid,
			'status' => $status,
			'message' => $this->l10n->t($messageKey),
		];
	}

	/**
	 * Get success message for allowed modification
	 *
	 * @param DocMdpLevel $level DocMDP permission level
	 * @return string Translated message
	 */
	private function getAllowedModificationMessage(DocMdpLevel $level): string {
		return match ($level) {
			DocMdpLevel::NO_CHANGES => 'Invalid: Document was modified after signing (DocMDP violation - no changes allowed)',
			DocMdpLevel::FORM_FILL => 'Document form fields were modified (allowed by DocMDP P=2)',
			DocMdpLevel::FORM_FILL_AND_ANNOTATIONS => 'Document form fields or annotations were modified (allowed by DocMDP P=3)',
			default => 'Document was modified after signing',
		};
	}

	/**
	 * Get error message for modification violation
	 *
	 * @param DocMdpLevel $level DocMDP permission level
	 * @return string Translated message
	 */
	private function getViolationMessage(DocMdpLevel $level): string {
		return match ($level) {
			DocMdpLevel::NO_CHANGES => 'Invalid: Document was modified after signing (DocMDP violation - no changes allowed)',
			DocMdpLevel::FORM_FILL => 'Invalid: Document was modified after signing (DocMDP P=2 only allows form field changes)',
			DocMdpLevel::FORM_FILL_AND_ANNOTATIONS => 'Invalid: Document was modified after signing (DocMDP P=3 only allows form fields and annotations)',
			default => 'Invalid: Document was modified after signing (DocMDP violation)',
		};
	}

	/**
	 * Analyze type of modification made to PDF after signing
	 *
	 * Patterns are checked in priority order (most specific first) to ensure
	 * accurate classification when multiple patterns could match.
	 *
	 * @param resource $pdfResource PDF file resource
	 * @param array $modificationInfo Modification detection info
	 * @return string Modification type: signature, form_field, template, annotation, structural, unknown
	 */
	private function analyzeModificationType($pdfResource, array $modificationInfo): string {
		if (empty($modificationInfo['details'])) {
			return 'unknown';
		}

		rewind($pdfResource);
		$content = stream_get_contents($pdfResource);
		$coveredEnd = $modificationInfo['details'][0]['coveredBytes'];
		$modifiedContent = substr($content, $coveredEnd);

		$patterns = [
			'signature' => '/\/Type\s*\/Sig/',
			'form_field' => '/\/FT\s*\/(?:Tx|Ch|Btn)/',
			'template' => '/\/Type\s*\/XObject\s*\/Subtype\s*\/Form/',
			'annotation' => '/\/Type\s*\/Annot/',
			'structural' => '/\/Type\s*\/Pages?/',
		];

		foreach ($patterns as $type => $pattern) {
			if (preg_match($pattern, $modifiedContent)) {
				return $type;
			}
		}

		return 'unknown';
	}

	/**
	 * ISO 32000-1 12.8.2.2.1: A document can contain only one signature field that contains a DocMDP transform method
	 */
	private function validateSingleDocMdpSignature(string $content): bool {
		$docmdpCount = preg_match_all('/\/TransformMethod\s*\/DocMDP/', $content);
		return $docmdpCount === 1;
	}

	/**
	 * ISO 32000-1 12.8.2.2.1: DocMDP shall be the first signed field
	 *
	 * "First signed field" means first CERTIFYING signature (has /Reference)
	 * that has been applied (/Contents present). Approval signatures (without
	 * /Reference) don't count as they cannot have DocMDP.
	 *
	 * @return bool True if DocMDP is in first certifying signature
	 */
	private function validateDocMdpIsFirstSignature(string $content): bool {
		$certifyingSignatures = $this->filterCertifyingSignatures($this->parsePdfObjects($content));

		if (empty($certifyingSignatures)) {
			return false;
		}

		usort($certifyingSignatures, fn ($a, $b) => $a['position'] <=> $b['position']);

		return $this->signatureHasDocMdp($content, $certifyingSignatures[0]['dict']);
	}

	/**
	 * Filter only certifying signatures from parsed objects
	 *
	 * @param array $objects Parsed PDF objects
	 * @return array Certifying signatures with /Filter, /ByteRange, /Contents, /Reference
	 */
	private function filterCertifyingSignatures(array $objects): array {
		return array_filter($objects, function ($obj) {
			$dict = $obj['dict'];
			return preg_match('/\/Filter\s*\//', $dict)
				&& preg_match('/\/ByteRange\s*\[/', $dict)
				&& preg_match('/\/Contents\s*</', $dict)
				&& preg_match('/\/Reference\s*\[/', $dict);
		});
	}

	/**
	 * Check if signature dictionary has DocMDP (inline or indirect)
	 *
	 * @param string $content Full PDF content
	 * @param string $dict Signature dictionary
	 * @return bool True if has DocMDP
	 */
	private function signatureHasDocMdp(string $content, string $dict): bool {
		if (preg_match('/\/Reference\s*\[.*?\/TransformMethod\s*\/DocMDP/s', $dict)) {
			return true;
		}

		if (preg_match('/\/Reference\s*\[\s*(\d+)\s+\d+\s+R/', $dict, $refMatch)) {
			$refPattern = '/' . $refMatch[1] . '\s+\d+\s+obj\s*<<.*?\/TransformMethod\s*\/DocMDP.*?>>.*?endobj/s';
			return (bool)preg_match($refPattern, $content);
		}

		return false;
	}

	/**
	 * ISO 32000-1 Table 252: Validate signature dictionary entries
	 *
	 * Required entries:
	 * - /Type /Sig (optional, but if present must be /Sig)
	 * - /Filter (Required) - signature handler name
	 * - /ByteRange (Required for DocMDP) - byte ranges covered by signature
	 *
	 * @return bool True if signature dictionary is valid
	 */
	private function validateSignatureDictionary(string $content): bool {
		$objects = $this->parsePdfObjects($content);
		$sigDict = $this->findSignatureDictionary($objects);

		if (!$sigDict) {
			return false;
		}

		return $this->validateDictionaryEntries($sigDict);
	}

	/**
	 * Find signature dictionary with /Reference entry
	 *
	 * @param array $objects Parsed PDF objects
	 * @return string|null Dictionary content or null
	 */
	private function findSignatureDictionary(array $objects): ?string {
		foreach ($objects as $obj) {
			if (preg_match('/\/Reference\s*\[/', $obj['dict'])) {
				return $obj['dict'];
			}
		}
		return null;
	}

	/**
	 * Validate signature dictionary entries per ISO Table 252
	 *
	 * @param string $dict Dictionary content
	 * @return bool True if all required entries are valid
	 */
	private function validateDictionaryEntries(string $dict): bool {
		if (preg_match('/\/Type\s*\/(\w+)/', $dict, $typeMatch) && $typeMatch[1] !== 'Sig') {
			return false;
		}

		if (!preg_match('/\/Filter\s*\/[\w.]+/', $dict)) {
			return false;
		}

		return (bool)preg_match('/\/ByteRange\s*\[/', $dict);
	}

	/**
	 * ISO 32000-1 Table 253: Validate signature reference dictionary
	 *
	 * @return bool True if /TransformMethod /DocMDP is present (inline or indirect)
	 */
	private function validateSignatureReference(string $content): bool {
		return (bool)preg_match('/\/TransformMethod\s*\/DocMDP/', $content);
	}
}
