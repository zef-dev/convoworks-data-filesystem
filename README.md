# Filesystem data layer for Convoworks

This library contains filesystem implementations for `\Convo\Core\IServiceDataProvider`, `\Convo\Core\IServiceParamsFactory`, `\Convo\Core\IServiceParamsFactory` and `Convo\Core\Media\IServiceMediaManager` Convoworks interfaces which are serving for data storage.

In addition there is a simple filesystem `Psr\SimpleCache\CacheInterface` implementation too.

### Service data

* `Convo\Data\Filesystem\FilesystemServiceDataProvider` implements `IServiceDataProvider` - stores service data
* `Convo\Data\Filesystem\FilesystemServiceParams` implements `IServiceParams` - stores runtime service params
* `Convo\Data\Filesystem\FilesystemServiceParamsFactory` implements `IServiceParamsFactory` - creates concrete service params storages
* `Convo\Data\Filesystem\FilesystemServiceMediaManager` implements `IServiceMediaManager` - stores service media

### Cache

* `Convo\Data\Filesystem\FilesystemCache` implements `CacheInterface` - cache is available to be used in Convoworks components