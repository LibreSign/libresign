<?php

namespace OCA\Libresign\Service;

use OC\Http\Client\ClientService;
use OCP\Files\File;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;

class WebhookService {
	/** @var ClientService */
	private $client;

	public function __construct(
		IClientService $client
	) {
		$this->client = $client;
	}

	public function notifyCallback(string $uri, string $uuid, File $file): IResponse {
		$options = [
			'multipart' => [
				[
					'name' => 'uuid',
					'contents' => $uuid
				],
				[
					'name' => 'file',
					'contents' => $file->fopen('r'),
					'filename' => $file->getName()
				]
			]
		];
		return $this->client->newClient()->post($uri, $options);
	}
}
