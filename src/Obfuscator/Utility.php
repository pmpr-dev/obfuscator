<?php

namespace Obfuscator;

use Obfuscator\Interfaces\ConstantInterface;
use Obfuscator\Parser\Comment;
use phpDocumentor\Reflection\DocBlockFactory;
use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Stmt\Goto_;
use PhpParser\Node\Stmt\Label;
use PhpParser\Node\VarLikeIdentifier;

/**
 * Class Utility
 * @package Obfuscator
 */
class Utility implements ConstantInterface
{
	/**
	 * @param $string
	 *
	 * @return string[]
	 */
	public function encodeString($string): array
	{
		$letters = str_split($string);

		$unique     = array_unique($letters);
		$binaries   = [];
		$characters = array_flip($this->getValidCharacters());
		foreach ($unique as $letter) {

			$position          = $characters[$letter] + 1;
			$binaries[$letter] = pow(2, $position - 1);
		}

		$start = rand(100, 150);

		asort($binaries);

		$keys = [];
		foreach ($binaries as $letter => $binary) {

			$keys[$letter] = $start;

			$start += (rand(1, 10) * 2 + 1);;
		}

		$result = [];
		foreach ($letters as $letter) {

			$result[] = $keys[$letter];
		}

		$sum    = array_sum($binaries);
		$result = implode('', $result);

		return [$sum, $result];

	}

	/**
	 * @return array
	 */
	public function getValidCharacters(): array
	{
		return array_merge(range('a', 'z'), range(0, 9), ['/', ':', '.', '\\', '_', '-']);
	}

	/**
	 * @param $list
	 *
	 * @return array
	 */
	public function shuffleAssoc($list): array
	{
		if (!is_array($list)) return $list;

		$keys = array_keys($list);
		shuffle($keys);
		$random = [];
		foreach ($keys as $key) {
			$random[$key] = $list[$key];
		}
		return $random;
	}


	/**
	 * @param $string
	 *
	 * @return string
	 */
	public function obfuscateString($string): string
	{
		$length = strlen($string);
		$result = '';
		for ($i = 0; $i < $length; ++$i) {

			$result .= mt_rand(0, 1) ? "\x" . dechex(ord($string[$i])) : "\\" . decoct(ord($string[$i]));
		}
		return $result;
	}

	/**
	 * @param $path
	 */
	public function createContextDirectories($path)
	{
		$dirs = [
			$path,
			//			"{$path}/context",
			//			"{$path}/obfuscated",
		];
		foreach ($dirs as $dir) {
			if (!file_exists($dir)) {

				mkdir($dir, 0777, true);
			}
			if (!file_exists($dir)) {

				fprintf(STDERR, "Error:\tCannot create directory [%s]%s", $dir, PHP_EOL);
				exit(51);
			}
		}
		$signature = realpath($path) . self::SIGNATURE;
		if (!file_exists($signature)) {

			touch($signature);
		}
	}

	/**
	 * @param $path
	 */
	public function removeDirectory($path)
	{
		if ($dp = opendir($path)) {

			while (($entry = readdir($dp)) !== false) {
				if (!in_array($entry, ['.', '..'])) {

					$filepath = "{$path}/{$entry}";
					if (!is_link($filepath) && is_dir($filepath)) {

						$this->removeDirectory("{$path}/{$entry}");
					} else {

						unlink("{$path}/{$entry}");
					}
				}
			}

			closedir($dp);
			rmdir($path);
		}
	}

	/**
	 * @param $stmts
	 *
	 * @return array
	 */
	public function shuffleStmts($stmts): array
	{
		global $config, $scramblers;

		if ($config instanceof Config
			&& $config->isShuffleStmts()) {

			$chunkSize = $this->getShuffleChunkSize($stmts);
			if ($chunkSize > 0) {

				$count = count($stmts);
				if ($count >= (2 * $chunkSize)) {

					$scrambler = $scramblers[self::LABEL_TYPE] ?? false;
					if ($scrambler instanceof Scrambler) {

						$labelNamePrev = $scrambler->scramble($scrambler->generateLabelName());
						$firstGoto     = new Goto_($labelNamePrev);
						$labelName     = '';
						$temp          = [];
						$chunk         = [];

						for ($i = 0; $i < $count; ++$i) {

							$chunk[] = $stmts[$i];
							if (count($chunk) >= $chunkSize) {

								$label         = [new Label($labelNamePrev)];
								$labelName     = $scrambler->scramble($scrambler->generateLabelName());
								$goto          = [new Goto_($labelName)];
								$temp[]        = array_merge($label, $chunk, $goto);
								$labelNamePrev = $labelName;
								$chunk         = [];
							}
						}

						if (count($chunk) > 0) {
							$label         = [new Label($labelNamePrev)];
							$labelName     = $scrambler->scramble($scrambler->generateLabelName());
							$goto          = [new Goto_($labelName)];
							$temp[]        = array_merge($label, $chunk, $goto);
							$labelNamePrev = $labelName;
							$chunk         = [];
						}

						$last_label = new Label($labelName);
						shuffle($temp);
						$stmts   = [];
						$stmts[] = $firstGoto;
						foreach ($temp as $stmt) {
							foreach ($stmt as $inst) {

								$stmts[] = $inst;
							}
						}
						$stmts[] = $last_label;
					}
				}
			}
		}
		return $stmts;
	}

	/**
	 * @param $statements
	 *
	 * @return int|mixed|string
	 */
	public function getShuffleChunkSize(&$statements)
	{
		global $config;

		$chunkSize = 1;
		if ($config instanceof Config) {

			$count        = count($statements);
			$minChunkSize = $config->getShuffleStmtsMinChunkSize();
			switch ($config->getShuffleStmtsChunkMode()) {
				case 'ratio':

					$chunkSize = max($count / $config->getShuffleStmtsChunkRatio(), $minChunkSize);
					break;
				case 'fixed':

					$chunkSize = $minChunkSize;
					break;
				default:
					$chunkSize = 1;       // should never occur!
			}
		}
		return $chunkSize;
	}

	/**
	 * @param string $string
	 *
	 * @return string
	 */
	public function removeWhitespaces(string $string): string
	{
		$tmpFilename = tempnam('/tmp', self::PREFIX);
		file_put_contents($tmpFilename, $string);
		$str = php_strip_whitespace($tmpFilename);  // can remove more whitespaces
		unlink($tmpFilename);
		return $str;
	}

	/**
	 * @param string|null $string
	 *
	 * @return string|null
	 */
	public function snake2camel(?string $string): ?string
	{
		if ($string) {

			$snakeCase = strtolower(str_replace('-', '_', $string));
			if ($snakeCase) {

				$string    = str_replace(' ', '', ucwords(str_replace('_', ' ', $snakeCase)));
				$string[0] = strtolower($string[0]);
			}
		}

		return $string;
	}

	/**
	 * @param Node $node
	 *
	 * @return mixed|Node\Stmt\ClassMethod
	 */
	public function getParentClassMethod(Node $node)
	{
		return $this->getParentUntilFound($node, Node\Stmt\ClassMethod::class);
	}

	/**
	 * @param Node $node
	 *
	 * @return mixed|Node\Stmt\ClassMethod
	 */
	public function getParentClass(Node $node)
	{
		return $this->getParentUntilFound($node, Node\Stmt\Class_::class);
	}

	/**
	 * @param Node $node
	 *
	 * @return string
	 */
	public function getParentNamespace(Node $node): string
	{
		$namespace = $this->getParentUntilFound($node, Node\Stmt\Namespace_::class);
		if ($namespace instanceof Node\Stmt\Namespace_) {

			$namespace = implode('\\', $namespace->name->parts);
		} else {

			$namespace = '';
		}

		return $namespace;
	}

	/**
	 * @param Node   $node
	 * @param string $type
	 *
	 * @return mixed|Node
	 */
	public function getParentUntilFound(Node $node, string $type)
	{
		if (class_exists($type)
			&& !is_a($node, $type)) {

			$node = $node->getAttribute('parent');
			if ($node instanceof Node) {

				$node = $this->getParentUntilFound($node, $type);
			}
		}

		return $node;
	}

	/**
	 * @param Node $node
	 *
	 * @return string
	 */
	public function getIdentifierName(Node $node): string
	{
		$name = '';
		if ($node instanceof Node\Identifier
			|| $node instanceof VarLikeIdentifier) {

			$name = $node->name;
		}

		return $name;
	}

	/**
	 * @param $string
	 *
	 * @return bool
	 */
	public function isSnakeCase($string): bool
	{
		return strpos($string, '_') !== false;
	}

	/**
	 * @param Node $node
	 *
	 * @return Doc|null
	 */
	public function getDocComment(Node $node): ?Doc
	{
		$doc = $node->getDocComment();
		if (!$doc) {

			$parent = $node->getAttribute('parent');
			if ($parent) {

				$isValid = false;
				if ($node instanceof Node\Const_
					|| $node instanceof Node\Stmt\ClassConst) {

					$isValid = true;
				} else if ($node instanceof Node\Expr\ClassConstFetch) {

					if ($parent instanceof Node\Arg
						|| $parent instanceof Node\Expr\Assign
						|| $parent instanceof Node\Expr\Assign
						|| $parent instanceof Node\Stmt\Return_) {

						$isValid = true;
					}
				} else if ($node instanceof Node\Expr\MethodCall
					|| $node instanceof Node\Expr\StaticCall) {

					if ($parent instanceof Node\Expr\Assign
						|| $parent instanceof Node\Stmt\If_
						|| $parent instanceof Node\Stmt\Return_
						|| $parent instanceof Node\Stmt\Switch_
						|| $parent instanceof Node\Stmt\Foreach_
						|| $parent instanceof Node\Expr\MethodCall
						|| $parent instanceof Node\Expr\StaticCall) {

						$isValid = true;
					}
				}
				if ($isValid) {

					$doc = $this->getDocComment($parent);
				}
			}
		}
		return $doc;
	}

	/**
	 * @param $node
	 *
	 * @return mixed
	 */
	public function getDocComments($node)
	{
		global $docParser;
		$docs = [];
		if ($docParser instanceof DocBlockFactory) {

			$doc = $this->getDocComment($node);
			if ($doc instanceof Doc
				&& ($docText = $doc->getText())) {

				$docs = $docParser->create($docText)->getTagsByName(Comment::NAME);
			}
		}

		return $docs;
	}

	/**
	 * @param Node $node
	 *
	 * @return bool
	 */
	public function hasExcludeDocComment(Node $node): bool
	{
		$hasExclude = false;

		$tags = $this->getDocComments($node);
		foreach ($tags as $tag) {

			if ($tag instanceof Comment) {

				if ($tag->isExclude()
					&& $this->isMethodComment($node, $tag)) {

					$hasExclude = true;
					break;
				}
			}
		}

		return $hasExclude;
	}

	/**
	 * @param Node $node
	 *
	 * @return bool
	 */
	public function hasRemoveDocComment(Node $node): bool
	{
		$hasRemove = false;

		$tags = $this->getDocComments($node);
		foreach ($tags as $tag) {

			if ($tag instanceof Comment) {

				if ($tag->isRemove()) {

					$hasRemove = true;
					break;
				}
			}
		}

		return $hasRemove;
	}

	/**
	 * @param Node $node
	 *
	 * @return bool
	 */
	public function hasEncodeDocComment(Node $node): bool
	{
		$hasExclude = false;

		$tags = $this->getDocComments($node);
		foreach ($tags as $tag) {

			if ($tag instanceof Comment) {

				if ($tag->isEncode()) {

					$hasExclude = true;
					break;
				}
			}
		}

		return $hasExclude;
	}

	/**
	 * @param Node    $node
	 * @param Comment $tag
	 *
	 * @return bool
	 */
	public function isMethodComment(Node $node, Comment $tag): bool
	{
		$check = true;
		if ($node instanceof Node\Expr\MethodCall
			|| $node instanceof Node\Expr\StaticCall) {

			$name   = $this->getIdentifierName($node->name);
			$target = $tag->getTarget();
			$count  = count($target);
			if (count($target) <= 1) {

				$sequence = array_reverse($this->getCallSequence($node));
				if ($count == 1) {

					$target   = $target[0];
					$sequence = array_slice($sequence, array_search($target, $sequence), null, true);
					$check    = in_array($name, $sequence);
				} else {

					// just exclude last call
					$check = end($sequence) == $name;
				}
			} else {

				$check = in_array($name, $target);
			}
		}

		return $check;
	}

	/**
	 * @param Node\Expr\StaticCall|Node\Expr\MethodCall $node
	 * @param array                                     $sequence
	 *
	 * @return array
	 */
	public function getCallSequence($node, $sequence = []): array
	{
		$parent = $node->getAttribute('parent');
		if ($parent instanceof Node\Expr\MethodCall
			|| $parent instanceof Node\Expr\StaticCall) {

			$sequence = $this->getCallSequence($parent, $sequence);
		}

		$sequence[] = $this->getIdentifierName($node->name);

		return $sequence;
	}
}