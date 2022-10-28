<?php

namespace OCA\Libresign\Handler;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Helper\MagicGetterSetterTrait;

/**
 * Class FileMapper
 *
 * @package OCA\Libresign\Handler
 *
 * @method CfsslHandler setPassword(string $password)
 * @method string getPassword()
 * @method CfsslHandler setCommonName(string $commonName)
 * @method string getCommonName()
 * @method CfsslHandler setHosts(array $hosts)
 * @method array getHosts()
 * @method CfsslHandler setFriendlyName(string $friendlyName)
 * @method string getFriendlyName()
 * @method CfsslHandler setCountry(string $country)
 * @method string getCountry()
 * @method CfsslHandler setState(string $state)
 * @method string getState()
 * @method CfsslHandler setLocality(string $locality)
 * @method string getLocality()
 * @method CfsslHandler setOrganization(string $organization)
 * @method string getOrganization()
 * @method CfsslHandler setOrganizationUnit(string $organizationUnit)
 * @method string getOrganizationUnit()
 * @method CfsslHandler setCfsslUri(string $cfsslUri)
 * @method string getCfsslUri()
 * @method CfsslHandler setClient(Client $client)
 * @method string getConfigPath()
 * @method CfsslHandler setConfigPath()
 */
class CfsslHandler {
	use MagicGetterSetterTrait;
	public const CFSSL_URI = 'http://127.0.0.1:8888/api/v1/cfssl/';
	private $commonName;
	private $hosts = [];
	private $friendlyName;
	private $country;
	private $state;
	private $locality;
	private $organization;
	private $organizationUnit;
	private $cfsslUri;
	private $password;
	private $configPath;
	private $binary;
	/** @var Client */
	private $client;

	public function getClient(): Client {
		if (!$this->client) {
			$this->setClient(new Client(['base_uri' => $this->getCfsslUri()]));
		}
		$this->wakeUp();
		return $this->client;
	}

	public function generateCertificate(): string {
		$certKeys = $this->newCert();
		$certContent = null;
		try {
			openssl_pkcs12_export(
				$certKeys['certificate'],
				$certContent,
				$certKeys['private_key'],
				$this->getPassword(),
				['friendly_name' => $this->getFriendlyName()],
			);
		} catch (\Throwable $th) {
			throw new LibresignException('Error while creating certificate file', 500);
		}

		return $certContent;
	}

	public function translateToLong($name): string {
		switch ($name) {
			case 'CN':
				return 'CommonName';
			case 'C':
				return 'Country';
			case 'ST':
				return 'State';
			case 'L':
				return 'Locality';
			case 'O':
				return 'Organization';
			case 'OU':
				return 'OrganizationUnit';
		}
		return '';
	}

	public function getNames(): array {
		$names = [
			'C' => $this->getCountry(),
			'ST' => $this->getState(),
			'L' => $this->getLocality(),
			'O' => $this->getOrganization(),
			'OU' => $this->getOrganizationUnit(),
		];
		$names = array_filter($names, function ($v) {
			return !empty($v);
		});
		return $names;
	}

	/**
	 * @psalm-suppress MixedReturnStatement
	 * @return array
	 */
	private function newCert(): array {
		$json = [
			'json' => [
				'profile' => 'CA',
				'request' => [
					'hosts' => $this->getHosts(),
					'CN' => $this->getCommonName(),
					'key' => [
						'algo' => 'rsa',
						'size' => 2048,
					],
					'names' => [
						$this->getNames(),
					],
				],
			],
		];
		try {
			$response = $this->getClient()
				->request('post',
					'newcert',
					$json
				)
			;
		} catch (RequestException | ConnectException $th) {
			if ($th->getHandlerContext() && $th->getHandlerContext()['error']) {
				throw new \Exception($th->getHandlerContext()['error'], 1);
			}
			throw new LibresignException($th->getMessage(), 500);
		}

		$responseDecoded = json_decode($response->getBody(), true);
		if (!isset($responseDecoded['success']) || !$responseDecoded['success']) {
			throw new LibresignException('Error while generating certificate keys!', 500);
		}

		return $responseDecoded['result'];
	}

	/**
	 * @psalm-suppress MixedReturnStatement
	 * @param string $cfsslUri
	 * @return array
	 */
	public function health(?string $cfsslUri = self::CFSSL_URI): array {
		try {
			if (!$this->getCfsslUri()) {
				$this->setCfsslUri($cfsslUri);
			}
			$client = $this->getClient();
			if (!$this->portOpen($cfsslUri)) {
				throw new LibresignException('CFSSL server is down', 500);
			}
			$response = $client
				->request('get',
					'health',
					[
						'base_uri' => $cfsslUri
					]
				)
			;
		} catch (RequestException | ConnectException $th) {
			switch ($th->getCode()) {
				case 404:
					throw new \Exception('Endpoint /health of CFSSL server not found. Maybe you are using incompatible version of CFSSL server. Use latests version.', 1);
				default:
					if ($th->getHandlerContext() && $th->getHandlerContext()['error']) {
						throw new \Exception($th->getHandlerContext()['error'], 1);
					}
					throw new LibresignException($th->getMessage(), 500);
			}
		}

		$responseDecoded = json_decode($response->getBody(), true);
		if (!isset($responseDecoded['success']) || !$responseDecoded['success']) {
			throw new LibresignException('Error while check cfssl API health!', 500);
		}

		return $responseDecoded['result'];
	}

	private function wakeUp(): void {
		$cfsslUri = $this->getCfsslUri() ?? self::CFSSL_URI;
		if ($this->portOpen($cfsslUri)) {
			return;
		}
		$binary = $this->getBinary();
		if (!$binary) {
			return;
		}
		if (!file_exists($binary)) {
			throw new LibresignException('Binary of CFSSL not found');
		}
		$configPath = $this->getConfigPath();
		if (!$configPath) {
			throw new LibresignException('CFSSL not configured.');
		}
		$cmd = 'nohup ' . $binary . ' serve -address=127.0.0.1 ' .
			'-ca-key ' . $configPath . 'ca-key.pem ' .
			'-ca ' . $configPath . 'ca.pem '.
			'-config ' . $configPath . 'config_server.json > /dev/null 2>&1 & echo $!';
		shell_exec($cmd);
		$loops = 0;
		while (!$this->portOpen($cfsslUri) && $loops <= 4) {
			sleep(1);
			$loops++;
		}
	}

	private function portOpen(string $uri): bool {
		$host = parse_url($uri, PHP_URL_HOST);
		$port = parse_url($uri, PHP_URL_PORT);
		try {
			$socket = fsockopen($host, $port, $errno, $errstr, 0.1);
		} catch (\Throwable $th) {
		}
		if (isset($socket) && is_resource($socket)) {
			fclose($socket);
			return true;
		}
		return false;
	}

	public function getBinary(): string {
		if (!$this->binary) {
			return '';
		}
		if (PHP_OS_FAMILY === 'Windows') {
			return $this->binary . '.exe';
		}
		return $this->binary;
	}

	/**
	 * @return self
	 */
	public function setBinary(string $binary): self {
		if ($binary) {
			if (!file_exists($binary)) {
				throw new LibresignException('Binary of CFSSL not found. Install binaries.');
			}
			$this->binary = $binary;
		}
		return $this;
	}

	public function genkey(): void {
		$binary = $this->getBinary();
		if (!$binary) {
			return;
		}
		$configPath = $this->getConfigPath();
		$cmd = $binary . ' genkey ' .
			'-initca=true ' . $configPath . 'csr_server.json | ' .
			$binary . 'json -bare ' . $configPath . 'ca;';
		shell_exec($cmd);
	}
}
