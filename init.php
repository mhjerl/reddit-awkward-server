<?php









//header('Content-Type: application/json');










include('config.php');
date_default_timezone_set('Europe/Copenhagen');
$dt2=date("Y-m-d H:i:s");
//echo "Velkommen";
$redditor = $_GET['redditor'];
$commentpageid = $_GET['commentpageid'];
$pagename = $_GET['pagename'];
$subreddit = $_GET['subreddit'];
$visitortimezone = $_GET['visitortimezone'];

if (!isset($_GET['redditor'])) { die("Forbidden"); }
if ($_GET['redditor'] === "") { die("Forbidden..."); }

if (!isset($_GET['subreddit'])) { die("Forbidden, su."); }
if ($_GET['subreddit'] === "") { die("Forbidden, su."); }

if (!isset($_GET['commentpageid'])) { die("Forbidden, cpid."); }
if ($_GET['commentpageid'] === "") { die("Forbidden, cpid."); }

if (!isset($_GET['pagename'])) { die("Forbidden, cpn."); }
if ($_GET['pagename'] === "") { die("Forbidden, cpn."); }

if (!isset($_GET['visitortimezone'])) { die("Forbidden, tz."); }
if ($_GET['visitortimezone'] === "") { die("Forbidden, tz."); }

if (!isset($_GET['strictversion'])) { die("Forbidden, sv."); }
if ($_GET['strictversion'] === "") { die("Forbidden, sv."); }

$jsonConglomerateEr = Array();

if ($_GET['strictversion'] !== "2") {
	$jsonConglomerateEr['versionError'] = "You need to upgrade this app...";
	echo json_encode($jsonConglomerateEr);
	exit(0);
}

$jsonConglomerateEr['blockedError'] = "none";
$jsonConglomerateEr['versionError'] = "none";
$redditCommentPageURL = "https://www.reddit.com/r/" . $subreddit . "/comments/" . $commentpageid . "/" . $pagename;
//echo $redditCommentPageURL;
//exit;
$sql = "SELECT * FROM prima_germany WHERE pageid='$commentpageid'";
$result = mysqli_query($GLOBALS["___mysqli_ston"], $sql);
$count = mysqli_num_rows($result);
if ($count == 0) {
	$t = time();
	$sql = "INSERT INTO prima_germany (redditor, latestprimadonnaactivity, latestprimadonnaactivity_utc, source, pageid, pageurl, undersurveillance) VALUES ('$redditor', '$dt2', '$t', 'reddit', '$commentpageid', '$redditCommentPageURL', 'true');";
	mysqli_query($GLOBALS["___mysqli_ston"], $sql);


	// Create notification for new user:
	$dt2=date("Y-m-d H:i:s");
	$t = time();
	$sql = "INSERT INTO `redditawkward_com`.`prima_notification` (`redditor`, `pageid`, `commentid`, `whenf`, `utc`, `claimed`, `claimedwhen`, `claimedwhen_utc`, `motivation`, `tag`, `rule`, `subreddit`, `pagename`, `id`) VALUES ('$redditor', NULL, NULL, '$dt2', '$t', 'false', NULL, NULL, 'Welcome!', NULL, NULL, NULL, NULL, NULL);";
	//echo "<br><br>$sql";
	mysqli_query($GLOBALS["___mysqli_ston"], $sql);

}


// Tell this fella that page is a-hot!
$query2 = "UPDATE prima_germany SET latestprimadonnaactivity='$dt2', WHERE pageid='$commentpageid'";
mysqli_query($GLOBALS["___mysqli_ston"], $query2);


$query4 = "SELECT * FROM prima_user WHERE redditor='$redditor';";
$result4 = mysqli_query($GLOBALS["___mysqli_ston"], $query4);
$count4 = mysqli_num_rows($result4);
if ($count4 == 0 && $redditor) {
	$newHash = generateRandomString();
	$dt2=date("Y-m-d H:i:s");
	$query5 = "INSERT INTO prima_user VALUES ('$redditor', '$dt2', '0', '$newHash', 'free', NULL, '0', 'neutral', NULL, '', NULL);";
	mysqli_query($GLOBALS["___mysqli_ston"], $query5);
}



$query4 = "UPDATE prima_user SET timezone='$visitortimezone' WHERE redditor='$redditor';";
mysqli_query($GLOBALS["___mysqli_ston"], $query4);


$sql = "SELECT * FROM prima_user_block WHERE redditor='$redditor' AND enabled='true'";
$result = mysqli_query($GLOBALS["___mysqli_ston"], $sql);
$count = mysqli_num_rows($result);
if ($count > 0) {
	$jsonConglomerateEr['blockedError'] = "blocked";
	echo json_encode($jsonConglomerateEr);
	exit(0);
}











$sql = "SELECT cert_id, status, commentid FROM prima_certificate WHERE subreddit='$subreddit' AND pageid='$commentpageid';";
$result = mysqli_query($GLOBALS["___mysqli_ston"], $sql);
$certificatesArray = Array();
$c = 0;
while($row = mysqli_fetch_array($result)) {
  $certificatesArray[$c] = new stdClass();
  $certificatesArray[$c]->cert_id = $row[0];
  $certificatesArray[$c]->status = $row[1];
  $certificatesArray[$c]->commentid = $row[2];
  $c++;
}

$data = json_encode($certificatesArray);

$jsonConglomerateEr['certificates'] = $data;














$query = "SELECT DISTINCT author
FROM prima_stuttgart s, prima_user u
WHERE s.author = u.redditor AND s.pageid = '$commentpageid' AND u.redditor != '$redditor'";

//echo $query . "<br>";

$result = mysqli_query($GLOBALS["___mysqli_ston"], $query);

$memberArrayTemp = Array();
$c = 0;
while($row = mysqli_fetch_array($result)){
  $memberArrayTemp[$c] = $row[0];
  //echo "<br><br>--->" . $memberArrayTemp[$c];
  $c++;
}

$memberArray = Array();
for ($c = 0; $c < sizeof($memberArrayTemp); $c++) {
	$otherRedditor = $memberArrayTemp[$c];
	$query4 = "SELECT imagetype, imagecustom FROM prima_user WHERE redditor='$otherRedditor';";
	$result4 = mysqli_query($GLOBALS["___mysqli_ston"], $query4);
	$row4 = mysqli_fetch_row($result4);
	$imageType = $row4[0];
	$imageCustom = $row4[1];
	$memberArray[$c] = new stdClass();
	$memberArray[$c]->member = $otherRedditor;
	$memberArray[$c]->imagetype = $imageType;
	$memberArray[$c]->imagecustom = $imageCustom;
	//echo "<br><br>" . $otherRedditor . " " . $imageType . " " . $imageCustom;
}

$data = json_encode($memberArray);

$jsonConglomerateEr['membersOnPage'] = $data;




$query = "SELECT * FROM prima_relation WHERE firstperson = '$redditor' AND firstperson_pageid = '$commentpageid';";

$result = mysqli_query($GLOBALS["___mysqli_ston"], $query);

$tagAArray = Array();
$c = 0;
while($row = mysqli_fetch_array($result)){
  $tagAArray[$c] = Array();
  $tagAArray[$c]['commentid'] = $row[3];
  $tagAArray[$c]['finallyrepliedbyredditor'] = $row[4]; 
  $tagAArray[$c]['utc_created'] = $row[5];
  $c++;
}
$data = json_encode($tagAArray);
//$data = preg_replace('/\\\"/',"\"", $data);


//$data1['success'] = "true";
//$data['viewtext'] = nl2br($t);
//$data1['result'] = $data;

$jsonConglomerateEr['arows'] = $data;












$query = "SELECT * FROM prima_taga_unanswered WHERE redditor='$redditor' AND commentpageid='$commentpageid';";

$result = mysqli_query($GLOBALS["___mysqli_ston"], $query);

$tagAArray = Array();
$c = 0;
while($row = mysqli_fetch_array($result)){
  $tagAArray[$c] = Array();
  $tagAArray[$c]['commentid'] = $row[3];
  $tagAArray[$c]['finallyrepliedbyredditor'] = $row[4]; 
  $tagAArray[$c]['utc_created'] = $row[5]; 
  $c++;
}
$data = json_encode($tagAArray);
//$data = preg_replace('/\\\"/',"\"", $data);


//$data1['success'] = "true";
//$data['viewtext'] = nl2br($t);
//$data1['result'] = $data;

$jsonConglomerateEr['arows'] = $data;
//echo '{ "arows": ' . $data; 






//echo "<br><br><br>";










$query = "SELECT * FROM prima_karmagift WHERE redditor='$redditor' AND claimed='false';";

$result = mysqli_query($GLOBALS["___mysqli_ston"], $query);

$gifts = Array();
$c = 0;
while($row = mysqli_fetch_array($result)) {
	$gifts[$c] = Array();
	$redditorAddressed = $row[1];
	$pageid = $row[2];
	$commentid = $row[3];
	$when = $row[4];
	$utc = $row[5];
	$points = $row[6];
	$motivation = $row[10];
	$rule = $row[11];
	$subreddit = $row[12];
	$pagename = $row[13];
	$tag = $row[14];
	$gifts[$c]['redditorAddressed'] = $redditorAddressed;
	$gifts[$c]['pageid'] = $pageid;
	$gifts[$c]['commentid'] = $commentid;
	$gifts[$c]['when'] = $when;
	$gifts[$c]['utc'] = $utc;
	$gifts[$c]['points'] = $points;
	$gifts[$c]['motivation'] = $motivation;
	$gifts[$c]['rule'] = $rule;
	$gifts[$c]['subreddit'] = $subreddit;
	$gifts[$c]['pagename'] = $pagename;
	$gifts[$c]['tag'] = $tag;
	$dt2=date("Y-m-d H:i:s");
  /*



	P R O D U C T I O N :

*/

/*
	$t = time();
	$query2 = "UPDATE prima_karmagift SET claimed='true', claimedwhen='$dt2', claimedwhen_utc='$t' WHERE redditor='$redditor' AND pageid='$pageid' AND commentid='$commentid';";
	mysqli_query($GLOBALS["___mysqli_ston"], $query2);
*/


	$query3 = "SELECT * FROM prima_user WHERE redditor='$redditor';";
	$result3 = mysqli_query($GLOBALS["___mysqli_ston"], $query3);
	$row3 = mysqli_fetch_row($result3);
	$currentpkarma = $row3[2];
	$newpkarma = $currentpkarma + $points;
	$query4 = "UPDATE prima_user SET points='$newpkarma' WHERE redditor='$redditor';";
	mysqli_query($GLOBALS["___mysqli_ston"], $query4);



  $c++;
}
//var_dump($gifts);
$data = json_encode($gifts);

//echo ', "gifts": ' . $data; 
$jsonConglomerateEr['gifts'] = $data;










$query4 = "SELECT * FROM prima_user WHERE redditor='$redditor';";
$result4 = mysqli_query($GLOBALS["___mysqli_ston"], $query4);
$row4 = mysqli_fetch_row($result4);
$totalPKarma = $row4[2];
$accountType = $row4[4];
$totalRKarma = $row4[6];
$imagetype = $row4[7];
$imagecustom = $row4[8];
$jsonConglomerateEr['totalPKarma'] = $totalPKarma;
$jsonConglomerateEr['accountType'] = $accountType;
$jsonConglomerateEr['totalRKarma'] = $totalRKarma;
$jsonConglomerateEr['imagetype'] = $imagetype;
$jsonConglomerateEr['imagecustom'] = $imagecustom;

/*
Deprecated
(Generated client-side)

$certificateId = "CTC-" . generateRandomNumberString(3) . "-" . generateRandomNumberString(4) . "-" . generateRandomNumberString(3);
$jsonConglomerateEr['certificateId'] = $certificateId;
*/





$query = "SELECT * FROM prima_notification WHERE redditor='$redditor' AND claimed='false';";

$result = mysqli_query($GLOBALS["___mysqli_ston"], $query);

$notifications = Array();
$c = 0;
while($row = mysqli_fetch_array($result)) {
	$notifications[$c] = Array();
	$pageid = $row[1];
	$commentid = $row[2];
	$when = $row[3];
	$utc = $row[4];
	$claimedwhen_utc = $row[7];
	$motivation = $row[8];
	$tag = $row[9];
	$rule = $row[10];
	$subreddit = $row[11];
	$pagename = $row[12];
	$notifications[$c]['pageid'] = $pageid;
	$notifications[$c]['commentid'] = $commentid;
	$notifications[$c]['when'] = $when;
	$notifications[$c]['utc'] = $utc;
	$notifications[$c]['claimedwhen_utc'] = $claimedwhen_utc;
	$notifications[$c]['motivation'] = $motivation;
	$notifications[$c]['tag'] = $tag;
	$notifications[$c]['rule'] = $rule;
	$notifications[$c]['subreddit'] = $subreddit;
	$notifications[$c]['pagename'] = $pagename;
	$dt2=date("Y-m-d H:i:s");
  /*



	P R O D U C T I O N :



*/


/*
	$t = time();
	$query2 = "UPDATE prima_notification SET claimed='true', claimedwhen='$dt2', claimedwhen_utc='$t' WHERE redditor='$redditor' AND pageid='$pageid' AND commentid='$commentid';";
	mysqli_query($GLOBALS["___mysqli_ston"], $query2);
*/


  $c++;
}
//var_dump($gifts);
$data = json_encode($notifications);

//echo ', "gifts": ' . $data; 
$jsonConglomerateEr['notifications'] = $data;













// se ¤ao siden
$query8 = "SELECT * FROM prima_tagc_status WHERE commentpageid='$commentpageid';";

$result8 = mysqli_query($GLOBALS["___mysqli_ston"], $query8);

$expectingbackwisereply = Array();
$c = 0;
while($row = mysqli_fetch_array($result8)){
  $commentidexpecting = $row[1];
  $expectedanswererredditorandoriginalbackwiseredditor = $row[2];
  $redditorwaiting = $row[3];
  $pkarmadistributed = $row[4];
  $expectingbackwisereply[$c]['commentidexpectingdirectanswerfromspecificredditor'] = $commentidexpecting;
  $expectingbackwisereply[$c]['expectedanswererredditorandoriginalbackwiseredditor'] = $expectedanswererredditorandoriginalbackwiseredditor;
  $expectingbackwisereply[$c]['redditorwaitingfordirectanswerfromspecificredditor'] = $redditorwaiting;
  $expectingbackwisereply[$c]['pkarmadistributed'] = $pkarmadistributed;
  $c++;
}
$data = json_encode($expectingbackwisereply);

//echo ', "crows": ' . $data; 
$jsonConglomerateEr['crows'] = $data;



// se ¤ao siden
$query8 = "SELECT * FROM prima_tagc_status WHERE commentpageid='$commentpageid';";

$result8 = mysqli_query($GLOBALS["___mysqli_ston"], $query8);

$expectingbackwisereply = Array();
$c = 0;
while($row = mysqli_fetch_array($result8)){
  $commentidexpecting = $row[1];
  $expectedanswererredditorandoriginalbackwiseredditor = $row[2];
  $redditorwaiting = $row[3];
  $pkarmadistributed = $row[4];
  $expectingbackwisereply[$c]['commentidexpectingdirectanswerfromspecificredditor'] = $commentidexpecting;
  $expectingbackwisereply[$c]['expectedanswererredditorandoriginalbackwiseredditor'] = $expectedanswererredditorandoriginalbackwiseredditor;
  $expectingbackwisereply[$c]['redditorwaitingfordirectanswerfromspecificredditor'] = $redditorwaiting;
  $expectingbackwisereply[$c]['pkarmadistributed'] = $pkarmadistributed;
  $c++;
}
$data = json_encode($expectingbackwisereply);

//echo ', "crows": ' . $data; 
$jsonConglomerateEr['crows'] = $data;



// Sum rkarma for each of this redditors friendships

// 1. find redditors friends (relations) where redditor is firstperson
$friendsTemp = Array();
$query = "SELECT firstperson FROM prima_relation WHERE secondperson='$redditor';";

$result8 = mysqli_query($GLOBALS["___mysqli_ston"], $query);

$c = 0;
while($row = mysqli_fetch_array($result8)){
	$friend = $row[0];
	$friendsTemp[$c] = $friend;
	$c++;
}

// 2. find redditors friends (relations) where redditor is firstperson
$query = "SELECT secondperson FROM prima_relation WHERE firstperson='$redditor';";

$result8 = mysqli_query($GLOBALS["___mysqli_ston"], $query);

while($row = mysqli_fetch_array($result8)) {
	$friend = $row[0];
	$friendsTemp[$c] = $friend;
	$c++;
}

// 3. remove duplicates
$friendsTemp = array_unique($friendsTemp);

// 4. sum the rkarma for each friend
$friends = Array();
for ($c = 0; $c < sizeof($friendsTemp); $c++) {
	$friend = $friendsTemp[$c];
	$sql = "SELECT SUM(rkarmaforboth) AS totalrkarma FROM prima_relation WHERE (firstperson='$redditor' AND secondperson='$friend') OR (firstperson='$friend' AND secondperson='$redditor');";
	$result8 = mysqli_query($GLOBALS["___mysqli_ston"], $sql);
	$row = mysqli_fetch_row($result8);
	$totalRKarma = $row[0];

	$sql = "SELECT imagetype, imagecustom FROM prima_user WHERE redditor='$friend';";
	$result9 = mysqli_query($GLOBALS["___mysqli_ston"], $sql);
	$row9 = mysqli_fetch_row($result9);
	$imagetype = $row9[0];
	$imagecustom = $row9[1];
	

	$friends[$c]['friend'] = $friend;
	$friends[$c]['total'] = $totalRKarma;
	$friends[$c]['imagetype'] = $imagetype;
	$friends[$c]['imagecustom'] = $imagecustom;
	//echo "$redditor friend: " . $friend . " total: " . $totalRKarma;
}

$data = json_encode($friends);

//echo ', "crows": ' . $data; 
$jsonConglomerateEr['friends'] = $data;


// Look for and add redditors with whom our redditor has a conflict







/*
$sql = "SELECT SUM(rkarmaforboth) AS totalrkarma FROM prima_relation WHERE (firstperson='$redditor' AND secondperson='$friend') OR (firstperson='$friend' AND secondperson='$redditor');";

$query = "SELECT * FROM prima_needed_apology WHERE angry_redditor = '$redditor' AND firstperson_pageid = '$commentpageid';";

$result = mysqli_query($GLOBALS["___mysqli_ston"], $query);

$tagAArray = Array();
$c = 0;
while($row = mysqli_fetch_array($result)){
  $tagAArray[$c] = Array();
  $tagAArray[$c]['commentid'] = $row[3];
  $tagAArray[$c]['finallyrepliedbyredditor'] = $row[4]; 
  $tagAArray[$c]['utc_created'] = $row[5];
  $c++;
}
*/









$extendedInfoAboutRedditorsOnPage = array();

$uri_path = parse_url($redditCommentPageURL, PHP_URL_PATH);
$uri_segments = explode('/', $uri_path);
$subreddit = $uri_segments[2];
$pagename = $uri_segments[4];
//echo "subreddit: $subreddit<br><br>";
//echo "pagename: $pagename<br><br>";
//echo "subreddit: " . $subreddit . "<br>pagename: " . $pagename . "<br>";
$ch = curl_init(stripTrailingSlash($redditCommentPageURL) . ".json");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
$content = curl_exec($ch);
curl_close($ch);
$jsonObj = json_decode($content, false);
$mainPostId = $jsonObj[0]->data->children[0]->data->id;
$mainPostAuthor = $jsonObj[0]->data->children[0]->data->author;
$mainPostType = "link";
if ($jsonObj[0]->data->children[0]->data->selftext_html) {
	$mainPostType = "text";
}
$mintArrayOfIdsToBodiesAndAuthorsAndParentIds = traverseG($jsonObj);
//echo "length: " . sizeof($mintArrayOfIdsToBodiesAndAuthorsAndParentIds . "<br");
$d = 0;
foreach ($mintArrayOfIdsToBodiesAndAuthorsAndParentIds as $id=>$mintCommentThatIsNotFromDBButFromTheNet) {
	$extendedInfoAboutRedditorsOnPage[$d] = new stdClass();
	$extendedInfoAboutRedditorsOnPage[$d]->id = $id;
	$parentId = $mintCommentThatIsNotFromDBButFromTheNet->parent_id;
	//$extendedInfoAboutRedditorsOnPage[$d]->parent_id = $parentId;
	//$extendedInfoAboutRedditorsOnPage[$d]->author = $mintCommentThatIsNotFromDBButFromTheNet->author;
	//$extendedInfoAboutRedditorsOnPage[$d]->body = $mintCommentThatIsNotFromDBButFromTheNet->body;
	
	$parentCommentWithAllInfoInHere = $mintArrayOfIdsToBodiesAndAuthorsAndParentIds[$parentId];
	$grandParentCommentWithAllInfoInHere = $mintArrayOfIdsToBodiesAndAuthorsAndParentIds[$parentCommentWithAllInfoInHere->parent_id];
	$parentRedditorName = $parentCommentWithAllInfoInHere->author;
	$parentBody = $parentCommentWithAllInfoInHere->body;
	$grandParentRedditorName = $grandParentCommentWithAllInfoInHere->author;
	$friendRelationStrength = null;
	$friend = null;
	$tagsAtYourDisposal = Array();
	










	// Enforce general rule §5: Redditors can't direct any tags towards their own comments.
	if ($mintCommentThatIsNotFromDBButFromTheNet->author === $redditor) {
		$extendedInfoAboutRedditorsOnPage[$d]->tagsAtYourDisposal = $tagsAtYourDisposal;
		$d++;
		continue; // Skip last of the loop structure
	}





















	/*for ($c = 0; $c < sizeof($friends); $c++) {
		if ($friends[$c]['friend'] == $parentRedditorName) {
			$friend = $friends[$c];
			//echo "<br><br>friend: " . $friend['friend'] . " total: " . $friend['total'] . " parentRedditorName: " . $parentRedditorName . " parentId: " . $parentId;
			$friendRelationStrength = $friends[$c]['total'];
			break;
		}
	}*/
	//$extendedInfoAboutRedditorsOnPage[$d]->relationStrength = $friendRelationStrength;
	
	$c = 0;
	$commentBody = $mintCommentThatIsNotFromDBButFromTheNet->body;
	if (strpos($commentBody, 'comment-tag{your.comment.inspired.me}') !== false) {
		if ($parentRedditorName === $redditor) {
			$tagsAtYourDisposal[$c] = new stdClass();
			$tagsAtYourDisposal[$c]->cid = $id;
			$tagsAtYourDisposal[$c]->tag = "comment-tag{i.am.glad.you.said.that.to.me}";
			$c++;
			$tagsAtYourDisposal[$c] = new stdClass();
			$tagsAtYourDisposal[$c]->cid = $id;
			$tagsAtYourDisposal[$c]->tag = "comment-tag{thanks}";
			$c++;
		}
	}
	if (strpos($commentBody, 'comment-tag{i.am.glad.you.said.that.to.me}') !== false) {
		if ($parentRedditorName === $redditor) {
			$tagsAtYourDisposal[$c] = new stdClass();
			$tagsAtYourDisposal[$c]->cid = $id;
			$tagsAtYourDisposal[$c]->tag = "comment-tag{thanks}";
			$c++;
			$tagsAtYourDisposal[$c] = new stdClass();
			$tagsAtYourDisposal[$c]->cid = $id;
			$tagsAtYourDisposal[$c]->tag = "comment-tag{youre.welcome}";
			$c++;
		}
	}
	if (strpos($commentBody, 'comment-tag{thanks}') !== false) {
		if ($parentRedditorName === $redditor) {
			$tagsAtYourDisposal[$c] = new stdClass();
			$tagsAtYourDisposal[$c]->cid = $id;
			$tagsAtYourDisposal[$c]->tag = "comment-tag{youre.welcome}";
			$c++;
		}
	}
	/*echo "<br><br><br>1:$id";
	echo "<br>$mainPostId";
	echo "<br>$parentId";
	echo "<br>$redditor";
	echo "<br>$mainPostAuthor";
	echo "<br>$mintCommentThatIsNotFromDBButFromTheNet->author";
	echo "<br>Done...";*/


	if ($id !== $mainPostId) {
		// Here: Post is not main post
		// Therefore: Check 
		if ($parentId === $mainPostId && $redditor === $mainPostAuthor && $redditor !== $mintCommentThatIsNotFromDBButFromTheNet->author) {
			// Here: Somebody commented on this redditors main post
			// Therefore: Give him/her the ability to say: "thanks but a bit off topic"
			$tagsAtYourDisposal[$c] = new stdClass();
			$tagsAtYourDisposal[$c]->cid = $id;
			$tagsAtYourDisposal[$c]->tag = "comment-tag{thanks.but.a.bit.off.topic}";
			$c++;
		
		}
	} 
	if ($id === $mainPostId) {
		$tagsAtYourDisposal[$c] = new stdClass();
		$tagsAtYourDisposal[$c]->cid = $id;
		$tagsAtYourDisposal[$c]->tag = "comment-tag{i.wont.comment.for.personal.reasons}";
		$c++;
		$tagsAtYourDisposal[$c] = new stdClass();
		$tagsAtYourDisposal[$c]->cid = $id;
		$tagsAtYourDisposal[$c]->tag = "comment-tag{i.find.this.unworthy.for.discussion}";
		$c++;
		$tagsAtYourDisposal[$c] = new stdClass();
		$tagsAtYourDisposal[$c]->cid = $id;
		$tagsAtYourDisposal[$c]->tag = "comment-tag{i.find.the.subject.unworthy.for.discussion}";
		$c++;
		$tagsAtYourDisposal[$c] = new stdClass();
		$tagsAtYourDisposal[$c]->cid = $id;
		$tagsAtYourDisposal[$c]->tag = "comment-tag{i.dont.think.the.original.post.has.been.addressed.yet}";
		$c++;
		$tagsAtYourDisposal[$c] = new stdClass();
		$tagsAtYourDisposal[$c]->cid = $id;
		$tagsAtYourDisposal[$c]->tag = "comment-tag{i.dont.think.the.original.post.has.been.taken.seriously.yet}";
		$c++;
		$tagsAtYourDisposal[$c] = new stdClass();
		$tagsAtYourDisposal[$c]->cid = $id;
		$tagsAtYourDisposal[$c]->tag = "comment-tag{i.dont.think.the.original.post.has.been.treated.respectfully}";
		$c++;
		$tagsAtYourDisposal[$c] = new stdClass();
		$tagsAtYourDisposal[$c]->cid = $id;
		$tagsAtYourDisposal[$c]->tag = "comment-tag{watch.me.playing.soccer.with.myself.in.this.video}";
		$c++;
		if ($mintCommentThatIsNotFromDBButFromTheNet->author !== $redditor) {
			if ($mainPostType === "text") {
				$tagsAtYourDisposal[$c] = new stdClass();
				$tagsAtYourDisposal[$c]->cid = $id;
				$tagsAtYourDisposal[$c]->tag = "comment-tag{your.post.inspired.me}";
				$c++;
			}
			else {
				$tagsAtYourDisposal[$c] = new stdClass();
				$tagsAtYourDisposal[$c]->cid = $id;
				$tagsAtYourDisposal[$c]->tag = "comment-tag{your.link.inspired.me}";
				$c++;
			}
		}
	}
	if (strpos($commentBody, 'comment-tag{i.will.not.reply.and.expect.apology}') !== false) {
		if ($parentRedditorName === $redditor) {
			$tagsAtYourDisposal[$c] = new stdClass();
			$tagsAtYourDisposal[$c]->cid = $id;
			$tagsAtYourDisposal[$c]->tag = "comment-tag{i.apologize}";
			$c++;
			$tagsAtYourDisposal[$c] = new stdClass();
			$tagsAtYourDisposal[$c]->cid = $id;
			$tagsAtYourDisposal[$c]->tag = "comment-tag{guarded.apology}";
			$c++;
		}
	}
	if (strpos($commentBody, 'comment-tag{i.apologize}') !== false or strpos($commentBody, 'comment-tag{guarded.apology}') !== false) {
		if ($parentRedditorName === $redditor) {
			$tagsAtYourDisposal[$c] = new stdClass();
			$tagsAtYourDisposal[$c]->cid = $id;
			$tagsAtYourDisposal[$c]->tag = "comment-tag{no.problem}";
			$c++;
			$tagsAtYourDisposal[$c] = new stdClass();
			$tagsAtYourDisposal[$c]->cid = $id;
			$tagsAtYourDisposal[$c]->tag = "comment-tag{dont.mind.its.ok.lets.move.on}";
			$c++;
			$tagsAtYourDisposal[$c] = new stdClass();
			$tagsAtYourDisposal[$c]->cid = $id;
			$tagsAtYourDisposal[$c]->tag = "comment-tag{its.fine.i.consider.the.case.closed}";
			$c++;
		}
	}
	if (strpos($commentBody, 'comment-tag{no.problem}') !== false or strpos($commentBody, 'comment-tag{dont.mind.its.ok.lets.move.on}') !== false or strpos($commentBody, 'comment-tag{its.fine.i.consider.the.case.closed}') !== false) {
		if ($parentRedditorName === $redditor) {
			$tagsAtYourDisposal[$c] = new stdClass();
			$tagsAtYourDisposal[$c]->cid = $id;
			$tagsAtYourDisposal[$c]->tag = "comment-tag{thanks}";
			$c++;
			$tagsAtYourDisposal[$c] = new stdClass();
			$tagsAtYourDisposal[$c]->cid = $id;
			$tagsAtYourDisposal[$c]->tag = "comment-tag{explanation.why.i.was.angry}";
			$c++;
			$tagsAtYourDisposal[$c] = new stdClass();
			$tagsAtYourDisposal[$c]->cid = $id;
			$tagsAtYourDisposal[$c]->tag = "comment-tag{i.was.being.careless}";
			$c++;
		}
	}
	if (strpos($commentBody, 'comment-tag{interesting.will.write.more.in.a.few.days.time}') !== false) {
		if ($parentRedditorName === $redditor) {
			$tagsAtYourDisposal[$c] = new stdClass();
			$tagsAtYourDisposal[$c]->cid = $id;
			$tagsAtYourDisposal[$c]->tag = "comment-tag{thanks}";
			$c++;
		}
	}
	if (strpos($commentBody, 'comment-tag{that.pissed.me.off.but.please.dont.mind}') !== false) {
		if ($parentRedditorName === $redditor) {
			$tagsAtYourDisposal[$c] = new stdClass();
			$tagsAtYourDisposal[$c]->cid = $id;
			$tagsAtYourDisposal[$c]->tag = "comment-tag{thanks}";
			$c++;
		}
	}
	if (strpos($commentBody, 'comment-tag{i.am.one.of.the.strangest.people.youll.ever.meet}') !== false) {
		if ($parentRedditorName === $redditor) {
			$tagsAtYourDisposal[$c] = new stdClass();
			$tagsAtYourDisposal[$c]->cid = $id;
			$tagsAtYourDisposal[$c]->tag = "comment-tag{er.hi.what.kind.of.strange.presentation.is.that}";
			$c++;
		}
	}
	if (strpos($commentBody, 'comment-tag{')                     ===                 false) {
		// Here: Body has no awkward tag of any kind
		// Therefore: Allow awkward tag
		$tagsAtYourDisposal[$c] = new stdClass();
		$tagsAtYourDisposal[$c]->cid = $id;
		$tagsAtYourDisposal[$c]->tag = "comment-tag{awkward}";
		$c++;
	}
	//echo "-----------------------------------------------------><br>parent: $parentRedditorName -redditor: $redditor";
	if ($parentRedditorName === $redditor) {
		$tagsAtYourDisposal[$c] = new stdClass();
		$tagsAtYourDisposal[$c]->cid = $id;
		$tagsAtYourDisposal[$c]->tag = "comment-tag{that.pissed.me.off.but.please.dont.mind}";
		$c++;
		//echo "<br>-------------->body: $commentBody";
		//if (strpos($commentBody, 'comment-tag{')             ===            false) {
			//echo "<br>b";
			if (!needsToApologize($parentRedditorName, $redditor)) {
					//echo "<br>c";
					// Here: No comment-tag{i.will.not.reply.and.expect.apology} already
					// Therefore: comment-tag{i.will.not.reply.and.expect.apology} allowed.
					$tagsAtYourDisposal[$c] = new stdClass();
					$tagsAtYourDisposal[$c]->cid = $id;
					$tagsAtYourDisposal[$c]->tag = "comment-tag{i.will.not.reply.and.expect.apology}";
					$c++;
			}
		//}
	}
	
	if (strpos($commentBody, 'comment-tag{') !== false) {
		// Here: Body has awkward tag
		// Therefore: Allow the comment-tag{youre.being.overly.ironic.and.are.violating.the.rules} tag:
		$tagsAtYourDisposal[$c] = new stdClass();
		$tagsAtYourDisposal[$c]->cid = $id;
		$tagsAtYourDisposal[$c]->tag = "comment-tag{youre.being.overly.ironic.and.are.violating.the.rules}";
		$c++;
	}
	
	if (strpos($commentBody, 'comment-tag{')             ===            false) {
		// Here: Author of comment is myself
		// Therefore: Allow: comment-tag{interesting.will.write.more.in.a.few.days.time}
		$tagsAtYourDisposal[$c] = new stdClass();
		$tagsAtYourDisposal[$c]->cid = $id;
		$tagsAtYourDisposal[$c]->tag = "comment-tag{interesting.will.write.more.in.a.few.days.time}";
		$c++;
	}

	if ($redditor !== $mintCommentThatIsNotFromDBButFromTheNet->author) {
		$tagsAtYourDisposal[$c] = new stdClass();
		$tagsAtYourDisposal[$c]->cid = $id;
		$tagsAtYourDisposal[$c]->tag = "comment-tag{a.warning.from.one.intellectual.to.another}";
		$c++;
	}
	
	if ($mainPostId !== $id) {
		$tagsAtYourDisposal[$c] = new stdClass();
		$tagsAtYourDisposal[$c]->cid = $id;
		$tagsAtYourDisposal[$c]->tag = "comment-tag{your.comment.inspired.me}";
		$c++;
	}
	$tagsAtYourDisposal[$c] = new stdClass();
	$tagsAtYourDisposal[$c]->cid = $id;
	$tagsAtYourDisposal[$c]->tag = "comment-tag{waits.for.anyone}";
	$c++;
	$tagsAtYourDisposal[$c] = new stdClass();
	$tagsAtYourDisposal[$c]->cid = $id;
	$tagsAtYourDisposal[$c]->tag = "comment-tag{waits.for.your.reply.only}";
	$c++;
	$tagsAtYourDisposal[$c] = new stdClass();
	$tagsAtYourDisposal[$c]->cid = $id;
	$tagsAtYourDisposal[$c]->tag = "comment-tag{i.consider.this.comment.definitive.and.consider.any.reply.inappropriate}";
	$c++;
	$tagsAtYourDisposal[$c] = new stdClass();
	$tagsAtYourDisposal[$c]->cid = $id;
	$tagsAtYourDisposal[$c]->tag = "comment-tag{a.warm.welcome.to.my.world.without.a.naive.invitation.to.be.my.friend}";
	$c++;





	$extendedInfoAboutRedditorsOnPage[$d]->tagsAtYourDisposal = $tagsAtYourDisposal;

	$d++;
}


$data = json_encode($extendedInfoAboutRedditorsOnPage);

//echo ', "crows": ' . $data; 
$jsonConglomerateEr['tagsAtYourDisposal'] = $data;







$conflictRedditorsOnPage = Array();
foreach ($mintArrayOfIdsToBodiesAndAuthorsAndParentIds as $id=>$mintCommentThatIsNotFromDBButFromTheNet) {
	if ($mintCommentThatIsNotFromDBButFromTheNet->author !== $redditor) {	
		if (needsToApologize($mintCommentThatIsNotFromDBButFromTheNet->author, $redditor)) {
			$apologizeEntry = new stdClass();
			$apologizeEntry->needsToApologizeRedditor = $mintCommentThatIsNotFromDBButFromTheNet->author;
			$apologizeEntry->angryRedditor = $redditor;
			array_push($conflictRedditorsOnPage, $apologizeEntry);
		}
		if (needsToApologize($redditor, $mintCommentThatIsNotFromDBButFromTheNet->author)) {
			$apologizeEntry = new stdClass();
			$apologizeEntry->needsToApologizeRedditor = $redditor;
			$apologizeEntry->angryRedditor = $mintCommentThatIsNotFromDBButFromTheNet->author;
			array_push($conflictRedditorsOnPage, $apologizeEntry);
		}
	}

}

$data = json_encode($conflictRedditorsOnPage);

//echo ', "crows": ' . $data; 
$jsonConglomerateEr['conflictRedditorsOnPage'] = $data;













echo json_encode($jsonConglomerateEr);













function getParentId2NewVersion($mintArrayOfIdsToBodiesAndAuthorsAndParentIds, $id) {
	foreach ($mintArrayOfIdsToBodiesAndAuthorsAndParentIds as $id2=>$mintCommentThatIsNotFromDBButFromTheNet2) {
		if ($mintCommentThatIsNotFromDBButFromTheNet2->parent_id == $id) {
			//echo "$id2's parent is $id<br><br><br><br>";
			return $id;
		}
	}
}

// copy-pasted from germany_cron.php
function traverseG($x, &$in_arr = array()) {  // <-- note the reference '&'
  if (is_array($x)) {
    traverseArrayG($x, $in_arr);
  }
  else if (is_object($x)) {	
	//echo "<br>" . $lookOut . " ". $lookOutForId;
    	traverseObjectG($x, $in_arr);
  }
  else {
	
  }
  return $in_arr;
}

// copy-pasted from germany_cron.php
function traverseArrayG($arr, &$in_arr = array()) {
  foreach ($arr as $x) {
    traverseG($x, $in_arr);
  };
}

// copy-pasted from germany_cron.php
function traverseObjectG($obj, &$in_arr = array()) {
  $array = get_object_vars($obj);
  $properties = array_keys($array);
  foreach($properties as $key) {
		if ($key == "body") {
			 if (!$in_arr[$rememberOThatIdYeah] ) { $in_arr[$rememberOThatIdYeah] = new stdClass(); }
			//echo "<br><br>Id:" . $rememberOThatIdYeah . " Body:" . $array['body'];
			$in_arr[$rememberOThatIdYeah]->body = $array['body'];
		}
		if ($key == "parent_id") { if (!$in_arr[$rememberOThatIdYeah] ) { $in_arr[$rememberOThatIdYeah] = new stdClass();} $in_arr[$rememberOThatIdYeah]->parent_id = substr($array['parent_id'], 3); }
		if ($key == "author") { if (!$in_arr[$rememberOThatIdYeah] ) { $in_arr[$rememberOThatIdYeah] = new stdClass();} $in_arr[$rememberOThatIdYeah]->author = $array['author']; }
		if ($key == "id") { $rememberOThatIdYeah = $array['id']; }
		traverseG($obj->$key, $in_arr);
  }
}

















// copy-pasted from germany_cron.php
function stripTrailingSlash($url) {
	if(substr($url, -1) == '/') {
		  $url = substr($url, 0, -1);
	}
	return $url;
}

// copy-pasted from germany_cron.php
function needsToApologize($hypotheticallyApologizerRedditor, $hypotheticallyAndHystericalAngryRedditor) {
	$query = "SELECT * FROM prima_needed_apology WHERE angry_redditor='$hypotheticallyAndHystericalAngryRedditor' AND need_to_apol_redditor='$hypotheticallyApologizerRedditor' AND has_apologized='false';";
	$result3 = mysqli_query($GLOBALS["___mysqli_ston"], $query);
	$count3 = mysqli_num_rows($result3);
	return ($count3 > 0);
}

function generateRandomString($length = 4) {
    return substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, $length);
}

function generateRandomNumberString($length = 4) {
    return substr(str_shuffle("0123456789"), 0, $length);
}

function die3($a) {
	$data2['success'] = "false";
	$data2['message'] = $a;
	die(json_encode($data2));
}



?>
