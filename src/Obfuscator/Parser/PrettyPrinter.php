<?php

namespace Obfuscator\Parser;

use PhpParser\Node\Scalar\Encapsed;
use PhpParser\Node\Scalar\EncapsedStringPart;
use PhpParser\Node\Scalar\String_;
use PhpParser\PrettyPrinter\Standard;

/**
 * Class PrettyPrinter
 * @package Obfuscator\Parser
 */
class PrettyPrinter extends Standard
{
	/**
	 * @param $str
	 *
	 * @return string
	 */
	private function obfuscateString($str): string
	{
		$length = strlen($str);
		$result = '';
		for ($i = 0; $i < $length; ++$i) {

			$result .= mt_rand(0, 1) ? "\x" . dechex(ord($str[$i])) : "\\" . decoct(ord($str[$i]));
		}
		return $result;
	}

	/**
	 * @param String_ $node
	 *
	 * @return string
	 */
	public function pScalar_String(String_ $node): string
	{
		$result = $this->obfuscateString($node->value);
		if (!strlen($result)) {

			$result = "''";
		} else {

			$result = "\"{$result}\"";
		}

		return $result;
	}

	/**
	 * @param Encapsed $node
	 *
	 * @return string
	 */
	protected function pScalar_Encapsed(Encapsed $node): string
	{
		$result = '';
		foreach ($node->parts as $element) {

			if ($element instanceof EncapsedStringPart) {

				$result .= $this->obfuscateString($element->value);
			} else {

				$result .= '{' . $this->p($element) . '}';
			}
		}
		return '"' . $result . '"';

	}
}