<?php

namespace OCA\Dsv\Service;

use OC\Files\Filesystem;

class DsvService {
	/**
	 * @NoAdminRequired
	 *
	 * @param mixed $source
	 *
	 * @throws \Exception
	 */
	public function getSignatures($source) {
		$file = Filesystem::getLocalFile($source);
		if (!$file) {
			throw new \Exception('Arquivo não encontrado.');
		}

		$signaturesInfo = $this->signaturesInfo($file);

		$data = [];
		foreach ($signaturesInfo as $sigKey => $signature) {
			$signatureInfo = [];
			foreach ($signature as $key => $value) {
				list($label, $value) = $this->translate($key, $value);
				if (!$label) {
					continue;
				}
				$signatureInfo[$key] = ['label' => $label, 'value' => $value];
			}
			$data[$sigKey] = $signatureInfo;
		}

		return $data;
	}

	private function signaturesInfo($file) {
		return (new PdfSig($file))->getSignature();
	}

	private function translate($key, $value) {
		switch ($key) {
			case 'Signer Certificate Common Name':
				return ['Nome Comum do Certificado do Assinante', $value];
			case 'Signer full Distinguished Name':
				return ['Nome Completo do Certificado do Assinante', $value];
			case 'Signing Time':
				return ['Assinado em', $value];
			case 'Signing Hash Algorithm':
				return ['Tipo do Algoritmo', $value];
			case 'Signature Type':
				return ['Tipo da assinatura', $value];
			case 'Signed Ranges':
				return [null, null];
			case 'Total document signed':
				return [null, null];
			case 'Not total document signed':
				return [null, null];
			case 'Signature Validation':
				return ['Validação da Assinatura', $this->translateSignatureValidation($value)];
			case 'Certificate Validation':
				return [null, null];
			default:
				return [$key, $value];
		}
	}

	private function translateSignatureValidation($value) {
		switch ($value) {
			case 'Signature is Valid.':
				return 'Assinatura válida.';

			case 'Signature is Invalid.':
				return 'Assinatura inválida.';

			case 'Digest Mismatch.':
				return 'Digest Mismatch.';

			case "Document isn't signed or corrupted data.":
				return 'Documento não está assinado ou possui dados corrompidos.';

			case 'Signature has not yet been verified.':
				return 'Assinatura ainda não foi verificada.';

			case 'Unknown Validation Failure.':
				return 'Falha desconhecida na validação.';
			default:
				return $value;
		}
	}
}
