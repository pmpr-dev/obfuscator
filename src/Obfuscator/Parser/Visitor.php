<?php

namespace Obfuscator\Parser;

use Exception;
use Obfuscator\Config;
use Obfuscator\Interfaces\ConstantInterface;
use Obfuscator\Scrambler;
use Obfuscator\Traits\UtilityTrait;
use PhpParser\Node;
use PhpParser\Node\Const_;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\ClosureUse;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Break_;
use PhpParser\Node\Stmt\Case_;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Continue_;
use PhpParser\Node\Stmt\Do_;
use PhpParser\Node\Stmt\Echo_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Goto_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\InlineHTML;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Label;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\PropertyProperty;
use PhpParser\Node\Stmt\Switch_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\Node\Stmt\TryCatch;
use PhpParser\Node\Stmt\UseUse;
use PhpParser\Node\Stmt\While_;
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
	 * @var array
	 */
	protected array $loopStack = [];

	/**
	 * @var Config|null
	 */
	protected ?Config $config = null;

	/**
	 * @var array
	 */
	protected array $scramblers = [];

	/**
	 * @var bool
	 */
	protected bool $isConstDefinition = false;

	/**
	 * @var string|null
	 */
	protected ?string $currentClassName = null;

	/**
	 * @return Config|null
	 */
	public function getConfig(): ?Config
	{
		return $this->config;
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
	 * Visitor constructor.
	 *
	 * @param $config
	 * @param $scramblers
	 */
	public function __construct($config, $scramblers)
	{
		$this->config     = $config;
		$this->scramblers = $scramblers;
	}

	/**
	 * @param Node $node
	 *
	 * @return void
	 */
	public function enterNode(Node $node)
	{
		global $config;

		if (count($this->nodeStack)) {

			$node->setAttribute('parent', $this->nodeStack[count($this->nodeStack) - 1]);
		}

		$this->nodeStack[] = $node;

		if ($config instanceof Config
			&& $config->isObfuscateLoopStmt()) {

			$scrambler = $this->getScrambler(self::LABEL_TYPE);
			if ($node instanceof For_
				|| $node instanceof Do_
				|| $node instanceof While_
				|| $node instanceof Switch_
				|| $node instanceof Foreach_) {

				$loopBreakName     = $scrambler->scramble($scrambler->generateLabelName());
				$loopContinueName  = $scrambler->scramble($scrambler->generateLabelName());
				$this->loopStack[] = [$loopBreakName, $loopContinueName];
			}
		}

		if (($node instanceof Class_)
			&& ($node->name != null)) {

			$name = $this->getIdentifierName($node->name);
			if (is_string($name)
				&& (strlen($name) !== 0)) {

				$this->currentClassName = $name;
			}
		}

		if ($node instanceof ClassConst) {

			$this->isConstDefinition = true;
		}
	}

	/**
	 * @param Node $node
	 *
	 * @return array|Goto_|Label[]|Node|Node[]|null
	 * @throws Exception
	 */
	public function leaveNode(Node $node)
	{
		global $debugMode;

		$nodeModified = false;
		if ($node instanceof Class_) {

			$this->currentClassName = null;
		}

		if ($node instanceof ClassConst) {

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
			if ($node instanceof If_) {

				$ok2scramble = true;
				$condition   = $node->cond;
				if ($condition instanceof BooleanNot) {

					$expr = $condition->expr;
					if ($expr instanceof FuncCall) {

						$name = $expr->name;
						if ($name instanceof Name) {

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
					$labelEndif     = [new Label($labelEndifName)];
					$gotoEndif      = [new Goto_($labelEndifName)];

					$newNodes1 = [];
					$newNodes2 = [];

					$labelIfName = $scrambler->scramble($scrambler->generateLabelName());
					$labelIf     = [new Label($labelIfName)];
					$gotoIf      = [new Goto_($labelIfName)];
					$if          = new If_($condition);
					$if->stmts   = $gotoIf;
					$newNodes1   = array_merge($newNodes1, [$if]);
					$newNodes2   = array_merge($newNodes2, $labelIf, $stmts, $gotoEndif);

					for ($i = 0; $i < count($elseif); ++$i) {
						$condition   = $elseif[$i]->cond;
						$stmts       = $elseif[$i]->stmts;
						$labelIfName = $scrambler->scramble($scrambler->generateLabelName());
						$labelIf     = [new Label($labelIfName)];
						$gotoIf      = [new Goto_($labelIfName)];
						$if          = new If_($condition);
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
						$labelThen      = [new Label($labelThenName)];
						$gotoThen       = [new Goto_($labelThenName)];
						$labelEndifName = $scrambler->scramble($scrambler->generateLabelName());
						$labelEndif     = [new Label($labelEndifName)];
						$gotoEndif      = [new Goto_($labelEndifName)];
						$node->stmts    = $gotoThen;
						$node->{'else'} = null;
						return array_merge([$node], $else, $gotoEndif, $labelThen, $stmts, $labelEndif);
					} else {

						if ($condition instanceof BooleanNot) {

							$newCondition = $condition->expr;
						} else {

							$newCondition = new BooleanNot($condition);
						}

						$labelEndifName = $scrambler->scramble($scrambler->generateLabelName());
						$labelEndif     = [new Label($labelEndifName)];
						$gotoEndif      = [new Goto_($labelEndifName)];
						$node->cond     = $newCondition;
						$node->stmts    = $gotoEndif;
						return array_merge([$node], $stmts, $labelEndif);
					}
				}
			}
		}

		if ($this->getConfig()->isObfuscateLoopStmt()) {

			$scrambler = $this->getScrambler(self::LABEL_TYPE);
			if ($node instanceof For_) {

				[$labelLoopBreakName, $labelLoopContinueName] = array_pop($this->loopStack);

				$init = null;
				if (isset($node->init) && count($node->init)) {

					foreach ($node->init as $tmp) {

						$init[] = new Expression($tmp);
					}
				}

				$condition = (isset($node->cond) && count($node->cond)) ? $node->cond[0] : null;

				$loop = null;
				if (isset($node->loop)
					&& count($node->loop)) {

					foreach ($node->loop as $tmp) {

						$loop[] = new Expression($tmp);
					}
				}

				$stmts         = $node->stmts;
				$labelLoopName = $scrambler->scramble($scrambler->generateLabelName());
				$labelLoop     = [new Label($labelLoopName)];
				$gotoLoop      = [new Goto_($labelLoopName)];
				$labelBreak    = [new Label($labelLoopBreakName)];
				$gotoBreak     = [new Goto_($labelLoopBreakName)];
				$labelContinue = [new Label($labelLoopContinueName)];

				$newNode = [];
				if (isset($init)) {

					$newNode = array_merge($newNode, $init);
				}
				$newNode = array_merge($newNode, $labelLoop);
				if (isset($condition)) {

					if ($condition instanceof BooleanNot) {

						$newCondition = $condition->expr;
					} else {

						$newCondition = new BooleanNot($condition);
					}

					$if        = new If_($newCondition);
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

			if ($node instanceof Foreach_) {

				[$labelLoopBreakName, $labelLoopContinueName] = array_pop($this->loopStack);

				$labelBreak    = [new Label($labelLoopBreakName)];
				$node->stmts[] = new Label($labelLoopContinueName);
				$this->shuffleStmts($node);
				return array_merge([$node], $labelBreak);
			}

			if ($node instanceof Switch_) {

				[$labelLoopBreakName, $labelLoopContinueName] = array_pop($this->loopStack);

				$labelBreak    = [new Label($labelLoopBreakName)];
				$labelContinue = [new Label($labelLoopContinueName)];

				return array_merge([$node], $labelContinue, $labelBreak);
			}

			if ($node instanceof While_) {

				[$labelLoopBreakName, $labelLoopContinueName] = array_pop($this->loopStack);

				$condition     = $node->cond;
				$stmts         = $node->stmts;
				$labelBreak    = [new Label($labelLoopBreakName)];
				$gotoBreak     = [new Goto_($labelLoopBreakName)];
				$labelContinue = [new Label($labelLoopContinueName)];
				$gotoContinue  = [new Goto_($labelLoopContinueName)];
				if ($condition instanceof BooleanNot) {

					$newCondition = $condition->expr;
				} else {

					$newCondition = new BooleanNot($condition);
				}

				$if        = new If_($newCondition);
				$if->stmts = $gotoBreak;
				return array_merge($labelContinue, [$if], $stmts, $gotoContinue, $labelBreak);
			}

			if ($node instanceof Do_) {

				[$labelLoopBreakName, $labelLoopContinueName] = array_pop($this->loopStack);

				$condition     = $node->cond;
				$stmts         = $node->stmts;
				$labelBreak    = [new Label($labelLoopBreakName)];
				$labelContinue = [new Label($labelLoopContinueName)];
				$gotoContinue  = [new Goto_($labelLoopContinueName)];
				$if            = new If_($condition);
				$if->stmts     = $gotoContinue;

				return array_merge($labelContinue, $stmts, [$if], $labelBreak);
			}

			if ($node instanceof Break_) {

				$count = 1;
				if (isset($node->num)) {
					if ($node->num instanceof LNumber) {

						$count = $node->num->value;
					} else {

						throw new Exception("Error: your use of break statement is not compatible with obfuscator!" . PHP_EOL . "\tAt max 1 literal numeric parameter is allowed...");
					}
				}
				if (count($this->loopStack) - $count < 0) {

					throw new Exception("Error: break statement outside loop found!;" . PHP_EOL . (($debugMode == 2) ? print_r($node, true) : ''));
				}

				[$labelLoopBreakName,] = $this->loopStack[count($this->loopStack) - $count];

				$node         = new Goto_($labelLoopBreakName);
				$nodeModified = true;
			}
			if ($node instanceof Continue_) {

				$count = 1;
				if (isset($node->num)) {

					if ($node->num instanceof LNumber) {

						$count = $node->num->value;
					} else {

						throw new Exception("Error: your use of continue statement is not compatible with yakpro-po!" . PHP_EOL . "\tAt max 1 literal numeric parameter is allowed...");
					}
				}

				if (count($this->loopStack) - $count < 0) {

					throw new Exception("Error: continue statement outside loop found!;" . PHP_EOL . (($debugMode == 2) ? print_r($node, true) : ''));
				}
				[, $labelLoopContinueName] = $this->loopStack[count($this->loopStack) - $count];
				$node         = new Goto_($labelLoopContinueName);
				$nodeModified = true;
			}
		}

		if ($this->getConfig()->isShuffleStmts()) {
			if ($node instanceof If_
				|| $node instanceof Case_
				|| $node instanceof Catch_
				|| $node instanceof Closure
				|| $node instanceof Foreach_
				|| $node instanceof TryCatch
				|| $node instanceof Function_
				|| $node instanceof ClassMethod) {

				if ($this->shuffleStmts($node)) {

					$nodeModified = true;
				}
			}

			if ($node instanceof If_) {

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

		array_pop($this->nodeStack);
		if ($nodeModified) {

			return $node;
		}
	}

	/**
	 * @param $value
	 *
	 * @return bool
	 */
	private function isValidValue($value): bool
	{
		return is_string($value) && strlen($value) !== 0;
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
	 * @param Node $node
	 *
	 * @return string
	 */
	private function getIdentifierName(Node $node): string
	{
		$name = '';
		if ($node instanceof Identifier
			|| $node instanceof VarLikeIdentifier) {

			$name = $node->name;
		}

		return $name;
	}

	/**
	 * @param Node        $node
	 * @param string|null $name
	 */
	private function setIdentifierName(Node &$node, ?string $name)
	{
		if ($node instanceof Identifier
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

			if ($node instanceof Label
				|| $node instanceof Goto_) {

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

			if ($node instanceof InlineHTML) {

				$node         = new Echo_([new String_($node->value)]);
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

				$nodeModified = $this->maybeScramble($node, $scrambler);
				if ($node instanceof Catch_
					|| $node instanceof Param
					|| $node instanceof ClosureUse) {

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

			if ($node instanceof PropertyFetch
				|| $node instanceof PropertyProperty
				|| $node instanceof StaticPropertyFetch) {

				$nodeModified = $this->scrambleIdentifier($node, 'property');
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
			if ($node instanceof FuncCall) {

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
								if ($arg instanceof String_) {

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
			if ($node instanceof ConstFetch) {

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

			if (($node instanceof Const_)
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
			if ($node instanceof Class_) {

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
			if ($node instanceof New_
				|| $node instanceof StaticCall
				|| $node instanceof Instanceof_
				|| $node instanceof ClassConstFetch
				|| $node instanceof StaticPropertyFetch) {

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
			if ($node instanceof Param) {

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
				&& ($node instanceof ClassMethod
					|| $node instanceof Function_)) {

				$nodeTmp = $node->returnType;
				if ($nodeTmp instanceof NullableType
					&& isset($nodeTmp->type)) {

					$nodeTmp = $nodeTmp->type;
				}
				if ($nodeTmp instanceof Name
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
			if ($node instanceof Catch_
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
			if ($node instanceof Namespace_
				|| $node instanceof UseUse) {

				$nodeModified = $this->scrambleParts($node, $scrambler);
			}
			if (($node instanceof FuncCall)
				|| ($node instanceof ConstFetch)) {

				$nodeModified = $this->scrambleParts($node, $scrambler, 1);
			}

			if ($node instanceof New_
				|| $node instanceof StaticCall
				|| $node instanceof Instanceof_
				|| $node instanceof ClassConstFetch
				|| $node instanceof StaticPropertyFetch) {

				$nodeModified = $this->scrambleParts($node, $scrambler, 1, 'class');
			}

			if ($node instanceof Class_) {

				$nodeModified = $this->scrambleParts($node, $scrambler, 1, 'extends');

				$nodeModified = $this->scrambleLoopParts($node, $scrambler, 1, 'implements');
			}
			if ($node instanceof Param) {

				$nodeModified = $this->scrambleParts($node, $scrambler, 1, 'type');
			}
			if ($node instanceof Interface_) {

				$nodeModified = $this->scrambleLoopParts($node, $scrambler, 1, 'extends');
			}
			if ($node instanceof TraitUse) {

				$nodeModified = $this->scrambleLoopParts($node, $scrambler, 1, 'traits');
			}
			if ($node instanceof Catch_) {

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
			if ($node instanceof Trait_) {

				$nodeModified = $this->scrambleIdentifier($node, $scrambler);
			}

			if ($node instanceof TraitUse) {
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

			if ($node instanceof ClassMethod
				|| $node instanceof MethodCall
				|| $node instanceof StaticCall) {

				$nodeModified = $this->scrambleIdentifier($node, 'method');
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
			if ($node instanceof Function_) {

				$this->scramble($node, $node->name->name, $scrambler);
			}

			if ($node instanceof FuncCall
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
							if (!$arg instanceof String_) {

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

			if ($node instanceof Interface_) {

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
			if ($node instanceof Class_) {
				if (isset($node->{'implements'})
					&& count($node->{'implements'})) {

					for ($j = 0; $j < count($node->{'implements'}); ++$j) {

						$parts = $node->{'implements'}[$j]->parts;
						$name  = $parts[count($parts) - 1];
						if ($this->isValidValue($name)) {

							$value = $scrambler->scramble($name);
							if ($value !== $name) {

								$node->{'implements'}[$j]->parts[count($parts) - 1] = $value;
								$nodeModified                                       = true;
							}
						}
					}
				}
			}
		}
	}

	/**
	 * @param $node
	 *
	 * @return bool
	 */
	private function isValidNamespace($node): bool
	{
		$parent  = false;
		$isValid = true;
		if ($node instanceof ClassMethod
			|| $node instanceof Class_) {

			$parent = $node->getAttribute('parent');
		} else if ($node instanceof Namespace_) {

			if ($prefixes = $this->getConfig()->getIncludeNamespacesPrefix()) {

				$isValid   = false;
				$namespace = implode('\\', $node->name->parts);
				if ($namespace) {

					foreach ($prefixes as $prefix) {

						if (substr($namespace, 0, strlen($prefix)) === $prefix) {

							$isValid = true;
							break;
						}
					}
				}
			}
		}
		if ($parent) {

			$isValid = $this->isValidNamespace($node);
		}

		return $isValid;
	}

	/**
	 * @param Node $node
	 * @param bool $nodeModified
	 */
	private function maybeObfuscateClassConstant(Node &$node, &$nodeModified = false)
	{
		if ($this->getConfig()->isObfuscateClassConstant()) {

			$scrambler = $this->getScrambler(self::CLASS_CONSTANT_TYPE);
			if ($node instanceof ClassConstFetch
				|| ($node instanceof Const_
					&& $this->isConstDefinition())) {

				$nodeModified = $this->scrambleIdentifier($node, $scrambler);
			}
		}
	}

	/**
	 * @param Node $node
	 * @param bool $nodeModified
	 */
	private function maybeObfuscateClassOrFunctionName(Node &$node, &$nodeModified = false)
	{
		if ($node instanceof UseUse) {

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