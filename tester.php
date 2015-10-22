<?php

define('DEFAULT_TIME_LIMIT', 3); // In seconds
define('IS_CLI', php_sapi_name() === 'cli');

function printCliHelp() {
	echo "Example usage: php " . basename(__FILE__) . " programPath testpubDir [-t timeLimit] [-s]

arguments:
	programPath:	Path to program executable.
	testpubDir:		Path to test direcotry. (directory with test files)

switches:
	-t, --time:		Use to set time limit. (in seconds) [default: " . sprintf("%.3f", DEFAULT_TIME_LIMIT) . "s]
	-s, --silent:	Use if you don't want to print output of your program
	-w, --watch:	Use if you want to keep running this script & re-run tests on update of program
	-h, --help:		Display this sexy help :)
";
}

$printResultsFirsTest = true;

function printResult(array $status) {
	global $printResultsFirsTest;
	if (!$printResultsFirsTest) {
		echo SILENT_OUTPUT ? "" : "\n";
	}
	$printResultsFirsTest = false;

	echo (IS_CLI ? "" : "&nbsp;") . "
" . (IS_CLI ? "" : "<div><div style=\"background-color:#" . ($status['solution_ok'] && $status['time_ok'] ? "00a000" : ($status['solution_ok'] ? "B8B84E" : "A90A3A")) . "/*#cfcfcf*/; text-align:center;\"><span style=\"color:white;\">") . "PUBLIC DATA INSTANCE: " . $status['task'] . ", result: " . (IS_CLI ? ($status['solution_ok'] && $status['time_ok'] ? "\033[32m" : ($status['solution_ok'] ? "\033[33m" : "\033[31m")) : "") . ($status['solution_ok'] ? "" : "IN") . "CORRECT SOLUTION" . (IS_CLI ? "\033[0m" : "</span></div></div>" ) . "
" . (IS_CLI ? "" : "<div class=\"public\">") . "Evaluating data instance '" . $status['task'] . "', time limit: " . sprintf("%.3f", $status['time_limit']) . " sec.
" . ($status['time_ok'] ? "Finished" : "Terminated") . ",  system time: " . sprintf("%.3f", $status['system_time']) . " sec, user time: " . sprintf("%.3f", $status['user_time']) . " sec.
" . (!$status['time_ok'] ? (IS_CLI ? "\033[31m" : "<b>") . "Timelimit expired!" . (IS_CLI ? "\033[0m" . (SILENT_OUTPUT ? "\n" : "") : "</b>") : "");

	if (!SILENT_OUTPUT) {
		echo "
-------------------------------------------------------------------------------
STDOUT of data instance: '" . $status['task'] . "'
-------------------------------------------------------------------------------
" . (strlen($status['stdout']) ? $status['stdout'] : (IS_CLI ? "<" : "&lt;") . "Empty output stream" . (IS_CLI ? ">" : "&gt;"));
	}

	if (!SILENT_OUTPUT || $status['stderr']) {
		echo "
-------------------------------------------------------------------------------
STDERR of data instance: '" . $status['task'] . "'
-------------------------------------------------------------------------------
" . (strlen($status['stderr']) ? $status['stderr'] : (IS_CLI ? "<" : "&lt;") . "Empty output stream" . (IS_CLI ? ">" : "&gt;")) . (IS_CLI ? "\n" : "</div>");
	}
}

function getPath($filename) {
	if (!preg_match("#^([A-Z]:(\\\|/)|(/))#", $filename)) {
		$filename = rtrim(getcwd(), '\\\/') . "/$filename";
	}
	return ($realpath = realpath($filename)) ? $realpath : $filename;
}

if (IS_CLI) {
	$_POST['timeLimit'] = DEFAULT_TIME_LIMIT;
	$_POST['exePath'] = NULL;
	$_POST['dataPath'] = NULL;
	$silent = false;
	$watch = false;

	$argp = 0;
	for ($i = 1; $i < $argc; $i++) {
		if (substr($argv[$i], 0, 1) == '-') {
			switch (substr($argv[$i], 1)) {
				case 't':
				case '-time':
					$_POST['timeLimit'] = $argv[++$i] / 1;
					break;
				case 'w':
				case '-watch':
					$watch = true;
					break;
				case 'h':
				case '-help':
					printCliHelp();
					exit($argc !== 2 ? 1 : 0);
					break;
				case 's':
				case '-silent':
					$silent = true;
					break;
				default:
					echo "Unknown switch " . $argv[$i] . ".\n";
					printCliHelp();
					exit(1);
			}
		} else {
			switch ($argp++) {
				case 0:
					$_POST['exePath'] = getPath($argv[$i]);
					break;
				case 1:
					$_POST['dataPath'] = getPath($argv[$i]);
					break;
				default:
					echo "Too many input arguments.\n";
					printCliHelp();
					exit(1);
			}
		}
	}

	define('SILENT_OUTPUT', $silent);
	define('WATCH', $watch);

	if (!$_POST['exePath'] || !$_POST['dataPath']) {
		echo "Not enought input arguments.\n";
		printCliHelp();
		exit(1);
	}

	if (!is_file($_POST['exePath'])) {
		echo "Not valid path to progam excutable.\n";
		printCliHelp();
		exit(1);
	}

	if (!is_dir($_POST['dataPath'])) {
		echo "Not valid path to test directory.\n";
		printCliHelp();
		exit(1);
	}

	@set_time_limit(0);
} else {
	$_POST['exePath'] = isset($_POST['exePath']) ? $_POST['exePath'] : (isset($_COOKIE['exePath']) ? $_COOKIE['exePath'] : "");
	$_POST['dataPath'] = rtrim(isset($_POST['dataPath']) ? $_POST['dataPath'] : (isset($_COOKIE['dataPath']) ? $_COOKIE['dataPath'] : ""), '\\/');
	$_POST['timeLimit'] = (isset($_POST['timeLimit']) ? $_POST['timeLimit'] : (isset($_COOKIE['timeLimit']) ? $_COOKIE['timeLimit'] : DEFAULT_TIME_LIMIT)) / 1;

	if (realpath($_POST['exePath'])) {
		$_POST['exePath'] = getPath($_POST['exePath']);
	}
	if (realpath($_POST['dataPath'])) {
		$_POST['dataPath'] = getPath($_POST['dataPath']);
	}

	define('SILENT_OUTPUT', false); // Not supported in GUI
	define('WATCH', false);   // Not supported in GUI

	setcookie("exePath", $_POST['exePath'], time() + 365 * 24 * 60 * 60);
	setcookie("dataPath", $_POST['dataPath'], time() + 365 * 24 * 60 * 60);
	setcookie("timeLimit", $_POST['timeLimit'], time() + 365 * 24 * 60 * 60);
}

/**
 * On Windows proc_terminate sometimes don't have to work
 * so it's better to use command taskkill (windows) / kill (unix)
 *
 * @param resource
 * @return int
 */
function proc_kill($pid) {
	if (stripos(php_uname('s'), 'win') > -1) {
		exec("tasklist /svc /fi \"PID eq $pid\"", $output, $returnVal);
		if (count($output) > 1) {
			$e = exec("taskkill /F /T /PID $pid", $output, $returnVal);
		}
	} else {
		$e = exec("kill -9 $pid", $output, $returnVal);
	}
	return $returnVal;
}

function getTests() {
	$tests = array();
	if (is_file($_POST['exePath']) && is_dir($_POST['dataPath'])) {
		$in = array();
		$out = array();

		foreach (scandir('C:\Users\Martin\Documents\Skola\PAL\DU 3_2\datapub') as $file) {
			if (is_file($path = $_POST['dataPath'] . '/' . $file)) {
				if (preg_match("#^([a-zA-Z0-9]+)\.in$#i", $file, $match)) {
					$in[$match[1]] = $path;
				} elseif (preg_match("#^([a-zA-Z0-9]+)\.out$#i", $file, $match)) {
					$out[$match[1]] = $path;
				}
			}
		}

		foreach ($in as $test => $file) {
			isset($out[$test]) && $tests[$test] = array('in' => $file, 'out' => $out[$test]);
		}
	}


	if (!$tests) {
		if (IS_CLI) {
			echo "No test cases found.";
		}
		require_once __DIR__ . '/output.phtml';
		exit(0);
	}

	return $tests;
}

function canonize($string) {
	return rtrim(preg_replace("#( )?(\n|\r)+#", "\n", preg_replace("#( )+#", " ", $string)));
}

$exec = (pathinfo($_POST['exePath'], PATHINFO_EXTENSION) == 'jar' ? 'java -jar ' : '') . '"' . $_POST['exePath'] . '"';

$descriptorspec = array(
	0 => array("pipe", "r"), // stdin is a pipe that the child will read from
	1 => array("pipe", "w"), // stdout is a pipe that the child will write to
	2 => array("pipe", "w"), // stderr is a pipe that the child will write to
);

$watchIteration = 0;
do {
	$startTime = time();

	$tests = getTests();
	if (!WATCH) {
		set_time_limit($_POST['timeLimit'] * count($tests) + 20);
	} else {
		$printResultsFirsTest = true;

		if ($watchIteration++) {
			echo "\n";
			echo "--------------------------" . "---------------------" . "---------\n";
			echo "-------- Re-evaluated at: " . date("d. m. Y H:i:s") . " --------\n";
			echo "--------------------------" . "---------------------" . "---------\n";
			echo "\n";
		}
	}

	$results = array();
	$_processes = array();
//	$i = 0;
	foreach ($tests as $test => $io) {
		$sysTime1 = microtime(true);
		$testInput = file_get_contents($io['in']) . "\n";
		$testOutput = canonize(file_get_contents($io['out']));

		$execTime = microtime(true);
		$process = proc_open($exec, $descriptorspec, $pipes);
		fwrite($pipes[0], $testInput);
		fclose($pipes[0]);
		$sysTime1 = microtime(true) - $sysTime1;

		while (true) {
			$status = proc_get_status($process);

			if ($status['running']) {
				if (microtime(true) - $execTime >= $_POST['timeLimit']) {
					proc_kill($status['pid']);
					break;
				}
			} else {
				break;
			}
			usleep(250);
		}
		$execTime = microtime(true) - $execTime;
		$sysTime2 = microtime(true);

		$stdout = canonize(stream_get_contents($pipes[1]));
		fclose($pipes[1]);

		$stderr = canonize(stream_get_contents($pipes[2]));
		fclose($pipes[2]);

		proc_close($process);

		$ok = $stdout == $testOutput;

		$sysTime2 = microtime(true) - $sysTime2;

//		if (($i + 3) % 3 == 0) {
//			$ok = false;
//		}
//		if ($i++ > 6) {
//			$execTime = $_POST['timeLimit'] + rand(0, 50) / 100 - 0.25;
//		}

		$result = array(
			'solution_ok' => $ok,
			'time_ok' => ($execTime < $_POST['timeLimit']),
			'task' => $test,
			'time_limit' => $_POST['timeLimit'],
			'system_time' => ($sysTime1 + $sysTime2),
			'user_time' => $execTime,
			'stdout' => $stdout,
			'stderr' => $stderr,
		);

		if (IS_CLI) {
			printResult($result);
		} else {
			$results[] = $result;
		}
	}
	$stopTime = time();

	if (WATCH) {
		$execFileTime = filemtime($_POST['exePath']);

		echo "\n";

		do {
			clearstatcache(true, $_POST['exePath']);

			$i = 4;
			$s = array('|', '\\', '-', '/');
			while ($i--) {
				echo "\rWatching " . $s[$i] . ". To terminate type Ctrl + C: ";
				usleep(250000);
			}

			if (!is_file($_POST['exePath'])) {
				echo "\rTerminated - Program executable not found.\n";
				break 2;
			}
		} while ($execFileTime == filemtime($_POST['exePath']));

		// Wait till all work on file is done (Windows)
		$i = 4;
		$s = array('|', '\\', '-', '/');
		while ($i--) {
			echo "\rWatching " . $s[$i] . ". To terminate type Ctrl + C: ";
			usleep(250000);
		}

		echo "\r                                        ";
	}
} while (WATCH);

if (!IS_CLI) {
	require_once __DIR__ . '/output.phtml';
}
exit(0);
