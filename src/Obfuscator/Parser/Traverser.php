<?php

namespace Obfuscator\Parser;

use PhpParser\Node;
use PhpParser\NodeTraverser;

/**
 * Class Traverser
 * @package Obfuscator\Parser
 */
class Traverser extends NodeTraverser
{
	/**
	 * Recursively traverse a node.
	 *
	 * @param Node $node Node to traverse.
	 *
	 * @return Node Result of traversal (may be original node or new one)
	 */
	protected function traverseNode(Node $node): Node
	{
		foreach ($node->getSubNodeNames() as $name) {
			$subNode =& $node->$name;

			if (\is_array($subNode)) {
				$subNode = $this->traverseArray($subNode);
				if ($this->stopTraversal) {
					break;
				}
			} else if ($subNode instanceof Node) {
				$traverseChildren  = true;
				$breakVisitorIndex = null;

				foreach ($this->visitors as $visitorIndex => $visitor) {
					$return = $visitor->enterNode($subNode);
					if (null !== $return) {
						if ($return instanceof Node) {
							$this->customEnsureReplacementReasonable($subNode, $return);
							$subNode = $return;
						} else if (self::DONT_TRAVERSE_CHILDREN === $return) {
							$traverseChildren = false;
						} else if (self::DONT_TRAVERSE_CURRENT_AND_CHILDREN === $return) {
							$traverseChildren  = false;
							$breakVisitorIndex = $visitorIndex;
							break;
						} else if (self::STOP_TRAVERSAL === $return) {
							$this->stopTraversal = true;
							break 2;
						} else {
							throw new \LogicException(
								'enterNode() returned invalid value of type ' . gettype($return)
							);
						}
					}
				}

				if ($traverseChildren) {
					$subNode = $this->traverseNode($subNode);
					if ($this->stopTraversal) {
						break;
					}
				}

				foreach ($this->visitors as $visitorIndex => $visitor) {
					$return = $visitor->leaveNode($subNode);

					if (null !== $return) {
						if ($return instanceof Node) {
							$this->customEnsureReplacementReasonable($subNode, $return);
							$subNode = $return;
						} else if (self::REMOVE_NODE === $return) {

							$subNode = null;
						} else if (self::STOP_TRAVERSAL === $return) {
							$this->stopTraversal = true;
							break 2;
						} else if (\is_array($return)) {
							throw new \LogicException(
								'leaveNode() may only return an array ' .
								'if the parent structure is an array'
							);
						} else {
							throw new \LogicException(
								'leaveNode() returned invalid value of type ' . gettype($return)
							);
						}
					}

					if ($breakVisitorIndex === $visitorIndex) {
						break;
					}
				}
			}
		}

		return $node;
	}

	private function customEnsureReplacementReasonable($old, $new)
	{
		if ($old instanceof Node\Stmt && $new instanceof Node\Expr) {
			throw new \LogicException(
				"Trying to replace statement ({$old->getType()}) " .
				"with expression ({$new->getType()}). Are you missing a " .
				"Stmt_Expression wrapper?"
			);
		}

		if ($old instanceof Node\Expr && $new instanceof Node\Stmt) {
			throw new \LogicException(
				"Trying to replace expression ({$old->getType()}) " .
				"with statement ({$new->getType()})"
			);
		}
	}

}