<?php

namespace OCA\Signer\Exception;

use JsonSerializable;

class SignerException extends \Exception implements JsonSerializable
{
    public function jsonSerialize()
    {
        return ['message' => $this->getMessage()];
    }
}
