<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Handler;

use Closure;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\CertificatePolicyService;

class CfsslServerHandler {
	private string $csrServerFile = '';
	private string $configServerFile = '';
	private string $configServerFileHash = '';
	private Closure $getConfigPath;

	public function __construct(
		private CertificatePolicyService $certificatePolicyService,
	) {
	}

	/**
	 * Create a callback to get config path not at the constructor because
	 * getting at constructor, every time that the class is instantiated, will
	 * try to create the config path if not exists.
	 */
	public function configCallback(Closure $callback): void {
		$this->getConfigPath = $callback;
		$this->getConfigPath = function () use ($callback): void {
			if ($this->csrServerFile) {
				return;
			}
			$configPath = $callback();
			$this->csrServerFile = $configPath . DIRECTORY_SEPARATOR . 'csr_server.json';
			$this->configServerFile = $configPath . DIRECTORY_SEPARATOR . 'config_server.json';
			$this->configServerFileHash = $configPath . DIRECTORY_SEPARATOR . 'hashes.sha256';
		};
	}

	public function createConfigServer(
		string $commonName,
		array $names,
		int $expirityInDays,
		string $crlUrl = '',
	): void {
		$this->putCsrServer(
			$commonName,
			$names,
			$expirityInDays,
			$crlUrl,
		);
		$this->saveNewConfig($expirityInDays);
	}

	private function putCsrServer(
		string $commonName,
		array $names,
		int $expirityInDays,
		string $crlUrl,
	): void {
		$content = [
			'CN' => $commonName,
			'key' => [
				'algo' => 'rsa',
				'size' => 2048,
			],
			'ca' => [
				'expiry' => ($expirityInDays * 24) . 'h',
				/**
				 * Look the RFC about pathlen constraint
				 *
				 * @link https://www.rfc-editor.org/rfc/rfc5280#section-4.2.1.9
				 */
				'pathlen' => 1,
			],
			'names' => [],
		];
		if (!empty($crlUrl)) {
			$content['crl_url'] = $crlUrl;
		}
		foreach ($names as $id => $name) {
			$content['names'][0][$id] = is_array($name['value']) ? implode(', ', $name['value']) : $name['value'];
		}
		($this->getConfigPath)();
		$response = file_put_contents($this->csrServerFile, json_encode($content));
		if ($response === false) {
			throw new LibresignException(
				"Error while writing CSR server file.\n"
				. 'Remove the CFSSL API URI and Config path to use the default values.',
				500
			);
		}
	}
	private function saveNewConfig(int $expirity): void {
		$config = [
			'signing' => [
				'profiles' => [
					'client' => [
						'expiry' => ($expirity * 24) . 'h',
						'usages' => [
							'signing',
							'digital signature',
							'content commitment',
							'key encipherment',
							'client auth',
							'email protection'
						],
						'ca_constraint' => [
							'is_ca' => false
						],
					],
				],
			],
		];
		$oid = $this->certificatePolicyService->getOid();
		$cps = $this->certificatePolicyService->getCps();
		if ($oid && $cps) {
			$config['signing']['profiles']['client']['policies'][] = [
				'id' => $oid,
				'qualifiers' => [
					[
						'type' => 'id-qt-cps',
						'value' => $cps,
					],
				],
			];
		}
		$this->saveConfig($config);
	}

	private function saveConfig(array $config): void {
		$jsonConfig = json_encode($config);
		($this->getConfigPath)();
		$response = file_put_contents($this->configServerFile, $jsonConfig);
		if ($response === false) {
			throw new LibresignException('Error while writing config server file!', 500);
		}
		$hash = hash('sha256', $jsonConfig) . ' config_server.json';
		file_put_contents($this->configServerFileHash, $hash);
	}

	public function updateExpirity(int $expirity): void {
		($this->getConfigPath)();
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
		$config['signing']['profiles']['client']['expiry'] = ($expirity * 24) . 'h';
		$this->saveConfig($config);
	}
}
