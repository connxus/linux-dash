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
	$serverName = trim(strtolower($argv[2]));

	// Report status
	if ('report' == $behavior) {
		// disk usage
		$diskRaw = shell_exec("{$shellPath}/disk_partitions.sh");
		$diskJSON = json_decode($diskRaw);
		$messageText = "Daily [{$serverName}] Disk Usage Status per Mount";
		$attachments = array();	
		foreach ($diskJSON as $mount) {
			$obj = new stdClass();
			$obj->color = sprintf('#%06X', mt_rand(0, 0xFFFFFF)); //"#46569f";
			$obj->title = $mount->file_system . '[' . $mount->mounted . ']';
			$obj->fields = array();

			$obj->fields[0] = new stdClass();
			$obj->fields[0]->title = 'Stats';
			$obj->fields[0]->value = $mount->used . ' / ' . $mount->avail;
			$obj->fields[0]->short = true;

			$obj->fields[1] = new stdClass();
			$obj->fields[1]->title = 'Used';
			$propName = 'used%';
			$obj->fields[1]->value = $mount->{$propName};
			$obj->fields[1]->short = true;

			$attachments[] = $obj;
		}
		$attachmentTxt = json_encode($attachments);
		exec("curl -X POST --data-urlencode 'payload={\"text\": \"{$messageText}\", \"attachments\": {$attachmentTxt}}' {$slackWebHookUrl}");

		// database connection
		$dbCheck = shell_exec('php -f ' . dirname(__FILE__).'/db-connect-test.php');
		$messageText = $dbCheck ? '[{$serverName}] Database Connection Operational :white_check_mark:' : '[{$serverName}] Database Connection Unavailable! :skull_and_crossbones::exclamation:';
		exec("curl -X POST --data-urlencode 'payload={\"text\": \"{$messageText}\"}' {$slackWebHookUrl}");
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





