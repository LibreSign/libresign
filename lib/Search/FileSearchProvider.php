<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Search;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\File;
use OCA\Libresign\Db\SignRequestMapper;
use OCP\App\IAppManager;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\IProvider;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;
use OCP\Search\SearchResultEntry;

class FileSearchProvider implements IProvider {
	public function __construct(
		private IL10N $l10n,
		private IURLGenerator $urlGenerator,
		private IRootFolder $rootFolder,
		private IAppManager $appManager,
		private IMimeTypeDetector $mimeTypeDetector,
		private SignRequestMapper $fileMapper,
	) {
	}

	#[\Override]
	public function getId(): string {
		return 'libresign_files';
	}

	#[\Override]
	public function getName(): string {
		return $this->l10n->t('LibreSign documents');
	}

	#[\Override]
	public function getOrder(string $route, array $routeParameters): int {
		if (strpos($route, Application::APP_ID . '.') === 0) {
			return 0;
		}
		return 10;
	}

	#[\Override]
	public function search(IUser $user, ISearchQuery $query): SearchResult {
		if (!$this->appManager->isEnabledForUser(Application::APP_ID, $user)) {
			return SearchResult::complete($this->l10n->t('LibreSign documents'), []);
		}

		$term = $query->getTerm();
		$limit = $query->getLimit();
		$offset = $query->getCursor();

		try {
			$files = $this->fileMapper->getFilesToSearchProvider($user, $term, $limit, (int)$offset);
		} catch (\Exception $e) {
			return SearchResult::complete($this->l10n->t('LibreSign documents'), []);
		}

		$results = array_map(function (File $file) use ($user) {
			return $this->formatResult($file, $user);
		}, $files);

		return SearchResult::paginated(
			$this->l10n->t('LibreSign documents'),
			$results,
			$offset + $limit
		);
	}

	private function formatResult(File $file, IUser $user): SearchResultEntry {
		$userFolder = $this->rootFolder->getUserFolder($file->getUserId());
		$thumbnailUrl = '';
		$subline = '';
		$icon = '';
		$path = '';

		try {
			$nodes = $userFolder->getById($file->getNodeId());
			if (!empty($nodes)) {
				$node = array_shift($nodes);

				$icon = $this->mimeTypeDetector->mimeTypeIcon($node->getMimetype());

				$thumbnailUrl = $this->urlGenerator->linkToRouteAbsolute(
					'core.Preview.getPreviewByFileId',
					[
						'x' => 32,
						'y' => 32,
						'fileId' => $node->getId()
					]
				);

				$path = $userFolder->getRelativePath($node->getPath());
				$subline = $this->formatSubline($path);
			}
		} catch (\Exception $e) {
		}

		if ($file->getUserId() === $user->getUID()) {
			$link = $this->urlGenerator->linkToRouteAbsolute(
				'libresign.page.indexFPath',
				['path' => 'filelist/sign']
			);
			$link .= '?uuid=' . urlencode($file->getUuid());
		} else {
			$link = $this->urlGenerator->linkToRouteAbsolute(
				'libresign.page.indexFPath',
				['path' => 'validation/' . $file->getUuid()]
			);
		}

		$searchResultEntry = new SearchResultEntry(
			$thumbnailUrl,
			$file->getName(),
			$subline,
			$link,
			$icon,
		);

		$searchResultEntry->addAttribute('fileId', (string)$file->getNodeId());
		$searchResultEntry->addAttribute('path', $path);

		return $searchResultEntry;
	}

	private function formatSubline(string $path): string {
		if (strrpos($path, '/') > 0) {
			$path = ltrim(dirname($path), '/');
			// TRANSLATORS This string indicates the location of a file in a given path.
			return $this->l10n->t('in %s', [$path]);
		} else {
			return '';
		}
	}

}
