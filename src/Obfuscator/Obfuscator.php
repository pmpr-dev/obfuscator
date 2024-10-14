<?php

namespace Obfuscator;

use Exception;
use Obfuscator\Parser\Comment;
use Obfuscator\Parser\Grab;
use Obfuscator\Parser\PrettyPrinter;
use Obfuscator\Parser\Scram;
use Obfuscator\Parser\Traverser;
use phpDocumentor\Reflection\DocBlockFactory;
use PhpParser\Error;
use PhpParser\Node\Stmt\Use_;
use PhpParser\ParserFactory;

/**
 * Class Obfuscator
 * @package Obfuscator
 */
class Obfuscator extends Container
{
	/**
	 * Obfuscator constructor.
	 *
	 * @param array $args
	 */
	public function __construct(array $args = [])
	{
		$this->init($args);
	}

	/**
	 * @param array $args
	 */
	private function init(array $args = [])
	{
		global $config, $scramblers, $parser, $prettyPrinter, $traverser, $docParser;

		$config = Config::getInstance($args);
		$config->validate();

		$docParser = DocBlockFactory::createInstance([
			Comment::NAME => Comment::class,
		]);

		$prettyPrinter = new PrettyPrinter();

		$types = [
			self::LABEL_TYPE,
			self::METHOD_TYPE,
			self::CONSTANT_TYPE,
			self::VARIABLE_TYPE,
			self::PROPERTY_TYPE,
			self::CLASS_CONSTANT_TYPE,
			self::FUNCTION_OR_CLASS_TYPE,
		];

		foreach ($types as $type) {

			$scramblers[$type] = new Scrambler($type, $config);
		}

		$path = $config->getTargetDirectory();
		if ($config->isCleanMode() &&
			file_exists($path . self::SIGNATURE)) {

			if (!$config->isSilent()) {

				fprintf(STDERR, "Info:\tRemoving directory\t= [%s]%s", $path, PHP_EOL);
				$this->getUtility()->removeDirectory($path);
			}
			exit(31);
		}

		$source = $config->getSourceDirectory();

		$parser    = (new ParserFactory())->create($config->getParserMode());
		$traverser = new Traverser();

		$traverser->addVisitor(new Grab($config));

		$this->parseDirectory($path, $source, true);

		$this->updateGrabbed();

		$this->getUtility()->createContextDirectories($path);

		$traverser = new Traverser();
		$traverser->addVisitor(new Scram($config, $scramblers));

		$this->parseDirectory($path, $source, false);
	}

	/**
	 * @param string $target
	 * @param string $source
	 * @param bool   $grabbing
	 */
	private function parseDirectory(string $target, string $source, bool $grabbing = false)
	{
		global $config;

		static $recursionLevel = 0;

		$maxNestedDir = $config->getMaxNestedDirectory();
		if ($config instanceof Config
			&& ++$recursionLevel <= $maxNestedDir) {

			if ($config->isFollowSymlinks()) {

				fprintf(STDERR, "Error:\t [%s] nested directories have been created!\nloop detected when follow_symlinks option is set to true!%s", $maxNestedDir, PHP_EOL);
				exit(52);
			}
			if (!$dp = opendir($source)) {

				fprintf(STDERR, "Error:\t [%s] directory does not exists!%s", $source, PHP_EOL);
				exit(53);
			}

			$skips = $config->getSkip();
			$keeps = $config->getKeep();
			while (($entry = readdir($dp)) !== false) {

				if (!in_array($entry, ['.', '..'])) {

					$sourcePath = "{$source}/{$entry}";
					$targetPath = "{$target}/{$entry}";

					$sourceStat = $targetStat = false;


					if (file_exists($sourcePath)) {

						$sourceStat = @lstat($sourcePath);
					}
					if (file_exists($targetPath)) {

						$targetStat = @lstat($targetPath);
					}

					if ($sourceStat === false) {

						fprintf(STDERR, "Error:\t cannot stat [%s] !%s", $sourcePath, PHP_EOL);
						exit(54);
					}

					if (!$this->isPathExcluded($sourcePath, $skips)) {

						if (!$config->isFollowSymlinks()
							&& is_link($sourcePath)) {

							if ($targetStat !== false && is_link($targetPath)
								&& ($sourceStat['mtime'] <= $targetStat['mtime'])) {

								continue;
							}
							if ($targetStat !== false) {

								if (is_dir($targetPath)) {

									$this->getUtility()->removeDirectory($targetPath);
								} else {

									if (unlink($targetPath) === false) {

										fprintf(STDERR, "Error:\t cannot unlink [%s] !%s", $targetPath, PHP_EOL);
										exit(55);
									}
								}
							}
							// Do not warn on non existing symbolinc link target!
							@symlink(readlink($sourcePath), $targetPath);
							if (strtolower(PHP_OS) == 'linux') {

								`touch '$targetPath' --no-dereference --reference='$sourcePath' `;
							}
						} else if (is_dir($sourcePath)) {

							if ($targetStat !== false) {

								if (!is_dir($targetPath)) {

									if (unlink($targetPath) === false) {

										fprintf(STDERR, "Error:\t cannot unlink [%s] !%s", $targetPath, PHP_EOL);
										exit(56);
									}
								}
							}
							if (!file_exists($targetPath)) {

								mkdir($targetPath, 0777, true);
							}
							$this->parseDirectory($targetPath, $sourcePath, $grabbing);
						} else if (is_file($sourcePath)) {

							if ($targetStat !== false
								&& is_dir($targetPath)) {

								$this->getUtility()->removeDirectory($targetPath);
							}
							if (!$config->isOverwrite() && $targetStat !== false
								&& ($sourceStat['mtime'] <= $targetStat['mtime'])) {

								// do not process if source timestamp is not greater than target
								continue;
							}

							$ext = pathinfo($sourcePath, PATHINFO_EXTENSION);
							if ($grabbing) {

								if ($ext == 'php') {

									$this->grabbing($sourcePath);
								}
							} else {

								if ($ext != 'php' || $this->isPathExcluded($sourcePath, $keeps)) {

									$content = file_get_contents($sourcePath);
                                } else {

									$content = $this->obfuscate($sourcePath);
									if ($content === null) {

										if ($config->isAbortOnError()) {

											fprintf(STDERR, "Aborting...%s", PHP_EOL);
											exit(57);
										}
									}
								}

								file_put_contents($targetPath, $content . PHP_EOL);

								touch($targetPath, $sourceStat['mtime']);
								chmod($targetPath, $sourceStat['mode']);
								chgrp($targetPath, $sourceStat['gid']);
								chown($targetPath, $sourceStat['uid']);
							}
						} else {

                            fprintf(STDERR, "%s is not a file%s", $sourcePath, PHP_EOL);
                        }
					} else {

                        fprintf(STDERR, "%s excluded%s", $sourcePath, PHP_EOL);
                    }
				}
			}

			closedir($dp);
			--$recursionLevel;
		}
	}

	private function updateGrabbed()
	{
		global $grabbed;

		if (is_array($grabbed)) {

			foreach ($grabbed as $type => $items) {

				foreach ($items as $key => $item) {

					$value = $item['value'] ?? false;
					if ($value) {

						if (is_array($value)) {

							$value = '';
						}
						if ($value && preg_match('/{(.*)::(.*)}/', $value, $matches)) {

							if (isset($matches[0], $matches[1], $matches[2])) {

								$find   = $matches[0];
								$first  = $matches[1];
								$second = $matches[2];

								if (isset($grabbed[$first][$second])) {

									$val = $grabbed[$first][$second]['value'];
								} else {

									$val = strtolower($second);
								}
								$item['value']        = str_replace($find, $val, $value);
								$grabbed[$type][$key] = $item;
								fprintf(STDERR, "Update grabbed $ from %s to %s%s", $type, $second, $item['value'], PHP_EOL);

							}
						}
					}
				}
			}
		}
	}

	/**
	 * @param string $filename
	 */
	private function grabbing(string $filename)
	{
		global $parser, $traverser;

		$source = file($filename);

		if (isset($source[0]) && substr($source[0], 0, 2) == '#!') {

			$tmpFilename = tempnam(sys_get_temp_dir(), self::PREFIX);
			file_put_contents($tmpFilename, implode(PHP_EOL, $source));
			$filename = $tmpFilename; // override
		}

		try {
//			fprintf(STDERR, "Grabbing %s data%s", $filename, PHP_EOL);
			try {

				if (is_array($source)) {

					$source = implode('', $source);
				}
				// PHP-Parser returns the syntax tree
				$stmts = $parser->parse($source);
			} catch (Error $e) {

                fprintf(STDERR, "Grabbing Parse Error [%s]:%s\t%s%s", $filename, PHP_EOL, $e->getMessage(), PHP_EOL);

				$stmts = $parser->parse(file_get_contents($filename));
			}

			$traverser->traverse($stmts);
		} catch (Exception $e) {

			fprintf(STDERR, "Grabbing Parse Error [%s]:%s\t%s%s", $filename, PHP_EOL, $e->getMessage(), PHP_EOL);
		}
	}

	/**
	 * @param string $filename
	 *
	 * @return string|null
	 */
	private function obfuscate(string $filename): ?string
	{
		global $config, $parser, $prettyPrinter, $traverser;

		$return = null;
		if ($config instanceof Config) {

			$source      = file($filename);
			$tmpFilename = $firstLine = '';

			if (isset($source[0]) && substr($source[0], 0, 2) == '#!') {

				$firstLine   = array_shift($source);
				$tmpFilename = tempnam(sys_get_temp_dir(), self::PREFIX);
				file_put_contents($tmpFilename, implode(PHP_EOL, $source));
				$filename = $tmpFilename; // override
			}

			try {
//				fprintf(STDERR, "Obfuscating %s%s", $filename, PHP_EOL);

				$source = implode('', $source);

				if ($source == '') {

					if ($config->isAllowOverwriteEmptyFiles()) {

						return $source;
					}
					throw new Exception("Error obfuscating php_strip_whitespace returned an empty string!");
				}

				$stmts = $parser->parse($source);

				//  Use PHP-Parser function to traverse the syntax tree and obfuscate names
				$stmts = $traverser->traverse($stmts);
				if ($config->isShuffleStmts()
					&& (count($stmts) > 2)) {

					$lastInst       = array_pop($stmts);
					$lastUseStmtPos = -1;
					// if a use statement exists, do not shuffle before the last use statement
					foreach ($stmts as $i => $stmt) {
						//TODO: enhancement: keep all use statements at their position, and shuffle all sub-parts
						if ($stmt instanceof Use_) {

							$lastUseStmtPos = $i;
						}
					}

					if ($lastUseStmtPos < 0) {

						$stmtsToShuffle = $stmts;
						$stmts          = [];
					} else {

						$stmtsToShuffle = array_slice($stmts, $lastUseStmtPos + 1);
						$stmts          = array_slice($stmts, 0, $lastUseStmtPos + 1);
					}

					$stmts   = array_merge($stmts, $this->getUtility()->shuffleStmts($stmtsToShuffle));
					$stmts[] = $lastInst;
				}

				//  Use PHP-Parser function to output the obfuscated source, taking the modified obfuscated syntax tree as input
				$code = trim($prettyPrinter->prettyPrintFile($stmts));

				if ($config->isRemoveComment()) {

					$tokens = token_get_all($code);
					$output = '';
					// remove comments and annotations
					foreach ($tokens as $token) {
						if (is_array($token)) {
							if (in_array($token[0], [T_DOC_COMMENT, T_COMMENT])) {

								continue;
							}
							$token = $token[1];
						}
						$output .= $token;
					}
					if ($output) {

						$code = $output;
					}
				}

				if ($config->isStripIndentation()) {

					$code = $this->getUtility()->removeWhitespaces($code);
				}

				$endCode = substr($code, 6);

				$code = '<?php' . PHP_EOL;
				$code .= $config->getComment();
				$code .= $endCode;

				if (($tmpFilename != '')
					&& ($firstLine != '')) {

					$code = $firstLine . $code;
					unlink($tmpFilename);
				}

				$return = trim($code);
			} catch (Exception $e) {

				fprintf(STDERR, "Obfuscator Parse Error [%s]:%s\t%s%s", $filename, PHP_EOL, $e->getMessage(), PHP_EOL);
			}
		}
		return $return;
	}

	/**
	 * @param       $path
	 * @param array $excludes
	 *
	 * @return bool
	 */
	private function isPathExcluded($path, $excludes = []): bool
	{
		$excluded = false;

		if (is_array($excludes) && $excludes) {

			foreach ($excludes as $exclude) {

				if ($exclude && false !== strpos($path, $exclude)) {

                    fprintf(STDERR, "%s is excluded by %s%s", $path, $exclude, PHP_EOL);
                    $excluded = true;
					break;
				}
			}
		}
		return $excluded;
	}
}