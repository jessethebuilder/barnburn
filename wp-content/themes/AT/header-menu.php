<?php
/*
* Theme: PREMIUMPRESS CORE FRAMEWORK FILE
* Url: www.premiumpress.com
* Author: Mark Fail
*
* THIS FILE WILL BE UPDATED WITH EVERY UPDATE
* IF YOU WANT TO MODIFY THIS FILE, CREATE A CHILD THEME
*
* http://codex.wordpress.org/Child_Themes
*/
if (!defined('THEME_VERSION')) {	header('HTTP/1.0 403 Forbidden'); exit; }

global $CORE, $userdata;

if(defined('WLT_DEMOMODE') && isset($_SESSION['skin']) && file_exists(WP_CONTENT_DIR."/themes/".$_SESSION['skin']."/header-menu.php") ){

	include(WP_CONTENT_DIR."/themes/".$_SESSION['skin']."/header-menu.php");

}else{


$mylocatopntop = "";

	$topmenu = wp_nav_menu( array(
            'container' => 'div',
            'container_class' => '',
            'theme_location' => 'top-navbar',
            'menu_class' => 'nav nav-pills',
			'fallback_cb'     => '',
			'echo'            => false,
            'walker' => new Bootstrap_Walker(),
            ) );


	if(!defined('WLT_CART') && isset($GLOBALS['CORE_THEME']['geolocation']) && $GLOBALS['CORE_THEME']['geolocation'] == "1" ){

			if(isset($_SESSION['mylocation'])){
				$country = $_SESSION['mylocation']['country'];
				$addresss = $_SESSION['mylocation']['address'];
			}else{
				$address = "";
				$country = $GLOBALS['CORE_THEME']['geolocation_flag'];
			}

			$mylocatopntop = '<li class="MyLocationLi">

			<a href="#" onclick="GMApMyLocation();" data-toggle="modal" data-target="#MyLocationModal"><div class="flag flag-'.strtolower($country).' wlt_locationflag"></div> '.$CORE->_e(array('widgets','8')).'</a> </li>';

			// ATTACH IT TO THE TOP MENU
			if($topmenu == ""){

				$topmenu = "<ul class='nav nav-pills'>".$mylocatopntop."</ul>";

			}else{

				$topmenu = str_replace('class="nav nav-pills">','class="nav nav-pills">'.$mylocatopntop,$topmenu);

			}
		}

	// ONLY SHOW IF WE'VE CREATED ONE
	if(strlen($topmenu) > 0 ||  defined('WLT_CART') ){


	$topmenustring = '<div id="core_header_navigation" class="hidden-xs">
	<div class="'.$CORE->CSS("container", true).'">

	<div class="row"> 	';

	if(isset($GLOBALS['CORE_THEME']['header_accountdetails']) && $GLOBALS['CORE_THEME']['header_accountdetails'] == 1){

	$topmenustring .= '<ul class="nav nav-pills pull-right accountdetails">'._accout_links().'</ul>';

	}else{

	$topmenustring .= '<div class="welcometext pull-right">'.hook_welcometext(stripslashes($GLOBALS['CORE_THEME']['header_welcometext'])).'</div>';

	}

	$topmenustring .= '<div class="navbar-inner">'.$topmenu.'</div>

	</div>

	</div></div>';

		echo hook_header_navbar($topmenustring);
	}









// LOAD IN USER STYLE
if(!isset($GLOBALS['CORE_THEME']['layout_header'])){ $style = 1; }else{ $style = $GLOBALS['CORE_THEME']['layout_header']; }

$STRING = '<div id="core_header_wrapper"><div class="'.$CORE->CSS("container",true).' header_style'.$style.'" id="core_header"><div class="row">'.hook_header_row_top('');

switch($style){

	case "6": { // LOGO LONG + TEXT

		// LOGO
		if(!isset($GLOBALS['CORE_THEME']['header_style_text']) ){ $GLOBALS['CORE_THEME']['header_style_text'] = ""; }

		$STRING .= hook_header_style6(stripslashes($GLOBALS['CORE_THEME']['header_style_text']));

	} break;

	case "5": { // LOGO LONG + TEXT

		// LOGO
		$STRING .= '<div class="col-md-6 col-sm-6 col-xs-12" id="core_logo"><a href="'.get_home_url().'/" title="'.get_bloginfo('name').'">'.hook_logo(true).'</a></div>';
		$STRING .= '<div class="col-md-6 col-sm-6 col-xs-12">';
		$STRING .= hook_header_style5(stripslashes($GLOBALS['CORE_THEME']['header_style_text']));
		$STRING .= '</div>';
	} break;

	case "4": { // LOGO LONG + SEARCH

		// LOGO
		$STRING .= '<div class="col-md-6 col-sm-6 col-xs-12" id="core_logo"><a href="'.get_home_url().'/" title="'.get_bloginfo('name').'">'.hook_logo(true).'</a></div>';
		$STRING .= '<div class="col-md-6 col-sm-6 col-xs-12" id="core_header_searchbox">';

		$searchform = '<form action="'.get_home_url().'/" method="get" id="wlt_searchbox_form">
			<div class="wlt_searchbox clearfix">
				<div class="inner">
					<div class="wlt_button_search"><i class="glyphicon glyphicon-search"></i></div>
					<input type="text" name="s" placeholder="'.$CORE->_e(array('button','11','flag_noedit')).'" value="'.(isset($_GET['s']) ? $_GET['s'] : "").'">
				</div>';

			if(!isset($GLOBALS['CORE_THEME']['addthis']) || ( isset($GLOBALS['CORE_THEME']['addthis']) && $GLOBALS['CORE_THEME']['addthis']  == 1 ) ){
			$searchform .= "<div class='text-right hidden-xs'>".do_shortcode('[D_SOCIAL size=16]')."</div>";
			}

			$searchform .= '</div></form>';

			$STRING .= hook_header_searchbox($searchform);

			$STRING .= '</div>';

	} break;
	case "2": { // LOGO MENU SPLIT

		// LOGO
		$STRING .= '<div class="col-md-4 col-sm-12" id="core_logo"><a href="'.get_home_url().'/" title="'.get_bloginfo('name').'">'.hook_logo(true).'</a></div>';
		$STRING .= '<div class="col-md-8 col-sm-12">
			<nav class="navbar hidden-xs"><div class="container-fluid">'.
			wp_nav_menu( array(
				'container' => 'div',
				'container_class' => '',
				'theme_location' => 'primary',
				'menu_class' => 'nav navbar-nav',
				'fallback_cb'     => '',
				'echo'            => false,
				'walker' => new Bootstrap_Walker(),
				) )	.
		'</div></nav></div>';

	} break;
	case "3": {	 // FULL HEADER IMAGE
		// LOGO
		$STRING .= hook_logo_wrapper('<div class="col-md-12" id="core_logo"><a href="'.get_home_url().'/" title="'.get_bloginfo('name').'">'.hook_logo(true).'</a></div>');
	} break;

	default: {	// LOGO BANNER SPLIT
		// LOGO
		$STRING .= hook_logo_wrapper('<div class="col-md-5 col-sm-5" id="core_logo"><a href="'.get_home_url().'/" title="'.get_bloginfo('name').'">'.hook_logo(true).'</a></div>');
		// BANNER
		$STRING .= hook_banner_header_wrapper('<div class="col-md-7 col-sm-7 hidden-xs" id="core_banner">'.hook_banner_header($CORE->BANNER('header')).'</div>');
	} break;
}

$STRING .= hook_header_row_bottom('').'</div></div></div>';

echo hook_header_layout($STRING);




$STRING = "";

	// LOAD IN USER STYLE
	if(!isset($GLOBALS['CORE_THEME']['layout_menu'])){ $style = 3; }else{ $style = $GLOBALS['CORE_THEME']['layout_menu']; }

	// GET MENU CONTENT
	$MENUCONTENT = wp_nav_menu( array(
					'container' => 'div',
					'container_class' => 'navbar-collapse',
					'theme_location' => 'primary',
					'menu_class' => 'nav navbar-nav',
					'fallback_cb'     => '',
					'echo'            => false,
					'walker' => new Bootstrap_Walker(),
					) );


switch($style){

	case "1": { // NO MENU

	if($GLOBALS['CORE_THEME']['layout_header'] == 2 && strlen($MENUCONTENT) > 2 ){

			$GLOBALS['flasg_smalldevicemenubar'] = true;
		$STRING = '<!-- [WLT] FRAMRWORK // MOBILE MENU -->

		<div class="container-fluid visible-xs" id="core_smallmenu"><div class="row">
			<div id="wlt_smalldevicemenubar">
			<a href="javascript:void(0);" class="b1" data-toggle="collapse" data-target=".wlt_smalldevicemenu">'.$CORE->_e(array('mobile','4')).' <span class="glyphicon glyphicon-align-justify"></span></a>
			 '.wp_nav_menu( array(
			'container' => 'div',
			'container_class' => 'wlt_smalldevicemenu collapse',
			'theme_location' => 'primary',
			'menu_class' => '',
			'fallback_cb'     => '',
			'echo'            => false,
			'walker' => new Bootstrap_Walker(),
			) ).'
			</div>
		</div></div>';

		unset($GLOBALS['flasg_smalldevicemenubar']);
	}

	} break;

	case "5": {

		// DISPLAY MENU

		$GLOBALS['flasg_smalldevicemenubar'] = true;
		$STRING = '<!-- [WLT] FRAMRWORK // MENU STYLE 5 -->

		<div class="container-fluid" id="core_smallmenu"><div class="row">
			<div id="wlt_smalldevicemenubar">
			<a href="javascript:void(0);" class="b1" data-toggle="collapse" data-target=".wlt_smalldevicemenu">'.$CORE->_e(array('mobile','4')).' <span class="glyphicon glyphicon-align-justify"></span></a>
			 '.wp_nav_menu( array(
			'container' => 'div',
			'container_class' => 'wlt_smalldevicemenu collapse',
			'theme_location' => 'primary',
			'menu_class' => '',
			'fallback_cb'     => '',
			'echo'            => false,
			'walker' => new Bootstrap_Walker(),
			) ).'
			</div>
		</div></div>

		<div id="core_menu_wrapper" class="menu_style5"><div class="'.$CORE->CSS("container", true).'"><div class="row"><nav class="navbar">';
		unset($GLOBALS['flasg_smalldevicemenubar']);

		if(!$userdata->ID){
		$EXTRA =  "<li class='pull-right'><a href='".site_url('wp-login.php?action=login', 'login_post')."'>".$CORE->_e(array('button','59'))."</a></li>";
		}else{

		$EXTRA = "<li class='pull-right'>
		<a href='".$GLOBALS['CORE_THEME']['links']['myaccount']."'>".$CORE->_e(array('head','4'))."</a>
		</li>";

		}

		$EXTRA = hook_menu_style5($EXTRA);

		$STRING .= str_replace("</ul></div>", $EXTRA. "</ul></div>",$MENUCONTENT) .'</nav></div></div></div>';

	} break;

	case "4": { //ADD LISTING BUTTON

		// DISPLAY MENU

		$GLOBALS['flasg_smalldevicemenubar'] = true;
		$STRING = '<!-- [WLT] FRAMRWORK // MENU STYLE 4 -->

		<div class="container-fluid" id="core_smallmenu"><div class="row">
			<div id="wlt_smalldevicemenubar">
			<a href="javascript:void(0);" class="b1" data-toggle="collapse" data-target=".wlt_smalldevicemenu">'.$CORE->_e(array('mobile','4')).' <span class="glyphicon glyphicon-align-justify"></span></a>
			 '.wp_nav_menu( array(
			'container' => 'div',
			'container_class' => 'wlt_smalldevicemenu collapse',
			'theme_location' => 'primary',
			'menu_class' => '',
			'fallback_cb'     => '',
			'echo'            => false,
			'walker' => new Bootstrap_Walker(),
			) ).'
			</div>
		</div></div>

		<div id="core_menu_wrapper" class="menu_style4"><div class="'.$CORE->CSS("container", true).'"><div class="row"><nav class="navbar">';
		unset($GLOBALS['flasg_smalldevicemenubar']);

		$STRING .= "<div class='pull-right visible-lg'>
		<a href='".$GLOBALS['CORE_THEME']['links']['add']."'>
		<div class='button clearfix'>
			<div class='title'>".$CORE->_e(array('homepage','4'))."</div>
		</div>
		</a>
		</div>";


		$STRING .= $MENUCONTENT .'</nav></div></div></div>';


	} break;

	case "2":
	default: {


		// DISPLAY MENU
		if(strlen($MENUCONTENT) > 1){
		$GLOBALS['flasg_smalldevicemenubar'] = true;
		$STRING = '<!-- [WLT] FRAMRWORK // MENU -->

		<div class="container-fluid" id="core_smallmenu"><div class="row">
			<div id="wlt_smalldevicemenubar">
			<a href="javascript:void(0);" class="b1" data-toggle="collapse" data-target=".wlt_smalldevicemenu">'.$CORE->_e(array('mobile','4')).' <span class="glyphicon glyphicon-align-justify"></span></a>
			 '.wp_nav_menu( array(
			'container' => 'div',
			'container_class' => 'wlt_smalldevicemenu collapse',
			'theme_location' => 'primary',
			'menu_class' => '',
			'fallback_cb'     => '',
			'echo'            => false,
			'walker' => new Bootstrap_Walker(),
			) ).'
			</div>
		</div></div>


		<div id="core_menu_wrapper"><div class="'.$CORE->CSS("container", true).'"><div class="row"><nav class="navbar">';
		unset($GLOBALS['flasg_smalldevicemenubar']);

		// STYLE 2
		if($style == "2"){
			$STRING .= hook_menu_searchbox('<form action="'.get_home_url().'/" method="get" id="wlt_searchbox_form" class="hidden-sm hidden-xs">
			<div class="wlt_searchbox">

			<div class="input-group" style="max-width:300px;">
<input type="search" name="s" placeholder="'.$CORE->_e(array('button','11','flag_noedit')).'">
<div class="wlt_button_search"><i class="glyphicon glyphicon-search"></i></div>

</div>

			</div>
			</form>');
		}

		$STRING .= $MENUCONTENT .'</nav></div></div></div>';

		}


	} break;

} // end switch


echo hook_menu($STRING);

}
?>
