<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileElement;
use OCA\Libresign\Db\FileElementMapper;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\ResponseDefinitions;
use OCP\AppFramework\Utility\ITimeFactory;

/**
 * @psalm-import-type LibresignVisibleElement from ResponseDefinitions
 */
class FileElementService {
	public function __construct(
		private FileMapper $fileMapper,
		private FileElementMapper $fileElementMapper,
		private ITimeFactory $timeFactory,
	) {
	}

	public function saveVisibleElement(array $element): FileElement {
		$fileElement = $this->getVisibleElementFromProperties($element);
		if ($fileElement->getId()) {
			$this->fileElementMapper->update($fileElement);
		} else {
			$this->fileElementMapper->insert($fileElement);
		}
		return $fileElement;
	}

	private function getVisibleElementFromProperties(array $properties): FileElement {
		if (!empty($properties['elementId'])) {
			$fileElement = $this->fileElementMapper->getById($properties['elementId']);
		} else {
			$fileElement = new FileElement();
			$fileElement->setCreatedAt($this->timeFactory->getDateTime());
		}
		$file = null;
		if (!empty($properties['uuid'])) {
			$file = $this->fileMapper->getByUuid($properties['uuid']);
			$fileElement->setFileId($file->getId());
		} elseif (!empty($properties['fileId'])) {
			$file = $this->fileMapper->getById($properties['fileId']);
			$fileElement->setFileId($properties['fileId']);
		}
		if (!$file) {
			throw new \InvalidArgumentException('File not found for visible element');
		}
		$coordinates = $this->translateCoordinatesToInternalNotation($properties, $file);
		$fileElement->setSignRequestId($properties['signRequestId']);
		$fileElement->setType($properties['type']);
		$fileElement->setPage($coordinates['page']);
		$fileElement->setUrx($coordinates['urx']);
		$fileElement->setUry($coordinates['ury']);
		$fileElement->setLlx($coordinates['llx']);
		$fileElement->setLly($coordinates['lly']);
		$fileElement->setMetadata($properties['metadata'] ?? null);
		return $fileElement;
	}

	private function translateCoordinatesToInternalNotation(array $properties, File $file): array {
		$translated['page'] = $properties['coordinates']['page'] ?? 1;
		$metadata = $file->getMetadata();
		$dimension = $metadata['d'][$translated['page'] - 1];

		if (isset($properties['coordinates']['ury'])) {
			$translated['ury'] = $properties['coordinates']['ury'];
		} elseif (isset($properties['coordinates']['top'])) {
			$translated['ury'] = $dimension['h'] - $properties['coordinates']['top'];
		} else {
			$translated['ury'] = 0;
		}

		if (isset($properties['coordinates']['lly'])) {
			$translated['lly'] = $properties['coordinates']['lly'];
		} elseif (isset($properties['coordinates']['height'])) {
			if ($properties['coordinates']['height'] > $translated['ury']) {
				$translated['ury'] = $properties['coordinates']['height'];
				$translated['lly'] = 0;
			} else {
				$translated['lly'] = $translated['ury'] - $properties['coordinates']['height'];
			}
		} else {
			$translated['lly'] = 0;
		}

		if (isset($properties['coordinates']['llx'])) {
			$translated['llx'] = $properties['coordinates']['llx'];
		} elseif (isset($properties['coordinates']['left'])) {
			$translated['llx'] = $properties['coordinates']['left'];
		} else {
			$translated['llx'] = 0;
		}

		if (isset($properties['coordinates']['urx'])) {
			$translated['urx'] = $properties['coordinates']['urx'];
		} elseif (isset($properties['coordinates']['width'])) {
			$translated['urx'] = $translated['llx'] + $properties['coordinates']['width'];
		} else {
			$translated['urx'] = 0;
		}
		if ($translated['ury'] < $translated['lly']) {
			$temp = $translated['ury'];
			$translated['ury'] = $translated['lly'];
			$translated['lly'] = $temp;
		}
		if ($translated['urx'] < $translated['llx']) {
			$temp = $translated['urx'];
			$translated['urx'] = $translated['llx'];
			$translated['llx'] = $temp;
		}

		return $translated;
	}

	public function deleteVisibleElement(int $elementId): void {
		$fileElement = new FileElement();
		$fileElement = $fileElement->fromRow(['id' => $elementId]);
		$this->fileElementMapper->delete($fileElement);
	}

	public function deleteVisibleElements(int $fileId): void {
		$visibleElements = $this->fileElementMapper->getByFileId($fileId);
		foreach ($visibleElements as $visibleElement) {
			$this->fileElementMapper->delete($visibleElement);
		}
	}

	/**
	 * Return visible elements formatted for API responses for given file and signRequestId
	 *
	 * @psalm-return list<LibresignVisibleElement>
	 */
	public function getVisibleElementsForSignRequest(File $file, int $signRequestId): array {
		$rows = $this->fileElementMapper->getByFileIdAndSignRequestId($file->getId(), $signRequestId);
		return $this->formatVisibleElements($rows, $file->getMetadata());
	}

	/**
	 * Format visible elements returned from DB rows for API responses.
	 *
	 * @param array<int, FileElement> $visibleElements Array of file elements as returned by mappers
	 * @param array $fileMetadata Metadata of the file (expects page dimensions under key 'd')
	 * @psalm-return list<LibresignVisibleElement>
	 */
	public function formatVisibleElements(array $visibleElements, array $fileMetadata): array {
		$result = [];
		foreach ($visibleElements as $fileElement) {
			$dimension = $fileMetadata['d'][$fileElement->getPage() - 1] ?? ['h' => 0];
			$height = (int)abs($fileElement->getUry() - $fileElement->getLly());
			$width = (int)abs($fileElement->getUrx() - $fileElement->getLlx());
			$top = (int)abs($dimension['h'] - $fileElement->getUry());
			$left = (int)$fileElement->getLlx();
			$result[] = [
				'elementId' => $fileElement->getId(),
				'signRequestId' => $fileElement->getSignRequestId(),
				'fileId' => $fileElement->getFileId(),
				'type' => $fileElement->getType(),
				'coordinates' => [
					'page' => $fileElement->getPage(),
					'urx' => $fileElement->getUrx(),
					'ury' => $fileElement->getUry(),
					'llx' => (int)$fileElement->getLlx(),
					'lly' => (int)$fileElement->getLly(),
					'left' => $left,
					'top' => $top,
					'width' => $width,
					'height' => $height,
				],
			];
		}
		return $result;
	}
}
