<?php

namespace Obfuscator\Parser;

use Exception;
use Obfuscator\Config;
use Obfuscator\Interfaces\ConstantInterface;
use Obfuscator\Scrambler;
use Obfuscator\Traits\UtilityTrait;
use PhpParser\Node;
use PhpParser\Node\Scalar\String_;
use PhpParser\PrettyPrinter\Standard;

/**
 * Class PrettyPrinter
 * @package Obfuscator\Parser
 */
class PrettyPrinter extends Standard implements ConstantInterface
{
	use UtilityTrait;

	/**
	 * @param $string
	 *
	 * @return string
	 */
	private function obfuscateString($string): string
	{
		$length = strlen($string);
		$result = '';
		for ($i = 0; $i < $length; ++$i) {

			$result .= mt_rand(0, 1) ? "\x" . dechex(ord($string[$i])) : "\\" . decoct(ord($string[$i]));
		}
		return $result;
	}

	/**
	 * @param String_ $node
	 *
	 * @return string
	 * @throws Exception
	 */
	public function pScalar_String(String_ $node): string
	{
		global $config;
		$result = null;
		if ($config instanceof Config) {

			if ($this->isCallbackString($node)) {

				global $scramblers;
				$scrambler = $scramblers[self::METHOD_TYPE] ?? null;
				if ($scrambler instanceof Scrambler) {

					$value = $node->value;
					if (!$config->isIgnoreSnakeCaseMethod()
						|| !$this->getUtility()->isSnakeCase($value)) {

						$node->value = $scrambler->scramble($value);
					}
				}
			}
			$string = $node->value;
			if ($config->isObfuscateString()) {

				$result = $this->obfuscateString($string);
				if (!strlen($result)) {

					$result = "''";
				} else {

					$result = "\"{$result}\"";
				}
			}
		}

		if (!$result) {

			$result = parent::pScalar_String($node);
		}
		return $result;
	}

	/**
	 * @param Node\Scalar\Encapsed $node
	 *
	 * @return string
	 */
	protected function pScalar_Encapsed(Node\Scalar\Encapsed $node): string
	{
		global $config;
		if ($config instanceof Config
			&& $config->isObfuscateString()) {

			$result = '';
			foreach ($node->parts as $element) {

				if ($element instanceof Node\Scalar\EncapsedStringPart) {

					$result .= $this->obfuscateString($element->value);
				} else {

					$result .= '{' . $this->p($element) . '}';
				}
			}
			$result = '"' . $result . '"';
		} else {

			$result = parent::pScalar_Encapsed($node);
		}
		return $result;
	}

	/**
	 * @param String_ $node
	 *
	 * @return bool
	 */
	public function isCallbackString(String_ $node): bool
	{
		$isCallback = false;
		$parent     = $node->getAttribute('parent');
		if ($parent instanceof Node\Expr\ArrayItem) {

			$parent = $parent->getAttribute('parent');
			if ($parent instanceof Node\Expr\Array_) {

				if (count($parent->items) == 2
					&& isset($parent->items[0])) {
					$item = $parent->items[0];
					// check not associative array
					if ($item->value && !$item->key) {

						$value      = $item->value;
						$isCallback = $value instanceof Node\Expr\Variable
							|| ($value instanceof Node\Expr\ClassConstFetch
								&& $this->getUtility()->getIdentifierName($value->name) == 'class')
							|| $value instanceof Node\Scalar\MagicConst\Class_;
					}
				}
			}
		} else if ($parent instanceof Node\Arg) {

			$parent = $parent->getAttribute('parent');
			if ($parent instanceof Node\Expr\FuncCall
				&& isset($parent->name->parts[0])
				&& $parent->name->parts[0] === 'method_exists') {

				$isCallback = true;
			}
		}

		return $isCallback;
	}
}