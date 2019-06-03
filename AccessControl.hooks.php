<?php

class AccessControlHooks {

	public static function onParserBeforeStrip( &$parser, &$text, &$strip_state ) {
		self::anonymousDeny();
		$rights = self::allRightTags( $text );
		if ( empty( $rights['visitors'] ) && empty( $rights['editors'] ) ) {
			// without protection
		} else {
//			self::userVerify( $rights ) ;
//			print_r('<!-- ');
//			print_r( $rights );
//			print_r(' -->');

			switch (self::testRightsArray( $rights ) ) {
				case 1 :
					// edit allowed
					break;
				case 2 :
					// read only allowed
					self::readOnlyUser();
					self::denyRead();
					$text='{{int:accesscontrol-readonly}}' . $text;
					break;
				default :
					// is false
					self::readOnlyUser();
					self::denyRead();
					$text='{{int:accesscontrol-info}}';
//					self::doRedirect( 'accesscontrol-redirect-users' );
					break;
			}

		}
	}

	public static function onModifyExportQuery( $db, &$tables, &$cond, &$opts, &$join  ) {
		global $wgQueryPages;
		/*
		    If is page protected, do skip and if user has only read
		    access, return only last revisioni - without history
		*/
		switch ( self::controlExportPage( $cond ) ) {
			case 1 :
				break;
			case 2 : $opts['LIMIT'] = 1;
				$join['revision'][1] = 'page_id=rev_page AND page_latest=rev_id';
				break;
			default :
				$opts['LIMIT'] = 0;
				break;
		}
	}


	public static function onUnknownAction( $action, Page $article ) {
		global $wgOut;
		switch ( $action ) {
			default:
				$wgOut->setPageTitle( $article->getTitle() . "->" . $action );
				$wgOut->addWikiText( wfMessage( 'accesscontrol-actions-deny' )->text() );
		}
		return false;
	}


	public static function accessControlExtension( Parser $parser ) {
		// This the hook function adds the tag <accesscontrol> to the wiki parser
		$parser->setHook( 'accesscontrol', [ 'AccessControlHooks', 'doControlUserAccess' ] );
		return true;
	}


	// Zobrazuje se pouze když je to nastavené přes tag
	public static function doControlUserAccess( $input, array $args, Parser $parser, PPFrame $frame ) {
		// Function called by accessControlExtension
		return self::displayGroups();
	}


	// info about protection
	public static function displayGroups() {
		/** Function replace the tag <accesscontrol> and his content,
		 * behind info about a protection this the page
		 */
		$style = "<p id=\"accesscontrol\" style=\"text-align:center;color:#BA0000;font-size:8pt\">";
		$text = wfMessage( 'accesscontrol-info' )->text();
		$style_end = "</p>";
		$wgAllowInfo = $style . $text . $style_end;
		return $wgAllowInfo;
	}


	public static function getAccessListCanonicalTarget( $title, $namespace = 0 ) {
		/* Function return by default array as [ 'title', 0 ] i.e. */
		global $wgContLang;
		$target = [];
		preg_match(
			'/(.*?):/',
			$title,
			$match,
			PREG_UNMATCHED_AS_NULL
			);
		if ($match) {
			$index = MWNamespace::getCanonicalIndex( strtolower( $match[1] ) );
			if ( $index === null ) {
				/* unexist namespace return null */
				$start = strpos($title, ':');
				if ( $start === false ) {
					// only string, without colon
					$target['title'] = trim($title);
					$target['ns'] = $namespace;
				} else {
					// namespace is in localize form
					$stringfortest = str_replace( " ", "_", substr( $title, 0, $start ) );
					foreach( MWNamespace::getValidNamespaces() as $index ) {
						if ( $wgContLang->getNsText( $index ) === $stringfortest ) {
							$target['title'] = trim( str_replace( "$stringfortest:", '', $title ) );
							$target['ns'] = $index;
							break;
						}
					}
					if ( array_key_exists( 'title', $target ) === false ) {
						/* page is in main namespace */
						$target['title'] = trim($title);
						$target['ns'] = 0;
					}
				}
			} else {
				/* canonical namespace name return integer */
				$target['title'] = trim( substr( $title, strpos($title, ':') + 1 ) );
				$target['ns'] = $index;
			}
		} else {
			$target['title'] = trim($title);
			$target['ns'] = $namespace;
		}
		return $target;
	}

	public static function testRightsArray ( $rights ) {
		global $wgUser, $wgAdminCanReadAll;
		if ( empty( $rights['visitors'] ) && empty( $rights['editors'] ) ) {
			/* stránka je bez ochrany */
			return 1;
		}
		if ( in_array( 'sysop', $wgUser->getGroups(), true ) ) {
			if ( isset( $wgAdminCanReadAll ) ) {
				if ( $wgAdminCanReadAll ) {
					/* admin může vše */
					return 1;
				}
			}
		}
		if ( array_key_exists( $wgUser->mName, $rights['editors'] ) || array_key_exists( $wgUser->mName, $rights['visitors'] ) ) {
			if ( array_key_exists( $wgUser->mName, $rights['editors'] ) && $rights['editors'][$wgUser->mName] ) {
				return 1;
			} else {
				/* uživatel může číst obsah */
				return 2;
			}
		} else {
			return false;
		}
	}

	public static function controlExportPage( $string = "page_namespace=0 AND page_title='test-protectbyoption'") {
		/* "page_namespace=8 AND page_title='fuckoff'" */
		global $wgUser;
		if ( $wgUser->mId === 0 ) {
			/* Deny export for all anonymous */
			return false;
		}
		preg_match(
			'/page_namespace=(.*?) AND page_title=\'(.*?)\'/',
			$string,
			$match,
			PREG_UNMATCHED_AS_NULL
			);
		if ($match) {
			$rights = self::allRightTags( self::getContentPage(
				$match[1],
				$match[2]
			) );
			if ( empty( $rights['visitors'] ) && empty( $rights['editors'] ) ) {
				/* stránka je bez ochrany */
				return 1;
			}
			if ( in_array( 'sysop', $wgUser->getGroups(), true ) ) {
				if ( isset( $wgAdminCanReadAll ) ) {
					if ( $wgAdminCanReadAll ) {
						/* admin může vše */
						return 1;
					}
				}
			}
			if ( array_key_exists( $wgUser->mName, $rights['editors'] ) || array_key_exists( $wgUser->mName, $rights['visitors'] ) ) {
				/* uživatel může číst obsah */
				return 2;
			} else {
				return false;
			}
		}
	}


	public static function oldSyntaxTest( $retezec ) {
		/* Blok kvůli staré syntaxi. uživatel, nebo členové
		    skupiny budou mít automaticky pouze readonly
		    přístup, pokud je přítomen za jménem, či jménem
		    skupiny řetězec '(ro)'. A to bez ohledu na
		    práva v accesslistu. */
//		print_r( $retezec );
		$ro = strpos($retezec, '(ro)');
		if ( $ro ) {
			// Blok kvůli staré syntaxi. Skupina, nebo uživatel bude mít automaticky pouze readonly přístup, bez ohledu na volbu accesslistu.
			return [ trim( str_replace( '(ro)', '', $retezec ) ) ,  false ];
		} else {
			return [ trim($retezec), true ];
		}
	}


	public static function earlySyntaxOfRights( $string ) {
		global $wgAccessControlNamespaces, $wgUser;
		/* u staršího typu syntaxe se mohly vyskytnout zároveň
		- nejprve test uživatelské skupiny MediaWiki
		- pak test na shodu uživatelského jména
		- seznamy
		Nezkoumají se všechna jména.
		Výsledek se vrací ihned po nastavení
		*/
		$allow = [ 'editors' => [], 'visitors' => [] ];
		$MWgroups = User::getAllGroups();
		foreach( explode( ',', $string ) as $title ) {
			//zkontrolovat, jestli není readonly
			$item = self::oldSyntaxTest( $title );
			if ( is_array($item) ) {
				/* Může to být seznmam uživatelů starého typu */
//				$skupina = self::testRightsOfMember($item[0]);
//print_r($item);
				/* Může to být seznmam uživatelů nového typu */
				foreach ($wgAccessControlNamespaces as $ns) {
					$array = self::getContentPageNew( $item[0], $ns);
//print_r($array);
					if ( empty($array) ) {

						foreach ( $MWgroups as $mwgroup ) {
							if ( $item[0] === $mwgroup ) {
								foreach ( $wgUser->getEffectiveGroups() as $group ) {
									if ( $group === $item[0] ) {
									    /* Nemá smysl zjišťovat všechny skupiny. Stačí zjistit, jestli do ní patří aktuální uživatel a přidat ho
									    */
										if ( $item[1] ) {
											$allow['editors'][ $wgUser->mName ] = true;
										} else {
											$allow['visitors'][ $wgUser->mName ] = true;
										}
									}
								}
							}
						}

						/* MW skupina nemusí být použita, zkoumá se jméno */
						if ( $item[0] === $wgUser->mName ) {
							/* Username */
							if ( $item[1] ) {
								$allow['editors'][ $wgUser->mName ] = true;
							} else {
								$allow['visitors'][ $wgUser->mName ] = true;
							}
						}

						if ( $item[1] ) {
							$allow['editors'][$item[0]] = true;
						} else {
							$allow['visitors'][$item[0]] = true;
						}

					} else {
						if ( array_key_exists( 'editors', $array) ) {
							if ( $item[1] ) {
								foreach( array_keys($array['editors']) as $user) {
									$allow['editors'][$user] = true;
								}
							} else {
								/* (ro) */
								foreach( array_keys($array['editors']) as $user) {
									$allow['editors'][$user] = false;
									$allow['visitors'][$user] = true;
								}
							}
						}
						if ( array_key_exists( 'visitors', $array) ) {
							foreach( array_keys($array['visitors']) as $user) {
								$allow['visitors'][$user] = true;
							}
						}
					}
				}
//print_r($allow);
			}
		}
		return $allow;
	}


	public static function testRightsOfMember( $member ) {
		/* Na vstupu je řetězec se jménem uživatele, nebo uživatelské skupiny
		    na výstupu je pole s aktuálním nastavením práv
		    [ userA = false, userB = 'read', userC = 'edit']
		*/
//		$allow = [];
		$item = self::oldSyntaxTest( $member );
//print_r($item);
		if ( is_array($item) ) {
			$accesslistpage = self::getAccessListCanonicalTarget( $item[0] );
			if ( $accesslistpage['ns'] === 2 ) {
				//netřeba dál chodit, je to user
				if ( $item[1] ) {
					$allow['editors'][$accesslistpage['title']] = true;
				} else {
					$allow['visitors'][$accesslistpage['title']] = true;
				}
			} else {
				/* extrakce obsahu seznamu (předává se jmenný prostor a jméno seznamu) */
				$allow = self::getContentPageNew( $accesslistpage['title'], $accesslistpage['ns'] );
			}
		}
//		print_r($allow);
		return $allow;
	}


	public static function membersOfGroup( $string ) {
		$output = [];
		$array = explode( '=', $string);
		if ( $array[1] ) {
			if ( strpos( $array[1], '}' ) ) {
				$members = trim( substr( $array[1], 0, strpos( $array[1], '}' ) ) );
			} else {
				$members = trim($array[1]);
			}
			$name = trim( $array[0] );
			$output[$name] = [];
			if ( strpos($members, '(ro)') ) {
				// invalid syntax!
				return false;
			} else {
				foreach ( explode(',', $members ) as $item ) {
					array_push( $output[$name], trim($item) );
				}
			}
		}
		return $output;
	}


	public static function parseOldList( $content ) {
		/* Extracts the users from the userspace access list by the old syntax */
		$allow = [];
		$usersAccess = explode( "\n", $content );
		foreach ( $usersAccess as $userEntry ) {
			if ( substr( $userEntry, 0, 1 ) == "*" ) {
				if ( strpos( $userEntry, "(ro)" ) === false ) {
					$user = trim( str_replace( "*", "", $userEntry ) );
					if ( self::isUser($user) ) {
						$allow['editors'][$user] = true;
					}
				} else {
					$user = trim( str_replace( "(ro)", "", str_replace( "*", "", $userEntry ) ) );
					if ( self::isUser($user) ) {
						$allow['visitors'][$user] = true;
					}
				}
			}
		}
		return $allow;
	}


	public static function parseNewList( $content ) {
		$allow = [];
		$usersAccess = explode( "|", $content);
		if ( is_array($usersAccess) ) {
			foreach ( $usersAccess as $userEntry ) {
				$item = trim($userEntry);
				if ( substr( $userEntry, 0, 21) === 'readOnlyAllowedGroups' ) {
					$visitorsGroup = self::membersOfGroup( $item );
					foreach ( $visitorsGroup['readOnlyAllowedGroups'] as $group ) {
						$array = self::testRightsOfMember( $group );
						if ( array_key_exists( 'editors', $array) ) {
							foreach( array_keys($array['editors']) as $user) {
								$allow['editors'][$user] = false;
							}
						}
						if ( array_key_exists( 'visitors', $array) ) {
							foreach( array_keys($array['visitors']) as $user) {
								$allow['visitors'][$user] = true;
							}
						}
					}
				}
				if ( substr( $userEntry, 0, 17) === 'editAllowedGroups' ) {
					$editorsGroup = self::membersOfGroup( $item );
					foreach ( $editorsGroup['editAllowedGroups'] as $group ) {
						$array = self::testRightsOfMember( $group );
						if ( array_key_exists( 'editors', $array) ) {
							foreach( array_keys($array['editors']) as $user) {
								$allow['editors'][$user] = true;
							}
						}
						if ( array_key_exists( 'visitors', $array) ) {
							foreach( array_keys($array['visitors']) as $user) {
								$allow['visitors'][$user] = true;
							}
						}
					}
				}
				if ( substr( $userEntry, 0, 20) === 'readOnlyAllowedUsers' ) {
					$visitors = self::membersOfGroup( $item );
					foreach ( $visitors['readOnlyAllowedUsers'] as $user ) {
						$allow['visitors'][$user] = true;
					}
				}
				if ( substr( $userEntry, 0, 16) === 'editAllowedUsers' ) {
					$editors = self::membersOfGroup( $item );
					foreach ( $editors['editAllowedUsers'] as $user ) {
						$allow['editors'][$user] = true;
					}
				}
			}
		}
		return $allow;
	}


	public static function getContentPageNew( $title, $ns ) {
		/* Return array with two keys: visitors and editors */
		$content = self::getContentPage( $ns, $title );
		if ( strpos( $content, '* ' ) === 0 ) {
			$array = self::parseOldList( $content );
		} else {
			$array = self::parseNewList( $content );
		}
		return $array;
	}


	public static function getContentPage( $namespace, $title ) {
		/* Function get content the page identified by title object from database */
		$gt = Title::makeTitle( $namespace, $title );
		if ( $gt->isSpecialPage() ) {
			// Can't create WikiPage for special page
			return '';
		}
		// Article::fetchContent() is deprecated.
		// Replaced by WikiPage::getContent()
		$page = WikiPage::factory( $gt );
		$content = ContentHandler::getContentText( $page->getContent() );
		return $content;
	}


	public static function isUser( $user ) {
		$title = Title::newFromText( $user, NS_USER );
		if ( $title !== null ) {
			return true;
		}
	}


	/* Přesměrování nežádoucího uživatele */
	public static function doRedirect( $info ) {
		/* make redirection for non authorized users */
		global $wgScript, $wgSitename, $wgOut, $wgAccessControlRedirect;
		if ( !$info ) {
			$info = "No_access";
		}
		if ( isset( $_SESSION['redirect'] ) ) {
			// removing info about redirect from session after move..
			unset( $_SESSION['redirect'] );
		}
		$wgOut->clearHTML();
		$wgOut->prependHTML( wfMessage( 'accesscontrol-info-box' )->text() );
		if ( $wgAccessControlRedirect ) {
//			header( "Location: " . $wgScript . "/" . $wgSitename . ":" . wfMessage( $info )->text() );
		}
	}


	public static function allRightTags( $string ) {
		global $wgAccessControlNamespaces;

		/* Set default value for $wgAccessControlNamespaces */
		if (is_array($wgAccessControlNamespaces)) {
			// if is set
		} else {
			$wgAccessControlNamespaces = [ 0, 2, 13 ];
		}

//print_r($string);
		/* Redirect */
		preg_match(
			'/\#REDIRECT +\[\[(.*?)[\]\]|\|]/i',
			$string,
			$match,
			PREG_UNMATCHED_AS_NULL
			);
		if ($match) {
			$array = self::getAccessListCanonicalTarget( $match[1] );
			if ($array) {
				$rights = self::allRightTags( self::getContentPage( $array['ns'], $array['title'] ) );
				self::anonymousDeny();
				self::userVerify($rights);
//print_r($rights);
			}
		}

		$allow = [ 'editors' => [], 'visitors' => [] ];
		preg_match_all(
			'/(?J)(?<match>\{\{[^\}]+(.*)\}\})|(?<match>\<accesscontrol\>(.*)\<\/accesscontrol\>)/',
			$string,
			$matches,
			PREG_PATTERN_ORDER
			);
		foreach( $matches[0] as $pattern ) {
			/* Transclusions of page, which is not from template namespace */
			if ( substr( $pattern, 0, 3 ) === '{{:' ) {
				// transclusion any page
				preg_match(
					'/\{\{:(.*?)[\}\}|\|]/',
					$pattern,
					$include,
					PREG_UNMATCHED_AS_NULL
					);
				if ($include) {
					$array = self::getAccessListCanonicalTarget( $include[1] );
					if ($array) {
						$rights = self::allRightTags( self::getContentPage( $array['ns'], $array['title'] ) );
						self::anonymousDeny();
						self::userVerify($rights);
//print_r($rights);
					}
				}
			}
			/* Template transclusion */
			if ( substr( $pattern, 0, 2 ) === '{{' ) {
				// transclusion template
				preg_match(
					'/\{\{(.*?)[\}\}|\|]/',
					$pattern,
					$include,
					PREG_UNMATCHED_AS_NULL
					);
				if ($include) {
					$rights = self::allRightTags( self::getContentPage( 10, trim($include[1]) ) );
					self::anonymousDeny();
					self::userVerify($rights);
//print_r($rights);
				}
			}

			switch ( substr( mb_strtolower( $pattern, 'UTF-8' ), 0, 15 ) ) {
				case '<accesscontrol>' :
					/* Protected by tag */
					$allow = self::earlySyntaxOfRights( trim(str_replace( '</accesscontrol>', '', str_replace( '<accesscontrol>', '', $pattern ) ) ) );
/* Array of members is based by content of the element accesscontrol */
//print_r($allow);
					break;
				default :
					if (
						strpos( $pattern, 'isProtectedBy') ||
						strpos( $pattern, 'readOnlyAllowedUsers') ||
						strpos( $pattern, 'editAllowedUsers') ||
						strpos( $pattern, 'readOnlyAllowedGroups') ||
						strpos( $pattern, 'editAllowedGroups')
					   ) {
						/* Protected by options */
						$options = explode( '|', $pattern );
						foreach ( $options as $string ) {
							if ( is_integer( strpos( $string, 'isProtectedBy' ) ) ) {
								/* page is protected by list of users */
								$groups = self::membersOfGroup($string);
								if ( array_key_exists( 'isProtectedBy', $groups) ) {
									foreach ( $groups['isProtectedBy'] as $group ) {
										foreach ($wgAccessControlNamespaces as $ns) {
											$array = self::getContentPageNew( $group, $ns);
											if ( array_key_exists( 'editors', $array) ) {
												foreach( array_keys($array['editors']) as $user) {
													$allow['editors'][$user] = true;
												}
											}
											if ( array_key_exists( 'visitors', $array) ) {
												foreach( array_keys($array['visitors']) as $user) {
													$allow['visitors'][$user] = true;
												}
											}
										}
									}
								}
/* isProtectedBy */
//print_r($allow);
							}
							if ( strpos ( $string, 'readOnlyAllowedUsers' ) || strpos ( $string, 'readOnlyAllowedGroups' ) ) {
								/* readonly access - stronger then rights from isProtectedby */
								$readers = self::membersOfGroup($string);
								if ( array_key_exists( 'readOnlyAllowedGroups', $readers ) ) {
									foreach( $readers['readOnlyAllowedGroups'] as $group ) {
										foreach ($wgAccessControlNamespaces as $ns) {
											$array = self::getContentPageNew( $group, $ns);
											if ( array_key_exists( 'editors', $array) ) {
												foreach( array_keys($array['editors']) as $user) {
													$allow['editors'][$user] = false;
													$allow['visitors'][$user] = true;
												}
											}
											if ( array_key_exists( 'visitors', $array) ) {
												foreach( array_keys($array['visitors']) as $user) {
													$allow['visitors'][$user] = true;
												}
											}
										}
									}
								}
/*  readOnlyAllowedGroups */
//print_r($allow);
								if ( array_key_exists( 'readOnlyAllowedUsers', $readers ) ) {
									foreach( $readers['readOnlyAllowedUsers'] as $user ) {
										if ( array_key_exists('editors', $allow) ) {
											if ( array_key_exists( $user, $allow['editors'] ) ) {
												/* vypínám právo k editaci */
												$allow['editors'][$user] = false;
											}
										}
										if ( array_key_exists('visitors', $allow) ) {
											$allow['visitors'][$user] = true;
										}
									}
								}
							}
/* readOnlyAllowedUsers */
//print_r($allow);
							if ( strpos ( $string, 'editAllowedUsers' ) || strpos ( $string, 'editAllowedGroups' ) ) {
								/* edit access - stronger then rights from isProtectedby, and rights from readonly options */
								$editors = self::membersOfGroup($string);
								if ( array_key_exists( 'editAllowedGroups', $editors ) ) {
									foreach( $editors['editAllowedGroups'] as $group ) {
										foreach ($wgAccessControlNamespaces as $ns) {
											$array = self::getContentPageNew( $group, $ns);
											if ( array_key_exists( 'visitors', $array) ) {
												foreach( array_keys($array['visitors']) as $user) {
													$allow['editors'][$user] = true;
												}
											}
										}
									}
								}
								if ( array_key_exists( 'editAllowedUsers', $editors ) ) {
									/* přidat do seznam editorů */
									foreach( $editors['editAllowedUsers'] as $user ) {
										$allow['editors'][$user] = true;
									}
								}
							}
							/* ignore other options or params */
						}
/* Array of rights is set by template options */
// print_r($allow);
					} elseif ( strpos( $pattern, 'accesscontrol') > 0 ) {
						/* Test first item of template with accesscontrol string in name - it is same alternative without tag */
						$retezec = trim( substr( $pattern, strpos( $pattern, '|' ) + 1 ) );
						if ( strpos( $retezec, '|' ) ) {
							$members = trim( substr( $retezec, 0, strpos( $retezec, '|' ) ) );
						} else {
							if ( strpos( $retezec, '}' ) ) {
								$members = trim( substr( $retezec, 0, strpos( $retezec, '}' ) ) );
							}
						}
						if ( !strpos( $members, '=') ) {
							/* {{Someone template name with accesscontrol string | accesslist_X, userA, userB | option = … }} */
							$allow = self::earlySyntaxOfRights( $members );
						}
/* Array of members is generated by first parameter of template with accesscontrol string in name */
// print_r($allow);
					}
			}
		}
	/* Array has 2 keys with arays of:
	    editors - member can do all (if have value true)
	    visitors - member can do only read
	*/
//		print_r($allow);
		return $allow;
	}

	public static function denyRead() {
		/* User is denied */
		global $wgActions, $wgUser;

		$wgActions['view'] = false;
	}

	public static function readOnlyUser() {
		/* User is anonymous - deny rights */
		global $wgActions, $wgUser;

		$wgActions['edit'] = false;
		$wgActions['history'] = false;
		$wgActions['submit'] = false;
		$wgActions['info'] = false;
		$wgActions['raw'] = false;
		$wgActions['delete'] = false;
		$wgActions['revert'] = false;
		$wgActions['revisiondelete'] = false;
		$wgActions['rollback'] = false;
		$wgActions['markpatrolled'] = false;
		$wgActions['formedit'] = false;
	}


	public static function anonymousDeny() {
		/* User is anonymous - deny rights */
		global $wgActions, $wgUser;

		if ( $wgUser->mId === 0 ) {
			/* Deny actions for all anonymous */
			$wgActions['edit'] = false;
			$wgActions['history'] = false;
			$wgActions['submit'] = false;
			$wgActions['info'] = false;
			$wgActions['raw'] = false;
			$wgActions['delete'] = false;
			$wgActions['revert'] = false;
			$wgActions['revisiondelete'] = false;
			$wgActions['rollback'] = false;
			$wgActions['markpatrolled'] = false;
			$wgActions['formedit'] = false;
		}
		return true;
	}


	public static function userVerify( $rights ) {
		/* User is logged */
		global $wgUser, $wgActions, $wgAdminCanReadAll;

		if ( empty( $rights['visitors'] ) && empty( $rights['editors'] ) ) {
			return true;
		} else {
			if ( $wgUser->mId === 0 ) {
				/* Redirection unknown users */
				$wgActions['view'] = false;
				self::doRedirect( 'accesscontrol-redirect-anonymous' );
			} else {
				if ( in_array( 'sysop', $wgUser->getGroups(), true ) ) {
					if ( isset( $wgAdminCanReadAll ) ) {
						if ( $wgAdminCanReadAll ) {
							return true;
						}
					}
				}
			}
			if ( array_key_exists( 'editors', $rights ) ) {
				if ( array_key_exists( $wgUser->mName, $rights['editors'] ) ) {
					if ( $rights['editors'][$wgUser->mName] ) {
						return true;
					}
				}
			}
			if ( array_key_exists( 'visitors' , $rights ) ) {
				self::readOnlyUser();
				if ( array_key_exists( $wgUser->mName, $rights['editors'] ) || array_key_exists( $wgUser->mName, $rights['visitors'] ) ) {
					if ( $rights['visitors'][$wgUser->mName] ) {
						return true;
					} else {
						$wgActions['view'] = false;
						return self::doRedirect( 'accesscontrol-redirect-users' );
					}
				} else {
					$wgActions['view'] = false;
					return self::doRedirect( 'accesscontrol-redirect-users' );
				}
			}
		}
	}


	public static function onUserCan( &$title, &$wgUser, $action, &$result ) {
		// Main function control access for all users

		self::anonymousDeny();
		self::controlExportPage();
		// return array of users & rights
		$rights = self::allRightTags(
			self::getContentPage(
				$title->getNamespace(),
				$title->mDbkeyform
			)
		);
		self::userVerify($rights);

	}

}
