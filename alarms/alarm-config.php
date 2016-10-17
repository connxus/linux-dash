<?php

$config = array(
	// message formatting values
	'HEX_COLOR_RED' => '#FF0000',
	'HEX_COLOR_GREEN' => '#00FF00',
	// alarm thresholds
	'ALARM_DISK_USAGE' => 60,
	// paths
	'SLACK_WEBHOOK_URL' => 'https://hooks.slack.com/services/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',
	'SHELL_PATH' => '/var/www/html/linux-dash/server/modules/shell_files',
	'SLACK_CHANNEL_OVERRIDE' => '@dolphinface', // set to null to use default configured channel, only change for testing
);

$scripts = array(
	'GENERAL_INFO' => "{$config['SHELL_PATH']}/general_info.sh",
	'APACHE' => "{$config['SHELL_PATH']}/apache_check.sh",
	'DISK_USAGE' => "{$config['SHELL_PATH']}/disk_partitions.sh",
	'CPU_LOAD_AVG' => "{$config['SHELL_PATH']}/load_avg.sh",
	'CPU_LOAD' => "{$config['SHELL_PATH']}/cpu_utilization.sh",
	'CPU_PROCESSES' => "{$config['SHELL_PATH']}/cpu_intensive_processes.sh",
);
