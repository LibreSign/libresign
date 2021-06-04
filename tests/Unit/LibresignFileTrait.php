<?php

namespace OCA\Libresign\Tests\Unit;

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
		$this->mockConfig([
			'core' => [
				'newUser.sendEmail' => 'no'
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
