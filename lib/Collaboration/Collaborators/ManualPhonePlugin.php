<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Collaboration\Collaborators;

use OCA\Libresign\Service\Identify\SignerSearchContext;
use OCP\Collaboration\Collaborators\ISearchPlugin;
use OCP\Collaboration\Collaborators\ISearchResult;
use OCP\Collaboration\Collaborators\SearchResultType;
use OCP\IConfig;
use OCP\IPhoneNumberUtil;

class ManualPhonePlugin implements ISearchPlugin {
	public const TYPE_SIGNER_MANUAL_PHONE = 53;
	private const PHONE_BASED_METHODS = ['whatsapp', 'sms', 'telegram', 'signal'];

	public function __construct(
		private IConfig $config,
		private IPhoneNumberUtil $phoneNumberUtil,
		private SignerSearchContext $searchContext,
	) {
	}

	/**
	 * {@inheritdoc}
	 */
	#[\Override]
	public function search($search, $limit, $offset, ISearchResult $searchResult): bool {
		$method = $this->searchContext->getMethod();
		$search = trim((string)$search);

		if ($search === '' || !in_array($method, self::PHONE_BASED_METHODS, true)) {
			return false;
		}

		$rawSearch = trim($this->searchContext->getRawSearch());
		if ($rawSearch === '') {
			return false;
		}

		$defaultRegion = $this->config->getSystemValueString('default_phone_region', '');
		$standardFormat = $this->phoneNumberUtil->convertToStandardFormat(
			$rawSearch,
			$defaultRegion !== '' ? $defaultRegion : null,
		);
		if ($standardFormat === null) {
			return false;
		}

		$result = [
			'exact' => [[
				'label' => $search,
				'shareWithDisplayNameUnique' => $search,
				'method' => $method,
				'value' => [
					'shareWith' => $search,
					'shareType' => self::TYPE_SIGNER_MANUAL_PHONE,
				],
			]],
			'wide' => [],
		];

		$type = new SearchResultType('manual-phone');
		$searchResult->addResultSet($type, $result['wide'], $result['exact']);

		return false;
	}
}
