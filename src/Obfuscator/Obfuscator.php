<?php

namespace Obfuscator;

use Exception;
use Obfuscator\Parser\Comment;
use Obfuscator\Parser\PrettyPrinter;
use Obfuscator\Parser\Visitor;
use phpDocumentor\Reflection\DocBlockFactory;
use PhpParser\Error;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeTraverser;
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

		$path = "{$config->getTargetDirectory()}/obfuscator";
		if ($config->isCleanMode() &&
			file_exists($path . self::SIGNATURE)) {

			if (!$config->isSilent()) {

				fprintf(STDERR, "Info:\tRemoving directory\t= [%s]%s", $path, PHP_EOL);
				$this->getUtility()->removeDirectory($path);
				exit(31);
			}
		}

		$this->getUtility()->createContextDirectories($path);

		$types = [
			self::LABEL_TYPE,
			self::METHOD_TYPE,
			self::CONSTANT_TYPE,
			self::VARIABLE_TYPE,
			self::PROPERTY_TYPE,
			self::CLASS_CONSTANT_TYPE,
			self::FUNCTION_OR_CLASS_TYPE,
		];

		$docParser = DocBlockFactory::createInstance([
			Comment::NAME => Comment::class,
		]);

		$parser        = (new ParserFactory())->create($config->getParserMode());
		$prettyPrinter = new PrettyPrinter();

		foreach ($types as $type) {

			$scramblers[$type] = new Scrambler($type, $config);
		}

		$traverser = new NodeTraverser();
		$traverser->addVisitor(new Visitor($config, $scramblers));

		$this->obfuscateDirectory("{$path}/obfuscated", $config->getSourceDirectory());
	}

	/**
	 * @param string $target
	 * @param string $source
	 * @param bool   $keepMode
	 */
	private function obfuscateDirectory(string $target, string $source, bool $keepMode = false)
	{
		global $config;

		static $recursionLevel = 0;

		$maxNestedDir = $config->getMaxNestedDirectory();
		if (++$recursionLevel <= $maxNestedDir) {

			if ($config->isFollowSymlinks()) {

				fprintf(STDERR, "Error:\t [%s] nested directories have been created!\nloop detected when follow_symlinks option is set to true!%s", $maxNestedDir, PHP_EOL);
				exit(52);
			}
			if (!$dp = opendir($source)) {

				fprintf(STDERR, "Error:\t [%s] directory does not exists!%s", $source, PHP_EOL);
				exit(53);
			}

			while (($entry = readdir($dp)) !== false) {

				if (!in_array($entry, ['.', '..'])) {

					$newKeepMode = $keepMode;

					$sourcePath = "{$source}/{$entry}";
					$sourceStat = @lstat($sourcePath);
					$targetPath = "{$target}/{$entry}";
					$targetStat = @lstat($targetPath);

					if ($sourceStat === false) {

						fprintf(STDERR, "Error:\t cannot stat [%s] !%s", $sourcePath, PHP_EOL);
						exit(54);
					}

					if (!is_array($config->getSkip())
						|| !in_array($sourcePath, $config->getSkip())) {

						if (!$config->isFollowSymlinks()
							&& is_link($sourcePath)) {

							if ($targetStat !== false
								&& is_link($targetPath)
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
								$x = `touch '$targetPath' --no-dereference --reference='$sourcePath' `;
							}
							continue;
						}
						if (is_dir($sourcePath)) {

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
							if (is_array($config->getKeep())
								&& in_array($sourcePath, $config->getKeep())) {

								$newKeepMode = true;
							}
							$this->obfuscateDirectory($targetPath, $sourcePath, $newKeepMode);
							continue;
						}
						if (is_file($sourcePath)) {

							if (($targetStat !== false)
								&& is_dir($targetPath)) {

								$this->getUtility()->removeDirectory($targetPath);
							}
							if (($targetStat !== false)
								&& ($sourceStat['mtime'] <= $targetStat['mtime'])) {

								// do not process if source timestamp is not greater than target
								continue;
							}

							$extension = pathinfo($sourcePath, PATHINFO_EXTENSION);

							$keep = $keepMode;
							if (is_array($config->getKeep())
								&& in_array($sourcePath, $config->getKeep())) {

								$keep = true;
							}

							if (!in_array($extension, $config->getObfuscatePhpExt())) {

								$keep = true;
							}

							if ($keep) {

								file_put_contents($targetPath, file_get_contents($sourcePath));
							} else {

								$obfuscatedString = $this->obfuscate($sourcePath);
								if ($obfuscatedString === null) {

									if (isset($conf->abort_on_error)) {

										fprintf(STDERR, "Aborting...%s", PHP_EOL);
										exit(57);
									}
								}
								file_put_contents($targetPath, $obfuscatedString . PHP_EOL);
							}

							touch($targetPath, $sourceStat['mtime']);
							chmod($targetPath, $sourceStat['mode']);
							chgrp($targetPath, $sourceStat['gid']);
							chown($targetPath, $sourceStat['uid']);
							continue;
						}
					}
				}
			}

			closedir($dp);
			--$recursionLevel;
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
			$SrcFilename = $filename;
			$tmpFilename = $firstLine = '';

			if (substr($source[0], 0, 2) == '#!') {

				$firstLine   = array_shift($source);
				$tmpFilename = tempnam(sys_get_temp_dir(), self::PREFIX);
				file_put_contents($tmpFilename, implode(PHP_EOL, $source));
				$filename = $tmpFilename; // override
			}

			try {

				$source = implode('', $source);
				if ($source == '') {

					if ($config->isAllowOverwriteEmptyFiles()) {

						return $source;
					}
					throw new Exception("Error obfuscating [$SrcFilename]: php_strip_whitespace returned an empty string!");
				}
				fprintf(STDERR, "Obfuscating %s%s", $SrcFilename, PHP_EOL);
				try {


					// PHP-Parser returns the syntax tree
					$stmts = $parser->parse($source);
				} catch (Error $e) {

					$source = file_get_contents($filename);
					$stmts  = $parser->parse($source);
				}
				if ($config->getDebugMode() === 2) {

					$source = file_get_contents($filename);
					$stmts  = $parser->parse($source);
				}

				if ($config->isDebugMode()) {

					var_dump($stmts);
				}

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
}