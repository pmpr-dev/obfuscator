<?php

namespace Obfuscator\Parser;

use Exception;
use Obfuscator\Config;
use Obfuscator\Interfaces\ConstantInterface;
use Obfuscator\Scrambler;
use Obfuscator\Traits\UtilityTrait;
use PhpParser\Node;
use PhpParser\Node\VarLikeIdentifier;
use PhpParser\NodeVisitorAbstract;

/**
 * Class Visitor
 * @package Obfuscator\Parser
 */
class Visitor extends NodeVisitorAbstract implements ConstantInterface
{
	use UtilityTrait;

	/**
	 * @var array
	 */
	protected array $nodeStack = [];

	/**
	 * @var Config|null
	 */
	protected ?Config $config = null;

	/**
	 * @var string|null
	 */
	protected ?string $currentClassName = null;

	/**
	 * @var string|null
	 */
	protected ?string $currentNamespace = null;

	/**
	 * @return Config|null
	 */
	public function getConfig(): ?Config
	{
		return $this->config;
	}

	/**
	 * Visitor constructor.
	 *
	 * @param $config
	 */
	public function __construct($config)
	{
		$this->config = $config;
	}

	/**
	 * @param \PhpParser\Node $node
	 *
	 * @return null
	 */
	public function enterNode(Node $node)
	{
		if ($node instanceof Node\Stmt\Class_
			&& ($node->name != null)) {

			$name = $this->getIdentifierName($node->name);
			if (is_string($name)
				&& (strlen($name) !== 0)) {

				$this->currentClassName = $name;
				$this->currentNamespace = $this->getUtility()->getParentNamespace($node);
			}
		}

		if (count($this->nodeStack)) {

			$node->setAttribute('parent', $this->nodeStack[count($this->nodeStack) - 1]);
		}

		$this->nodeStack[] = $node;

		return parent::enterNode($node);
	}

	/**
	 * @param \PhpParser\Node $node
	 *
	 * @return mixed
	 */
	public function leaveNode(Node $node)
	{
		if ($node instanceof Node\Stmt\Class_) {

			$this->currentClassName = null;
			$this->currentNamespace = null;
		}

		array_pop($this->nodeStack);

		return parent::leaveNode($node);
	}

	/**
	 * @param $value
	 *
	 * @return bool
	 */
	protected function isValidValue($value): bool
	{
		return is_string($value) && strlen($value) !== 0;
	}

	/**
	 * @param Node $node
	 *
	 * @return string
	 */
	protected function getIdentifierName(Node $node): string
	{
		return $this->getUtility()->getIdentifierName($node);
	}
}