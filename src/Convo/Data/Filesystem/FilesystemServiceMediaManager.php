<?php declare(strict_types=1);

namespace Convo\Data\Filesystem;

use Convo\Core\Media\IServiceMediaManager;
use Convo\Core\Util\SimpleFileResource;

class FilesystemServiceMediaManager implements IServiceMediaManager
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $_logger;

    private $_dataPath;
    private $_baseUrl;

    public function __construct($logger, $dataPath, $baseUrl)
    {
        $this->_logger = $logger;

        $this->_dataPath = $dataPath;
		$this->_baseUrl = $baseUrl;
    }

    public function saveMediaItem($serviceId, $file)
    {
        $media_path = $this->_getMediaFilePath($serviceId);

        $filename = $file->getFilename();

        $hash = md5($filename);
        $ext = $this->_extensionFromFilename($filename);

        $meta = [
            'filename' => $filename,
            'mime_type' => $file->getContentType(),
            'size' => $file->getSize(),
            'time_created' => time(),
            'hash' => $hash,
            'ext' => $ext
        ];

        $this->_logger->debug('Final meta for file ['.print_r($meta, true).']');

        file_put_contents($media_path.'/'.$hash.'.json', json_encode($meta, JSON_PRETTY_PRINT));
        file_put_contents($media_path.'/'.$hash.'.'.$ext, $file->getContent());

        return $hash;
    }

    public function getMediaItem($serviceId, $mediaItemId)
	{
		$info = $this->getMediaInfo($serviceId, $mediaItemId);
		$image = $this->_getMediaFilePath($serviceId).'/'.$mediaItemId.'.'.$info['ext'];

		return new SimpleFileResource(
			$info['filename'],
			$info['mime_type'],
			file_get_contents($image)
		);
	}

	public function getMediaInfo($serviceId, $mediaItemId)
    {
        $media_path = $this->_getMediaFilePath($serviceId);

        $full = $media_path.'/'.$mediaItemId.'.json';

        if (($data = file_get_contents($full)) === false) {
            throw new \Exception("Could not open file [$full] for reading.");
        }

        return json_decode($data, true);
    }

    public function getMediaUrl($serviceId, $mediaItemId)
    {
        return $this->_baseUrl.'/service-media/'.$serviceId.'/'.$mediaItemId;
    }

    // UTIL
    public function __toString()
    {
        return get_class($this).'[]';
    }

    private function _extensionFromFilename($filename)
    {
        $parts = explode('.', $filename);
        return array_pop($parts);
    }

    private function _getMediaFilePath($serviceId)
    {
        $media_folder = $this->_dataPath."/services/$serviceId/media";

        if (!is_dir($media_folder)) {
            if ((false === @mkdir($media_folder, 0777, true))) {
				throw new \Exception('Failed to create service folder ['.$media_folder.']');
			}
		}
        
        return $media_folder;
    }
}