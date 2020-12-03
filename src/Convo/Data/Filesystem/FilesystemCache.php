<?php declare(strict_types=1);

namespace Convo\Data\Filesystem;

use Psr\SimpleCache\CacheInterface;

class FilesystemCache implements CacheInterface
{
	/**
	 * @var string
	 */
	private $_basePath;

	/**
	 * Logger
	 *
	 * @var \Psr\Log\LoggerInterface
	 */
	private $_logger;

	public function __construct( \Psr\Log\LoggerInterface $logger, $basePath)
	{
		$this->_logger		=	$logger;
		$this->_basePath	=	\Convo\Core\Util\StrUtil::removeTrailingSlashes($basePath);
	}

	/**
	 * @param string $key
	 * @return array
	 */
	public function get($key, $default = null)
	{
		$this->_checkKey($key);

		$filename = $this->_getFullPath($key);

		// Cache miss
		if (!file_exists($filename)) {
			return $default;
		}

		$content = file_get_contents($filename);

		if ($content === false) {
			throw new \Exception('Failed to read [' . $filename . ']');
		}

		$json = json_decode($content, true);

		if ($json === false) {
			throw new \Exception('Failed to json decode [' . $filename . ']');
		}

		if (isset($json['expires']) && $json['expires'] !== null) {
			$this->_logger->debug("Item [$key] expires at [{$json['expires']}]");

			if (time() > $json['expires']) {
				$this->_logger->debug("Item [$key] has expired. Will  treat GET as a cache miss.");

				return $default;
			}
		}

		return $json['value'];
	}

	public function getMultiple($keys, $default = null)
	{
		$ret = [];

		foreach ($keys as $key) {
			$ret[$key] = $this->get($key, $default);
		}

		return $ret;
	}

	public function set($key, $value, $ttl = null)
	{
		$this->_checkKey($key);

		$this->_logger->debug('Storing response [' . $key . '][' . json_encode($value) . ']');

		$filename = $this->_getFullPath($key);

		$now = time();
		$expires = null;

		if ($ttl) {
			$expires = $now + $ttl;
		}

		$json = array(
			'key' => $key,
			'value' => $value,
			'time_created' => $now,
			'expires' => $expires
		);

		$this->_ensureFolder();

		return file_put_contents($filename, json_encode($json));
	}

	public function setMultiple($values, $ttl = null)
	{
		$ret = true;

		foreach ($values as $key => $value) {
			$ret = $ret && $this->set($key, $value, $ttl);
		}

		return $ret;
	}

	public function clear()
	{
		$ret = true;

		$mask = $this->_basePath . '/*.json';

		foreach (glob($mask) as $file) {
			$ret = $ret && unlink($file);
		}

		return $ret;
	}

	public function delete($key)
	{
		$this->_checkKey($key);

		$filename = $this->_getFullPath($key);

		$this->_logger->debug("Will delete [$filename]");

		$res = unlink($filename);

		return $res;
	}

	public function deleteMultiple($keys)
	{
		$ret = true;

		foreach ($keys as $key) {
			$ret = $ret && $this->delete($key);
		}

		return $ret;
	}

	public function has($key)
	{
		return $this->get($key) !== null;
	}

	public function _getFullPath($key)
	{
		$filename	=	$key . '.json';

		return $this->_basePath . '/' . $filename;
	}

	private function _ensureFolder()
	{
		if (!is_dir($this->_basePath)) {
			mkdir($this->_basePath, 0755, true);
		}

		if (!is_dir($this->_basePath)) {
			throw new \Exception('Could not create folder [' . $this->_basePath . ']');
		}
		return $this->_basePath;
	}

	// UTIL
	public function __toString()
	{
		return get_class($this) . '[' . $this->_basePath . ']';
	}

	private function _checkKey(string $key) {
        $regex = '/[\{\}\(\)\/\\\\@:]/m';

        if (preg_match($regex, $key) === 1) {
            throw new InvalidKeyException("Invalid key [$key]");
        }
    }
}
