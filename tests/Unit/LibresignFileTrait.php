<?php

namespace OCA\Libresign\Tests\Unit;

use donatj\MockWebServer\Response;

trait LibresignFileTrait {
	/**
	 * @var MockWebServer
	 */
	protected static $server;

	private $files = [];

	/**
	 * @var \OCA\Libresign\Service\WebhookService
	 */
	private $webhook;

	public function requestSignFile($data): array {
		self::$server->setResponseOfPath('/api/v1/cfssl/newcert', new Response(
			file_get_contents(__DIR__ . '/../fixtures/cfssl/newcert-with-success.json')
		));

		$this->mockConfig([
			'libresign' => [
				'notifyUnsignedUser' => 0,
				'commonName' => 'CommonName',
				'country' => 'Brazil',
				'organization' => 'Organization',
				'organizationUnit' => 'organizationUnit',
				'cfsslUri' => self::$server->getServerRoot() . '/api/v1/cfssl/'
			]
		]);

		$this->webhook = \OC::$server->get(\OCA\Libresign\Service\WebhookService::class);
		$this->files[] = $file = $this->webhook->save($data);
		return $file;
	}

	public function tearDown(): void {
		foreach ($this->files as $file) {
			$toRemove['uuid'] = $file['uuid'];
			foreach ($file['users'] as $user) {
				$toRemove['users'][] = [
					'email' => $user->getEmail()
				];
			}
			$this->webhook->deleteSignRequest($toRemove);
		}
	}
}
