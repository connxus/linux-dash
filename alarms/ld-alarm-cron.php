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
		// message prefix
		$genInfo = shell_exec("{$shellPath}/general_info.sh");
		$genJSON = json_decode($genInfo);
		$messageText = "[{$serverName}] Server Status Summary. <https://connxus.com/linux-dash|View Real-Time Status>. This server has been running for {$genJSON->Uptime}.";
		exec("curl -X POST --data-urlencode 'payload={\"text\": \"{$messageText}\"}' {$slackWebHookUrl}");

		// apache
		$apacheCheck = shell_exec("{$shellPath}/apache_check.sh");
		$messageText = $apacheCheck ? "[{$serverName}] Apache Operational :white_check_mark:" : "[{$serverName}] Apache Unavailable! :skull_and_crossbones::exclamation:";
		exec("curl -X POST --data-urlencode 'payload={\"text\": \"{$messageText}\"}' {$slackWebHookUrl}");

		// database
		$dbCheck = shell_exec('php -f ' . dirname(__FILE__).'/db-connect-test.php');
		$messageText = $dbCheck ? "[{$serverName}] Database Connection Operational :white_check_mark:" : "[{$serverName}] Database Connection Unavailable! :skull_and_crossbones::exclamation:";
		exec("curl -X POST --data-urlencode 'payload={\"text\": \"{$messageText}\"}' {$slackWebHookUrl}");

		// disk usage
		$diskRaw = shell_exec("{$shellPath}/disk_partitions.sh");
		$diskJSON = json_decode($diskRaw);
		$messageText = "[{$serverName}] Disk Usage Status per Mount:";
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

		// cpu usage
		$loadAvg = shell_exec("{$shellPath}/load_avg.sh");
		$loadJSON = json_decode($loadAvg);
		$cpuUtil = shell_exec("{$shellPath}/cpu_utilization.sh");
		$propName1 = '1_min_avg';
		$propName5 = '5_min_avg';
		$propName15 = '15_min_avg';

		// cpu intensive processes
		$cpuRaw = shell_exec("{$shellPath}/cpu_intensive_processes.sh");
		$cpuJSON = json_decode($cpuRaw);
		$messageText = "[{$serverName}] CPU Current Load: {$cpuUtil}%\nCPU Average Load: {$loadJSON->{$propName1}}%[1 min avg] {$loadJSON->{$propName5}}%[5 min avg] {$loadJSON->{$propName15}}%[15 min avg]\nTop CPU Intensive Processes:";
		$attachments = array();
		$c = 0;
		foreach ($cpuJSON as $proc) {
			$obj = new stdClass();
			$obj->color = sprintf('#%06X', mt_rand(0, 0xFFFFFF)); //"#46569f";
			$obj->title = 'PID: ' . $proc->pid;
			$obj->fields = array();

			$obj->fields[0] = new stdClass();
			$obj->fields[0]->title = 'User';
			$obj->fields[0]->value = $proc->user;
			$obj->fields[0]->short = true;

			$obj->fields[1] = new stdClass();
			$obj->fields[1]->title = 'CPU%';
			$propName = 'cpu%';
			$obj->fields[1]->value = $proc->{$propName};
			$obj->fields[1]->short = true;

			$obj->fields[2] = new stdClass();
			$obj->fields[2]->title = 'CMD';
			$obj->fields[2]->value = $proc->cmd;
			$obj->fields[2]->short = true;

			$attachments[] = $obj;
			$c++;
			if ($c == 5) {
				break;
			}
		}
		$attachmentTxt = json_encode($attachments);
		exec("curl -X POST --data-urlencode 'payload={\"text\": \"{$messageText}\", \"attachments\": {$attachmentTxt}}' {$slackWebHookUrl}");

		// ram intensive processes + mem utlization
		$memInfo = shell_exec("{$shellPath}/memory_info.sh");
		$memJSON = json_decode($memInfo);
		$memAvail = round(preg_replace('[^0-9]','', $memJSON->MemAvailable) / 1024);
		$memFree = round(preg_replace('[^0-9]','', $memJSON->MemFree) / 1024);
		$memTotal = round(preg_replace('[^0-9]','', $memJSON->MemTotal) / 1024);
		$inUsePercent = 100 - (round($memFree / $memTotal, 2) * 100);
		$messageText = "[{$serverName}] RAM Current Utilization. Available: {$memAvail}MB  Free: {$memFree}MB Total: {$memTotal}MB InUse: {$inUsePercent}%\nTop RAM Intensive Processes:";
		$ramRaw = shell_exec("{$shellPath}/ram_intensive_processes.sh");
		$ramJSON = json_decode($ramRaw);
		$attachments = array();
		$c = 0;
		foreach ($ramJSON as $proc) {
			$obj = new stdClass();
			$obj->color = sprintf('#%06X', mt_rand(0, 0xFFFFFF)); //"#46569f";
			$obj->title = 'PID: ' . $proc->pid;
			$obj->fields = array();

			$obj->fields[0] = new stdClass();
			$obj->fields[0]->title = 'User';
			$obj->fields[0]->value = $proc->user;
			$obj->fields[0]->short = true;

			$obj->fields[1] = new stdClass();
			$obj->fields[1]->title = 'MEM%';
			$propName = 'mem%';
			$obj->fields[1]->value = $proc->{$propName};
			$obj->fields[1]->short = true;

			$obj->fields[2] = new stdClass();
			$obj->fields[2]->title = 'CMD';
			$obj->fields[2]->value = $proc->cmd;
			$obj->fields[2]->short = true;

			$attachments[] = $obj;
			$c++;
			if ($c == 5) {
				break;
			}
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





