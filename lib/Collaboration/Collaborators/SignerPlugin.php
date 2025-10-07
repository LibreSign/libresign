<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Collaboration\Collaborators;

use OCA\Libresign\Db\IdentifyMethodMapper;
use OCP\Collaboration\Collaborators\ISearchPlugin;
use OCP\Collaboration\Collaborators\ISearchResult;
use OCP\Collaboration\Collaborators\SearchResultType;
use OCP\IUserSession;

class SignerPlugin implements ISearchPlugin {
	public const TYPE_SIGNER = 50; // IShare::TYPE_SIGNER = 50; It's a custom share type. Not defined in OCP\Share\IShare
	public static string $method = '';

	public function __construct(
		protected IdentifyMethodMapper $identifyMethodMapper,
		private IUserSession $userSession,
	) {
	}

	public static function setMethod(string $method): void {
		self::$method = $method;
	}

	/**
	 * {@inheritdoc}
	 */
	#[\Override]
	public function search($search, $limit, $offset, ISearchResult $searchResult): bool {
		$user = $this->userSession->getUser()->getUID();

		$limit++;
		$identifiers = $this->identifyMethodMapper->searchByIdentifierValue(
			$search,
			$user,
			self::$method,
			$limit,
			$offset,
		);

		$result = ['wide' => [], 'exact' => []];

		$hasMore = false;
		if (count($identifiers) > $limit) {
			$hasMore = true;
			array_pop($identifiers);
		}

		foreach ($identifiers as $row) {
			$item = $this->rowToSearchResultItem($row);
			if (strtolower($row['identifier_value']) === strtolower($search)
				|| strtolower($row['display_name']) === strtolower($search)
			) {
				$result['exact'][] = $item;
			} else {
				$result['wide'][] = $item;
			}
		}

		if (empty($identifiers) && self::$method && !$this->canValidateMethod()) {
			$result['exact'][] = [
				'label' => $search,
				'shareWithDisplayNameUnique' => $search,
				'method' => self::$method,
				'value' => [
					'shareWith' => $search,
					'shareType' => self::TYPE_SIGNER,
				],
			];
		}

		$type = new SearchResultType('signer');
		$searchResult->addResultSet($type, $result['wide'], $result['exact']);

		return $hasMore;
	}

	private function canValidateMethod(): bool {
		return in_array(self::$method, ['email', 'account'], true);
	}

	private function rowToSearchResultItem(array $row): array {
		$item = [
			'label' => $row['display_name'],
			'shareWithDisplayNameUnique' => $row['identifier_value'],
			'method' => $row['identifier_key'],
			'value' => [
				'shareWith' => $row['identifier_value'],
				'shareType' => self::TYPE_SIGNER,
			]
		];

		return $item;
	}
}
