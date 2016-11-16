<?php
header('Content-Type: text/html; charset=utf-8');
while (@ob_end_flush());
include('config.php');
require __DIR__ . "/../tagoverview.php";
date_default_timezone_set('Europe/Copenhagen');
$dt2=date("Y-m-d H:i:s");
$somethingHappenedInStuttTown;
////echo "Velkommen";

$query = "SELECT * FROM prima_germany WHERE undersurveillance='true';";

$result = mysqli_query($GLOBALS["___mysqli_ston"], $query);

while($row = mysqli_fetch_array($result)){
  $latestprimadonnaactivity = $row[0];
  $pageid = $row[3];
  $pageurl = $row[4];
//echo "<br>$pageurl<br>";

	$latest = new DateTime($latestprimadonnaactivity);
	$now = new DateTime($dt2);

	$diff = $now->diff($latest);

	$monthsGoneBy = (($diff->format('%y') * 12) + $diff->format('%m'));
	//echo "hej-1! " . $monthsGoneBy;
	if ($monthsGoneBy > 0) {
		$query2 = "UPDATE prima_germany SET undersurveillance='false' WHERE pageid='$pageid'";
		mysqli_query($GLOBALS["___mysqli_ston"], $query2);
	}
  	else {
		putStuttgartUnderSurveillanceAndLookForChangesAndOddBehaviourHere($pageid, $pageurl, $subreddit, $tagCategories);
	
	}
	////echo "hej0! ";
	////echo (($diff->format('%y') * 12) + $diff->format('%m')) . " full months difference";

}

echo "<br><br>DONE!";

function stripTrailingSlash($url) {
	if(substr($url, -1) == '/') {
		  $url = substr($url, 0, -1);
	}
	return $url;
}

function putStuttgartUnderSurveillanceAndLookForChangesAndOddBehaviourHere($pageid, $pageurl, $subreddit, $tagCategories) {
	$uri_path = parse_url($pageurl, PHP_URL_PATH);
	$uri_segments = explode('/', $uri_path);
	$subreddit = $uri_segments[2];
	$pagename = $uri_segments[5];
	//echo "subreddit: " . $subreddit . "<br>pagename: " . $pagename . "<br>";
	$ch = curl_init(stripTrailingSlash($pageurl) . ".json");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
	$content = curl_exec($ch);
	curl_close($ch);
	$jsonObj = json_decode($content, false);
	$mainPostId = $jsonObj->data->children[0]->data->id;
	//echo "mainPostId: " . $mainPostId;

    //1. look for new and altered comment texts

    //But first...: 1.a. Put all comments in an array with id->body

    $query = "SELECT * FROM prima_stuttgart WHERE pageid='$pageid'";
    $result = mysqli_query($GLOBALS["___mysqli_ston"], $query);
    //echo "hej1! ";
    $previousStoredWayBackIdToCommentBodyArray = Array();
    while ($row = mysqli_fetch_array($result)) {
        //$pageidAnother = $row[0];
        $commentid = $row[2];
        $commentbody = $row[3];
        $previousStoredWayBackIdToCommentBodyArray[$commentid] = $commentbody;
    }
    // ... Done! 1.b.
    $mintArrayOfIdsToBodiesAndAuthorsAndParentIds = traverseG($jsonObj);
    //echo "length: " . sizeof($mintArrayOfIdsToBodiesAndAuthorsAndParentIds);
    foreach ($mintArrayOfIdsToBodiesAndAuthorsAndParentIds as $id => $mintCommentThatIsNotFromDBButFromTheNet) {
        ////echo "id: " . $id . "<br><br>";
        ////echo "$mintCommentThatIsNotFromDBButFromTheNet: " . $mintCommentThatIsNotFromDBButFromTheNet . "<br><br>";


        if (!array_key_exists($id, $previousStoredWayBackIdToCommentBodyArray)) {
            // Here: (The comment loaded directly from the net ISN'T known in the db)
            // Therefore: Insert it
            if ($pageid !== $id) {
                // Here: (The comment loaded directly from the net ISN'T known in the db) & (The comment IS a comment and not the main post)
                // Therefore: Insert it into db
                $body = $mintCommentThatIsNotFromDBButFromTheNet->body;
                $author = $mintCommentThatIsNotFromDBButFromTheNet->author;
                $query = "INSERT INTO `redditawkward_com`.`prima_stuttgart` (
										`author` ,
										`pageid` ,
										`commentid` ,
										`commentbody`
										)
										VALUES (
										'$author', '$pageid', '$id', '$body' )";
                mysqli_query($GLOBALS["___mysqli_ston"], $query);
                $somethingHappenedInStuttTown = true; // O, something definitely happened in Stuttgart - a whole new comment - woha!
            }
        } else {
            // Here: The comment loaded directly from the net IS known in the db
            // Therefore: See below
            $oldHackyBodyHehehe = $previousStoredWayBackIdToCommentBodyArray[$id];
            $bodyMint = $mintCommentThatIsNotFromDBButFromTheNet->body;
            if ($oldHackyBodyHehehe !== $bodyMint) {
                // Here: (The comment loaded directly from the net IS known in the db) & (Its body was altered since last check from Germany)
                // Therefore: Update body
                //echo "Inspecting this live body (hehe): " . $bodyMint . " old body " . $oldHackyBodyHehehe . "<br><br>";
                //echo "It changed. <br><br>";
                $query = "UPDATE prima_stuttgart SET commentbody='$bodyMint' WHERE pageid='$pageid' AND commentid='$id';";
                mysqli_query($GLOBALS["___mysqli_ston"], $query);
                $somethingHappenedInStuttTown = true;  // Odd! Something happened in Stuttgart, someone changed their comment.
            } else {
                // Here: (The comment loaded directly from the net IS known in the db) & (It was altered since last check from Germany)
                // Therefore: Update body
                ////echo "It didn't change.<br><br>";
                $somethingHappenedInStuttTown = false;  // Nothing ever happens in Stuttgart! -Nor in Düsseldorf!
            }
        }
    }

    

    // Enforce general rule §1:No more than one tag by the same redditor on the same level of the comment tree, i.e. as answer to any given comment.
    foreach ($mintArrayOfIdsToBodiesAndAuthorsAndParentIds as $id => $mintCommentThatIsNotFromDBButFromTheNet) {
        if (strpos($mintCommentThatIsNotFromDBButFromTheNet->body, "comment-tag{") !== false) {
            $redditorInAllThisMess = $mintCommentThatIsNotFromDBButFromTheNet->author;
            $parentCommentId = $mintArrayOfIdsToBodiesAndAuthorsAndParentIds[$mintCommentThatIsNotFromDBButFromTheNet->parent_id];
            $myRepliesToParentComment = Array();
            foreach ($mintArrayOfIdsToBodiesAndAuthorsAndParentIds as $id2 => $mintCommentThatIsNotFromDBButFromTheNet2) {
                if ($mintCommentThatIsNotFromDBButFromTheNet2->parent_id == $parentCommentId) {
                    // Here: Found comment on the same level as mine
                    // Therefore: Be certain it isn't the one already known to the system
                    if ($id2 !== $id) {
                        // Here: Comment isn't identical with the one already known to the system
                        // Therefore: Check if it is indeed redditor, who is the author
                        if ($mintCommentThatIsNotFromDBButFromTheNet2->author == $redditorInAllThisMess) {
                            // Here: Redditor is author
                            // Therefore: Check for existence of Reddit Awkward tag
                            $commentBody = $mintCommentThatIsNotFromDBButFromTheNet2->body;
                            if (strpos($commentBody, "comment-tag{") !== false) {
                                // Here: Conclusion: Redditor used more than oneAwkward Tags on the same level
                                // Therefore: Extract tag and give penalty
                                // Strip text between {}
                                preg_match('#\{(.*?)\}#', $commentBody, $match);
                                $shortHandTag = $match[1];
                                $tag = "comment-tag{" . $shortHandTag . "}";
                                subtractPKarmaConditionally($redditorInAllThisMess, $tag, $pageid, $id2, $subreddit, $pagename, "You used more than one tag in the same spot, i.e. as a reply to the same comment.", -5);
                            }
                        }
                    }
                }
            }
        }
    }

    // Enforce general rule §2: No self-made tags. - and: insert into prima_tag_use
    foreach ($mintArrayOfIdsToBodiesAndAuthorsAndParentIds as $id => $mintCommentThatIsNotFromDBButFromTheNet) {
        if (strpos($mintCommentThatIsNotFromDBButFromTheNet->body, "comment-tag{") !== false) {
            $commentBody = $mintCommentThatIsNotFromDBButFromTheNet->body;
            // Strip text between {}
            preg_match('#\{(.*?)\}#', $commentBody, $match);
            $shortHandTag = $match[1];
			$tag = "comment-tag{" . $shortHandTag . "}";
            if (!array_key_exists($shortHandTag, $tagCategories)) {
                // Here: Unknown tag
                // Therefore: Give penalty
                subtractPKarmaConditionally($mintCommentThatIsNotFromDBButFromTheNet->author, $tag, $pageid, $id, $subreddit, $pagename, "You used a self-made tg.", -100);
            }
			else {
				// Here: Known tag
				// Therefore: Insert it into the very big table prima_tag_use
				$sql = "SELECT * FROM prima_tag_use WHERE pageid='$pageid' AND commentid='$id'";
				$result = mysqli_query($GLOBALS["___mysqli_ston"], $sql);
				$count = mysqli_num_rows($result);
				//echo "hej";
				if ($count == 0) {
					//echo "dav";
					$redditor = $mintCommentThatIsNotFromDBButFromTheNet->author;
					$dt2=date("Y-m-d H:i:s");
					$t = time();
$sql = "INSERT INTO  `redditawkward_com`.`prima_tag_use` (
`redditor` ,
`pageid` ,
`commentid` ,
`subreddit` ,
`when_detected_utc` ,
`when_detected` ,
`tag`
)
VALUES (
'$redditor',  '$pageid',  '$id',  '$subreddit',  '$t',  '$dt2',  '$tag'
);";
					//echo $sql;
					mysqli_query($GLOBALS["___mysqli_ston"], $sql);
				}
			}
        }
    }

    // Enforce general rule §3: Tags can be either: mayNotStandAlone, mustStandAlone or noStandAloneRule
    foreach ($mintArrayOfIdsToBodiesAndAuthorsAndParentIds as $id => $mintCommentThatIsNotFromDBButFromTheNet) {
        if (strpos($mintCommentThatIsNotFromDBButFromTheNet->body, "comment-tag{") !== false) {
            $commentBody = $mintCommentThatIsNotFromDBButFromTheNet->body;
            // Strip text between {}
            preg_match('#\{(.*?)\}#', $commentBody, $match);
            $shortHandTag = $match[1];
            $tag = "comment-tag{" . $shortHandTag . "}";
            $isStandAlone = !hasMoreWordsBesidesTheTagItselfDude(commentBody, $tag);
            if ($mustBeStandAloneTags[$shortHandTag] == "mayNotStandAlone") {
                // Here: Tag may not stand alone
                // Therefore: If it does, give penalty
                if ($isStandAlone) {
                    subtractPKarmaConditionally($mintCommentThatIsNotFromDBButFromTheNet->author, $tag, $pageid, $id, $subreddit, $pagename, "Tag $tag is not allowed to stand alone.", -10);
                }
            } else if ($mustBeStandAloneTags[$shortHandTag] == "mustStandAlone") {
                // Here: Tag must stand alone
                // Therefore: If it doesn't, give penalty
                if (!$isStandAlone) {
                    subtractPKarmaConditionally($mintCommentThatIsNotFromDBButFromTheNet->author, $tag, $pageid, $id, $subreddit, $pagename, "Tag $tag must stand alone.", -10);
                }
            }
        }
    }

		
	// General rule §4 implemented below


	// Enforce general rule §5: Redditors can't direct any tags, besides comment-tag{no.i.mean.it} towards their own comments.
    foreach ($mintArrayOfIdsToBodiesAndAuthorsAndParentIds as $id => $mintCommentThatIsNotFromDBButFromTheNet) {
        if (strpos($mintCommentThatIsNotFromDBButFromTheNet->body, "comment-tag{") !== false) {
			$redditor = $mintCommentThatIsNotFromDBButFromTheNet->author;
			$wantedSecondPersonWithAlleFieldsInHere = $mintArrayOfIdsToBodiesAndAuthorsAndParentIds[$mintCommentThatIsNotFromDBButFromTheNet->parent_id];
			if ($redditor === $wantedSecondPersonWithAlleFieldsInHere) {
				// Here: Redditor is responding to himself
				// Test if he's using the comment-tag{no.i.mean.it} tag
				if (strpos($mintCommentThatIsNotFromDBButFromTheNet->body, "comment-tag{no.i.mean.it")      ===       false) {
					// Here: He is not using the comment-tag{no.i.mean.it} tag
					// Therefore: Give penalty
					subtractPKarmaConditionally($mintCommentThatIsNotFromDBButFromTheNet->author, $tag, $pageid, $id, $subreddit, $pagename, "General Rule §5: §5 Nearly all Awkward tags are social in nature. Redditors can't direct any tags, besides comment-tag{no.i.mean.it}, towards their own comments.");
				}
			}
        }
    }




	// look for "comment-tag{i.am.one.of.the.strangest.people.youll.ever.meet}"
    foreach ($mintArrayOfIdsToBodiesAndAuthorsAndParentIds as $id => $mintCommentThatIsNotFromDBButFromTheNet) {
        if (strpos($mintCommentThatIsNotFromDBButFromTheNet->body, "comment-tag{i.am.one.of.the.strangest.people.youll.ever.meet}") !== false) {
			givePKarmaForUseOfTagConditionally("comment-tag{i.am.one.of.the.strangest.people.youll.ever.meet}", $mTagAgentRedditorName, $pageid, $id, $subreddit, $pagename);
		}
	}



	// look for "comment-tag{er.hi.what.kind.of.strange.presentation.is.that}"
    foreach ($mintArrayOfIdsToBodiesAndAuthorsAndParentIds as $id => $mintCommentThatIsNotFromDBButFromTheNet) {
        if (strpos($mintCommentThatIsNotFromDBButFromTheNet->body, "comment-tag{er.hi.what.kind.of.strange.presentation.is.that}") !== false) {
			$mTagAgentRedditorName = $mintCommentThatIsNotFromDBButFromTheNet->author;
			$wantedSecondPersonWithAlleFieldsInHere = $mintArrayOfIdsToBodiesAndAuthorsAndParentIds[$mintCommentThatIsNotFromDBButFromTheNet->parent_id];
			if ($mintCommentThatIsNotFromDBButFromTheNet->author === $wantedSecondPersonWithAlleFieldsInHere->author) {
				// Whoops. Redditor can't say this to himself/herself
				// Give penalty
				subtractPKarmaConditionally($mTagAgentRedditorName, "comment-tag{er.hi.what.kind.of.strange.presentation.is.that}", $pageid, $id, $subreddit, $pagename, "Tag should only be used in reply to comment-tag{i.am.one.of.the.strangest.people.youll.ever.meet}", -5);
			}
			else {
				if (strpos($wantedSecondPersonWithAlleFieldsInHere->body, "comment-tag{i.am.one.of.the.strangest.people.youll.ever.meet}") !== false) {
					givePKarmaForUseOfTagConditionally("comment-tag{er.hi.what.kind.of.strange.presentation.is.that}", $mTagAgentRedditorName, $pageid, $id, $subreddit, $pagename);
					
				}
				else {
					subtractPKarmaConditionally($mTagAgentRedditorName, "comment-tag{er.hi.what.kind.of.strange.presentation.is.that}", $pageid, $idAndRedditorAndMoreDude->id, $subreddit, $pagename, "Tag should only be used in reply to comment-tag{i.am.one.of.the.strangest.people.youll.ever.meet}", -5);
				}
			}
		}
	}



	// look for "comment-tag{watch.me.playing.soccer.with.myself.in.this.video}"
    foreach ($mintArrayOfIdsToBodiesAndAuthorsAndParentIds as $id => $mintCommentThatIsNotFromDBButFromTheNet) {
        if (strpos($mintCommentThatIsNotFromDBButFromTheNet->body, "comment-tag{watch.me.playing.soccer.with.myself.in.this.video}") !== false) {
            $mTagAgentRedditorName = $mintCommentThatIsNotFromDBButFromTheNet->author;
			$body = strtolower($mintCommentThatIsNotFromDBButFromTheNet->body);
			if (strpos($body, "youtube") === false) {
				// Here: No YouTube links in here
				// Therefore: Give penalty
			}
			else {
				// Here: Found YouTube word
				// Therefore: Give tag use award
				givePKarmaConditionally("comment-tag{watch.me.playing.soccer.with.myself.in.this.video}", $mTagAgentRedditorName, $pageid, $id, $subreddit, $pagename, "For playing soccer!", 100);
			}
		}
	}





	// look for "comment-tag{interesting.will.write.more.in.a.few.days.time}"
    foreach ($mintArrayOfIdsToBodiesAndAuthorsAndParentIds as $id => $mintCommentThatIsNotFromDBButFromTheNet) {
        if (strpos($mintCommentThatIsNotFromDBButFromTheNet->body, "comment-tag{interesting.will.write.more.in.a.few.days.time}") !== false) {
            $mTagAgentRedditorName = $mintCommentThatIsNotFromDBButFromTheNet->author;
            $wantedSecondPersonWithAlleFieldsInHere = $mintArrayOfIdsToBodiesAndAuthorsAndParentIds[$mintCommentThatIsNotFromDBButFromTheNet->parent_id];
			$idOfSecondPerson = $wantedSecondPersonWithAlleFieldsInHere->id;
			$idAndRedditorArrayOfVeryDifferentDirectChildrenWhoCouldBeMe = giveMeTheNamesOfAllApplesOnTheBranchWithThisCommentAsAxePointHmmm($jsonObj, $idOfSecondPerson);
			$answeredCorrectly = false;
			foreach($idAndRedditorArrayOfVeryDifferentDirectChildrenWhoCouldBeMe as $idAndRedditorAndMoreDude) {
				if ($idAndRedditorAndMoreDude->id !== $id) {
					// Here: Found other comment which is a reply to parent comment
					// Therefore: Check if author is me, i.e. then the comment is maybe valide (if it's within the timespan)
					//echo "<br>a";
					if ($idAndRedditorAndMoreDude->author === $mTagAgentRedditorName) {
						// Here: Found comment by redditor
						// Therefore: Check if redditor has answered in the appropriate timespan in the branch of the parent comment
						//echo "<br>b";
						$timeUTCTagUsed = $mintCommentThatIsNotFromDBButFromTheNet->utc;
						$timeOfMyReply = $mintArrayOfIdsToBodiesAndAuthorsAndParentIds[$idAndRedditorAndMoreDude->id]->utc;
						$hoursGoneBy = (($timeOfMyReply - $timeUTCTagUsed) / (60 * 60));
                        if ($hoursGoneBy < 24) {
							// Here: Redditor answered too early
							// Therefore: Give penalty
							//echo "<br>c";
							subtractPKarmaConditionally($mTagAgentRedditorName, "comment-tag{interesting.will.write.more.in.a.few.days.time}", $pageid, $idAndRedditorAndMoreDude->id, $subreddit, $pagename, "Your answer came within 24 hours. You should have waited a little more.", -5);
                        } else if ($hoursGoneBy > 24 * 4) {
                            // Here: Expired
                            // Therefore: Give penalty
							//echo "<br>d";
							subtractPKarmaConditionally($mTagAgentRedditorName, "comment-tag{interesting.will.write.more.in.a.few.days.time}", $pageid, $idAndRedditorAndMoreDude->id, $subreddit, $pagename, "Your came too late.", -5);
                        }
						else {
							// Here: Redditor answered correctly
							// Therefore: Give Awkward Karma gift
							//echo "<br>e";
							$answeredCorrectly = true;
							givePKarmaConditionally("comment-tag{interesting.will.write.more.in.a.few.days.time}", $mTagAgentRedditorName, $pageid, $idAndRedditorAndMoreDude->id, $subreddit, $pagename, "You answered " . $wantedSecondPersonWithAlleFieldsInHere . " in time after thinking about the answer for appr. " . floor($hoursGoneBy) . " hours.", +50);
						}
					}
				}
			}
			if (!$answeredCorrectly) {
				// Here: Redditor didn't answer yet, as he/she said he/she would.
				// Therefore: Check if the time has run out
				//echo "<br>f";
				$timeUTCTagUsed = $mintCommentThatIsNotFromDBButFromTheNet->utc;
				$hoursGoneBy = ((time() - $timeUTCTagUsed) / (60 * 60));
				if ($hoursGoneBy < 24) {
					// Too early
					// Give penalty
					//echo "<br>g";
					subtractPKarmaConditionally($mTagAgentRedditorName, "comment-tag{interesting.will.write.more.in.a.few.days.time}", $pageid, $id, $subreddit, $pagename, "You said to " . $wantedSecondPersonWithAlleFieldsInHere->author . " you would answer in a few days time, but you answered within 24 hours.", -100);
				}
				if ($hoursGoneBy > 24 * 4) {
					// Expired
					// Give penalty
					//echo "<br>g";
					subtractPKarmaConditionally($mTagAgentRedditorName, "comment-tag{interesting.will.write.more.in.a.few.days.time}", $pageid, $id, $subreddit, $pagename, "You said to " . $wantedSecondPersonWithAlleFieldsInHere->author . " you would answer in a few days time, but you didn\'t.", -100);
				}
			}
		}
	}
			



    // look for "comment-tag{youre.being.overly.ironic.and.are.violating.the.rules}"
    foreach ($mintArrayOfIdsToBodiesAndAuthorsAndParentIds as $id => $mintCommentThatIsNotFromDBButFromTheNet) {
        if (strpos($mintCommentThatIsNotFromDBButFromTheNet->body, "comment-tag{youre.being.overly.ironic.and.are.violating.the.rules}") !== false) {
            $mTagAgentRedditorName = $mintCommentThatIsNotFromDBButFromTheNet->author;
            $wantedSecondPersonWithAlleFieldsInHere = $mintArrayOfIdsToBodiesAndAuthorsAndParentIds[$mintCommentThatIsNotFromDBButFromTheNet->parent_id];
			if (strpos($wantedSecondPersonWithAlleFieldsInHere->body, "comment-tag{")          ===           false) {
				// Here: Disobeyed §1 Must be a reply to a comment with a Reddit Awkward tag in it.
				// Therefore: Give penalty
				subtractPKarmaConditionally($mintCommentThatIsNotFromDBButFromTheNet->author, "comment-tag{youre.being.overly.ironic.and.are.violating.the.rules}", $pageid, $id, $subreddit, $pagename, "§1 Must be a reply to a comment with a Reddit Awkward tag in it.", -5);
			}
		}
	}




    // look for "comment-tag{i.consider.this.comment.definitive.and.consider.any.reply.inappropriate}"
    foreach ($mintArrayOfIdsToBodiesAndAuthorsAndParentIds as $id => $mintCommentThatIsNotFromDBButFromTheNet) {
        if (strpos($mintCommentThatIsNotFromDBButFromTheNet->body, "comment-tag{i.consider.this.comment.definitive.and.consider.any.reply.inappropriate}") !== false) {
            $mTagAgentRedditorName = $mintCommentThatIsNotFromDBButFromTheNet->author;
			$appleIds = giveMeTheNamesOfAllApplesOnTheBranchWithThisCommentAsAxePointHmmm($jsonObj, $id);
			//echo "<br>cid: ".$id ;
			//echo "<br>appleIds size: ". sizeof($appleIds);
			$violatedMyOwnRule = false;
			foreach ($appleIds as $appleId) {
				$thirdPersonName = $mintArrayOfIdsToBodiesAndAuthorsAndParentIds[$appleId]->author;
				if ($mintArrayOfIdsToBodiesAndAuthorsAndParentIds[$appleId]->author === $mTagAgentRedditorName) {
					// Here: Tag agaent violated his own rule!
					// Give penalty
					$violatedMyOwnRule = true;
					echo "1: " . $appleId;
					subtractPKarmaConditionally($thirdPersonName, "comment-tag{i.consider.this.comment.definitive.and.consider.any.reply.inappropriate}", $pageid, $appleId, $subreddit, $pagename, "You previously used comment-tag{i.consider.this.comment.definitive.and.consider.any.reply.inappropriate} and should therefore not reply or participate in discussions following this tag.", -30);
				}
				else {
					echo "2: " . $appleId;
					subtractPKarmaConditionally($thirdPersonName, "comment-tag{i.consider.this.comment.definitive.and.consider.any.reply.inappropriate}", $pageid, $appleId, $subreddit, $pagename, "This was used earlier here. You should therefore not reply or participate in discussions following this tag.", -5);
				}
			}
			if (!$violatedMyOwnRule) {
				givePKarmaConditionally("comment-tag{i.consider.this.comment.definitive.and.consider.any.reply.inappropriate}", $mTagAgentRedditorName, $pageid, $id, $subreddit, $pagename, "You have received Awkward Karma for using this tag.", 10);
			}
		}
	}




	// look for comment-tag{thanks}, comment-tag{explanation.why.i.was.angry} and comment-tag{i.was.being.careless}
    foreach ($mintArrayOfIdsToBodiesAndAuthorsAndParentIds as $id => $mintCommentThatIsNotFromDBButFromTheNet) {
        if ((strpos($mintCommentThatIsNotFromDBButFromTheNet->body, "comment-tag{thanks}") !== false) || (strpos($mintCommentThatIsNotFromDBButFromTheNet->body, "comment-tag{explanation.why.i.was.angry}") !== false) || (strpos($mintCommentThatIsNotFromDBButFromTheNet->body, "comment-tag{i.was.being.careless}") !== false)) {
			$commentBody = $mintCommentThatIsNotFromDBButFromTheNet->body;
			$wantedSecondPersonWithAlleFieldsInHere = $mintArrayOfIdsToBodiesAndAuthorsAndParentIds[$mintCommentThatIsNotFromDBButFromTheNet->parent_id];
			$parentCommentBody = $wantedSecondPersonWithAlleFieldsInHere->body;
			if (strpos($parentCommentBody, 'comment-tag{no.problem}')         ===     false or strpos($parentCommentBody, 'comment-tag{dont.mind.its.ok.lets.move.on}')         ===             false or strpos($parentCommentBody, 'comment-tag{its.fine.i.consider.the.case.closed}')           ===          false)
			{
				// Here: Tag is not directed towards an overbearing act of kindness
				// Therefore: Give penalty
				// Strip text between {}
                preg_match('#\{(.*?)\}#', $commentBody, $match);
                $shortHandTag = $match[1];
                $tag = "comment-tag{" . $shortHandTag . "}";
				subtractPKarmaConditionally($redditorInAllThisMess, $tag, $pageid, $id, $subreddit, $pagename, "You misused this tag. It should be directed against 'an overbearing act' i.e. either towards comment-tag{no.problem}, comment-tag{dont.mind.its.ok.lets.move.on} or comment-tag{its.fine.i.consider.the.case.closed}.", -5);
			}
		}
	}

	
    // look for "comment-tag{i.will.not.reply.and.expect.apology}"
    foreach ($mintArrayOfIdsToBodiesAndAuthorsAndParentIds as $id => $mintCommentThatIsNotFromDBButFromTheNet) {
        if (strpos($mintCommentThatIsNotFromDBButFromTheNet->body, "comment-tag{i.will.not.reply.and.expect.apology}") !== false) {
            $mTagAgentRedditorName = $mintCommentThatIsNotFromDBButFromTheNet->author;
            $wantedSecondPersonWithAlleFieldsInHere = $mintArrayOfIdsToBodiesAndAuthorsAndParentIds[$mintCommentThatIsNotFromDBButFromTheNet->parent_id];
            if (needsToApologize($wantedSecondPersonWithAlleFieldsInHere->author, $mintCommentThatIsNotFromDBButFromTheNet->author)) {
                // Here: Rule §2 disobeyed: Conflict already begun in the past.
                // Therefore: Give penalty to this redditor
                givePKarmaConditionally("comment-tag{i.will.not.reply.and.expect.apology}", $mTagAgentRedditorName, $pageid, $id, $subreddit, $pagename, "You have been given a penalty for using comment-tag{guarded.apology} more than one time towards the same user before giving him/her a chance to apologize", -300);
            } else {
                // Here: Redditor didn't use comment-tag{i.will.not.reply.and.expect.apology} towards second person (without getting an apology). (Rule §1)
                // Therefore: Check if redditor obeys Rule §2
                if (strpos($wantedSecondPersonWithAlleFieldsInHere->body, "comment-tag{") !== false) {
                    // Here: Redditor is replying to a comment with at least one Awkward Tag, violating Rule §2
                    // Therefore: Give penalty.
                    givePKarmaConditionally("comment-tag{i.will.not.reply.and.expect.apology}", $mTagAgentRedditorName, $pageid, $id, $subreddit, $pagename, "You have been given a penalty for replying with comment-tag{i.will.not.reply.and.expect.apology} to a comment with at least one Awkward tag. (Rule §1)", -10);
                } else {
                    $idOfParentRedditor = $mintCommentThatIsNotFromDBButFromTheNet->parent_id;
                    $culpritName = $mintArrayOfIdsToBodiesAndAuthorsAndParentIds[$idOfParentRedditor]->author;

                    needApology($culpritName, $mTagAgentRedditorName, $subreddit, $pageid, $id);

                    createNotification($pageid, $id, $culpritName, $subreddit, $pagename, "$mTagAgentRedditorName slammed the door at you. You need to apologize using either comment-tag{i.apologize} or comment-tag{guarded.apology}.", "comment-tag{i.will.not.reply.and.expect.apology}", "mustApologizeIfOtherExpectsIt");
                }
            }
			$appleIds = giveMeTheNamesOfAllApplesOnTheBranchWithThisCommentAsAxePointHmmm($jsonObj, $id);
            foreach ($appleIds as $appleId) {
                //echo "<br>appleId: $appleId  author:" . $mintArrayOfIdsToBodiesAndAuthorsAndParentIds[$appleId]->author;
                if ($mTagAgentRedditorName !== $mintArrayOfIdsToBodiesAndAuthorsAndParentIds[$appleId]->author && $wantedSecondPersonWithAlleFieldsInHere->author !== $mintArrayOfIdsToBodiesAndAuthorsAndParentIds[$appleId]->author) {
                    // Here: §5 No third person must answer this comment (Penalty: -10)
                    // Give penalty
                    subtractPKarmaConditionally($mintArrayOfIdsToBodiesAndAuthorsAndParentIds[$appleId]->author, "comment-tag{i.will.not.reply.and.expect.apology}", $pageid, $appleId, $subreddit, $pagename, "You should not intrude in a bilateral conflict.", -5);
                }
            }
        }
    }

    // look for "comment-tag{youre.welcome}"
    foreach ($mintArrayOfIdsToBodiesAndAuthorsAndParentIds as $id => $mintCommentThatIsNotFromDBButFromTheNet) {
        if (strpos($mintCommentThatIsNotFromDBButFromTheNet->body, "comment-tag{youre.welcome}") !== false) {
            $mTagAgentRedditorName = $mintCommentThatIsNotFromDBButFromTheNet->author;
            $wantedSecondPersonWithAlleFieldsInHere = $mintArrayOfIdsToBodiesAndAuthorsAndParentIds[$mintCommentThatIsNotFromDBButFromTheNet->parent_id];
            givePKarmaConditionally("comment-tag{youre.welcome}", $mTagAgentRedditorName, $pageid, $id, $subreddit, $pagename, "You have received Awkward Karma for using this tag.", 10);
            givePKarmaConditionally("comment-tag{youre.welcome}", $wantedSecondPersonWithAlleFieldsInHere->author, $pageid, $wantedSecondPersonWithAlleFieldsInHere->id, $subreddit, $pagename, "You received Awkward Karma because $mTagAgentRedditorName is polite against you.", 5);
        }
    }

    // look for "comment-tag{thanks}"
    foreach ($mintArrayOfIdsToBodiesAndAuthorsAndParentIds as $id => $mintCommentThatIsNotFromDBButFromTheNet) {
        if (strpos($mintCommentThatIsNotFromDBButFromTheNet->body, "comment-tag{thanks}") !== false) {
            $mTagAgentRedditorName = $mintCommentThatIsNotFromDBButFromTheNet->author;
            $wantedSecondPersonWithAlleFieldsInHere = $mintArrayOfIdsToBodiesAndAuthorsAndParentIds[$mintCommentThatIsNotFromDBButFromTheNet->parent_id];
            givePKarmaConditionally("comment-tag{thanks}", $mTagAgentRedditorName, $pageid, $id, $subreddit, $pagename, "You have received Awkward Karma for using this tag.", 10);
            givePKarmaConditionally("comment-tag{thanks}", $wantedSecondPersonWithAlleFieldsInHere->author, $pageid, $wantedSecondPersonWithAlleFieldsInHere->id, $subreddit, $pagename, "You received Awkward Karma because $mTagAgentRedditorName is thankful towards you.", 5);
        }
    }


    // look for "comment-tag{i.am.glad.you.said.that.to.me}"
    foreach ($mintArrayOfIdsToBodiesAndAuthorsAndParentIds as $id => $mintCommentThatIsNotFromDBButFromTheNet) {
        if (strpos($mintCommentThatIsNotFromDBButFromTheNet->body, "comment-tag{i.am.glad.you.said.that.to.me}") !== false) {
            $mTagAgentRedditorName = $mintCommentThatIsNotFromDBButFromTheNet->author;
            $wantedSecondPersonWithAlleFieldsInHere = $mintArrayOfIdsToBodiesAndAuthorsAndParentIds[$mintCommentThatIsNotFromDBButFromTheNet->parent_id];
            givePKarmaConditionally("comment-tag{i.am.glad.you.said.that.to.me}", $mTagAgentRedditorName, $pageid, $id, $subreddit, $pagename, "You have received Awkward Karma for using this tag.", 10);
            givePKarmaConditionally("comment-tag{i.am.glad.you.said.that.to.me}", $wantedSecondPersonWithAlleFieldsInHere->author, $pageid, $id, $subreddit, $pagename, "You received Awkward Karma because $mTagAgentRedditorName was glad of the inspiration you gave him/her!", 5);
        }
    }


    // look for "comment-tag{awkward}"
    foreach ($mintArrayOfIdsToBodiesAndAuthorsAndParentIds as $id => $mintCommentThatIsNotFromDBButFromTheNet) {
        if (strpos($mintCommentThatIsNotFromDBButFromTheNet->body, 'comment-tag{awkward}') !== false) {
            $mTagAgentRedditorName = $mintCommentThatIsNotFromDBButFromTheNet->author;
            $wantedSecondPersonWithAlleFieldsInHere = $mintArrayOfIdsToBodiesAndAuthorsAndParentIds[$mintCommentThatIsNotFromDBButFromTheNet->parent_id];
            givePKarmaConditionally("comment-tag{thanks}", $mTagAgentRedditorName, $pageid, $id, $subreddit, $pagename, "You have received Awkward Karma for using this tag.", 10);
            if (strpos($wantedSecondPersonWithAlleFieldsInHere->body, 'comment-tag{') !== false) {
                // Here: Second person's comment has ra tag. = violation!
                // Therefore: Give penalty
                $motivation = "You have received a penalty of -5 for using comment-tag{awkward} against another Reddit Awkward tag.";
                subtractPKarmaForPlainAwkwardTagViolationConditionally($mintCommentThatIsNotFromDBButFromTheNet->author, $pageid, $id, $subreddit, $pagename, $motivation, -5);
            } else {
                if (!hasMoreWordsBesidesTheTagItselfDude($mintCommentThatIsNotFromDBButFromTheNet->body, "comment-tag{awkward}")) {
                    // Here: tag is stand-alone the way it sholdn't be
                    // Therefore: Give penalty!
                    $motivation = "You have received a penalty of -5 for using comment-tag{awkward} as stand-alone tag, which it isn't.";
                    subtractPKarmaForPlainAwkwardTagViolationConditionally($mintCommentThatIsNotFromDBButFromTheNet->author, $pageid, $id, $subreddit, $pagename, $motivation, -5);
                } else {
                    if (getWordCountBesidesTheTagItselfDude($mintCommentThatIsNotFromDBButFromTheNet->body, "comment-tag{awkward}") < 20) {
                        // Here: Less than 20 words
                        $motivation = "comment-tag{awkward} tag violation: §3 Must be precluded by a text with no less than 20 words.";
                        subtractPKarmaForPlainAwkwardTagViolationConditionally($mintCommentThatIsNotFromDBButFromTheNet->author, $pageid, $id, $subreddit, $pagename, $motivation, -5);
                    } else {
                        // Here: No RA tag in second person's comment
                        // Therefore: Everything is ok.
                        givePKarmaForUseOfTagConditionally("comment-tag{awkward}", $mTagAgentRedditorName, $pageid, $id, $subreddit, $pagename);
                    }
                }
            }
        }
    }


    // look for "comment-tag{waits.for.anyone}"
    // Jeg må sige følgende, idet jeg ville blive glad for et hvilket som helst svar så hurtigt som muligt.
    foreach ($mintArrayOfIdsToBodiesAndAuthorsAndParentIds as $id => $mintCommentThatIsNotFromDBButFromTheNet) {
        if (strpos($mintCommentThatIsNotFromDBButFromTheNet->body, 'comment-tag{waits.for.anyone}') !== false) {
            $cursoryRedditorWaiting = $mintCommentThatIsNotFromDBButFromTheNet->author;
            givePKarmaForUseOfTagConditionally("comment-tag{waits.for.anyone}", $cursoryRedditorWaiting, $pageid, $id, $subreddit, $pagename);

            // Here: $mintCommentThatIsNotFromDBButFromTheNet->body has comment-tag{waits.for.anyone] tag
            //echo "Found comment with comment-tag{waits.for.anyone]: " . $id . "<br><br>";
            $idAndTimeAndAuthorAndUTCArrayOfOldestChildWhoIsNtMe = anybodyOutThereWhoHasMeAsParentHeAskedKnowinglyNoActuallyINeedTheOLDESTOneOfMyKidsAndNotMyselfByTheWay($jsonObj, $id, $mintCommentThatIsNotFromDBButFromTheNet->author);
            if ($idAndTimeAndAuthorAndUTCArrayOfOldestChildWhoIsNtMe[0]->id === "") {
                // Here: ($mintCommentThatIsNotFromDBButFromTheNet->body has comment-tag{waits.for.anyone]-tag) & ($mintCommentThatIsNotFromDBButFromTheNet has no children)
                //echo "still unanswered: " . $id;
                $query = "SELECT * FROM prima_taga_unanswered WHERE commentpageid='$pageid' AND commentid='$id'";
                $result3 = mysqli_query($GLOBALS["___mysqli_ston"], $query);
                $count3 = mysqli_num_rows($result3);
                if ($count3 == 0) {
                    // Here: ($mintCommentThatIsNotFromDBButFromTheNet->body has comment-tag{waits.for.anyone]-tag) & ($mintCommentThatIsNotFromDBButFromTheNet has no children) & ($mintCommentThatIsNotFromDBButFromTheNet is not registered in db)
                    // Therefore: Register $mintCommentThatIsNotFromDBButFromTheNet in db
                    $backwiseOriginalPoster = $mintCommentThatIsNotFromDBButFromTheNet->author;
                    $dt2 = date("Y-m-d H:i:s");
                    $query = "INSERT INTO `redditawkward_com`.`prima_taga_unanswered` (`redditor`, `commentpageid`, `whentagfirstdetected`, `commentid`, `finallyrepliedbyredditor`, `utc_created`, `lastevaluatedingerman`) VALUES ('$backwiseOriginalPoster', '$pageid', '$dt2', '$id', null, '0', '$dt2');";
                    mysqli_query($GLOBALS["___mysqli_ston"], $query);
                } else {
                    // Here: ($mintCommentThatIsNotFromDBButFromTheNet->body has comment-tag{waits.for.anyone]-tag) & ($mintCommentThatIsNotFromDBButFromTheNet has no children) & ($mintCommentThatIsNotFromDBButFromTheNet is already in db)
                    // Therefore: Touch row in db
                    $dt2 = date("Y-m-d H:i:s");
                    $query = "UPDATE prima_taga_unanswered SET lastevaluatedingerman='$dt2' WHERE commentpageid='$pageid' AND commentid='$id'";
                    mysqli_query($GLOBALS["___mysqli_ston"], $query);
                }
            } else {
                // Here: ($mintCommentThatIsNotFromDBButFromTheNet->body has comment-tag{waits.for.anyone]-tag) & ($mintCommentThatIsNotFromDBButFromTheNet HAS oldest child, who isn't me)
                // Therefore: Find out if it is recorded in prima_taga_unanswered
                //echo "oldestReplyToQuestionInDireNeedOfAnyAnswer: (" . $id . ") oldest child id:" . $idAndTimeAndAuthorAndUTCArrayOfOldestChildWhoIsNtMe[0]->id . " time:" . $idAndTimeAndAuthorAndUTCArrayOfOldestChildWhoIsNtMe[1]->utc . " author: " . $idAndTimeAndAuthorAndUTCArrayOfOldestChildWhoIsNtMe[0]->author . "<br><br>";

                $backwiseOriginalPoster = $mintCommentThatIsNotFromDBButFromTheNet->author;
                $answeredAtThisUTCTime = $idAndTimeAndAuthorAndUTCArrayOfOldestChildWhoIsNtMe[0]->utc;
                $kindAnsweringRedditor = $idAndTimeAndAuthorAndUTCArrayOfOldestChildWhoIsNtMe[0]->author;

                $query = "SELECT * FROM prima_taga_unanswered WHERE commentpageid='$pageid' AND commentid='$id'";
                $result3 = mysqli_query($GLOBALS["___mysqli_ston"], $query);
                $row3 = mysqli_fetch_row($result3);
                $kindAnswererMightBeThere = $row3[4];
                givePKarmaForTagWaitsForAnyPersonConditionally($kindAnsweringRedditor, $answeredAtThisUTCTime, $backwiseOriginalPoster, $pageid, $id, $subreddit, $pagename);
                giveRKarmaForUseOfTagConditionally("comment-tag{waits.for.anyone}", $kindAnsweringRedditor, $backwiseOriginalPoster, 1, $pageid, $id, $subreddit, $pagename);
                $count3 = mysqli_num_rows($result3);
                if ($count3 > 1) {
                    // 
                } else if ($count3 == 1) {
                    // Here: ($mintCommentThatIsNotFromDBButFromTheNet->body has comment-tag{waits.for.anyone]-tag) & ($mintCommentThatIsNotFromDBButFromTheNet HAS oldest child, who isn't me) & (IS recorded in db)
                    // Therefore: Update and give pKarma
                    if (!$kindAnswererMightBeThere) {
                        // Here: ($mintCommentThatIsNotFromDBButFromTheNet->body has comment-tag{waits.for.anyone]-tag) & ($mintCommentThatIsNotFromDBButFromTheNet HAS oldest child, who isn't me) & (IS recorded in db) & (row has not already been given pKarma for)
                        // Therefor: Update and awart pKarma!

                        //if ($backwiseOriginalPoster !== $kindAnsweringRedditor) {       <---NOT NECESSARY!
                        $dt2 = date("Y-m-d H:i:s");
                        $query = "UPDATE prima_taga_unanswered SET finallyrepliedbyredditor='$kindAnsweringRedditor', utc_created='$answeredAtThisUTCTime', lastevaluatedingerman='$dt2' WHERE commentpageid='$pageid' AND commentid='$id'";
                        mysqli_query($GLOBALS["___mysqli_ston"], $query);

                        //}
                    }
                } else if ($count3 == 0) {
                    // Here: ($mintCommentThatIsNotFromDBButFromTheNet->body has comment-tag{waits.for.anyone]-tag) & ($mintCommentThatIsNotFromDBButFromTheNet HAS oldest child, who isn't me) & (IS NOT recorded in db)
                    // Therefore: Insert and give pKarma
                    $dt2 = date("Y-m-d H:i:s");
                    $query = "INSERT INTO `redditawkward_com`.`prima_taga_unanswered` (`redditor`, `commentpageid`, `whentagfirstdetected`, `commentid`, `finallyrepliedbyredditor`, `utc_created`, `lastevaluatedingerman`) VALUES ('$backwiseOriginalPoster', '$pageid', '$dt2', '$id', '$kindAnsweringRedditor', '$answeredAtThisUTCTime', '$dt2');";
                    //echo "<br><br>QUERY: $query: $dt2<br><br>";
                    mysqli_query($GLOBALS["___mysqli_ston"], $query);

                    
                }
            }
        }
    }


    // look for "comment-tag{waits.for.your.reply.only}"
    // Det ville glæde mig, hvis du så ville svare på følgende, idet jeg desværre ser mig nødsaget til at frafiltrere og se mig vred på andre der kommenterer mit indlæg:
    foreach ($mintArrayOfIdsToBodiesAndAuthorsAndParentIds as $id => $mintCommentThatIsNotFromDBButFromTheNet) {
        if (strpos($mintCommentThatIsNotFromDBButFromTheNet->body, 'comment-tag{waits.for.your.reply.only}') !== false) {
            $cursoryRedditorWaiting = $mintCommentThatIsNotFromDBButFromTheNet->author;
            givePKarmaForUseOfTagConditionally("comment-tag{waits.for.your.reply.only}", $cursoryRedditorWaiting, $pageid, $id, $subreddit, $pagename);

            // Here: $mintCommentThatIsNotFromDBButFromTheNet->body has comment-tag{waits.for.your.reply.only]-tag
            //echo "<br><br>Found comment with comment-tag{waits.for.your.reply.only]: " . $id . "<br><br>";
            $wantedSecondPersonWithAlleFieldsInHere = $mintArrayOfIdsToBodiesAndAuthorsAndParentIds[$mintCommentThatIsNotFromDBButFromTheNet->parent_id];
            $wantedAnswerersRedditorName = $wantedSecondPersonWithAlleFieldsInHere->author;
            //echo "<br><br>wantedAnswerersRedditorName: $wantedAnswerersRedditorName";
            $query = "SELECT * FROM prima_tagc_status WHERE commentpageid='$pageid' AND commentidexpectingdirectanswerfromspecificredditor='$id'";
            $result3 = mysqli_query($GLOBALS["___mysqli_ston"], $query);
            $count3 = mysqli_num_rows($result3);
            if ($count3 == 0) {
                // Here: There's no record in the db of this comment-tag{waits.for.your.reply.only] tag
                // Therefore: Create it in the db, clearly indicating that it hasn't been replied to, as of yet (It might be, but we'll find out later down...)
                // Also: Create notification for second person of this request
                $cursoryOriginalBackwiserPoster = $mintCommentThatIsNotFromDBButFromTheNet->parent_id;
                $cursoryRedditorWaiting = $mintCommentThatIsNotFromDBButFromTheNet->author;

                $query = "INSERT INTO `redditawkward_com`.`prima_tagc_status` (`commentpageid`, `commentidexpectingdirectanswerfromspecificredditor`, `expectedanswererredditorandoriginalbackwiseredditor`, `redditorwaitingfordirectanswerfromspecificredditor`, `pkarmadistributed`) VALUES ('$pageid', '$id', '$wantedAnswerersRedditorName', '$cursoryRedditorWaiting', 'false');";
                mysqli_query($GLOBALS["___mysqli_ston"], $query);
                createCTagNotificationForSecondPerson($pageid, $id, $wantedAnswerersRedditorName, $cursoryRedditorWaiting, $subreddit, $pagename);
            }
            $idAndRedditorArrayOfVeryDifferentDirectChildrenWhoIsNtMe = anybodyOutThereWhoHasMeAsDirectParentHeAskedKnowinglyNoActuallyINeedAllOfMyKidsAndNotMyselfByTheWay($jsonObj, $id, $mintCommentThatIsNotFromDBButFromTheNet->author);
            //echo "<br><br>sizeof child array: " . sizeof($idAndRedditorArrayOfVeryDifferentDirectChildrenWhoIsNtMe) . ":<br><br>";
            //var_dump($idAndRedditorArrayOfVeryDifferentDirectChildrenWhoIsNtMe);
            if ($idAndRedditorArrayOfVeryDifferentDirectChildrenWhoIsNtMe[0]->id === "") {
                //echo "<br><br>comment-tag{waits.for.your.reply.only]: No answers yet to this comment " . $id;
            } else {
                // Here: ($mintCommentThatIsNotFromDBButFromTheNet->body has comment-tag{waits.for.your.reply.only] tag) & ($mintCommentThatIsNotFromDBButFromTheNet HAS one or more direct children, who isn't me)
                // Therefore: Give positive and negative pKarma to those direct children
                foreach ($idAndRedditorArrayOfVeryDifferentDirectChildrenWhoIsNtMe as $naughtyOrWellBehavedChild) {
                    $answerer = $naughtyOrWellBehavedChild->author;
                    //echo "<br><br>answerer: $answerer";
                    if ($answerer !== $wantedAnswerersRedditorName) {
                        // Here: The answerers are third-person
                        // Therefore: Search for replies by our agent in illegal third-person branch (Special rule)
                        //echo "<br><br>Violation! cid=" . $naughtyOrWellBehavedChild->id;
                        $appleIds = giveMeTheNamesOfAllApplesOnTheBranchWithThisCommentAsAxePointHmmm($jsonObj, $naughtyOrWellBehavedChild->id);
                        $violationCount = 0;
                        foreach ($appleIds as $appleId) {
                            //echo "<br>appleId: $appleId  author:" . $mintArrayOfIdsToBodiesAndAuthorsAndParentIds[$appleId]->author;
                            if ($mintCommentThatIsNotFromDBButFromTheNet->author === $mintArrayOfIdsToBodiesAndAuthorsAndParentIds[$appleId]->author) {
                                // Here: Apple found which is tag-c-agent's
                                // Give penalty
                                $violationCount++;
                                subtractPKarmaForWaitsForYourReplyOnlySelfDisciplineViolationConditionally($mintArrayOfIdsToBodiesAndAuthorsAndParentIds[$appleId]->author, $pageid, $appleId, $naughtyOrWellBehavedChild->author, $subreddit, $pagename);
                            }
                        }
                        //echo "violationCount: $violationCount";
                    } else {
                        // Here: Poster satisfied.
                        // Therefore: Give PKarma it hasn't already been given for this particular comment-tag{waits.for.your.reply.only] tag answering.
                        //echo "<br><br>Poster satisfied!";
                        $query = "SELECT pkarmadistributed FROM prima_tagc_status WHERE commentpageid='$pageid' AND commentidexpectingdirectanswerfromspecificredditor='$id'";
                        $result3 = mysqli_query($GLOBALS["___mysqli_ston"], $query);
                        $row3 = mysqli_fetch_row($result3);
                        $cursoryPKarmaHasBeenDistributed = $row3[0];
                        $cursoryVeryVeryCursory = $cursoryPKarmaHasBeenDistributed === 'true' ? true : false;
                        //echo "<br><br>cursoryVeryVeryCursory: $cursoryVeryVeryCursory";
                        if (!$cursoryVeryVeryCursory) {
                            $query = "UPDATE prima_tagc_status SET pkarmadistributed='true' WHERE commentpageid='$pageid' AND commentidexpectingdirectanswerfromspecificredditor='$id'";
                            mysqli_query($GLOBALS["___mysqli_ston"], $query);
                            $cursoryRedditorWhoWaited = $mintCommentThatIsNotFromDBButFromTheNet->author;
                            givePKarmaForWaitsForYourReplyOnlyUnconditionally($answerer, $cursoryRedditorWhoWaited, $pageid, $id, $subreddit, $pagename);
                            giveRKarmaForUseOfTagConditionally("comment-tag{waits.for.your.reply.only}", $answerer, $cursoryRedditorWhoWaited, 1, $pageid, $id, $subreddit, $pagename);

                        } else {
                            //echo "<br><br>$wantedAnswerersRedditorName has already been awarded for answering $a";
                        }
                    }
                }
            }
        }
    }


    // look for "comment-tag{i.find.the.subject.unworthy.for.discussion}"
    foreach ($mintArrayOfIdsToBodiesAndAuthorsAndParentIds as $id => $mintCommentThatIsNotFromDBButFromTheNet) {
        if (strpos($mintCommentThatIsNotFromDBButFromTheNet->body, 'comment-tag{i.find.the.subject.unworthy.for.discussion}') !== false) {

            $mTagAgentRedditorName = $mintCommentThatIsNotFromDBButFromTheNet->author;
            givePKarmaForUseOfTagConditionally("comment-tag{i.find.the.subject.unworthy.for.discussion}", $mTagAgentRedditorName, $pageid, $id, $subreddit, $pagename);

            $agentsCommentsParentId = $mintCommentThatIsNotFromDBButFromTheNet->parent_id;
            //echo "<br><br>agentsCommentsParentId: $agentsCommentsParentId";
            if ($mainPostId !== $agentsCommentsParentId) {
                // Here: Violation! comment-tag{i.find.this.unworthy.for.discussion] tag can just be used on this "level":answering main post
                // Therefore: Give penalty
                $anotherCommentsAuthor = $mintCommentThatIsNotFromDBButFromTheNet->author;
                if ($anotherCommentsAuthor === $agentsCommentsAuthor) {
                    //echo "<br><br>Violation!";
                    //echo "<br><br>mTagAgentRedditorName: $mTagAgentRedditorName<br>anotherCommentsAuthor: $anotherCommentsAuthor";
                    subtractPKarmaForTagMNotLevel2ViolationConditionally($mTagAgentRedditorName, $pageid, $id, $subreddit, $pagename);
                }
            }

            //$idsOfCommentWhoHasTOneParent = traverseA($jsonObj);
            ////echo "<br><br>Number of comments---: " . sizeof($idsOfCommentWhoHasTOneParent);
            foreach ($mintArrayOfIdsToBodiesAndAuthorsAndParentIds as $kkey => $vvalue) {
                if ($kkey !== $id) {
                    $anotherCommentsAuthor = $mintArrayOfIdsToBodiesAndAuthorsAndParentIds[$kkey]->author;
                    ////echo "<br><br>mTagAgentRedditorName: $mTagAgentRedditorName<br>mTagAgentRedditorName: $anotherCommentsAuthor";
                    if ($anotherCommentsAuthor === $mTagAgentRedditorName) {
                        subtractPKarmaForSelfDisciplineViolationConditionally($mTagAgentRedditorName, $pageid, $kkey, $subreddit, $pagename, "comment-tag{i.find.the.subject.unworthy.for.discussion}");
                    }
                }
            }
        }
    }


    // look for "comment-tag{i.find.this.unworthy.for.discussion}"
    foreach ($mintArrayOfIdsToBodiesAndAuthorsAndParentIds as $id => $mintCommentThatIsNotFromDBButFromTheNet) {
        if (strpos($mintCommentThatIsNotFromDBButFromTheNet->body, 'comment-tag{i.find.this.unworthy.for.discussion}') !== false) {

            $mTagAgentRedditorName = $mintCommentThatIsNotFromDBButFromTheNet->author;
            givePKarmaForUseOfTagConditionally("comment-tag{i.find.this.unworthy.for.discussion}", $mTagAgentRedditorName, $pageid, $id, $subreddit, $pagename);

            $agentsCommentsParentId = $mintCommentThatIsNotFromDBButFromTheNet->parent_id;
            //echo "<br><br>agentsCommentsParentId: $agentsCommentsParentId";
            if ($mainPostId !== $agentsCommentsParentId) {
                // Here: Violation! comment-tag{i.find.this.unworthy.for.discussion] tag can just be used on this "level":answering main post
                // Therefore: Give penalty
                $anotherCommentsAuthor = $mintCommentThatIsNotFromDBButFromTheNet->author;
                if ($anotherCommentsAuthor === $agentsCommentsAuthor) {
                    //echo "<br><br>Violation!";
                    //echo "<br><br>mTagAgentRedditorName: $mTagAgentRedditorName<br>anotherCommentsAuthor: $anotherCommentsAuthor";
                    subtractPKarmaForTagMNotLevel2ViolationConditionally($mTagAgentRedditorName, $pageid, $id, $subreddit, $pagename);
                }
            }

            //$idsOfCommentWhoHasTOneParent = traverseA($jsonObj);
            ////echo "<br><br>Number of comments---: " . sizeof($idsOfCommentWhoHasTOneParent);
            foreach ($mintArrayOfIdsToBodiesAndAuthorsAndParentIds as $kkey => $vvalue) {
                if ($kkey !== $id) {
                    $anotherCommentsAuthor = $mintArrayOfIdsToBodiesAndAuthorsAndParentIds[$kkey]->author;
                    ////echo "<br><br>mTagAgentRedditorName: $mTagAgentRedditorName<br>mTagAgentRedditorName: $anotherCommentsAuthor";
                    if ($anotherCommentsAuthor === $mTagAgentRedditorName) {
                        subtractPKarmaForSelfDisciplineViolationConditionally($mTagAgentRedditorName, $pageid, $kkey, $subreddit, $pagename, "comment-tag{i.find.the.subject.unworthy.for.discussion}");
                    }
                }
            }
        }
    }



    // look for "comment-tag{i.will.not.reply.and.expect.apology}"
    foreach ($mintArrayOfIdsToBodiesAndAuthorsAndParentIds as $id => $mintCommentThatIsNotFromDBButFromTheNet) {
        if (strpos($mintCommentThatIsNotFromDBButFromTheNet->body, 'comment-tag{i.will.not.reply.and.expect.apology}') !== false) {

            $mTagAgentRedditorName = $mintCommentThatIsNotFromDBButFromTheNet->author;
            givePKarmaForUseOfTagConditionally("comment-tag{i.will.not.reply.and.expect.apology}", $mTagAgentRedditorName, $pageid, $id, $subreddit, $pagename);

            $idOfParentRedditor = $mintCommentThatIsNotFromDBButFromTheNet->parent_id;
            $culprit = $mintArrayOfIdsToBodiesAndAuthorsAndParentIds[$idOfParentRedditor]->author;

            needApology($culprit, $mTagAgentRedditorName, $subreddit, $pageid, $id);

            createNotification($pageid, $id, $culprit, $subreddit, $pagename, "You need to apologize to $mTagAgentRedditorName.", "comment-tag{i.will.not.reply.and.expect.apology}", "mustApologizeIfOtherExpectsIt");

        }
    }


    // look for "comment-tag{i.apologize}"
    foreach ($mintArrayOfIdsToBodiesAndAuthorsAndParentIds as $id => $mintCommentThatIsNotFromDBButFromTheNet) {
        if ((strpos($mintCommentThatIsNotFromDBButFromTheNet->body, 'comment-tag{i.apologize}') !== false) or (strpos($mintCommentThatIsNotFromDBButFromTheNet->body, 'comment-tag{guarded.apology}') !== false)) {

            $mTagAgentRedditorName = $mintCommentThatIsNotFromDBButFromTheNet->author;
            givePKarmaForUseOfTagConditionally("comment-tag{i.apologize}", $mTagAgentRedditorName, $pageid, $id, $subreddit, $pagename);

			$commentBody = $mintCommentThatIsNotFromDBButFromTheNet->body;
			preg_match('#\{(.*?)\}#', $commentBody, $match);
 			$shortHandTag = $match[1];
			$tag = "comment-tag{" . $shortHandTag . "}";

            $idOfParentRedditor = $mintCommentThatIsNotFromDBButFromTheNet->parent_id;
            $angryRedditor = $mintArrayOfIdsToBodiesAndAuthorsAndParentIds[$idOfParentRedditor]->author;
            $angryRedditorsCommentBody = $mintArrayOfIdsToBodiesAndAuthorsAndParentIds[$idOfParentRedditor]->body;
            if (strpos($angryRedditorsCommentBody, 'comment-tag{i.will.not.reply.and.expect.apology}') !== false) {
                givePKarmaForCorrectUseIApologizeTagConditionally($mTagAgentRedditorName, $angryRedditor, $pageid, $id, $subreddit, $pagename, $tag);
                gaveNeededApology($mTagAgentRedditorName, $angryRedditor, $subreddit, $pageid, $id);
            } else {
                subtractPKarmaForTagQAbsurdApologyConditionally($mTagAgentRedditorName, $pageid, $id, $subreddit, $pagename, $tag);
            }
        }
    }


    // look for comment-tag{no.problem}, comment-tag{dont.mind.its.ok.lets.move.on} or comment-tag{its.fine.i.consider.the.case.closed}
    foreach ($mintArrayOfIdsToBodiesAndAuthorsAndParentIds as $id => $mintCommentThatIsNotFromDBButFromTheNet) {
        if ((strpos($mintCommentThatIsNotFromDBButFromTheNet->body, 'comment-tag{no.problem}') !== false) || (strpos($mintCommentThatIsNotFromDBButFromTheNet->body, 'comment-tag{dont.mind.its.ok.lets.move.on}') !== false) || (strpos($mintCommentThatIsNotFromDBButFromTheNet->body, 'comment-tag{its.fine.i.consider.the.case.closed}') !== false)) {
            $mTagAgentRedditorName = $mintCommentThatIsNotFromDBButFromTheNet->author;
            givePKarmaForUseOfTagConditionally("comment-tag{no.problem}", $mTagAgentRedditorName, $pageid, $id, $subreddit, $pagename);

            $idOfParentRedditor = $mintCommentThatIsNotFromDBButFromTheNet->parent_id;
			$commentBody = $mintCommentThatIsNotFromDBButFromTheNet->body;
			preg_match('#\{(.*?)\}#', $commentBody, $match);
 			$shortHandTag = $match[1];
			$tag = "comment-tag{" . $shortHandTag . "}";
            $apologizingRedditor = $mintArrayOfIdsToBodiesAndAuthorsAndParentIds[$idOfParentRedditor]->author;
            $apologizingRedditorsCommentBody = $mintArrayOfIdsToBodiesAndAuthorsAndParentIds[$idOfParentRedditor]->body;
			
            if ((strpos($apologizingRedditorsCommentBody, 'comment-tag{i.apologize}') !== false) or (strpos($apologizingRedditorsCommentBody, 'comment-tag{guarded.apology}') !== false)) {
				$motivation = "You have received $points for saying \"no problems\" to $apologizingRedditor by using this tag.";
				$points = 15;
                givePKarmaForCorrectUseOfNoProblemosTagConditionally($mTagAgentRedditorName, $apologizingRedditor, $pageid, $id, $subreddit, $pagename, $motivation, $points, $tag);
            } else {
				$points = -15;
				$motivation = "You have received $points in penalty, because you didn\'t use directly towards someone who is apologizing.";
                subtractPKarmaForTagNoProblemUsedInAnAbsurdWayConditionally($mTagAgentRedditorName, $apologizingRedditor, $pageid, $id, $subreddit, $pagename, $tag);
            }

        }
    }

    // look for "comment-tag{your.comment.inspired.me}"
    foreach ($mintArrayOfIdsToBodiesAndAuthorsAndParentIds as $id => $mintCommentThatIsNotFromDBButFromTheNet) {
        if (strpos($mintCommentThatIsNotFromDBButFromTheNet->body, 'comment-tag{your.comment.inspired.me}') !== false) {
            $mTagAgentRedditorName = $mintCommentThatIsNotFromDBButFromTheNet->author;
            givePKarmaForUseOfTagConditionally("comment-tag{your.comment.inspired.me}", $mTagAgentRedditorName, $pageid, $id, $subreddit, $pagename);
            $wantedSecondPersonWithAlleFieldsInHere = $mintArrayOfIdsToBodiesAndAuthorsAndParentIds[$mintCommentThatIsNotFromDBButFromTheNet->parent_id];
            if (getWordCountBesidesTheTagItselfDude($wantedSecondPersonWithAlleFieldsInHere->body, "comment-tag{your.comment.inspired.me}") < 20) {
                // Here: Word count of second persons comment is less than 20 words
                // Therefore: Give penalty to first person
                subtractPKarmaForTagInspiredNotBeingUsedAsAnswerToMainPostConditionally($mTagAgentRedditorName, "comment-tag{your.comment.inspired.me}", $pageid, $id, $subreddit, $pagename);
            }
            $idAndTimeAndAuthorAndUTCArrayOfOldestChildWhoIsNtMe = anybodyOutThereWhoHasMeAsParentHeAskedKnowinglyNoActuallyINeedTheOLDESTOneOfMyKidsAndNotMyselfByTheWay($jsonObj, $id, $mintCommentThatIsNotFromDBButFromTheNet->author);
            $foundValidIMeanItTag = false;
            for ($j = 0; $j < sizeof($idAndTimeAndAuthorAndUTCArrayOfOldestChildWhoIsNtMe); $j++) {
                if ($idAndTimeAndAuthorAndUTCArrayOfOldestChildWhoIsNtMe[$j]->author === $mintCommentThatIsNotFromDBButFromTheNet->author) {
                    // Here: Second person = first person.
                    // Therefore: Find out if the reply has been correctly answered with a stand-alone comment-tag{no.i.mean.it} tag
                    if (strpos($idAndTimeAndAuthorAndUTCArrayOfOldestChildWhoIsNtMe[$j]->body, 'comment-tag{no.i.mean.it}') !== false) {
                        // Here: First person answered himself with a comment-tag{no.i.mean.it} tag, the way he should do
                        // Therefore: Find out if it is stand-alone
                        if (hasMoreWordsBesidesTheTagItselfDude($idAndTimeAndAuthorAndUTCArrayOfOldestChildWhoIsNtMe[j]->body, "comment-tag{no.i.mean.it}")) {
                            // Here: It was stand-alone
                            // Therefore: Lastly: Find out if more than 10 minutes has gone by and garble if it is so.
                            $utc = $idAndTimeAndAuthorAndUTCArrayOfOldestChildWhoIsNtMe[j]->utc;
                            $minutesGoneBy = ((time() - $utc) / 60);
                            if (minutesGoneBy <= 10) {
                                // Here: Everything is ok
                                // Therefore: Give points for use of comment-tag{no.i.mean.it} tag
                                givePKarmaForUseOfTagConditionally("comment-tag{no.i.mean.it}", $mintCommentThatIsNotFromDBButFromTheNet->author, $pageid, $id, $subreddit, $pagename);
                                $foundValidIMeanItTag = true;
                            } else {
                                // Here: Expired
                                // Therefore: Do nothing
                            }
                        }
                    }
                }
            }
            if (!$foundValidIMeanItTag) {
                // Here: No valid no.i.mean.it tag found
                // Therefore: See if too long time has gone by and, in this case, give a penalty
                $utc = $idAndTimeAndAuthorAndUTCArrayOfOldestChildWhoIsNtMe[j]->utc;
                $minutesGoneBy = ((time() - $utc) / 60);
                if ($minutesGoneBy > 10) {
                    // Here: Too long time has passed
                    // Therefore: Subtract karma
                    subtractPKarmaForTagInspiredNotBeingFollowedUpByIMeanItTagInTimeConditionally($mintCommentThatIsNotFromDBButFromTheNet->author, $pageid, $id, $subreddit, $pagename);
                }
            }
        }
    }

    // look for "comment-tag{i.dont.think.the.original.post.has.been.addressed.yet}"
    foreach ($mintArrayOfIdsToBodiesAndAuthorsAndParentIds as $id => $mintCommentThatIsNotFromDBButFromTheNet) {
        if (strpos($mintCommentThatIsNotFromDBButFromTheNet->body, 'comment-tag{i.dont.think.the.original.post.has.been.addressed.yet}') !== false) {

            $mTagAgentRedditorName = $mintCommentThatIsNotFromDBButFromTheNet->author;
            givePKarmaForUseOfTagConditionally("comment-tag{i.dont.think.the.original.post.has.been.addressed.yet}", $mTagAgentRedditorName, $pageid, $id, $subreddit, $pagename);

            if ($mainPostId !== $mintCommentThatIsNotFromDBButFromTheNet->parent_id) {
                subtractPKarmaForIDontThinkTheOriginalBlaBlaBlaConditionally($mTagAgentRedditorName, "comment-tag{i.dont.think.the.original.post.has.been.addressed.yet}", $pageid, $id, $subreddit, $pagename);
            }
        }
    }

    // look for "comment-tag{i.dont.think.the.original.post.has.been.taken.seriously.yet}"
    foreach ($mintArrayOfIdsToBodiesAndAuthorsAndParentIds as $id => $mintCommentThatIsNotFromDBButFromTheNet) {
        if (strpos($mintCommentThatIsNotFromDBButFromTheNet->body, 'comment-tag{i.dont.think.the.original.post.has.been.taken.seriously.yet}') !== false) {

            $mTagAgentRedditorName = $mintCommentThatIsNotFromDBButFromTheNet->author;
            givePKarmaForUseOfTagConditionally("comment-tag{i.dont.think.the.original.post.has.been.taken.seriously.yet}", $mTagAgentRedditorName, $pageid, $id, $subreddit, $pagename);

            if ($mainPostId !== $mintCommentThatIsNotFromDBButFromTheNet->parent_id) {
                subtractPKarmaForIDontThinkTheOriginalBlaBlaBlaConditionally($mTagAgentRedditorName, "comment-tag{i.dont.think.the.original.post.has.been.taken.seriously.yet}", $pageid, $id, $subreddit, $pagename);
            }
        }
    }


    // look for "comment-tag{i.dont.think.the.original.post.has.been.treated.respectfully}"
    foreach ($mintArrayOfIdsToBodiesAndAuthorsAndParentIds as $id => $mintCommentThatIsNotFromDBButFromTheNet) {
        if (strpos($mintCommentThatIsNotFromDBButFromTheNet->body, 'comment-tag{i.dont.think.the.original.post.has.been.treated.respectfully}') !== false) {

            $mTagAgentRedditorName = $mintCommentThatIsNotFromDBButFromTheNet->author;
            givePKarmaForUseOfTagConditionally("comment-tag{i.dont.think.the.original.post.has.been.treated.respectfully}", $mTagAgentRedditorName, $pageid, $id, $subreddit, $pagename);

            if ($mainPostId !== $mintCommentThatIsNotFromDBButFromTheNet->parent_id) {
                subtractPKarmaForIDontThinkTheOriginalBlaBlaBlaConditionally($mTagAgentRedditorName, "comment-tag{i.dont.think.the.original.post.has.been.treated.respectfully}", $pageid, $id, $subreddit, $pagename);
            }
        }
	}

    // check if redditors who are in conflict talk to each (on this particular page, known to the system) other without the wrongdoer having apologized first
    foreach ($mintArrayOfIdsToBodiesAndAuthorsAndParentIds as $id => $mintCommentThatIsNotFromDBButFromTheNet) {
        $mTagAgentRedditorName = $mintCommentThatIsNotFromDBButFromTheNet->author;
        $wantedSecondPersonWithAlleFieldsInHere = $mintArrayOfIdsToBodiesAndAuthorsAndParentIds[$mintCommentThatIsNotFromDBButFromTheNet->parent_id];
        if (needsToApologize($mTagAgentRedditorName, $wantedSecondPersonWithAlleFieldsInHere->author)) {
            // Here: first person needs to apologize to second person
            // Therefore: Create notification for first person about this situation
            createNeedToApologizeBeforeChattingNotificationForBothFirstAndSecondPerson($pageid, $id, $mTagAgentRedditorName, $wantedSecondPersonWithAlleFieldsInHere->author, $subreddit, $pagename);
        } else if (needsToApologize($wantedSecondPersonWithAlleFieldsInHere->author, $mTagAgentRedditorName)) {
            // Here: second person needs to apologize to first person
            // Therefore: Create notification for second person about this situation
            createNeedToApologizeBeforeChattingNotificationForBothFirstAndSecondPerson($pageid, $id, $wantedSecondPersonWithAlleFieldsInHere->author, $mTagAgentRedditorName, $subreddit, $pagename);
        }
    }

    // look for "comment-tag{that.pissed.me.off.but.please.dont.mind}"
    foreach ($mintArrayOfIdsToBodiesAndAuthorsAndParentIds as $id => $mintCommentThatIsNotFromDBButFromTheNet) {
        if (strpos($mintCommentThatIsNotFromDBButFromTheNet->body, 'comment-tag{that.pissed.me.off.but.please.dont.mind}') !== false) {
            $mTagAgentRedditorName = $mintCommentThatIsNotFromDBButFromTheNet->author;
            $wantedSecondPersonWithAlleFieldsInHere = $mintArrayOfIdsToBodiesAndAuthorsAndParentIds[$mintCommentThatIsNotFromDBButFromTheNet->parent_id];
            givePKarmaForUseOfTagConditionally("comment-tag{that.pissed.me.off.but.please.dont.mind}", $mTagAgentRedditorName, $pageid, $id, $subreddit, $pagename);
        }
    }
}


function needsToApologize($hypotheticallyApologizerRedditor, $hypotheticallyAndHystericalAngryRedditor) {
	$query = "SELECT * FROM prima_needed_apology WHERE angry_redditor='$hypotheticallyAndHystericalAngryRedditor' AND need_to_apol_redditor='$hypotheticallyApologizerRedditor' AND has_apologized='false';";
	$result3 = mysqli_query($GLOBALS["___mysqli_ston"], $query);
	$count3 = mysqli_num_rows($result3);
	return ($count3 > 0);
}

function gaveNeededApology($apologizerRedditor, $angryRedditor, $subreddit, $pageid, $cid) {
	$dt2=date("Y-m-d H:i:s");
	$t = time();
	$sql = "UPDATE prima_needed_apology SET has_apologized='true', apologized_when='$dt2', apologized_when_utc='$t', conflict_ended_subreddit='$subreddit', conflict_ended_pageid='$pageid', conflict_ended_commentid='$cid' WHERE has_apologized='false' AND angry_redditor='$angryRedditor' AND need_to_apol_redditor='$apologizerRedditor';";
	mysqli_query($GLOBALS["___mysqli_ston"], $sql);
}

function needApology($needToApologizeRedditor, $angryRedditor, $subreddit, $pageid, $cid) {
	$dt2=date("Y-m-d H:i:s");
	$t = time();
	$sql = "INSERT INTO `redditawkward_com`.`prima_needed_apology` (`angry_redditor`, `need_to_apol_redditor`, `created_when_by_doorslam_or_expect`, `created_when_by_doorslam_or_expect_utc`, `conflict_started_subreddit`, `conflict_started_pageid`, `conflict_started_commentid`, `has_apologized`, `apologized_when`, `apologized_when_utc`, `conflict_ended_subreddit`, `conflict_ended_pageid`, `conflict_ended_commentid`, `id`) VALUES ('$angryRedditor', '$needToApologizeRedditor', $dt2, '$t', '$subreddit', '$pageid', '$cid', 'false', NULL, NULL, NULL, NULL, NULL, NULL);";
	mysqli_query($GLOBALS["___mysqli_ston"], $sql);
}


function getWordCountBesidesTheTagItselfDude($text, $tag) {
	//This code removes line breaks
	$text = str_replace(array("\r", "\n"), '', $text);
	$actualStringTheWayItLooksOnThePage = $tag;
	if (strpos($text, '](http://redditawkward.com') !== false) {
		$actualStringTheWayItLooksOnThePage = '[' + $tag + '](http://redditawkward.com/rules/' + $tag + '.php)';
	}
	$text = str_replace($actualStringTheWayItLooksOnThePage, "", $text);
	return str_word_count($text);
}

function hasMoreWordsBesidesTheTagItselfDude($text, $tag) {
	//This code removes line breaks
	$text = str_replace(array("\r", "\n"), '', $text);
	if (strpos($text, '](http://redditawkward.com') !== false) {
		$actualStringTheWayItLooksOnThePage = '[' + $tag + '](http://redditawkward.com/rules/' + $tag + '.php)';
	}
	$text = str_replace($actualStringTheWayItLooksOnThePage, "", $text);
	return (strlen($text) > $actualStringTheWayItLooksOnThePage);
}

function isUnbrokenTagSequenceBackwards($tag, $idStart, $idEnd, $json, $bigDataOYeah) {
	$d = $idStart;
	while ($c = getParentSemiDeprecated($d, $json)) {
		//echo "<br>c[0]:".$c[0];
		$body = $bigDataOYeah[$c[0]]->body;
		$idUnderTheLup =  $c[0];
		if ($idUnderTheLup === $idEnd) {
			return true; 
			//echo "<brChain is unbroken!";
		}
		if (strpos($body, $tag) === false) { 
			//echo "<brChain is broken for $tag in $body";		
			return false; 
		}
		$d = $c[0];
	}



	// throw exception
	return true;



}

function roadBackToRootCointainsOneOrMoreOfThisTag($tag, $cid, $json, $bigDataOYeah) {
	$d = $cid;
	while ($c = getParentSemiDeprecated($d, $json)) {
		//echo "<br>c[0]:".$c[0];
		$body = $bigDataOYeah[$c[0]]->body;
		if (strpos($body, $tag) !== false) { 
			//echo "<br>Body contains $tag:$body";		
			return true; 
		}
		$d = $c[0];
	}
	return false;
}

function giveMeTheNamesOfAllApplesOnTheBranchWithThisCommentAsAxePointHmmm($jsonObj, $cid) {
	$idsOfCommentWhoHasTOneParent = traverseA($jsonObj);
	//echo "<br><br>Number of comments---: " . sizeof($idsOfCommentWhoHasTOneParent);
	$leafs = Array();
	$branches = Array();
	foreach ($idsOfCommentWhoHasTOneParent as $kkey=>$vvalue) {
		//echo "<br>kkey: $kkey<br>vvalue->id:$vvalue->id<br>vvalue->parent_id:$vvalue->parent_id";
		//$idsOfCommentWhoHasTOneParentPointer = i;
		//echo "<br><br>-----------------idsOfCommentWhoHasTOneParent id: " . $kkey;
		$r = (isJustALeaf($kkey, $jsonObj));
		//echo "<br><br>-----------------idsOfCommentWhoHasTOneParent id2: " . $kkey;
		//echo "<br><br>r:".(string)$r;
		if ($r) {
			//echo "<br><br>pushin";
			array_push($leafs, $kkey);
		}
		
	}
	//echo "leaf count: " + sizeof($leafs);
	foreach ($leafs as $leaf) {
		//echo "<br>leaf:" . $leaf;	
	}
	for ($i = 0; $i < sizeof($leafs); $i++) {
		//console.log("leaf: " + leafs[i]);
		$s = constructBranch($leafs[$i], $jsonObj);
		if ($s) { $branches[$i] = $s; }
	}
	//echo "branch count: " + sizeof($branches);
	for ($i = 0; $i < sizeof($branches); $i++) {
		//console.log("branch " + branches[i][0] + " size: " + branches[i].length);
		//echo "<br><br>branch: ";
		for ($b = 0; $b < sizeof($branches[$i]); $b++) {
			//console.log("branch " + i + ": " + branches[i][b]);
			//echo "-".$branches[$i][$b];
		}
	}
	$rightBranches = Array();
	$branchoDancho = Array();
	$rightBranchIndexTookThreeHoursForMeToFindThisBug = 0;
	for ($i = 0; $i < sizeof($branches); $i++) {
		$branchoDancho = Array();
		$foundCId = false;
		//echo "<br><br>branchodancho:(". $cid . ")";
		for ($b = 0; $b < sizeof($branches[$i]); $b++) {
			//console.log("branch " + i + ": " + branches[i][b]);
			if ($branches[$i][$b] === $cid) $foundCId = true;
			array_push($branchoDancho, $branches[$i][$b]);
			//echo "+".$branches[$i][$b]."(".sizeof($branchoDancho).")";
			
		}
		if ($foundCId) {
			//echo "<br><br>found: (".$i.")";
			$rightBranches[$rightBranchIndexTookThreeHoursForMeToFindThisBug] = Array();
			for ($c = 0; $c < sizeof($branchoDancho); $c++) {
				//echo ".".$branchoDancho[$c];
				array_push($rightBranches[$rightBranchIndexTookThreeHoursForMeToFindThisBug], $branchoDancho[$c]);
			}
			//echo "<br><br>rightBranches*:(" . sizeof($rightBranches[$rightBranchIndexTookThreeHoursForMeToFindThisBug]) . ")";
			$rightBranchIndexTookThreeHoursForMeToFindThisBug++;
		}
	}
	//echo "<br><br>lizey: " . sizeof($rightBranches);
	//echo "<br><br>sizey: " . sizeof($rightBranches[0]);




	//echo "<br><br>";
	//var_dump($rightBranches);






	for ($i = 0; $i < sizeof($rightBranches); $i++) {
		//echo "<br><br>rightBranches:(" . sizeof($rightBranches[$i]) . ")";
	}
	$commentsOnBranch = Array();
	for ($i = 0; $i < sizeof($rightBranches); $i++) {
		$foundAxePoint = true;
		//echo "<br><br>rightBranches:(" . sizeof($rightBranches[$i]) . ")";
		for ($b = 0; $b < sizeof($rightBranches[$i]); $b++) {
			if ($foundAxePoint) {	
				//echo "#".$rightBranches[$i][$b];
				if (!in_array($rightBranches[$i][$b], $commentsOnBranch)) {
					array_push($commentsOnBranch, $rightBranches[$i][$b]);
				}
			}
			if ($rightBranches[$i][$b] === $cid) $foundAxePoint = false;
		}
	}
	return $commentsOnBranch;
}


function constructBranch($k, $jsonObj) {
	$a = Array();
	$i = 1;
	array_push($a, $k);
	while ($c = getParentSemiDeprecated($a[$i-1], $jsonObj)) {
		//echo "<br>c[0]:".$c[0];
		if ($c[0]) array_push($a, $c[0]);
		$i++;
	}
	return $a;
}

function getParentSemiDeprecated($k, $jsonObj) {
	$commentIdToLookFor = $k;
	$parentC = traverseC($jsonObj, $commentIdToLookFor);
	//echo "<br>commentIdToLookFor: $commentIdToLookFor<br>parentC: $parentC[0]";
	return $parentC;
}

function isJustALeaf($idf, $jsonObj) {
	$parentIdToLookFor = "t1_" . $idf;
	//echo "<br><br><br><br>TESTING ID: " . $parentIdToLookFor;
	$pointedTo = traverseB($jsonObj, $parentIdToLookFor);
	if ($pointedTo[0]->val === 'yes') {
		//echo "<br>***************************d******************************";
		return false;
	}
	else if ($pointedTo[0]->val === 'no') {
		//echo "<br>ooooooooooooooooooooodooooooooooooooooooooooooooooooooo";
		return true;
	}
	else {
		//echo "<br>###############################d#########################################";
		return true;
	}
	//if (!$pointedTo) //echo "NOT LEAF: " . $idf;
	





	//throw exception




}

function subtractPKarmaConditionally($redditor, $tag, $pid, $cid, $subreddit, $pagename, $motivation, $points) {
	$query = "SELECT * FROM prima_karmagift WHERE redditor='$redditor' AND pageid='$pid' AND commentid='$cid'";
	//echo "<br>$query";
	$result3 = mysqli_query($GLOBALS["___mysqli_ston"], $query);
	$count3 = mysqli_num_rows($result3);
	if ($count3 > 0) {
		//echo "<br>subtract1";
		//echo "<br>$redditor has already been given a penalty!";
	}
	else {
 		//echo "<br><br>Awarding $points to $redditor";
		//echo "<br>subtract2";
		$dt2=date("Y-m-d H:i:s");
		$t = time();
		$sql = "INSERT INTO `redditawkward_com`.`prima_karmagift` (`redditor`, `pageid`, `commentid`, `whenf`, `utc`, `points`, `claimed`, `claimedwhen`, `claimedwhen_utc`, `motivation`, `rule`, `subreddit`, `pagename`, `tag`, `id`) VALUES ('$redditor', '$pid', '$cid', '$dt2', '$t', '$points', 'false', NULL, NULL, '$motivation', 'subtractPKarmaForTagInspiredNotBeingUsedAsAnswerToMainPostConditionally', '$subreddit', '$pagename', '$tag', NULL);";
		//echo "<br><br>$sql";
		mysqli_query($GLOBALS["___mysqli_ston"], $sql);
	}
}

function createNotification($pageid, $cid, $redditor, $subreddit, $pagename, $notificationMessage, $tag, $rule) {
	$query = "SELECT * FROM prima_notification WHERE redditor='$redditor' AND pageid='$pageid' AND commentid='$cid'";
	$result3 = mysqli_query($GLOBALS["___mysqli_ston"], $query);
	$count3 = mysqli_num_rows($result3);
	if ($count3 > 0) {
		//echo "$redditor notification ('$notificationMessage') already sent";
	}
	else {
		$dt2=date("Y-m-d H:i:s");
		$t = time();
		$sql = "INSERT INTO `redditawkward_com`.`prima_notification` (`redditor`, `pageid`, `commentid`, `whenf`, `utc`, `claimed`, `claimedwhen`, `claimedwhen_utc`, `motivation`, `tag`, `rule`, `subreddit`, `pagename`) VALUES ('$redditor', '$pageid', '$cid', '$dt2', '$t', 'false', NULL, NULL, '$notificationMessage', '$tag', '$rule', '$subreddit', '$pagename');";
		//echo "<br><br>$sql";
		mysqli_query($GLOBALS["___mysqli_ston"], $sql);
	}
}

function createNeedToApologizeBeforeChattingNotificationForBothFirstAndSecondPerson($pageid, $cid, $needsToApologizeRedditorName, $angryPersonRedditorName, $subreddit, $pagename) {
	$query = "SELECT * FROM prima_notification WHERE redditor='$firstPersonRedditorName' AND pageid='$pageid' AND commentid='$cid'";
	$result3 = mysqli_query($GLOBALS["___mysqli_ston"], $query);
	$count3 = mysqli_num_rows($result3);
	if ($count3 > 0) {
		//echo "$needsToApologizeRedditorName and $angryPersonRedditorName has already been notified of chat situation in conflict!";
	}
	else {
		$notification = "Redditor $needsToApologizeRedditorName needs to apologize with comment-tag{i.apologize} $angryPersonRedditorName or comment-tag{guarded.apology} for this incidence: http://  before they should engage in direct conversation! No relational karma or Awkward Karma has been subtracted from either of you. Thanks.";
		$dt2=date("Y-m-d H:i:s");
		$t = time();
		$sql = "INSERT INTO `redditawkward_com`.`prima_notification` (`redditor`, `pageid`, `commentid`, `whenf`, `utc`, `claimed`, `claimedwhen`, `claimedwhen_utc`, `motivation`, `tag`, `rule`, `subreddit`, `pagename`) VALUES ('$needsToApologizeRedditorName', '$pageid', '$cid', '$dt2', '$t', 'false', NULL, NULL, '$notification', 'comment-tag{i.apologize}' 'commentatorExpectsAnswerFrom', '$subreddit', '$pagename');";
		//echo "<br><br>$sql";
		mysqli_query($GLOBALS["___mysqli_ston"], $sql);
		$sql = "INSERT INTO `redditawkward_com`.`prima_notification` (`redditor`, `pageid`, `commentid`, `whenf`, `utc`, `claimed`, `claimedwhen`, `claimedwhen_utc`, `motivation`, `tag`, `rule`, `subreddit`, `pagename`) VALUES ('$angryPersonRedditorName', '$pageid', '$cid', '$dt2', '$t', 'false', NULL, NULL, '$notification', 'comment-tag{i.apologize}' 'shouldApologizeBeforeChatting', '$subreddit', '$pagename');";
		//echo "<br><br>$sql";
		mysqli_query($GLOBALS["___mysqli_ston"], $sql);
	}
}

function giveRKarmaForUseOfTagConditionally($tag, $redditorFirstPerson, $redditorSecondPerson, $rKarma, $pid, $cid, $subreddit, $pagename) {
	$query = "SELECT * FROM prima_relation WHERE firstperson='$redditorFirstPerson' AND secondperson='$redditorSecondPerson' AND firstperson_commentid='$cid' AND firstperson_pageid = '$pid'";
	$result3 = mysqli_query($GLOBALS["___mysqli_ston"], $query);
	$count3 = mysqli_num_rows($result3);
	if ($count3 > 0) {
		//echo "<br>redditors $redditorFirstPerson (firstperson) and $redditorSecondPerson (secondperson) has already been awarded.";
	}
	else {
 		//echo "<br><br>Awarding $rKarma in relational karma to firstperson: $redditorFirstPerson and secondperson: $redditorSecondPerson";
		$motivation = "$redditorFirstPerson and $redditorSecondPerson were both awarded $rKarma in relational karma because $redditorFirstPerson used this tag.";
		$dt2=date("Y-m-d H:i:s");
		$t = time();
$sql = "INSERT INTO  `redditawkward_com`.`prima_relation (`firstperson`, `secondperson`, `whencreated`, `whencreated_utc`, `secondperson_notified`, `whensecondpersonnotified`, `whensecondpersonnotified_utc`, `rkarmaforboth`, `firstperson_commentid`, `firstperson_pageid`, `subreddit`, `motivation`, `tag`) VALUES ('$redditorFirstPerson',  '$redditorSecondPerson',  '$dt2',  '$t',  'false',  NULL,  '0',  '$rKarma',  '$cid',  '$pid',  '$subreddit',  '$motivation' , '$tag');";
		 mysqli_query($GLOBALS["___mysqli_ston"], $sql);
	}

}

function subtractPKarmaForTagInspiredNotBeingFollowedUpByIMeanItTagInTimeConditionally($redditor, $pid, $cid, $subreddit, $pagename) {
	$query = "SELECT * FROM prima_karmagift WHERE redditor='$redditor' AND pageid='$pid' AND commentid='$cid'";
	//echo "<br>$query";
	$result3 = mysqli_query($GLOBALS["___mysqli_ston"], $query);
	$count3 = mysqli_num_rows($result3);
	if ($count3 > 0) {
		//echo "<br>$redditor has already been given a penalty!";
	}
	else {
		$actualPoints = -10;
 		//echo "<br><br>Awarding $actualPoints to $redditor";
		$motivation = "You have received a penalty of $actualPoints because you didn\'t use the comment-tag{no.i.mean.it} tag as a reply to the comment where you used comment-tag{your.comment.inspired.me}: §3 Should always be followed by an answer to yourself, within 10 minutes, with a single, stand-alone comment-tag{no.i.mean.it} tag.";
		$dt2=date("Y-m-d H:i:s");
		$t = time();
		$sql = "INSERT INTO `redditawkward_com`.`prima_karmagift` (`redditor`, `pageid`, `commentid`, `whenf`, `utc`, `points`, `claimed`, `claimedwhen`, `claimedwhen_utc`, `motivation`, `rule`, `subreddit`, `pagename`, `tag`, `id`) VALUES ('$redditor', '$pid', '$cid', '$dt2', '$t', '$actualPoints', 'false', NULL, NULL, '$motivation', 'subtractPKarmaForTagInspiredNotBeingFollowedUpByIMeanItTagInTimeConditionally', '$subreddit', '$pagename', 'comment-tag{your.comment.inspired.me}', NULL);";
		//echo "<br><br>$sql";
		 mysqli_query($GLOBALS["___mysqli_ston"], $sql);
	}
}

function subtractPKarmaForTagInspiredNotBeingUsedAsAnswerToMainPostConditionally($redditor, $tag, $pid, $cid, $subreddit, $pagename) {
	$query = "SELECT * FROM prima_karmagift WHERE redditor='$redditor' AND pageid='$pid' AND commentid='$cid'";
	//echo "<br>$query";
	$result3 = mysqli_query($GLOBALS["___mysqli_ston"], $query);
	$count3 = mysqli_num_rows($result3);
	if ($count3 > 0) {
		//echo "<br>$redditor has already been given a penalty!";
	}
	else {
		$actualPoints = -10;
 		//echo "<br><br>Awarding $actualPoints to $redditor";
		$motivation = "You have received a penalty of $actualPoints because you used $tag as a an answer to a comment and not the original post or link.";
		$dt2=date("Y-m-d H:i:s");
		$t = time();
		$sql = "INSERT INTO `redditawkward_com`.`prima_karmagift` (`redditor`, `pageid`, `commentid`, `whenf`, `utc`, `points`, `claimed`, `claimedwhen`, `claimedwhen_utc`, `motivation`, `rule`, `subreddit`, `pagename`, `tag`, `id`) VALUES ('$redditor', '$pid', '$cid', '$dt2', '$t', '$actualPoints', 'false', NULL, NULL, '$motivation', 'subtractPKarmaForTagInspiredNotBeingUsedAsAnswerToMainPostConditionally', '$subreddit', '$pagename', '$tag', NULL);";
		//echo "<br><br>$sql";
		 mysqli_query($GLOBALS["___mysqli_ston"], $sql);
	}
}

function subtractPKarmaForIDontThinkTheOriginalBlaBlaBlaConditionally($redditor, $tag, $pid, $cid, $subreddit, $pagename) {
	$query = "SELECT * FROM prima_karmagift WHERE redditor='$redditor' AND pageid='$pid' AND commentid='$cid'";
	//echo "<br>$query";
	$result3 = mysqli_query($GLOBALS["___mysqli_ston"], $query);
	$count3 = mysqli_num_rows($result3);
	if ($count3 > 0) {
		//echo "<br>$redditor has already been given a penalty";
	}
	else {
		$actualPoints = -10;
 		//echo "<br><br>Awarding $actualPoints to $redditor";
		$motivation = "You have received a penalty of $actualPoints because you used $tag as a an answer to a comment and not the original post or link.";
		$dt2=date("Y-m-d H:i:s");
		$t = time();
		$sql = "INSERT INTO `redditawkward_com`.`prima_karmagift` (`redditor`, `pageid`, `commentid`, `whenf`, `utc`, `points`, `claimed`, `claimedwhen`, `claimedwhen_utc`, `motivation`, `rule`, `subreddit`, `pagename`, `tag`, `id`) VALUES ('$redditor', '$pid', '$cid', '$dt2', '$t', '$actualPoints', 'false', NULL, NULL, '$motivation', 'subtractPKarmaForIDontThinkTheOriginalBlaBlaBlaConditionally', '$subreddit', '$pagename', '$tag', NULL);";
		//echo "<br><br>$sql";
		 mysqli_query($GLOBALS["___mysqli_ston"], $sql);
	}
}


function givePKarmaForCorrectUseOfNoProblemosTagConditionally($redditorJTagAgent, $apologizingRedditor, $pid, $cid, $subreddit, $pagename, $motivation, $points, $tag) {
	$query = "SELECT * FROM prima_karmagift WHERE redditor='$redditorJTagAgent' AND pageid='$pid' AND commentid='$cid'";
	$result3 = mysqli_query($GLOBALS["___mysqli_ston"], $query);
	$count3 = mysqli_num_rows($result3);
	if ($count3 > 0) {
		//echo "$redditorJTagAgent has already been given a penalty";
	}
	else {
 		//echo "<br><br>Awarding $points to $redditorJTagAgent";
		$dt2=date("Y-m-d H:i:s");
		$t = time();
		
		$sql = "INSERT INTO `redditawkward_com`.`prima_karmagift` (`redditor`, `pageid`, `commentid`, `whenf`, `utc`, `points`, `claimed`, `claimedwhen`, `claimedwhen_utc`, `motivation`, `rule`, `subreddit`, `pagename`, `tag`, `id`) VALUES ('$redditorJTagAgent', '$pid', '$cid', '$dt2', '$t', '$points', 'false', NULL, NULL, '$motivation', 'givePKarmaForCorrectUseOfNoProblemosTagConditionally', '$subreddit', '$pagename', '$tag', NULL);";
		////echo "<br><br>$sql";
		queryDoorslamAndExpectConditionally($redditorJTagAgent, $apologizingRedditor, $subreddit, $pid, $pagename, "comment-tag{no.problem}", $cid, $sql);
	}
}


function subtractPKarmaForTagNoProblemUsedInAnAbsurdWayConditionally($redditor, $nonApologizingRedditor, $pid, $cid, $subreddit, $pagename, $motivation, $points, $tag) {
	$query = "SELECT * FROM prima_karmagift WHERE redditor='$redditor' AND pageid='$pid' AND commentid='$cid'";
	//echo "<br>$query";
	$result3 = mysqli_query($GLOBALS["___mysqli_ston"], $query);
	$count3 = mysqli_num_rows($result3);
	if ($count3 > 0) {
		//echo "<br>$redditor has already been given a penalty";
	}
	else {
 		//echo "<br><br>Awarding $points to $redditor";
		$dt2=date("Y-m-d H:i:s");
		$t = time();
		$sql = "INSERT INTO `redditawkward_com`.`prima_karmagift` (`redditor`, `pageid`, `commentid`, `whenf`, `utc`, `points`, `claimed`, `claimedwhen`, `claimedwhen_utc`, `motivation`, `rule`, `subreddit`, `pagename`, `tag`, `id`) VALUES ('$redditor', '$pid', '$cid', '$dt2', '$t', '$points', 'false', NULL, NULL, '$motivation', 'subtractPKarmaForTagNoProblemUsedInAnAbsurdWayConditionally', '$subreddit', '$pagename', '$tag', NULL);";
		//echo "<br><br>$sql";
		mysqli_query($GLOBALS["___mysqli_ston"], $sql);
	}
}




function givePKarmaForCorrectUseIApologizeTagConditionally($redditorJTagAgent, $angryRedditor, $pid, $cid, $subreddit, $pagename, $tag) {
	$query = "SELECT * FROM prima_karmagift WHERE redditor='$redditorJTagAgent' AND pageid='$pid' AND commentid='$cid'";
	$result3 = mysqli_query($GLOBALS["___mysqli_ston"], $query);
	$count3 = mysqli_num_rows($result3);
	if ($count3 > 0) {
		//echo "$redditorJTagAgent has already been given a penalty";
	}
	else {
		$actualPoints = 15;
 		//echo "<br><br>Awarding $actualPoints to $redditorJTagAgent";
		$motivation = "You have received $actualPoints for apologizing to $angryRedditor by using this tag.";
		$dt2=date("Y-m-d H:i:s");
		$t = time();
		$sql = "INSERT INTO `redditawkward_com`.`prima_karmagift` (`redditor`, `pageid`, `commentid`, `whenf`, `utc`, `points`, `claimed`, `claimedwhen`, `claimedwhen_utc`, `motivation`, `rule`, `subreddit`, `pagename`, `tag`, `id`) VALUES ('$redditorJTagAgent', '$pid', '$cid', '$dt2', '$t', '$actualPoints', 'false', NULL, NULL,  '$motivation', 'givePKarmaForCorrectUseIApologizeTagConditionally', '$subreddit', '$pagename', '$tag', NULL);";
		////echo "<br><br>$sql";
		queryDoorslamAndExpectConditionally($redditorJTagAgent, $angryRedditor, $subreddit, $pid, $pagename, "comment-tag{i.apologize}", $cid, $sql);
	}
}


function subtractPKarmaForTagQAbsurdApologyConditionally($redditor, $pid, $cid, $subreddit, $pagename, $tag) {
	$query = "SELECT * FROM prima_karmagift WHERE redditor='$redditor' AND pageid='$pid' AND commentid='$cid'";
	//echo "<br>$query";
	$result3 = mysqli_query($GLOBALS["___mysqli_ston"], $query);
	$count3 = mysqli_num_rows($result3);
	if ($count3 > 0) {
		//echo "<br>$redditor has already been given a penalty";
	}
	else {
		$actualPoints = -10;
 		//echo "<br><br>Awarding $actualPoints to $redditor";
		$motivation = "You have received a penalty of $actualPoints, because you have apologized without using it directly.";
		$dt2=date("Y-m-d H:i:s");
		$t = time();
		$sql = "INSERT INTO `redditawkward_com`.`prima_karmagift` (`redditor`, `pageid`, `commentid`, `whenf`, `utc`, `points`, `claimed`, `claimedwhen`, `claimedwhen_utc`, `motivation`, `rule`, `subreddit`, `pagename`, `tag`, `id`) VALUES ('$redditor', '$pid', '$cid', '$dt2', '$t', '$actualPoints', 'false', NULL, NULL, '$motivation', 'subtractPKarmaForTagQAbsurdApologyConditionally', '$subreddit', '$pagename', '$tag', NULL);";
		//echo "<br><br>$sql";
		 mysqli_query($GLOBALS["___mysqli_ston"], $sql);
	}
}


function subtractPKarmaForSelfDisciplineViolationConditionally($redditor, $pid, $cid, $subreddit, $pagename, $tag) {
	$query = "SELECT * FROM prima_karmagift WHERE redditor='$redditor' AND pageid='$pid' AND commentid='$cid'";
	$result3 = mysqli_query($GLOBALS["___mysqli_ston"], $query);
	$count3 = mysqli_num_rows($result3);
	if ($count3 > 0) {
		//echo "<br>$redditor has already been given a penalty";
	}
	else {
		$actualPoints = -10;
 		//echo "<br><br>Awarding $actualPoints to $redditor";
		$motivation = "You have received a penalty of $actualPoints, because you used this tag and took part in the discussion despite this.";
		$dt2=date("Y-m-d H:i:s");
		$t = time();
		$sql = "INSERT INTO `redditawkward_com`.`prima_karmagift` (`redditor`, `pageid`, `commentid`, `whenf`, `utc`, `points`, `claimed`, `claimedwhen`, `claimedwhen_utc`, `motivation`, `rule`, `subreddit`, `pagename`, `tag`, `id`) VALUES ('$redditor', '$pid', '$cid', '$dt2', '$t', '$actualPoints', 'false', NULL, NULL, '$motivation', 'subtractPKarmaForSelfDisciplineViolationConditionally', '$subreddit', '$pagename', '$tag', NULL);";
		//echo "<br><br>$sql";
		mysqli_query($GLOBALS["___mysqli_ston"], $sql);
	}
}

function subtractPKarmaForTagMNotLevel2ViolationConditionally($redditor, $pid, $cid, $subreddit, $pagename) {
	$query = "SELECT * FROM prima_karmagift WHERE redditor='$redditor' AND pageid='$pid' AND commentid='$cid'";
	$result3 = mysqli_query($GLOBALS["___mysqli_ston"], $query);
	$count3 = mysqli_num_rows($result3);
	if ($count3 > 0) {
		//echo "<br>$redditor has already been given a penalty";
	}
	else {
		$actualPoints = -10;
 		//echo "<br><br>Awarding $actualPoints to $redditor";
		$motivation = "You have received a penalty of $actualPoints because you used $tag as a an answer to a comment and not the original post or link.";
		$dt2=date("Y-m-d H:i:s");
		$t = time();
		$sql = "INSERT INTO `redditawkward_com`.`prima_karmagift` (`redditor`, `pageid`, `commentid`, `whenf`, `utc`, `points`, `claimed`, `claimedwhen`, `claimedwhen_utc`, `motivation`, `rule`, `subreddit`, `pagename`, `tag`, `id`) VALUES ('$redditor', '$pid', '$cid', '$dt2', '$t', '$actualPoints', 'false', NULL, NULL, '$motivation', 'subtractPKarmaForTagMNotLevel2ViolationConditionally', '$subreddit', '$pagename', '$tag', NULL);";
		//echo "<br><br>$sql";
		 mysqli_query($GLOBALS["___mysqli_ston"], $sql);
	}
}

function givePKarmaConditionally($tag, $redditorWaiting, $pid, $cid, $subreddit, $pagename, $motivation, $points) {
	$query = "SELECT * FROM prima_karmagift WHERE redditor='$redditorWaiting' AND pageid='$pid' AND commentid='$cid'";
	$result3 = mysqli_query($GLOBALS["___mysqli_ston"], $query);
	$count3 = mysqli_num_rows($result3);
	if ($count3 > 0) {
		//echo "<br>$redditorWaiting has already been given Awkward Karma";
	}
	else {
 		//echo "<br><br>Awarding $points to $redditorWaiting";
		$dt2=date("Y-m-d H:i:s");
		$t = time();
		$tagFullName = "comment-tag{" + $tag + "}";
		$points = $awkwardKarmaRewards[$tagFullName];
		$sql = "INSERT INTO `redditawkward_com`.`prima_karmagift` (`redditor`, `pageid`, `commentid`, `whenf`, `utc`, `points`, `claimed`, `claimedwhen`, `claimedwhen_utc`, `claimedwhen_utc`, `motivation`, `rule`, `subreddit`, `pagename`, `tag`, `id`) VALUES ('$redditorWaiting', '$pid', '$cid', '$dt2', '$t', '$points', 'false', NULL, NULL, '$motivation', 'givePKarmaForUseOfTagConditionally', '$subreddit', '$pagename', '$tag', NULL);";
		//echo "<br><br>$sql";
		mysqli_query($GLOBALS["___mysqli_ston"], $sql);
	}
}

function givePKarmaForUseOfTagConditionally($tag, $redditorWaiting, $pid, $cid, $subreddit, $pagename) {
	$query = "SELECT * FROM prima_karmagift WHERE redditor='$redditorWaiting' AND pageid='$pid' AND commentid='$cid'";
	$result3 = mysqli_query($GLOBALS["___mysqli_ston"], $query);
	$count3 = mysqli_num_rows($result3);
	if ($count3 > 0) {
		//echo "<br>$redditorWaiting has already been given Awkward Karma";
	}
	else {
        $points = 5;
 		//echo "<br><br>Awarding $points to $redditorWaiting";
		$motivation = "You have received $points for using this tag.";
		$dt2=date("Y-m-d H:i:s");
		$t = time();
		$tagFullName = "comment-tag{" + $tag + "}";
		$points = $awkwardKarmaRewards[$tagFullName];
		$sql = "INSERT INTO `redditawkward_com`.`prima_karmagift` (`redditor`, `pageid`, `commentid`, `whenf`, `utc`, `points`, `claimed`, `claimedwhen`, `claimedwhen_utc`, `motivation`, `rule`, `subreddit`, `pagename`, `tag`, `id`) VALUES ('$redditorWaiting', '$pid', '$cid', '$dt2', '$t', '$points', 'false', NULL, NULL, '$motivation', 'givePKarmaForUseOfTagConditionally', '$subreddit', '$pagename', $tag, NULL);";
		////echo "<br><br>$sql";
		mysqli_query($GLOBALS["___mysqli_ston"], $sql);
	}
}

function subtractPKarmaForPlainAwkwardTagViolationConditionally($redditor, $pid, $cid, $subreddit, $pagename, $motivation, $actualPoints) {
	$query = "SELECT * FROM prima_karmagift WHERE redditor='$redditor' AND pageid='$pid' AND commentid='$cid'";
	$result3 = mysqli_query($GLOBALS["___mysqli_ston"], $query);
	$count3 = mysqli_num_rows($result3);
	if ($count3 > 0) {
		//echo "<br>$redditor has already been given his penalty for subtractPKarmaForPlainAwkwardTagViolationConditionally";
	}
	else {
 		//echo "<br><br>Penalty: $actualPoints to $redditor";
		$dt2=date("Y-m-d H:i:s");
		$t = time();
		$sql = "INSERT INTO `redditawkward_com`.`prima_karmagift` (`redditor`, `pageid`, `commentid`, `whenf`, `utc`, `points`, `claimed`, `claimedwhen`, `claimedwhen_utc`, `motivation`, `rule`, `subreddit`, `pagename`, `tag`, `id`) VALUES ('$redditor', '$pid', '$cid', '$dt2', '$t', '$actualPoints', 'false', NULL, NULL, '$motivation', 'subtractPKarmaForPlainAwkwardTagViolationConditionally', '$subreddit', '$pagename', 'comment-tag{awkward}', NULL);";
		//echo "<br><br>$sql";
		mysqli_query($GLOBALS["___mysqli_ston"], $sql);
	}
}

function subtractPKarmaForWaitsForYourReplyOnlySelfDisciplineViolationConditionally($redditor, $pid, $cid, $intruderRedditor, $subreddit, $pagename) {
	$query = "SELECT * FROM prima_karmagift WHERE redditor='$redditor' AND pageid='$pid' AND commentid='$cid'";
	$result3 = mysqli_query($GLOBALS["___mysqli_ston"], $query);
	$count3 = mysqli_num_rows($result3);
	if ($count3 > 0) {
		//echo "<br>$redditor has already been given a penalty";
	}
	else {
		$actualPoints = -10;
 		//echo "<br><br>Awarding $actualPoints to $redditor";
		$motivation = "You have received a penalty of $actualPoints because you used tag and got into the following discussion despite this.";
		$dt2=date("Y-m-d H:i:s");
		$t = time();
		$sql = "INSERT INTO `redditawkward_com`.`prima_karmagift` (`redditor`, `pageid`, `commentid`, `whenf`, `utc`, `points`, `claimed`, `claimedwhen`, `claimedwhen_utc`, `motivation`, `rule`, `subreddit`, `pagename`, `tag`, `id`) VALUES ('$redditor', '$pid', '$cid', '$dt2', '$t', '$actualPoints', 'false', NULL, NULL, '$motivation', 'subtractPKarmaForWaitsForYourReplyOnlySelfDisciplineViolationConditionally', '$subreddit', '$pagename', 'comment-tag{waits.for.your.reply.only}', NULL);";
		//echo "<br><br>$sql";
		mysqli_query($GLOBALS["___mysqli_ston"], $sql);
	}
}

function givePKarmaForWaitsForYourReplyOnlyUnconditionally($kindAnsweringRedditor, $tagCAgentExpectingAnswer, $pid, $cid, $subreddit, $pagename) {
	//echo "<br><br>award show2*!!<br><br>";
	$wildestDreamPoints = ( (time() - $utc) / (60*10));
	$actualPoints = 10;
 	//echo "<br><br>Awarding $actualPoints to $kindAnsweringRedditor";
	$motivation = "Because you were nice and replied to the comment from $tagCAgentExpectingAnswer who specifically wanted you to answer.";
	$dt2=date("Y-m-d H:i:s");
	$t = time();
	$sql = "INSERT INTO `redditawkward_com`.`prima_karmagift` (`redditor`, `pageid`, `commentid`, `whenf`, `utc`, `points`, `claimed`, `claimedwhen`, `motivation`, `rule`, `subreddit`, `pagename`, `tag`, `id`) VALUES ('$kindAnsweringRedditor', '$pid', '$cid' '$dt2', '$t', '$actualPoints', 'false', NULL, NULL, '$motivation', 'givePKarmaForWaitsForYourReplyOnlyUnconditionally', '$subreddit', '$pagename', 'comment-tag{waits.for.your.reply.only}', NULL);";
	////echo "<br><br>query: $sql<br><br>";
	queryDoorslamAndExpectConditionally($tagCAgentExpectingAnswer, $kindAnsweringRedditor, $subreddit, $pid, $pagename, "comment-tag{waits.for.your.reply.only}", $cid, $sql);

}


function givePKarmaForTagWaitsForAnyPersonConditionally($kindAnsweringRedditor, $AnsweredAtThisTimeUTC, $backwiseOriginalPoster, $pid, $cid, $subreddit, $pagename) {
	$query = "SELECT * FROM prima_karmagift WHERE redditor='$kindAnsweringRedditor' AND pageid='$pid' AND commentid='$cid'";
	$result3 = mysqli_query($GLOBALS["___mysqli_ston"], $query);
	$count3 = mysqli_num_rows($result3);
	if ($count3 > 0) {
		//echo "<br>$redditor has already been awarded!";
	}
	else {
		//echo "award show*!!<br><br>";
		$wildestDreamPoints = ( (time() - $AnsweredAtThisTimeUTC) / (60*10));
		$actualPoints = $wildestDreamPoints;
		if ($actualPoints < 5) $actualPoints = 5;
		if ($actualPoints > 20) $actualPoints = 20;
	 	//echo "Awarding $actualPoints to $kindAnsweringRedditor";
		$motivation = "Because you were nice in replying to the comment written by $backwiseOriginalPoster!";
		$dt2=date("Y-m-d H:i:s");
		$t = time();
		$sql = "INSERT INTO `redditawkward_com`.`prima_karmagift` (`redditor`, `pageid`, `commentid`, `whenf`, `utc`, `points`, `claimed`, `claimedwhen`, `claimedwhen_utc`, `motivation`, `rule`, `subreddit`, `pagename`, `tag`, `id`) VALUES ('$kindAnsweringRedditor', '$pid', '$cid', '$dt2', '$t', '$actualPoints', 'false', NULL, NULL, '$motivation', 'givePKarmaForTagWaitsForAnyPersonConditionally', '$subreddit', '$pagename', 'comment-tag{waits.for.anyone}', NULL);";
		queryDoorslamAndExpectConditionally($backwiseOriginalPoster, $kindAnsweringRedditor, $subreddit, $pid, $pagename, "comment-tag{waits.for.anyone}", $cid, $sql);
		////echo "query: $sql<br><br>";
	}

}







function queryDoorslamAndExpectConditionally($firstPerson, $secondPerson, $subreddit, $pageid, $pagename, $tag, $cid, $sql) {
	$flagged = false;
	if (needsToApologize($firstPerson, $secondPerson)) {
		$flagged = true;
		$dt2=date("Y-m-d H:i:s");
		$t = time();
		$motivation = "You need to apologize to $secondPerson before you should speak to him/her.";
		$sql = "INSERT INTO `redditawkward_com`.`prima_karmagift` (`redditor`, `pageid`, `commentid`, `whenf`, `utc`, `points`, `claimed`, `claimedwhen`, `claimedwhen_utc`, `motivation`, `rule`, `subreddit`, `pagename`, `tag`, `id`) VALUES ('$firstPerson', '$pageid', '$cid',  '$dt2', '$t', '-5', 'false', NULL, NULL, '$motivation', 'givePKarmaForTagWaitsForAnyPersonConditionally', '$subreddit', '$pagename', '$tag', NULL);";
		////echo "query: $sql<br><br>";
		mysqli_query($GLOBALS["___mysqli_ston"], $sql);
	}
	if (needsToApologize($secondPerson, $firstPerson)) {
		$flagged = true;
		$dt2=date("Y-m-d H:i:s");
		$t = time();
		$notificationMessage = "$secondPerson needs to apologize to  before he/she should speak to you.";
		$rule = "General Rules: §4 Redditors shouldn't talk to each other after A has used comment-tag{i.will.not.reply.and.expect.apology}. When A has apologized they can talk to each other again.";
		////echo "query: $sql<br><br>";
		createNotification($pageid, $cid, $firstPerson, $subreddit, $pagename , $notificationMessage, $tag, $rule);
	}
	if (!$flagged) {
		mysqli_query($GLOBALS["___mysqli_ston"], $sql);
	}

}
		


function createCTagNotificationForSecondPerson($pageid, $cid, $wantedAnswerersRedditorName, $cursoryRedditorWaiting, $subreddit, $pagename) {
	$query = "SELECT * FROM prima_notification WHERE redditor='$wantedAnswerersRedditorName' AND pageid='$pageid' AND commentid='$cid'";
	$result3 = mysqli_query($GLOBALS["___mysqli_ston"], $query);
	$count3 = mysqli_num_rows($result3);
	if ($count3 > 0) {
		//echo "$wantedAnswerersRedditorName has already been notified!";
	}
	else {
		$notification = "The redditor $cursoryRedditorWaiting awaits a direct answer from you. Please click the link to see his or her comment.";
		$dt2=date("Y-m-d H:i:s");
		$t = time();
		$sql = "INSERT INTO `redditawkward_com`.`prima_notification` (`redditor`, `pageid`, `commentid`, `whenf`, `utc`, `claimed`, `claimedwhen`, `claimedwhen_utc`, `motivation`, `tag`, `rule`, `subreddit`, `pagename`) VALUES ('$wantedAnswerersRedditorName', '$pageid', '$cid', '$dt2', '$t', 'false', NULL, NULL, '$notification', 'comment-tag{waits.for.your.reply.only}', 'commentatorExpectsAnswerFrom', '$subreddit', '$pagename');";
		//echo "<br><br>$sql";
		mysqli_query($GLOBALS["___mysqli_ston"], $sql);
	}
}


function anybodyOutThereWhoHasMeAsDirectParentHeAskedKnowinglyNoActuallyINeedAllOfMyKidsAndNotMyselfByTheWay($jsonObj, $cid, $cTagAgentRedditorName) {
	//echo "<br><br>looking for this id: $cid<br><br>tag agent: $cTagAgentRedditorName";
	return traverseF($jsonObj, $cid, $cTagAgentRedditorName);
}

function anybodyOutThereWhoHasMeAsDirectParentHeAskedKnowinglyNoActuallyINeedAllOfMyKidsMyselfIncludedByTheWay($jsonObj, $cid) {
	//echo "<br><br>looking for this id: $cid<br><br>tag agent: $cTagAgentRedditorName";
	return traverseF($jsonObj, $cid, "-");
}


function anybodyOutThereWhoHasMeAsParentHeAskedKnowinglyNoActuallyINeedTheOLDESTOneOfMyKidsAndNotMyselfByTheWay($jsonObj, $cid, $originalBackwiseDaddyPoster) {
	return traverseE($jsonObj, $cid, $originalBackwiseDaddyPoster);
}







/*
var jsonsOfCommentPagesArray;
var arrayOfPermalinks;
var arrayOfMainPagePostIds;

function processMainPage3() {
    console.log("bmput!!");
    arrayOfPermalinks = [];
    arrayOfMainPagePostIds = [];
    var jsonObj = JSON.parse(pageJson);
    var c = jsonObj.data.children;
    console.log("children: " + c.length);
    for (var i = 0; i < c.length; i++) {
        console.log("a");
        arrayOfPermalinks.push("https://www.reddit.com/" + stripTrailingSlash(c[i].data.permalink));
        console.log("b" + "https://www.reddit.com/" + stripTrailingSlash(c[i].data.permalink));
        arrayOfMainPagePostIds.push(c[i].data.id);
        console.log("c" + c[i].data.id );
    }
    permaHaarPointer = 0;
    jsonsOfCommentPagesArray = [];
    console.log("d" + arrayOfPermalinks.length );
    if (arrayOfPermalinks.length > 0) processMainPage4();
}

var permaHaarPointer;
function processMainPage4() {
    if (permaHaarPointer < arrayOfPermalinks.length) {
        console.log("e");
        var u = arrayOfPermalinks[permaHaarPointer] + ".json";
        console.log("url: " + u);
        var xhr = new XMLHttpRequest();
        xhr.open("GET", u, true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState == 4) {
                jsonsOfCommentPagesArray.push(xhr.responseText);
                //console.log("plimseplimseplimseplimseplimseplimseplimse" + jsonsOfCommentPagesArray.length);
                processMainPage4();
            }
        }
        xhr.send();
        permaHaarPointer++;
    }
    else { // finished cycling!
        console.log("boogy boogy boogy boogy boogy boogy boogy boogy ");
        runScriptP(arrayOfMainPagePostIds, jsonsOfCommentPagesArray);

    }
}
}
*/




function traverseA($x, &$in_arr = array()) {  // <-- note the reference '&'
  static $rememberOThatIdYeah;
  if (is_array($x)) {
    traverseArrayA($x, $in_arr);
	} else if (is_object($x)) {
    traverseObjectA($x, $in_arr);
  } else {
  }
	return $in_arr;
}
 
function traverseArrayA($arr, &$in_arr = array()) {
  foreach ($arr as $x) {
    traverseA($x, $in_arr);
	}
}

function traverseObjectA($obj, &$in_arr = array()) {
  $array = get_object_vars($obj);
  $properties = array_keys($array);
  foreach($properties as $key) {
			
      ////console.log(level + "  " + key + ":");
			if ($key === "parent_id") {
				if (!$in_arr[$rememberOThatIdYeah] ) {
					$in_arr[$rememberOThatIdYeah] = new stdClass();
				} 
				$in_arr[$rememberOThatIdYeah]->parent_id = substr($array['parent_id'], 3);
				$in_arr[$rememberOThatIdYeah]->id = $rememberOThatIdYeah;
			}
			if ("id" === $key) { $rememberOThatIdYeah = $array['id']; }
      traverseA($obj->$key, $in_arr);
	}
}






function traverseB($x, $parentIdToLookFor, &$pointedTo = array()) {
	//static $pointedTo;
	//if ($pointedTo[0]) return $pointedTo;
  if (is_array($x)) {
    traverseArrayB($x, $parentIdToLookFor, $pointedTo);
  }
  else if (is_object($x)) {	
	////echo "<br>" . $lookOut . " ". $lookOutForId;
    	traverseObjectB($x, $parentIdToLookFor, $pointedTo);
  }
  else {
	
  }
	
	return $pointedTo;
}
 
function traverseArrayB($arr, $parentIdToLookFor, &$pointedTo = array()) {
  foreach ($arr as $x) {
    traverseB($x, $parentIdToLookFor, $pointedTo);
  }
}

function traverseObjectB($obj, $parentIdToLookFor, &$pointedTo = array()) {
  $array = get_object_vars($obj);
  $properties = array_keys($array);
  foreach($properties as $key) {
		if ($key === "parent_id") {
			$string2 = $array['parent_id'];
			////echo "<br><br>string2: $string2<br>parentIdToLookFor: $parentIdToLookFor";
			
			if (0 === strpos($string2, 't1_') && $parentIdToLookFor === $string2) {
				if (!$pointedTo[0]) $pointedTo[0] = new stdClass();
				$pointedTo[0]->val = 'yes';
			}
			else {
				////echo "matcho!";
				/*if (!$pointedTo[0]) $pointedTo[0] = new stdClass();
				$pointedTo[0]->val = 'no';*/
			}
		}
		traverseB($obj->$key, $parentIdToLookFor, $pointedTo);
	}
}


function traverseC($x, $idToLookFor, &$parentC = array()) {
	static $rememberOThatIdYeah;
  if (is_array($x)) {
    traverseArrayC($x, $idToLookFor, $parentC);
  }
  else if (is_object($x)) {	
////echo "<br>" . $lookOut . " ". $lookOutForId;
    traverseObjectC($x, $idToLookFor, $parentC);
  }
  else {
	
  }
	return $parentC;
}
 
function traverseArrayC($arr, $idToLookFor, &$parentC = array()) {
  foreach ($arr as $x) {
    traverseC($x, $idToLookFor, $parentC);
  }
}

function traverseObjectC($obj, $idToLookFor, &$parentC = array()) {
  $array = get_object_vars($obj);
  $properties = array_keys($array);
  foreach($properties as $key) {
		if ($key == "parent_id"  && $idToLookFor === $rememberOThatIdYeah) { $parentC[0] = new stdClass(); $parentC[0] = substr($array['parent_id'], 3); }
		if ($key == "id") { $rememberOThatIdYeah = $array['id']; }
		traverseC($obj->$key, $idToLookFor, $parentC);
  }
}



function traverseG($x, &$in_arr = array()) {  // <-- note the reference '&'
  if (is_array($x)) {
    traverseArrayG($x, $in_arr);
  }
  else if (is_object($x)) {	
	////echo "<br>" . $lookOut . " ". $lookOutForId;
    	traverseObjectG($x, $in_arr);
  }
  else {
	
  }
	return $in_arr;
}
 
function traverseArrayG($arr, &$in_arr = array()) {
  foreach ($arr as $x) {
    traverseG($x, $in_arr);
  };
}

function traverseObjectG($obj, &$in_arr = array()) {
  $array = get_object_vars($obj);
  $properties = array_keys($array);
  foreach($properties as $key) {
		if ($key == "body") {
			////echo "<br><br>Id:" . $rememberOThatIdYeah . " Body:" . $array['body'];
			$in_arr[$rememberOThatIdYeah]->body = $array['body'];
		}
		if ($key == "parent_id") { if (!$in_arr[$rememberOThatIdYeah] ) { $in_arr[$rememberOThatIdYeah] = new stdClass();} $in_arr[$rememberOThatIdYeah]->parent_id = substr($array['parent_id'], 3); }
		if ($key == "author") { if (!$in_arr[$rememberOThatIdYeah] ) { $in_arr[$rememberOThatIdYeah] = new stdClass();} $in_arr[$rememberOThatIdYeah]->author = $array['author']; }
		if ($key == "id") { $rememberOThatIdYeah = $array['id']; }
		traverseG($obj->$key, $in_arr);
  }
}








function traverseE($x, $commentIdToLookFor, $originalBackwiseDaddyPoster, &$oldestIdeaEverKnownToManFollowedByOldestTimeFollowedByAuthor = array()) {  // <-- note the reference '&'
	if (!$oldestIdeaEverKnownToManFollowedByOldestTimeFollowedByAuthor[0]->utc) {
		if (!$oldestIdeaEverKnownToManFollowedByOldestTimeFollowedByAuthor[0] ) { $oldestIdeaEverKnownToManFollowedByOldestTimeFollowedByAuthor[0] = new stdClass();}
		$oldestIdeaEverKnownToManFollowedByOldestTimeFollowedByAuthor[0]->id = "";
		$oldestIdeaEverKnownToManFollowedByOldestTimeFollowedByAuthor[0]->utc = time();
		$oldestIdeaEverKnownToManFollowedByOldestTimeFollowedByAuthor[0]->author = "";
	}
  if (is_array($x)) {
		////echo "<br><br> array!";
    traverseArrayE($x, $commentIdToLookFor, $originalBackwiseDaddyPoster, $oldestIdeaEverKnownToManFollowedByOldestTimeFollowedByAuthor);
  }
  else if (is_object($x)) {	
	////echo "<br>" . $lookOut . " ". $lookOutForId;	
			////echo "<br><br> object!";
    	traverseObjectE($x, $commentIdToLookFor, $originalBackwiseDaddyPoster, $oldestIdeaEverKnownToManFollowedByOldestTimeFollowedByAuthor);
  }
  else {
	
  }
	return $oldestIdeaEverKnownToManFollowedByOldestTimeFollowedByAuthor;
}
 
function traverseArrayE($arr, $commentIdToLookFor, $originalBackwiseDaddyPoster, &$oldestIdeaEverKnownToManFollowedByOldestTimeFollowedByAuthor = array()) {
  foreach ($arr as $x) {
    traverseE($x, $commentIdToLookFor, $originalBackwiseDaddyPoster, $oldestIdeaEverKnownToManFollowedByOldestTimeFollowedByAuthor);
  };
}

function traverseObjectE($obj, $commentIdToLookFor, $originalBackwiseDaddyPoster, &$oldestIdeaEverKnownToManFollowedByOldestTimeFollowedByAuthor = array()) {
	static $rememberOThatIdYeah, $rememberOThatParentIdYee, $rememberOThatAuthorYeah;
	$array = get_object_vars($obj);
	$properties = array_keys($array);
	foreach($properties as $key) {
		if ($key == "created_utc") {
			$created_utc = $array['created_utc'];
			//echo "<br><br>Id:" . $rememberOThatIdYeah;
			//echo "<br><br>look for Id:" . $commentIdToLookFor;
			//echo "<br><br>Parent id:" . $rememberOThatParentIdYee;
			//echo "<br><br>created_utc:" . $created_utc;
			//echo "<br><br>-oldest id:" . $oldestIdeaEverKnownToManFollowedByOldestTimeFollowedByAuthor[0]->id;
			//echo "<br><br>-oldest times:" . $oldestIdeaEverKnownToManFollowedByOldestTimeFollowedByAuthor[0]->utc;
			//echo "<br><br><br><br><br><br>";
			if ($commentIdToLookFor === $rememberOThatParentIdYee && $rememberOThatAuthorYeah !== "[deleted]") {
				if ($originalBackwiseDaddyPoster !== $rememberOThatAuthorYeah) {
					//echo "<br><br>It's a match!!!";
					if ($created_utc < $oldestIdeaEverKnownToManFollowedByOldestTimeFollowedByAuthor[0]->utc) { 
						////echo "<br><br>Kartoffel!";
						$oldestIdeaEverKnownToManFollowedByOldestTimeFollowedByAuthor[0]->id = $rememberOThatIdYeah; 
						$oldestIdeaEverKnownToManFollowedByOldestTimeFollowedByAuthor[0]->utc = $created_utc; 
						$oldestIdeaEverKnownToManFollowedByOldestTimeFollowedByAuthor[0]->author = $rememberOThatAuthorYeah; 
					}
					else {
						//echo "<br><br>ALERT: Schnuffel!";
					}
				}
			}
		}
		if ($key == "parent_id") {
			$rememberOThatParentIdYee = substr($array['parent_id'], 3);
		 	////echo "<br><br>parent id: " . $rememberOThatParentIdYee;
		}
		if ($key == "author") { $rememberOThatAuthorYeah = $array['author']; /*//echo "<br><br>asdf author: " . $rememberOThatAuthorYeah;*/ }
		if ($key == "id") { $rememberOThatIdYeah = $array['id']; /*//echo "<br><br>asdf id: " . $rememberOThatIdYeah;*/ }
		traverseE($obj->$key, $commentIdToLookFor, $originalBackwiseDaddyPoster, $oldestIdeaEverKnownToManFollowedByOldestTimeFollowedByAuthor);
	}
}









function traverseF($x, $idWaitingToBeMatchedByOneOrMoreParentIds, $cTagAgentRedditorName, &$allMyDirectChildrenExceptErMe = array()) {  // <-- note the reference '&'
	//if (!$allMyDirectChildrenExceptErMe[0]->author) {
		//if (!$allMyDirectChildrenExceptErMe[0] ) { $allMyDirectChildrenExceptErMe[0] = new stdClass();}
		//$allMyDirectChildrenExceptErMe[0]->id = "";
		//$allMyDirectChildrenExceptErMe[0]->author = "";
	//}
  if (is_array($x)) {
		////echo "<br><br> array!";
    traverseArrayF($x, $idWaitingToBeMatchedByOneOrMoreParentIds, $cTagAgentRedditorName, $allMyDirectChildrenExceptErMe);
  }
  else if (is_object($x)) {	
	////echo "<br>" . $lookOut . " ". $lookOutForId;	
			////echo "<br><br> object!";
    	traverseObjectF($x, $idWaitingToBeMatchedByOneOrMoreParentIds, $cTagAgentRedditorName, $allMyDirectChildrenExceptErMe);
  }
  else {
	
  }
	return $allMyDirectChildrenExceptErMe;
}
 
function traverseArrayF($arr, $idWaitingToBeMatchedByOneOrMoreParentIds, $cTagAgentRedditorName, &$allMyDirectChildrenExceptErMe = array()) {
  foreach ($arr as $x) {
    traverseF($x, $idWaitingToBeMatchedByOneOrMoreParentIds, $cTagAgentRedditorName, $allMyDirectChildrenExceptErMe);
  };
}

function traverseObjectF($obj, $idWaitingToBeMatchedByOneOrMoreParentIds, $cTagAgentRedditorName, &$allMyDirectChildrenExceptErMe = array()) {
	static $rememberOThatIdYeah, $rememberOThatParentIdYee, $rememberOThatAuthorYeah;
  $array = get_object_vars($obj);
  $properties = array_keys($array);
  foreach($properties as $key) {
	if ($key == "parent_id") {
		$rememberOThatParentIdYee = substr($array['parent_id'], 3);
		//echo "<br><br>parentIdToLookFor: $idWaitingToBeMatchedByOneOrMoreParentIds<br><br>rememberOThatParentIdYee: $rememberOThatParentIdYee<br><br>id: $rememberOThatIdYeah<br><br>rememberOThatAuthorYeah: $rememberOThatAuthorYeah<br><br>cTagAgentRedditorName: $cTagAgentRedditorName";
		if ($idWaitingToBeMatchedByOneOrMoreParentIds === $rememberOThatParentIdYee) {
			//echo "<br><br>Found!";
			if ($rememberOThatAuthorYeah !== $cTagAgentRedditorName) {
				$a = new stdClass();
				$a->id = $rememberOThatIdYeah;
				//echo "<br><br> rememberOThatIdYeah: $rememberOThatIdYeah<br><br>rememberOThatAuthorYeah: $rememberOThatAuthorYeah<br><br>cTagAgentRedditorName: $cTagAgentRedditorName";
				$a->author = $rememberOThatAuthorYeah;
				$ptr = sizeof($allMyDirectChildrenExceptErMe);
				//echo "<br><br> $ptr";
				$allMyDirectChildrenExceptErMe[$ptr] = $a;
			}
		}
	 	////echo "<br><br>parent id: " . $rememberOThatParentIdYee;
	}
	if ($key == "author") { $rememberOThatAuthorYeah = $array['author']; /*//echo "<br><br>asdf author: " . $rememberOThatAuthorYeah;*/ }
	if ($key == "id") { $rememberOThatIdYeah = $array['id']; /*//echo "<br><br>asdf id: " . $rememberOThatIdYeah;*/ }
	traverseF($obj->$key, $idWaitingToBeMatchedByOneOrMoreParentIds, $cTagAgentRedditorName, $allMyDirectChildrenExceptErMe);
  }
}











function die3($a) {
	$data2['success'] = "false";
	$data2['message'] = $a;
	die(json_encode($data2));
}

