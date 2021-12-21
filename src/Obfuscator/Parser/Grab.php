<?php

namespace Obfuscator\Parser;

use PhpParser\Node;

/**
 * Class Grabber
 * @package Obfuscator\Parser
 */
class Grab extends Visitor
{
	/**
	 * @param \PhpParser\Node $node
	 *
	 * @return null
	 */
	public function leaveNode(Node $node)
	{
		$this->grabClassConstant($node);

		return parent::leaveNode($node);
	}

	/**
	 * @param \PhpParser\Node $node
	 */
	private function grabClassConstant(Node $node)
	{
		if ($node instanceof Node\Const_) {

			$name = $this->getIdentifierName($node->name);
			if ($this->isValidValue($name)) {

				$value  = $this->getConstantValue($node);
				$encode = $this->getUtility()->hasEncodeDocComment($node);
				$this->updateGrabber($name, $value, self::CONSTANT_TYPE, $encode);
			}
		}
	}

	/**
	 * @param $node
	 *
	 * @return array|mixed|string
	 */
	private function getConstantValue($node)
	{
		$value = '';
		if ($node instanceof Node\Const_) {

			$subNode = $node->value;
			if ($subNode instanceof Node\Expr\BinaryOp\Concat) {

				$value = $this->getConcatValue($subNode);
			} else if ($subNode instanceof Node\Expr\ClassConstFetch) {

				$name  = $this->getIdentifierName($subNode);
				$value = $this->getGrabbedValue(self::CONSTANT_TYPE, $name);
			} else if ($subNode instanceof Node\Scalar\String_) {

				$value = $subNode->value;
			}
		}

		return $value;
	}

	/**
	 * @param $node
	 *
	 * @return string
	 */
	public function getConcatValue($node): string
	{
		$value = '';
		if ($node instanceof Node\Expr\BinaryOp\Concat) {

			$items = [
				'left'  => $node->left,
				'right' => $node->right,
			];
			foreach ($items as $item) {

				if ($item instanceof Node\Expr\BinaryOp\Concat) {

					$value .= $this->getConcatValue($item);
				} else if ($item instanceof Node\Expr\ClassConstFetch) {

					$name = $this->getIdentifierName($item->name);

					$value = $this->getGrabbedValue(self::CONSTANT_TYPE, $name);
				} else if ($item instanceof Node\Scalar\String_) {

					$value .= $item->value;
				}
			}
		}
		return $value;
	}

	/**
	 * @param $type
	 * @param $key
	 *
	 * @return array|mixed
	 */
	private function getGrabbedValue($type, $key)
	{
		global $grabbed;

		return $grabbed[$type][$key]['value'] ?? sprintf("%s::%s", $type, $key);
	}

	/**
	 * @param      $key
	 * @param      $value
	 * @param      $type
	 * @param bool $encode
	 */
	private function updateGrabber($key, $value, $type, bool $encode = false)
	{
		global $grabbed;

		if (!isset($grabbed[$type][$key])) {

			$grabbed[$type][$key] = [
				'value'  => $value,
				'encode' => $encode,
			];
		}
	}
}