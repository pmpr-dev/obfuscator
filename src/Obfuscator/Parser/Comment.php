<?php

namespace Obfuscator\Parser;

use phpDocumentor\Reflection\DocBlock\Description;
use phpDocumentor\Reflection\DocBlock\DescriptionFactory;
use phpDocumentor\Reflection\DocBlock\Tags\BaseTag;
use phpDocumentor\Reflection\DocBlock\Tags\Factory\StaticMethod;
use phpDocumentor\Reflection\Types\Context;
use Webmozart\Assert\Assert;

/**
 * Class Comment
 * @package Obfuscator\Parser
 */
class Comment extends BaseTag implements StaticMethod
{
	const NAME = 'fescate';

	/**
	 * @var string
	 */
	protected $name = self::NAME;

	/**
	 * @var array
	 */
	protected array $target = [];

	/**
	 * @var bool
	 */
	protected bool $encode = false;

	/**
	 * @var bool
	 */
	protected bool $remove = false;

	/**
	 * @var bool
	 */
	protected bool $exclude = false;

	/**
	 * @param string                  $body
	 * @param DescriptionFactory|null $descriptionFactory
	 * @param Context|null            $context
	 *
	 * @return Comment
	 */
	public static function create(string $body, DescriptionFactory $descriptionFactory = null, Context $context = null): Comment
	{
		Assert::notNull($descriptionFactory);

		return new static($descriptionFactory->create($body, $context));
	}

	/**
	 * Comment constructor.
	 *
	 * @param Description $description
	 */
	public function __construct(Description $description)
	{
		$this->description = $description;
		$this->parseParameters();
	}

	public function parseParameters()
	{
		$body = $this->getDescription()->getBodyTemplate();
		if ($body && preg_match('/\((.*)\)/', $body, $matches)) {

			if (isset($matches[1])
				&& $matches[1]) {

				preg_match_all('/([^=,()]+)(?:="?([^,)"]+)"?)?/', $matches[1], $chunks);
				$params = [];
				if (isset($chunks[1], $chunks[2])) {
					foreach ($chunks[1] as $index => $value) {

						if (isset($chunks[2][$index])) {

							$params[trim($value)] = trim($chunks[2][$index]);
						}
					}
				}
				foreach ($params as $key => $value) {

					if (property_exists($this, $key)) {

						if (in_array($key, ['exclude'])) {

							$value = true;
						} else if ($key === 'target') {

							$value = array_map('trim', explode(',', $value));
						}
						$this->{$key} = $value;
					}
				}
			}
		}
	}

	/**
	 * @return array
	 */
	public function getTarget(): array
	{
		return $this->target;
	}

	/**
	 * @return bool
	 */
	public function isEncode(): bool
	{
		return $this->encode;
	}

	/**
	 * @return bool
	 */
	public function isRemove(): bool
	{
		return $this->remove;
	}

	/**
	 * @return bool
	 */
	public function isExclude(): bool
	{
		return $this->exclude;
	}

	/**
	 * @return string
	 */
	public function __toString(): string
	{
		return (string)$this->description;
	}
}