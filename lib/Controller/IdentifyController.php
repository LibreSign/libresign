<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Collaboration\Collaborators\SignerPlugin;
use OCA\Libresign\Middleware\Attribute\RequireManager;
use OCA\Libresign\ResponseDefinitions;
use OCA\Libresign\Service\Identify\ResultEnricher;
use OCA\Libresign\Service\Identify\ResultFilter;
use OCA\Libresign\Service\Identify\ResultFormatter;
use OCA\Libresign\Service\Identify\SearchNormalizer;
use OCA\Libresign\Service\IdentifyMethod\Account;
use OCA\Libresign\Service\IdentifyMethod\Email;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\Collaboration\Collaborators\ISearch;
use OCP\IRequest;
use OCP\Share\IShare;

/**
 * @psalm-import-type LibresignIdentifyAccount from ResponseDefinitions
 */
class IdentifyController extends AEnvironmentAwareController {
	private array $shareTypes = [];

	public function __construct(
		IRequest $request,
		private ISearch $collaboratorSearch,
		private Email $identifyEmailMethod,
		private Account $identifyAccountMethod,
		private SearchNormalizer $searchNormalizer,
		private ResultFilter $resultFilter,
		private ResultFormatter $resultFormatter,
		private ResultEnricher $resultEnricher,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * List possible signers
	 *
	 * Used to identify who can sign the document. The return of this endpoint is related with Administration Settiongs > LibreSign > Identify method.
	 *
	 * @param string $search search params
	 * @param string $method filter by method (email, account, sms, signal, telegram, whatsapp, xmpp)
	 * @param int $page the number of page to return. Default: 1
	 * @param int $limit Total of elements to return. Default: 25
	 * @return DataResponse<Http::STATUS_OK, LibresignIdentifyAccount[], array{}>
	 *
	 * 200: Certificate saved with success
	 * 400: No file provided or other problem with provided file
	 */
	#[NoAdminRequired]
	#[RequireManager]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/identify-account/search', requirements: ['apiVersion' => '(v1)'])]
	public function search(string $search = '', string $method = '', int $page = 1, int $limit = 25): DataResponse {
		$search = $this->searchNormalizer->normalize($search, $method);

		// Only search for string larger than a minimum length
		if (strlen($search) < 1) {
			return new DataResponse([]);
		}

		$shareTypes = $this->getShareTypes();
		$offset = $limit * ($page - 1);
		
		$this->registerPlugin($method);
		[$result] = $this->collaboratorSearch->search($search, $shareTypes, false, $limit, $offset);
		
		// Process results through filters and formatters
		$result['exact'] = $this->resultFilter->unify($result['exact']);
		$result = $this->resultFilter->unify($result);
		$result = $this->resultFilter->excludeEmpty($result);
		
		$return = $this->resultFormatter->formatForNcSelect($result);
		$return = $this->resultEnricher->addHerselfAccount($return, $search);
		$return = $this->resultEnricher->addHerselfEmail($return, $search);
		$return = $this->resultFormatter->replaceShareTypeWithMethod($return);
		$return = $this->resultEnricher->addEmailNotificationPreference($return);
		$return = $this->resultFilter->excludeNotAllowed($return);

		return new DataResponse($return);
	}

	private function registerPlugin(string $method): void {
		SignerPlugin::setMethod($method);

		$refObject = new \ReflectionObject($this->collaboratorSearch);
		$refProperty = $refObject->getProperty('pluginList');

		$plugins = $refProperty->getValue($this->collaboratorSearch);
		$plugins[SignerPlugin::TYPE_SIGNER] = [SignerPlugin::class];

		$refProperty->setValue($this->collaboratorSearch, $plugins);
	}

	private function getShareTypes(): array {
		if (count($this->shareTypes) > 0) {
			return $this->shareTypes;
		}
		$settings = $this->identifyEmailMethod->getSettings();
		if ($settings['enabled']) {
			$this->shareTypes[] = IShare::TYPE_EMAIL;
		}
		$settings = $this->identifyAccountMethod->getSettings();
		if ($settings['enabled']) {
			$this->shareTypes[] = IShare::TYPE_USER;
		}

		$this->shareTypes[] = SignerPlugin::TYPE_SIGNER;
		return $this->shareTypes;
	}
}
