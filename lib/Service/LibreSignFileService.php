<?php

namespace OCA\Libresign\Service;

use OCA\Libresign\Db\FileElement;
use OCA\Libresign\Db\FileElementMapper;
use OCA\Libresign\Db\FileMapper;
use OCP\AppFramework\Utility\ITimeFactory;

class LibreSignFileService {
	/** @var FileMapper */
	private $fileMapper;
	/** @var FileElementMapper */
	private $fileElementMapper;
	/** @var ITimeFactory */
	private $timeFactory;

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
		$fileElement = new FileElement();
		if ($uuid) {
			$file = $this->fileMapper->getByUuid($uuid);
			$fileElement->setFileId($file->getId());
		} elseif (!empty($element['fileId'])) {
			$fileElement->setFileId($element['fileId']);
		}
		$fileElement->setUserId($element['uid']);
		$fileElement->setType($element['type']);
		$fileElement->setPage($element['coordinates']['page'] ?? 1);
		$fileElement->setUrx($element['coordinates']['urx'] ?? 0);
		$fileElement->setUry($element['coordinates']['ury'] ?? 0);
		$fileElement->setLlx($element['coordinates']['llx'] ?? 0);
		$fileElement->setLly($element['coordinates']['lly'] ?? 0);
		$fileElement->setMetadata(!empty($element['metadata']) ? json_encode($element['metadata']) : null);
		if (!empty($element['elementId'])) {
			$fileElement->setId($element['elementId']);
			$this->fileElementMapper->update($fileElement);
		} else {
			$fileElement->setCreatedAt($this->timeFactory->getDateTime());
			$this->fileElementMapper->insert($fileElement);
		}
	}

	public function deleteVisibleElement($elementId) {
		$fileElement = new FileElement();
		$fileElement->fromRow(['id' => $elementId]);
		$this->fileElementMapper->delete($fileElement);
	}
}
