<?php

namespace OCA\Libresign\Handler;

use OCA\Libresign\Exception\LibresignException;

class CfsslServerHandler {
	public const CSR_FILE = 'csr_server.json';
	public const CONFIG_FILE = 'config_server.json';

	public function createConfigServer(
		$commonName,
		$country,
		$organization,
		$organizationUnit,
		$key,
		$configPath
	) {
		$this->putCsrServer(
			$commonName,
			$country,
			$organization,
			$organizationUnit,
			$configPath
		);
		$this->putConfigServer($key, $configPath);
	}

	private function putCsrServer(
		$commonName,
		$country,
		$organization,
		$organizationUnit,
		$configPath
	) {
		$filename = $configPath . self::CSR_FILE;
		$content = [
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
		];
		
		$response = file_put_contents($filename, json_encode($content));
		if ($response === false) {
			throw new LibresignException("Error while writing CSR server file!", 500);
		}
	}

	private function putConfigServer(string $key, string $configPath) {
		$filename = $configPath . self::CONFIG_FILE;
		$content = [
			'signing' => [
				'profiles' => [
					'CA' => [
						'auth_key' => 'key1',
						'expiry' => '8760h',
						'usages' => [
							"signing",
							"digital signature",
							"cert sign"
						],
					],
				],
			],
			'auth_keys' => [
				'key1' => [
					'key' => $key,
					'type' => 'standard',
				],
			],
		];

		$response = file_put_contents($filename, json_encode($content));
		if ($response === false) {
			throw new LibresignException("Error while writing config server file!", 500);
		}
	}
}
