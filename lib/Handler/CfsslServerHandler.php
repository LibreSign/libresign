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
	public function createConfigServer(
		string $commonName,
		array $names,
		string $key,
		string $configPath,
	): void {
		$this->putCsrServer(
			$commonName,
			$names,
			$configPath
		);
		$this->putConfigServer($key, $configPath);
	}

	private function putCsrServer(
		string $commonName,
		array $names,
		string $configPath,
	): void {
		$filename = $configPath . DIRECTORY_SEPARATOR . 'csr_server.json';
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
		$response = file_put_contents($filename, json_encode($content));
		if ($response === false) {
			throw new LibresignException(
				"Error while writing CSR server file.\n" .
				'Remove the CFSSL API URI and Config path to use the default values.',
				500
			);
		}
	}

	private function putConfigServer(string $key, string $configPath): void {
		$filename = $configPath . DIRECTORY_SEPARATOR . 'config_server.json';
		$content = [
			'signing' => [
				'profiles' => [
					'CA' => [
						'auth_key' => 'key1',
						'expiry' => '8760h',
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

		$response = file_put_contents($filename, json_encode($content));
		if ($response === false) {
			throw new LibresignException('Error while writing config server file!', 500);
		}
	}
}
