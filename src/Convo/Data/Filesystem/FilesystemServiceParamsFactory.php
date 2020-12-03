<?php declare(strict_types=1);

namespace Convo\Data\Filesystem;

class FilesystemServiceParamsFactory implements \Convo\Core\Params\IServiceParamsFactory
{
	private $_basePath;

	private $_storeAsGz;

	/**
	 *
	 * @var \Psr\Log\LoggerInterface
	 */
	private $_logger;

	/**
	 * @var \Convo\Core\Params\SimpleParams[]
	 */
	private $_params	=	[];

	public function __construct( \Psr\Log\LoggerInterface $logger, $basePath, $storeAsGz)
	{
		$this->_logger		=	$logger;
		$this->_basePath	=	\Convo\Core\Util\StrUtil::removeTrailingSlashes( $basePath);
		$this->_storeAsGz   =   $storeAsGz;
	}

	/**
	 * {@inheritDoc}
	 * @see \Convo\Core\Params\IServiceParamsFactory::getServiceParams()
	 */
	public function getServiceParams( \Convo\Core\Params\IServiceParamsScope $scope) {

		if ( $scope->getScopeType() === \Convo\Core\Params\IServiceParamsScope::SCOPE_TYPE_REQUEST) {

			if ( !isset( $this->_params[$scope->getKey()])) {
				$this->_params[$scope->getKey()]	=	new \Convo\Core\Params\SimpleParams();
			}

			return $this->_params[$scope->getKey()];
		}

		$full_path		=	$this->_basePath.'/params/';
		$service_params	=	new \Convo\Data\Filesystem\FilesystemServiceParams( $this->_logger, $full_path, $scope, $this->_storeAsGz);
		return $service_params;
	}


	// UTIL
	public function __toString()
	{
		return get_class( $this).'['.$this->_basePath.']';
	}
}
