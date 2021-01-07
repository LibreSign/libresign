<?php

namespace OCA\Signer\Storage;

use OC\Files\Node\File;
use OC\Files\Node\Node;
use OCA\Signer\Exception\SignerException;
use OCP\Files\Folder;

class ClientStorage
{
    /** @var Folder */
    private $storage;

    public function __construct(Folder $userStorage)
    {
        $this->storage = $userStorage;
    }

    public function getFile(string $path): ?File
    {
        $node = $this->getNode($path);
        if (!$node instanceof File) {
            throw new SignerException("path {$path} is not a valid file!", 400);
        }

        return $node;
    }

    public function createFolder(string $path): Folder
    {
        if (!$this->storage->nodeExists($path)) {
            return $this->storage->newFolder($path);
        }

        $node = $this->storage->get($path);
        if (!$node instanceof Folder) {
            throw new SignerException("path {$path} already exists and is not a folder!", 400);
        }

        return $node;
    }

    public function saveFile(string $filePath, $content = null, Folder $folder = null): File
    {
        if (null === $folder) {
            $folder = $this->storage;
        }
        if (null !== $content && $folder->nodeExists($filePath)) {
            $node = $folder->get($filePath);

            if (!$node instanceof File) {
                throw new SignerException("path {$filePath} already exists and is not a file!", 400);
            }

            $node->putContent($content);

            return $node;
        }

        if (null !== $content) {
            $file = $folder->newFile($filePath);
            $file->putContent($content);

            return $file;
        }

        return $folder->newFile($filePath);
    }

    private function getNode(string $path): Node
    {
        if (!$this->storage->nodeExists($path)) {
            throw new SignerException("path {$path} is not valid!", 400);
        }

        return $this->storage->get($path);
    }
}
