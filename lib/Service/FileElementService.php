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

namespace OCA\Libresign\Service;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileElement;
use OCA\Libresign\Db\FileElementMapper;
use OCA\Libresign\Db\FileMapper;
use OCP\AppFramework\Utility\ITimeFactory;

class FileElementService {
	public function __construct(
		private FileMapper $fileMapper,
		private FileElementMapper $fileElementMapper,
		private ITimeFactory $timeFactory
	) {
	}

	public function saveVisibleElement(array $element, string $uuid = ''): FileElement {
		$fileElement = $this->getVisibleElementFromProperties($element, $uuid);
		if ($fileElement->getId()) {
			$this->fileElementMapper->update($fileElement);
		} else {
			$this->fileElementMapper->insert($fileElement);
		}
		return $fileElement;
	}

	private function getVisibleElementFromProperties(array $properties, string $uuid = ''): FileElement {
		if (!empty($properties['elementId'])) {
			$fileElement = $this->fileElementMapper->getById($properties['elementId']);
		} else {
			$fileElement = new FileElement();
			$fileElement->setCreatedAt($this->timeFactory->getDateTime());
		}
		$file = null;
		if ($uuid) {
			$file = $this->fileMapper->getByUuid($uuid);
			$fileElement->setFileId($file->getId());
		} elseif (!empty($properties['fileId'])) {
			$file = $this->fileMapper->getById($properties['fileId']);
			$fileElement->setFileId($properties['fileId']);
		}
		$coordinates = $this->translateCoordinatesToInternalNotation($properties, $file);
		$fileElement->setSignRequestId($properties['signRequestId']);
		$fileElement->setType($properties['type']);
		$fileElement->setPage($coordinates['page']);
		$fileElement->setUrx($coordinates['urx']);
		$fileElement->setUry($coordinates['ury']);
		$fileElement->setLlx($coordinates['llx']);
		$fileElement->setLly($coordinates['lly']);
		$fileElement->setMetadata(!empty($properties['metadata']) ? json_encode($properties['metadata']) : null);
		return $fileElement;
	}

	private function translateCoordinatesToInternalNotation(array $properties, File $file): array {
		$translated['page'] = $properties['coordinates']['page'] ?? 1;
		$metadata = json_decode($file->getMetadata(), true);
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

	public function translateCoordinatesFromInternalNotation(array $properties, File $file): array {
		$metadata = json_decode($file->getMetadata(), true);
		$dimension = $metadata['d'][$properties['coordinates']['page'] - 1];

		$translated['left'] = $properties['coordinates']['llx'];
		$translated['height'] = abs($properties['coordinates']['ury'] - $properties['coordinates']['lly']);
		$translated['top'] = $dimension['h'] - $properties['coordinates']['ury'];
		$translated['width'] = $properties['coordinates']['urx'] - $properties['coordinates']['llx'];

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
}
