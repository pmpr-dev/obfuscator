<?php

namespace Obfuscator\Traits;

use Obfuscator\Utility;

/**
 * Trait UtilityTrait
 * @package Obfuscator\Traits
 */
trait UtilityTrait
{
	/**
	 * @var Utility|null
	 */
	protected ?Utility $utility = null;

	/**
	 * @return Utility|null
	 */
	public function getUtility(): ?Utility
	{
		if (!$this->utility) {

			$this->utility = new Utility();
		}
		return $this->utility;
	}
}