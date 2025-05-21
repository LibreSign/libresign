<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\Http\Response;
use OCP\Files\SimpleFS\InMemoryFile;
use OCP\IConfig;
use OCP\IRequest;

class DevelopController extends Controller {
	public function __construct(
		IRequest $request,
		private IConfig $config,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * Get a demo PDF file to be used by test purpose
	 *
	 * To use this endpoint is necessary to enable the debug mode in your instance. To do this, run the command:
	 *
	 * `occ config:system:set debug --value true --type boolean`
	 *
	 * @return FileDisplayResponse<Http::STATUS_OK, array{Content-Type: 'application/pdf',Content-Disposition: 'inline; filename="file.pdf"'}>|DataResponse<Http::STATUS_NOT_FOUND, array<empty>, array{}>
	 *
	 * 200: PDF returned
	 * 404: Debug mode not enabled
	 */
	#[NoCSRFRequired]
	#[PublicPage]
	#[FrontpageRoute(verb: 'GET', url: '/develop/pdf')]
	public function pdf(): FileDisplayResponse|Response {
		if (!$this->isDebugMode()) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
		$file = new InMemoryFile('file.pdf', file_get_contents(__DIR__ . '/../../tests/php/fixtures/small_valid.pdf'));
		$response = new FileDisplayResponse($file);
		$response->setHeaders([
			'Content-Disposition' => 'inline; filename="file.pdf"',
			'Content-Type' => 'application/pdf',
		]);
		return $response;
	}

	public function isDebugMode(): bool {
		return $this->config->getSystemValue('debug', false) === true;
	}
}
