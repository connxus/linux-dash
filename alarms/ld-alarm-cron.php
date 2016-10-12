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
	$serverName = trim(strtolower($argv[1]));

	// Report status
	if ('report' == $behavior) {
		$diskRaw = shell_exec("{$shellPath}/disk_partitions.sh");
		$diskJSON = json_decode($diskRaw);
	
		$messageText = "[{$serverName}] Disk Usage Status";
		$attachments = array();	
		var_dump($diskJSON);
		foreach ($diskJSON as $mount) {
			$obj = new stdClass();
			$obj->fallback = "Disk Usage Status Report for {$serverName}";
			$obj->pretext = "Disk Usage Status Report for {$serverName}";
			$obj->color = "#46569f";
			$obj->title = $mount->file_system . '[' . $mount->mounted . ']';
			$obj->fields = array();

			$obj->fields[0] = new stdClass();
			$obj->fields[0]->title = 'Used/Avail';
			$obj->fields[0]->value = $mount->used . ' / ' . $mount->avail;
			$obj->fields[0]->short = true;

			$obj->fields[0] = new stdClass();
			$obj->fields[0]->title = 'Used';
			$propName = 'used%';
			$obj->fields[0]->value = $mount->{$propName};
			$obj->fields[0]->short = true;


			$attachments[] = $obj;
		}
		$attachmentTxt = json_encode($attachments);
		exec("curl -X POST --data-urlencode 'payload={\"text\": \"{$messageText}\", \"attachments\": {$attachmentTxt}}' {$slackWebHookUrl}");
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





