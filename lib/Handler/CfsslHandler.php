<?php

namespace OCA\Libresign\Handler;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\TransferException;
use OCA\Libresign\Exception\LibresignException;

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
 * @method CfsslHandler setOrganization(string $organization)
 * @method string getOrganization()
 * @method CfsslHandler setOrganizationUnit(string $organizationUnit)
 * @method string getOrganizationUnit()
 * @method CfsslHandler setCfsslUri(string $cfsslUri)
 * @method string getCfsslUri()
 * @method CfsslHandler setClient(ClientInterface $client)
 * @method Client getClient()
 * @method string getConfigPath()
 * @method CfsslHandler setConfigPath()
 */
class CfsslHandler {
	private $commonName;
	private $hosts = [];
	private $friendlyName;
	private $country;
	private $organization;
	private $organizationUnit;
	private $cfsslUri;
	private $password;
	private $configPath;
	private $binary;
	/** @var ClientInterface */
	private $client;
	public function __call($name, $arguments) {
		if (!preg_match('/^(?<type>get|set)(?<property>.+)/', $name, $matches)) {
			throw new \LogicException(sprintf('Cannot set non existing property %s->%s = %s.', \get_class($this), $name, var_export($arguments, true)));
		}
		$property = lcfirst($matches['property']);
		if (!property_exists($this, $property)) {
			throw new \LogicException(sprintf('Cannot set non existing property %s->%s = %s.', \get_class($this), $name, var_export($arguments, true)));
		}
		if ($matches['type'] === 'get') {
			return $this->$property;
		}
		$this->$property = $arguments[0] ?? null;
		return $this;
	}

	public function getClient(): ClientInterface {
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
						[
							'C' => $this->getCountry(),
							'O' => $this->getOrganization(),
							'OU' => $this->getOrganizationUnit(),
							'CN' => $this->getCommonName(),
						],
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
		} catch (TransferException $th) {
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
	public function health(string $cfsslUri): array {
		try {
			$response = $this->getClient()
				->request('get',
					'health',
					[
						'base_uri' => $cfsslUri
					]
				)
			;
		} catch (TransferException $th) {
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
		if ($this->portOpen()) {
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
		$cmd = 'nohup ' . $binary . ' serve -address=127.0.0.1 ' .
			'-ca-key ' . $configPath . 'ca-key.pem ' .
			'-ca ' . $configPath . 'ca.pem '.
			'-config ' . $configPath . 'config_server.json > /dev/null 2>&1 & echo $!';
		shell_exec($cmd);
		$loops = 0;
		while (!$this->portOpen() && $loops <= 4) {
			sleep(1);
			$loops++;
		}
	}

	private function portOpen(): bool {
		$uri = $this->getCfsslUri();
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
			if (PHP_OS_FAMILY === 'Windows') {
				$this->binary .= '.exe';
			}
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
