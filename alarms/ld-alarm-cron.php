<?php

/*
This script should be scheduled on cron to report server status.

Usage:

ld-alarm-cron.php {behavior} {serverName}

The behavior argument accepts two values:
"monitor" will send alerts to #server-status slack channel if criteria exceeds defined thresholds.
"report" will send a status summary to #server-status slack channel.

*/


require(dirname(__FILE__).'/alarm-config.php');


function runShellScript($name, $scripts, $returnJson = true) 
{
	if (isset($scripts[$name])) {
		$output = shell_exec($scripts[$name]);
		if ($returnJson) {
			return json_decode($output);
		} else {
			return $output;
		}
	}
}

function postSlackMessage($messageText, $config, $attachments = array()) 
{
	$channelOverride = '';
	if (null != $config['SLACK_CHANNEL_OVERRIDE']) {
		$channelOverride = "\"channel\": \"{$config['SLACK_CHANNEL_OVERRIDE']}\",";
	}

	$attachmentPayload = '';
	if (count($attachments)) {
		$attachmentTxt = json_encode($attachments);
		$attachmentPayload = ", \"attachments\": {$attachmentTxt}";
	}

	exec("curl -X POST --data-urlencode 'payload={{$channelOverride}\"text\": \"{$messageText}\"{$attachmentPayload}}' {$config['SLACK_WEBHOOK_URL']}");
}


if (isset($argv[1]) && isset($argv[2])) {
	// set cli args to local variables
	$behavior = trim(strtolower($argv[1]));
	$serverName = trim(strtolower($argv[2]));

	// behavior value determines behavior, monitor will check all alarms and report alarm conditions, report will dump full status report
	// to the #_server-status channel.

	// Report status
	if ('report' == $behavior) {
		// message prefix
		$generalInfoJson = runShellScript('GENERAL_INFO', $scripts);
		$messageText = "[{$serverName}] Server Status Summary. <https://connxus.com/linux-dash|View Real-Time Status>. This server has been running for " . trim($generalInfoJson->Uptime);
		postSlackMessage($messageText, $config);

		// apache
		$apacheCheck = runShellScript('APACHE', $scripts, false);
		$messageText = $apacheCheck ? "[{$serverName}] Apache Operational :white_check_mark:" : "[{$serverName}] Apache Unavailable! :skull_and_crossbones::exclamation:";
		postSlackMessage($messageText, $config);

		// database
		$dbCheck = shell_exec('php -f ' . dirname(__FILE__).'/db-connect-test.php');
		$messageText = $dbCheck ? "[{$serverName}] Database Connection Operational :white_check_mark:" : "[{$serverName}] Database Connection Unavailable! :skull_and_crossbones::exclamation:";
		postSlackMessage($messageText, $config);

		// disk usage
		$diskJSON = runShellScript('DISK_USAGE', $scripts);
		$messageText = "[{$serverName}] Disk Usage Status per Mount:";
		$attachments = array();	
		$propName = 'used%';
		foreach ($diskJSON as $mount) {
			$usedPercent = (int) substr($mount->{$propName}, 0, (strlen($mount->{$propName}) - 1));
			$obj = new stdClass();
			$obj->color = $usedPercent >= $config['ALARM_DISK_USAGE'] ? $config['HEX_COLOR_RED'] : $config['HEX_COLOR_GREEN'];
			$obj->title = $mount->file_system . '[' . $mount->mounted . ']';
			$obj->fields = array();

			$obj->fields[0] = new stdClass();
			$obj->fields[0]->title = 'Stats';
			$obj->fields[0]->value = $mount->used . ' / ' . $mount->avail;
			$obj->fields[0]->short = true;

			$obj->fields[1] = new stdClass();
			$obj->fields[1]->title = 'Used';
			$obj->fields[1]->value = $mount->{$propName};
			$obj->fields[1]->short = true;

			$attachments[] = $obj;
		}
		postSlackMessage($messageText, $config, $attachments);
		

		// cpu usage
		$loadJSON = runShellScript('CPU_LOAD_AVG', $scripts);
		$cpuUtil = runShellScript('CPU_LOAD', $scripts, false);
		$propName1 = '1_min_avg';
		$propName5 = '5_min_avg';
		$propName15 = '15_min_avg';

		// cpu intensive processes
		$cpuJSON = runShellScript('CPU_PROCESSES', $scripts);
		$messageText = "[{$serverName}] CPU Current Load: {$cpuUtil}%\nCPU Average Load:\n{$loadJSON->{$propName1}}%[1 min avg]\n{$loadJSON->{$propName5}}%[5 min avg]\n{$loadJSON->{$propName15}}%[15 min avg]\n\nTop CPU Intensive Processes:";
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
		postSlackMessage($messageText, $config, $attachments);

		// ram intensive processes + mem utlization
		$memJSON = runShellScript('MEM_INFO', $scripts);
		$memAvail = round(preg_replace('[^0-9]','', $memJSON->MemAvailable) / 1024);
		$memFree = round(preg_replace('[^0-9]','', $memJSON->MemFree) / 1024);
		$memTotal = round(preg_replace('[^0-9]','', $memJSON->MemTotal) / 1024);
		$inUsePercent = 100 - (round($memFree / $memTotal, 2) * 100);
		$messageText = "[{$serverName}] RAM Current Utilization.\nInUse: {$inUsePercent}%\nAvailable: {$memAvail}MB\nFree: {$memFree}MB\nTotal: {$memTotal}MB\n\nTop RAM Intensive Processes:";
		$ramJSON = runShellScript('MEM_PROCESSES', $scripts);
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
		postSlackMessage($messageText, $config, $attachments);

	} 
	// Check all alarms	
	elseif ('monitor' == $behavior) {
		// memory utilization
		$memJSON = runShellScript('MEM_INFO', $scripts);
		$memFree = round(preg_replace('[^0-9]','', $memJSON->MemFree) / 1024);
		$memTotal = round(preg_replace('[^0-9]','', $memJSON->MemTotal) / 1024);
		$inUsePercent = 100 - (round($memFree / $memTotal, 2) * 100);
		if ($inUsePercent >= $config['ALARM_MEM_USAGE']) {
			postSlackMessage("[{$serverName}] CRITICAL WARNING! MEMORY USAGE EXCEEDED WARNING THRESHOLD. MEMORY USAGE AT {$inUsePercent}%.", $config);
		}

		// cpu utilization
		$cpuUtil = runShellScript('CPU_LOAD', $scripts, false);
		if ($cpuUtil >= $config['ALARM_CPU_USAGE']) {
			postSlackMessage("[{$serverName}] CRITICAL WARNING! CPU USAGE EXCEEDED WARNING THRESHOLD. CPU USAGE AT {$cpuUtil}%.", $config);
		}

		// disk usage
		$diskJSON = runShellScript('DISK_USAGE', $scripts);
		$propName = 'used%';
		foreach ($diskJSON as $mount) {
			$usedPercent = (int) substr($mount->{$propName}, 0, (strlen($mount->{$propName}) - 1));
			if ($usedPercent >= $config['ALARM_DISK_USAGE']) {
				postSlackMessage("[{$serverName}] [{$mount->file_system} ({$mount->mounted})] CRITICAL WARNING! DISK USAGE EXCEEDED WARNING THRESHOLD. USAGE AT {$usedPercent}%", $config);	
			}
		}

		// apache
		$apacheCheck = runShellScript('APACHE', $scripts, false);
		if (!$apacheCheck) {
			postSlackMessage("[{$serverName}] CRITICAL WARNING! APACHE IS NOT RUNNING.", $config);	
		}

		// database
		$dbCheck = shell_exec('php -f ' . dirname(__FILE__).'/db-connect-test.php');
		if (!$dbCheck) {
			postSlackMessage("[{$serverName}] CRITICAL WARNING! MYSQL IS UNAVAILABLE.", $config);	
		}
	}
	else {
		echo "\nUsage: ld-alarm-cron.php (monitor|report) serverName\n\n";
	}
	
} else {
	echo "\nUsage: ld-alarm-cron.php (monitor|report) serverName\n\n";
}





