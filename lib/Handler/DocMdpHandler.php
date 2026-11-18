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

/**
 * @psalm-type PdfObject = array{num: int, gen: int, value: string, entries: ?array<string, string>, stream: ?string, end: int}
 * @psalm-type SignedRevision = array{startxref: int, offsets: array<int, int>, size: int, root: string, info: ?string}
 */
class DocMdpHandler {
	private const int MAX_NESTING_DEPTH = 64;

	/** @var array<string, string[]> Allowed modification types per DocMDP level */
	private const array ALLOWED_MODIFICATIONS = [
		'CERTIFIED_NO_CHANGES_ALLOWED' => [],
		'CERTIFIED_FORM_FILLING' => ['form_field', 'template', 'signature'],
		'CERTIFIED_FORM_FILLING_AND_ANNOTATIONS' => ['form_field', 'template', 'annotation', 'signature'],
	];

	public function __construct(
		private IL10N $l10n,
	) {
	}

	public function allowsAdditionalSignatures($resource): bool {
		$docmdpLevel = $this->extractDocMdpLevel($resource);

		return $docmdpLevel !== DocMdpLevel::CERTIFIED_NO_CHANGES_ALLOWED;
	}

	public function extractDocMdpData($resource): array {
		if (!is_resource($resource)) {
			return [];
		}

		rewind($resource);
		$content = stream_get_contents($resource);
		$isoStatus = $this->getIsoComplianceStatus($content);

		rewind($resource);
		$docmdpLevel = $this->extractDocMdpLevel($resource);

		$result = [
			'docmdp' => [
				'level' => $docmdpLevel->value,
				'label' => $docmdpLevel->getLabel($this->l10n),
				'description' => $docmdpLevel->getDescription($this->l10n),
				'isCertifying' => $docmdpLevel->isCertifying(),
			],
		];
		if ($docmdpLevel->isCertifying() && $this->isOnlySingleDocMdpViolation($isoStatus)) {
			$result['docmdp_validation'] = [
				'valid' => false,
				// TRANSLATORS Validation message shown for a PDF with more than one DocMDP (document modification policy) certifying signature. Only the first certifying signature defines the document permission level.
				'message' => $this->l10n->t('Multiple DocMDP signatures detected. The first certifying signature determines the document\'s permission level.'),
			];
		}

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

		$isoStatus = $this->getIsoComplianceStatus($content);
		if (!$this->isIsoCompliant($isoStatus) && !$this->isOnlySingleDocMdpViolation($isoStatus)) {
			return DocMdpLevel::NOT_CERTIFIED;
		}

		$pValue = $this->extractPValue($content);
		if ($pValue === null) {
			return DocMdpLevel::NOT_CERTIFIED;
		}

		return DocMdpLevel::tryFrom($pValue) ?? DocMdpLevel::NOT_CERTIFIED;
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

	private function getIsoComplianceStatus(string $content): array {
		return [
			'single_docmdp' => $this->validateSingleDocMdpSignature($content),
			'docmdp_first' => $this->validateDocMdpIsFirstSignature($content),
			'sig_dict' => $this->validateSignatureDictionary($content),
			'sig_ref' => $this->validateSignatureReference($content),
		];
	}

	private function isIsoCompliant(array $status): bool {
		return $status['single_docmdp']
			&& $status['docmdp_first']
			&& $status['sig_dict']
			&& $status['sig_ref'];
	}

	private function isOnlySingleDocMdpViolation(array $status): bool {
		return !$status['single_docmdp']
			&& $status['docmdp_first']
			&& $status['sig_dict']
			&& $status['sig_ref'];
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
			$paramsPattern = '/' . preg_quote((string)$objNum, '/') . '\s+obj\s*(<<.*?>>)\s*endobj/s';
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
		if (!preg_match_all('/(\d+)\s+\d+\s+obj(.*?)endobj/s', $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
			return [];
		}

		$objects = [];
		foreach ($matches as $match) {
			$objects[] = [
				'objNum' => $match[1][0],
				'dict' => trim($match[2][0]),
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
			$paramsPattern = '/' . preg_quote((string)$objNum, '/') . '\s+obj\s*(<<.*?>>)\s*endobj/s';
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
			// TRANSLATORS Validation result for a signed PDF that was not changed after the signature was applied.
			return $this->buildValidationResult(
				true,
				File::MODIFICATION_UNMODIFIED,
				$this->l10n->t('Document has not been modified after signing')
			);
		}

		if ($docmdpLevel === DocMdpLevel::NOT_CERTIFIED) {
			// TRANSLATORS Validation result for a signed PDF that was modified, but the document has no DocMDP certification restrictions.
			return $this->buildValidationResult(
				true,
				File::MODIFICATION_ALLOWED,
				$this->l10n->t('Document was modified after signing')
			);
		}

		if ($this->hasOnlyValidationDataUpdates($pdfResource, $modificationInfo)) {
			// TRANSLATORS Validation result for a certified PDF where, after signing, only long-term validation data (DSS: certificates, CRLs, OCSP responses) or document timestamps were added. DocMDP (document modification policy) permits this at every level.
			return $this->buildValidationResult(
				true,
				File::MODIFICATION_ALLOWED,
				$this->l10n->t('Only validation data or document timestamps were added after signing (allowed by DocMDP)')
			);
		}

		$modificationType = $this->analyzeModificationType($pdfResource, $modificationInfo);
		$allowedTypes = self::ALLOWED_MODIFICATIONS[$docmdpLevel->name] ?? null;

		if ($allowedTypes === null) {
			// TRANSLATORS Validation error for a signed PDF modified in a way that violates its DocMDP (document modification policy) certification rules.
			return $this->buildValidationResult(
				false,
				File::MODIFICATION_VIOLATION,
				$this->l10n->t('Invalid: Document was modified after signing (DocMDP violation)')
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
	 * @param string $message Translated message
	 * @return array Validation result
	 */
	private function buildValidationResult(bool $valid, int $status, string $message): array {
		return [
			'valid' => $valid,
			'status' => $status,
			'message' => $message,
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
			// TRANSLATORS Validation error for a certified PDF with DocMDP level P=1, which does not allow any change after signing.
			DocMdpLevel::CERTIFIED_NO_CHANGES_ALLOWED => $this->l10n->t('Invalid: Document was modified after signing (DocMDP violation - no changes allowed)'),
			// TRANSLATORS Validation result for a certified PDF with DocMDP level P=2. Only form field completion is allowed after signing.
			DocMdpLevel::CERTIFIED_FORM_FILLING => $this->l10n->t('Document form fields were modified (allowed by DocMDP P=2)'),
			// TRANSLATORS Validation result for a certified PDF with DocMDP level P=3. Form field completion and annotations are allowed after signing.
			DocMdpLevel::CERTIFIED_FORM_FILLING_AND_ANNOTATIONS => $this->l10n->t('Document form fields or annotations were modified (allowed by DocMDP P=3)'),
			// TRANSLATORS Generic validation result for a signed PDF that was modified after signing.
			default => $this->l10n->t('Document was modified after signing'),
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
			// TRANSLATORS Validation error for a certified PDF with DocMDP level P=1, which does not allow any change after signing.
			DocMdpLevel::CERTIFIED_NO_CHANGES_ALLOWED => $this->l10n->t('Invalid: Document was modified after signing (DocMDP violation - no changes allowed)'),
			// TRANSLATORS Validation error for a certified PDF with DocMDP level P=2. Only form field completion is allowed after signing.
			DocMdpLevel::CERTIFIED_FORM_FILLING => $this->l10n->t('Invalid: Document was modified after signing (DocMDP P=2 only allows form field changes)'),
			// TRANSLATORS Validation error for a certified PDF with DocMDP level P=3. Only form field completion and annotations are allowed after signing.
			DocMdpLevel::CERTIFIED_FORM_FILLING_AND_ANNOTATIONS => $this->l10n->t('Invalid: Document was modified after signing (DocMDP P=3 only allows form fields and annotations)'),
			// TRANSLATORS Generic validation error for a signed PDF modified in a way that violates DocMDP (document modification policy) certification rules.
			default => $this->l10n->t('Invalid: Document was modified after signing (DocMDP violation)'),
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
	 * ISO 32000-2 12.8.2.2.1 and ETSI EN 319 142-1 5.4: DocMDP restrictions do not
	 * apply to incremental updates that only add a DSS dictionary (with its VRI,
	 * Certs, CRLs and OCSPs) or a document timestamp.
	 *
	 * The bytes after the certified revision are parsed strictly as a sequence
	 * of incremental updates: indirect objects, a classic xref table listing
	 * exactly those objects, a trailer and startxref. Anything else (xref
	 * streams, unlisted or unparsable bytes, a changed /Root or /Info, /Encrypt)
	 * means the update is not validation data only. The certified revision must
	 * use classic xref tables, so that the objects an update rewrites can be
	 * compared with their certified version.
	 *
	 * New objects (numbers from the certified /Size on) are inert unless an
	 * existing object references them. Of the existing objects, an update may
	 * only rewrite:
	 * - the catalog, changing /DSS, /Extensions, /Version or appending
	 *   timestamp fields to an inline /AcroForm;
	 * - the AcroForm, changing /SigFlags and appending timestamp fields to /Fields;
	 * - a page, appending timestamp fields to /Annots;
	 * - the document information dictionary, changing /ModDate and /Producer.
	 * A new field must be an invisible document timestamp field; any other new
	 * field, annotation or signature is not validation data.
	 *
	 * @param resource $pdfResource PDF file resource
	 */
	private function hasOnlyValidationDataUpdates($pdfResource, array $modificationInfo): bool {
		if (empty($modificationInfo['details'])) {
			return false;
		}

		rewind($pdfResource);
		$content = (string)stream_get_contents($pdfResource);
		$coveredEnd = (int)$modificationInfo['details'][0]['coveredBytes'];

		$signed = $this->readSignedRevision($content, $coveredEnd);
		if ($signed === null) {
			return false;
		}
		$objects = $this->readIncrementalUpdates($content, $coveredEnd, $signed);
		if (empty($objects)) {
			return false;
		}

		$newObjects = array_filter($objects, static fn (int $num): bool => $num >= $signed['size'], ARRAY_FILTER_USE_KEY);
		$timestampFields = [];
		foreach ($newObjects as $num => $object) {
			$entries = $object['entries'];
			if ($entries === null) {
				continue;
			}
			$type = $entries['/Type'] ?? null;
			if (in_array($type, ['/Sig', '/ObjStm', '/XRef'], true)
				|| (isset($entries['/ByteRange']) && $type !== '/DocTimeStamp')) {
				return false;
			}
			if (isset($entries['/FT'])) {
				if (!$this->isTimestampField($entries, $newObjects)) {
					return false;
				}
				$timestampFields[$num . ' 0 R'] = true;
				continue;
			}
			if ($type === '/Annot' || ($entries['/Subtype'] ?? null) === '/Widget') {
				return false;
			}
		}

		$rootNum = (int)$signed['root'];
		$catalog = $this->readSignedObject($content, $signed, $rootNum);
		$acroFormRef = $catalog['entries']['/AcroForm'] ?? '';
		$acroFormNum = preg_match('/^(\d+) \d+ R$/', $acroFormRef, $match) ? (int)$match[1] : null;
		$infoNum = $signed['info'] === null ? null : (int)$signed['info'];

		foreach ($objects as $num => $object) {
			if ($num >= $signed['size']) {
				continue;
			}
			$old = $this->readSignedObject($content, $signed, $num);
			if ($old === null) {
				return false;
			}
			if ($old['value'] === $object['value'] && $old['stream'] === $object['stream']) {
				continue;
			}
			if ($old['stream'] !== null || $object['stream'] !== null
				|| $old['entries'] === null || $object['entries'] === null) {
				return false;
			}

			$allowed = match (true) {
				$num === $rootNum => $this->isAllowedCatalogChange($old['entries'], $object['entries'], $timestampFields),
				$num === $acroFormNum => $this->isAllowedAcroFormChange($old['entries'], $object['entries'], $timestampFields),
				$num === $infoNum => $this->isAllowedEntriesChange($old['entries'], $object['entries'], [], ['/ModDate', '/Producer'], $timestampFields),
				($old['entries']['/Type'] ?? null) === '/Page' => $this->isAllowedEntriesChange($old['entries'], $object['entries'], ['/Annots'], [], $timestampFields),
				default => false,
			};
			if (!$allowed) {
				return false;
			}
		}

		return true;
	}

	/**
	 * A document timestamp field: merged field and widget without kids or
	 * actions, invisible (zero-area /Rect), whose value is a new
	 * /DocTimeStamp dictionary with /SubFilter /ETSI.RFC3161.
	 *
	 * @param array<string, string> $entries
	 * @param array<int, PdfObject> $newObjects
	 */
	private function isTimestampField(array $entries, array $newObjects): bool {
		if (($entries['/FT'] ?? null) !== '/Sig'
			|| isset($entries['/Kids']) || isset($entries['/Parent']) || isset($entries['/A']) || isset($entries['/AA'])
			|| ($entries['/Type'] ?? '/Annot') !== '/Annot'
			|| ($entries['/Subtype'] ?? '/Widget') !== '/Widget') {
			return false;
		}

		$rect = $entries['/Rect'] ?? null;
		$number = '(-?(?:\d+\.?\d*|\.\d+))';
		if ($rect === null || !preg_match("/^\\[{$number} {$number} {$number} {$number}\\]$/", $rect, $corners)
			|| abs(((float)$corners[3] - (float)$corners[1]) * ((float)$corners[4] - (float)$corners[2])) > 0.0) {
			return false;
		}

		if (!preg_match('/^(\d+) \d+ R$/', $entries['/V'] ?? '', $match)) {
			return false;
		}
		$value = $newObjects[(int)$match[1]]['entries'] ?? null;
		return $value !== null
			&& ($value['/Type'] ?? null) === '/DocTimeStamp'
			&& ($value['/SubFilter'] ?? null) === '/ETSI.RFC3161';
	}

	/**
	 * Trailer and object offsets of the certified revision, read through its
	 * classic xref tables.
	 *
	 * @return SignedRevision|null
	 */
	private function readSignedRevision(string $content, int $coveredEnd): ?array {
		$signedContent = substr($content, 0, $coveredEnd);
		$startxrefPos = strrpos($signedContent, 'startxref');
		if ($startxrefPos === false
			|| !preg_match('/\Gstartxref\s+(\d+)\s+%%EOF\s*$/', $signedContent, $match, 0, $startxrefPos)) {
			return null;
		}
		$startxref = (int)$match[1];

		$offsets = [];
		$newestTrailer = null;
		$visited = [];
		$offset = $startxref;
		while ($offset !== null) {
			if (isset($visited[$offset]) || count($visited) >= 1000 || $offset >= $coveredEnd) {
				return null;
			}
			$visited[$offset] = true;
			$section = $this->readXrefSection($signedContent, $offset);
			if ($section === null || isset($section['trailer']['/XRefStm'])) {
				return null;
			}
			$newestTrailer ??= $section['trailer'];
			foreach ($section['entries'] as $num => [$entryOffset, , $inUse]) {
				if (!array_key_exists($num, $offsets)) {
					$offsets[$num] = $inUse ? $entryOffset : null;
				}
			}
			$prev = $section['trailer']['/Prev'] ?? null;
			$offset = $prev === null ? null : (ctype_digit($prev) ? (int)$prev : -1);
			if ($offset === -1) {
				return null;
			}
		}

		$size = $newestTrailer['/Size'] ?? '';
		$root = $newestTrailer['/Root'] ?? '';
		$info = $newestTrailer['/Info'] ?? null;
		if (!ctype_digit($size) || !preg_match('/^\d+ \d+ R$/', $root)
			|| ($info !== null && !preg_match('/^\d+ \d+ R$/', $info))) {
			return null;
		}

		return [
			'startxref' => $startxref,
			'offsets' => array_filter($offsets, static fn (?int $entryOffset): bool => $entryOffset !== null),
			'size' => (int)$size,
			'root' => $root,
			'info' => $info,
		];
	}

	/**
	 * @param SignedRevision $signed
	 * @return PdfObject|null
	 */
	private function readSignedObject(string $content, array $signed, int $num): ?array {
		if (!isset($signed['offsets'][$num])) {
			return null;
		}
		$object = $this->readIndirectObject($content, $signed['offsets'][$num]);
		return $object !== null && $object['num'] === $num ? $object : null;
	}

	/**
	 * Parse every incremental update after the certified revision.
	 *
	 * @param SignedRevision $signed
	 * @return array<int, PdfObject>|null Latest version of each object written by the updates
	 */
	private function readIncrementalUpdates(string $content, int $coveredEnd, array $signed): ?array {
		$length = strlen($content);
		$pos = $coveredEnd;
		$prev = $signed['startxref'];
		$objects = [];

		while (true) {
			$pos += strspn($content, "\x00\t\n\x0c\r ", $pos);
			if ($pos >= $length) {
				return $objects;
			}

			$revision = [];
			$offsets = [];
			while (preg_match('/\G\d+\s+\d+\s+obj\b/', $content, $match, 0, $pos)) {
				$object = $this->readIndirectObject($content, $pos);
				if ($object === null || isset($revision[$object['num']])) {
					return null;
				}
				$revision[$object['num']] = $object;
				$offsets[$object['num']] = [$pos, $object['gen']];
				$pos = $object['end'];
				$pos += strspn($content, "\x00\t\n\x0c\r ", $pos);
			}

			$xrefOffset = $pos;
			$section = $this->readXrefSection($content, $pos);
			if ($section === null) {
				return null;
			}
			$pos = $section['end'];
			if (!preg_match('/\G\s*startxref\s+(\d+)\s+%%EOF/', $content, $match, 0, $pos)
				|| (int)$match[1] !== $xrefOffset) {
				return null;
			}
			$pos += strlen($match[0]);

			$trailer = $section['trailer'];
			if (($trailer['/Prev'] ?? null) !== (string)$prev
				|| ($trailer['/Root'] ?? null) !== $signed['root']
				|| ($trailer['/Info'] ?? $signed['info']) !== $signed['info']
				|| isset($trailer['/Encrypt']) || isset($trailer['/XRefStm'])) {
				return null;
			}

			$listed = 0;
			foreach ($section['entries'] as $num => [$entryOffset, $generation, $inUse]) {
				if (!$inUse) {
					if ($num !== 0) {
						return null;
					}
					continue;
				}
				if (($offsets[$num] ?? null) !== [$entryOffset, $generation]) {
					return null;
				}
				$listed++;
			}
			if ($listed !== count($revision)) {
				return null;
			}

			$objects = array_replace($objects, $revision);
			$prev = $xrefOffset;
		}
	}

	/**
	 * Classic cross-reference table and its trailer at $pos.
	 *
	 * @return array{entries: array<int, array{0: int, 1: int, 2: bool}>, trailer: array<string, string>, end: int}|null
	 */
	private function readXrefSection(string $content, int $pos): ?array {
		if (!preg_match('/\Gxref[ \t]*(?:\r\n|\r|\n)/', $content, $match, 0, $pos)) {
			return null;
		}
		$pos += strlen($match[0]);

		$entries = [];
		while (preg_match('/\G[ \t]*(\d+) (\d+)[ \t]*(?:\r\n|\r|\n)/', $content, $header, 0, $pos)) {
			$pos += strlen($header[0]);
			$first = (int)$header[1];
			for ($i = 0; $i < (int)$header[2]; $i++) {
				if (!preg_match('/\G(\d{10}) (\d{5}) ([nf])(?: \r| \n|\r\n)/', $content, $entry, 0, $pos)) {
					return null;
				}
				$pos += 20;
				$entries[$first + $i] = [(int)$entry[1], (int)$entry[2], $entry[3] === 'n'];
			}
		}

		if (!preg_match('/\G\s*trailer\b/', $content, $match, 0, $pos)) {
			return null;
		}
		$pos += strlen($match[0]);
		$this->skipPdfWhitespace($content, $pos);
		if (substr($content, $pos, 2) !== '<<') {
			return null;
		}
		$trailer = $this->readPdfDictionary($content, $pos, 0);
		if ($trailer === null) {
			return null;
		}

		return ['entries' => $entries, 'trailer' => $trailer, 'end' => $pos];
	}

	/**
	 * Indirect object at $pos: "N G obj", one value, an optional stream with a
	 * direct /Length, "endobj".
	 *
	 * @return PdfObject|null
	 */
	private function readIndirectObject(string $content, int $pos): ?array {
		if (!preg_match('/\G(\d+)\s+(\d+)\s+obj\b/', $content, $match, 0, $pos)) {
			return null;
		}
		$pos += strlen($match[0]);

		$this->skipPdfWhitespace($content, $pos);
		$entries = null;
		if (substr($content, $pos, 2) === '<<') {
			$valueStart = $pos;
			$entries = $this->readPdfDictionary($content, $pos, 0);
			$value = $entries === null ? null : $this->readPdfValue($content, $valueStart, 0);
		} else {
			$value = $this->readPdfValue($content, $pos, 0);
		}
		if ($value === null) {
			return null;
		}

		$stream = null;
		if (preg_match('/\G\s*stream(?:\r\n|\n)/', $content, $streamStart, 0, $pos)) {
			$length = $entries['/Length'] ?? '';
			if (!ctype_digit($length)) {
				return null;
			}
			$pos += strlen($streamStart[0]);
			$stream = substr($content, $pos, (int)$length);
			$pos += (int)$length;
			if (strlen($stream) !== (int)$length || !preg_match('/\G\s*endstream\b/', $content, $streamEnd, 0, $pos)) {
				return null;
			}
			$pos += strlen($streamEnd[0]);
		}

		if (!preg_match('/\G\s*endobj\b/', $content, $end, 0, $pos)) {
			return null;
		}

		return [
			'num' => (int)$match[1],
			'gen' => (int)$match[2],
			'value' => $value,
			'entries' => $entries,
			'stream' => $stream,
			'end' => $pos + strlen($end[0]),
		];
	}

	/**
	 * @param array<string, string> $old
	 * @param array<string, string> $new
	 * @param array<string, true> $timestampFields
	 */
	private function isAllowedCatalogChange(array $old, array $new, array $timestampFields): bool {
		if (!$this->isAllowedEntriesChange($old, $new, [], ['/DSS', '/Extensions', '/Version', '/AcroForm'], $timestampFields)) {
			return false;
		}
		if (($old['/AcroForm'] ?? null) === ($new['/AcroForm'] ?? null)) {
			return true;
		}

		$oldAcroForm = $this->parseDictionaryEntries($old['/AcroForm'] ?? '<<>>');
		$newAcroForm = $this->parseDictionaryEntries($new['/AcroForm'] ?? '');
		return $oldAcroForm !== null
			&& $newAcroForm !== null
			&& $this->isAllowedAcroFormChange($oldAcroForm, $newAcroForm, $timestampFields);
	}

	/**
	 * @param array<string, string> $old
	 * @param array<string, string> $new
	 * @param array<string, true> $timestampFields
	 */
	private function isAllowedAcroFormChange(array $old, array $new, array $timestampFields): bool {
		return $this->isAllowedEntriesChange($old, $new, ['/Fields'], ['/SigFlags'], $timestampFields);
	}

	/**
	 * @param array<string, string> $old
	 * @param array<string, string> $new
	 * @param list<string> $appendOnlyKeys Keys whose array may only gain timestamp fields
	 * @param list<string> $freeKeys Keys whose value may change freely (checked elsewhere if needed)
	 * @param array<string, true> $timestampFields References to the new timestamp fields
	 */
	private function isAllowedEntriesChange(array $old, array $new, array $appendOnlyKeys, array $freeKeys, array $timestampFields): bool {
		foreach (array_keys($old + $new) as $key) {
			$oldValue = $old[$key] ?? null;
			$newValue = $new[$key] ?? null;
			if ($oldValue === $newValue || in_array($key, $freeKeys, true)) {
				continue;
			}
			if (!in_array($key, $appendOnlyKeys, true) || $newValue === null
				|| !$this->isTimestampFieldAppend($oldValue ?? '[]', $newValue, $timestampFields)) {
				return false;
			}
		}
		return true;
	}

	/**
	 * An array of references that keeps every old entry and only gains
	 * references to the new timestamp fields.
	 *
	 * @param array<string, true> $timestampFields
	 */
	private function isTimestampFieldAppend(string $old, string $new, array $timestampFields): bool {
		$oldRefs = $this->parseReferenceArray($old);
		$newRefs = $this->parseReferenceArray($new);
		if ($oldRefs === null || $newRefs === null || array_slice($newRefs, 0, count($oldRefs)) !== $oldRefs) {
			return false;
		}

		foreach (array_slice($newRefs, count($oldRefs)) as $ref) {
			if (!isset($timestampFields[$ref])) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @return list<string>|null References like "5 0 R", or null when the value is not an array of references only
	 */
	private function parseReferenceArray(string $canonicalArray): ?array {
		if (!preg_match('/^\[((?:\d+ \d+ R ?)*)\]$/', $canonicalArray, $match)) {
			return null;
		}
		preg_match_all('/\d+ \d+ R/', $match[1], $refs);
		return $refs[0];
	}

	/**
	 * Top-level entries of a PDF dictionary, each value in canonical form.
	 *
	 * @return array<string, string>|null Null when the object is not a dictionary
	 */
	private function parseDictionaryEntries(string $object): ?array {
		$pos = 0;
		$this->skipPdfWhitespace($object, $pos);
		if (substr($object, $pos, 2) !== '<<') {
			return null;
		}
		return $this->readPdfDictionary($object, $pos, 0);
	}

	/**
	 * @return array<string, string>|null Null on malformed input or a duplicate key
	 */
	private function readPdfDictionary(string $pdf, int &$pos, int $depth): ?array {
		if ($depth > self::MAX_NESTING_DEPTH) {
			return null;
		}
		$pos += 2;
		$entries = [];
		while (true) {
			$this->skipPdfWhitespace($pdf, $pos);
			if ($pos >= strlen($pdf) || $pdf[$pos] !== '/') {
				if (substr($pdf, $pos, 2) !== '>>') {
					return null;
				}
				$pos += 2;
				return $entries;
			}
			$key = $this->readPdfValue($pdf, $pos, $depth + 1);
			$value = $this->readPdfValue($pdf, $pos, $depth + 1);
			if ($key === null || $value === null || isset($entries[$key])) {
				return null;
			}
			$entries[$key] = $value;
		}
	}

	/**
	 * Read one PDF object at $pos and return it in a canonical form: single
	 * spaces between tokens, dictionary keys sorted, names with #xx escapes
	 * decoded and strings compared by their bytes, so a rewritten but
	 * unchanged object compares equal regardless of the writer's formatting.
	 */
	private function readPdfValue(string $pdf, int &$pos, int $depth): ?string {
		if ($depth > self::MAX_NESTING_DEPTH) {
			return null;
		}
		$this->skipPdfWhitespace($pdf, $pos);
		$length = strlen($pdf);
		if ($pos >= $length) {
			return null;
		}

		if (substr($pdf, $pos, 2) === '<<') {
			$entries = $this->readPdfDictionary($pdf, $pos, $depth + 1);
			if ($entries === null) {
				return null;
			}
			ksort($entries);
			$parts = [];
			foreach ($entries as $key => $value) {
				$parts[] = $key . ' ' . $value;
			}
			return '<<' . implode(' ', $parts) . '>>';
		}

		$char = $pdf[$pos];
		if ($char === '[') {
			$pos++;
			$items = [];
			while (true) {
				$this->skipPdfWhitespace($pdf, $pos);
				if ($pos >= $length) {
					return null;
				}
				if ($pdf[$pos] === ']') {
					$pos++;
					return '[' . implode(' ', $items) . ']';
				}
				$item = $this->readPdfValue($pdf, $pos, $depth + 1);
				if ($item === null) {
					return null;
				}
				$items[] = $item;
			}
		}

		if ($char === '(') {
			$bytes = '';
			$nesting = 0;
			for (; $pos < $length; $pos++) {
				$current = $pdf[$pos];
				if ($current === '\\') {
					$next = $pdf[++$pos] ?? '';
					if (preg_match('/\G[0-7]{1,3}/', $pdf, $octal, 0, $pos)) {
						$bytes .= chr((int)octdec($octal[0]) & 0xff);
						$pos += strlen($octal[0]) - 1;
					} elseif ($next === "\r" && ($pdf[$pos + 1] ?? '') === "\n") {
						$pos++;
					} elseif ($next !== "\r" && $next !== "\n") {
						$bytes .= strtr($next, 'nrtbf', "\n\r\t\x08\x0c");
					}
					continue;
				}
				if ($current === '(' && $nesting++ === 0) {
					continue;
				}
				if ($current === ')' && --$nesting === 0) {
					$pos++;
					return '<' . bin2hex($bytes) . '>';
				}
				$bytes .= $current;
			}
			return null;
		}

		if ($char === '<') {
			$end = strpos($pdf, '>', $pos);
			if ($end === false) {
				return null;
			}
			$hex = strtolower((string)preg_replace('/\s+/', '', substr($pdf, $pos + 1, $end - $pos - 1)));
			if (!ctype_xdigit($hex) && $hex !== '') {
				return null;
			}
			$pos = $end + 1;
			return '<' . $hex . (strlen($hex) % 2 === 1 ? '0' : '') . '>';
		}

		if (!preg_match('/\G\/?[^\x00\t\n\x0c\r ()<>\[\]{}\/%]*/', $pdf, $token, 0, $pos) || $token[0] === '') {
			return null;
		}
		$pos += strlen($token[0]);

		if ($char === '/') {
			return $this->canonicalName(substr($token[0], 1));
		}

		if (ctype_digit($token[0]) && preg_match('/\G\s+(\d+)\s+R(?![^\x00\t\n\x0c\r ()<>\[\]{}\/%])/', $pdf, $ref, 0, $pos)) {
			$pos += strlen($ref[0]);
			return $token[0] . ' ' . $ref[1] . ' R';
		}

		return $token[0];
	}

	/**
	 * Name with #xx escapes decoded, then re-escaped only where needed, so
	 * /ObjStm and /Obj#53tm compare equal.
	 */
	private function canonicalName(string $name): string {
		$decoded = (string)preg_replace_callback(
			'/#([0-9A-Fa-f]{2})/',
			static fn (array $match): string => chr((int)hexdec($match[1])),
			$name,
		);
		return '/' . (string)preg_replace_callback(
			'/[^\x21-\x7e]|[#()<>\[\]{}\/%]/',
			static fn (array $match): string => sprintf('#%02X', ord($match[0])),
			$decoded,
		);
	}

	private function skipPdfWhitespace(string $pdf, int &$pos): void {
		$length = strlen($pdf);
		while ($pos < $length) {
			if (str_contains("\x00\t\n\x0c\r ", $pdf[$pos])) {
				$pos++;
			} elseif ($pdf[$pos] === '%') {
				$pos += strcspn($pdf, "\r\n", $pos);
			} else {
				return;
			}
		}
	}

	/**
	 * ISO 32000-1 12.8.2.2.1: A document can contain only one signature field that contains a DocMDP transform method
	 */
	private function validateSingleDocMdpSignature(string $content): bool {
		$objects = $this->parsePdfObjects($content);
		if (empty($objects)) {
			return false;
		}

		$latestByObj = [];
		foreach ($objects as $obj) {
			$objNum = $obj['objNum'];
			if (!isset($latestByObj[$objNum]) || $obj['position'] > $latestByObj[$objNum]['position']) {
				$latestByObj[$objNum] = $obj;
			}
		}

		$docMdpSignatureCount = 0;
		foreach ($latestByObj as $obj) {
			$dict = $obj['dict'];
			if (!preg_match('/\/Type\s*\/Sig\b/', (string)$dict) || !preg_match('/\/Reference\s*\[/', (string)$dict)) {
				continue;
			}
			if ($this->signatureHasDocMdp($content, $dict)) {
				$docMdpSignatureCount++;
				if ($docMdpSignatureCount > 1) {
					return false;
				}
			}
		}

		// ISO 32000-1: exactly one DocMDP certifying signature is allowed.
		return $docMdpSignatureCount === 1;
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

	private function findSignatureDictionary(array $objects): ?string {
		foreach ($objects as $obj) {
			$dict = $obj['dict'];
			if (preg_match('/\/Type\s*\/Sig\b/', (string)$dict) && preg_match('/\/Reference\s*\[/', (string)$dict)) {
				return $dict;
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
		if (!preg_match('/\/Type\s*\/Sig\b/', $dict)) {
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
