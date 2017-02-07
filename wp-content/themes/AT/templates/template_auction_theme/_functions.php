<?php
// Anysoft ---------------------------------
function load_custom_scripts(){
	wp_register_script('custom_scripts', get_template_directory_uri() . '/templates/template_auction_theme/custom_scripts.js', array('jquery'));
	wp_enqueue_script('custom_scripts');

	wp_register_style('custom_styles', get_template_directory_uri() . '/templates/template_auction_theme/custom_styles.css');
	wp_enqueue_style('custom_styles');
}

add_action('wp_enqueue_scripts', 'load_custom_scripts');
// end Anysoft ----------------------------
?>

<?php
// ADD IN AUCTION
define('WLT_AUCTION',true);


function _hook_header_searchbox($c){ global $CORE, $wpdb, $userdata;


if(isset($GLOBALS['CORE_THEME']['auction_sbtns']) && $GLOBALS['CORE_THEME']['auction_sbtns'] == 0){ return $c; }

$c1 = 0; $c2 = 0; $c3 = 0; $s1 = ""; $s2 = ""; $s3 = "";

if($userdata->ID){

// COUNT ITEMS IM BIDDING ON
$SQL = "SELECT count(*) AS total FROM ".$wpdb->prefix."posts
	INNER JOIN ".$wpdb->prefix."postmeta AS mt2 ON (".$wpdb->prefix."posts.ID = mt2.post_id)
	WHERE ".$wpdb->prefix."posts.post_type = '".THEME_TAXONOMY."_type'
	AND ( ".$wpdb->prefix."posts.post_status = 'publish' )
	AND mt2.meta_key = 'bidstring' AND mt2.meta_value LIKE ('%-".$userdata->ID."-%')";

	$EXISTINGDATA = get_option('wlt_system_counts');
	if(!is_array($EXISTINGDATA) || current_user_can( 'edit_user', $userdata->ID ) ){ $EXISTINGDATA = array(); }

 	if(!isset($EXISTINGDATA['bidon']['date']) || ( isset($EXISTINGDATA['bidon']['date']) && strtotime($EXISTINGDATA['bidon']['date']) < strtotime(current_time( 'mysql' )) ) ) {

		$result = $wpdb->get_results($SQL);

		$EXISTINGDATA['bidon'] = array("date" => date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s'). "+10 minutes")), "count" => $result[0]->total );

		update_option("wlt_system_counts", $EXISTINGDATA , true);

		$c2 = $result[0]->total;

	}else{

		$c2 = $EXISTINGDATA['bidon']['count'];

	}



if($c2 > 0){ $s2 = "red"; }

 // COUNT WINNING ITEMS
$SQL = "SELECT count(*) AS total FROM ".$wpdb->prefix."posts
	INNER JOIN ".$wpdb->prefix."postmeta AS mt2 ON (".$wpdb->prefix."posts.ID = mt2.post_id)
	WHERE ".$wpdb->prefix."posts.post_type = '".THEME_TAXONOMY."_type'
	AND ( ".$wpdb->prefix."posts.post_status = 'publish' )
	AND mt2.meta_key = 'bidwinnerstring' AND mt2.meta_value LIKE ('%-".$userdata->ID."-%')";

	$EXISTINGDATA = get_option('wlt_system_counts');
	if(!is_array($EXISTINGDATA) || current_user_can( 'edit_user', $userdata->ID ) ){ $EXISTINGDATA = array(); }

 	if(!isset($EXISTINGDATA['bidwin']['date']) || ( isset($EXISTINGDATA['bidwin']['date']) && strtotime($EXISTINGDATA['bidwin']['date']) < strtotime(current_time( 'mysql' )) ) ) {

		$result = $wpdb->get_results($SQL);

		$EXISTINGDATA['bidwin'] = array("date" => date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s'). "+10 minutes")), "count" => $result[0]->total );

		update_option("wlt_system_counts", $EXISTINGDATA , true);

		$c3 = $result[0]->total;

	}else{

		$c3 = $EXISTINGDATA['bidwin']['count'];

	}

if($c3 > 0){ $s3 = "red"; }

$c1 = $CORE->MESSAGECOUNT($userdata->user_login);

if($c1 > 0){ $s1 = "red"; }

} // end if userdata->ID

ob_start();
?>


<div class="row clearfix hidden-xs" id="auctionsearchblock">

    <div class="col-md-4">

        <form action="<?php echo get_home_url(); ?>/" method="get" class="hidden-sm hidden-xs" id="wlt_searchbox_form">
        <div class="wlt_searchbox clearfix">

            <div class="inner">
               <div class="wlt_button_search"><i class="glyphicon glyphicon-search"></i></div>
                <input type="text" name="s" placeholder="<?php echo $CORE->_e(array('button','11','flag_noedit')); ?>" value="<?php echo (isset($_GET['s']) ? $_GET['s'] : ""); ?>">
            </div>

        </div>

        </form>

    </div>

    <div class="col-md-8">

        <ul class="nav nav-pills" role="tablist">
          <li><a href="<?php echo $GLOBALS['CORE_THEME']['links']['myaccount'].'?notify=1'; ?>"><?php echo $CORE->_e(array('auction','99')); ?> <span class="badge <?php echo $s1; ?>"><?php echo $c1;  ?></span></a></li>
          <li><a href="<?php if(!$userdata->ID){ echo wp_login_url( ''); }else{ echo get_home_url(); ?>/?s=&orderby=bidding<?php } ?>"><?php echo $CORE->_e(array('auction','97')); ?> <span class="badge <?php echo $s2; ?>"><?php echo $c2; ?></span></a></li>
          <li><a href="<?php if(!$userdata->ID){ echo wp_login_url( '' ); }else{  echo get_home_url(); ?>/?s=&orderby=bidwin<?php } ?>"><?php echo $CORE->_e(array('auction','98')); ?> <span class="badge <?php echo $s3; ?>"><?php echo $c3; ?></span></a></li>
        </ul>

    </div>

</div>





<?php
return ob_get_clean();
}
add_action('hook_header_searchbox','_hook_header_searchbox');
?>
