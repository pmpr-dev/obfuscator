<?php

namespace Obfuscator;

use PhpParser\ParserFactory;

/**
 * Class Config
 * @package Obfuscator
 */
class Config extends Container
{
	/**
	 * Config constructor.
	 *
	 * @param array $args
	 */
	public function __construct($args = [])
	{
		foreach ($args as $prop => $value) {

			$property = $this->getUtility()->snake2camel($prop);
			if (property_exists($this, $property)) {

				$this->{$property} = $value;
			} else {

				$a = 1;
			}
		}

		$this->comment .= "/*   _______________________________________________________" . PHP_EOL;
		$this->comment .= "    |  Obfuscated by PMPR - Php Obfuscator  %-5.5s          |" . PHP_EOL;
		$this->comment .= "    |_______________________________________________________|" . PHP_EOL;
		$this->comment .= "*/" . PHP_EOL;
	}

	/**
	 * @var bool
	 */
	protected bool $cleanMode = false;

	/**
	 * @return bool
	 */
	public function isCleanMode(): bool
	{
		return $this->cleanMode;
	}

	/**
	 * @var int
	 */
	protected int $debugMode = 0;

	/**
	 * @return int
	 */
	public function getDebugMode(): int
	{
		return $this->debugMode;
	}

	/**
	 * @return bool
	 */
	public function isDebugMode(): bool
	{
		return $this->getDebugMode() > 0;
	}

	/**
	 * @var int
	 */
	protected int $maxNestedDirectory = 99;

	/**
	 * @var bool
	 */
	protected bool $followSymlinks = false;

	/**
	 * @return int
	 */
	public function getMaxNestedDirectory(): int
	{
		return $this->maxNestedDirectory;
	}

	/**
	 * @return bool
	 */
	public function isFollowSymlinks(): bool
	{
		return $this->followSymlinks;
	}

	/**
	 * @var array
	 */
	protected array $ignoreTraitsPrefix = [];

	/**
	 * @var array
	 */
	protected array $ignoreLabelsPrefix = [];

	/**
	 * @var array
	 */
	protected array $ignoreClassesPrefix = [];

	/**
	 * @var array
	 */
	protected array $ignoreInterfacesPrefix = [];

	/**
	 * @var array
	 */
	protected array $ignoreNamespacesPrefix = [];

	/**
	 * @return array
	 */
	public function getIgnoreTraitsPrefix(): array
	{
		return $this->ignoreTraitsPrefix;
	}

	/**
	 * @return array
	 */
	public function getIgnoreLabelsPrefix(): array
	{
		return $this->ignoreLabelsPrefix;
	}

	/**
	 * @return array
	 */
	public function getIgnoreClassesPrefix(): array
	{
		return $this->ignoreClassesPrefix;
	}

	/**
	 * @return array
	 */
	public function getIgnoreInterfacesPrefix(): array
	{
		return $this->ignoreInterfacesPrefix;
	}

	/**
	 * @return array
	 */
	public function getIgnoreNamespacesPrefix(): array
	{
		return $this->ignoreNamespacesPrefix;
	}

	/**
	 * @var array
	 */
	protected array $ignoreMethodsPrefix = [];

	/**
	 * @var array
	 */
	protected array $ignorePropertiesPrefix = [];

	/**
	 * @var array
	 */
	protected array $ignoreClassConstantsPrefix = [];

	/**
	 * @return array
	 */
	public function getIgnoreMethodsPrefix(): array
	{
		return $this->ignoreMethodsPrefix;
	}

	/**
	 * @return array
	 */
	public function getIgnorePropertiesPrefix(): array
	{
		return $this->ignorePropertiesPrefix;
	}

	/**
	 * @return array
	 */
	public function getIgnoreClassConstantsPrefix(): array
	{
		return $this->ignoreClassConstantsPrefix;
	}

	/**
	 * @var array
	 */
	protected array $ignoreVariablesPrefix = [];

	/**
	 * @var array
	 */
	protected array $ignoreFunctionsPrefix = [];

	/**
	 * @var array
	 */
	protected array $ignoreConstantsPrefix = [];

	/**
	 * @return array
	 */
	public function getIgnoreVariablesPrefix(): array
	{
		return $this->ignoreVariablesPrefix;
	}

	/**
	 * @return array
	 */
	public function getIgnoreFunctionsPrefix(): array
	{
		return $this->ignoreFunctionsPrefix;
	}

	/**
	 * @return array
	 */
	public function getIgnoreConstantsPrefix(): array
	{
		return $this->ignoreConstantsPrefix;
	}

	/**
	 * @var array
	 */
	protected array $ignoreLabels = [];

	/**
	 * @var array
	 */
	protected array $ignoreTraits = [];

	/**
	 * @var array
	 */
	protected array $ignoreMethods = [];

	/**
	 * @var array
	 */
	protected array $ignoreClasses = [];

	/**
	 * @var array
	 */
	protected array $ignoreConstants = [];

	/**
	 * @var array
	 */
	protected array $ignoreVariables = [];

	/**
	 * @var array
	 */
	protected array $ignoreFunctions = [];

	/**
	 * @var array
	 */
	protected array $ignoreProperties = [];

	/**
	 * @var array
	 */
	protected array $ignoreInterfaces = [];

	/**
	 * @var array
	 */
	protected array $ignoreNamespaces = [];

	/**
	 * @var array
	 */
	protected array $ignoreClassConstants = [];

	/**
	 * @return array
	 */
	public function getIgnoreLabels(): array
	{
		return $this->ignoreLabels;
	}

	/**
	 * @return array
	 */
	public function getIgnoreTraits(): array
	{
		return $this->ignoreTraits;
	}

	/**
	 * @return array
	 */
	public function getIgnoreMethods(): array
	{
		return $this->ignoreMethods;
	}

	/**
	 * @return array
	 */
	public function getIgnoreClasses(): array
	{
		return $this->ignoreClasses;
	}

	/**
	 * @return array
	 */
	public function getIgnoreConstants(): array
	{
		return $this->ignoreConstants;
	}

	/**
	 * @return array
	 */
	public function getIgnoreVariables(): array
	{
		return $this->ignoreVariables;
	}

	/**
	 * @return array
	 */
	public function getIgnoreFunctions(): array
	{
		return $this->ignoreFunctions;
	}

	/**
	 * @return array
	 */
	public function getIgnoreProperties(): array
	{
		return $this->ignoreProperties;
	}

	/**
	 * @return array
	 */
	public function getIgnoreInterfaces(): array
	{
		return $this->ignoreInterfaces;
	}

	/**
	 * @return array
	 */
	public function getIgnoreNamespaces(): array
	{
		return $this->ignoreNamespaces;
	}

	/**
	 * @return array
	 */
	public function getIgnoreClassConstants(): array
	{
		return $this->ignoreClassConstants;
	}

	/**
	 * @var bool
	 */
	protected bool $ignoreSnakeCaseMethod = false;

	/**
	 * @return bool
	 */
	public function isIgnoreSnakeCaseMethod(): bool
	{
		return $this->ignoreSnakeCaseMethod;
	}

	/**
	 * @var bool
	 */
	protected bool $ignoreSnakeCaseVariable = false;

	/**
	 * @return bool
	 */
	public function isIgnoreSnakeCaseVariable(): bool
	{
		return $this->ignoreSnakeCaseVariable;
	}

	/**
	 * @var array|string[]
	 */
	protected array $obfuscatePhpExt = ['php'];

	/**
	 * @return array|string[]
	 */
	public function getObfuscatePhpExt(): array
	{
		return $this->obfuscatePhpExt;
	}

	/**
	 * @var string|array|null
	 */
	protected $ignorePreDefinedClasses = 'all';

	/**
	 * @return array|string|null
	 */
	public function getIgnorePreDefinedClasses()
	{
		return $this->ignorePreDefinedClasses;
	}

	/**
	 * allowed modes are 'PREFER_PHP7', 'PREFER_PHP5', 'ONLY_PHP7', 'ONLY_PHP5'
	 * see PHP-Parser documentation for meaning...
	 *
	 * @var string|null
	 */
	protected ?string $parserMode = self::PREFER_PHP7;

	/**
	 * min length of scrambled names (max = 16 for identifier, 32 for hexa and numeric)
	 *
	 * @var int
	 */
	protected int $scrambleLength = 16;

	/**
	 * allowed modes are identifier, hexa, numeric
	 *
	 * @var string|null
	 */
	protected ?string $scrambleMode = 'identifier';

	/**
	 * @return string|null
	 */
	public function getParserMode(): ?string
	{
		$mode = $this->parserMode;

		switch ($mode) {
			case self::PREFER_PHP5:
				$mode = ParserFactory::PREFER_PHP5;
				break;
			case self::ONLY_PHP7:
				$mode = ParserFactory::ONLY_PHP7;
				break;
			case self::ONLY_PHP5:
				$mode = ParserFactory::ONLY_PHP5;
				break;
			case self::PREFER_PHP7:
			default:
				$mode = ParserFactory::PREFER_PHP7;
				break;
		}

		return $mode;
	}

	/**
	 * @return int
	 */
	public function getScrambleLength(): int
	{
		return $this->scrambleLength;
	}

	/**
	 * @return string|null
	 */
	public function getScrambleMode(): ?string
	{
		return $this->scrambleMode;
	}

	/**
	 * @var bool
	 */
	protected bool $obfuscateLabel = true;

	/**
	 * @var bool
	 */
	protected bool $obfuscateString = true;

	/**
	 * @var bool
	 */
	protected bool $obfuscateIfStmt = true;

	/**
	 * @var bool
	 */
	protected bool $obfuscateLoopStmt = true;

	/**
	 * @var bool
	 */
	protected bool $obfuscateVariable = true;

	/**
	 * @var bool
	 */
	protected bool $obfuscateConstant = true;

	/**
	 * @var bool
	 */
	protected bool $obfuscateProperty = true;

	/**
	 * @var bool
	 */
	protected bool $obfuscateNamespace = true;

	/**
	 * @var bool
	 */
	protected bool $obfuscateTraitName = true;

	/**
	 * @var bool
	 */
	protected bool $obfuscateClassName = true;

	/**
	 * @var bool
	 */
	protected bool $obfuscateMethodName = true;

	/**
	 * @var bool
	 */
	protected bool $obfuscateFunctionName = true;

	/**
	 * @var bool
	 */
	protected bool $obfuscateClassConstant = true;

	/**
	 * @var bool
	 */
	protected bool $obfuscateInterfaceName = true;

	/**
	 * @return bool
	 */
	public function isObfuscateString(): bool
	{
		return $this->obfuscateString;
	}

	/**
	 * @return bool
	 */
	public function isObfuscateLabel(): bool
	{
		return $this->obfuscateLabel;
	}

	/**
	 * @return bool
	 */
	public function isObfuscateIfStmt(): bool
	{
		return $this->obfuscateIfStmt;
	}

	/**
	 * @return bool
	 */
	public function isObfuscateLoopStmt(): bool
	{
		return $this->obfuscateLoopStmt;
	}

	/**
	 * @return bool
	 */
	public function isObfuscateVariable(): bool
	{
		return $this->obfuscateVariable;
	}

	/**
	 * @return bool
	 */
	public function isObfuscateConstant(): bool
	{
		return $this->obfuscateConstant;
	}

	/**
	 * @return bool
	 */
	public function isObfuscateProperty(): bool
	{
		return $this->obfuscateProperty;
	}

	/**
	 * @return bool
	 */
	public function isObfuscateNamespace(): bool
	{
		return $this->obfuscateNamespace;
	}

	/**
	 * @return bool
	 */
	public function isObfuscateTraitName(): bool
	{
		return $this->obfuscateTraitName;
	}

	/**
	 * @return bool
	 */
	public function isObfuscateClassName(): bool
	{
		return $this->obfuscateClassName;
	}

	/**
	 * @return bool
	 */
	public function isObfuscateMethodName(): bool
	{
		return $this->obfuscateMethodName;
	}

	/**
	 * @return bool
	 */
	public function isObfuscateClassConstant(): bool
	{
		return $this->obfuscateClassConstant;
	}

	/**
	 * @return bool
	 */
	public function isObfuscateFunctionName(): bool
	{
		return $this->obfuscateFunctionName;
	}

	/**
	 * @return bool
	 */
	public function isObfuscateInterfaceName(): bool
	{
		return $this->obfuscateInterfaceName;
	}

	/**
	 * comment to insert inside each obfuscated file
	 *
	 * @var string|null
	 */
	protected ?string $comment = null;

	/**
	 * @return string|null
	 */
	public function getComment(): ?string
	{
		$unique = uniqid();
		return sprintf($this->comment, $unique);
	}

	/**
	 * @var string|null
	 */
	protected ?string $sourceDirectory = null;

	/**
	 * @var string|null
	 */
	protected ?string $targetDirectory = null;

	/**
	 * @return string|null
	 */
	public function getSourceDirectory(): ?string
	{
		return $this->sourceDirectory;
	}

	/**
	 * @return string|null
	 */
	public function getTargetDirectory(): ?string
	{
		return $this->targetDirectory;
	}

	/**
	 * array of directory or file pathnames to keep 'as is' ...  i.e. not obfuscate.
	 *
	 * @var array
	 */
	protected array $keep = [];

	/**
	 * array of directory or file pathnames to skip when exploring source tree structure ... they will not be on target!
	 *
	 * @var array
	 */
	protected array $skip = [];

	/**
	 * allow empty files to be kept as is
	 *
	 * @var bool
	 */
	protected bool $allowOverwriteEmptyFiles = false;

	/**
	 * @return array
	 */
	public function getKeep(): array
	{
		return $this->keep;
	}

	/**
	 * @return array
	 */
	public function getSkip(): array
	{
		return $this->skip;
	}

	/**
	 * @return bool
	 */
	public function isAllowOverwriteEmptyFiles(): bool
	{
		return $this->allowOverwriteEmptyFiles;
	}

	/**
	 * shuffle chunks of statements!  disable this obfuscation (or minimize the number of chunks) if performance is important for you!
	 *
	 * @var bool
	 */
	protected bool $shuffleStmts = true;

	/**
	 * ratio > 1  100/ratio is the percentage of chunks in a statements sequence  ratio = 2 means 50%  ratio = 100 mins 1% ...
	 * if you increase the number of chunks, you increase also the obfuscation level ... and you increase also the performance overhead!
	 *
	 * @var int
	 */
	protected int $shuffleStmtsChunkRatio = 20;

	/**
	 * minimum number of statements in a chunk! the min value is 1, that gives you the maximum of obfuscation ... and the minimum of performance...
	 *
	 * @var int
	 */
	protected int $shuffleStmtsMinChunkSize = 1;

	/**
	 * 'fixed' or 'ratio' in fixed mode, the chunk_size is always equal to the min chunk size!
	 *
	 * @var string
	 */
	protected string $shuffleStmtsChunkMode = 'fixed';

	/**
	 * @return bool
	 */
	public function isShuffleStmts(): bool
	{
		return $this->shuffleStmts;
	}

	/**
	 * @return int
	 */
	public function getShuffleStmtsChunkRatio(): int
	{
		return $this->shuffleStmtsChunkRatio;
	}

	/**
	 * @return int
	 */
	public function getShuffleStmtsMinChunkSize(): int
	{
		return $this->shuffleStmtsMinChunkSize;
	}

	/**
	 * @return string
	 */
	public function getShuffleStmtsChunkMode(): string
	{
		return $this->shuffleStmtsChunkMode;
	}

	/**
	 * display or not Information level messages.
	 *
	 * @var int
	 */
	protected int $silent = 1;

	/**
	 * rfu : will answer Y on confirmation request (reserved for future use ... or not...)
	 *
	 * @var bool
	 */
	protected bool $confirm = true;

	/**
	 * @var bool
	 */
	protected bool $abortOnError = true;

	/**
	 * remove all comments from obfuscated code
	 *
	 * @var bool
	 */
	protected bool $removeComment = true;

	/**
	 * all your obfuscated code will be generated on a single line
	 *
	 * @var bool
	 */
	protected bool $stripIndentation = true;

	/**
	 * @return int
	 */
	public function getSilent(): int
	{
		return $this->silent;
	}

	/**
	 * @return int
	 */
	public function isSilent(): int
	{
		return $this->getSilent() > 0;
	}

	/**
	 * @return bool
	 */
	public function isConfirm(): bool
	{
		return $this->confirm;
	}

	/**
	 * @return bool
	 */
	public function isAbortOnError(): bool
	{
		return $this->abortOnError;
	}

	/**
	 * @return bool
	 */
	public function isRemoveComment(): bool
	{
		return $this->removeComment;
	}

	/**
	 * @return bool
	 */
	public function isStripIndentation(): bool
	{
		return $this->stripIndentation;
	}

	public function validate()
	{
		$this->shuffleStmtsMinChunkSize += 0;
		if ($this->shuffleStmtsMinChunkSize < 1) {

			$this->shuffleStmtsMinChunkSize = 1;
		}

		$this->shuffleStmtsChunkRatio += 0;
		if ($this->shuffleStmtsChunkRatio < 2) {

			$this->shuffleStmtsChunkRatio = 2;
		}

		if ($this->shuffleStmtsChunkMode !== 'ratio') {

			$this->shuffleStmtsChunkMode = 'fixed';
		}

		if (!isset($this->ignorePreDefinedClasses)
			|| (!is_array($this->getIgnorePreDefinedClasses())
				&& $this->getIgnorePreDefinedClasses() !== 'all')) {

			$this->ignorePreDefinedClasses = 'all';
		}
	}
}