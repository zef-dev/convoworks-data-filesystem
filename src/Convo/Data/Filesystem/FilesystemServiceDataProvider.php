<?php declare(strict_types=1);

namespace Convo\Data\Filesystem;

use Convo\Core\Publish\IPlatformPublisher;
use Convo\Core\IAdminUser;
use Convo\Core\IServiceDataProvider;
use Convo\Core\Rest\NotAuthorizedException;
use Convo\Core\AbstractServiceDataProvider;

class FilesystemServiceDataProvider extends AbstractServiceDataProvider
{

	private $_basePath;


	public function __construct( \Psr\Log\LoggerInterface $logger, $basePath)
	{
	    parent::__construct( $logger);
		$this->_basePath	=	\Convo\Core\Util\StrUtil::removeTrailingSlashes( $basePath);
	}

	/**
	 * {@inheritDoc}
	 * @see \Convo\Core\IServiceDataProvider::getAllServices()
	 */
	public function getAllServices( \Convo\Core\IAdminUser $user) {

		$full_path	=	$this->_basePath.'/services/';

		if ( !is_dir( $full_path)) {
			throw new \Exception( 'Expected to have folder at ['.$full_path.']');
		}

		$this->_logger->debug( 'Loading folders ['.$full_path.']');

		$all		=	array();

		$dirs		=	array_filter( glob( $full_path.'*'), 'is_dir');

		$this->_logger->debug( 'Found ['.count( $dirs).']');

		foreach ( $dirs as $filename)
		{
			$service_id	=	basename( $filename);
			$this->_logger->debug( 'Handling service ['.$service_id.']');
			try {
			    $serviceMeta = $this->getServiceMeta( $user, $service_id);
			    if ($this->_checkServiceOwner($user, $serviceMeta)) {
                    $all[]		=	$serviceMeta;
                }
			} catch ( \Convo\Core\DataItemNotFoundException $e) {
				$this->_logger->warning( $e->getMessage());
			}
		}

		return $all;
	}

	/**
	 * {@inheritDoc}
	 * @see \Convo\Core\IServiceDataProvider::createNewService()
	 */
	public function createNewService( \Convo\Core\IAdminUser $user, $serviceName, $defaultLanguage, $isPrivate, $serviceAdmins, $workflowData)
	{
	    $service_id                 =   $this->_generateIdFromName( $serviceName);

	    // META
	    $meta_data					=	$this->_getDefaultMeta( $user, $service_id, $serviceName);
	    $meta_data['service_id']	=	$service_id;
	    $meta_data['name']			=	$serviceName;
        $meta_data['default_language']	=	$defaultLanguage;
	    $meta_data['owner']			=	$user->getEmail();
	    $meta_data['admins']        =   $serviceAdmins;
	    $meta_data['is_private']    =   $isPrivate;
	    $this->_saveServiceFile( $service_id, 'meta.json', $meta_data);

	    // CONFIG
	    $this->_saveServiceFile( $service_id, 'platform-config.json', []);

	    // WORKFLOW
	    $service_data					=   array_merge( IServiceDataProvider::DEFAULT_WORKFLOW, $workflowData);
	    $service_data['name']   		=	$serviceName;
	    $service_data['service_id']		=	$service_id;

	    $service_data['time_updated']             =   time();
	    $service_data['intents_time_updated']     =   time();

	    $this->_saveServiceFile( $service_id, 'workflow.json', $service_data);

	    return $service_id;
	}

	/**
	 * {@inheritDoc}
	 * @see \Convo\Core\IServiceDataProvider::deleteService()
	 */
	public function deleteService( \Convo\Core\IAdminUser $user, $serviceId)
	{
	    $service_meta = $this->getServiceMeta($user, $serviceId);

	    $is_owner = $user->getEmail() === $service_meta['owner'];
	    $is_admin = in_array($user->getEmail(), $service_meta['admins']);

	    if (!($is_owner || $is_admin))
	    {
	        throw new \Exception('User ['.$user->getName().']['.$user->getEmail().'] is not allowed to delete skill ['.$serviceId.']');
	    }

	    $service_dir = $this->_basePath.'/services/'.$serviceId;

	    $it = new \RecursiveDirectoryIterator($service_dir, \RecursiveDirectoryIterator::SKIP_DOTS);
	    $files = new \RecursiveIteratorIterator($it, \RecursiveIteratorIterator::CHILD_FIRST);

	    foreach ($files as $file)
	    {
	        if ($file->isDir()) {
	            rmdir($file->getRealPath());
	        } else {
	            unlink($file->getRealPath());
	        }
	    }

	    rmdir($service_dir);
	}

	/**
	 * {@inheritDoc}
	 * @see \Convo\Core\IServiceDataProvider::getServiceData()
	 */
	public function getServiceData( \Convo\Core\IAdminUser $user, $serviceId, $versionId)
	{
	    $data = null;
	    if ( $versionId === IPlatformPublisher::MAPPING_TYPE_DEVELOP) {
	        $data = $this->_loadServiceFile( $serviceId, 'workflow.json');
	    } else {
	        $data = $this->_loadServiceFile( $serviceId, 'workflow.json', $versionId);
	    }

	    if($data !== null) {
	        $serviceMeta = $this->getServiceMeta( $user, $serviceId);
	        if(!$this->_checkServiceOwner($user, $serviceMeta)) {
	            $errorMessage = "User [" . $user->getEmail() . "] is not authorized to open the service [" . $serviceId ."]";
	            throw new NotAuthorizedException($errorMessage);
	        }
	    }

	    return array_merge( IServiceDataProvider::DEFAULT_WORKFLOW, $data);
	}

	/**
	 * {@inheritDoc}
	 * @see \Convo\Core\IServiceDataProvider::saveServiceData()
	 */
	public function saveServiceData( \Convo\Core\IAdminUser $user, $serviceId, $data)
	{
	    $data['time_updated']   =   time();
	    $this->_saveServiceFile( $serviceId, 'workflow.json', $data);
	    return $data;
	}

	/**
	 * {@inheritDoc}
	 * @see \Convo\Core\IServiceDataProvider::getServiceMeta()
	 */
	public function getServiceMeta( \Convo\Core\IAdminUser $user, $serviceId, $versionId=null)
	{
	    $meta     =   $this->_loadServiceFile( $serviceId, 'meta.json', $versionId);
	    if ( $versionId && $versionId !== IPlatformPublisher::MAPPING_TYPE_DEVELOP) {
	        return $meta;
	    }
	    return array_merge( IServiceDataProvider::DEFAULT_META, $meta);
	}

	/**
	 * {@inheritDoc}
	 * @see \Convo\Core\IServiceDataProvider::saveServiceMeta()
	 */
	public function saveServiceMeta( \Convo\Core\IAdminUser $user, $serviceId, $meta)
	{
	    $meta['time_updated']   =   time();
	    $this->_saveServiceFile( $serviceId, 'meta.json', $meta);
	    return $meta;
	}

	/**
	 * {@inheritDoc}
	 * @see \Convo\Core\IServiceDataProvider::markVersionAsRelease()
	 */
	public function markVersionAsRelease( \Convo\Core\IAdminUser $user, $serviceId, $versionId, $releaseId)
	{
	    $meta   =   $this->getServiceMeta( $user, $serviceId, $versionId);
	    $meta['release_id']     =   $releaseId;
	    $meta['time_updated']   =   time();
	    $this->_saveServiceFile( $serviceId, 'meta.json', $meta, $versionId);
	    return $meta;
	}

	/**
	 * {@inheritDoc}
	 * @see \Convo\Core\IServiceDataProvider::getAllServiceVersions()
	 */
	public function getAllServiceVersions( \Convo\Core\IAdminUser $user, $serviceId) {

	    $full_path	=	$this->_basePath.'/services/'.$serviceId.'/versions/';

	    if ( !is_dir( $full_path)) {
	        mkdir( $full_path, 0777, true);
	        if ( !is_dir( $full_path)) {
	            throw new \Exception( 'Failed to create service versions folder ['.$full_path.']');
	        }
	    }

	    $this->_logger->debug( 'Loading folders ['.$full_path.']');

	    $all		=	array();

	    $dirs		=	array_filter( glob( $full_path.'*'), 'is_dir');

	    $this->_logger->debug( 'Found ['.count( $dirs).']');

	    foreach ( $dirs as $filename) {
	        $all[]	     =	basename( $filename);
	    }

	    return $all;
	}

	/**
	 * {@inheritDoc}
	 * @see \Convo\Core\IServiceDataProvider::createServiceVersion()
	 */
	public function createServiceVersion(\Convo\Core\IAdminUser $user, $serviceId, $workflow, $config, $versionTag=null)
	{
	    $version_id	=	$this->_getNextServiceVersion( $serviceId);
	    $this->_logger->debug( 'Got new version ['.$version_id.'] for service ['.$serviceId.']');

	    $meta      =   [
	        'service_id' => $serviceId,
	        'version_id' => $version_id,
	        'version_tag' => $versionTag,
	        'release_id' => null,
	        'time_updated' => time(),
	        'time_created' => time(),
	    ];

	    $this->_saveServiceFile( $serviceId, 'workflow.json', $workflow, $version_id);
	    $this->_saveServiceFile( $serviceId, 'platform-config.json', $config, $version_id);
	    $this->_saveServiceFile( $serviceId, 'meta.json', $meta, $version_id);

	    return $version_id;
	}

	/**
	 * {@inheritDoc}
	 * @see \Convo\Core\IServiceDataProvider::createRelease()
	 */
	public function createRelease( IAdminUser $user, $serviceId, $platformId, $type, $stage, $alias, $versionId)
	{
	    $service_folder	=	$this->_basePath.'/services/'.$serviceId.'/releases';

	    // SERVICE FOLER
	    if ( !is_dir( $service_folder)) {
	        mkdir( $service_folder, 0777, true);
	        if ( !is_dir( $service_folder)) {
	            throw new \Exception( 'Failed to create service releases folder ['.$service_folder.']');
	        }
	    }

	    $release_id    =   $this->_getNextReleseId( $serviceId);
	    $full_path	   =   $service_folder.'/'.$release_id.'.json';

	    $this->_logger->debug( 'Saving service ['.$serviceId.'] release ['.$release_id.'] to ['.$full_path.']');

	    $data      =   array_merge( IServiceDataProvider::DEFAULT_RELEASE, [
	        'service_id' => $serviceId,
	        'release_id' => $release_id,
	        'version_id' => $versionId,
	        'platform_id' => $platformId,
	        'type' => $type,
	        'stage' => $stage,
	        'alias' => $alias,
	        'time_created' => time(),
	        'time_updated' => time()
	    ]);

	    $ret	=	file_put_contents( $full_path, json_encode( $data, JSON_PRETTY_PRINT));
	    if ( $ret === false) {
	        throw new \Exception( 'Could not save service release ['.$serviceId.'] release ['.$release_id.'] to ['.$full_path.']');
	    }

	    return $release_id;
	}

	/**
	 * {@inheritDoc}
	 * @see \Convo\Core\IServiceDataProvider::getReleaseData()
	 */
	public function getReleaseData( IAdminUser $user, $serviceId, $releaseId)
	{
	    $full_path	=	$this->_basePath.'/services/'.$serviceId.'/releases/'.$releaseId.'.json';

	    $this->_logger->debug( 'Trying to load service ['.$serviceId.']['.$releaseId.'] release data from ['.$full_path.']');

	    if ( !is_file( $full_path)) {
	        throw new \Convo\Core\DataItemNotFoundException( 'Service release data not found at ['.$full_path.']');
	    }

	    $data	=	json_decode( file_get_contents( $full_path), true);
	    if ( $data === false) {
	        throw new \Exception( 'Invalid service ['.$serviceId.'] release ['.$releaseId.']. Reason ['.json_last_error().']['.json_last_error_msg().']');
	    }

	    return $data;

	}

	/**
	 * {@inheritDoc}
	 * @see \Convo\Core\IServiceDataProvider::promoteRelease()
	 */
	public function promoteRelease( \Convo\Core\IAdminUser $user, $serviceId, $releaseId, $type, $stage) {
	    $release        =   $this->getReleaseData( $user, $serviceId, $releaseId);
	    $release        =   array_merge( $release, [ 'type' => $type, 'stage' => $stage, 'time_updated' => time() ]);
	    $service_folder	=	$this->_basePath.'/services/'.$serviceId.'/releases';
	    $full_path	    =   $service_folder.'/'.$releaseId.'.json';
	    $ret	=	file_put_contents( $full_path, json_encode( $release, JSON_PRETTY_PRINT));
	    if ( $ret === false) {
	        throw new \Exception( 'Could not save service release ['.$serviceId.'] release ['.$releaseId.'] to ['.$full_path.']');
	    }
	}

	/**
	 * {@inheritDoc}
	 * @see \Convo\Core\IServiceDataProvider::setReleaseVersion()
	 */
	public function setReleaseVersion( \Convo\Core\IAdminUser $user, $serviceId, $releaseId, $versionId) {
	    $release        =   $this->getReleaseData( $user, $serviceId, $releaseId);
	    $release        =   array_merge( $release, [ 'version_id' => $versionId, 'time_updated' => time() ]);
	    $service_folder	=	$this->_basePath.'/services/'.$serviceId.'/releases';
	    $full_path	    =   $service_folder.'/'.$releaseId.'.json';
	    $ret	=	file_put_contents( $full_path, json_encode( $release, JSON_PRETTY_PRINT));
	    if ( $ret === false) {
	        throw new \Exception( 'Could not save service release ['.$serviceId.'] release ['.$releaseId.'] to ['.$full_path.']');
	    }
	}

	/**
	 * {@inheritDoc}
	 * @see \Convo\Core\IServiceDataProvider::getServicePlatformConfig()
	 */
	public function getServicePlatformConfig( \Convo\Core\IAdminUser $user, $serviceId, $versionId)
	{
	    try {
	        if ( $versionId === IPlatformPublisher::MAPPING_TYPE_DEVELOP) {
	            return $this->_loadServiceFile( $serviceId, 'platform-config.json');
	        }
	    } catch ( \Convo\Core\DataItemNotFoundException $e) {
	        return [];
	    }

	    return $this->_loadServiceFile( $serviceId, 'platform-config.json', $versionId);
	}

	/**
	 * {@inheritDoc}
	 * @see \Convo\Core\IServiceDataProvider::updateServicePlatformConfig()
	 */
	public function updateServicePlatformConfig( \Convo\Core\IAdminUser $user, $serviceId, $config)
	{
	    $this->_saveServiceFile( $serviceId, 'platform-config.json', $config);
	}

	// COMMON
	private function _getNextServiceVersion( $serviceId) {
		$base	=	$this->_basePath.'/services/'.$serviceId.'/versions/';

		if ( !is_dir( $base)) {
			$this->_logger->debug( 'No versions so far. Returning [1]');
			return sprintf('%08d', 1);
		}

		$dirs		=	array_filter( glob( $base.'*'), 'is_dir');

		$this->_logger->debug( 'Found ['.count( $dirs).']');

		$max	=	0;
		foreach ( $dirs as $filename)
		{
			$version_id	=	intval( basename( $filename));
			$this->_logger->debug( 'version check ['.$version_id.']['.basename( $filename).']');
			if ( $version_id > $max) {
				$max	=	$version_id;
			}
		}

		$max++;
		$this->_logger->debug( 'New max ['.$max.']');
		return sprintf('%08d', $max);
	}


	private function _getNextReleseId( $serviceId) {
		$base	=	$this->_basePath.'/services/'.$serviceId.'/releases/';

		if ( !is_dir( $base)) {
			$this->_logger->debug( 'No releases so far. Returning [1]');
			return sprintf('%08d', 1);
		}

		$dirs		=	array_filter( glob( $base.'*'), 'is_file');

		$this->_logger->debug( 'Found ['.count( $dirs).']');

		$max	=	0;
		foreach ( $dirs as $filename)
		{
		    $version_id	=	intval( str_replace( '.json', '', basename( $filename)));
			$this->_logger->debug( 'version check ['.$version_id.']['.basename( $filename).']');
			if ( $version_id > $max) {
				$max	=	$version_id;
			}
		}

		$max++;
		$this->_logger->debug( 'New max ['.$max.']');
		return sprintf('%08d', $max);
	}

	private function _saveServiceFile( $serviceId, $file, $data, $versionId=null)
	{
	    if ( $versionId && $versionId !== IPlatformPublisher::MAPPING_TYPE_DEVELOP) {
			$service_folder	=	$this->_basePath.'/services/'.$serviceId.'/versions/'.$versionId;
		} else {
			$service_folder	=	$this->_basePath.'/services/'.$serviceId;
		}

		// SERVICE FOLER
		if ( !is_dir( $service_folder)) {
			mkdir( $service_folder, 0777, true);
			if ( !is_dir( $service_folder)) {
				throw new \Exception( 'Failed to create service folder ['.$service_folder.']');
			}
		}

		$full_path	=	$service_folder.'/'.$file;

		$this->_logger->debug( 'Saving service ['.$serviceId.']['.$file.'] to ['.$full_path.']');

		$ret	=	file_put_contents( $full_path, json_encode( $data, JSON_PRETTY_PRINT));
		if ( $ret === false) {
			throw new \Exception( 'Could not save service ['.$serviceId.']['.$file.'] to ['.$full_path.']');
		}
	}

	private function _loadServiceFile( $serviceId, $file, $versionId=null)
	{
	    if ( $versionId && $versionId !== IPlatformPublisher::MAPPING_TYPE_DEVELOP) {
			$full_path	=	$this->_basePath.'/services/'.$serviceId.'/versions/'.$versionId.'/'.$file;
		} else {
			$full_path	=	$this->_basePath.'/services/'.$serviceId.'/'.$file;
		}

		$this->_logger->debug( 'Trying to load service data from ['.$full_path.']');

		if ( !is_file( $full_path)) {
			throw new \Convo\Core\DataItemNotFoundException( 'Service data not found at ['.$full_path.']');
		}

		$data	=	json_decode( file_get_contents( $full_path), true);
		if ( $data === false) {
			throw new \Exception( 'Invalid service ['.$serviceId.']['.$file.']. Reason ['.json_last_error().']['.json_last_error_msg().']');
		}

		return $data;
	}

	// UTIL
	public function __toString()
	{
		return parent::__toString().'['.$this->_basePath.']';
	}


}
