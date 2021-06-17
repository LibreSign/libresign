<?php

namespace OCA\Libresign\Tests\Unit;

use donatj\MockWebServer\MockWebServer;
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
		if (!self::$server) {
			self::$server = new MockWebServer();
			self::$server->start();
		}
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

		if (!isset($data['settings'])) {
			$data['settings']['separator'] = '_';
			$data['settings']['folderPatterns'][] = [
				'name' => 'date',
				'setting' => 'Y-m-d\TH:i:s.u'
			];
			$data['settings']['folderPatterns'][] = [
				'name' => 'name'
			];
			$data['settings']['folderPatterns'][] = [
				'name' => 'userId'
			];
		}
		$file = $this->getWebhookService()->save($data);
		$this->addFile($file);
		return $file;
	}

	/**
	 * @return \OCA\Libresign\Service\WebhookService
	 */
	public function getWebhookService(): \OCA\Libresign\Service\WebhookService {
		if (!$this->webhook) {
			$this->webhook = \OC::$server->get(\OCA\Libresign\Service\WebhookService::class);
		}
		return $this->webhook;
	}

	public function addFile($file) {
		$this->files[] = $file;
	}

	/**
	 * @after
	 */
	public function libresignFileTearDown(): void {
		foreach ($this->files as $file) {
			$toRemove['uuid'] = $file['uuid'];
			foreach ($file['users'] as $user) {
				if (is_array($user)) {
					$toRemove['users'][] = [
						'email' => $user['email']
					];
				} else {
					$toRemove['users'][] = [
						'email' => $user->getEmail()
					];
				}
			}
			$this->getWebhookService()->deleteSignRequest($toRemove);
		}
	}
}
