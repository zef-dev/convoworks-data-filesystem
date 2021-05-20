<?php declare(strict_types=1);

namespace Convo\Data\Filesystem;

class FilesystemServiceParams extends \Convo\Core\Params\AbstractServiceParams
{
	/**
	 * Path to storage folder
	 *
	 * @var string
	 */
	private $_basePath;

    private $_storeAsGz = true;

    private $_jsonFilename;
    private $_gzFilename;

    public function __construct( \Psr\Log\LoggerInterface $logger, $basePath, \Convo\Core\Params\IServiceParamsScope $scope, $storeAsGz)
    {
        parent::__construct( $logger, $scope);
    	$this->_basePath 	=   \Convo\Core\Util\StrUtil::removeTrailingSlashes( $basePath);
        $this->_storeAsGz   =   $storeAsGz;

        $this->_jsonFilename = $this->_getFilename('.json');
        $this->_gzFilename = $this->_getFilename('.json.gz');
    }

    public function getData()
    {
        $data = [];

        if (file_exists($this->_gzFilename))
        {
            $data = json_decode(gzdecode(file_get_contents($this->_gzFilename)), true);
            
            if (!$data || json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON in ['.$this->_gzFilename.']['.json_last_error_msg().']');
            }
        }
        else if (file_exists($this->_jsonFilename))
        {
            $data = json_decode(file_get_contents($this->_jsonFilename), true);

            if (!$data || json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON in ['.$this->_jsonFilename.']['.json_last_error_msg().']');
            }
        }

    	return $data;
    }

    protected function _storeData( $data)
    {
    	$this->_ensureFolder();

    	if ($this->_storeAsGz)
        {
            if (file_exists($this->_jsonFilename)) {
                $this->_logger->info("Going to migrate data from [". $this->_jsonFilename . "] into [" . $this->_gzFilename . "]");

                $backupFileContents = json_decode(file_get_contents($this->_jsonFilename), true);
                $newData = array_merge($backupFileContents, $data);

                $newDataStringContent = json_encode($newData);
                $gzData = gzencode($newDataStringContent);
                file_put_contents($this->_gzFilename, $gzData);

                unlink($this->_jsonFilename);
            } else {
                $this->_logger->info("Creating new file [" . $this->_gzFilename . "]");
                $gzData = gzencode(json_encode($data));
                file_put_contents($this->_gzFilename, $gzData);
            }
        }
        else
        {
    	    if (file_exists($this->_gzFilename)) {
                $this->_logger->info("Going to migrate data form [". $this->_gzFilename . "] into [" . $this->_jsonFilename . "]");

                $backupFileContents = json_decode(gzdecode(file_get_contents( $this->_gzFilename)), true);
                $newData = array_merge($backupFileContents, $data);

                $newDataStringContent = json_encode($newData);
                file_put_contents($this->_jsonFilename, $newDataStringContent);

                unlink($this->_gzFilename);
            } else {
    	        $this->_logger->info("Creating new file " . $this->_jsonFilename);
                $newDataStringContent = json_encode($data);
                file_put_contents($this->_jsonFilename, $newDataStringContent);
            }
        }
    }

    private function _getFolder()
    {
    	$folder	=	$this->_basePath.'/'.$this->_scope->getScopeType().'/'.$this->_scope->getLevelType().'/'.$this->_scope->getServiceId();
    	return $folder;
    }

    private function _ensureFolder()
    {
    	$folder	=	$this->_basePath.'/'.$this->_scope->getScopeType().'/'.$this->_scope->getLevelType().'/'.$this->_scope->getServiceId();

    	if ( !is_dir( $folder)) {
    		mkdir( $folder, 0777, true);
    	}

    	if ( !is_dir( $folder)) {
    		throw new \Exception( 'Could not create folder ['.$folder.']');
    	}
    	return $folder;
    }

    private function _getFilename($extension)
    {
    	$folder		=	$this->_getFolder();
    	$root		=	$this->_scope->getKey();
    	$root		=	md5( $root);
    	$filename	=	$folder.'/'.$root.$extension;
    	return $filename;
    }

    // UTIL
    public function __toString()
    {
    	return parent::__toString().'['.$this->_basePath.']';
    }
}
