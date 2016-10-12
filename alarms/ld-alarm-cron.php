<?php

$slackWebHookUrl = "https://hooks.slack.com/services/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";
$messageText = 'Test.';

exec("curl -X POST --data-urlencode 'payload={\"text\": \"{$messageText}\"} {$slackWebHookUrl}");





