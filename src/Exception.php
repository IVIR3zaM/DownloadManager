<?php
namespace IVIR3aM\DownloadManager;

use IVIR3aM\DownloadManager\Files\Exception as FilesException;
use IVIR3aM\DownloadManager\HttpClient\Exception as HttpClientException;
use IVIR3aM\DownloadManager\OutPuts\Exception as OutPutsException;
use IVIR3aM\DownloadManager\Proxies\Exception as ProxiesException;

/**
 * Class Exception
 * @package IVIR3aM\DownloadManager
 */
class Exception extends \Exception
{
    const CODES = [
        FilesException::class,
        HttpClientException::class,
        OutPutsException::class,
        ProxiesException::class,
    ];

    /**
     * Exception constructor.
     * @param string $message
     * @param int $code
     * @param \Exception|null $previous
     */
    public function __construct($message, $code, \Exception $previous = null)
    {
        $i = -1;
        $thisClass = get_called_class();
        foreach (self::CODES as $i => $class) {
            if ($class == $thisClass) {
                break;
            }
        }
        $i++;
        $newCode = $i . str_pad($code, 2, '0', STR_PAD_LEFT);
        parent::__construct($message, $newCode, $previous);
    }

}