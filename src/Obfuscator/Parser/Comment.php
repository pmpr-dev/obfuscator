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
	const NAME = 'obfuscator';

	/**
	 * @var string
	 */
	protected $name = self::NAME;

	/**
	 * @var string|null
	 */
	protected ?string $class = null;

	/**
	 * @var string|null
	 */
	protected ?string $method = null;

	/**
	 * @var string|null
	 */
	protected ?string $replace = null;

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

						$this->{$key} = $value;
					}
				}
			}
		}
	}

	/**
	 * @return string|null
	 */
	public function getClass(): ?string
	{
		return $this->class;
	}

	/**
	 * @return string|null
	 */
	public function getMethod(): ?string
	{
		return $this->method;
	}

	/**
	 * @return string|null
	 */
	public function getReplace(): ?string
	{
		return $this->replace;
	}

	/**
	 * @return bool
	 */
	public function isOrigin(): bool
	{
		return !empty($this->getReplace());
	}

	/**
	 * @return bool
	 */
	public function isDestination(): bool
	{
		return !$this->isOrigin();
	}

	/**
	 * @return string
	 */
	public function __toString(): string
	{
		return (string)$this->description;
	}
}