<?php

namespace Obfuscator;

use Obfuscator\Interfaces\ConstantInterface;
use Obfuscator\Traits\SingletonTrait;
use Obfuscator\Traits\UtilityTrait;

/**
 * Class Common
 * @package Obfuscator
 */
class Container implements ConstantInterface
{
	use SingletonTrait,
		UtilityTrait;
}