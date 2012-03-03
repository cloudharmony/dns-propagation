#!/usr/bin/php -q
<?php
/**
 * This script parses runtime results and outputs the resulting key/value pairs
 * these are all of the values that follow the line [results] in the output 
 * file
 */
$status = 1;

if (is_file($argv[1]) && is_readable($argv[1])) {
	$status = 0;
	$in_results = FALSE;
	// go through each line in the output file and look for a line that starts
	// with [results]. after that line, search for and print key/value pairs
	foreach(file($argv[1]) as $line) {
		if (!$in_results && preg_match('/^\[results\]/', $line)) $in_results = TRUE;
		else if ($in_results && preg_match('/^([^=]+)=(.*)$/', $line)) print($line);
	}
}

exit($status);
?>