<?php

namespace OCA\Dsv\Service;

use NcJoes\PopplerPhp\PopplerOptions\ConsoleFlags;
use NcJoes\PopplerPhp\PopplerOptions\CredentialOptions;
use NcJoes\PopplerPhp\PopplerOptions\DateFlags;
use NcJoes\PopplerPhp\PopplerOptions\EncodingOptions;
use NcJoes\PopplerPhp\PopplerOptions\InfoFlags;
use NcJoes\PopplerPhp\PopplerOptions\PageRangeOptions;
use NcJoes\PopplerPhp\PopplerUtil;

class PdfSig extends PopplerUtil
{
    use CredentialOptions;
    use DateFlags;
    use EncodingOptions;
    use PageRangeOptions;
    use ConsoleFlags;
    use InfoFlags;

    /**
     * @var
     */
    private $signatures;

    /**
     * @param string $pdfFile
     *
     * @throws Exceptions\PopplerPhpException
     */
    public function __construct($pdfFile = '', array $options = [])
    {
        $this->setRequireOutputDir(false);
        $this->binFile = '/tmp/poppler-20.08.0/build/utils/pdfsig';

        return parent::__construct($pdfFile, $options);
    }

    /**
     * @return array|mixed
     */
    public function utilOptions()
    {
        return [];
    }

    /**
     * @return array|mixed
     */
    public function utilOptionRules()
    {
        return [];
    }

    /**
     * @return array|mixed
     */
    public function utilFlags()
    {
        return $this->allConsoleFlags();
    }

    /**
     * @return array|mixed
     */
    public function utilFlagRules()
    {
        return [];
    }

    /**
     * @return mixed|null
     */
    public function outputExtension()
    {
        return null;
    }

    public function getSignature()
    {
        $content = $this->shellExec();
        $lines = explode("\n", $content);

        $signatures = [];
        foreach ($lines as $item) {
            $isFirstLevel = preg_match('/^(Signature\s#\d)/', $item, $match);
            if ($isFirstLevel) {
                $signatures[$match[1]] = [];
                continue;
            }

            $lastSignature = array_key_last($signatures);

            $match = [];
            $isSecondLevel = preg_match('/^\s+-\s(.+):\s(.*)/', $item, $match);
            if ($isSecondLevel) {
                $signatures[$lastSignature][$match[1]] = $match[2];
            }
        }

        return $signatures;
    }
}
