<?php

namespace Obfuscator;

use Exception;
use ReflectionClass;

/**
 * Class Scrambler
 * @package Obfuscator
 */
class Scrambler extends Container
{
	/**
	 * @var string|null
	 */
	protected ?string $seed = null;

	/**
	 * @var Config|null
	 */
	protected ?Config $config = null;

	/**
	 * current length of scrambled names
	 *
	 * @var int
	 */
	protected int $length = 0;

	/**
	 * min length of scrambled names
	 *
	 * @var int
	 */
	protected int $minLength = 0;

	/**
	 * max length of scrambled names
	 *
	 * @var int
	 */
	protected int $maxLength = 0;

	/**
	 * array where keys are names to ignore.
	 *
	 * @var array
	 */
	protected array $ignores = [];

	/**
	 * array of scrambled items (key = source name , value = scrambled name) private
	 *
	 * @var array
	 */
	protected array $scrambles = [];

	/**
	 * @var string|null
	 */
	protected ?string $type = null;

	/**
	 * internal label counter.
	 *
	 * @var int
	 */
	protected int $labelCounter = 0;

	/**
	 * @var bool
	 */
	protected bool $caseSensitive = false;

	/**
	 * @var string|null
	 */
	protected ?string $firstChars = self::VALID_FIRST_CHARS;

	/**
	 * @var string|null
	 */
	protected ?string $notFirstChars = self::VALID_NOT_FIRST_CHARS;

	/**
	 * length of $firstChars string
	 *
	 * @var int
	 */
	protected int $firstCharsLength = 0;

	/**
	 * length of $notFirstChars string
	 *
	 * @var int
	 */
	protected int $notFirstCharsLength = 0;

	/**
	 * @var string|null
	 */
	protected ?string $contextDirectory = null;

	/**
	 * array where keys are prefix of names to ignore.
	 *
	 * @var array
	 */
	protected array $ignorePrefixes = [];

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
	public function isSilent(): bool
	{
		return $this->getConfig()->isSilent();
	}

	/**
	 * @return string|null
	 */
	public function getMode(): ?string
	{
		$mode = $this->getConfig()->getScrambleMode();
		return !in_array($mode, [self::IDENTIFIER, self::HEXA, self::HASH, self::NUMERIC]) ? self::IDENTIFIER : $mode;
	}

	/**
	 * @return int
	 */
	public function getLength(): int
	{
		return $this->length;
	}

	/**
	 * @return string|null
	 */
	public function getType(): ?string
	{
		return $this->type;
	}

	/**
	 * @return int
	 */
	public function getMinLength(): int
	{
		return $this->minLength;
	}

	/**
	 * @return int
	 */
	public function getMaxLength(): int
	{
		return $this->maxLength;
	}

	/**
	 * @return int
	 */
	public function getLabelCounter(): int
	{
		return $this->labelCounter;
	}

	/**
	 * @return bool
	 */
	public function isCaseSensitive(): bool
	{
		return $this->caseSensitive;
	}

	/**
	 * @return string|null
	 */
	public function getFirstChars(): ?string
	{
		return $this->firstChars;
	}

	/**
	 * @return int
	 */
	public function getFirstCharsLength(): int
	{
		return $this->firstCharsLength;
	}

	/**
	 * @return string|null
	 */
	public function getNotFirstChars(): ?string
	{
		return $this->notFirstChars;
	}

	/**
	 * @return int
	 */
	public function getNotFirstCharsLength(): int
	{
		return $this->notFirstCharsLength;
	}

	/**
	 * @return string|null
	 */
	public function getContextDirectory(): ?string
	{
		return $this->contextDirectory;
	}

	/**
	 * @return array
	 */
	public function getIgnores(): array
	{
		return $this->ignores;
	}

	/**
	 * @return array
	 */
	public function getIgnorePrefixes(): array
	{
		return $this->ignorePrefixes;
	}

	/**
	 * @return array
	 */
	public function getScrambles(): array
	{
		return $this->scrambles;
	}

	/**
	 * @return array
	 */
	public function getRScrambles(): array
	{
		return array_flip($this->getScrambles());
	}

	/**
	 * Scrambler constructor.
	 *
	 * @param string $type
	 * @param Config $config
	 */
	public function __construct(string $type, Config $config)
	{
		$preDefinedClasses                = $this->getPreDefined('class');
		$preDefinedClassMethods           = $this->getPreDefined(self::METHOD_TYPE);
		$preDefinedClassConstants         = $this->getPreDefined(self::CONSTANT_TYPE);
		$preDefinedClassProperties        = $this->getPreDefined(self::PROPERTY_TYPE);
		$preDefinedClassMethodsByClass    = $this->getPreDefined(self::METHOD_TYPE, 1);
		$preDefinedClassConstantsByClass  = $this->getPreDefined(self::CONSTANT_TYPE, 1);
		$preDefinedClassPropertiesByClass = $this->getPreDefined(self::PROPERTY_TYPE, 1);

		$this->type      = $type;
		$this->seed      = md5(microtime(true));
		$this->config    = $config;
		$this->scrambles = [];

		if ($mode = $config->getScrambleMode()) {
			switch ($mode) {
				case self::NUMERIC:

					$this->maxLength     = 32;
					$this->firstChars    = 'O';
					$this->notFirstChars = self::NUMBERS;
					break;
				case self::HEXA:

					$this->maxLength  = 32;
					$this->firstChars = 'abcdefABCDEF';
					break;
				case self::IDENTIFIER:
				case self::HASH:
				default:
					$this->maxLength = 16;
			}
		}

		$this->firstCharsLength    = strlen($this->getFirstChars()) - 1;
		$this->notFirstCharsLength = strlen($this->getNotFirstChars()) - 1;

		$this->length    = 5;
		$this->minLength = 2;

		if ($length = $config->getScrambleLength()) {

			if ($length >= $this->minLength
				&& $length <= $this->maxLength) {

				$this->length = $length;
			}
		}

		$ignores                 = [];
		$ignorePrefixes          = [];
		$ignorePreDefinedClasses = $config->getIgnorePreDefinedClasses();
		switch ($type) {
			case self::CONSTANT_TYPE:
				$ignores = array_merge(array_flip(self::RESERVED_FUNCTIONS), get_defined_constants(false));
				if ($config->getIgnoreConstants()) {

					$ignores = array_merge($ignores, array_flip($config->getIgnoreConstants()));
				}
				if ($config->getIgnoreConstantsPrefix()) {

					$ignorePrefixes = array_flip($config->getIgnoreConstantsPrefix());
				}
				$this->caseSensitive = true;
				break;
			case self::CLASS_CONSTANT_TYPE:

				$ignores = array_merge(array_flip(self::RESERVED_FUNCTIONS), get_defined_constants(false));

				$ignores = $this->preparePreDefinedClasses($ignores, $preDefinedClassConstants, $preDefinedClassConstantsByClass);

				if ($ignoreClassConstants = $config->getIgnoreClassConstants()) {

					$ignores = array_merge($ignores, array_flip($ignoreClassConstants));
				}
				if ($ignoreClassConstantsPrefix = $config->getIgnoreClassConstantsPrefix()) {

					$ignorePrefixes = array_flip($ignoreClassConstantsPrefix);
				}

				$this->caseSensitive = true;
				break;
			case self::VARIABLE_TYPE:
				$ignores = array_flip(self::RESERVED_VARIABLES);
				if ($ignoreVariables = $config->getIgnoreVariables()) {

					$ignores = array_merge($ignores, array_flip($ignoreVariables));
				}

				if ($ignoreVariablesPrefix = $config->getIgnoreVariablesPrefix()) {

					$ignorePrefixes = array_flip($ignoreVariablesPrefix);
				}
				$this->caseSensitive = true;
				break;
			case self::PROPERTY_TYPE:

				$ignores = array_flip(self::RESERVED_VARIABLES);

				$ignores = $this->preparePreDefinedClasses($ignores, $preDefinedClassProperties, $preDefinedClassPropertiesByClass);

				if ($ignoreProperties = $config->getIgnoreProperties()) {

					$ignores = array_merge($ignores, array_flip($ignoreProperties));
				}

				if ($ignorePropertiesPrefix = $config->getIgnorePropertiesPrefix()) {

					$ignorePrefixes = array_flip($ignorePropertiesPrefix);
				}

				$this->caseSensitive = true;
				break;
			case self::FUNCTION_OR_CLASS_TYPE:
				$this->caseSensitive = false;

				$ignores = array_merge(array_flip(self::RESERVED_FUNCTIONS), array_flip(array_map('strtolower', get_defined_functions()['internal'])));

				$ignores = $this->prepareIgnores($ignores, $config->getIgnoreFunctions());

				$ignorePrefixes = $this->prepareIgnores($ignorePrefixes, $config->getIgnoreFunctionsPrefix());;

				$ignores = array_merge($ignores, array_flip(self::RESERVED_NAMES), array_flip(self::RESERVED_VARIABLES));
				$ignores = array_merge($ignores, array_flip(get_defined_functions()['internal']));

				if ($ignorePreDefinedClasses != 'none') {

					if ($ignorePreDefinedClasses == 'all') {

						$ignores = array_merge($ignores, $preDefinedClasses);
					} else if (is_array($ignorePreDefinedClasses)) {

						$classNames = array_map('strtolower', $ignorePreDefinedClasses);
						foreach ($classNames as $className) {

							if (isset($preDefinedClasses[$className])) {

								$ignores[$className] = 1;
							}
						}
					}
				}

				$ignores = $this->prepareIgnores($ignores, $config->getIgnoreTraits());;

				$ignores = $this->prepareIgnores($ignores, $config->getIgnoreClasses());;

				$ignores = $this->prepareIgnores($ignores, $config->getIgnoreInterfaces());;

				$ignores = $this->prepareIgnores($ignores, $config->getIgnoreNamespaces());;

				$ignorePrefixes = $this->prepareIgnores($ignorePrefixes, $config->getIgnoreTraitsPrefix());

				$ignorePrefixes = $this->prepareIgnores($ignorePrefixes, $config->getIgnoreClassesPrefix());

				$ignorePrefixes = $this->prepareIgnores($ignorePrefixes, $config->getIgnoreInterfacesPrefix());

				$ignorePrefixes = $this->prepareIgnores($ignorePrefixes, $config->getIgnoreNamespacesPrefix());

				break;
			case self::METHOD_TYPE:

				if ($config->getParserMode() == self::ONLY_PHP7) {
					// in php7 method names can be keywords
					$ignores = [];
				} else {

					$ignores = array_flip(self::RESERVED_FUNCTIONS);
				}

				$ignores = array_merge(array_merge($ignores, self::RESERVED_METHODS), array_flip(array_map('strtolower', get_defined_functions()['internal'])));

				$ignores = $this->preparePreDefinedClasses($ignores, $preDefinedClassMethods, $preDefinedClassMethodsByClass);

				$ignores = $this->prepareIgnores($ignores, $config->getIgnoreMethods());;

				$ignorePrefixes = $this->prepareIgnores($ignores, $config->getIgnoreMethodsPrefix());;

				$this->caseSensitive = false;
				break;
			case self::LABEL_TYPE:
				$ignores = array_flip(self::RESERVED_FUNCTIONS);
				if ($ignoreLabels = $config->getIgnoreLabels()) {

					$ignores = array_merge($ignores, array_flip($ignoreLabels));
				}
				if ($ignoreLabelsPrefix = $config->getIgnoreLabelsPrefix()) {

					$ignorePrefixes = array_flip($ignoreLabelsPrefix);
				}
				$this->caseSensitive = true;
				break;
		}

		$this->ignores        = $ignores;
		$this->ignorePrefixes = $ignorePrefixes;

		$directory = $config->getTargetDirectory();
		if ($directory) {

			$this->contextDirectory = "{$directory}/obfuscator/context";
			if (file_exists("{$this->getContextDirectory()}/{$this->getType()}")) {

				$content = unserialize(file_get_contents("{$this->getContextDirectory()}/{$this->getType()}"));
				if ($content[0] !== self::SCRAMBLER_CONTEXT_VERSION) {

					fprintf(STDERR, "Error:\tContext format has changed! run with --clean option!" . PHP_EOL);
					$this->contextDirectory = null;
					exit(1);
				}

				$this->scrambles    = $content[1];
				$this->length       = $content[2];
				$this->labelCounter = $content[3];
			}
		}
	}

	function __destruct()
	{
		if (!$this->isSilent()) {

			fprintf(STDERR, "Info:\t[%-17s] scrambled \t: %8d%s", $this->getType(), count($this->scrambles), PHP_EOL);
		}
		if ($directory = $this->getContextDirectory()) {

			file_put_contents("{$directory}/{$this->getType()}", serialize([
				self::SCRAMBLER_CONTEXT_VERSION,
				$this->getScrambles(),
				$this->getLength(),
				$this->getLabelCounter(),
			]));
		}
	}

	/**
	 * @param string      $string
	 * @param string|null $mode
	 *
	 * @return mixed|string
	 */
	private function stringScramble(string $string, string $mode = null)
	{
		// first char of the identifier
		$firstChar = $this->getFirstChars()[mt_rand(0, $this->getFirstCharsLength())];
		// prepending salt for md5
		$notFirstChar = $this->getNotFirstChars()[mt_rand(0, $this->getNotFirstCharsLength())];
		// 32 chars random hex number derived from $s and lot of pepper and salt
		$this->seed = str_shuffle(md5($notFirstChar . $string . md5($this->seed)));

		if (!$mode) {

			$mode = $this->getMode();
		}
		switch ($mode) {
			case self::NUMERIC:
				for ($i = 0, $l = $this->getLength() - 1; $i < $l; ++$i) {

					$firstChar .= $this->firstChars[base_convert(substr($this->seed, $i, 2), 16, 10) % ($this->getNotFirstCharsLength() + 1)];
				}
				break;
			case self::HEXA:
				for ($i = 0, $l = $this->getLength() - 1; $i < $l; ++$i) {

					$firstChar .= substr($this->seed, $i, 1);
				}
				break;
			case self::HASH:
				$length = $this->getMaxLength();
				$string = hash('md5', $string);
				// Convert to a string which may contain only characters [0-9a-p]
				$hash = base_convert(md5($string), 16, 26);
				// Get part of the string
				$hash = substr($hash, -$length);
				// In rare cases it will be too short, add zeroes
				$hash = str_pad($hash, $length, '0', STR_PAD_LEFT);
				// Convert character set from [0-9a-p] to [a-z]
				$firstChar = strtr($hash, self::NUMBERS, 'qrstuvwxyz');

				break;
			case self::IDENTIFIER:
			default:
				for ($i = 0, $l = $this->getLength() - 1; $i < $l; ++$i) {

					$firstChar .= $this->notFirstChars[base_convert(substr($this->seed, 2 * $i, 2), 16, 10) % ($this->getNotFirstCharsLength() + 1)];
				}
		}
		return $firstChar;

	}

	/***
	 * @param string $string
	 *
	 * @return string
	 */
	private function caseShuffle(string $string): string
	{
		for ($i = 0; $i < strlen($string); ++$i) {

			$string[$i] = mt_rand(0, 1) ? strtoupper($string[$i]) : strtolower($string[$i]);
		}
		return $string;
	}

	/**
	 * @param string $prefix
	 *
	 * @return string|null
	 */
	public function generateLabelName($prefix = '!label'): ?string
	{
		return $prefix . ($this->labelCounter++);
	}

	/**
	 * @param string      $string
	 * @param string|null $mode
	 *
	 * @return mixed|string
	 */
	public function scramble(string $string, string $mode = null)
	{
		$isCaseSensitive = $this->isCaseSensitive();

		$value          = $isCaseSensitive ? $string : strtolower($string);
		$ignores        = $this->getIgnores();
		$ignorePrefixes = $this->getIgnorePrefixes();

		if (isset($ignores[$value])) {

			return $string;
		}
		if ($ignorePrefixes) {

			foreach ($ignorePrefixes as $key => $dummy) {
				if (substr($value, 0, strlen($key)) === $key) {

					return $string;
				}
			}
		}

		if (!isset($this->t_scramble[$value])) {
			$limit = 50;
			for ($i = 0; $i < $limit; ++$i) {

				$x = $this->stringScramble($string, $mode);
				$z = strtolower($x);
				$y = $isCaseSensitive ? $x : $z;
				// this random value is either already used or a reserved name
				if (isset($this->getRScrambles()[$y])
					|| isset($ignores[$z])) {

					// if not found after 5 attempts, increase the length...
					if ($i == 5 && $this->length < $this->maxLength) {

						++$this->length;
					}
					// the next attempt will always be successful, unless we already are maxlength
					continue;
				}
				$this->scrambles[$value] = $y;
				break;
			}
			if (!isset($this->scrambles[$value])) {

				fprintf(STDERR, "Scramble Error: Identifier not found after %d iterations!%sAborting...%s", $limit, PHP_EOL, PHP_EOL); // should statistically never occur!
				exit(2);
			}
		}
		return $isCaseSensitive ? $this->scrambles[$value] : $this->caseShuffle($this->scrambles[$value]);
	}

	/**
	 * @param $string
	 *
	 * @return mixed|string
	 */
	public function unscramble($string)
	{
		if (!$this->isCaseSensitive()) {

			$string = strtolower($string);
		}
		return isset($this->getRScrambles()[$string]) ? $this->getRScrambles()[$string] : '';
	}

	/**
	 * @param array $current
	 * @param array $new
	 *
	 * @return array|int[]|string[]
	 */
	private function prepareIgnores(array $current, array $new): array
	{
		if ($new) {

			$current = array_merge($current, array_flip(array_map('strtolower', $new)));
		}
		return $current;
	}

	/**
	 * @param array $current
	 * @param array $new
	 * @param array $other
	 *
	 * @return array
	 */
	private function preparePreDefinedClasses(array $current, array $new, array $other): array
	{
		$ignorePreDefinedClasses = $this->getConfig()->getIgnorePreDefinedClasses();
		if ($ignorePreDefinedClasses != 'none') {

			if ($ignorePreDefinedClasses == 'all') {

				$current = array_merge($current, $new);
			} else if (is_array($ignorePreDefinedClasses)) {


				$classNames = array_map('strtolower', $ignorePreDefinedClasses);
				foreach ($classNames as $className) {

					if (isset($other[$className])) {

						$current = array_merge($current, $other[$className]);
					}
				}
			}
		}

		return $current;
	}

	/**
	 * @param string|null $target
	 * @param int         $index
	 *
	 * @return array
	 */
	public function getPreDefined(string $target = null, $index = 0): array
	{
		$preDefinedTraits     = function_exists('get_declared_traits') ? array_flip(array_map('strtolower', get_declared_traits())) : [];
		$preDefinedClasses    = array_flip(array_map('strtolower', get_declared_classes()));
		$preDefinedInterfaces = array_flip(array_map('strtolower', get_declared_interfaces()));

		$preDefinedClasses = array_merge($preDefinedClasses, $preDefinedInterfaces, $preDefinedTraits);

		$preDefinedClassMethods           = [];
		$preDefinedClassConstants         = [];
		$preDefinedClassProperties        = [];
		$preDefinedClassMethodsByClass    = [];
		$preDefinedClassConstantsByClass  = [];
		$preDefinedClassPropertiesByClass = [];

		foreach ($preDefinedClasses as $preDefinedClassName => $dummy) {

			$temp = array_flip(array_map('strtolower', get_class_methods($preDefinedClassName)));
			if (count($temp)) {

				$preDefinedClassMethodsByClass[$preDefinedClassName] = $temp;
			}
			$preDefinedClassMethods = array_merge($preDefinedClassMethods, $temp);

			$temp = get_class_vars($preDefinedClassName);
			if (count($temp)) {

				$preDefinedClassPropertiesByClass[$preDefinedClassName] = $temp;
			}
			$preDefinedClassProperties = array_merge($preDefinedClassProperties, $temp);


			try {

				$reflectionClass = new ReflectionClass($preDefinedClassName);
				$temp            = $reflectionClass->getConstants();
				if (count($temp)) {

					$preDefinedClassConstantsByClass[$preDefinedClassName] = $temp;
				}
				$preDefinedClassConstants = array_merge($preDefinedClassConstants, $temp);
			} catch (Exception $exception) {

			}
		}

		$return = [
			'class'             => $preDefinedClasses,
			self::METHOD_TYPE   => [
				$preDefinedClassMethods,
				$preDefinedClassMethodsByClass,
			],
			self::CONSTANT_TYPE => [
				$preDefinedClassConstants,
				$preDefinedClassConstantsByClass,
			],
			self::PROPERTY_TYPE => [
				$preDefinedClassProperties,
				$preDefinedClassPropertiesByClass,
			],
		];

		if ($target) {

			if (isset($return[$target][$index])) {

				$return = $return[$target][$index];
			} else if (isset($return[$target])) {

				$return = $return[$target];
			} else {

				$return = [];
			}
		}
		return $return;
	}
}