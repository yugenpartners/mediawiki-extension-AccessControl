<?php

/**
 * MediaWiki extension that enables group access restriction on a page-by-page
 * basis contributed by Martin Mueller (http://blog.pagansoft.de) based on
 * accesscontrol.php by Josh Greenberg
 * @package MediaWiki
 * @subpackage Extensions
 * @author Aleš Kapica
 * @copyright 2008 Aleš Kapica
 * @licence GNU General Public Licence
 */


if( !defined( 'MEDIAWIKI' ) ) {
	echo ( "This file is an extension to the MediaWiki software and cannot be used standalone.\n" );
	die();
}


// $wgAccessControlMessages = false;			// if set to true, show a line on of each secured page, which says, which groups are allowed to see this page.
$wgUseMediaWikiGroups = true;				// use the groups from MediaWiki with Usergroup pages
$wgAccessControlDebug = true;				// Debug log on if set to true
$wgAccessControlDebugFile = "/var/www/debug.txt";	// Path to the debug log

$wgAdminCanReadAll = true;				// sysop users can read all restricted pages
$wgAllowUserList = true;				// Users can managed access list from userspace


$wgExtensionFunctions[] = 'wfAccessControlExtension';
$wgExtensionCredits['specialpage']['AccessControl'] = array(
	'name'           => 'AccessControlExtension',
	'svn-date' => '$LastChangedDate$',
	'svn-revision' => '$LastChangedRevision$',
	'author'         => array( 'Aleš Kapica' ),
	'description'    => 'Group based access control on page based on original script by Martin Gondermann [ http://www.mediawiki.org/wiki/Extension:Group_Based_Access_Control | Extension:Group_Based_Access_Control ]',
	'descriptionmsg' => 'accesscontrol-desc',
	'url'            => 'http://www.mediawiki.org/wiki/Extension:AccessControl',
);


$dir = dirname(__FILE__) . '/';
$wgExtensionMessagesFiles['AccessControl'] = $dir . 'AccessControl.i18n.php';


// Hook the conTolEditAccess function in the edit action
$wgHooks['AlternateEdit'][] = 'controlEditAccess';


//Hook the userCan function for bypassing the cache (bad bad hackaround)
$wgHooks['userCan'][] = 'hookUserCan';


// Hook the controlUserGroupPageAccess in the Article to allow access to the "Usergroup:..." pages only to the sysop
$wgHooks['ArticleAfterFetchContent'][] = 'controlUserGroupPageAccess';

function debugme($input) {
	/* handles the debug output to a debug file */
	global $wgAccessControlDebug, $wgAccessControlDebugFile;
	if ($wgAccessControlDebug) {
		$f = fopen($wgAccessControlDebugFile, "a+");
		fputs($f, $input."\n");
		fclose($f);
	}
}


function wfAccessControlExtension() {
	/* This is the hook function. It adds the tag to the wiki parser
	   and tells it what callback function to use. */
	global $wgExtensionMessagesFiles,  $wgMessageCache, $wgParser;
	$wgParser->setHook( "accesscontrol", "doControlUserAccess" );
	$wgParser->disableCache();

	// loading extension messages
	require_once($wgExtensionMessagesFiles['AccessControl']);
	$wgMessageCache->addMessagesByLang($messages);
}


function doControlUserAccess( $input, $argv, &$parser ) {
	/* Funcion called by wfAccessControlExtension */
	debugme("doControlUserAccess : start accesscontrol with input: ".$input);
}


function accessControl($groups){
	/* This function controled user access on page. If user is "sysop"
	   return true allways. */
	global $wgGroupPermissions, $wgUseMediaWikiGroups, $wgUser, $wgAdminCanReadAll;

	$groupsFromPage= Array();
	$access = '';

	foreach (array_keys($groups) as $skupina) {
		$userspace = explode(":",$skupina);
		if (count($userspace) > 1) {
			// group from userspace
			$usersList = getUsersFromPages($skupina);
			if ($usersList) {
				$groupsFromPage[] = '[['.$skupina.']]';
				if ( array_key_exists( $wgUser->getName(), $usersList )) {
					if ($access != 'edit') {
						if (($usersList[$wgUser->getName()] == 'edit') && ($groups[$skupina] == 'edit')) {
							$access = 'edit';
						} elseif ($usersList[$wgUser->getName()] == 'read') {
							$access = 'read';
						} else {
							break;
						}
					}
				}
			}
		} else {
			// groups from MediaWiki
			$groupsFromPage[] = " (".$skupina.")";
			if ($access !='edit') {
				if (in_array($skupina, array_values($wgUser->getGroups())) === true) {
					if ($groups[$skupina] == 'edit') {
						debugme("accessControl 6: User ".$wgUser->getName()." is member systemgroup  '".$skupina."'");
						$access = 'edit';
					} elseif ($groups[$skupina] == 'read') {
						debugme("accessControl 7: User ".$wgUser->getName()." is member systemgroup  '".$skupina."' which only read page");
				 		$access = 'read';
					} else {
						// Not member in any group
					}
				}
			}
		}
	}

	if ($wgAdminCanReadAll) {
		// allowing access for sysop members
		if (in_array("sysop",$wgUser->mGroups)) $access = 'edit';
		}

	$accessGroups['access']=$access;
	$accessGroups['info']=$groupsFromPage;

	return $accessGroups;
}


function makeGroupArray($input) {
	/* Function returns array with element info (information about groups
	   with access right) and element access with information about acces
	   for current user. */

	$groupEntry = explode(",", $input);
	// ..if is only one group
	if (!$groupEntry && strlen(trim($input)) > 0) {
		$groupEntry[] = trim($input);
	}

	$groups = array();
	foreach ($groupEntry as $userGroup) {
		if (strpos($userGroup,"(ro)") === false) {
			$groups[$userGroup] = "edit";
			debugme("makeGroupArray 1: set edit access for group - ".$userGroup);
		} else {
			$userGroup = trim(str_replace("(ro)","",$userGroup));
			$groups[$userGroup] = "read";
			debugme("makeGroupArray 2: set readonly access for group - ".$userGroup);
		}
	}
	debugme("makeGroupArray_3: return array \$groups back into function getContentTag");
	return $groups;
}


function displayGroups($groups,$from) {
	/* Make the textual hyperlink group list - is not used */
	global $wgOut;

	$style = "<div id=\"accesscontrol\" style=\"color:#BA0000;font-size:8pt\">";
	$style_end = "</div>";
	return ( $style."kokakola".$style_end );
	debugme("displayGroup_1: generating output from array with info about access rights groups");
}


function getUsersFromPages($skupina) {
	/* Extracts the allowed users from the userspace access list */
	$allowedAccess = Array();

	$Title = new Title();
	// create title in namespace 0 (default) from groupList
	$gt = $Title->makeTitle(0, $skupina);
	// create Article and get the content
	$groupPage = new Article( $gt, 0 );
	$allowedUsers=$groupPage->fetchContent(0);
	$Title = null;
	$groupPage = NULL;
	$usersAccess = explode("\x0A", $allowedUsers);
	foreach ($usersAccess as $userEntry) {
		$userItem = trim($userEntry);
		if (substr($userItem,0,1) == "*") {
			if (strpos($userItem,"(ro)") === false) {
				$user = trim(str_replace("*","",$userItem));
				$allow[$user] = 'edit';
			} else {
				// user může pouze číst - oříznu a vložím do pole
				$user = trim(str_replace("*","",$userItem));
				$user = trim(str_replace("(ro)","",$user));
				$allow[$user] = 'read';
			}
		}
	}
	if (is_array($allow)) {
		$allowedAccess = $allow;
		unset($allow);
	}
	return $allowedAccess;
}


function doRedirect($info) {
	/* make redirection for non authorized users */
	global $wgScript, $wgSitename, $wgOut;
//	echo "<hr>doRedirect start <br>";
//	print_r($info);
//	echo "<br>doRedirect end<hr>";
	// setting default No_access page
//	echo $info;
//	exit;
	if (!$info) $info="No_access";
	if ($info == "Only_sysop") {
		$target = wfMsg('accesscontrol-info-user');
	} elseif ($info == "No_anonymous") {
		$target = wfMsg('accesscontrol-info-anonymous');
	} elseif ($info == "Deny_anonymous") {
		$target = wfMsg('accesscontrol-edit-anonymous');
	} elseif ($info == "Deny_edit_list") {
		$target = wfMsg('accesscontrol-edit-users');
	} else {
		$target = wfMsg('accesscontrol-info-deny');
	}
	header("Location: ".$wgScript."/".$wgSitename.":".$target);
}

function hookUserCan( &$title, &$wgUser, $action, &$result ) {
	/* Control read access */
	global $wgOut;

	debugme("hookUserCan_1: Go on ".$title);
	$article = new Article( $title, 0 );
	$content = $article->getContent();
	$allowedGroups = getContentTag($content);

	if ($action=='read') {
		if (is_array($allowedGroups)) {

			if ($allowedGroups['access'] != '') {
				debugme("hookUserCan - read 1 : Access is set add info");
				if($wgUser->mId == 0) {
					doRedirect('No_anonymous 1');
					return false;
				} else {
				return true;
				}
			} elseif (count($allowedGroups['info']) < 1 ) {
				debugme("hookUserCan - read 2 : Is not any groups, it is page for anonymous");
				return true;
			} else {
				debugme("hookUserCan - read 3 : Access denied, do redirect into No_access page");
				if ($wgUser->mId == 0) {
					doRedirect('No_anonymous');
					} else {
					doRedirect('No_access');
				}
				return false;
			}

		} else {
			debugme("hookUserCan - read 5: Any accesscontrol tag on page ".$title);
			if($wgUser->mId == 0) {
				return false;
			} else {
				return true;
			}
		}
	} elseif ($action=='edit') {
		if ($allowedGroups['access'] == 'edit') {
				debugme("hookUserCan - edit 1 : User has access right for editation");
				return true;
			} elseif ( count($allowedGroups['info']) < 1 ) {
				debugme("hookUserCan - edit 2 : Is not set any groups, it is page for anonymous access");
				if($wgUser->mId == 0) {
					return false;
				} else {
					return true;
				}
			} else {
				debugme("hookUserCan - edit 3 : Editation for this user is denied");
				return false;
			}
	} elseif ($action=='move') {
		if($wgUser->mId == 0) {
			return false;
		} else {
			return true;
		}
	} else {
	return true;
	}
}


function getContentTag($content) {
	/* Function for get content from accesscontrol element. */
	$starttag = "<accesscontrol>";
	$endtag = "</accesscontrol>";
	$start = strpos( $content, $starttag );
	if ($start != false) {
		$start += strlen($starttag);
		$end = strpos( $content, $endtag );
		if ($end != false) {
			$groupsString = substr($content, $start, $end-$start );
			debugme("getContentTag_1: substring '".$groupsString."' forward into function makeGroupArray" );
			$groups = makeGroupArray($groupsString);
			debugme("getContentTag_2: Vrátil se seznam skupin, je třeba ho vyhodnotit" );
			$message= accessControl($groups);
			if ($message) {
				return $message;
			} else {
				debugme("getContentTag_3: Stránka nemá nastavený žádný seznam skupin s právem přístupu" );
				return true;
			}
		} else {
			debugme("getContentTag_4: Na stránce chybí ukončovací tag" );
			return true;
		}
	} else {
		debugme("getContentTag_5: Stránka nemá nijak omezená přístupová práva");
		return true;
	}
}


function controlEditAccess(&$editpage) {
	/* Hook function for the edit action; */
	global $wgAllowUserList, $wgUser;

	$title = $editpage->mTitle;
	$editPage = new Article( $title, 0 );
	$content = $editPage->getContent();
	$groups = makeGroupArray($content);
	$allowedGroups = accessControl($groups);

	if (is_array($allowedGroups)) {
		if ($allowedGroups['access'] == 'edit') {
				// allow editations page
				if($wgUser->mId == 0) {
					return false;
				} else {
					return true;
				}
			} elseif (count($allowedGroups['info']) < 1 ) {
				// editation for page with empty or anything accesscontrol tag
				if($wgUser->mId == 0) {
					return false;
				} else {
					if ($wgAllowUserList === true ) {
						return true;
					} else {
						doRedirect('Only_sysop');
						return false;
					}
				}
			} elseif ($allowedGroups['access'] == 'read') {
				// info for readonly access
				return false;
			} else {
				// redirection anonymous user
				// tuhle pasáž je nutné ošetřit!!!!
				if($wgUser->mId == 0) {
					doRedirect('No_anonymous 3');
					return false;
				} else {
					return true;
				}
			}
		} else {
		// for uncle Adventure...
				if($wgUser->mId == 0) {
					return false;
				} else {
					return true;
				}
	}
}


function controlUserGroupPageAccess( $out ) {
	/* Function for controlling access on page with user list */
	global $wgUser, $wgTitle, $wgAllowUserList;

	$pageTitle = $wgTitle->getText();
	$userspace = explode(":",$pageTitle);
	if (count($userspace) > 1) {
		if($wgAllowUserList) {
			if($wgUser->mId == 0) {
				doRedirect('Deny_anonymous');
				return false;
			} else {
				return true;
			}
		} else {
			if (in_array('sysop', array_values($wgUser->getGroups())) === false) {
				doRedirect('Deny_edit_list');
				return false;
			} else {
				return true;
			}
		}
	} else {
		return true;
	}
}

?>
