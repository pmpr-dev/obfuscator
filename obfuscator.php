<?php

if (isset($_SERVER['SERVER_SOFTWARE'])
	&& $_SERVER["SERVER_SOFTWARE"] != "") {

	echo "<h1>Comand Line Interface Only!</h1>";
	die;
}

$configs  = [];
$args     = $argv;
$pathInfo = pathinfo(realpath(array_shift($args)));
$dirname  = $pathInfo['dirname'];

$processMode = '';

$hasHelp = read_arg($args, '-h') || read_arg($args, '-help');
if ($hasHelp) {

	fprintf(STDERR, "Info:\t Start Obfuscate = %s", PHP_EOL . PHP_EOL);
	$lang = '';
	if ($x = getenv('LANG') !== false) {

		$s = strtolower($x);
	}

	$x = explode('_', $x);
	$x = $x[0];
	if (file_exists("{$dirname}/locale/{$x}/README.md")) {

		$help = file_get_contents("{$dirname}/locale/{$x}/README.md");
	} else if (file_exists("{$dirname}/README.md")) {

		$help = file_get_contents("{$dirname}/README.md");
	} else {

		$help = "Help File not found!";
	}

	$pos = stripos($help, '####');
	if ($pos !== false) {

		$help = substr($help, $pos + strlen('####'));
	}
	$pos = stripos($help, '####');
	if ($pos !== false) {

		$help = substr($help, 0, $pos);
	}
	$help = trim(str_replace(['## ', '`'], ['', ''], $help));
	echo "$help" . PHP_EOL;
	exit(11);
}

$target = read_arg($args, '-o', true, true);
if (!$target) {

	$target = read_arg($args, '--output-file', true, true);
}

$cleanMode = false;
if (read_arg($args, '--clean', true, true)) {

	$cleanMode = true;
}

$forceConfSilent = read_arg($args, '--silent') ? 1 : 0;
if ($forceConfSilent
	&& read_arg($args, '--silent')) {

	$forceConfSilent = 2;
}

$debugMode = 0;
if (read_arg($args, '--debug')) {

	$debugMode = 1;
	if (read_arg($args, '--debug')) {

		$debugMode = 2;
	}
}

$configExt              = '.cnf.json';
$defaultConfigFile      = 'default' . $configExt;
$files                  = [];
$argumentConfigFilename = read_arg($args, '--config-file', true, true);
if ($argumentConfigFilename) {

	$files[] = $argumentConfigFilename;
} else {
	$configType = read_arg($args, '--config-type', true, true);
	if ($configType) {

		$files[] = "{$dirname}/{$configType}{$configExt}";
	}
}
$files[] = "{$dirname}/{$defaultConfigFile}";

$configFilename = '';
foreach ($files as $dummy => $file) {
	if (file_exists($file)
		&& is_readable($file)) {

		$configFilename = $file;
		break;
	}
}

$confirm = true;
if (read_arg($args, '-y')) {

	$confirm = false;
}

if ($configFilename == '') {

	fprintf(STDERR, "Warning:\tNo config file found... using default values!%s", PHP_EOL);
} else {

	$configFilename = realpath($configFilename);
	if (!$forceConfSilent) {

		fprintf(STDERR, "Info:\tUsing [%s] Config File...%s", $configFilename, PHP_EOL);
	}

	$configs = json_decode(file_get_contents($configFilename), true);
	if ($configs) {

		if ($forceConfSilent) {

			$configs['silent'] = true;
		}
		$configs['confirm']    = $confirm;
		$configs['debug_mode'] = $debugMode;
		$configs['clean_mode'] = $cleanMode;
		if (isset($args[0])) {

			$source = realpath($args[0]);
			if ($source !== false
				&& file_exists($source)
				&& is_dir($source)) {

				if ($target) {

					$configs['target_directory'] = $target;
					$configs['source_directory'] = $source;
				} else {

					fprintf(STDERR, "Error:\tTarget directory is not specified!%s", PHP_EOL);
					exit(15);
				}
			} else {

				fprintf(STDERR, "Error:\tSource file [%s] is not readable!%s", ($source !== false) ? $source : $args[0], PHP_EOL);
				exit(16);
			}
		}
	} else {

		fprintf(STDERR, "Warning:\tThere is a problem about config file, just using default values!%s", PHP_EOL);
	}
}

/**
 * @param        $args
 * @param string $key
 * @param bool   $remove
 * @param false  $hasValue
 *
 * @return bool|mixed
 */
function read_arg($args, string $key, $remove = true, $hasValue = false)
{
	$return   = false;
	$position = array_search($key, $args);
	if ($position !== false
		&& is_numeric($position)) {

		$length = 1;
		if ($hasValue) {
			if (isset($args[$position + 1])) {

				$return = $args[$position + 1];
				$length = 2;
			} else {

				$length = 0;
			}
		} else {

			$return = true;
		}
		if ($remove && $length >= 1) {

			// remove the arg and reorder
			array_splice($args, $position, $length);
		}
	}

	return $return;
}

$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {

	require_once __DIR__ . '/vendor/autoload.php';
	\Obfuscator\Obfuscator::getInstance($configs);
} else {

	fprintf(STDERR, "Please run composer install first%s", PHP_EOL);
}
