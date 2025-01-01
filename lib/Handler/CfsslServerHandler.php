<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\Libresign\Handler;

use OCA\Libresign\Exception\LibresignException;

class CfsslServerHandler {
	private string $csrServerFile;
	private string $configServerFile;
	private string $configServerFileHash;
	public function __construct(string $configPath) {
		$this->csrServerFile = $configPath . DIRECTORY_SEPARATOR . 'csr_server.json';
		$this->configServerFile = $configPath . DIRECTORY_SEPARATOR . 'config_server.json';
		$this->configServerFileHash = $configPath . DIRECTORY_SEPARATOR . 'hashes.sha256';
	}

	public function createConfigServer(
		string $commonName,
		array $names,
		string $key,
		int $expirity,
	): void {
		$this->putCsrServer(
			$commonName,
			$names,
		);
		$this->saveNewConfig($key, $expirity);
	}

	private function putCsrServer(
		string $commonName,
		array $names,
	): void {
		$content = [
			'CN' => $commonName,
			'key' => [
				'algo' => 'rsa',
				'size' => 2048,
			],
		];
		foreach ($names as $id => $name) {
			$content['names'][0][$id] = $name['value'];
		}
		$response = file_put_contents($this->csrServerFile, json_encode($content));
		if ($response === false) {
			throw new LibresignException(
				"Error while writing CSR server file.\n" .
				'Remove the CFSSL API URI and Config path to use the default values.',
				500
			);
		}
	}

	private function saveNewConfig(string $key, int $expirity): void {
		$config = [
			'signing' => [
				'profiles' => [
					'CA' => [
						'auth_key' => 'key1',
						'expiry' => ($expirity * 24) . 'h',
						'usages' => [
							'signing',
							'digital signature',
							'cert sign',
							'key encipherment',
							'client auth',
							'email protection'
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
		$this->saveConfig($config);
	}

	private function saveConfig(array $config): void {
		$jsonConfig = json_encode($config);
		$response = file_put_contents($this->configServerFile, $jsonConfig);
		if ($response === false) {
			throw new LibresignException('Error while writing config server file!', 500);
		}
		$hash = hash('sha256', $jsonConfig) . ' config_server.json';
		file_put_contents($this->configServerFileHash, $hash);
	}

	public function updateExpirity(int $expirity): void {
		if (file_exists($this->configServerFileHash)) {
			$hashes = file_get_contents($this->configServerFileHash);
			preg_match('/(?<hash>\w*) +config_server.json/', $hashes, $matches);
			$savedHash = $matches['hash'];
		} else {
			$savedHash = '';
		}
		$jsonConfig = file_get_contents($this->configServerFile);
		$currentHash = hash('sha256', $jsonConfig);
		if ($currentHash === $savedHash) {
			return;
		}
		$config = json_decode($jsonConfig, true);
		$config['signing']['profiles']['CA']['expiry'] = ($expirity * 24) . 'h';
		$this->saveConfig($config);
	}
}
