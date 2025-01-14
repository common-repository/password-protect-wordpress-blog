<?php
class private_blog_callbacks extends lavaBase
{
	function lavaConstruct()
	{
		$this->translation();
	}
	
	function init() {
		$hookTag = "settingInnerPre";
		$this->addFilter( "{$hookTag}-tag/password-label", "addLabelHtml" );

		$hookTag = "get_header";
		$this->addWPAction( $hookTag, "doHeadActions", 2 );

		$hookTag = "template_redirect";
		$this->addWPAction( $hookTag, "doHeadActions", 2 );

		$hookTag = "displayLoginPage";
		$this->addAction( $hookTag );

		$hookTag = "_templateVars_pluginVars";
		$this->addAction( $hookTag, "pluginVars");

		//Adds the fields to the login form - doing it this way allows extensions to add fields without messing with custom themes
		$hookTag = "formInputs";
		$this->addFilter( $hookTag, "addActionField");
		$this->addFilter( $hookTag, "addRedirectField");
		$this->addFilter( $hookTag, "addPasswordField");
		$this->addFilter( $hookTag, "addSubmitField");

		$hookTag = "isLoginRequest";
		$this->addFilter( $hookTag );

		$hookTag = "isLogoutRequest";
		$this->addFilter( $hookTag );

		$hookTag = "isLoginAccepted";
		$this->addFilter( $hookTag );
		
		$hookTag = "loginAccepted";
		$this->addAction( $hookTag );

		$hookTag = "loginRejected";
		$this->addAction( $hookTag );

		$hookTag = "doLogout";
		$this->addAction( $hookTag );

		$hookTag = "isLoggedIn";
		$this->addFilter( $hookTag );

		$hookTag = "insertRow";
		$hookTags = array(
			"{$hookTag}/nonce-loginAccepted",
			"{$hookTag}/nonce-loginRejected",
			"{$hookTag}/nonce-doLogout"
		);
		$callbacks = array(
			"addUserAgentField",
			"addIpAddressField",
			"addBrowserField",
			"addOperatingSystemField",
			"addDeviceField"
		);
		$this->addFilter( $hookTags, $callbacks );

		$dataSourceSlug = "access_logs";
		$col = "timestamp";
		$hookTag = "_dataSourceAjax_columnClasses/dataSource:{$dataSourceSlug}/col:$col";
		$this->addFilter( $hookTag, "addTimestampClass" );


		$hookTag = "_dataSourceAjax_columnTitle/dataSource:{$dataSourceSlug}/col:$col";
		$this->addFilter( $hookTag, "addTimestampTitle", 10, 2 );

		$hookTag = "_dataSourceAjax_column/dataSource:{$dataSourceSlug}/col:$col";
		$this->addFilter( $hookTag, "formatTimestamp" );


		$hookTags = array(
			"_dataSourceAjax_columnClasses/dataSource:{$dataSourceSlug}/col:action",
			"_dataSourceAjax_columnClasses/dataSource:{$dataSourceSlug}/col:browser",
			"_dataSourceAjax_columnClasses/dataSource:{$dataSourceSlug}/col:device",
			"_dataSourceAjax_columnClasses/dataSource:{$dataSourceSlug}/col:operating_system"
		);
		$this->addFilter( $hookTags, "addValueAsClass", 10, 2 );

		$hookTag = "_dataSourceAjax_column/dataSource:{$dataSourceSlug}/col:action";
		$this->addFilter( $hookTag, "formatAction" );

		$rss_public = $this->_settings()->fetchSetting( "rss_feed_visible" )->getValue();

		$hookTags = array(
			"do_feed",
			"do_feed_rdf",
			"do_feed_rss",
			"do_feed_rss2",
			"do_feed_atom"
		);
		if( $rss_public != "on" ) {
			$this->addWPAction( $hookTags, "disableRssFeed", 1, true );
		}

		$is_enabled = $this->_settings()->fetchSetting( "enabled" )->getValue();
		$add_logout = $this->_settings()->fetchSetting( "logout_link" )->getValue();

		if( $is_enabled == "on" and $add_logout == "on" ) {
			$this->addLogoutLink();
		}

		$this->doInitActions();
	}


	function adminInit() {
		$this->logoutLinkBackend();
		$this->copyLegacySettings();
	}

	function copyLegacySettings() {
		$hasBeenCopied = get_option( $this->_slug( 'legacy' ) );

		if( $hasBeenCopied == "done" ) {
			return;
		}

		update_option( $this->_slug( 'legacy' ), 'done' );

		$legacySettings = get_option( "password_protect_options", array() );

		if( array_key_exists('password', $legacySettings) ) {
			$this->_settings()->fetchSetting( 'password1_value' )->updateValue( $legacySettings['password'] );
		}

		for( $i = 1; $i <= 10; $i++ ) {
			if( array_key_exists('password'.$i, $legacySettings) ) {
				if( strlen( $legacySettings['password1'] ) > 0 ){
					$this->_settings()->fetchSetting( 'password' . $i . '_value' )->updateValue( $legacySettings['password'.$i] );
				}
			}
		}


	}

	function translation() {
		$this
			->_skins()
				->addTranslation( "logout_message", __( "You have been logged out.", $this->_slug() ) )
				->addTranslation( "incorrect_credentials", __( "The password you submitted was wrong.", $this->_slug() ) )
		;
	}

    function addLabelHtml( $html )
    {
        $pluginSlug = $this->_slug();

        $html = '<div class="js-only custom-password-label clearfix tiptip" onclick="alert(\'not implemented yet\')" title="' . __( "Click to change the name and colour of this password. These will be used in the Access Logs.", $pluginSlug ) . '"><span>undefined</span></div>' .$html;
        return $html;
    }

    function doInitActions() {
	$isLoginRequest = $this->runFilters( "isLoginRequest", false);
        if( true === $isLoginRequest )
        {
			//the user is attempting to login
            $isLoginAccepted = apply_filters( $this->_slug( "isLoginAccepted" ), false );

            if( true === $isLoginAccepted ) {
                do_action( $this->_slug( "loginAccepted" ) );
            } else {
                do_action( $this->_slug( "loginRejected" ) );
            }
        }

        $isLogoutRequest = $this->runFilters( "isLogoutRequest", false );

        if( true === $isLogoutRequest )
        {
			//the user is attempting to logout
            do_action( $this->_slug( "doLogout" ) );
        }
    }
	/**
	 * doHeadActions function
	 *	Wrapper function that calls all hooks - isn't using getHeader() as we need priority here
	 *
	 */
    function doHeadActions()
    {
		$isEnabled = $this->_settings()->fetchSetting( "enabled" )->getValue();
		if( $isEnabled == "off" ) {
			//protection is disabled
			return;
		}
        

        $isLoggedIn = apply_filters( $this->_slug( "isLoggedIn" ), false );

        if( true === $isLoggedIn ) {
            //refresh logged in cookies
			$this->setCookie();
            return;
        } else {
			do_action( $this->_slug( "displayLoginPage" ) );
			exit;
        }
    }

	function isLoginRequest( $current ) {
		//should only alter value if there is a login as default is false so if it is something else then it is by design
		$field = $this->_slug( "action" );
		if( array_key_exists( $field , $_POST ) and $_POST[$field] == "login" )
		{
			$current = true;
		}
		return $current;
	}

	function isLogoutRequest( $current ) {
		$field = $this->_slug( "action" );
		if( array_key_exists( $field , $_GET ) and $_GET[$field] == "logout" )
		{
			$current = true;
		}
		return $current;
	}

	function doLogout() {
		$row = array(
			"action" => "doLogout"
		);
		if( $this->recordLogs() )
			$this->_tables()->fetchTable( "access_logs" )->insertRow( $row, "doLogout" );
		setcookie( $this->_slug( "loggedin" ), "LOGGEDOUT", time() - 1000, COOKIEPATH, COOKIE_DOMAIN );
		$redirect = remove_query_arg( $this->_slug( "action" ) );
		$redirect = add_query_arg( "loggedout", "", $redirect );
		wp_redirect( $redirect );
		exit();
	}

	function isLoginAccepted( $current ) {
		global $maxPasswords;//get the maxPasswords constant (can be changed by an extension)
		$password = $_POST[ $this->_slug( "password" ) ];
		$password = $this->runFilters( "passwordFilter", $password );//allows extensions to do weird stuff like hash the damn thing
		
		$multiplePasswords = $this->_settings()->fetchSetting( "multiple_passwords" )->getValue();
			
		$limit = 1;
		if( $multiplePasswords == "on" ) {
			$limit = $maxPasswords;
		}

		for( $i = 1; $i <= $limit; $i++ ) {
			
			$passToCheck = $this->_settings()->fetchSetting( "password".$i."_value" )->getValue();
			if( !empty( $passToCheck ) and $passToCheck == $password ) {
				$passwordName = $this->_settings()->fetchSetting( "password{$i}_name" )->getValue();
				$passwordColour = $this->_settings()->fetchSetting( "password{$i}_colour" )->getValue();
				$row = array(
					"password" => $i,
					"password_name" => $passwordName,
					"password_color" => $passwordColour,
					"action" => "loginAccepted"
				);
				if( $this->recordLogs() )
					$this->_tables()->fetchTable( "access_logs" )->insertRow( $row, "loginAccepted" );
				return true; // we just logged in
			}
		}

		return $current;
	}

	function loginAccepted() {
		$this->setCookie();
		$redirect = $_POST[ $this->_slug( "redirect" ) ];
		wp_redirect( $redirect );
		exit;
	}

	function loginRejected() {
		$row = array(
			"action" => "loginRejected",
		);
		if( $this->recordLogs() )
			$this->_tables()->fetchTable( "access_logs" )->insertRow( $row, "loginRejected" );
		$redirect = add_query_arg( "incorrect_credentials", "" );
		$redirect = remove_query_arg( "loggedout", $redirect );
		wp_redirect( $redirect );
	}

	function setCookie() {
		$loginNonce = wp_create_nonce( $this->_slug( "loggedin" ) );
		$expire = $this->_settings()->fetchSetting( "timeout_length" )->getValue();
		if( $expire != 0) {
			$expire = time() + $expire;
		}
		if( !headers_sent() ) {
			setcookie( $this->_slug( "loggedin" ), $loginNonce, $expire, COOKIEPATH, COOKIE_DOMAIN );
		}
	}

	function isLoggedIn( $current ) {
		$cookieName = $this->_slug( "loggedin" );
		
		if( array_key_exists( $cookieName, $_COOKIE ) ) {
			$nonce = $_COOKIE[ $cookieName ];
			
			$nonceName = $this->_slug( "loggedin" );
			if( wp_verify_nonce( $nonce, $nonceName ) ) {
				$current = true;
			}
		}

		return $current;
	}




	function displayLoginPage()
	{
		echo $this->_skins()->renderTemplate( "loginpage" );
	}

	function pluginVars( $pluginVars) {
		$pluginVars['form_inputs'] = apply_filters( $this->_slug( "formInputs" ), array() );

		return $pluginVars;
	}

	function addActionField( $formInputs ) {
		$formInputs[] = array(
			"type" => "hidden",
			"name" => $this->_slug( "action" ),
			"value" => "login"
		);

		return $formInputs;
	}
	
	function addRedirectField( $formInputs ) {
		$redirect = add_query_arg( "loggedin", "" );
		$redirect = remove_query_arg( "loggedout", $redirect );
		$redirect = remove_query_arg( "incorrect_credentials", $redirect );

		$formInputs[] = array(
			"type" => "hidden",
			"name" => $this->_slug( "redirect" ),
			"value" => $redirect
		);

		return $formInputs;
	}

	function addPasswordField( $formInputs ) {
		$formInputs[] = array(
			"type" => "password",
			"name" => $this->_slug( "password" ),
			"id" => "password",
			"label" => __( "Password", $this->_slug() ),
			"class" => "input"
		);
		
		return $formInputs;
	}
	/*
		Adds the submit field to the login form
	*/
	
	function addSubmitField( $formInputs ) {
		$formInputs[] = array(
			"type" => "submit",
			"name" => $this->_slug( "submit" ),
			"id" => "submit",
			"value" => __( "Login", $this->_slug() )
		);
		
		return $formInputs;
	}
	
	function disableRssFeed() {
		$isLoggedIn = apply_filters( $this->_slug( "isLoggedIn" ), false );
		
		if( $isLoggedIn === true ) {
			return;
		} else {
			wp_die( __('The feed for this website is protected, please visit our <a href="'. get_bloginfo('url') .'">website</a>!') );
		}
	}

	/*
	Detects what mechanism the theme is using to display navigation and adds the relevant filters
	*/

	function addLogoutLink() {
		$menu = $this->_settings()->fetchSetting( "logout_link_menu" )->getValue();
		$this->addWPFilter( "wp_nav_menu_{$menu}_items", "pagesFilter", 10, 2 );
		$this->addWPFilter( "wp_list_pages", "pagesFilter", 10, 2 );
		$this->addWPFilter( "wp_page_menu", "pagesFilter", 10, 2 );//this is fired when the theme uses WP3 menus but the admin hasn't created one
	}

	/*
		Adds logout link to themes that use the old wp_list_pages function and the new WP3 menus
	*/
	function pagesFilter( $output, $r ) {
		if( !strpos( $output, "page-item-logout" ) )
			$output .= '<li class="page_item page-item-logout"><a href="' . add_query_arg( $this->_slug( "action" ), "logout", get_bloginfo('url') ) . '">' . __( "Logout", $this->_slug() ) . '</a></li>';
		return $output;
	}

	function recordLogs() {
		$shouldRecord = $this->_settings()->fetchSetting( "record_logs" )->getValue();

		if( $shouldRecord == "on" ) {
			return true;
		}

		return false;
	}



	/*
		Handles the backend stuff to make sure only the options that can actually be changed are shown to user and makes sure that theme changes don't break it.
	*/
	function logoutLinkBackend() {
		
		$locations = get_nav_menu_locations();// get the locations set by the theme
		if( !is_array( $locations ) ) {
			$locations = array();
		}
		$counter = 0;
		$valueArray = array();
		$defaultValue = "list_pages";
		foreach( $locations as $location => $locationId ) {
			if( has_nav_menu( $location ) ) {
				$menu = wp_get_nav_menu_object( $locationId );
				$counter ++;
				if( $counter == 1 ) {
					$defaultValue = $menu->slug;//make sure a default is set if any menus exist
				}
				$valueArray[] = $menu->slug;
				$this->_settings()->fetchSetting( "logout_link_menu" )->addSettingOption( $menu->slug, $menu->name );//add this WP 3 menu to the dropdown of options
			}
		}
		if( $counter == 0 ) {
			//if there is no menu that is attached to a location then we should check to see whether there is any menus at all as by default WP will display an unassigned menu
			$this->_settings()->fetchSetting( "logout_link_menu" )->removeTag( "options-available" )->addTag("force-invisible");
			$menus = wp_get_nav_menus();
			foreach ( $menus as $menu_maybe ) {
				if ( $menu_items = wp_get_nav_menu_items($menu_maybe->term_id) ) {
					$menu = $menu_maybe;
					$this->_settings()->fetchSetting( "logout_link_menu" )->addSettingOption( $menu->slug, $menu->name );
					$valueArray[] = $menu->slug;
					$defaultValue = $menu->slug;
					break;
				}
			}
		} else if( $counter == 1 ) {
			//if there is only one then no need to display dropdown
			$this->_settings()->fetchSetting( "logout_link_menu" )->removeTag( "options-available" )->addTag("force-invisible");
		}
		$currentValue = $this->_settings()->fetchSetting( "logout_link_menu" )->setDefault( $defaultValue )->getValue();
		if( !in_array( $currentValue, $valueArray ) ) {
			//something has changed and the value stored no longer exists as a menu so reset it to new default (probably theme change)
			$this->_settings()->fetchSetting( "logout_link_menu" )->updateValue( $defaultValue );
		}
	}
	

	/*
		Table array Filters
	*/
	function addUserAgentField( $row ) {
		$row['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
		return $row;
	}

	function addBrowserField( $row ) {
		$info = $this->_misc()->userAgentInfo();
		$row['browser'] = $info['browser'];
		return $row;
	}

	function addOperatingSystemField( $row ) {
		$info = $this->_misc()->userAgentInfo();
		$row['operating_system'] = $info['os'];
		return $row;
	}

	function addDeviceField( $row ) {
		$info = $this->_misc()->userAgentInfo();
		$row['device'] = $info['device'];
		return $row;
	}

	function addIpAddressField( $row ) {
		$row['ip_address'] = $_SERVER['REMOTE_ADDR'];
		return $row;
	}

	/* Table server side filters */

	function addTimestampClass( $classes ) {
		return $classes . "implements-timestamp ";
	}

	function addValueAsClass( $classes, $args ) {
		$value = $args['original'];
		return $classes . "value-$value ";
	}

	function formatTimestamp( $timeString ) {
		$timeStamp = strtotime( $timeString );
		$now = time();
		$today = strtotime( "Today");
		$yesterday = strtotime( "Yesterday");

		if( $now < $timeStamp ) {
			return __( "The future (check config)", $this->_slug() );
		} elseif( $now - $timeStamp < 60 ) {
			//less than a minute ago
			return __( "Less than a minute ago", $this->_slug() );
		} elseif( $now - $timeStamp < 60 * 60 ) {
			//less than an hour ago
			$minutes = floor( ($now - $timeStamp) / 60 );
			return sprintf(_n('%d minute ago', '%d minutes ago', $minutes), $minutes);
		} elseif( $timeStamp > $today ) {
			//it was today
			return date( "H:i", $timeStamp );
		} elseif( $timeStamp > $yesterday ) {
			return __( "Yesterday", $this->_slug() );
		} else {
			return date( "d F", $timeStamp );
		}



		return $now - $timeStamp;
		//return $timeStamp;
		return $timeStamp;
	}

	function formatAction( $value ) {
		switch( $value ) {
			case "doLogout":
				return __( "Logout", $this->_slug() );
			case "loginAccepted":
				return __( "Login accepted", $this->_slug() );
			case "loginRejected":
				return __( "Login rejected", $this->_slug() );
				break;
		}

		return $value;
	}

	function addTimestampTitle( $title, $args ) {

		$timeStamp = strtotime( $args['original'] );
		return date( "D, d F Y H:i", $timeStamp );

	}
	
}
?>