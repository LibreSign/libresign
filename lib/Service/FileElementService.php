<?php

namespace OCA\Libresign\Service;

use OCA\Libresign\Db\FileElement;
use OCA\Libresign\Db\FileElementMapper;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Handler\TCPDILibresign;
use OCP\AppFramework\Utility\ITimeFactory;

class FileElementService {
	/** @var FileMapper */
	private $fileMapper;
	/** @var FileElementMapper */
	private $fileElementMapper;
	/** @var ITimeFactory */
	private $timeFactory;
	/** @var array */
	private $fileMetadata = [];

	public function __construct(
		FileMapper $fileMapper,
		FileElementMapper $fileElementMapper,
		ITimeFactory $timeFactory
	) {
		$this->fileMapper = $fileMapper;
		$this->fileElementMapper = $fileElementMapper;
		$this->timeFactory = $timeFactory;
	}

	public function saveVisibleElement(array $element, string $uuid = '') {
		$fileElement = $this->getVisibleElementFromProperties($element, $uuid);
		$this->fileElementMapper->insertOrUpdate($fileElement);
	}

	private function getVisibleElementFromProperties(array $properties, string $uuid = ''): FileElement {
		$fileElement = new FileElement();
		if ($uuid) {
			$file = $this->fileMapper->getByUuid($uuid);
			$fileElement->setFileId($file->getId());
		} elseif (!empty($properties['fileId'])) {
			$file = $this->fileMapper->getById($properties['fileId']);
			$fileElement->setFileId($properties['fileId']);
		}
		$metadata = $this->signFileService->getFileMetadata($file->getNodeId());
		$fileElement->setUserId($properties['uid']);
		$fileElement->setType($properties['type']);
		$fileElement->setPage($properties['coordinates']['page'] ?? 1);
		$fileElement->setUrx($properties['coordinates']['urx'] ?? 0);
		if (!empty($properties['coordinates']['ury'])) {
			$fileElement->setUry($properties['coordinates']['ury']);
		} elseif (!empty($properties['coordinates']['top'])) {

		}
		$fileElement->setLlx($properties['coordinates']['llx'] ?? 0);
		$fileElement->setLly($properties['coordinates']['lly'] ?? 0);
		$fileElement->setMetadata(!empty($properties['metadata']) ? json_encode($properties['metadata']) : null);
		if (!empty($properties['elementId'])) {
			$fileElement->setId($properties['elementId']);
		} else {
			$fileElement->setCreatedAt($this->timeFactory->getDateTime());
		}
		return $fileElement;
	}

	public function deleteVisibleElement($elementId) {
		$fileElement = new FileElement();
		$fileElement->fromRow(['id' => $elementId]);
		$this->fileElementMapper->delete($fileElement);
	}

	public function deleteVisibleElements($fileId) {
		$visibleElements = $this->fileElementMapper->getByFileId($fileId);
		foreach ($visibleElements as $visibleElement) {
			$this->fileElementMapper->delete($visibleElement);
		}
	}
}
