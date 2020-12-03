<?php declare(strict_types=1);

namespace Convo\Data\Filesystem;

class InvalidKeyException extends \Exception implements \Psr\SimpleCache\InvalidArgumentException
{
}