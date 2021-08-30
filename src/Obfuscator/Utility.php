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
	 * @param $path
	 */
	public function createContextDirectories($path)
	{
		$dirs = [
			$path,
			"{$path}/context",
			"{$path}/obfuscated",
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
	 * @param Node        $node
	 * @param string|null $name
	 *
	 * @return string
	 */
	public function getMatchedMethodName(Node $node, string $name = null): string
	{
		global $isMethodGathering, $gatheredMethods;

		$return = '';
		if (!$isMethodGathering
			&& $gatheredMethods) {

			$method = $this->getParentClassMethod($node);
			if ($method) {

				if (!$name
					&& isset($node->name)) {

					$name = $this->getIdentifierName($node->name);
				}
				if ($name) {

					$class     = false;
					$classNode = $this->getParentClass($node);
					$namespace = $this->getParentNamespace($classNode);
					$comments  = $this->getParsedDocComment($method, false);
					if ($comments && is_array($comments)) {

						$found = null;
						foreach ($comments as $comment) {

							if ($comment->getMethod() == $name) {

								$found = $comment;
								break;
							} else if ($comment->isOrigin()) {

								$found = $comment;
							}
						}

						if ($found instanceof Comment) {

							$class = $found->getClass();
						}
						if ($comments) {
							if (!$class) {

								$class = $namespace;
							} else if ($classNode) {

								$classname = $this->getIdentifierName($classNode->name);
								if ($classname === $class) {

									$class = $namespace;
								}
							}
							if (isset($gatheredMethods[$class][$name])
								&& $gatheredMethods[$class][$name]) {

								$return = $gatheredMethods[$class][$name];
							}
						}
					}
				}
			}
		}

		return $return;
	}

	/**
	 * @param Node   $node
	 * @param string $onlyOrigin
	 *
	 * @return Comment[]|Comment|null
	 */
	public function getParsedDocComment(Node $node, $onlyOrigin = true)
	{
		global $docParser;
		$return = [];
		if ($docParser instanceof DocBlockFactory) {

			$doc = $node->getDocComment();
			if ($doc instanceof Doc
				&& ($docText = $doc->getText())) {

				$parsed = $docParser->create($docText);
				$return = $parsed->getTagsByName(Comment::NAME);
				if ($onlyOrigin) {

					foreach ($return as $item) {
						if ($item instanceof Comment) {

							if ($item->isOrigin()) {

								$return = $item;
								break;
							}
						}
					}
					if (!$return instanceof Comment) {

						$return = null;
					}
				}
			}
		}

		return $return;
	}

}