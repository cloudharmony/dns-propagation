#!/usr/bin/php -q
<?php
/**
 * this script performs a test iteration. It utilizes the following exit status 
 * codes
 *   0 Iteration successful
 *   1 Unable to get DNS servers
 *   2 No question or answer
 *   3 Benchmark timeout
 *   4 Unknown exception
 */
require_once(dirname(__FILE__) . '/lib/util.php');

$latency = array();
$status = 0;

if (!is_array($servers = bm_get_servers()) || !count($servers)) {
	bm_log_msg("Unable to get DNS servers for zone $bm_zone", basename(__FILE__), __LINE__, TRUE);
	$status = 1;
}
if (!$bm_answer || !$bm_question) {
	bm_log_msg('No question or answer specified', basename(__FILE__), __LINE__, TRUE);
	$status = 2;
}

if (!$status) {
	try {
		if ($bm_debug) bm_log_msg("Starting DNS propagation test iteration: bm_answer=$bm_answer; bm_multiple=$bm_multiple; bm_options=$bm_options; bm_question=$bm_question; bm_run_timeout=$bm_run_timeout; bm_type=$bm_type; bm_spacing=$bm_spacing; bm_zone=$bm_zone; servers=" . implode(',', $servers), basename(__FILE__), __LINE__);
		$keys = array_keys($servers);
		$iteration = 0;
		$numExpected = $bm_multiple ? count($servers) : 1;
		while(TRUE) {
			$iteration++;
			shuffle($keys);
			if ($bm_debug) bm_log_msg("Starting test iteration $iteration. " . count($latency) . " of $numExpected latency metrics have been captured", basename(__FILE__), __LINE__);
			foreach($keys as $i => $key) {
				if (isset($latency[$servers[$key]])) continue;
				
				if (is_array($answers = bm_dig($servers[$key])) && in_array($bm_answer, $answers)) {
					$latency[$servers[$key]] = bm_exec_time();
					if ($bm_debug) bm_log_msg('Got valid response from server ' . $servers[$key] . ' in ' . $latency[$servers[$key]] . ' secs', basename(__FILE__), __LINE__);
				}
				else if (is_array($answers)) {
					if ($bm_debug) bm_log_msg('Did not get a valid response from server ' . $servers[$key], basename(__FILE__), __LINE__);
				}
				else if ($answers === NULL) {
					bm_log_msg('Server ' . $servers[$key] . ' is not valid or responded with an error - will not be re-queried', basename(__FILE__), __LINE__, TRUE);
					unset($keys[$i]);
					$numExpected--;
				}
				// only need one answer
				if (count($latency) && !$bm_multiple) break;
			}
			// done if at least 1 latency metric is present [bm_multiple=0] OR number 
			// of latency metrics == number of servers [bm_multiple=1]
			if ($numExpected == count($latency)) break;
			// timeout
			if (bm_exec_time() >= $bm_run_timeout) {
				$status = 3;
				break;
			}
			// sleep before starting a new iteration
			if ($bm_debug) bm_log_msg("Sleeping for $bm_spacing seconds before starting next test iteration", basename(__FILE__), __LINE__);
			sleep($bm_spacing);
		}
	}
	catch (Exception $e) {
		bm_log_msg('Exception: ' . $e->getMessage(), $e->getFile(), $e->getLine(), TRUE, basename(__FILE__), __LINE__);
		$status = 4;
	}
}

if ($bm_debug) bm_log_msg("Test iteration finished with status code $status", basename(__FILE__), __LINE__);

if (!$status && count($latency)) {
	print("\n\n[results]\n");
	$segment = $bm_multiple ? 1 : '';
	foreach($latency as $server => $l) {
		print("latency${segment}=${l}\n");
		print("ns${segment}=${server}\n");
		if ($bm_multiple) $segment++;
		else break;
	}
}

exit($status);
?>