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

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
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
	public function pdf(): FileDisplayResponse|Response {
		if (!$this->isDebugMode()) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
		$file = new InMemoryFile('file.pdf', file_get_contents(__DIR__ . '/../../tests/fixtures/small_valid.pdf'));
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
