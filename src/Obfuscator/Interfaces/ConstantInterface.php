<?php

namespace Obfuscator\Interfaces;

/**
 * Interface ConstantInterface
 * @package Obfuscator\Interfaces
 */
interface ConstantInterface
{
	const SIGNATURE = '/.obfuscator-directory';
	const PREFIX    = '-obf';

	// scrambler modes
	const HASH       = 'hash';
	const HEXA       = 'hexa';
	const NUMERIC    = 'numeric';
	const IDENTIFIER = 'identifier';

	// scrambler types
	const  LABEL_TYPE             = 'label';
	const  METHOD_TYPE            = 'method';
	const  PROPERTY_TYPE          = 'property';
	const  CONSTANT_TYPE          = 'constant';
	const  VARIABLE_TYPE          = 'variable';
	const  CLASS_CONSTANT_TYPE    = 'class_constant';
	const  FUNCTION_OR_CLASS_TYPE = 'function_or_class';

	const ONLY_PHP7   = 'ONLY_PHP7';
	const ONLY_PHP5   = 'ONLY_PHP5';
	const PREFER_PHP7 = 'PREFER_PHP7';
	const PREFER_PHP5 = 'PREFER_PHP5';

	const NUMBERS                   = '0123456789';
	const VALID_FIRST_CHARS         = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
	const VALID_NOT_FIRST_CHARS     = self::NUMBERS . self::VALID_FIRST_CHARS;
	const SCRAMBLER_CONTEXT_VERSION = '1.0';

	const RESERVED_VARIABLES = [
		'this', 'php_errormsg', 'http_response_header', 'argc', 'argv',
		'HTTP_RAW_POST_DATA', 'GLOBALS', '_SERVER', '_GET', '_POST', '_FILES', '_COOKIE', '_SESSION', '_ENV', '_REQUEST',
	];

	const RESERVED_FUNCTIONS = [
		'apache_request_headers',
		'class', 'clone', 'const', 'continue', 'declare', 'default', 'die', 'do', 'echo', 'else',
		'elseif', 'empty', 'enddeclare', 'endfor', 'endforeach', 'endif', 'endswitch', 'endwhile',
		'namespace', 'new', 'null', 'or', 'print', 'private', 'protected', 'public', 'require', 'require_once',
		'implements', 'include', 'include_once', 'instanceof', 'insteadof', 'int', 'interface', 'isset', 'list',
		'__halt_compiler', '__autoload', 'abstract', 'and', 'array', 'as', 'bool', 'break', 'callable', 'case', 'catch',
		'return', 'static', 'string', 'switch', 'throw', 'trait', 'true', 'try', 'unset', 'use', 'var', 'while', 'xor', 'yield',
		'eval', 'exit', 'extends', 'false', 'final', 'finally', 'float', 'for', 'foreach', 'function', 'global', 'goto', 'if', 'fn',
	];

	const RESERVED_NAMES = [
		'parent', 'self', 'static',
		'int', 'float', 'bool', 'string', 'true', 'false', 'null',
		'void', 'iterable', 'object', 'resource', 'scalar', 'mixed', 'numeric', 'fn',
	];

	const RESERVED_METHODS = [
		'__construct', '__destruct', '__call', '__callstatic', '__get', '__set', '__isset',
		'__unset', '__sleep', '__wakeup', '__tostring', '__invoke', '__set_state', '__clone', '__debuginfo',
	];
}