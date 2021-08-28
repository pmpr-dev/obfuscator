<?php

namespace Obfuscator\Traits;

/**
 * Class SingletonTrait
 * @package Obfuscator\Traits
 */
trait SingletonTrait
{
	/**
	 * @var array
	 */
	protected static array $instances = [];

	/**
	 * @param ...$args
	 *
	 * @return static
	 */
	public static function getInstance(...$args): self
	{
		$class = get_called_class();
		if (!isset(self::$instances[$class])) {

			self::$instances[$class] = new $class(...$args);
		}
		return self::$instances[$class];
	}
}