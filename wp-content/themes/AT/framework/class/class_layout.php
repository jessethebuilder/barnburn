<?php

class core_layout extends white_label_themes {

function sociallogin(){ global $CORE;
 
if( isset($_GET['sociallogin']) && $_GET['sociallogin'] && in_array($_GET['sociallogin'] ,array("facebook","twitter","linkedin","google") ) ) { 
			 
			$pp = trim($_GET['sociallogin']); 
	 
			// LOAD DEFAULT
			$core_admin_values = get_option("core_admin_values");
			
			// CHECK TO MAKE SURE ITS ENABLED
			if($core_admin_values['allow_socialbuttons'] != 1){
			//die('social login disabled');
			}
			
			// BUILD CONGIF DATA
			$config = array();
			$config['base_url'] = home_url()."/index.php?sociallogin=".$pp;
			//$config['debug_mode'] = true; 
			//$config['debug_file'] = "";
			$providers =  array(
				"twitter" => array(
					"kname" => "Twitter",
					"name" => "Twitter",
				),			
				"facebook" => array(
					"kname" => "Facebook",
					"name" => "Facebook",
				),
				"linkedin" => array(
					"kname" => "LinkedIn",
					"name" => "LinkedIn",
				),
				
				 
			);
			foreach($providers as $key => $pro){ 
				
				$config['providers'][$providers[$key]['kname']]['enabled'] = $core_admin_values['social_'.$key.''];
				
				switch($key){
					case "facebook": {
						$config['providers'][$providers[$key]['kname']]['keys'] = array(
							"id" => trim($core_admin_values['social_'.$key.'_key1']), 
							"secret" => trim($core_admin_values['social_'.$key.'_key2'])					
						);						
						$config['providers'][$providers[$key]['kname']]["trustForwarded"] = 0;
						//$config['providers'][$providers[$key]['kname']]["display"] = "popup"; // optional
						
					} break;
					default: {
						$config['providers'][$providers[$key]['kname']]['keys'] = array(
							"key" => trim($core_admin_values['social_'.$key.'_key1']), 
							"secret" => trim($core_admin_values['social_'.$key.'_key2'])			
						);	
						
						//$config['providers'][$providers[$key]['kname']]["scope"] = "r_fullprofile";
					} break;
				}
						
			}
		 
			// SWITCH TYPE
			switch($pp){
				case "twitter": {
					$provider_name = "Twitter";
				} break;
				case "facebook": {
					$provider_name = "Facebook";
				} break;
				case "facebook": {
					$provider_name = "Facebook";
				} break;
				case "linkedin": {
					$provider_name = "LinkedIn";
				} break;
				default: { die("service provider (".esc_attr($pp).") not found".$_SERVER['REQUEST_URI']);}
			}
		 	
			
			require_once( TEMPLATEPATH."/framework/Hybrid/Auth.php" );
			require_once( TEMPLATEPATH."/framework/Hybrid/Endpoint.php" );
			$ha = new Hybrid_Auth($config);
			
			if (isset($_REQUEST['hauth_start']) || isset($_REQUEST['hauth_done']))
			{
				Hybrid_Endpoint::process();
			} 
			
			$adapter = $ha->authenticate( $provider_name );
			$user_bits = $adapter->getUserProfile();
			 
			$fname = $user_bits->firstName;
			$lname = $user_bits->lastName;
			$email = "";
			$identifier = $user_bits->identifier;
			
			switch($pp){
				case "facebook":
				case "google":
				case "linkedin": {
					$email = $user_bits->email;					
				} break;
				case "twitter": {
					$email = $user_bits->displayName."@twitter-sociallogin.com";
				} break;
				 
			}
 
			if(strlen($email) < 4){
					
				header("location: ".home_url()."/wp-login.php?socialloginerror=1");
				exit();
					
			}else{
					
						$gg = explode("@", $email);
						$newusername = $gg[0].date('s');
						
						$random_password = wp_generate_password( $length=12, $include_standard_special_chars=false );
						$_POST['password'] = $random_password;
				
						// CREATE NEW USER
						$errors = $CORE->USER_REGISTER($newusername, $random_password, $email, 1, 1, $GLOBALS['CORE_THEME']['links']['myaccount']);
						
						// IF HAS ERRORS					 
						if ( is_wp_error($errors) ) {						 
							  echo '<h4>' . $errors->get_error_message() . '</h4>';
							  die();
						}
					
			} // end if
		 
			 }

}


function handle_home_template_object1(){ global $OBJECTS;

	// GET HOME PAGE OBJECTS
 	 
    if(isset($GLOBALS['CORE_THEME']['homepage']) && isset($GLOBALS['CORE_THEME']['homepage']['widgetblock1']) && strlen($GLOBALS['CORE_THEME']['homepage']['widgetblock1']) > 1 && isset($GLOBALS['CORE_THEME']['homeeditor']) && $GLOBALS['CORE_THEME']['homeeditor'] == 1){
		echo '<div id="core_homepage_underheader_wrapper">';
        echo $OBJECTS->WIDGETBLOCKS($GLOBALS['CORE_THEME']['homepage']['widgetblock1'], false, true);
		echo '</div>';
     }

}
function handle_home_template_object2(){ global $OBJECTS;

	// GET HOME PAGE OBJECTS
    if(isset($GLOBALS['CORE_THEME']['homepage']) && isset($GLOBALS['CORE_THEME']['homepage']['widgetblock1']) && strlen($GLOBALS['CORE_THEME']['homepage']['widgetblock1']) > 1 && isset($GLOBALS['CORE_THEME']['homeeditor']) && $GLOBALS['CORE_THEME']['homeeditor'] == 1){
		echo '<div id="core_homepage_fullwidth_wrapper">';
        echo $OBJECTS->WIDGETBLOCKS($GLOBALS['CORE_THEME']['homepage']['widgetblock1'],true, false );
		echo '</div>';
    }

}

function __construct(){
	
	// WP_HEADER
	add_filter('wp', array($this, 	'handle_header_template') );
	
	// SOCIAL LOGIN
	add_filter('wp', array($this, 	'sociallogin') );
	
	// TEMPLATE ADJUSTMENTS
	add_filter('page_template', array($this, 	'handle_page_template') );
	add_filter('single_template', array($this,	'handle_post_type_template') );
	add_filter('home_template', array($this,	'handle_home_template') );
	add_filter('search_template', array($this, 	'handle_search_template') );
	add_filter('archive_template', array($this, 'handle_search_template') );
	add_filter('taxonomy_template', array($this, 'handle_search_template') );
	add_filter('author_template', array($this, 'handle_author_template') );
	 	 
		// PAGE FILTERS
		add_filter( 'the_content', array($this, 'my_the_content_filter' ) );
		 
		// LOGIN PAGE
		add_action('hook_login_before', array($this, 'login_before' ) );
		add_action('login_form', array($this, 'login_form' ) );
		
		// REGISTER PAGE
		add_action('hook_register_before', array($this, 'register_before' ) );
		add_action('register_form', array($this, 'register_form' ) );
	
		// SEARCH RESULTS PAGE
		add_filter('hook_gallerypage_results_title', array($this, 'gallerypage_results_title' ) );		 
		add_action('hook_items_before', array($this, 'gallerypage_results_top' ) );
		
		// TPL-CALLBACK PAGE
		add_action('hook_callback_success',array($this,'_hook_callback_success') );
		
		// ITEM FILTERS
		add_action('hook_item_class', array($this,'gallerypage_item_class'), 1 );
		
		// IMAGE ADJUSTMENTS
		add_filter( 'get_avatar' , array($this, 'image_avatar' ) , 1 , 4 );
		
		// CONTENT FILTERS
		add_filter('hook_listing_templatename', array($this, 'hook_listing_templatename' ) );
		add_filter('hook_content_templatename', array($this, 'hook_content_templatename' ) );
		
		// MAP INS EARCH RESULTS
		add_action('hook_core_columns_right_top', array($this, 'hook_map_display' ) );
		add_action('hook_core_columns_left_top', array($this, 'hook_map_display1' ) );
	
		// SELLSPACE ADVERTING HOOKS
		add_action('hook_core_columns_right_bottom', array($this, 'hook_sidebar_bottom' ) );
		add_action('hook_core_columns_left_bottom', array($this, 'hook_sidebar_bottom1' ) );
		
		add_action('hook_taxonomy_title_after', array($this,'taxonomy_title_before' ) ); 
		
		
}



function hook_sidebar_bottom(){ global $CORE;
	echo $CORE->BANNER('sidebar_right_bottom'); 
}
function hook_sidebar_bottom1(){ global $CORE;
	echo $CORE->BANNER('sidebar_left_bottom'); 
}
function hook_map_display(){ global $CORE;

if( isset($GLOBALS['CORE_THEME']['display_search_map'] ) && $GLOBALS['CORE_THEME']['display_search_map']  == "2" ){ 

echo $this->wlt_googlemap_html(false);  

}

echo $CORE->BANNER('sidebar_right_top'); 

}

function hook_map_display1(){ global $CORE;
 
if( isset($GLOBALS['CORE_THEME']['display_search_map'] ) && $GLOBALS['CORE_THEME']['display_search_map']  == "1" ){ 

echo $this->wlt_googlemap_html(false);  

}

echo $CORE->BANNER('sidebar_left_top'); 


}

function hook_listing_templatename($c){
	 
	// MOBILE VIEW
	if(defined('IS_MOBILEVIEW')){
	return "listing-mobile";	
	}
	
	if($GLOBALS['CORE_THEME']['customlisting_enable'] == 1 && $c == THEME_TAXONOMY."_type"){
	return "listing";
	}
 
	$c = str_replace("coupon_type","listing_type",$c);
	$c = str_replace("product_type","listing_type",$c);
	$c = str_replace("_type","",$c);
	if($c == "listing" && isset($GLOBALS['CORE_THEME']['single_layout'])){ $c = str_replace("content-","",$GLOBALS['CORE_THEME']['single_layout']); }
 
 
	// DEMO UPDATE ISSUE
	if(defined('WLT_DEMOMODE') && $c == "listing" && $GLOBALS['CORE_THEME']['single_layout'] == ""){
 
		$HandlePath = TEMPLATEPATH;
		if(substr($HandlePath,-1) != "/"){
		$HandlePath = $HandlePath . "/";
		} 
		if($handle1 = opendir($HandlePath)) {      
			while(false !== ($file = readdir($handle1))){			 		
				if(strpos($file,"content-single-".$THEMESTUB) !== false ){ 				
				$file_name = str_replace(".php","",str_replace("content-single-","",$file)); 
				
				return str_replace("_type","",$file_name);
				}
			 
		} }
	}
	 
	return $c;
}
function hook_content_templatename($c){
 
	if($c == "post"){
	return $c;
	}
	
	if($GLOBALS['CORE_THEME']['customsearch_enable'] == 1){
	return "listing";
	}

	$c = str_replace("coupon_type","listing_type",$c);
	$c = str_replace("product_type","listing_type",$c);
	$c = str_replace("_type","",$c); 
 
	if(isset($GLOBALS['CORE_THEME']['search_layout'])){ $c =  str_replace("content-","",$GLOBALS['CORE_THEME']['search_layout']); } 
	
	// DEMO UPDATE ISSUE
	if(defined('WLT_DEMOMODE') && $c == "listing" && $GLOBALS['CORE_THEME']['search_layout'] == ""){
 
		$HandlePath = TEMPLATEPATH;
		if(substr($HandlePath,-1) != "/"){
		$HandlePath = $HandlePath . "/";
		} 
		if($handle1 = opendir($HandlePath)) {      
			while(false !== ($file = readdir($handle1))){			 		
				if(strpos($file,"content-listing-".$THEMESTUB) !== false ){ 				
				$file_name = str_replace(".php","",str_replace("content-","",$file)); 
				
				return str_replace("_type","",$file_name);
				}
			 
		} }
	}


	if(defined('WLT_DEMOMODE') && isset($_SESSION['skin']) && file_exists(WP_CONTENT_DIR."/themes/".$_SESSION['skin']."/content-".$c.".php") ){

			include(WP_CONTENT_DIR."/themes/".$_SESSION['skin']."/content-".$c.".php");	
			return "";
			
	}else{
		return $c;
	} 
	
}
	
function login_form(){ if(isset($_GET['redirect']) || isset($_GET['redirect_to']) ){ ?>
 <input type="hidden" name="redirect_to" value="<?php if(isset($_GET['redirect'])){  echo esc_attr($_GET['redirect']); }elseif(isset($_GET['redirect_to'])){  echo esc_attr($_GET['redirect_to']); }else{ echo $GLOBALS['CORE_THEME']['links']['myaccount']; } ?>" />
<?php    
} }
function register_form(){
     if(isset($_GET['redirect'])){ ?>
    <input type="hidden" name="redirect" value="<?php echo esc_attr($_GET['redirect']); ?>" /> 
    <?php }elseif($_GET['redirect_to']){ ?>
    <input type="hidden" name="redirect" value="<?php echo esc_attr($_GET['redirect_to']); ?>" /> 
    <?php }
}
function register_before(){	
	
	// SPAM PROTECTION BY MARK FAIL
	if($_SERVER['HTTP_REFERER'] == "" && !isset($_GET['stopspam']) && !isset($_GET['pid']) ){
	global $CORE;
	?>
	<p class="alert alert-warning"><?php echo str_replace("%a", site_url('wp-login.php?action=register&stopspam=1', 'login_post'), $CORE->_e(array('login','22'))); ?></p>
	<?php 
	get_footer($CORE->pageswitch());
	die();
	}
}
function login_before(){
	
	if(defined('WLT_DEMOMODE')){ ?>
	<div class="bs-callout bs-callout-info">
				<button type="button" class="close" data-dismiss="alert">x</button>
				<h4 class="alert-heading">Demo Account Logins</h4>
				<p>You can login with the details below to test our the members and admin areas.</p>
				<p>
				  Username: <b>demo</b> / Password: <b>demo</b>
				</p>
				<p>Username: <b>admindemo</b> / Password: <b>admindemo</b> </p>
	</div>
	<?php }
}
function _hook_callback_success(){ global $payment_data;

   $gc = stripslashes(get_option('google_conversion'));
   
   if(isset($payment_data['orderid'])){        
   echo str_replace("[orderid]",$payment_data['orderid'], $gc ); 
   }
   
   if(isset($payment_data['description'])){
   $gc = str_replace("[description]",$payment_data['description'], $gc);
   }
   
   if(isset($payment_data['total'])){
   $gc = str_replace("[total]",$payment_data['total'], $gc);
   }
   
   echo $gc;	
	
}
function image_avatar($avatar, $id_or_email, $size, $default){ global $wpdb;
	 
	 	// GET USERID
		if(is_object($id_or_email)){
			if(isset($id_or_email->ID))
				$id_or_email = $id_or_email->ID;
			//Comment
			else if($id_or_email->user_id)
				$id_or_email = $id_or_email->user_id;
			else if($id_or_email->comment_author_email)
				$id_or_email = $id_or_email->comment_author_email;
		}
		
		$userid = false;
		if(is_numeric($id_or_email))
			$userid = (int)$id_or_email;
		else if(is_string($id_or_email))
			$userid = (int)$wpdb->get_var("SELECT ID FROM $wpdb->users WHERE user_email = '" . esc_sql($id_or_email) . "'");
		
		// FALLBACK IF NOT AVATAR
		if(!$userid){ return $avatar; }
		
		// CHECK IF ISSET
		$userphoto = get_user_meta($userid,'userphoto',true);
		 
		if(is_array($userphoto) && isset($userphoto['path'])){
			return "<img src='".$userphoto['img']."' class='avatar img-responsive' alt='image' />";
		}else{
			return str_replace('avatar ','avatar img-responsive ',$avatar);
		}
}
function handle_header_template($template_dir) { global $CORE;

	if(is_admin()){ return; }

	// LOAD IN COLUMN LAYOUTS
	$CORE->BODYCOLUMNS();
	
	// CUSTOM HEADER
	header('X-UA-Compatible: IE=edge,chrome=1');
}
function my_the_content_filter($content) { global $post, $CORE;
	  
	  if(isset($GLOBALS['flag-page'])){
	  
		// MEMBERSHIP ACCESS
		if(!$CORE->MEMBERSHIPACCESS($post->ID)){
		$content = stripslashes($GLOBALS['CORE_THEME']['noaccesscode']);
		}
	 
	  }
	 
	  return $content;
}	
function _hook_single1(){ $GLOBALS['flag_single_content'] = true; }
function _hook_single2(){ unset($GLOBALS['flag_single_content']); }
function _facebookmeta(){ global $post, $CORE;  if($post->post_excerpt == ""){ $exce = $post->post_content; }else{ $exce = $post->post_excerpt; } ?>


<meta property="og:url" content="<?php echo get_permalink($post->ID); ?>" />
<meta property="og:type" content="article" />
<meta property="og:title" content="<?php echo esc_html(strip_tags($post->post_title)); ?>" />
<meta property="og:description" content="<?php echo substr(esc_html(strip_tags($exce)),0,255); ?>" />
<meta property="og:image" content="<?php echo $CORE->GETIMAGE($post->ID, false, array('pathonly' => true) ); ?>" />
<meta property="og:image:width" content="700" />
<meta property="og:image:height" content="700" />
<?php }
function _facebookmeta_cat(){ global $post, $CORE;   $term = get_term_by('slug', get_query_var( 'term' ), get_query_var( 'taxonomy' ) ); 
	
	// RETURN IF NOT FOUND
	if(!isset($term->term_id)){ return; } 
	
	// CHECK FOR IMAGE
	if( isset($term->term_id) && isset($GLOBALS['CORE_THEME']['category_icon_'.$term->term_id]) ){
	
	$image = str_replace("&", "&amp;",$GLOBALS['CORE_THEME']['category_icon_'.$term->term_id]);
 	
	 ?>
	<meta property="og:url" content="<?php echo 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?>" />
    <meta property="og:type" content="article" />
	<meta property="og:title" content="<?php echo esc_html($term->name); ?>" />
	<meta property="og:description" content="<?php echo substr(esc_html(strip_tags($term->description)),0,255); ?>" />
	<meta property="og:image" content="<?php echo $image; ?>" />	
	<?php } ?>

<?php }
function handle_post_type_template($single_template) { global $post, $CORE;
 
  if ($post->post_type == THEME_TAXONOMY."_type") {
	  
		// SET FLAG
	 	$GLOBALS['flag-single'] = 1;
		
		// SINGLE PAGE FILTERS
		add_filter('hook_single_before', array($this, 'TOOLBOX') );
		add_filter('hook_single_before', array($this, '_hook_single1') );
		add_filter('hook_single_after', array($this, '_hook_single2') );
		
		// ADD ON FACEBOOK META
		add_action('wp_head',  array($this, '_facebookmeta') ); 
 
		// UPDATE VIEW COUNTER
		$CORE->HITCOUNTER($post->ID);
		
		// UPDATE VIEW COUNTER
		$CORE->RECENTLYVIEWED($post->ID,"");
		 	
		// CHECK FOR FORCED LOGIN
		if(isset($GLOBALS['CORE_THEME']['requirelogin']) && $GLOBALS['CORE_THEME']['requirelogin'] == 1){ $CORE->Authorize(); }
		
		// CHECK IF EXPIRED
		$CORE->EXPIRED();
			
		// CHECK FOR TIMEOUT ACCESS
		$canWatch = $CORE->TIMEOUTACCESS($post->ID);
		
		// EXTRA FOR FEEDBACK
		if(isset($_GET['ftyou'])){
		
			$GLOBALS['error_type'] 		= "success"; //ok,warn,error,info
			$GLOBALS['error_message'] 	= $CORE->_e(array('feedback','7'));
				
		}		
     
	 }else{
	 	
		// SET FLAG
		$GLOBALS['flag-blog'] = true; 
		
	 } 
	 
	 // ADD BOOTSTRAP IMG-RESPONSIVE CODE
	 add_filter( 'the_content', array($this, '_make_images_responsive' ) );
	 
	 //RETURN	 
     return str_replace("single-post.php","single.php",$single_template);
}

function _make_images_responsive($content) {
  
  $content = str_replace("wp-image","img-responsive wp-image", $content);
  
  return $content;
}

function handle_home_template($template_dir) { 
    
	// SET FLAG
	$GLOBALS['flag-home'] = 1; 
	
	// MOBILE HOME PAGE
	if(defined('IS_MOBILEVIEW') && file_exists(str_replace("home.php","home-mobile.php",$template_dir))){	
	 
		return str_replace("home.php","home-mobile.php",$template_dir);	
	
	}elseif(defined('IS_MOBILEVIEW')){
	
		return TEMPLATEPATH."/home-mobile.php";
	
	}
	
	// ACTION
	add_action('hook_header_after', array($this, 'handle_home_template_object1') );
	
	// ACTION
	add_action('hook_core_columns_wrapper_inside_inside', array($this, 'handle_home_template_object2') );
 	
	//RETURN
	return $template_dir;
}
 
function handle_search_template($template_dir) { 

	// SETUP PAGE GLOBALS
	global $wp_query, $post, $CORE, $category;
	
	// ADD ON FACEBOOK META
	add_action('wp_head',  array($this, '_facebookmeta_cat') ); 
 	 
	// MOBILE VIEW
	if(defined('IS_MOBILEVIEW')){
	return THEME_PATH. "search-mobile.php";	
	} 
 
 	// EXTRAS
	if(is_object($post) && $post->post_type == THEME_TAXONOMY."_type"){
 	 	
		// SET FLAG JUST IN CASE WP DOESNT DO IT
		$GLOBALS['flag-search'] = 1;		
		
		// INCLUDE GOOGLE MAP
		add_action('hook_header_after', array($CORE, 'wlt_googlemap_search') ); 
 		
		// EXTRA FOR LISTING CATEGORIES
		if($template_dir == ""){			
			$template_dir = THEME_PATH. "search.php";			
		} 
	
	}elseif(is_object($post) && $post->post_type == "post"){
	
		$GLOBALS['flag-blog'] = true; // FLAG FOR WIDGETS
	
	}
		
	//RETURN
	return $template_dir;
}

function gallerypage_item_class($c){ global $post, $CORE; $extra = ""; 
 
	
	// DEFAULTS FOR GALLERY PAGE
	if(isset($GLOBALS['flag-search'])){ 
 
		switch($GLOBALS['CORE_THEME']['default_gallery_perrow']){
		case "2": { $c = "col-md-6 col-sm-6"; } break;
		case "3": { $c = "col-md-4  col-sm-4"; } break;
		case "4": { $c = "col-md-3  col-sm-3"; } break;
		case "5": { $c = "col-md-new5  col-sm-new5"; } break;
		default: { $c = "col-md-4  col-sm-6"; } break;
		}
 
		// READJUST FOR 3 COLUMN LAYOUTS
		if( $GLOBALS['CORE_THEME']['layout_columns']['search'] == 4){ $c = "col-md-3  col-sm-6"; }
	
	}
 
	 
	// EXTRAS FOR HOME PAGE OBJECTS
	if(isset($GLOBALS['item_class_size'])){ $c = $GLOBALS['item_class_size']; }
	
	// ADD-ON PENDING CLASS FOR VIEWING OWN LISTINGS
	if(isset($_GET['uid']) && ($post->post_status == "pending" || $post->post_status == "draft")){ $extra .= " pending"; }
	 
	// CHANGE SPAN SIZE FOR 3 COLUMN LAYOUTS
	if(isset($GLOBALS['CORE_THEME']['layout_columns']['3columns']) && $GLOBALS['CORE_THEME']['layout_columns']['3columns'] == "1"){ $c = str_replace("col-md-3","col-md-4",$c); }
	 
	//RETURN
	echo hook_content_listing_class($c." item-".$post->ID." col-xs-12".$extra.$CORE->FEATURED($post->ID));
	
}
function gallerypage_results_top(){ global $CORE, $post, $paged, $wp_query;

if(!defined('WLT_CART')){  
 		
	// GLOBALS
	$category = $wp_query->get_queried_object();
 
	if(isset($category->slug) && ( !isset($paged) || $paged < 2 ) ){ 
	 	
			$top_category_results_string = "";	 $top_category_results_string_e = ""; $i=0; $c=0;
			if(is_object($category)){ 
			$args = array(
			'post_type' => THEME_TAXONOMY.'_type',
				'posts_per_page' => '10',
				'orderby' => 'rand',
				'tax_query' => array(
					array(
						'taxonomy' => THEME_TAXONOMY,
						 'field' => 'id',
						 'terms' => array( $category->term_id ),
					)
				),
				'meta_query' => array(
				   array(
					   'key' => 'topcategory',
					   'value' => 'yes',				 
				   )
			   ),
			);
			
			$my_query = new WP_Query($args);
			
			 
			while ( $my_query->have_posts() ) {
				$my_query->the_post();
			 
				
				if($i%4){ $ff = ""; }else{ $ff = " butleft"; $i=1; }
				
					// CONTENT LISTING 
					$GLOBALS['item_class_size'] = 'col-md-4 catoplist';
						
					ob_start();
					get_template_part( 'content', hook_content_templatename($post->post_type) );
					echo "<style>.wlt_search_results .itemid".$post->ID." { display:none; } .wlt_search_results .catoplist.itemid".$post->ID."  { display:block; } .wlt_search_results .swaped .wlt_starrating { display:none; }</style>";
					$top_category_results_string .= ob_get_contents();
					ob_end_clean();
					
					unset($GLOBALS['item_class_size']);
				 
				
				if($c > 1){
				$top_category_results_string_e .='jQuery("#catoplist .wlt_search_results .item:gt('.$c.')").hide();';
				}
				$i++; $c++;
				 
			}
		}
		if(isset($top_category_results_string) && strlen($top_category_results_string) > 5){
		
   		// ECHO OUTPUT
		echo $top_category_results_string; 
		 
		
		if($c > 3 && isset($GLOBALS['CORE_THEME']['topofcategoryrotate']) && $GLOBALS['CORE_THEME']['topofcategoryrotate'] == 1 ){ ?>
            <script type="application/javascript">
            jQuery(document).ready(function() {
                var swapLast = function() {
                <?php echo $top_category_results_string_e; ?>
                    jQuery(".wlt_search_results .catoplist:last").delay(7000).slideUp('slow', function() {
                        jQuery(this).delay(5000).remove();
						jQuery(this).addClass('swaped');
                        jQuery(".wlt_search_results").delay(7000).prepend(jQuery(this));
                        jQuery(this).delay(7000).slideDown('slow', function() {
                            swapLast();
				 
                        });
                    });
                }
                
                swapLast();
            });
            </script>
            <?php } ?>
        <?php }
		}		

}// end defined WLT_CART 

}
function taxonomy_title_before(){ global $CORE, $category;
 
	
	// print out sub categories
	if($GLOBALS['CORE_THEME']['subcategories'] == '1' && !isset($_GET['s']) ){ 
		$term = get_term_by( 'slug', get_query_var( 'term' ), get_query_var( 'taxonomy' ) );
		if ($term->parent == 0) {		 
			$cats = wp_list_categories('echo=0&taxonomy='.THEME_TAXONOMY.'&depth=1&hide_count=0&hide_empty=0&title_li=&child_of=' . $term->term_id);
			
			if(strpos(strtolower($cats), "no c") === false){
			echo '<div id="wlt_core_subcategories"><ul class="list-inline">'.$cats.'</ul><div class="clearfix"></div></div>';
			}
		}	
	}
}

function gallerypage_results_title($c){ global $CORE, $category, $wp_query;
 
	// EXTRASD FOR ZIPCODE SEARCHES
	$title_extra = "";
	if(isset($_GET['zipcode']) && strlen($_GET['zipcode']) > 2){
			$saved_searches = get_option('wlt_saved_zipcodes');
			
			
			if(isset($saved_searches[$_GET['zipcode']]['log'])){
			$longitude 	= $saved_searches[$_GET['zipcode']]['log'];
			}else{ $longitude =0; }
			
			if(isset($saved_searches[$_GET['zipcode']]['lat'])){
			$latitude 	= $saved_searches[$_GET['zipcode']]['lat'];
			}else{ $latitude =0; }			
		 
			$title_extra 	= "(". esc_html($_GET['zipcode']).") <span class='right' style='text-decoration:underline;font-size:16px;'><a href='https://www.google.com/maps/place/".$latitude.",".$longitude."/' rel='nofollow' target='_blank'>".$latitude.",".		$longitude."</a></span>";
		$GLOBALS['CORE_THEME']['default_gallery_map'] = 1;
	}elseif(isset($_GET['s'])){
			$title_extra = ": ".strip_tags($_GET['s']);
	}
		
	if(isset($category->name) && strlen($category->name) > 1){ 
			$c = $category->name; 
	}else{
			$c = $CORE->_e(array('gallerypage','0'))." ".$title_extra;
	} 
	 
return $c;
}
function handle_page_template($template_dir) { global $post, $userdata, $wp_query, $CORE;
 
	if ( is_page_template() ) {
		
		// EXTRAS FOR CALLBACK PAGE
		if(strpos($template_dir, "tpl-callback") !== false){
	  	
		// SET FLAG
		$GLOBALS['flag-callback'] = 1;
		
		// PAYMENT DATA GLOBAL
		global $payment_status, $payment_data;
	 
	 	// ADD HOOK FOR PAYPAL
		add_action('hook_callback','core_paypal_callback');
		add_action('hook_callback','core_usercredit_callback');
		
		// GET PAYMENT RESPONSDE
		$payment_status = hook_callback($_POST);
		if(isset($_POST['order_data_raw'])){
		$payment_data = $_POST['order_data_raw'];
		}else{
		$payment_data = "";
		}
		
		
		// AUTO FOR FORCING PAYMENT SUCCESS
		if(isset($_GET['auth'])){ $payment_status = "success"; }
		
		  
		// EMAIL OPTIONS
		if(isset($payment_status) && $payment_status != ""){
		 
			switch($payment_status){
				case "thankyou":
				case "success": { 
				
					$sentAlready = get_user_meta($userdata->ID,'email_sent_order_new_sccuess',true);
					if( $sentAlready == "" ){					
						$CORE->SENDEMAIL($userdata->user_email,'order_new_sccuess'); 
						update_user_meta($userdata->ID,'email_sent_order_new_sccuess', date('Y-m-d H') );						
					}elseif(!defined('WLT_CART') && $sentAlready == date('Y-m-d H')){
					
					}else{
						$CORE->SENDEMAIL($userdata->user_email,'order_new_sccuess'); 	
						update_user_meta($userdata->ID,'email_sent_order_new_sccuess', date('Y-m-d H') );
					}
					
					
					// SEND EMAIL
					$CORE->SENDEMAIL('admin','admin_order_new');
					
				
				} break;
				default: { 
				
					$sentAlready = get_user_meta($userdata->ID,'email_sent_order_new_failed',true);
					if( $sentAlready == "" ){					
						$CORE->SENDEMAIL($userdata->user_email,'order_new_failed'); 
						update_user_meta($userdata->ID,'email_sent_order_new_failed', date('Y-m-d H') );						
					}elseif(!defined('WLT_CART') && $sentAlready == date('Y-m-d H')){
					
					}else{
						$CORE->SENDEMAIL($userdata->user_email,'order_new_failed'); 
						update_user_meta($userdata->ID,'email_sent_order_new_failed', date('Y-m-d H') );
					} 
				 
				} break;
			   }
		 
			 
		}
		
			// REMOVE SESSIONS
			if(defined('WLT_CART')){
			 
				session_destroy();
				// DELETE STORED SESSION COOKIE
				if (ini_get("session.use_cookies")) {
					$params = session_get_cookie_params();
					setcookie(session_name(), '', time() - 42000,
						$params["path"], $params["domain"],
						$params["secure"], $params["httponly"]
					);
				}
			}
			
		} // END IF CALLBACK
 
	 
	}else{
		
		// SET FLAG
		$GLOBALS['flag-page'] = 1;
		 
 		// CHECK FOR PAGE WIDGET
		$GLOBALS['page_width'] 	= get_post_meta($post->ID, 'width', true);
		if($GLOBALS['page_width'] =="full"){ $GLOBALS['nosidebar-right'] = true; $GLOBALS['nosidebar-left'] = true; }
		 
	}
	
	//RETURN
	return $template_dir;
}
function handle_author_template($template_dir) { global $post,$userdata, $authorID, $listingcount, $wp_query, $CORE;
   
	// SET FLAG 
	$GLOBALS['flag-author'] = 1;
	
	if(isset($_POST['action']) && $_POST['action'] !=""){

		switch($_POST['action']){
		
			case "delfeedback": {	
			 
			$my_post 				= array();
			$my_post['ID'] 			= $_POST['fid'];
			$my_post['post_status'] = "draft";
			wp_update_post( $my_post );	
			
			$GLOBALS['error_message'] 	= "Feedback Deleted";				
			
			} break;
		
		}	
	} 
  
	// GET THE AUTHOR ID 
	if(isset($_GET['author']) && is_numeric($_GET['author'])){
	$authorID = $_GET['author'];
	}else{	
	$author = get_user_by( 'slug', get_query_var( 'author_name' ) );
	$authorID = $author->ID;
	}
		
	// GET LISTING COUNT
	$listingcount = $CORE->count_user_posts_by_type( $authorID, THEME_TAXONOMY."_type" );
	
	//RETURN
	return $template_dir;
}

}
?>