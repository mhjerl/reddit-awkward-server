<?php
header('Content-Type: application/json');
session_start();
include('config.php');
date_default_timezone_set('Europe/Copenhagen');
$dt2=date("Y-m-d H:i:s");

if (!isset($_GET['hash'])) { 
	$data['msg'] = "nohash";
	echo json_encode($data);
	exit();
}

if (!isset($_GET['redditor'])) { 
	$data['msg'] = "noredditor";
	echo json_encode($data);
	exit();
}


$data = Array();


$redditor = $_GET['redditor'];
$hash = $_GET['hash'];

$query4 = "SELECT * FROM prima_user WHERE redditor='$redditor' AND hash='$hash';";
$result4 = mysqli_query($GLOBALS["___mysqli_ston"], $query4);
$num_rows2 = mysqli_num_rows($result4);
if ($num_rows2 === 0) {
	$data['msg'] = "wronghash";
	echo json_encode($data);
	exit();
}
else {
	$data['msg'] = "correcthash";
	echo json_encode($data);
	exit();
}



?>
