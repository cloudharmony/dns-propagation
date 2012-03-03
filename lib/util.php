<?php
/**
 * this file contains common PHP functions used during the benchmark execution
 * process
 */

// get runtime parameters from environment variables

// answer
$bm_answer = trim(strtolower(getenv('bm_param_answer')));

// set the start time
$bm_start_time = microtime(TRUE);

// multiple flag
$bm_multiple = getenv('bm_param_multiple') && getenv('bm_param_multiple') == '1';

// debug flag
$bm_debug = getenv('bm_param_debug') && getenv('bm_param_debug') == '1';

// dig options
$bm_options = getenv('bm_param_options');

// question
$bm_question = getenv('bm_param_question');

// timeout
$bm_run_timeout = getenv('bm_run_timeout');

// query type (A or CNAME)
$bm_type = preg_match('/^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$/', $bm_answer) ? 'A' : 'CNAME';

// servers
if ($bm_servers = getenv('bm_param_servers') ? explode(',', getenv('bm_param_servers')) : NULL) {
	foreach(array_keys($bm_servers) as $i) {
		$bm_servers[$i] = trim($bm_servers[$i]);
		if (!$bm_servers[$i]) unset($bm_servers[$i]);
	}
}

// spacing
$bm_spacing = getenv('bm_param_spacing');
if (!$bm_spacing || !is_numeric($bm_spacing) || $bm_spacing < 0) $bm_spacing = 5;

// zone
$bm_zone = getenv('bm_param_zone');

/**
 * rounding precision to use
 */
define('BM_DNS_PROPAGATION_ROUND_PRECISION', 4);

/**
 * executes a dig query and returns the response as an array of answers. Note:
 * trailing periods will be removed from all hostname answers. returns NULL if
 * the dig command fails
 * @param string $question the dig question value (a hostname)
 * @param string $server a specific DNS server to use
 * @param string $type the dig question type
 * @param boolean $useOptions whether or not to use user defined dig options 
 * (a runtime parameter)
 * @return array
 */
function bm_dig($server=NULL, $question=NULL, $type=NULL, $useOptions=TRUE) {
	global $bm_debug;
	global $bm_options;
	global $bm_question;
	global $bm_type;
	global $bm_zone;
	$answers = array();
	// query type
	if (!$type) $type = $bm_type;
	// question
	if (!$question) $question = $bm_question;
	if ($question == $bm_question && !strpos($question, $bm_zone)) $question .= '.' . $bm_zone;
	
	$dig = 'dig' . ($server ? " @$server" : '') . ($bm_options && $useOptions ? " $bm_options" : '') . ($type != 'NS' ? ' +norecurse' : '') . " +short $question $type";
	if ($bm_debug) bm_log_msg("Executing dig using command: $dig", basename(__FILE__), __LINE__);
	
	$output = rand();
	if ($question && trim($status = shell_exec("$dig &> $output; echo $?")) == '0') {
		foreach(explode("\n", file_get_contents($output)) as $a) {
			$a = trim($a);
			if (substr($a, -1) == '.') $a = substr($a, 0, strlen($a) - 1);
			if ($a) $answers[] = strtolower($a);
		}
	}
	// status 10 = couldn't get address
	else if ($status && $status != 10) {
		bm_log_msg("Dig command $dig resulted in status $status", basename(__FILE__), __LINE__, TRUE);
		$answers = NULL;
	}
	if (file_exists($output)) unlink($output);
	
	if ($bm_debug) bm_log_msg($answers ? "Got answers: " . implode(', ', $answers) : 'Did not receive an answer', basename(__FILE__), __LINE__);
	return $answers;
}

/**
 * returns the current execution time
 * @return float
 */
function bm_exec_time() {
	global $bm_start_time;
	return round(microtime(TRUE) - $bm_start_time, BM_DNS_PROPAGATION_ROUND_PRECISION);
}

/**
 * returns the DNS servers to query for this test
 * @return array
 */
function bm_get_servers() {
	global $bm_servers;
	global $bm_zone;
	if (!$bm_servers) $bm_servers = bm_dig(NULL, $bm_zone, 'NS', FALSE);
	return $bm_servers;
}

/**
 * this function outputs a log message
 * @param string $msg the message to output
 * @param string $source the source of the message
 * @param int $line an optional line number
 * @param boolean $error is this an error message
 * @param string $source1 secondary source
 * @param int $line1 secondary line number
 * @return void
 */
function bm_log_msg($msg, $source=NULL, $line=NULL, $error=FALSE, $source1=NULL, $line1=NULL) {
	$source = basename($source);
	if ($source1) $source1 = basename($source1);
	$exec_time = bm_exec_time();
	printf("%-24s %-12s %-12s %s\n", date('m/d/Y H:i:s T'), bm_exec_time() . 's', 
				 $source ? str_replace('.php', '', $source) . ($line ? ':' . $line : '') : '', 
				 ($error ? 'ERROR - ' : '') . $msg . 
				 ($source1 ? ' [' . str_replace('.php', '', $source1) . ($line1 ? ":$line1" : '') . ']' : ''));
}

// set default time zone if necessary
if (!ini_get('date.timezone')) ini_set('date.timezone', ($tz = trim(shell_exec('date +%Z'))) ? $tz : 'UTC');
?>