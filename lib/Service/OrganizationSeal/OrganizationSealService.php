<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\OrganizationSeal;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Handler\DocMdpHandler;
use OCA\Libresign\Handler\SignEngine\Pkcs12Handler;
use OCA\Libresign\Service\Policy\FrozenFilePolicySnapshotResolver;
use OCA\Libresign\Service\Policy\Provider\OrganizationSeals\OrganizationSealsPolicy;
use OCA\Libresign\Service\Policy\Provider\OrganizationSeals\OrganizationSealsPolicyValue;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\File;
use Psr\Log\LoggerInterface;

/**
 * Applies the organization seals chosen for a request to the fully signed
 * document: one signature per seal, without visible field, made with
 * LibreSign's own signing engine. The document is already signed, so the
 * engine adds an approval signature and never certifies it.
 */
class OrganizationSealService {
	public const RESULT_METADATA_KEY = 'organization_seals';
	public const STATUS_SEALED = 'sealed';
	public const STATUS_FAILED = 'failed';
	public const ERROR_NOT_FOUND = 'seal_not_found';
	public const ERROR_DOCMDP = 'docmdp_no_changes_allowed';
	public const ERROR_SIGN = 'sign_failed';

	/** @var FrozenFilePolicySnapshotResolver<array{seals: list<string>}> */
	private FrozenFilePolicySnapshotResolver $snapshotResolver;

	public function __construct(
		FileMapper $fileMapper,
		private OrganizationSealStore $sealStore,
		private Pkcs12Handler $pkcs12Handler,
		private DocMdpHandler $docMdpHandler,
		private ITimeFactory $timeFactory,
		private LoggerInterface $logger,
	) {
		$this->snapshotResolver = new FrozenFilePolicySnapshotResolver(
			$fileMapper,
			OrganizationSealsPolicy::KEY,
			static fn (mixed $value): array => ['seals' => OrganizationSealsPolicyValue::decode($value)],
		);
	}

	/**
	 * The seal ids stored on the request when it was sent, in order.
	 *
	 * @return list<string>
	 */
	public function getRequestedSeals(FileEntity $file): array {
		return $this->snapshotResolver->findSnapshot($file)['seals'] ?? [];
	}

	/**
	 * Seal the signed file with every seal chosen for the request.
	 *
	 * Never throws: the signatures are already in the file, so a seal that
	 * cannot be applied is recorded in the file metadata and the file stays
	 * as it is. The result of every seal is stored under
	 * metadata.organization_seals.
	 *
	 * @return bool whether the content of the signed file changed
	 */
	public function apply(FileEntity $libreSignFile, File $signedFile): bool {
		$results = [];
		$changed = false;
		foreach ($this->getRequestedSeals($libreSignFile) as $id) {
			$error = $this->applySeal($id, $signedFile, $libreSignFile);
			$results[$id] = $error === null
				? ['status' => self::STATUS_SEALED]
				: ['status' => self::STATUS_FAILED, 'error' => $error];
			$results[$id]['at'] = $this->timeFactory->getDateTime('now', new \DateTimeZone('UTC'))->format(\DateTimeInterface::ATOM);
			$changed = $changed || $error === null;
		}

		if ($results !== []) {
			$metadata = $libreSignFile->getMetadata() ?? [];
			$metadata[self::RESULT_METADATA_KEY] = $results;
			$libreSignFile->setMetadata($metadata);
		}

		return $changed;
	}

	/**
	 * @return string|null the error code, null when the seal was applied
	 */
	private function applySeal(string $id, File $signedFile, FileEntity $libreSignFile): ?string {
		$credentials = $this->sealStore->getCredentials($id);
		if ($credentials === null) {
			$this->logger->warning('Organization seal {id} is not configured, file {uuid} was not sealed with it', [
				'id' => $id,
				'uuid' => $libreSignFile->getUuid(),
			]);
			return self::ERROR_NOT_FOUND;
		}

		try {
			if (!$this->allowsAdditionalSignatures($signedFile)) {
				$this->logger->warning('File {uuid} is certified with no changes allowed, organization seal {id} was not applied', [
					'id' => $id,
					'uuid' => $libreSignFile->getUuid(),
				]);
				return self::ERROR_DOCMDP;
			}

			$engine = $this->getEngine();
			$engine->setInputFile($signedFile);
			$engine->setCertificate($credentials['pfx']);
			$engine->setPassword($credentials['password']);
			$engine->setVisibleElements([]);
			$engine->setSignatureParams(['SignerCommonName' => $credentials['subject']]);
			$engine->sign();
		} catch (\Throwable $e) {
			$this->logger->error('Failed to apply organization seal {id} to file {uuid}', [
				'id' => $id,
				'uuid' => $libreSignFile->getUuid(),
				'exception' => $e,
			]);
			return self::ERROR_SIGN;
		}

		return null;
	}

	/**
	 * A copy of the signing engine, so the state of the engine used by the
	 * current signer is not changed by the seal.
	 */
	protected function getEngine(): Pkcs12Handler {
		return clone $this->pkcs12Handler;
	}

	private function allowsAdditionalSignatures(File $signedFile): bool {
		$resource = $signedFile->fopen('rb');
		if ($resource === false) {
			throw new \RuntimeException('Unable to open the signed file for reading.');
		}
		try {
			return $this->docMdpHandler->allowsAdditionalSignatures($resource);
		} finally {
			fclose($resource);
		}
	}
}
