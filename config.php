<?php

$host="redditawkward.com.mysql"; // Host name
$username="redditawkward_com"; // mysql username
$password="***"; // mysql password
$db_name="redditawkward_com"; // Database name

$hostname = "redditawkward.com";
$ENTRIES_PER_PAGE = 5;
$dbh=($GLOBALS["___mysqli_ston"] = mysqli_connect("$host",  "$username",  "$password")) or die ('2220 I cannot connect to the database because: ' . ((is_object($GLOBALS["___mysqli_ston"])) ? mysqli_error($GLOBALS["___mysqli_ston"]) : (($___mysqli_res = mysqli_connect_error()) ? $___mysqli_res : false)));
((bool)mysqli_query($GLOBALS["___mysqli_ston"], "USE " . $db_name));

mysqli_query($GLOBALS["___mysqli_ston"], "SET NAMES utf8");
//mysql_query("SET character_set_results=’utf8′"); // See more at: http://meebox.net/da/support/artikel/87/Problemer+med+aeoeaa+i+din+mysql+database/#sthash.CsbFQtL5.dpuf


// Connect to server and select databse.
//mysql_connect("$host", "$username", "$password")or die("cannot connect");
//mysql_select_db("$db_name")or die("cannot select DB");
//mysql_set_charset("utf8");
//
$goldMembership = "4";
?>
