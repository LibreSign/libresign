<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Handler;

use DateTime;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\InvalidPasswordException;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\CertificateEngine\Handler as CertificateEngineHandler;
use OCA\Libresign\Handler\CertificateEngine\OrderCertificatesTrait;
use OCA\Libresign\Service\FolderService;
use OCP\Files\File;
use OCP\Files\GenericFileException;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\ITempManager;
use phpseclib3\File\ASN1;
use TypeError;

class Pkcs12Handler extends SignEngineHandler {
	use OrderCertificatesTrait;
	private string $pfxFilename = 'signature.pfx';
	private string $pfxContent = '';
	private array $signaturesFromPoppler = [];

	public function __construct(
		private FolderService $folderService,
		private IAppConfig $appConfig,
		private CertificateEngineHandler $certificateEngineHandler,
		private IL10N $l10n,
		private JSignPdfHandler $jSignPdfHandler,
		private FooterHandler $footerHandler,
		private ITempManager $tempManager,
	) {
	}

	public function savePfx(string $uid, string $content): string {
		$this->folderService->setUserId($uid);
		$folder = $this->folderService->getFolder();
		if ($folder->nodeExists($this->pfxFilename)) {
			$file = $folder->get($this->pfxFilename);
			if (!$file instanceof File) {
				throw new LibresignException("path {$this->pfxFilename} already exists and is not a file!", 400);
			}
			try {
				$file->putContent($content);
			} catch (GenericFileException $e) {
				throw new LibresignException("path {$file->getPath()} does not exists!", 400);
			}
			return $content;
		}

		$file = $folder->newFile($this->pfxFilename);
		$file->putContent($content);
		return $content;
	}

	public function deletePfx(string $uid): void {
		$this->folderService->setUserId($uid);
		$folder = $this->folderService->getFolder();
		try {
			$file = $folder->get($this->pfxFilename);
			$file->delete();
		} catch (\Throwable $th) {
		}
	}

	public function updatePassword(string $uid, string $currentPrivateKey, string $newPrivateKey): string {
		$pfx = $this->getPfx($uid);
		$content = $this->certificateEngineHandler->getEngine()->updatePassword(
			$pfx,
			$currentPrivateKey,
			$newPrivateKey
		);
		return $this->savePfx($uid, $content);
	}

	public function readCertificate(?string $uid = null, string $privateKey = ''): array {
		$this->setPassword($privateKey);
		$pfx = $this->getPfx($uid);
		return $this->certificateEngineHandler->getEngine()->readCertificate(
			$pfx,
			$privateKey
		);
	}

	/**
	 * @throws LibresignException When is not a signed file
	 */
	private function getSignatures($resource): iterable {
		$content = stream_get_contents($resource);
		preg_match_all(
			'/ByteRange\s*\[\s*(?<offset1>\d+)\s+(?<length1>\d+)\s+(?<offset2>\d+)\s+(?<length2>\d+)\s*\]/',
			$content,
			$bytes
		);
		if (empty($bytes['offset1']) || empty($bytes['length1']) || empty($bytes['offset2']) || empty($bytes['length2'])) {
			throw new LibresignException($this->l10n->t('Unsigned file.'));
		}

		for ($i = 0; $i < count($bytes['offset1']); $i++) {
			// Starting position (in bytes) of the first part of the PDF that will be included in the validation.
			$offset1 = (int)$bytes['offset1'][$i];
			// Length (in bytes) of the first part.
			$length1 = (int)$bytes['length1'][$i];
			// Starting position (in bytes) of the second part, immediately after the signature.
			$offset2 = (int)$bytes['offset2'][$i];

			$signatureStart = $offset1 + $length1 + 1;
			$signatureLength = $offset2 - $signatureStart - 1;

			rewind($resource);

			$signature = stream_get_contents($resource, $signatureLength, $signatureStart);

			yield hex2bin($signature);
		}

		$this->tempManager->clean();
	}

	/**
	 * @param resource $resource
	 * @throws LibresignException When is not a signed file
	 * @return array
	 */
	public function getCertificateChain($resource): array {
		$signerCounter = 0;
		$certificates = [];
		foreach ($this->getSignatures($resource) as $signature) {
			// The signature could be invalid
			$fromFallback = $this->popplerUtilsPdfSignFallback($resource, $signerCounter);
			if ($fromFallback) {
				$certificates[$signerCounter] = $fromFallback;
			}
			if (!$signature) {
				$certificates[$signerCounter]['chain'][0]['signature_validation'] = $this->getReadableSigState('Digest Mismatch.');
				$signerCounter++;
				continue;
			}

			if (!isset($fromFallback['signingTime'])) {
				// Probably the best way to do this would be:
				// ASN1::asn1map($decoded[0], Maps\TheMapName::MAP);
				// But, what's the MAP to use?
				//
				// With maps also could be possible read all certificate data and
				// maybe discart openssl at  this pint
				try {
					$decoded = ASN1::decodeBER($signature);
					$certificates[$signerCounter]['signingTime'] = $decoded[0]['content'][1]['content'][0]['content'][4]['content'][0]['content'][3]['content'][1]['content'][1]['content'][0]['content'];
				} catch (\Throwable $th) {
				}
			}

			$pkcs7PemSignature = $this->der2pem($signature);
			if (openssl_pkcs7_read($pkcs7PemSignature, $pemCertificates)) {
				foreach ($pemCertificates as $certificateIndex => $pemCertificate) {
					$parsed = openssl_x509_parse($pemCertificate);
					foreach ($parsed as $key => $value) {
						if (!isset($certificates[$signerCounter]['chain'][$certificateIndex][$key])) {
							$certificates[$signerCounter]['chain'][$certificateIndex][$key] = $value;
						} elseif ($key === 'name') {
							$certificates[$signerCounter]['chain'][$certificateIndex][$key] = $value;
						} elseif ($key === 'signatureTypeSN' && $certificates[$signerCounter]['chain'][$certificateIndex][$key] !== $value) {
							$certificates[$signerCounter]['chain'][$certificateIndex][$key] = $value;
						}
					}
					if (empty($certificates[$signerCounter]['chain'][$certificateIndex]['signature_validation'])) {
						$certificates[$signerCounter]['chain'][$certificateIndex]['signature_validation'] = [
							'id' => 1,
							'label' => $this->l10n->t('Signature is valid.'),
						];
					}
				}
			};
			$certificates[$signerCounter]['chain'] = $this->orderCertificates($certificates[$signerCounter]['chain']);
			$signerCounter++;
		}
		return $certificates;
	}

	private function popplerUtilsPdfSignFallback($resource, int $signerCounter): array {
		if (shell_exec('which pdfsig') === null) {
			return [];
		}
		if (!empty($this->signaturesFromPoppler)) {
			if (isset($this->signaturesFromPoppler[$signerCounter])) {
				return $this->signaturesFromPoppler[$signerCounter];
			}
			return [];
		}
		rewind($resource);
		$content = stream_get_contents($resource);
		$tempFile = $this->tempManager->getTemporaryFile('file.pdf');
		file_put_contents($tempFile, $content);

		$content = shell_exec('pdfsig ' . $tempFile);
		if (empty($content)) {
			return [];
		}
		$lines = explode("\n", $content);

		$lastSignature = 0;
		foreach ($lines as $item) {
			$isFirstLevel = preg_match('/^Signature\s#(\d)/', $item, $match);
			if ($isFirstLevel) {
				$lastSignature = (int)$match[1] - 1;
				$this->signaturesFromPoppler[$lastSignature] = [];
				continue;
			}


			$match = [];
			$isSecondLevel = preg_match('/^\s+-\s(?<key>.+):\s(?<value>.*)/', $item, $match);
			if ($isSecondLevel) {
				switch ((string)$match['key']) {
					case 'Signing Time':
						$this->signaturesFromPoppler[$lastSignature]['signingTime'] = DateTime::createFromFormat('M d Y H:i:s', $match['value']);
						break;
					case 'Signer full Distinguished Name':
						$this->signaturesFromPoppler[$lastSignature]['chain'][0]['subject'] = $this->parseDistinguishedNameWithMultipleValues($match['value']);
						$this->signaturesFromPoppler[$lastSignature]['chain'][0]['name'] = $match['value'];
						break;
					case 'Signing Hash Algorithm':
						$this->signaturesFromPoppler[$lastSignature]['chain'][0]['signatureTypeSN'] = $match['value'];
						break;
					case 'Signature Validation':
						$this->signaturesFromPoppler[$lastSignature]['chain'][0]['signature_validation'] = $this->getReadableSigState($match['value']);
						break;
					case 'Certificate Validation':
						$this->signaturesFromPoppler[$lastSignature]['chain'][0]['certificate_validation'] = $this->getReadableCertState($match['value']);
						break;
					case 'Signed Ranges':
						preg_match('/\[(\d+) - (\d+)\], \[(\d+) - (\d+)\]/', $match['value'], $ranges);
						$this->signaturesFromPoppler[$lastSignature]['chain'][0]['range'] = [
							'offset1' => (int)$ranges[1],
							'length1' => (int)$ranges[2],
							'offset2' => (int)$ranges[3],
							'length2' => (int)$ranges[4],
						];
						break;
					case 'Signature Field Name':
						$this->signaturesFromPoppler[$lastSignature]['chain'][0]['field'] = $match['value'];
						break;
					case 'Signature Validation':
					case 'Signature Type':
					case 'Total document signed':
					case 'Not total document signed':
					default:
						break;
				}
			}
		}
		if (isset($this->signaturesFromPoppler[$signerCounter])) {
			return $this->signaturesFromPoppler[$signerCounter];
		}
		return [];
	}

	private function getReadableSigState(string $status) {
		switch ($status) {
			case 'Signature is Valid.':
				return [
					'id' => 1,
					'label' => $this->l10n->t('Signature is valid.'),
				];
			case 'Signature is Invalid.':
				return [
					'id' => 2,
					'label' => $this->l10n->t('Signature is invalid.'),
				];
			case 'Digest Mismatch.':
				return [
					'id' => 3,
					'label' => $this->l10n->t('Digest mismatch.'),
				];
			case "Document isn't signed or corrupted data.":
				return [
					'id' => 4,
					'label' => $this->l10n->t("Document isn't signed or corrupted data."),
				];
			case 'Signature has not yet been verified.':
				return [
					'id' => 5,
					'label' => $this->l10n->t('Signature has not yet been verified.'),
				];
			default:
				return [
					'id' => 6,
					'label' => $this->l10n->t('Unknown validation failure.'),
				];
		}
	}


	private function getReadableCertState(string $status) {
		switch ($status) {
			case 'Certificate is Trusted.':
				return [
					'id' => 1,
					'label' => $this->l10n->t('Certificate is trusted.'),
				];
			case "Certificate issuer isn't Trusted.":
				return [
					'id' => 2,
					'label' => $this->l10n->t("Certificate issuer isn't trusted."),
				];
			case 'Certificate issuer is unknown.':
				return [
					'id' => 3,
					'label' => $this->l10n->t('Certificate issuer is unknown.'),
				];
			case 'Certificate has been Revoked.':
				return [
					'id' => 4,
					'label' => $this->l10n->t('Certificate has been revoked.'),
				];
			case 'Certificate has Expired':
				return [
					'id' => 5,
					'label' => $this->l10n->t('Certificate has expired'),
				];
			case 'Certificate has not yet been verified.':
				return [
					'id' => 6,
					'label' => $this->l10n->t('Certificate has not yet been verified.'),
				];
			default:
				return [
					'id' => 7,
					'label' => $this->l10n->t('Unknown issue with Certificate or corrupted data.')
				];
		}
	}


	private function parseDistinguishedNameWithMultipleValues(string $dn): array {
		$result = [];
		$pairs = preg_split('/,(?=(?:[^"]*"[^"]*")*[^"]*$)/', $dn);

		foreach ($pairs as $pair) {
			[$key, $value] = explode('=', $pair, 2);
			$key = trim($key);
			$value = trim($value);
			$value = trim($value, '"');

			if (!isset($result[$key])) {
				$result[$key] = $value;
			} else {
				if (!is_array($result[$key])) {
					$result[$key] = [$result[$key]];
				}
				$result[$key][] = $value;
			}
		}

		return $result;
	}

	private function der2pem($derData) {
		$pem = chunk_split(base64_encode($derData), 64, "\n");
		$pem = "-----BEGIN CERTIFICATE-----\n" . $pem . "-----END CERTIFICATE-----\n";
		return $pem;
	}

	public function setPfxContent(string $content): void {
		$this->pfxContent = $content;
	}

	/**
	 * Get content of pfx file
	 */
	public function getPfx(?string $uid = null): string {
		if (!empty($this->pfxContent) || empty($uid)) {
			return $this->pfxContent;
		}
		$this->folderService->setUserId($uid);
		$folder = $this->folderService->getFolder();
		if (!$folder->nodeExists($this->pfxFilename)) {
			throw new LibresignException($this->l10n->t('Password to sign not defined. Create a password to sign.'), 400);
		}
		try {
			/** @var \OCP\Files\File */
			$node = $folder->get($this->pfxFilename);
			$this->pfxContent = $node->getContent();
		} catch (GenericFileException $e) {
			throw new LibresignException($this->l10n->t('Password to sign not defined. Create a password to sign.'), 400);
		} catch (\Throwable $th) {
		}
		if (empty($this->pfxContent)) {
			throw new LibresignException($this->l10n->t('Password to sign not defined. Create a password to sign.'), 400);
		}
		if ($this->getPassword()) {
			try {
				$this->certificateEngineHandler->getEngine()->opensslPkcs12Read($this->pfxContent, $this->getPassword());
			} catch (InvalidPasswordException $e) {
				throw new LibresignException($this->l10n->t('Invalid password'));
			}
		}
		return $this->pfxContent;
	}

	private function getHandler(): SignEngineHandler {
		$sign_engine = $this->appConfig->getValueString(Application::APP_ID, 'sign_engine', 'JSignPdf');
		$property = lcfirst($sign_engine) . 'Handler';
		if (!property_exists($this, $property)) {
			throw new LibresignException($this->l10n->t('Invalid Sign engine.'), 400);
		}
		$classHandler = 'OCA\\Libresign\\Handler\\' . $property;
		if (!$this->$property instanceof $classHandler) {
			$this->$property = \OCP\Server::get($classHandler);
		}
		return $this->$property;
	}

	public function sign(): File {
		$signedContent = $this->getHandler()
			->setCertificate($this->getCertificate())
			->setInputFile($this->getInputFile())
			->setPassword($this->getPassword())
			->setSignatureText($this->getSignatureText())
			->setVisibleElements($this->getVisibleElements())
			->getSignedContent();
		$this->getInputFile()->putContent($signedContent);
		return $this->getInputFile();
	}

	public function isHandlerOk(): bool {
		return $this->certificateEngineHandler->getEngine()->isSetupOk();
	}

	/**
	 * Generate certificate
	 *
	 * @param array $user Example: ['host' => '', 'name' => '']
	 * @param string $signPassword Password of signature
	 * @param string $friendlyName Friendly name
	 */
	public function generateCertificate(array $user, string $signPassword, string $friendlyName): string {
		$content = $this->certificateEngineHandler->getEngine()
			->setHosts([$user['host']])
			->setCommonName($user['name'])
			->setFriendlyName($friendlyName)
			->setUID($user['uid'])
			->setPassword($signPassword)
			->generateCertificate();
		if (!$content) {
			throw new TypeError();
		}
		return $content;
	}
}
