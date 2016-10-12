<?php
$dbh = mysqli_init();
if (mysqli_real_connect($dbh, 'localhost', 'cxs_wp_user', '4seUesc4HII5GS2r')) {
	echo 1;
} else {
	echo 0;
}
exit;



