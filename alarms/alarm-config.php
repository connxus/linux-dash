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
);
