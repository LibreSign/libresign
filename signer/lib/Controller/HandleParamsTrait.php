<?php

namespace OCA\Signer\Controller;

use OCA\Signer\Exception\SignerException;

trait HandleParamsTrait
{
    protected function checkParams(array $params): void
    {
        foreach ($params as $key => $param) {
            if (empty($param)) {
                throw new SignerException("parameter '{$key}' is required!", 400);
            }
        }
    }
}
