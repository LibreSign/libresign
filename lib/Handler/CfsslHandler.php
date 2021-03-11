<?php

namespace OCA\Libresign\Handler;

use GuzzleHttp\Client;
use OCA\Libresign\Exception\LibresignException;

class CfsslHandler {
	public function generateCertificate(
		string $commonName,
		array $hosts,
		string $country,
		string $organization,
		string $organizationUnit,
		string $password,
		string $cfsslUri
	) {
		$certKeys = $this->newCert(
			$commonName,
			$hosts,
			$country,
			$organization,
			$organizationUnit,
			$cfsslUri
		);
		$certContent = null;
		$isCertGenerated = openssl_pkcs12_export($certKeys['certificate'], $certContent, $certKeys['private_key'], $password);
		if (!$isCertGenerated) {
			throw new LibresignException('Error while creating certificate file', 500);
		}

		return $certContent;
	}

	private function newCert(
		string $commonName,
		array $hosts,
		string $country,
		string $organization,
		string $organizationUnit,
		string $cfsslUri
	) {
		$response = (new Client(['base_uri' => $cfsslUri]))
			->request(
				'POST',
				'newcert',
				[
					'json' => [
						'profile' => 'CA',
						'request' => [
							'hosts' => $hosts,
							'CN' => $commonName,
							'key' => [
								'algo' => 'rsa',
								'size' => 2048,
							],
							'names' => [
								[
									'C' => $country,
									'O' => $organization,
									'OU' => $organizationUnit,
									'CN' => $commonName,
								],
							],
						],
					],
				]
			)
		;

		$responseDecoded = json_decode($response->getBody(), true);
		if (!$responseDecoded['success']) {
			throw new LibresignException('Error while generating certificate keys!', 500);
		}

		return $responseDecoded['result'];
	}

	public function health(string $cfsslUri) {
		try {
			$response = (new Client(['base_uri' => $cfsslUri]))
				->request(
					'GET',
					'health'
				)
			;
		} catch (TransferException $th) {
			if ($th->getHandlerContext() && $th->getHandlerContext()['error']) {
				throw new \Exception($th->getHandlerContext()['error'], 1);
			}
			throw new LibresignException($th->getMessage(), 500);
		}

		$responseDecoded = json_decode($response->getBody(), true);
		if (!$responseDecoded['success']) {
			throw new LibresignException('Error while check cfssl API health!', 500);
		}

		return $responseDecoded['result'];
	}
}
