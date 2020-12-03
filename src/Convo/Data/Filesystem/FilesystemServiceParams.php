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

    public function __construct( \Psr\Log\LoggerInterface $logger, $basePath, \Convo\Core\Params\IServiceParamsScope $scope, $storeAsGz)
    {
        parent::__construct( $logger, $scope);
    	$this->_basePath 	=   \Convo\Core\Util\StrUtil::removeTrailingSlashes( $basePath);
        $this->_storeAsGz   =   $storeAsGz;
    }

    public function getData()
    {
        $data = array();
        $gzFileName	=	$this->_getFilename('.gz');
    	$jsonFileName	=	$this->_getFilename('.json');

    	if ( file_exists( $jsonFileName) || file_exists( $gzFileName)) {
            if (file_exists($gzFileName)) {
                $data = json_decode(gzinflate(file_get_contents( $gzFileName)), true);
                if ( $data === false) {
                    throw new \Exception( 'Invalid JSON in ['.$gzFileName.']');
                }
            } else if (file_exists($jsonFileName)){
                $data = json_decode( file_get_contents( $jsonFileName), true);
                if ( $data === false) {
                    throw new \Exception( 'Invalid JSON in ['.$jsonFileName.']');
                }
            }
    	}

    	return $data;
    }

    protected function _storeData( $data)
    {
    	$this->_ensureFolder();
    	$jsonFile = $this->_getFilename('.json');
    	$gZipFile = $this->_getFilename('.gz');

    	if ($this->_storeAsGz) {
            if (file_exists($jsonFile)) {
                $this->_logger->info("Going to migrate data form [". $jsonFile . "] into [" . $gZipFile . "]");

                $backupFileContents = json_decode(file_get_contents($jsonFile), true);
                $newData = array_merge($backupFileContents, $data);

                $newDataStringContent = json_encode($newData, JSON_PRETTY_PRINT);
                $gzData = gzdeflate($newDataStringContent, 9);
                file_put_contents($gZipFile, $gzData);

                unlink($jsonFile);
            } else {
                $this->_logger->info("Creating new file [" . $gZipFile . "]");
                $gzData = gzdeflate(json_encode($data, JSON_PRETTY_PRINT), 9);
                file_put_contents($gZipFile, $gzData);
            }
        } else if (!$this->_storeAsGz) {
    	    if (file_exists($gZipFile)) {
                $this->_logger->info("Going to migrate data form [". $gZipFile . "] into [" . $jsonFile . "]");

                $backupFileContents = json_decode(gzinflate(file_get_contents( $gZipFile)), true);
                $newData = array_merge($backupFileContents, $data);

                $newDataStringContent = json_encode($newData, JSON_PRETTY_PRINT);
                file_put_contents($jsonFile, $newDataStringContent);

                unlink($gZipFile);
            } else {
    	        $this->_logger->info("Creating new file " . $jsonFile);
                $newDataStringContent = json_encode($data, JSON_PRETTY_PRINT);
                file_put_contents($jsonFile, $newDataStringContent);
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
