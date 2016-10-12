<?php

/*
This script should be scheduled on cron to report server status.

Usage:

ld-alarm-cron.php {behavior} {serverName}

The behavior argument accepts two values:
"monitor" will send alerts to #server-status slack channel if criteria exceeds defined thresholds.
"report" will send a status summary to #server-status slack channel.

*/
$slackWebHookUrl = "https://hooks.slack.com/services/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";
$shellPath = "/var/www/html/linux-dash/server/modules/shell_files";

if (isset($argv[1]) && isset($argv[2])) {
	$behavior = trim(strtolower($argv[1]));

	// Report status
	if ('report' == $behavior) {
		$messageText = 'Test.';
		$diskRaw = shell_exec("{$shellPath}/disk_partitions.sh");
		echo "\n{$diskRaw}\n\n";
		$diskJSON = json_decode($diskRaw);
		// exec("curl -X POST --data-urlencode 'payload={\"text\": \"{$messageText}\"}' {$slackWebHookUrl}");
		var_dump($diskJSON);
	} 
	// Check all alarms	
	elseif ('monitor' == $behavior) {
		$messageText = 'Test.';
		// exec("curl -X POST --data-urlencode 'payload={\"text\": \"{$messageText}\"}' {$slackWebHookUrl}");
	}
	else {
		echo "\nUsage: ld-alarm-cron.php (monitor|report) serverName\n\n";
	}
	
} else {
	echo "\nUsage: ld-alarm-cron.php (monitor|report) serverName\n\n";
}





