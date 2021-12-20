<?php

namespace Obfuscator\Parser;

use Exception;
use Obfuscator\Config;
use Obfuscator\Scrambler;
use PhpParser\Node;
use PhpParser\Node\VarLikeIdentifier;

/**
 * Class Scram
 * @package Obfuscator\Parser
 */
class Scram extends Visitor
{
	/**
	 * @var array
	 */
	protected array $loopStack = [];

	/**
	 * @var array
	 */
	protected array $scramblers = [];

	/**
	 * @var bool
	 */
	protected bool $isConstDefinition = false;

	/**
	 * Visitor constructor.
	 *
	 * @param $config
	 * @param $scramblers
	 */
	public function __construct($config, $scramblers)
	{
		$this->scramblers = $scramblers;
		parent::__construct($config);
	}

	/**
	 * @return bool
	 */
	public function isConstDefinition(): bool
	{
		return $this->isConstDefinition;
	}

	/**
	 * @param string $name
	 *
	 * @return Scrambler|null
	 */
	public function getScrambler(string $name): ?Scrambler
	{
		return $this->scramblers[$name] ?? null;
	}

	/**
	 * @param Node $node
	 *
	 * @return void
	 */
	public function enterNode(Node $node)
	{
		global $config;

		if ($config instanceof Config
			&& $config->isObfuscateLoopStmt()) {

			$scrambler = $this->getScrambler(self::LABEL_TYPE);
			if ($node instanceof Node\Stmt\For_
				|| $node instanceof Node\Stmt\Do_
				|| $node instanceof Node\Stmt\While_
				|| $node instanceof Node\Stmt\Switch_
				|| $node instanceof Node\Stmt\Foreach_) {

				$loopBreakName     = $scrambler->scramble($scrambler->generateLabelName());
				$loopContinueName  = $scrambler->scramble($scrambler->generateLabelName());
				$this->loopStack[] = [$loopBreakName, $loopContinueName];
			}
		}

		if ($node instanceof Node\Stmt\ClassConst) {

			$this->isConstDefinition = true;
		}

		parent::enterNode($node);

	}

	/**
	 * @param Node $node
	 *
	 * @return array|Node|Node[]|Node\Stmt\Goto_|Node\Stmt\Goto_[]|Node\Stmt\If_[]|Node\Stmt\Label[]|null
	 * @throws Exception
	 */
	public function leaveNode(Node $node)
	{
		global $debugMode;

		$nodeModified = false;

		if ($node instanceof Node\Stmt\ClassConst) {

			$this->isConstDefinition = false;
		}

		$this->maybeObfuscateLabel($node, $nodeModified);

		$this->maybeObfuscateString($node, $nodeModified);

		$this->maybeObfuscateProperty($node, $nodeModified);

		$this->maybeObfuscateVariable($node, $nodeModified);

		$this->maybeObfuscateConstant($node, $nodeModified);

		$this->maybeObfuscateTraitName($node, $nodeModified);

		$this->maybeObfuscateNamespace($node, $nodeModified);

		$this->maybeObfuscateClassName($node, $nodeModified);

		$this->maybeObfuscateMethodName($node, $nodeModified);

		$this->maybeObfuscateFunctionName($node, $nodeModified);

		$this->maybeObfuscateInterfaceName($node, $nodeModified);

		$this->maybeObfuscateClassConstant($node, $nodeModified);

		$this->maybeObfuscateClassOrFunctionName($node, $nodeModified);

		if ($this->getConfig()->isObfuscateIfStmt()) {

			$scrambler   = $this->getScrambler(self::LABEL_TYPE);
			$ok2scramble = false;
			if ($node instanceof Node\Stmt\If_) {

				$ok2scramble = true;
				$condition   = $node->cond;
				if ($condition instanceof Node\Expr\BooleanNot) {

					$expr = $condition->expr;
					if ($expr instanceof Node\Expr\FuncCall) {

						$name = $expr->name;
						if ($name instanceof Node\Name) {

							if ($name->parts[0] == 'function_exists') {

								$ok2scramble = false;
							}
						}
					}
				}
			}

			if ($ok2scramble) {

				$stmts     = $node->stmts;
				$else      = isset($node->{'else'}) ? $node->{'else'}->stmts : null;
				$elseif    = $node->elseifs;
				$condition = $node->cond;

				if (isset($elseif)
					&& count($elseif)) {

					$labelEndifName = $scrambler->scramble($scrambler->generateLabelName());
					$labelEndif     = [new Node\Stmt\Label($labelEndifName)];
					$gotoEndif      = [new Node\Stmt\Goto_($labelEndifName)];

					$newNodes1 = [];
					$newNodes2 = [];

					$labelIfName = $scrambler->scramble($scrambler->generateLabelName());
					$labelIf     = [new Node\Stmt\Label($labelIfName)];
					$gotoIf      = [new Node\Stmt\Goto_($labelIfName)];
					$if          = new Node\Stmt\If_($condition);
					$if->stmts   = $gotoIf;
					$newNodes1   = array_merge($newNodes1, [$if]);
					$newNodes2   = array_merge($newNodes2, $labelIf, $stmts, $gotoEndif);

					for ($i = 0; $i < count($elseif); ++$i) {
						$condition   = $elseif[$i]->cond;
						$stmts       = $elseif[$i]->stmts;
						$labelIfName = $scrambler->scramble($scrambler->generateLabelName());
						$labelIf     = [new Node\Stmt\Label($labelIfName)];
						$gotoIf      = [new Node\Stmt\Goto_($labelIfName)];
						$if          = new Node\Stmt\If_($condition);
						$if->stmts   = $gotoIf;
						$newNodes1   = array_merge($newNodes1, [$if]);
						$newNodes2   = array_merge($newNodes2, $labelIf, $stmts);
						if ($i < count($elseif) - 1) {

							$newNodes2 = array_merge($newNodes2, $gotoEndif);
						}
					}
					if (isset($else)) {

						$newNodes1 = array_merge($newNodes1, $else);
					}

					$newNodes1 = array_merge($newNodes1, $gotoEndif);
					$newNodes2 = array_merge($newNodes2, $labelEndif);
					return array_merge($newNodes1, $newNodes2);
				} else {
					if (isset($else)) {

						$labelThenName  = $scrambler->scramble($scrambler->generateLabelName());
						$labelThen      = [new Node\Stmt\Label($labelThenName)];
						$gotoThen       = [new Node\Stmt\Goto_($labelThenName)];
						$labelEndifName = $scrambler->scramble($scrambler->generateLabelName());
						$labelEndif     = [new Node\Stmt\Label($labelEndifName)];
						$gotoEndif      = [new Node\Stmt\Goto_($labelEndifName)];
						$node->stmts    = $gotoThen;
						$node->{'else'} = null;
						return array_merge([$node], $else, $gotoEndif, $labelThen, $stmts, $labelEndif);
					} else {

						if ($condition instanceof Node\Expr\BooleanNot) {

							$newCondition = $condition->expr;
						} else {

							$newCondition = new Node\Expr\BooleanNot($condition);
						}

						$labelEndifName = $scrambler->scramble($scrambler->generateLabelName());
						$labelEndif     = [new Node\Stmt\Label($labelEndifName)];
						$gotoEndif      = [new Node\Stmt\Goto_($labelEndifName)];
						$node->cond     = $newCondition;
						$node->stmts    = $gotoEndif;
						return array_merge([$node], $stmts, $labelEndif);
					}
				}
			}
		}

		if ($this->getConfig()->isObfuscateLoopStmt()) {

			$scrambler = $this->getScrambler(self::LABEL_TYPE);
			if ($node instanceof Node\Stmt\For_) {

				[$labelLoopBreakName, $labelLoopContinueName] = array_pop($this->loopStack);

				$init = null;
				if (isset($node->init) && count($node->init)) {

					foreach ($node->init as $tmp) {

						$init[] = new Node\Stmt\Expression($tmp);
					}
				}

				$condition = (isset($node->cond) && count($node->cond)) ? $node->cond[0] : null;

				$loop = null;
				if (isset($node->loop)
					&& count($node->loop)) {

					foreach ($node->loop as $tmp) {

						$loop[] = new Node\Stmt\Expression($tmp);
					}
				}

				$stmts         = $node->stmts;
				$labelLoopName = $scrambler->scramble($scrambler->generateLabelName());
				$labelLoop     = [new Node\Stmt\Label($labelLoopName)];
				$gotoLoop      = [new Node\Stmt\Goto_($labelLoopName)];
				$labelBreak    = [new Node\Stmt\Label($labelLoopBreakName)];
				$gotoBreak     = [new Node\Stmt\Goto_($labelLoopBreakName)];
				$labelContinue = [new Node\Stmt\Label($labelLoopContinueName)];

				$newNode = [];
				if (isset($init)) {

					$newNode = array_merge($newNode, $init);
				}
				$newNode = array_merge($newNode, $labelLoop);
				if (isset($condition)) {

					if ($condition instanceof Node\Expr\BooleanNot) {

						$newCondition = $condition->expr;
					} else {

						$newCondition = new Node\Expr\BooleanNot($condition);
					}

					$if        = new Node\Stmt\If_($newCondition);
					$if->stmts = $gotoBreak;
					$newNode   = array_merge($newNode, [$if]);
				}
				if (isset($stmts)) {

					$newNode = array_merge($newNode, $stmts);
				}
				$newNode = array_merge($newNode, $labelContinue);
				if (isset($loop)) {

					$newNode = array_merge($newNode, $loop);
				}

				$newNode = array_merge($newNode, $gotoLoop);
				return array_merge($newNode, $labelBreak);
			}

			if ($node instanceof Node\Stmt\Foreach_) {

				[$labelLoopBreakName, $labelLoopContinueName] = array_pop($this->loopStack);

				$labelBreak    = [new Node\Stmt\Label($labelLoopBreakName)];
				$node->stmts[] = new Node\Stmt\Label($labelLoopContinueName);
				$this->shuffleStmts($node);
				return array_merge([$node], $labelBreak);
			}

			if ($node instanceof Node\Stmt\Switch_) {

				[$labelLoopBreakName, $labelLoopContinueName] = array_pop($this->loopStack);

				$labelBreak    = [new Node\Stmt\Label($labelLoopBreakName)];
				$labelContinue = [new Node\Stmt\Label($labelLoopContinueName)];

				return array_merge([$node], $labelContinue, $labelBreak);
			}

			if ($node instanceof Node\Stmt\While_) {

				[$labelLoopBreakName, $labelLoopContinueName] = array_pop($this->loopStack);

				$condition     = $node->cond;
				$stmts         = $node->stmts;
				$labelBreak    = [new Node\Stmt\Label($labelLoopBreakName)];
				$gotoBreak     = [new Node\Stmt\Goto_($labelLoopBreakName)];
				$labelContinue = [new Node\Stmt\Label($labelLoopContinueName)];
				$gotoContinue  = [new Node\Stmt\Goto_($labelLoopContinueName)];
				if ($condition instanceof Node\Expr\BooleanNot) {

					$newCondition = $condition->expr;
				} else {

					$newCondition = new Node\Expr\BooleanNot($condition);
				}

				$if        = new Node\Stmt\If_($newCondition);
				$if->stmts = $gotoBreak;
				return array_merge($labelContinue, [$if], $stmts, $gotoContinue, $labelBreak);
			}

			if ($node instanceof Node\Stmt\Do_) {

				[$labelLoopBreakName, $labelLoopContinueName] = array_pop($this->loopStack);

				$condition     = $node->cond;
				$stmts         = $node->stmts;
				$labelBreak    = [new Node\Stmt\Label($labelLoopBreakName)];
				$labelContinue = [new Node\Stmt\Label($labelLoopContinueName)];
				$gotoContinue  = [new Node\Stmt\Goto_($labelLoopContinueName)];
				$if            = new Node\Stmt\If_($condition);
				$if->stmts     = $gotoContinue;

				return array_merge($labelContinue, $stmts, [$if], $labelBreak);
			}

			if ($node instanceof Node\Stmt\Break_) {

				$count = 1;
				if (isset($node->num)) {
					if ($node->num instanceof Node\Scalar\LNumber) {

						$count = $node->num->value;
					} else {

						throw new Exception("Error: your use of break statement is not compatible with obfuscator!" . PHP_EOL . "\tAt max 1 literal numeric parameter is allowed...");
					}
				}
				if (count($this->loopStack) - $count < 0) {

					throw new Exception("Error: break statement outside loop found!;" . PHP_EOL . (($debugMode == 2) ? print_r($node, true) : ''));
				}

				[$labelLoopBreakName,] = $this->loopStack[count($this->loopStack) - $count];

				$node         = new Node\Stmt\Goto_($labelLoopBreakName);
				$nodeModified = true;
			}
			if ($node instanceof Node\Stmt\Continue_) {

				$count = 1;
				if (isset($node->num)) {

					if ($node->num instanceof Node\Scalar\LNumber) {

						$count = $node->num->value;
					} else {

						throw new Exception("Error: your use of continue statement is not compatible with yakpro-po!" . PHP_EOL . "\tAt max 1 literal numeric parameter is allowed...");
					}
				}

				if (count($this->loopStack) - $count < 0) {

					throw new Exception("Error: continue statement outside loop found!;" . PHP_EOL . (($debugMode == 2) ? print_r($node, true) : ''));
				}
				[, $labelLoopContinueName] = $this->loopStack[count($this->loopStack) - $count];
				$node         = new Node\Stmt\Goto_($labelLoopContinueName);
				$nodeModified = true;
			}
		}

		if ($this->getConfig()->isShuffleStmts()) {
			if ($node instanceof Node\Stmt\If_
				|| $node instanceof Node\Stmt\Case_
				|| $node instanceof Node\Stmt\Catch_
				|| $node instanceof Node\Expr\Closure
				|| $node instanceof Node\Stmt\Foreach_
				|| $node instanceof Node\Stmt\TryCatch
				|| $node instanceof Node\Stmt\Function_
				|| $node instanceof Node\Stmt\ClassMethod) {

				if ($this->shuffleStmts($node)) {

					$nodeModified = true;
				}
			}

			if ($node instanceof Node\Stmt\If_) {

				if (isset($node->{'else'})) {

					if ($this->shuffleStmts($node->{'else'})) {

						$nodeModified = true;
					}
				}

				$elseif = $node->elseifs;
				if (isset($elseif) && count($elseif)) {

					for ($i = 0; $i < count($elseif); ++$i) {

						if ($this->shuffleStmts($elseif[$i])) {

							$nodeModified = true;
						}
					}
				}
			}
		}

		parent::leaveNode($node);

		if ($nodeModified) {

			return $node;
		}
	}

	/**
	 * @param Node $node
	 *
	 * @return bool
	 */
	private function shuffleStmts(Node &$node): bool
	{
		global $config;

		$return = false;
		if ($config instanceof Config
			&& $config->isShuffleStmts()
			&& isset($node->stmts)) {

			$stmts     = $node->stmts;
			$chunkSize = $this->getUtility()->getShuffleChunkSize($stmts);
			if ($chunkSize > 0
				&& count($stmts) > (2 * $chunkSize)) {

				$stmts       = $this->getUtility()->shuffleStmts($stmts);
				$node->stmts = $stmts;

				$return = true;
			}
		}

		return $return;
	}

	/**
	 * @param Node        $node
	 * @param string|null $name
	 */
	private function setIdentifierName(Node &$node, ?string $name)
	{
		if ($node instanceof Node\Identifier
			|| $node instanceof VarLikeIdentifier) {

			$node->name = $name;
		}
	}

	/**
	 * @param        $node
	 * @param        $scrambler
	 * @param string $property
	 *
	 * @return bool
	 */
	private function maybeScramble(&$node, $scrambler, string $property = 'name'): bool
	{
		$nodeModified = false;
		if (isset($node->{$property})) {

			$nodeModified = $this->scramble($node, $node->{$property}, $scrambler, $property);
		}

		return $nodeModified;
	}

	/**
	 * @param        $node
	 * @param        $value
	 * @param        $scrambler
	 * @param string $property
	 *
	 * @return bool
	 */
	private function scramble(&$node, $value, $scrambler, string $property = 'name'): bool
	{
		if (is_string($scrambler)) {

			$scrambler = $this->getScrambler($scrambler);
		}

		$nodeModified = false;
		if ($this->isValidValue($value)) {

			$val = $scrambler->scramble($value);
			if ($val !== $value) {

				$nodeModified      = true;
				$node->{$property} = $val;
			}
		}
		return $nodeModified;
	}

	/**
	 * @param        $node
	 * @param        $scrambler
	 * @param string $property
	 *
	 * @return bool
	 */
	private function scrambleIdentifier(&$node, $scrambler, string $property = 'name'): bool
	{
		if (is_string($scrambler)) {

			$scrambler = $this->getScrambler($scrambler);
		}

		$nodeModified = false;
		$name         = $this->getIdentifierName($node->{$property});
		if ($this->isValidValue($name)) {

			$value = $scrambler->scramble($name);
			if ($value !== $name) {

				$this->setIdentifierName($node->{$property}, $value);
				$nodeModified = true;
			}
		}

		return $nodeModified;
	}

	/**
	 * @param        $node
	 * @param        $scrambler
	 * @param int    $decrease
	 * @param string $property
	 *
	 * @return bool
	 */
	private function scrambleParts(&$node, $scrambler, int $decrease = 0, string $property = 'name'): bool
	{
		if (is_string($scrambler)) {

			$scrambler = $this->getScrambler($scrambler);
		}

		$nodeModified = false;
		if (isset($node->{$property}->parts)) {

			$parts = $node->{$property}->parts;
			for ($i = 0; $i < count($parts) - $decrease; ++$i) {

				$name = $parts[$i];
				if ($this->isValidValue($name)) {

					$value = $scrambler->scramble($name);
					if ($value !== $name) {

						$node->{$property}->parts[$i] = $value;
						$nodeModified                 = true;
					}
				}
			}
		}

		return $nodeModified;
	}

	/**
	 * @param        $node
	 * @param        $scrambler
	 * @param int    $decrease
	 * @param string $property
	 *
	 * @return bool
	 */
	private function scrambleLoopParts(&$node, $scrambler, int $decrease = 1, string $property = 'name'): bool
	{
		if (is_string($scrambler)) {

			$scrambler = $this->getScrambler($scrambler);
		}

		$nodeModified = false;
		if (isset($node->{$property})
			&& count($node->{$property})) {

			for ($j = 0; $j < count($node->{$property}); ++$j) {

				$parts = $node->{$property}[$j]->parts;
				for ($i = 0; $i < count($parts) - $decrease; ++$i) {

					$name = $parts[$i];
					if ($this->isValidValue($name)) {

						$value = $scrambler->scramble($name);
						if ($value !== $name) {

							$node->{$property}[$j]->parts[$i] = $value;
							$nodeModified                     = true;
						}
					}
				}
			}
		}

		return $nodeModified;
	}

	/**
	 * @param Node $node
	 * @param bool $nodeModified
	 */
	private function maybeObfuscateLabel(Node &$node, &$nodeModified = false)
	{
		if ($this->getConfig()->isObfuscateLabel()) {

			$scrambler = $this->getScrambler(self::LABEL_TYPE);

			if ($node instanceof Node\Stmt\Label
				|| $node instanceof Node\Stmt\Goto_) {

				$name = $this->getIdentifierName($node->name);
				if ($this->isValidValue($name)) {

					$value = $scrambler->scramble($name);
					if ($value !== $name) {

						$node->name   = $value;
						$nodeModified = true;
					}
				}
			}
		}
	}

	/**
	 * @param Node $node
	 * @param bool $nodeModified
	 */
	private function maybeObfuscateString(Node &$node, &$nodeModified = false)
	{
		if ($this->getConfig()->isObfuscateString()) {

			if ($node instanceof Node\Stmt\InlineHTML) {

				$node         = new Node\Stmt\Echo_([new Node\Scalar\String_($node->value)]);
				$nodeModified = true;
			}
		}
	}

	/**
	 * @param Node $node
	 * @param bool $nodeModified
	 */
	private function maybeObfuscateVariable(Node &$node, &$nodeModified = false)
	{
		if ($this->getConfig()->isObfuscateVariable()) {

			$scrambler = $this->getScrambler(self::VARIABLE_TYPE);
			if ($scrambler) {
				if ($node instanceof Node\Expr\Variable) {

					$nodeModified = $this->maybeScramble($node, $scrambler);
				}
				if ($node instanceof Node\Param
					|| $node instanceof Node\Stmt\Catch_
					|| $node instanceof Node\Expr\ClosureUse) {

					$nodeModified = $this->maybeScramble($node, $scrambler, 'var');
				}
			}
		}
	}

	/**
	 * @param Node $node
	 * @param bool $nodeModified
	 */
	private function maybeObfuscateProperty(Node &$node, &$nodeModified = false)
	{
		if ($this->getConfig()->isObfuscateProperty()) {

			if ($node instanceof Node\Expr\PropertyFetch
				|| $node instanceof Node\Stmt\PropertyProperty
				|| $node instanceof Node\Expr\StaticPropertyFetch) {

				$nodeModified = $this->scrambleIdentifier($node, self::PROPERTY_TYPE);
			}
		}
	}

	/**
	 * @param Node  $node
	 * @param false $nodeModified
	 *
	 * @throws Exception
	 */
	private function maybeObfuscateConstant(Node &$node, &$nodeModified = false)
	{
		if ($this->getConfig()->isObfuscateConstant()) {

			$scrambler = $this->getScrambler(self::CONSTANT_TYPE);
			if ($node instanceof Node\Expr\FuncCall) {

				if (isset($node->name->parts)) {

					$parts   = $node->name->parts;
					$funName = $parts[count($parts) - 1];
					if (is_string($funName)
						&& (($funName == 'define')
							|| ($funName == 'defined'))) {

						$ok = false;
						while (!$ok) {

							if (isset($node->args[0]->value)
								&& ($funName != 'define'
									|| count($node->args) == 2)) {

								$arg = $node->args[0]->value;
								if ($arg instanceof Node\Scalar\String_) {

									$name = $arg->value;
									if ($this->isValidValue($name)) {

										$ok    = true;
										$value = $scrambler->scramble($name);
										if ($value !== $name) {

											$arg->value   = $value;
											$nodeModified = true;
										}
									}
								}
							}
							break;
						}
						if (!$ok) {

							if ($funName == 'define') {

								throw new Exception("Error: your use of $funName() function is not compatible with yakpro-po!" . PHP_EOL . "\tOnly 2 parameters, when first is a literal string is allowed...");
							} else {

								throw new Exception("Error: your use of $funName() function is not compatible with yakpro-po!" . PHP_EOL . "\tOnly 1 literal string parameter is allowed...");
							}
						}
					}
				}
			}
			if ($node instanceof Node\Expr\ConstFetch) {

				$parts = $node->name->parts;
				$name  = $parts[count($parts) - 1];
				if ($this->isValidValue($name)) {

					$value = $scrambler->scramble($name);
					if ($value !== $name) {

						$node->name->parts[count($parts) - 1] = $value;
						$nodeModified                         = true;
					}
				}
			}

			if (($node instanceof Node\Const_)
				&& !$this->isConstDefinition()) {

				$nodeModified = $this->scrambleIdentifier($node, $scrambler);
			}
		}
	}

	/**
	 * @param Node $node
	 * @param bool $nodeModified
	 */
	private function maybeObfuscateClassName(Node &$node, &$nodeModified = false)
	{
		if ($this->getConfig()->isObfuscateClassName()) {

			$scrambler = $this->getScrambler(self::FUNCTION_OR_CLASS_TYPE);
			if ($node instanceof Node\Stmt\Class_) {

				if ($node->name != null) {

					$this->scrambleIdentifier($node, $scrambler);
				}
				if (isset($node->{'extends'})) {

					$parts = $node->{'extends'}->parts;
					$name  = $parts[count($parts) - 1];

					if ($this->isValidValue($name)) {

						$value = $scrambler->scramble($name);
						if ($value !== $name) {
							$node->{'extends'}->parts[count($parts) - 1] = $value;
							$nodeModified                                = true;
						}
					}
				}
			}
			if ($node instanceof Node\Expr\New_
				|| $node instanceof Node\Expr\StaticCall
				|| $node instanceof Node\Expr\Instanceof_
				|| $node instanceof Node\Expr\ClassConstFetch
				|| $node instanceof Node\Expr\StaticPropertyFetch) {

				if (isset($node->{'class'}->parts)) {

					$parts = $node->{'class'}->parts;
					$name  = $parts[count($parts) - 1];
					if ($this->isValidValue($name)) {

						$value = $scrambler->scramble($name);
						if ($value !== $name) {

							$nodeModified                              = true;
							$node->{'class'}->parts[count($parts) - 1] = $value;
						}
					}
				}
			}
			if ($node instanceof Node\Param) {

				if (isset($node->type)
					&& isset($node->type->parts)) {

					$parts = $node->type->parts;
					$name  = $parts[count($parts) - 1];

					if ($this->isValidValue($name)) {

						$value = $scrambler->scramble($name);
						if ($value !== $name) {

							$node->type->parts[count($parts) - 1] = $value;
							$nodeModified                         = true;
						}
					}
				}
			}
			if (isset($node->returnType)
				&& ($node instanceof Node\Stmt\ClassMethod
					|| $node instanceof Node\Stmt\Function_)) {

				$nodeTmp = $node->returnType;
				if ($nodeTmp instanceof Node\NullableType
					&& isset($nodeTmp->type)) {

					$nodeTmp = $nodeTmp->type;
				}
				if ($nodeTmp instanceof Node\Name
					&& isset($nodeTmp->parts)) {

					$parts = $nodeTmp->parts;
					$name  = $parts[count($parts) - 1];
					if ($this->isValidValue($name)) {

						$value = $scrambler->scramble($name);
						if ($value !== $name) {

							$nodeTmp->parts[count($parts) - 1] = $value;
							$nodeModified                      = true;
						}
					}
				}
			}
			if ($node instanceof Node\Stmt\Catch_
				&& isset($node->types)) {

				$types = $node->types;
				foreach ($types as &$type) {

					$parts = $type->parts;
					$name  = $parts[count($parts) - 1];
					if ($this->isValidValue($name)) {

						$value = $scrambler->scramble($name);
						if ($value !== $name) {

							$type->parts[count($parts) - 1] = $value;
							$nodeModified                   = true;
						}
					}
				}
			}
		}
	}

	/**
	 * @param Node $node
	 * @param bool $nodeModified
	 */
	private function maybeObfuscateNamespace(Node &$node, &$nodeModified = false)
	{
		if ($this->getConfig()->isObfuscateNamespace()) {

			$scrambler = $this->getScrambler(self::FUNCTION_OR_CLASS_TYPE);
			if ($node instanceof Node\Stmt\Namespace_
				|| $node instanceof Node\Stmt\UseUse) {

				$nodeModified = $this->scrambleParts($node, $scrambler);
			}
			if ($node instanceof Node\Expr\FuncCall
				|| $node instanceof Node\Expr\ConstFetch) {

				$nodeModified = $this->scrambleParts($node, $scrambler, 1);
			}

			if ($node instanceof Node\Expr\New_
				|| $node instanceof Node\Expr\StaticCall
				|| $node instanceof Node\Expr\Instanceof_
				|| $node instanceof Node\Expr\ClassConstFetch
				|| $node instanceof Node\Expr\StaticPropertyFetch) {

				$nodeModified = $this->scrambleParts($node, $scrambler, 1, 'class');
			}

			if ($node instanceof Node\Stmt\Class_) {

				$nodeModified = $this->scrambleParts($node, $scrambler, 1, 'extends');

				$nodeModified = $this->scrambleLoopParts($node, $scrambler, 1, 'implements');
			}
			if ($node instanceof Node\Param) {

				$nodeModified = $this->scrambleParts($node, $scrambler, 1, 'type');
			}
			if ($node instanceof Node\Stmt\Interface_) {

				$nodeModified = $this->scrambleLoopParts($node, $scrambler, 1, 'extends');
			}
			if ($node instanceof Node\Stmt\TraitUse) {

				$nodeModified = $this->scrambleLoopParts($node, $scrambler, 1, 'traits');
			}
			if ($node instanceof Node\Stmt\Catch_) {

				if (isset($node->types)) {

					$types = $node->types;
					foreach ($types as &$type) {

						$parts = $type->parts;
						for ($i = 0; $i < count($parts) - 1; ++$i) {

							$name = $parts[$i];
							if ($this->isValidValue($name)) {

								$value = $scrambler->scramble($name);
								if ($value !== $name) {

									$type->parts[$i] = $value;
									$nodeModified    = true;
								}
							}
						}
					}
				}
			}
		}
	}

	/**
	 * @param Node $node
	 * @param bool $nodeModified
	 */
	private function maybeObfuscateTraitName(Node &$node, &$nodeModified = false)
	{
		if ($this->getConfig()->isObfuscateTraitName()) {

			$scrambler = $this->getScrambler(self::FUNCTION_OR_CLASS_TYPE);
			if ($node instanceof Node\Stmt\Trait_) {

				$nodeModified = $this->scrambleIdentifier($node, $scrambler);
			}

			if ($node instanceof Node\Stmt\TraitUse) {
				if (isset($node->{'traits'})
					&& count($node->{'traits'})) {

					for ($j = 0; $j < count($node->{'traits'}); ++$j) {

						$parts = $node->{'traits'}[$j]->parts;
						$name  = $parts[count($parts) - 1];

						if ($this->isValidValue($name)) {

							$value = $scrambler->scramble($name);
							if ($value !== $name) {

								$node->{'traits'}[$j]->parts[count($parts) - 1] = $value;
								$nodeModified                                   = true;
							}
						}
					}
				}
			}
		}
	}

	/**
	 * @param Node  $node
	 * @param false $nodeModified
	 */
	private function maybeObfuscateMethodName(Node &$node, &$nodeModified = false)
	{
		if ($this->getConfig()->isObfuscateMethodName()) {

			if ($node instanceof Node\Stmt\ClassMethod
				|| $node instanceof Node\Expr\MethodCall
				|| $node instanceof Node\Expr\StaticCall) {

				if (!$this->getUtility()->hasExcludeDocComment($node)) {

					$nodeModified = $this->scrambleIdentifier($node, self::METHOD_TYPE);
				}
			}

		}
	}

	/**
	 * @param Node  $node
	 * @param false $nodeModified
	 *
	 * @throws Exception
	 */
	private function maybeObfuscateFunctionName(Node &$node, &$nodeModified = false)
	{
		if ($this->getConfig()->isObfuscateFunctionName()) {

			$scrambler = $this->getScrambler(self::FUNCTION_OR_CLASS_TYPE);
			if ($node instanceof Node\Stmt\Function_) {

				$this->scramble($node, $node->name->name, $scrambler);
			}

			if ($node instanceof Node\Expr\FuncCall
				&& isset($node->name->parts)) {

				$parts = $node->name->parts;
				$name  = $parts[count($parts) - 1];
				if ($this->isValidValue($name)) {

					$value = $scrambler->scramble($name);
					if ($value !== $name) {

						$node->name->parts[count($parts) - 1] = $value;
						$nodeModified                         = true;
					}
				}
				if (is_string($name) && ($name == 'function_exists')) {

					$ok      = false;
					$warning = false;
					while (!$ok) {

						if (isset($node->args[0]->value)
							&& count($node->args) == 1) {

							$arg = $node->args[0]->value;
							if (!$arg instanceof Node\Scalar\String_) {

								$ok      = true;
								$warning = true;
								break;
							}
							$name = $arg->value;
							if ($this->isValidValue($name)) {

								$value = $scrambler->scramble($name);
								if ($value !== $name) {

									$arg->value   = $value;
									$nodeModified = true;
								}
								$ok      = true;
								$warning = false;
							}
						}
						break;
					}
					if (!$ok) {

						throw new Exception("Error: your use of function_exists() function is not compatible with yakpro-po!" . PHP_EOL . "\tOnly 1 literal string parameter is allowed...");
					}
					if ($warning) {

						fprintf(STDERR, "Warning: your use of function_exists() function is not compatible with yakpro-po!" . PHP_EOL . "\t Only 1 literal string parameter is allowed..." . PHP_EOL);
					}
				}

			}
		}
	}

	/**
	 * @param Node $node
	 * @param bool $nodeModified
	 */
	private function maybeObfuscateInterfaceName(Node &$node, &$nodeModified = false)
	{
		if ($this->getConfig()->isObfuscateInterfaceName()) {

			$scrambler = $this->getScrambler(self::FUNCTION_OR_CLASS_TYPE);

			if ($node instanceof Node\Stmt\Interface_) {

				$this->scrambleIdentifier($node, $scrambler);

				if (isset($node->{'extends'})
					&& count($node->{'extends'})) {

					for ($j = 0; $j < count($node->{'extends'}); ++$j) {

						$parts = $node->{'extends'}[$j]->parts;
						$name  = $parts[count($parts) - 1];

						if ($this->isValidValue($name)) {

							$value = $scrambler->scramble($name);
							if ($value !== $name) {

								$node->{'extends'}[$j]->parts[count($parts) - 1] = $value;
								$nodeModified                                    = true;
							}
						}
					}
				}
			}
			if ($node instanceof Node\Stmt\Class_) {
				if (isset($node->{'implements'})
					&& count($node->{'implements'})) {

					for ($j = 0; $j < count($node->{'implements'}); ++$j) {

						$parts = $node->{'implements'}[$j]->parts;
						$name  = $parts[count($parts) - 1];
						if ($this->isValidValue($name)) {

							$value = $scrambler->scramble($name);
							if ($value !== $name) {

								$nodeModified = true;

								$node->{'implements'}[$j]->parts[count($parts) - 1] = $value;
							}
						}
					}
				}
			}
		}
	}

	/**
	 * @param Node $node
	 * @param bool $nodeModified
	 */
	private function maybeObfuscateClassConstant(Node &$node, &$nodeModified = false)
	{
		$config = $this->getConfig();
		if ($config->isObfuscateClassConstant()) {

			$scrambler = $this->getScrambler(self::CLASS_CONSTANT_TYPE);
			if ($node instanceof Node\Expr\ClassConstFetch) {

				$nodeModified = false;

				$name = $this->getIdentifierName($node->name);
				if ($this->isValidValue($name)) {

					global $grabbed;
					$encode = false;
					if (isset($grabbed[self::CONSTANT_TYPE][$name]['value'])) {

						$value  = $grabbed[self::CONSTANT_TYPE][$name]['value'];
						$encode = $grabbed[self::CONSTANT_TYPE][$name]['encode'];
					} else {

						$value = strtolower($name);
					}

					if ($this->isValidConstantFetch($node) && $encode) {

						$nodeModified = true;

						$method = $this->getScrambler(self::METHOD_TYPE)->scramble('getDecodedConstant');

						[$sum, $decodedKey] = $this->getUtility()->encodeString($value);

						$this->setIdentifierName($node->name, "{$method}({$sum}, \"{$this->getUtility()->obfuscateString($decodedKey)}\")");
					} else {

						$nodeModified = $this->scrambleIdentifier($node, $scrambler);
					}
				}
			} else if ($node instanceof Node\Const_ && $this->isConstDefinition()) {

				$nodeModified = $this->scrambleIdentifier($node, $scrambler);
			}
		}
	}

	/**
	 * @param $node
	 *
	 * @return bool
	 */
	public function isValidConstantFetch($node): bool
	{
		$isValid = false;
		if ($node instanceof Node\Expr\ClassConstFetch) {
			$parent = $node->getAttribute('parent');
			if ($parent instanceof Node\Expr\BinaryOp\Concat) {

				$isValid = $this->isValidValue($parent);
			} else if ($parent instanceof Node\Const_) {

				$isValid = true;
			}
		}
		return $isValid;
	}

	/**
	 * @param Node $node
	 * @param bool $nodeModified
	 */
	private function maybeObfuscateClassOrFunctionName(Node &$node, &$nodeModified = false)
	{
		if ($node instanceof Node\Stmt\UseUse) {

			$config = $this->getConfig();
			if ($config->isObfuscateFunctionName()
				|| $config->isObfuscateClassName()) {

				if (isset($node->alias)) {

					if (!$config->isObfuscateFunctionName()
						|| !$config->isObfuscateClassName()) {

						fprintf(STDERR, "Warning:[use alias] cannot determine at compile time if it is a function or a class alias" . PHP_EOL . "\tyou must obfuscate both functions and classes or none..." . PHP_EOL . "\tObfuscated code may not work!" . PHP_EOL);
					}

					$nodeModified = $this->scrambleIdentifier($node, self::FUNCTION_OR_CLASS_TYPE, 'alias');
				}
			}
		}
	}
}