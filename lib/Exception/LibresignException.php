<?php

namespace OCA\Libresign\Exception;

use JsonSerializable;

class LibresignException extends \Exception implements JsonSerializable
{
    public function jsonSerialize()
    {
        return ['message' => $this->getMessage()];
    }
}
