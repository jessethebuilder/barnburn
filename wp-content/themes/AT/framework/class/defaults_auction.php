<?php
//--- Anysoft ----

//require_once(ABSPATH . 'wp-content/debug/anysoft.php');
define('STANDARD_BID_INCREASE', 0.02);

?>
<?php
function hook_custom_paypal_payment($c){   return  apply_filters('hook_custom_paypal_payment', $c);   }

class core_auctions  {

	function _expiry_listing_action($c){ global $CORE, $post, $wpdb, $userdata;

	// GET LISTING ID
	if(is_numeric($c)){
		$LISTINGID = $c;
	}elseif(isset($post->ID) && is_numeric($post->ID)){
		$LISTINGID = $post->ID;
	}else{
		return;
	}

	// WORK OUT ANY OF THE AUCTION DETAILS AND WINNER
	$current_bidding_data = get_post_meta($LISTINGID,'current_bid_data',true);
	$reserve_price = get_post_meta($LISTINGID,'price_reserve',true);
	$price_current = get_post_meta($LISTINGID,'price_current',true);

	// RE-ORDER DATA
	if(!is_array($current_bidding_data)){ $current_bidding_data = array(); }
	krsort($current_bidding_data);

	// CHECK RESERVE PRICE AND INCREASE USERS BID IF LOWER THAN RESERVE
	$checkme = current($current_bidding_data);

	if($reserve_price != "" && $reserve_price > 0 && $reserve_price > $price_current){
		if(isset($checkme['username']) && $checkme['max_amount'] >= $reserve_price && is_numeric($reserve_price) && $reserve_price != "0" ){
			// update the price so it meets the reserve
			update_post_meta($LISTINGID,'price_current',get_post_meta($LISTINGID,'price_reserve',true));

			$price_current = $reserve_price;
		}
	}

	// IF NO RESERVE PRICE CHECK FOR THE HIGHEST BIDDER
	// ??


	// REMOVE EXPIRY DATE
	update_post_meta($LISTINGID,'listing_expiry_date','');

	//SEND EMAIL TO BIDDERS
	$_POST['winningbid'] = hook_price($price_current);
	$_POST['title'] 	 = $post->post_title;
	$_POST['link'] 		 = get_permalink($LISTINGID);


		// LOOP BIDDERS
		if(is_array($current_bidding_data) && !empty($current_bidding_data)){
			$sent_to_array = array(); $i=1;
			foreach($current_bidding_data as $maxbid=>$data){
				if($i == 1 && $data['max_amount'] > 0 ){

					// CHECK IF THE AUCTION WAS WON SUCCESSULlY
					if($reserve_price > 0 && $reserve_price > $price_current){	//new


					}else{

						$_POST['username'] 	= $data['username'];
						$CORE->SENDEMAIL($data['userid'],'auction_ended_winner');

						update_post_meta($LISTINGID,'bidstring', '');
						update_post_meta($LISTINGID,'bidwinnerstring', get_post_meta($LISTINGID,'bidwinnerstring', true)."-".$data['userid']."-");

					}


				}else{
					if(!in_array($data['userid'],$sent_to_array)){
						$_POST['username'] 	= $data['username'];
						$CORE->SENDEMAIL($data['userid'],'auction_ended');
						array_push($sent_to_array,$data['userid']);
					}// end if
				}// end if
				$i++;
			}
		}

	//SEND EMAIL TO AUCTION SELLER
	$author_data = get_userdata( $post->post_author );
	$_POST['username'] 	= $author_data->display_name;
	$CORE->SENDEMAIL($post->post_author,'auction_ended_owner');

	// IF THE ITEM SOLD, ADD A COMISSION AMOUNT TO THE USERS ACCOUNT SO THEY HAVE TO PAY THE ADMIN
	$comissionadded = 0;
	$price_current = get_post_meta($LISTINGID,'price_current',true);
	$reserve_price = get_post_meta($LISTINGID,'price_reserve',true);
	$price_shipping = get_post_meta($LISTINGID,'price_shipping',true);


	if($price_current > 0 && ( $price_current > $reserve_price ) && ( isset($GLOBALS['CORE_THEME']['auction_house_percentage']) && strlen($GLOBALS['CORE_THEME']['auction_house_percentage']) > 0 ) ){

		if(is_numeric($price_shipping) && $price_shipping > 0){
			$price_current += $price_shipping;
		}

		// WORK OUT AMOUNT OWED BY THE SELLER
		$AMOUNTOWED = ($GLOBALS['CORE_THEME']['auction_house_percentage']/100)*$price_current;
		$AMOUNTOWED = -1 * abs($AMOUNTOWED);

		// CHECK WE HAVENT ALREADY DEDUCTED
		$ALREADY_DEDUCTED = get_user_meta($post->post_author,'wlt_action_deducted',true);
		if(strpos($ALREADY_DEDUCTED,"*".$LISTINGID) === false){

			$user_balance = get_user_meta($post->post_author,'wlt_usercredit',true);
			if($user_balance == ""){ $user_balance = 0; }
			$user_balance = $user_balance+$AMOUNTOWED;
			update_user_meta($post->post_author,'wlt_usercredit', $user_balance);
			update_user_meta($post->post_author,'wlt_action_deducted', $ALREADY_DEDUCTED.'*'.$LISTINGID );

		}

		$comissionadded = $AMOUNTOWED;

		// SEND EMAIL TO THE SELLER
		$CORE->SENDEMAIL($post->post_author,'auction_itemsold');

	}

	// ADD LOG ENTRY
	$CORE->ADDLOG("<a href='".home_url()."/?p=".$LISTINGID."'>".$post->post_title.'</a> auction finished. (comission '.$comissionadded.')', $LISTINGID,'','label-inverse');


	return "stop";// this will stop it going to draft

	}

	/* =============================================================================
		[TIMELEFT] - SHORTCODE
		========================================================================== */
	function shortcode_timeleft( $atts, $content = null ) { global $wpdb, $userdata, $CORE, $post, $shortcode_tags; $STRING = ""; $strTxt = "";

		extract( shortcode_atts( array('postid' => "", "layout" => "", "text_before" => "", "text_ended" => "", "key" => "listing_expiry_date" ), $atts ) );

		// SETUP ID FOR CUSTOM DISPLAY
		$milliseconds = str_replace("+","",round(microtime(true) * 100)); $milliseconds .= rand( 0, 10000 );

		// CHECK FOR CUSTOM POST ID
		if($postid == ""){ $postid = $post->ID; }

		// GET VALUE FROM LISTING
		$expiry_date = get_post_meta($postid,$key,true);

		if($expiry_date == "" || strlen($expiry_date) < 3){

				// EXPIRED DISPLAY HERE
				if(defined('IS_MOBILEVIEW')){
					return "<span class='aetxt'>".$CORE->_e(array('auction','3'))."</span>";
				}

				// GET THE LISTING DATA
				$str = "";
				$expiry_date = get_post_meta($post->ID,'listing_expiry_date',true);
				$current_bidding_data = get_post_meta($post->ID,'current_bid_data',true);
				$reserve_price = get_post_meta($post->ID,'price_reserve',true);
				$price_current = get_post_meta($post->ID,'price_current',true);


				if(!is_array($current_bidding_data)){ $current_bidding_data = array(); }
				krsort($current_bidding_data);
				$checkme = current($current_bidding_data);

				// AUCTION HAS ENDED
				if($expiry_date == "" || strtotime($expiry_date) < strtotime(current_time( 'mysql' ))){

					if(is_numeric($reserve_price) && $reserve_price != "0" && $price_current < $reserve_price){

						$strTxt  .= $CORE->_e(array('auction','1'));

					}elseif(isset($checkme['username']) ){

						$strTxt  .= "".$checkme['username'].$CORE->_e(array('auction','2'));

					}

					if(isset($strTxt) && strlen($strTxt) > 1){

						$str  .=  "<span>".$strTxt."</span>";

					}

				}

				return "<div class='ea_finished'><span class='aetxt'>".$CORE->_e(array('auction','3'))."</span> ".$str."</div>";

		} // END EXPIRY DATE DISPLAY

		// SWITCH LAYOUTS
		switch($layout){
			case "1": { $layout_code = ",layout: '".$text_before." {sn} {sl}, {mn} {ml}, {hn} {hl}, and {dn} {dl}',"; } break;
			case "2": { $layout_code = ",compact: true, "; } break;
			default: { $layout_code = ""; } break;
		}
		if(strlen($expiry_date) == 10){ $expiry_date = $expiry_date." 00:00:00"; }

		// REFRESH PAGE EXTR
		$run_extra =  ""; $run_extrab  = "";

		// DISPLAY AFTER FINISHED
		if(isset($GLOBALS['flag-single'])){

		$run_extra = "location.reload(); jQuery('#auctionbidform').hide();";

		}else{

		$run_extra = "jQuery('#timeleft_".$postid.$milliseconds."_wrap').html('<div class=ea_finished><span class=aetxt>".$CORE->_e(array('auction','3'))."</span></div>');";

		}

		// BUILD DISPLAY
		$STRING = "<span id='timeleft_".$postid.$milliseconds."_wrap'><span id='timeleft_".$postid.$milliseconds."'></span></span>";

		// FORE EXPIRY IF ALREADY EXPIRED
		if(strtotime($expiry_date) < strtotime(current_time( 'mysql' )) ) {

		$STRING .= "<script> jQuery(document).ready(function(){ CoreDo('". str_replace("https://","",str_replace("http://","",get_home_url()))."/?core_aj=1&action=validateexpiry&pid=".$postid."', 'timeleft_".$postid.$milliseconds."'); });</script> ";

		}

		$STRING .= "<script>

			jQuery(document).ready(function() {
			var dateStr ='".$expiry_date."'
			var a=dateStr.split(' ');
			var d=a[0].split('-');
			var t=a[1].split(':');
			var date1 = new Date(d[0],(d[1]-1),d[2],t[0],t[1],t[2]);
			jQuery('#timeleft_".$postid.$milliseconds."').countdown({timezone: ".get_option('gmt_offset').", until: date1, onExpiry: WLTvalidateexpiry".$postid."".$layout_code." });
			});

			function WLTvalidateexpiry".$postid."(){ ".$run_extrab." setTimeout(function(){ CoreDo('". str_replace("https://","",str_replace("http://","",get_home_url()))."/?core_aj=1&action=validateexpiry&pid=".$postid."', 'timeleft_".$postid.$milliseconds."'); ".$run_extra." }, 1000);  };

			</script>";


			return $STRING;
	}


	function __construct(){

	if(is_admin()){

		// BIDDING HISTORY SIDEBAR
		add_action('admin_menu', array($this, '_custom_metabox' ) );

		// PAGE SETUP OPTIONS IN THE THEME
		add_action('hook_admin_1_pagesetup', array($this, '_hook_admin_1_pagesetup'  ) );
	}


	// MOBILE FUNCTIONS
	add_action('hook_mobile_content_listing_output', array($this, 'mobilelistingcotent' ), 1 );
	add_action('hook_mobile_content_output', array($this, 'mobilesearchcontent' ), 1 );
	add_action('hook_mobile_header', array($this, 'mobile_header' ) );
	add_action('hook_mobile_footer', array($this, 'mobile_footer' ) );


	 add_action('init', array($this,'_init'));
	 add_action('wp_head', array($this,'_wp_head'));

	 // RELIST AUCTIONS
	 add_action('wp_head', array($this,'_relistactions'));

	 // HOOK THE EXPIRY FUNCTION FOR AUCTIONS
	 add_action('hook_expiry_listing_action', array($this, '_expiry_listing_action' ));

 	 // HOOK INTO THE EDIT PAGE AND ADD-ON STORE FIELDS
	 add_action('hook_fieldlist_0', array($this, '_fields' ) );

	 // ADD IN NEW EMAILS
	 add_action('hook_email_list_filter', array($this, '_newemails' ) );

	 // ADD IN NEW MEMBER AREA OPTIONS
	 add_action('hook_account_dashboard_before', array($this, '_memberblock' ) );
	  add_action('hook_account_after', array($this, '_paymentform' ) );

	 add_action('hook_account_pagelist', array($this, '_hook_account_pagelist' ) );
	 add_action('hook_account_save', array($this,'_saveaccount' ));

	 // HOOK ALL CUSTOM QUERIES TO REMOVE FINISHED AUCTIONS FROM DISPLAY
	// add_action('hook_custom_queries',  array($this, '_hook_custom_query' ) );

	 // HOOK SUBMISSION PAGE AND ADD IN CORE FIELDS
	 add_action('hook_add_fieldlist',  array($this, '_hook_customfields' ) );

	 // HOOK LISTING EXPIRY DATE FOR NON-PACKAGE ITEMS
	 add_action('hook_add_form_post_save_extra', array($this, '_hook_post_save' ) );

	 // CUSTOM OUTPUT FOR PRICES
	 add_action('hook_item_pre_code',  array($this, '_hook_item_pre_code' ) );


	 // NEW SHORTCODES
	add_action('hook_admin_2_tags_search', array($this,'_new_tags' ) );
	add_action('hook_admin_2_tags_listing', array($this,'_new_tags' ) );
	add_action('hook_admin_2_tags_listing', array($this,'_new_tags1' ) );


	// REMOVE EDIT BOX IF BIDS ARE THERE
	add_action('hook_single_after', array($this,'_removebox' ) );

	// REQUIRE PAYPAL
	add_action('hook_add_form_abovebutton', array($this, 'requirepaypal' ) );
	add_action('hook_tpl_add_field_validation', array($this, '_hook_tpl_add_field_validation' ) );
	add_action('hook_add_form_post_save_extra', array($this, '_hook_add_form_post_save_extra'));


	// ADD IN SHORTCODES
	add_shortcode( 'BIDDINGHISTORY', array($this,'shortcode_biddinghistory') );
	add_shortcode( 'BIDS', array($this,'shortcode_bids') );

	add_shortcode( 'BIDDINGFORM', array($this,'shortcode_biddingform') );
	add_shortcode( 'BIDDINGTIMELEFT', array($this,'shortcode_timeleft') );

	// ADD PAYPAL TO ADMIN AREA EDIT SCREEN
	add_action( 'show_user_profile', array($this,'extra_user_profile_fields') );
	add_action( 'edit_user_profile', array($this,'extra_user_profile_fields') );
	add_action( 'personal_options_update', array($this,'save_extra_user_profile_fields') );
	add_action( 'edit_user_profile_update', array($this,'save_extra_user_profile_fields') );

	// REMOVE 0 FROM DISPLAY OF LISTING
	add_action('hook_content_listing', array($this, '_hook_content_listing' ) );

	add_action('hook_account_dashboard_items',  array($this, '_hook_account_dashboard_items' ) );


	// HOOK IN LANGUAGE TEXT
	add_action('hook_language_array', array($this,'_hook_language_array') );


	}










function _hook_account_dashboard_items($c){ global $userdata, $wpdb, $CORE;

// GET MESSAGE COUNT
$mc = $CORE->MESSAGECOUNT($userdata->user_login);
if($mc == ""){ $mc = 0; }

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

ob_start();
?>

<li class="list-group-item col-md-3 col-sm-12 col-xs-12 text-center">
        <a href="javascript:void(0);" onclick="jQuery('#MyDetailsBlock').hide();jQuery('#MyMsgBlock').show(); jQuery('#MyFeedback').hide(); jQuery('#MyDashboardBlock').hide();">
        <span><?php echo $mc; ?></span> <?php echo $CORE->_e(array('account','113')); ?></a>
</li>

 <li class="list-group-item col-md-3 col-sm-12 col-xs-12 text-center">
 <a href="<?php if(!$userdata->ID){ echo wp_login_url( ''); }else{ echo get_home_url(); ?>/?s=&orderby=bidding<?php } ?>"><span><?php echo $c2; ?></span> <?php echo $CORE->_e(array('auction','97')); ?></a></li>

<li class="list-group-item col-md-2 col-sm-12 col-xs-12 text-center">
<a href="<?php if(!$userdata->ID){ echo wp_login_url( '' ); }else{  echo get_home_url(); ?>/?s=&orderby=bidwin<?php } ?>">

 <span><?php echo $c3; ?></span> <?php echo $CORE->_e(array('auction','98')); ?></a></li>


<?php
return ob_get_clean();
}


/* =============================================================================
	  ADMIN BIDDING HISTORY
	========================================================================== */

	 function _custom_metabox(){
	 add_meta_box( 'wlt_auction_bidhistory', "Bidding History", array($this, '_admin_bidhistory' ), THEME_TAXONOMY.'_type', 'side', 'high' );
	 }
	 function _admin_bidhistory(){ global $post;

	echo do_shortcode('[BIDDINGHISTORY]');
	echo "<a href='edit.php?post_type=listing_type&resetaction=".$post->ID."'>reset auction</a>";
	 }


function _hook_add_form_post_save_extra(){ global $userdata;


	if(isset($_POST['user_paypalemail']) && strlen($_POST['user_paypalemail']) > 1 ){

		update_user_meta( $userdata->ID, 'user_paypalemail',$_POST['user_paypalemail']);

	}

}


/* =============================================================================
	  MOBILE ADJUSTMENTS
	========================================================================== */

	function mobilelistingcotent(){ global $post, $userdata; $GLOBALS['CUSTOMMOBILECONTENT'] = true;  ?>

	<?php if($post->post_author == $userdata->ID){ ?><a href="[EDIT]">Edit</a><?php } ?>

	<div style="background:#fff; padding:10px; border:1px solid #ddd;">

        <h1 style="font-size:16px; margin-top:0px; margin-bottom:10px; padding-bottom:10px; border-bottom:1px solid #efefef; text-align:center;">[TITLE]</h1>

        <div class="text-center">[IMAGES]</div>

        <ul class="menulist">

            <li class="timeleft">[TIMELEFT]</li>

        </ul>

        [BIDDINGBOX]

        <b>{Description}</b>

        [CONTENT]

        [FIELDS]

        [GOOGLEMAP]

        <ul class="menulist">

            <li>[FAVS]</li>

            <li>[CONTACT style=1 class=""]</li>

        </ul>

	</div>

	<?php }


	function mobilesearchcontent($c){ global $post, $CORE; $GLOBALS['CUSTOMMOBILECONTENT'] = true;

	?>

	<div style="padding:10px;">

    	[IMAGE]

		<h2 style="font-weight:bold;margin-top:0px;">[TITLE]</h2>

    	 [EXCERPT size=60]

	</div>

    <div class="clearfix"></div>

	<div style="background:#eee; height:30px; padding:5px; font-size:11px; text-transform:uppercase; color:#999; font-weight:bold; ">
        <div class="row">
       		<div class="col-md-5 col-xs-6">[TIMELEFT layout=2]  </div>
        	<div class="col-md-7 col-xs-6 text-right">  [BIDS] <?php echo $CORE->_e(array('auction','88')); ?> / [hits] <?php echo $CORE->_e(array('single','19')); ?> </div>
        </div>
	</div>
	<?php }

	function mobile_header(){ ?>
	<style>
	.nav-pills { display:none; }

.countdown_section {	display: block;	float: left;	font-size: 80%;	text-align: center;}


.single .countdown_holding span {color: #888;}
.single .countdown_row {	clear: both;	width: 100%;	padding: 0px 0px;	text-align: center;}
.single .countdown_show1 .countdown_section {width: 98%;}
.single .countdown_show2 .countdown_section {	width: 48%;}
.single .countdown_show3 .countdown_section {	width: 32.5%;}
.single .countdown_show4 .countdown_section {	width: 24.5%;}
.single .countdown_show5 .countdown_section {	width: 19.5%;}
.single .countdown_show6 .countdown_section {	width: 16.25%;}
.single .countdown_show7 .countdown_section {	width: 14%;}
.single .countdown_section {	display: block;	float: left;	font-size: 75%;	text-align: center;}
.single .countdown_amount {	font-size: 200%;}
.single .countdown_descr {	display: block;	width: 100%;}
.timeleft { height:60px; }
.timeleftbox { font-weight:bold; color:#666; }
.searchblock .frame img { float:left; padding-right:20px; }


.single  #auctionbidbox { margin-top:20px; margin-bottom:20px; }
.single  #auctionbidbox .topbits div {   text-align:center;  }
.single  #auctionbidbox .topbits span { font-weight:bold;  display:block; color:gray; }
.single  #auctionbidbox .topbits a { color:gray; }

.single  #auctionbidbox .pricebits div .txt { font-weight:bold; font-size:12px; line-height:40px; }
.single  #auctionbidbox .pricebits .priceextra { font-size:11px; display:block; padding-top:5px;  }
.pricebits strong { margin-right:10px;  line-height:40px; }
.single  #auctionbidbox .bidbox { display:none; }
.single  #auctionbidbox .bidbox .wrap { background:#F7F7F7; padding:20px; }
.single  #auctionbidbox .bidbox textarea { width:100%;height:100px; }
.single  #auctionbidbox .bidbox .label { cursor:pointer; }
.single  #auctionbidbox .bidbox .input-group input { border-radius:0px; height:40px; }
.single  #auctionbidbox .bidbox .input-group-addon { border-radius:0px; }
.single  #auctionbidbox .bidbox .txtb { font-size: 18px; padding-top: 8px;}
.single  #auctionbidbox pre span { display:block; }
.single  #auctionbidbox .btn { font-size:12px; background:#efefef; border:0px; }
.single  .paybits .wrap { padding:20px; }
.single  .paybits .txt { font-weight:bold; font-size:12px;  }
.single  .paybits .pull-right { margin-top:-10px; }
.single  .paybits .rnm { font-size:12px; font-weight:normal; }
.single  .imgbox { background:#F7F7F7; min-height:300px; padding:20px; }
.single  .imgbox #carousel { margin-bottom:0px !important; }
.single  .imgbox .flexslider { padding:0px; background:transparent; }
.single  #biddinghistory ul { max-height:300px; overflow-y: scroll; }

	</style>
	<?php }
	function mobile_footer(){ global $post; ?>

	<?php }






function _hook_account_pagelist($c){

global $CORE;

if(isset($GLOBALS['CORE_THEME']['auction_paypal']) && $GLOBALS['CORE_THEME']['auction_paypal'] == '1'){
$c[] = array(
	"l" => "#top",
	"oc" => "jQuery('#MyDashboardBlock').hide();jQuery('#MyAccountBlock').hide(); jQuery('#MyDetailsBlock').hide(); jQuery('#MyMJobs').show(); jQuery('#MyFeedback').hide(); jQuery('#MyPayments').show();",
	"i" => "glyphicon glyphicon-cog",
	"t" => $CORE->_e(array('auction','14')),
	"d" => $CORE->_e(array('auction','26')),
	"e" => "",
);
}




return $c;

}


	function _hook_content_listing($c){ global $wpdb, $CORE, $post;

		if(get_post_meta($post->ID, 'auction_type', true) == 2 ){

		$c = str_replace("[price_current]","[price_bin]", $c);
		$c = str_replace('class="bids"','class="bids" style="display:none;"', $c);
		$c = str_replace('<span class="bids"','<span class="bids">'.$CORE->_e(array('auction','55')).'</span> <span class="bids"', $c);
		}

		return $c;
	}

	function save_extra_user_profile_fields( $user_id ) {
	global $CORE;
	if ( !current_user_can( 'edit_user', $user_id ) ) { return false; }

		update_user_meta( $user_id, 'user_paypalemail',$_POST['user_paypalemail']);
	}

	function extra_user_profile_fields( $user ) { global $wpdb, $CORE; ?>

   <h3>PayPal Information</h3>


   <table class="form-table">


    <tr>
    <th><label>PayPal Email</label></th>
    <td>
    <input type="text" name="user_paypalemail" value="<?php echo get_user_meta($user->ID,'user_paypalemail',true); ?>" class="regular-text" />
    </td>
    </tr>



    </table>

    <?php }



	//1. HOOK INTO THE ADMIN MENU TO CREATE A NEW TAB
	function _hook_admin_1_pagesetup(){  global $wpdb, $CORE; $core_admin_values = get_option("core_admin_values");  ?>

	<div class="accordion-group">
    <div class="accordion-heading" style="background:#fff;">
        <a class="accordion-toggle collapsed" data-toggle="collapse" data-parent="#pagesetup_according" href="#extra1">
         <h4 style="margin:0xp;font-weight:bold;">
         <img src="<?php echo get_template_directory_uri(); ?>/framework/admin/img/icons/set.png">
         Auction Settings <span style="font-size:12px;">(view/hide)</span></h4>
        </a>
    </div>

    <div id="extra1" class="accordion-body collapse">
    <div class="accordion-inner">
    <div class="innerwrap content">




       <div class="form-row control-group row-fluid ">
                            <label class="control-label span6" rel="tooltip" data-original-title="This open will show/hide auctions which are finished from displaying." data-placement="top">Hide Finished Auctions</label>
                            <div class="controls span6">
                              <div class="row-fluid">
                                <div class="pull-left">
                                  <label class="radio off">
                                  <input type="radio" name="toggle"
                                  value="off" onchange="document.getElementById('hide_expired').value='0'">
                                  </label>
                                  <label class="radio on">
                                  <input type="radio" name="toggle"
                                  value="on" onchange="document.getElementById('hide_expired').value='1'">
                                  </label>
                                  <div class="toggle <?php if($core_admin_values['hide_expired'] == '1'){  ?>on<?php } ?>">
                                    <div class="yes">ON</div>
                                    <div class="switch"></div>
                                    <div class="no">OFF</div>
                                  </div>
                                </div>
                               </div>
                             </div>

                             <input type="hidden" class="row-fluid" id="hide_expired" name="admin_values[hide_expired]"
                             value="<?php echo $core_admin_values['hide_expired']; ?>">
            </div>






    	<div class="heading2">Auction Terms &amp; Conditions</div>


    <div class="form-row control-group row-fluid" id="myaccount_page_select">
        <label class="control-label span5" for="normal-field">House Percentage</label>
        <div class="controls span6">
         <div class="input-prepend">
          <span class="add-on">%</span>
          <input type="text" name="admin_values[auction_house_percentage]" value="<?php echo stripslashes($core_admin_values['auction_house_percentage']); ?>" class="span4">
        </div>
        <p>This is the % you will keep from sold auctions.</p>
        </div>
    </div>

    <div class="form-row control-group row-fluid ">
                            <label class="control-label span5" rel="tooltip" data-original-title="this will allow the user to relist their item if unsold." data-placement="top">Relist Option</label>
                            <div class="controls span5">
                              <div class="row-fluid">
                                <div class="pull-left">
                                  <label class="radio off">
                                  <input type="radio" name="toggle"
                                  value="0" onChange="document.getElementById('auction_relist').value='0'">
                                  </label>
                                  <label class="radio on">
                                  <input type="radio" name="toggle"
                                  value="1" onChange="document.getElementById('auction_relist').value='1'">
                                  </label>
                                  <div class="toggle <?php if($core_admin_values['auction_relist'] == '1'){  ?>on<?php } ?>">
                                    <div class="yes">ON</div>
                                    <div class="switch"></div>
                                    <div class="no">OFF</div>
                                  </div>
                                </div>
                               </div>
                             </div>

                             <input type="hidden" class="row-fluid" id="auction_relist" name="admin_values[auction_relist]"
                             value="<?php echo $core_admin_values['auction_relist']; ?>">
            </div>


    <!------------ FIELD -------------->
    <div class="form-row control-group row-fluid">
        <label class="control-label">Terms &amp; Conditions</label>
        <div class="controls">
        <textarea class="row-fluid" style="height:50px; font-size:11px;" name="admin_values[auction_terms]"><?php echo stripslashes($core_admin_values['auction_terms']); ?></textarea>
        </div>
    </div>



    <div class="heading2">Auction Listing Settings</div>









               <div class="form-row control-group row-fluid ">
                            <label class="control-label span6" rel="tooltip" data-original-title="This open will turn on/off the option for the user to select how long their auction lasts for. Turning this off will default to the listing package length." data-placement="top">Auction Length Option</label>
                            <div class="controls span6">
                              <div class="row-fluid">
                                <div class="pull-left">
                                  <label class="radio off">
                                  <input type="radio" name="toggle"
                                  value="off" onchange="document.getElementById('auction_theme_usl').value='0'">
                                  </label>
                                  <label class="radio on">
                                  <input type="radio" name="toggle"
                                  value="on" onchange="document.getElementById('auction_theme_usl').value='1'">
                                  </label>
                                  <div class="toggle <?php if($core_admin_values['auction_theme_usl'] == '1'){  ?>on<?php } ?>">
                                    <div class="yes">ON</div>
                                    <div class="switch"></div>
                                    <div class="no">OFF</div>
                                  </div>
                                </div>
                               </div>
                             </div>

                             <input type="hidden" class="row-fluid" id="auction_theme_usl" name="admin_values[auction_theme_usl]"
                             value="<?php echo $core_admin_values['auction_theme_usl']; ?>">
            </div>



 <div class="form-row control-group row-fluid ">
                            <label class="control-label span6" rel="tooltip" data-original-title="" data-placement="top">Buy Now Option</label>
                            <div class="controls span5">
                              <div class="row-fluid">
                                <div class="pull-left">
                                  <label class="radio off">
                                  <input type="radio" name="toggle"
                                  value="0" onChange="document.getElementById('auction_buynow').value='0'">
                                  </label>
                                  <label class="radio on">
                                  <input type="radio" name="toggle"
                                  value="1" onChange="document.getElementById('auction_buynow').value='1'">
                                  </label>
                                  <div class="toggle <?php if($core_admin_values['auction_buynow'] == '1'){  ?>on<?php } ?>">
                                    <div class="yes">ON</div>
                                    <div class="switch"></div>
                                    <div class="no">OFF</div>
                                  </div>
                                </div>
                               </div>
                             </div>

                             <input type="hidden" class="row-fluid" id="auction_buynow" name="admin_values[auction_buynow]"
                             value="<?php echo $core_admin_values['auction_buynow']; ?>">
            </div>




  <div class="form-row control-group row-fluid ">
                            <label class="control-label span6" rel="tooltip" data-original-title="" data-placement="top">Shipping Option</label>
                            <div class="controls span5">
                              <div class="row-fluid">
                                <div class="pull-left">
                                  <label class="radio off">
                                  <input type="radio" name="toggle"
                                  value="0" onChange="document.getElementById('auction_shipping').value='0'">
                                  </label>
                                  <label class="radio on">
                                  <input type="radio" name="toggle"
                                  value="1" onChange="document.getElementById('auction_shipping').value='1'">
                                  </label>
                                  <div class="toggle <?php if($core_admin_values['auction_shipping'] == '1'){  ?>on<?php } ?>">
                                    <div class="yes">ON</div>
                                    <div class="switch"></div>
                                    <div class="no">OFF</div>
                                  </div>
                                </div>
                               </div>
                             </div>

                             <input type="hidden" class="row-fluid" id="auction_shipping" name="admin_values[auction_shipping]"
                             value="<?php echo $core_admin_values['auction_shipping']; ?>">
            </div>



             <div class="heading2">Auction Display Settings</div>


              <div class="form-row control-group row-fluid ">
                            <label class="control-label span6" rel="tooltip" data-original-title="Turn ON/OFF the buttons next to the search box in your main header." data-placement="top">Header Buttons</label>
                            <div class="controls span5">
                              <div class="row-fluid">
                                <div class="pull-left">
                                  <label class="radio off">
                                  <input type="radio" name="toggle"
                                  value="0" onChange="document.getElementById('auction_sbtns').value='0'">
                                  </label>
                                  <label class="radio on">
                                  <input type="radio" name="toggle"
                                  value="1" onChange="document.getElementById('auction_sbtns').value='1'">
                                  </label>
                                  <div class="toggle <?php if($core_admin_values['auction_sbtns'] == '1'){  ?>on<?php } ?>">
                                    <div class="yes">ON</div>
                                    <div class="switch"></div>
                                    <div class="no">OFF</div>
                                  </div>
                                </div>
                               </div>
                             </div>

                             <input type="hidden" class="row-fluid" id="auction_sbtns" name="admin_values[auction_sbtns]"
                             value="<?php if(!isset($core_admin_values['auction_sbtns'])){ echo 1; }else{ echo $core_admin_values['auction_sbtns']; } ?>">
            </div>


             <div class="heading2">PayPal Settings</div>


      <div class="form-row control-group row-fluid ">
                            <label class="control-label span6" rel="tooltip" data-original-title="This will allow users to recieve payments to their PayPal email address." data-placement="top">Allow User PayPal Payments </label>
                            <div class="controls span5">
                              <div class="row-fluid">
                                <div class="pull-left">
                                  <label class="radio off">
                                  <input type="radio" name="toggle"
                                  value="0" onChange="document.getElementById('auction_paypal').value='0'">
                                  </label>
                                  <label class="radio on">
                                  <input type="radio" name="toggle"
                                  value="1" onChange="document.getElementById('auction_paypal').value='1'">
                                  </label>
                                  <div class="toggle <?php if($core_admin_values['auction_paypal'] == '1'){  ?>on<?php } ?>">
                                    <div class="yes">ON</div>
                                    <div class="switch"></div>
                                    <div class="no">OFF</div>
                                  </div>
                                </div>
                               </div>
                             </div>

                             <input type="hidden" class="row-fluid" id="auction_paypal" name="admin_values[auction_paypal]"
                             value="<?php echo $core_admin_values['auction_paypal']; ?>">
            </div>


      <div class="form-row control-group row-fluid ">
                            <label class="control-label span6" rel="tooltip" data-original-title="Force users to enter their PayPal email before creating new auctions." data-placement="top">Require PayPal </label>
                            <div class="controls span5">
                              <div class="row-fluid">
                                <div class="pull-left">
                                  <label class="radio off">
                                  <input type="radio" name="toggle"
                                  value="0" onChange="document.getElementById('auction_paypal_require').value='0'">
                                  </label>
                                  <label class="radio on">
                                  <input type="radio" name="toggle"
                                  value="1" onChange="document.getElementById('auction_paypal_require').value='1'">
                                  </label>
                                  <div class="toggle <?php if($core_admin_values['auction_paypal_require'] == '1'){  ?>on<?php } ?>">
                                    <div class="yes">ON</div>
                                    <div class="switch"></div>
                                    <div class="no">OFF</div>
                                  </div>
                                </div>
                               </div>
                             </div>

                             <input type="hidden" class="row-fluid" id="auction_paypal_require" name="admin_values[auction_paypal_require]"
                             value="<?php echo $core_admin_values['auction_paypal_require']; ?>">
            </div>


    </div>

     </div> </div>
    </div>

    <?php

    }


	function _hook_tpl_add_field_validation(){ global $CORE;
	?>
	var de422 	= document.getElementById("user_paypalemail");
    if( de422 != null && de422.value == ''){
        alert('<?php echo $CORE->_e(array('validate','0')); ?>');
        de422.style.border = 'thin solid red';
        de422.focus();
        colAll();
        return false;
    }
    <?php
	}

	function requirepaypal(){ global $CORE, $userdata;

		if(isset($GLOBALS['CORE_THEME']['auction_paypal_require']) && $GLOBALS['CORE_THEME']['auction_paypal_require'] == '1' && $userdata->ID){

			$pp = get_user_meta($userdata->ID, 'user_paypalemail', true);
			if($pp == ""){
			?>
            <div class="panel panel-default">

            <div class="panel-heading"><?php echo $CORE->_e(array('auction','14')); ?></div>

            <div class="panel-body">

            <p><?php echo $CORE->_e(array('auction','16')); ?></p>

            <hr />


           <b><?php echo $CORE->_e(array('auction','17')); ?> <span class="required">*</span></b>

            <input type="text" class="form-control" id="user_paypalemail" name="user_paypalemail" value="<?php echo get_user_meta($userdata->ID, 'user_paypalemail',true); ?>">


            <div class="clearfix"></div>
            <hr />

            </div>
            </div>
			<script>

			function CheckPD(){

				if(jQuery("#user_paypalemail").val() == ""){
				return false;
				}

				return true;

			}



            </script>
			<?php
			}
		}

	}

	function _removebox(){ global $post;

		$current_bidding_data = get_post_meta($post->ID,'current_bid_data',true);
		if(!is_array($current_bidding_data)){
		}else{
		echo "<style>#editlistingbox{ display:none;}</style>";
		}
	}


	// ADD A NEW SHROTCODE

	function _new_tags1(){echo "<br />[ADDBIG] - Displays the 'add-to' Cart Button";}





	function _hook_item_pre_code($c){ global $post;

	$price = get_post_meta($post->ID,'price_current',true);
	$auction_type = get_post_meta($post->ID,'auction_type',true);


		$current_bidding_data = get_post_meta($post->ID,'current_bid_data',true);
		if(!is_array($current_bidding_data)){
		$bidcount = 0;
		}else{
		$bidcount = count($current_bidding_data);
		}

	// ADD ON PRICE TAG
	$c = str_replace('[price_current]','<b>'.hook_price($price).'</b>', $c);

	// ADD ON PRICE TAG
	$c = str_replace('[BIDCOUNT]',$bidcount, $c);

	if($price == "" || $auction_type == 2){ // || $price == 0

		// ADJUST PRICE
		$bidprice = get_post_meta($post->ID,'price_bin',true);
		if($bidprice != ""){
		$c = str_replace("price_current","price_bin", $c);
		}else{
		$c = str_replace('text="[price_current]"',"text='0&nbsp;'", $c);
		}
	}

	return $c;
	}

	function _hook_post_save($POSTID){

		// SET EXPIRY DATE
		if(is_numeric($packagefields[$_POST['packageID']]['expires']) && !isset($_POST['eid']) ){

		}else{
			// MAKE SURE ITS NOT ALREADY SET
			$g = get_post_meta($POSTID,'listing_expiry_date',true);
			if($g == ""){
			update_post_meta($POSTID, 'listing_expiry_date', date("Y-m-d H:i:s", strtotime( current_time( 'mysql' ) . " +30 days")));
			}
		}

		// SAVE THE PRICES
		if(is_numeric($_POST['form']['price_bin'])){
		update_post_meta($POSTID, 'price_bin', $_POST['form']['price_bin']);
		}

		if(is_numeric($_POST['form']['price_reserve'])){
		update_post_meta($POSTID, 'price_reserve', $_POST['form']['price_reserve']);
		}

	}


	function _hook_customfields($c){ global $CORE;

		$o = 50;
		$canEditPrice = true;
		if(isset($_GET['eid'])){

			// CHECK FOR BIDDING SO WE CAN DISABLE FIELDS
			$current_bidding_data = get_post_meta($_GET['eid'],'current_bid_data',true);
			if(is_array($current_bidding_data) && !empty($current_bidding_data) ){ $canEditPrice = false; }

		}



		if(isset($GLOBALS['CORE_THEME']['auction_buynow']) && $GLOBALS['CORE_THEME']['auction_buynow'] == '1'){

			$c[$o]['title'] 	= $CORE->_e(array('auction','4'));
			$c[$o]['name'] 		= "auction_type";
			$c[$o]['type'] 		= "select";
			$c[$o]['class'] 	= "form-control";
			$c[$o]['listvalues'] 	= array("1" => $CORE->_e(array('auction','5')), "2" => $CORE->_e(array('auction','6')));
			$c[$o]['help'] 		= $CORE->_e(array('auction','7'))." <script>jQuery('#form_auction_type').change(function(e) { if(jQuery('#form_auction_type').val() == '2'){ jQuery('#form-row-rapper-price_current').hide(); jQuery('#form-row-rapper-price_reserve').hide(); } else { jQuery('#form-row-rapper-price_reserve').show();  jQuery('#form-row-rapper-price_current').show(); } }); </script> ";
			//$c[$o]['required'] 	= true;
			$c[$o]['defaultvalue'] 	= "0";

		}else{

			$c[$o]['title'] 	= $CORE->_e(array('auction','4'));
			$c[$o]['name'] 		= "auction_type";
			$c[$o]['type'] 		= "hidden";
			$c[$o]['class'] 	= "form-control";
			$c[$o]['values'] 	= 1;

		}

		$o++;


		if($canEditPrice){
		$c[$o]['title'] 	= $CORE->_e(array('auction','74'));
		$c[$o]['name'] 		= "price_current";
		$c[$o]['type'] 		= "price";
		$c[$o]['class'] 	= "form-control";
		$c[$o]['help'] 		= $CORE->_e(array('auction','75'));
		$c[$o]['required'] 	= true;
		$c[$o]['defaultvalue'] 	= "0";
		$o++;
		}


		if(isset($GLOBALS['CORE_THEME']['auction_buynow']) && $GLOBALS['CORE_THEME']['auction_buynow'] == '1'){

		if($canEditPrice){
		$c[$o]['title'] 	= $CORE->_e(array('auction','8'));
		$c[$o]['name'] 		= "price_bin";
		$c[$o]['type'] 		= "price";
		$c[$o]['class'] 	= "form-control";
		$c[$o]['help'] 		= $CORE->_e(array('auction','9'));
		//$c[$o]['required'] 	= true;
		$c[$o]['defaultvalue'] 	= "0";
		$o++;
		}

		$c[$o]['title'] 	= $CORE->_e(array('auction','95'));
		$c[$o]['name'] 		= "qty";
		$c[$o]['type'] 		= "text";
		$c[$o]['class'] 	= "form-control";
		$c[$o]['help'] 		= $CORE->_e(array('auction','96'))."<style>#form_qty { width:100px; } </style>";
		$c[$o]['required'] 	= true;
		$c[$o]['defaultvalue'] 	= "1";
		$o++;

		}

		if($canEditPrice){
		$c[$o]['title'] 	= $CORE->_e(array('auction','10'));
		$c[$o]['name'] 		= "price_reserve";
		$c[$o]['type'] 		= "price";
		$c[$o]['class'] 	= "form-control";
		$c[$o]['help'] 		= $CORE->_e(array('auction','11'));
		$c[$o]['required'] 	= true;
		$c[$o]['defaultvalue'] 	= "0";
		$o++;
		}

		if(isset($GLOBALS['CORE_THEME']['auction_shipping']) && $GLOBALS['CORE_THEME']['auction_shipping'] == '1'){

		if($canEditPrice){
		$c[$o]['title'] 	= $CORE->_e(array('auction','67'));
		$c[$o]['name'] 		= "price_shipping";
		$c[$o]['type'] 		= "price";
		$c[$o]['class'] 	= "form-control";
		$c[$o]['help'] 		= $CORE->_e(array('auction','68'));
		$c[$o]['required'] 	= true;
		$c[$o]['defaultvalue'] 	= "0";
		$o++;
		}

		}

		if($GLOBALS['CORE_THEME']['auction_theme_usl'] == '1' && !isset($_GET['eid'])){
		$c[$o]['title'] 	= $CORE->_e(array('auction','12'));
		$c[$o]['name'] 		= "listing_expiry_date";
		$c[$o]['type'] 		= "select";
		$c[$o]['class'] 	= "form-control";
		$c[$o]['listvalues'] 	= array("1" => "1 ".$CORE->_e(array('date','1')), "3" => "3 ".$CORE->_e(array('date','1')),"5" => "5 ".$CORE->_e(array('date','1')),"7" => "7 ".$CORE->_e(array('date','2')), "14" => "14 ".$CORE->_e(array('date','2')), "21" => "21 ".$CORE->_e(array('date','2')), "30" => "30 ".$CORE->_e(array('date','2')));
		$c[$o]['help'] 		= $CORE->_e(array('auction','13'));
		$c[$o]['required'] 	= true;
		$o++;
		}


		$c[$o]['title'] 	= $CORE->_e(array('auction','91'));
		$c[$o]['name'] 		= "condition";
		$c[$o]['type'] 		= "select";
		$c[$o]['class'] 	= "form-control";
		$c[$o]['listvalues'] 	= array("1" => $CORE->_e(array('auction','92')), "2" => $CORE->_e(array('auction','93')));

		return $c;
	}


	function _hook_custom_query($c){

		$args = array(
			'post_type' => THEME_TAXONOMY."_type",
			'meta_query' => array(
				array(
					'key' => 'listing_expiry_date',
					'value' => '',
					'compare' => '!='
				),
			),
		);

	// ADD-ON USER INPUTTED STRING
	if(!is_array($c)){

		$bits = explode("&",$c);
		if(is_array($bits)){
			foreach($bits as $stringbit){

				$v = explode("=",$stringbit);
				switch($v[0]){
					case "order": { $args = array_merge( $args, array("order"=>$v[1]) ); } break;
					case "orderby": { $args = array_merge( $args, array("orderby"=>$v[1]) ); } break;
				}
			}
		}
	}

	// MERGE VALUES
	$c = array_merge($c,$args);

	return $c;
	}




	function _saveaccount(){ global $CORE, $userdata, $wpdb;

		// SAVE USER CHANGES
		if($_POST['action'] == "savepaypal" && isset($_POST['user_paypalemail'])){
		update_user_meta($userdata->ID, 'user_paypalemail', $_POST['user_paypalemail']);
		$GLOBALS['error_message'] 	= $CORE->_e(array('auction','15'));
		}
	}

	function _paymentform(){ global $CORE, $post, $wpdb, $userdata;

       if(isset($GLOBALS['CORE_THEME']['auction_paypal']) && $GLOBALS['CORE_THEME']['auction_paypal'] == '1'){   ?>
    	<!-- START PAYPAL BLOCK   -->
		<div class="panel panel-default" id="MyPayments" style="display:none;">

        <div class="panel-heading"><?php echo $CORE->_e(array('auction','14')); ?></div>

        <div class="panel-body">

        <p><?php echo $CORE->_e(array('auction','16')); ?></p>

        <hr />

       <form method="post">
      <input type="hidden" name="action" value="savepaypal" />
        <b><?php echo $CORE->_e(array('auction','17')); ?></b>

        <input type="text" class="form-control" name="user_paypalemail" value="<?php echo get_user_meta($userdata->ID, 'user_paypalemail',true); ?>">


        <div class="clearfix"></div>
        <hr />

        <div class="text-center">
		<button class="btn btn-primary btn-lg" type="submit"><?php echo $CORE->_e(array('button','6')); ?></button>
		</div>
		 </form>

        </div>

        </div>
        <?php }
    }

	function _memberblock(){ global $CORE, $post, $wpdb, $userdata; ?>





       <?php

	   // GET USER BIDDING DATA
		$user_bidding_data = get_user_meta($userdata->data->ID,'user_bidding_data',true);

		if(!is_array($user_bidding_data)){ $user_bidding_data = array(); }

		if(!empty($user_bidding_data)){
	   ?>

        <table class="table table-bordered">
              <thead>
                <tr>

                  <th><?php echo $CORE->_e(array('auction','18')); ?> </th>
                  <th style="width:100px; text-align:center;"><?php echo $CORE->_e(array('auction','20')); ?></th>
                  <th style="text-align:center;"><?php echo $CORE->_e(array('auction','21')); ?></th>
                </tr>
              </thead>
              <tbody>
                <?php



				$shownalready = array();

				$user_bidding_data = array_reverse($user_bidding_data, true);
				foreach($user_bidding_data as $data){  if(!isset($data['postid']) || $data['title'] == "" ){ continue; }

				// CHEK ALREADY SHOWN

				if(!in_array($data['postid'],$shownalready)){ $shownalready[$data['postid']] = $data['postid']; }else { continue; }

				// CHECK IF LISTING EXISTS OTHERWISE REMOVE IT
				if ( get_post_status ( $data['postid'] ) != 'publish' && get_post_type( $data['postid'] ) != THEME_TAXONOMY."_type" ) { continue; }

				// LAST ID TO PREVENT DUPLICATES
				if(isset($last_id) && $last_id == $data['postid']){ continue; }
				$last_id = $data['postid'];

				// GET LINK
				$link = get_permalink($data['postid']);
				if($link == ""){ continue; }

				// GET THE LISTING DATA
				$expiry_date = get_post_meta($data['postid'],'listing_expiry_date',true);
				$current_price = get_post_meta($data['postid'],'price_current',true);
				$current_bidding_data = get_post_meta($data['postid'],'current_bid_data',true);
				if(!is_array($current_bidding_data)){ $current_bidding_data = array(); }
				krsort($current_bidding_data);
				$checkme = current($current_bidding_data);


				if($expiry_date == ""){ $status = $CORE->_e(array('auction','76')); }else{ $status = $CORE->_e(array('auction','77')); }
				?>
                <tr>
                  <td>

                 <b><a href="<?php echo $link; ?>"><?php echo $data['title']; ?></a></b><br />
                 <span style="font-size:11px;"><?php echo hook_date($data['date']); ?> | <?php echo $CORE->_e(array('auction','22')); ?>: <?php echo $status; ?></span>

                 <?php

				 if($expiry_date == ""){
				 ?>
                 - <a href="<?php echo $GLOBALS['CORE_THEME']['links']['myaccount']; ?>/?delhistory=<?php echo $data['postid']; ?>" class="label label-success">
                 <?php echo $CORE->_e(array('checkout','55')); ?>
                 </a>
                 <?php } ?>


				<?php if($expiry_date != ""){ ?>

                <div id='countdown_id<?php echo $data['postid']; ?>'></div>
                <script>
                jQuery(document).ready(function() {

				var dateStr ='<?php echo $expiry_date; ?>';
				var a=dateStr.split(' ');
				var d=a[0].split('-');
				var t=a[1].split(':');
				var date1 = new Date(d[0],(d[1]-1),d[2],t[0],t[1],t[2]);

                jQuery('#countdown_id<?php echo $data['postid']; ?>').countdown({until: date1, timezone: <?php echo get_option('gmt_offset'); ?>,layout: '<b>{sn} {sl}, {mn} {ml}, {hn} {hl}, and {dn} {dl}</b> left!'});
                });
                </script>
                <?php } ?>



                  </td>
                  <td style="text-align:center;"><?php echo hook_price($data['max_amount']); ?> <br /><small><?php echo $CORE->_e(array('auction','78')); ?> <?php echo hook_price($current_price); ?></small></td>
                  <td style="text-align:center;">

                  <?php if($checkme['userid'] == $userdata->ID && get_post_meta($data['postid'],'auction_price_paid',true) != "" && $CORE->FEEDBACKEXISTS($data['postid'], $userdata->ID) === false){ ?>

                   <a href="<?php echo $GLOBALS['CORE_THEME']['links']['myaccount']."/?fdid=".$data['postid']; ?>" class="btn btn-success"><?php echo $CORE->_e(array('auction','23')); ?></a>

                  <?php }else{ ?>

                  <a href="<?php echo $link; ?>" class="btn btn-primary"><?php echo $CORE->_e(array('auction','24')); ?></a>

                  <?php } ?>

                  </td>
                </tr>


                <?php

				if(isset($has_feedback) && $has_feedback == 1){
				?>
                <tr><td colspan="3">

				<blockquote><?php echo wpautop(get_post_meta($data['postid'],'feedback_message',true)); ?></blockquote>
                <small><?php echo $CORE->_e(array('auction','25')); ?> <?php echo hook_date(get_post_meta($data['postid'],'feedback_date',true)); ?></small><br />
                </td></tr>
                <?php } ?>


                <?php } ?>
              </tbody>
            </table>

            <?php } // not empty ?>


	<?php }


	function _relistactions(){global $userdata, $post, $CORE;

		if( isset($GLOBALS['CORE_THEME']['auction_relist']) && $GLOBALS['CORE_THEME']['auction_relist'] == '1' && $post->post_author == $userdata->ID  && isset($_GET['relistme']) == 1 ){  //&&

		// CHECK THE LISTING HAS EXPIRD
		$expiry_date = get_post_meta($post->ID,'listing_expiry_date',true);

		if(   $expiry_date == "" || strtotime($expiry_date) < strtotime(current_time( 'mysql' )) ){


				$days_renew = get_option($post->ID, 'listing_expiry_days', true);

				// GET DAYS FROM THE PACKAGE
				if(!is_numeric($days_renew)){

						$packageID =  get_post_meta($post->ID,'packageID',true);
						$packagefields = get_option("packagefields");
						if(isset($packagefields[$packageID]['expires']) && is_numeric($packagefields[$packageID]['expires']) ){
							$days_renew = $packagefields[$packageID]['expires'];
						}
				}

				if(!is_numeric($days_renew)){ $days_renew = 30; }

				// STORE IF FOR PROCESSING
				$SAVEDID = $post->ID;

				// IF NOONE BOUGHT THE ITEM WE CAN RENEW IT OTHERWISE CREATE A NEW LISTING
				if(get_post_meta($post->ID, 'bidwinnerstring', true) != ""){

							// Gather post data.
							$my_post = array(
										'post_title'    => $post->post_title,
										'post_content'  => $post->post_content,
										'post_status'   => 'publish',
										'post_type'     => 'listing_type',
										'post_author'   => $post->post_author,
										'post_category' => array( 8,39 )
							);

							// Insert the post into the database.
							$NEWID = wp_insert_post( $my_post );

							// GET CUSTOM FIELDS
							$custom_fields = get_post_custom($post->ID);
							foreach ( $custom_fields as $key => $value ) {
									update_post_meta($NEWID, $key, $value[0]);
							}

							// UPDATE THE OLD POST
							$my_post1 = array(
								'ID'           => $post->ID,
								'post_status'   => 'trash'
							);
							wp_update_post( $my_post1 );

							$SAVEDID = $NEWID;


					}

					update_post_meta($SAVEDID, 'listing_expiry_date', date("Y-m-d H:i:s", strtotime( current_time( 'mysql' ) . " +".$days_renew." days")));
					update_post_meta($SAVEDID, 'current_bid_data', '');
					update_post_meta($SAVEDID,	'bidstring', '');
					update_post_meta($SAVEDID,	'relisted', current_time( 'mysql' ) );

					// UPDATE LISTING DATE
					$my_post = array();
					$my_post['ID'] 					= $SAVEDID;
					$my_post['post_date']			= current_time( 'mysql' );
					wp_update_post( $my_post  );

					// REDIRECT TO NEW PAGE
					header('location: '.get_permalink($SAVEDID));
					exit();


		}// end renew
		}

	}
	function _init(){ global $userdata, $post;


 		if( current_user_can( 'edit_user', $userdata->ID ) ) {

			if(isset($_GET['resetaction']) && is_numeric($_GET['resetaction']) ){

				$days_renew = get_option($_GET['resetaction'], 'listing_expiry_days', true);
				if(!is_numeric($days_renew)){ $days_renew = 30; }

				update_post_meta($_GET['resetaction'], 'listing_expiry_date', date("Y-m-d H:i:s", strtotime( current_time( 'mysql' ) . " +".$days_renew." days")));
				update_post_meta($_GET['resetaction'], 'current_bid_data', '');
				update_post_meta($_GET['resetaction'],	'bidstring', '');
				update_post_meta($_GET['resetaction'],	'listing_price_due', '');

				// UPDATE LISTING DATE
				$my_post = array();
				$my_post['ID'] 					= $_GET['resetaction'];
				$my_post['post_date']			= current_time( 'mysql' );
				wp_update_post( $my_post  );
			}

			if(isset($_GET['resetactionall']) && defined('WLT_DEMOMODE')  ){

				// The Query
				$args = array(
					'post_type' => 'listing_type',

				);
				$the_query = new WP_Query($args );

				// The Loop
				if ( $the_query->have_posts() ) {

					foreach($the_query->posts as $post){

							$days_renew = get_option($post->ID, 'listing_expiry_days', true);
							if(!is_numeric($days_renew)){ $days_renew = 30; }

							update_post_meta($post->ID, 'listing_expiry_date', date("Y-m-d H:i:s", strtotime( current_time( 'mysql' ) . " +".$days_renew." days")));
							update_post_meta($post->ID, 'current_bid_data', '');
							update_post_meta($post->ID,	'bidstring', '');
							update_post_meta($post->ID,	'bidwinnerstring', '');

							// UPDATE LISTING DATE
							$my_post = array();
							$my_post['ID'] 					= $post->ID;
							$my_post['post_date']			= current_time( 'mysql' );
							wp_update_post( $my_post  );

					}

				}
				/* Restore original Post Data */
				wp_reset_postdata();


			}

		}
	}



	function _wp_head(){ global $CORE, $post, $wpdb, $userdata;

	if(isset($_GET['delhistory']) && is_numeric($_GET['delhistory']) && $userdata->ID ){

		$newdata = array();
		$user_bidding_data = get_user_meta($userdata->ID,'user_bidding_data',true);

		$user_bidding_data = array_reverse($user_bidding_data, true);
		foreach($user_bidding_data as $key => $data){

				if(!isset($data['postid']) || $data['title'] == "" ){ continue; }

				if($_GET['delhistory'] == $data['postid']){

				}else{

				$newdata[] = $data;

				}
		}

		 update_user_meta($userdata->ID, 'user_bidding_data', $newdata);

	}

		if(isset($_POST['auction_action'])){

			// VERIFY THAT THE AUCTION HASNT FINISHED TO STOP EXTRA BIDS
			$expiry_date = get_post_meta($post->ID,'listing_expiry_date',true);
			if($expiry_date == "" || ( strtotime($expiry_date) < strtotime(current_time( 'mysql' )))){

				// LEAVE MSG
				$GLOBALS['error_message'] = $CORE->_e(array('auction','3'));

				// ADD LOG ENTRY
				$CORE->ADDLOG("{overtime bid stopped} <a href='(ulink)'>".$userdata->display_name."</a> on listing <a href='(plink)'>".$post->post_title.'</a>', $userdata->ID, $post->ID,'label-warning');

				return;
			}

			switch($_POST['auction_action']){

				// BUY NOW OPTION
				case "buynow": {

				// SET THE CURRENT PRICE TO THE BUYNOW PRICE
				$bin_price = get_post_meta($post->ID,'price_bin',true);
				update_post_meta($post->ID,'price_current', $bin_price);

				//3. ADD ON THE NEW BID
				$current_bidding_data[$bin_price] = array( 'max_amount' => $bin_price, 'userid' => $userdata->data->ID, 'username' => $userdata->data->user_nicename, 'date' => current_time( 'mysql' ), 'bid_type' => "buynow"  );

				//4. UPDATE USER META TO INDICATE THEY BID ON THIS ITEM
				$user_bidding_data = get_user_meta($userdata->data->ID,'user_bidding_data',true);
				if(!is_array($user_bidding_data)){ $user_bidding_data = array(); }
				$user_bidding_data[] = array('postid' => $post->ID, 'max_amount' => $bin_price, 'date' => current_time( 'mysql' ), 'bid_type' => 'bin', 'title' => $post->post_title );
				update_user_meta($userdata->data->ID,'user_bidding_data',$user_bidding_data);

				//4. MERGE THE TWO AND SAVE
				update_post_meta($post->ID,'current_bid_data', $current_bidding_data);

				//5. SEND EMAIL TO BIDDERS
				$_POST['winningbid'] = hook_price($bin_price);
				$_POST['title'] 	= $post->post_title;
				$_POST['link'] 		= get_permalink($post->ID);

				// SEND EMAIL
				$_POST['username'] 	= $userdata->display_name;
				$CORE->SENDEMAIL($userdata->ID,'auction_ended_winner');


				// LOOP BIDDERS
				krsort($current_bidding_data); // order data
				if(is_array($current_bidding_data) && !empty($current_bidding_data)){
					$sent_to_array = array();

					// SEND EMAIL
					$_POST['username'] 	= $userdata->display_name;
					$CORE->SENDEMAIL($userdata->ID,'auction_ended_winner');

					$i = 1;
					foreach($current_bidding_data as $maxbid=>$data){

						if($i == 1 && $data['max_amount'] > 0 ){

						}else{
							if(!in_array($data['userid'],$sent_to_array)){
								$_POST['username'] 	= $data['username'];
								$CORE->SENDEMAIL($data['userid'],'auction_ended');
								array_push($sent_to_array,$data['userid']);
							} // end if
						}// end else
						$i++;
					}
				}

				//6. SEND EMAIL TO AUCTION SELLER
				$author_data = get_userdata( $post->post_author );
				$_POST['username'] 	= $author_data->display_name;
				$CORE->SENDEMAIL($post->post_author,'auction_ended_owner');

				// 7. IF THE ITEM SOLD, ADD A COMISSION AMOUNT TO THE USERS ACCOUNT SO THEY HAVE TO PAY THE ADMIN
				$comissionadded = 0;
				$price_current = get_post_meta($post->ID,'price_current',true);
				if($price_current > 0 && isset($GLOBALS['CORE_THEME']['auction_house_percentage']) && strlen($GLOBALS['CORE_THEME']['auction_house_percentage']) > 0){

					// WORK OUT AMOUNT OWED BY THE SELLER
					$AMOUNTOWED = ($GLOBALS['CORE_THEME']['auction_house_percentage']/100)*$price_current;
					$AMOUNTOWED = -1 * abs($AMOUNTOWED);

					// DEDUCT AMOUNT FROM MEMBERS AREA
					$user_balance = get_user_meta($post->post_author,'wlt_usercredit',true);
					if($user_balance == ""){ $user_balance = 0; }
					$user_balance = $user_balance+$AMOUNTOWED;
					update_user_meta($post->post_author,'wlt_usercredit',$user_balance);

					$comissionadded = $AMOUNTOWED;

				}

				// CHECK FOR QTY ADDED IN 8.2
				$qty = get_post_meta($post->ID,'qty',true);
				if(is_numeric($qty) && $qty > 0){
					$qty_sold = get_post_meta($post->ID,'qty_sold',true);
					if(!is_numeric($qty_sold)){ $qty_sold = 1; }else{ $qty_sold = $qty_sold + 1; }
					update_post_meta($post->ID,'qty_sold',$qty_sold);

					// IF SOLD MORE THAN QTY EXPIRE LISTING
					if($qty_sold > $qty){
						update_post_meta($post->ID,'listing_expiry_date','');
					}

					// SET FLAG SO SYSTEM KNOWS WHO THE CURRENT WINNING BIGGER IS
					update_post_meta($post->ID,'bidstring', '');
					update_post_meta($post->ID,'bidwinnerstring', get_post_meta($post->ID,'bidwinnerstring', true)."-".$userdata->ID."-");


				}else{

				// REMOVE EXPIRY FOR AUTO EXPIRY SO THE CORE SYSTEM DOESNT PICK IT UP
				// AND THE LISTING IS THEN FINISHED
				update_post_meta($post->ID,'listing_expiry_date','');
				update_post_meta($post->ID,'bidstring', '');
				update_post_meta($post->ID,'bidwinnerstring', get_post_meta($post->ID,'bidwinnerstring', true)."-".$userdata->ID."-");

				}

				// LEAVE MSG
				$GLOBALS['error_message'] = $CORE->_e(array('auction','28'))."<style>.timeleftbox { display:none; }</style>";

				// ADD LOG ENTRY
				$CORE->ADDLOG("<a href='(plink)'>".$post->post_title.'</a> auction finished. (buy now / comission '.$comissionadded.')', $post->ID,'','label-inverse');

				// SEND EMAIL TO THE SELLER
				$CORE->SENDEMAIL($post->post_author,'auction_itemsold');

				// RESET COUNTERS
				update_option('wlt_system_counts','');

				} break;

				// BIDDING AND MAKE OFFER
				case "newbid": {
				  $is_new_bid = false;

					// if(!is_numeric($bidamount)){

						// LEAVE MSG
						// $GLOBALS['error_message'] = str_replace("%a", $bidamount ,$CORE->_e(array('auction','29')));

					// }else{

						//1. GET ANY CURRENT BIDDING DATA
						$current_bidding_data = get_post_meta($post->ID,'current_bid_data',true);
						if(!is_array($current_bidding_data)){ $current_bidding_data = array(); }

						//2. ORDER IT BY KEY (WHICH HOLDS THE BID AMOUNT)
						krsort($current_bidding_data);

						// 3. SWITCH THE BID TYPE TO PERFORM ACTIONS
						switch($_POST['bidtype']){

							// BIDDING SYSTEM
							case "bid": {

								// GET THE CURRENT PRICE
								$current_price = get_post_meta($post->ID,'price_current',true);
								if($current_price == ""){ $current_price = 0; }

								// anysoft ------------------
								$standard_bid_increase = STANDARD_BID_INCREASE;
								$bidamount = $current_price + $standard_bid_increase;

								// end anysoft ---------------


								// CHECK IF THE BID AMOUNT IS GREATER THAN THE EXISTINT CURRENT PRICE
								if( $bidamount > $current_price ){


										// LETS CHECK IF WE HAVE A BIGGER AMOUNT THAN THE BIDDERS TOTAL
										$checkme = current($current_bidding_data);

										// OLD BIDDER IS STILL WINNER
										if(is_numeric($current_price) && $current_price > 0 && isset($checkme['max_amount']) && ( $checkme['max_amount'] >=  $bidamount) ){

											$is_new_bid = false;


											if($bidamount == $checkme['max_amount']  ){

											$current_price = $checkme['max_amount'];

											}elseif( $bidamount + 0.1 >= $checkme['max_amount'] ){

											$current_price = $checkme['max_amount'];

											// BID +1
											}elseif( $bidamount + 1 <= $checkme['max_amount'] ){

													$current_price = $bidamount;

											}else{

											$current_price = $bidamount+0.1;
											}

											$GLOBALS['error_message'] = $CORE->_e(array('auction','30'));

										// NEW BIDDER IS WINNER
										}else{

											$is_new_bid = true;

											 //echo "new bid";

											// EMAIL THE OLD BIDDER AND LET THEM KNOW THEY HAVE BEEN OUTBID
											if(isset($checkme['userid']) && $checkme['userid'] != $userdata->data->ID){

												$_POST['username'] 	= $checkme['username'];
												$_POST['title'] 	= $post->post_title;
												$_POST['link'] 		= get_permalink($post->ID);
												$CORE->SENDEMAIL($checkme['userid'],'auction_outbid');

											}

											// NOW SET NEW PRICE
											if($current_price == "" || $current_price == "0"){

												$current_price = 1;

											 // SAME USER UPDATING THEIR MAX BID
											}elseif(isset($checkme['userid']) && $checkme['userid'] == $userdata->data->ID){

												$current_price = get_post_meta($post->ID,'price_current',true);

											// BID IT HIGHER THAN OLD MAX BID
											}elseif(isset($checkme['max_amount']) && $bidamount > $checkme['max_amount'] ){

												$current_price = $checkme['max_amount'];

												$newprice1 = 	$checkme['max_amount']+1;
												if($newprice1 <= $bidamount){
													$current_price = $newprice1;
												}

											// BID +1
											}elseif( ($current_price + 1) <= $bidamount ){

												$current_price = $current_price + 1;

											// NEW BID + 0.1
											}else{

												$current_price = $current_price + 0.1;

											}


											//echo "now:".$current_price." bid:".$bidamount." max:".$checkme['max_amount'];

											$GLOBALS['error_message'] = $CORE->_e(array('auction','31'));

										}
								}else{

								// LEAVE MSG
								$GLOBALS['error_message'] = str_replace("%a", $bidamount ,$CORE->_e(array('auction','29')));

								}

								// UPDATE THE LISTING WITH THE NEW CURRENT PRICE
					 			update_post_meta($post->ID,'price_current', $current_price);


							} break;

						}

						//3. ADD ON THE NEW BID
						if($is_new_bid){

						$current_bidding_data[$bidamount] = array( 'max_amount' =>$bidamount, 'userid' => $userdata->data->ID, 'username' => $userdata->data->user_nicename, 'date' => current_time( 'mysql' ), 'bid_type' => $_POST['bidtype']  );

						//4. MERGE THE TWO AND SAVE
						update_post_meta($post->ID,'current_bid_data', $current_bidding_data);

						// SET FLAG SO SYSTEM KNOWS WHO THE CURRENT WINNING BIGGER IS
						update_post_meta($post->ID,'bidstring', get_post_meta($post->ID,'bidstring', true)."-".$userdata->ID."-");


						}

						//5. ADD LOG ENTRY
						$CORE->ADDLOG("<a href='(ulink)'>".$userdata->user_nicename.'</a> bid on the listing <a href="(plink)"><b>['.$post->post_title.']</b></a>.', $userdata->ID, $post->ID ,'label-info');

						// RESET COUNTERS
						update_option('wlt_system_counts','');

						//6. UPDATE USER META TO INDICATE THEY BID ON THIS ITEM
						$user_bidding_data = get_user_meta($userdata->data->ID,'user_bidding_data',true);
						if(!is_array($user_bidding_data)){ $user_bidding_data = array(); }
						$user_bidding_data[] = array('postid' => $post->ID, 'max_amount' =>$bidamount, 'date' => current_time( 'mysql' ), 'bid_type' => $_POST['bidtype'], 'title' => $post->post_title);
						update_user_meta($userdata->data->ID,'user_bidding_data',$user_bidding_data);

					// }
				} break;

			}// end switch
		}// end if

	}

		// HOOK INTO THE EDIT LISTING PAGE
	function _fields($c){ global $CORE;

		$list1 = array (

		"tab_action" => array("tab" => true, "title" => "Auction Settings" ),

			"auction_type" 		=> array("label" => "Auction Type", "desc" => "", "values" => array("1" => "Normal Auction", "2" => "Classifieds (Buy Now Only)" )),
			"price_reserve" 	=> array("label" => "Reserve Price", "desc" => "", "price" => true ),
			"price_current" 	=> array("label" => "Current Price", "desc" => "This is the current auction price. Only visible in the admin." , "price" => true),
			"price_shipping" 	=> array("label" => "Shipping Price", "desc" => "This is the shipping price for the item. Added to the total after the auction has been won." , "price" => true),
			"price_bin" 		=> array("label" => "Buy Now Price", "desc" => "Leave blank if you do not wish to use this feature.", "price" => true ),
			"condition" 		=> array("label" => "Condition", "values" => array("1" => "New", "2" => "Used") ),




		);

	return array_merge($c,$list1);
	}





 	function _newemails($c){ global $CORE, $post, $wpdb, $userdata;

		$new_emails = array("n5" => array('break' => 'Auction Emails'),
		"auction_outbid" => array('name' => 'User Outbidded',  'shortcodes' => 'username = (user_login) \n item title = (title) \n link = (link)', 'label'=>'label-warning','desc' => 'This email is sent to bidders who are outbid on an auction.'),
		"auction_ended" => array('name' => 'Auction Finished (All Bidders)',  'shortcodes' => 'username = (user_login) \n item title = (title) \n link = (link)\n winning amount = (winningbid)', 'label'=>'label-warning','desc' => 'This email is sent to ALL bidders who have bid on an auction which has just finished.'),

		"auction_ended_winner" => array('name' => 'Auction Finished (Winner)',  'shortcodes' => 'username = (user_login) \n item title = (title) \n link = (link)\n winning amount = (winningbid)', 'label'=>'label-warning','desc' => 'This email is sent to winner of the auction.'),

		"auction_ended_owner" => array('name' => 'Auction Finished (Seller)',  'shortcodes' => 'username = (username) \n item title = (title) \n link = (link)\n winning amount = (winningbid)', 'label'=>'label-warning','desc' => 'This email is sent to auction owners (sellers) to let them know their listing has finished.'),



		 "auction_itemsold" => array('name' => 'Auction Finished + Winner (Seller)',  'shortcodes' => 'username = (username) \n item title = (title) \n link = (link)\n winning amount = (winningbid)', 'label'=>'label-warning','desc' => 'This email is sent to auction owner (sellers) when their item has been sold.'),


		);

		return array_merge($c, $new_emails);

	}







































/* =============================================================================
BIDDING FORM SHORTCODE
========================================================================== */
function shortcode_biddingform(){ global $CORE, $post, $userdata, $wpdb;


// GET BASIC BIDDING DATA FOR DISPLAY
$reserve_price = get_post_meta($post->ID,'price_reserve',true);
$price_current = get_post_meta($post->ID,'price_current',true);
$price_shipping = get_post_meta($post->ID,'price_shipping',true);
if($price_shipping == "" || !is_numeric($price_shipping)){$price_shipping = 0; }
$price_bin = get_post_meta($post->ID,'price_bin',true);
$auction_type = get_post_meta($post->ID,'auction_type',true);
$condition = get_post_meta($post->ID,'condition',true);


// GET HITS
$hits = get_post_meta($post->ID,'hits',true);
if($hits == ""){ $hits = 0; }

	// GET CURRENT BIDING DATA
	$current_bidding_data = get_post_meta($post->ID,'current_bid_data',true);
	if(!is_array($current_bidding_data)){ $current_bidding_data = array(); }

		//2. ORDER IT BY KEY (WHICH HOLDS THE BID AMOUNT)
		krsort($current_bidding_data);

		$bid_count = count($current_bidding_data);

		//3. GET THE CURRENT BIDDING DATA
		$checkme = current($current_bidding_data);
		if(isset($checkme['username']) ){

			// SHOW TEXT FOR CURRENT HIGHEST BIDDER
			if($userdata->data->ID == $checkme['userid']){

				$current_bidder_amount 	= "<pre> <i class='fa fa-check-square-o'></i> ".$CORE->_e(array('auction','33'))." ".hook_price($checkme['max_amount'])."";
				// CHECK IF ITS LOWER THAN THE RESERVER PRICE
				if($price_current != "" && $checkme['max_amount'] >= $reserve_price && is_numeric($reserve_price) && $reserve_price != "0"){

				$current_bidder_amount .= " ".$CORE->_e(array('auction','34'))." ".hook_price($reserve_price).". <span>".$CORE->_e(array('auction','35'))."</span>";
				}elseif($reserve_price > $price_current){
				$current_bidder_amount .= " ".$CORE->_e(array('auction','36'))." ".hook_price($reserve_price).". <span>".$CORE->_e(array('auction','37'))."</span>";
				}

				$current_bidder_amount .= "</pre>";

			}

	}else{
		// DEFAULTS
		$current_bidder_amount 	= "";

		$bid_count = 0 ;
	}

	//2. GET THE CURRENT PRICE
	$current_price = get_post_meta($post->ID,'price_current',true);
	if($current_price == ""){ $current_price = 0; }
		 //<-- add one onto the existing price so the bidder doesnt bid nothing

	//3. GET EXPIRY DATE
	$expiry_date = get_post_meta($post->ID,'listing_expiry_date',true);

	//4. CHECK FOR BIN QTY
	$bin_qty = get_post_meta($post->ID,'qty',true);

	if(!empty($current_bidding_data)){
		foreach($current_bidding_data as $gg){
			if($gg['userid'] == $userdata->ID && $gg['bid_type'] == "buynow"){

				$SHOWPAYMENTFORM = true;
				// CHECK IFHAS PAID
				if(strtotime(get_post_meta($post->ID,'auction_price_paid_date',true)) > strtotime($gg['date'])){
				$SHOWPAYMENTFORM = false;
				}
			}
		}
	}


	// CHECK IF THIS IS AN AFFILIATE PRODUCT OR NOT
	$aff_p = get_post_meta($post->ID,'buy_link',true);
	if(strlen($aff_p) > 1){
		$link_l = get_home_url()."/out/".$post->ID."/buy_link/";
		$bid_btn = "<a href='".$link_l."' class='btn btn-primary right'>".$CORE->_e(array('auction','53'))."</a>";
	}elseif($userdata->ID == $post->post_author){ // STOP BIDDING ON OWN ITEMS
		$bid_btn = "<button class='btn btn-primary btn-lg' href='javascript:void(0);' onclick=\"alert('".$CORE->_e(array('auction','54','flag_noedit'))."');\">".$CORE->_e(array('auction','70'))."</button>";
	}elseif(!$userdata->ID){
		$bid_btn = "<button class='btn btn-primary btn-lg' href='javascript:void(0);' onclick=\"alert('".$CORE->_e(array('auction','56','flag_noedit'))."');\">".$CORE->_e(array('auction','70'))."</button>";
	}else{
		// $bid_btn = "<button class='btn btn-primary btn-lg' href='javascript:void(0);' onclick=\"jQuery('.biddingbox').show();\">".$CORE->_e(array('auction','70'))."</button>";

		// anysoft

		$bid_button_text = $CORE->_e(array('auction','66'));
		$bid_btn = <<<EOT
		        <form method="post" action="">
							<input type="hidden" name="auction_action" value="newbid" />
							<input type="hidden" name="bidtype" value="bid" />
			 				<input type='hidden' name='hidden_cp' id='hidden_cp' value='$current_price' />

							<button class="btn btn-lg btn-primary" type="submit">$bid_button_text</button>
		        </form>
EOT;
	}

	// GET BUY NOW BUTTON
	$buynow_btn = "";
	if(!$userdata->ID){
		$buynow_btn = "<button class='btn btn-primary btn-lg' href='javascript:void(0);' onclick=\"alert('".$CORE->_e(array('auction','56','flag_noedit'))."');\">".$CORE->_e(array('auction','55'))."</button>";
	}elseif($userdata->ID == $post->post_author){
		$buynow_btn = "<button class='btn btn-primary btn-lg' href='javascript:void(0);' onclick=\"alert('".$CORE->_e(array('auction','54','flag_noedit'))."');\">".$CORE->_e(array('auction','55'))."</button>";
	}else{
		$buynow_btn = "<button class='btn btn-primary btn-lg' href='javascript:void(0);' onclick=\"jQuery('.buynowbox').show();\">".$CORE->_e(array('auction','55'))."</button>";
	}

	// GET SELLER DETAILS
	$user_info = get_userdata($post->post_author);

	// START OUTPUT
	ob_start();

	?>

<div id="auctionbidform">
<ul class="list-group">

    <li class="list-group-item item1">

    <span class="pull-right"> <?php echo do_shortcode('[D_SOCIAL size=16]'); ?></span>

    ID: #<?php echo $post->ID; ?>
    </li>

    <li class="list-group-item item1">

    <?php if($bid_count > 0){ ?> <i class="fa fa-search bidhistoryicon"></i>

    <!-----------------------  POPUP ------------------------->
    		<div id="bidhistory" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
		<div class="modal-dialog"><div class="modal-content">
		  <div class="modal-header">
			<button type="button" class="close" data-dismiss="modal" aria-hidden="true">x</button>
			<h4 class="modal-title"><?php echo $CORE->_e(array('auction','18')); ?></h4>
		  </div>
		  <div class="modal-body">

          <script>

		jQuery('.bidhistoryicon').hover(function () {
			jQuery('#bidhistory').modal({
				show: true
			});
		});

		</script>
    <?php echo do_shortcode('[BIDDINGHISTORY]'); ?>


    		  </div>

		</div>
		</div></div>
		<!----------------------- end POPUP ------------------------->

    <?php } ?>

    <?php echo $CORE->_e(array('auction','97')); ?>: <strong><?php echo $bid_count; ?>

    </strong>

       <?php if(isset($checkme['username']) && $checkme['username'] != "" && is_numeric($checkme['userid'])){ ?>

        <span class="pull-right">

		<small><?php echo $CORE->_e(array('auction','32')); ?></small>:  <i class="fa fa-user"></i> <a href="<?php echo get_author_posts_url( $checkme['userid']); ?>"><?php echo $checkme['username']; ?></a>

		</span>

       <?php } ?>

    </li>
    <li class="list-group-item item3">

    <span class="pull-right"> <i class="fa fa-line-chart"></i>  <?php echo $CORE->_e(array('single','19')); ?>: <strong><?php echo number_format($hits); ?></strong> </span>

    <?php echo $CORE->_e(array('auction','91')); ?>: <strong><?php if($condition == 1){ echo $CORE->_e(array('auction','92')); }else{ echo $CORE->_e(array('auction','93')); } ?></strong>
    </li>

    <li class="list-group-item item4">

    <div class="row">

    <div class="col-md-7">

    <?php echo $CORE->_e(array('auction','71')); ?>: <i class="fa fa-user"></i> <strong><a style="text-decoration:underline;" href="<?php echo get_author_posts_url( $post->post_author ); ?>"><?php echo $user_info->data->display_name; ?></a> </strong>

    </div>

    <?php if(isset($GLOBALS['CORE_THEME']['feedback_enable']) && $GLOBALS['CORE_THEME']['feedback_enable'] == '1'){ ?>
    <div class="col-md-5">

    <?php echo _user_trustbar($post->post_author, 'inone'); ?>

    </div>

    <?php } ?>

    </div>


    </li>

    <li class="list-group-item item4">
    <?php echo $CORE->_e(array('author','9')); ?>: <strong><?php echo hook_date($post->post_date); ?></strong>
    </li>




	<?php


	// CHECK IF THE LISTING HAS ENDED AND THE ITE HAS BEEN WON
	if(  ( $expiry_date == "" || strtotime($expiry_date) < strtotime(current_time( 'mysql' )) ) || isset($SHOWPAYMENTFORM) ){  if($userdata->ID){ ?>

      <li class="list-group-item paybits text-center">

		<h4 class="text-center"><?php echo $CORE->_e(array('auction','39')); ?></h4>

		<div>

			<?php if($bid_count > 0 && is_numeric($reserve_price) && $reserve_price != "0" && $price_current < $reserve_price){ ?>

            <div class="alert alert-danger"><?php echo $CORE->_e(array('auction','38')); ?></div>

            <?php }	?>


		<?php echo $this->actions_auctionended(); ?>


        </div>

        </li>


        <?php  } }else{ // START BIDDING AREA ?>


        <?php if($auction_type != 2){ ?>

      <li class="list-group-item pricebits">

      <div class="row">

          <div class="col-md-6">

            <?php echo $CORE->_e(array('auction','84')); ?>: <strong> <?php echo hook_price($price_current); ?></strong>

            <div class="clearfix"></div>

            <?php if(is_numeric($price_shipping)  && $price_shipping > 0){ ?>
            <small> <i class="fa fa-codepen"></i> <?php echo $CORE->_e(array('auction','67')); ?>: <?php echo hook_price($price_shipping); ?> </small>
            <?php } ?>

            <?php
            if(strlen($reserve_price) > 0 && $reserve_price != "0" && $price_bin < 1 ){
            if(is_numeric($reserve_price) && $reserve_price != "0" && $price_current < $reserve_price){
            ?>

            <span class="priceextra"><i class="fa fa-times"></i> <?php echo $CORE->_e(array('auction','57')); ?></span>

            <?php }else{ ?>

            <span class="priceextra"><i class="fa fa-check"></i> <?php echo $CORE->_e(array('auction','69')); ?></span>

            <?php } } ?>

          </div>

          <div class="col-md-6 text-right">

          <?php echo $bid_btn; ?>

          </div>

      </div>

       </li>



       <?php

	   // RESEVR PRICE NOT YET MET

	   if(strlen($current_bidder_amount) > 4){ ?>
       <li class="list-group-item">

        <?php echo $current_bidder_amount; ?>
       </li>
       <?php } ?>


       <li class="list-group-item biddingbox" style="display:none;">

       <span class="label label-default pull-right" onclick="jQuery('.biddingbox').hide();" ><?php echo $CORE->_e(array('account','48')); ?></span>

        <form method="post" action="" class="row clearfix" onsubmit="return CheckBidding();">
					<input type="hidden" name="auction_action" value="newbid" />
					<input type="hidden" name="bidtype" value="bid" />
	 				<input type='hidden' name='hidden_cp' id='hidden_cp' value='<?php echo $current_price; ?>' />

          <div class="col-md-12">
         		<h4><?php echo $CORE->_e(array('auction','89')); ?></h4>
          </div>

        	<div class="col-md-2">
        		<button class="btn btn-lg btn-primary" type="submit"><?php echo $CORE->_e(array('auction','66')); ?></button>
        	</div>
        </form>

        <script>
		function CheckBidding(){
		<?php

		if($userdata->ID && ($userdata->ID == $post->post_author && $userdata->ID != 1) ){ ?>

		alert("<?php echo $CORE->_e(array('auction','72','flag_noedit')); ?>"); return false;

		<?php }elseif($userdata->ID){ ?>

			var bidprice = jQuery('#bid_amount').val();
			var ecp = jQuery('#hidden_cp').val();
			var ecp = Math.round(parseFloat(ecp)*100)/100;
			var bidprice = Math.round(parseFloat(bidprice)*100)/100;

			if(jQuery.isNumeric(bidprice) && bidprice > ecp){
				return true;
			}else{
			alert('<?php echo $CORE->_e(array('auction','73','flag_noedit')).' '.$GLOBALS['CORE_THEME']['currency']['symbol']; ?>'+ecp+'');
			return false;
			}

		<?php }else{ ?>

			alert("<?php echo $CORE->_e(array('auction','56','flag_noedit')); ?>"); return false;

		<?php } ?>

		};
        </script>

        <hr />

        <h4><?php echo $CORE->_e(array('auction','20')); ?></h4>

        <?php // echo $CORE->_e(array('auction','90')); ?>

        <?php if(strlen($GLOBALS['CORE_THEME']['auction_terms']) > 1){ ?>

        <p><?php echo $CORE->_e(array('auction','65')); ?></p>

        <textarea><?php echo stripslashes($GLOBALS['CORE_THEME']['auction_terms']); ?></textarea>

     	<?php } ?>

        </li>

        <?php } ?>




        <?php if($price_bin != "" && is_numeric($price_bin) && $price_bin > 0 && ( $price_current <= $price_bin ) ){ ?>


        <li class="list-group-item pricebits">


          <div class="row">

              <div class="col-md-6">

              <?php echo $CORE->_e(array('auction','8')); ?>: <strong><?php echo hook_price($price_bin); ?></strong>

               <?php
                     // SHOW SHIPPING IF CLASSIFIEDS IT ON
                     if($auction_type == 2){ ?>
                    <?php if(is_numeric($price_shipping)  && $price_shipping > 0){ ?>
                    <span class="priceextra"> <i class="fa fa-codepen"></i> <?php echo $CORE->_e(array('auction','67')); ?>: <?php echo hook_price($price_shipping); ?></span>
                    <?php } ?>
                    <?php } ?>

                      <?php

                if(is_numeric($bin_qty) && $bin_qty > 1){
                $bin_qty_sold = get_post_meta($post->ID,'qty_sold',true);
                if($bin_qty_sold == ""){ $bin_qty_sold = 0; }
                ?>
                <br /> <?php echo $bin_qty-$bin_qty_sold; ?>/<?php echo $bin_qty; ?> <?php echo $CORE->_e(array('auction','94')); ?>.
                <?php } ?>

              </div>


               <div class="col-md-6 text-right">

              <?php echo $buynow_btn; ?>
              </div>

          </div>


        </li>

        <li class="list-group-item buynowbox" style="display:none;">

            <span class="label label-default pull-right" onclick="jQuery('.buynowbox').hide();" ><?php echo $CORE->_e(array('account','48')); ?></span>


           <h4>Buy Now</h4>

            <?php if(strlen($GLOBALS['CORE_THEME']['auction_terms']) > 1){ ?>

            <p><?php echo $CORE->_e(array('auction','65')); ?></p>

            <textarea><?php echo stripslashes($GLOBALS['CORE_THEME']['auction_terms']); ?></textarea>

            <?php } ?>

            <hr />
            <form method="post" action="" name="buynowform" id="buynowform">
            <input type="hidden" name="auction_action" value="buynow" />
            <div class="text-center"><button class="btn btn-lg btn-primary" type="submit"><?php echo $CORE->_e(array('auction','66')); ?></button></div>
            </form>


       </li>
        <?php } ?>




        <?php } // END BIDDING AREA ?>





</ul>
</div>

    <?php
	// RETURN OUTPUT
	$output = ob_get_contents();
	ob_end_clean();
	return $output;

}

function shortcode_bids(){ global $CORE, $post, $wpdb;

	$bidding_history = get_post_meta($post->ID,'current_bid_data',true);
	if(is_array($bidding_history) && !empty($bidding_history) ){
		return count($bidding_history);
	}else{
		return 0;
	}

}
function shortcode_biddinghistory(){ global $CORE, $post, $wpdb;

	$bidding_history = get_post_meta($post->ID,'current_bid_data',true);

	// START OUTPUT
	ob_start();

	// LOOP LIST
	if(is_array($bidding_history) && !empty($bidding_history) ){

	$bidding_history = $CORE->multisort( $bidding_history, array('max_amount') );

	// BUILD DATA
	?>
    <ul class="list-group" <?php if(is_admin() && count($bidding_history) > 5) { ?> style="max-height:400px; overflow:scroll; "<?php } ?>>

	<?php foreach($bidding_history as $kk => $bhistory){ ?>

            <li class="list-group-item">


            <?php if(is_admin()){ ?>

            User: <a href="<?php echo get_author_posts_url( $bhistory['userid'] ); ?>"><?php echo $bhistory['username']; ?></a> <br />

            Max Bid: <?php echo hook_price($bhistory['max_amount']); ?> <br />

            Date:  <?php echo hook_date($bhistory['date']); ?>

            <hr />

            <?php }else{ ?>

              <span class="badge pull-right"><small><?php echo hook_date($bhistory['date']); ?></small></span>

            <small><a href="<?php echo get_author_posts_url( $bhistory['userid'] ); ?>"><?php echo $bhistory['username']; ?></a></small>

            <?php } ?>

            </li>
            <?php } ?>
            </ul>

     <?php }else{ ?>

        <div class="text-center"><h4><?php echo $CORE->_e(array('auction','87')); ?></h4></div>

   <?php }

	// RETURN OUTPUT
	$output = ob_get_contents();
	ob_end_clean();
	return $output;
}

	function _hook_language_array($c){

			$d = array(

			"1" 			=> __("Item not sold.","premiumpress"),
			"2" 			=> __(" was the winning bidder.","premiumpress"),
			"3" 			=> __("Auction Finished","premiumpress"),
			"4" 			=> __("Type","premiumpress"),
			"5" 			=> __("Normal Auction","premiumpress"),
			"6" 			=> __("Classifieds (Buy Now Only)","premiumpress"),
			"7" 			=> __("Here you can choose the format of your auction.","premiumpress"),
			"8" 			=> __("Buy Now Price","premiumpress"),
			"9" 			=> __("Here you can set a price for the user to buy this item outright.","premiumpress"),
			"10" 			=> __("Reserve Price","premiumpress"),
			"11" 			=> __("Here you can set the lowest price your willing to sell this item for.","premiumpress"),
			"12" 			=> __("Auction Length","premiumpress"),
			"13" 			=> __("Select the number of days you would like the auction to run for.","premiumpress"),
			"14" 			=> __("My Payment Options","premiumpress"),
			"15" 			=> __("Paypal email updated successfully.","premiumpress"),
			"16" 			=> __("Please enter your PayPal email below, all of your auction payments will be sent to this email minus our service and commission charges.","premiumpress"),
			"17" 			=> __("PayPal Email","premiumpress"),
			"18" 			=> __("Bidding History","premiumpress"),
			"19" 			=> __("Item","premiumpress"),
			"20" 			=> __("Max Bid","premiumpress"),
			"21" 			=> __("Actions","premiumpress"),
			"22" 			=> __("Auction Status","premiumpress"),
			"23" 			=> __("Leave Feedback","premiumpress"),
			"24" 			=> __("View Auction","premiumpress"),
			"25" 			=> __("Feedback you left on","premiumpress"),
			"26" 			=> __("Here you can setup your payment options for receiving payments.","premiumpress"),
			"27" 			=> __("Here you can view your bidding history.","premiumpress"),
			"28" 			=> __("You are the winning bidder.","premiumpress"),
			"29" 			=> __("Bid amount of (%a) is invalid.","premiumpress"),
			"30" 			=> __("You have been outbid!","premiumpress"),
			"31" 			=> __("You are now the highest bidder.","premiumpress"),
			"32" 			=> __("Highest","premiumpress"),
			"33" 			=> __("Your max bid is","premiumpress"),
			"34" 			=> __("and the sellers reserve price is","premiumpress"),
			"35" 			=> __("<b>Note</b> If this auction ends your bid will be increased to match the reserve.","premiumpress"),
			"36" 			=> __("which is less than the sellers reserve price of","premiumpress"),
			"37" 			=> __("<b>Note</b> If the reserve price is not met the item will not be sold.","premiumpress"),
			"38" 			=> __("Reserve Price Not Met","premiumpress"),
			"39" 			=> __("This auction has ended.","premiumpress"),
			"40" 			=> __("was the winning bidder.","premiumpress"),
			"41" 			=> __("Contact Seller","premiumpress"),
			"42" 			=> __("Contact Buyer","premiumpress"),
			"43" 			=> __("Please leave some feedback about this transaction.","premiumpress"),
			"44" 			=> __("Please leave a user rating:","premiumpress"),
			"45" 			=> __("Bad (0%)","premiumpress"),
			"46" 			=> __("Poor","premiumpress"),
			"47" 			=> __("Regular","premiumpress"),
			"48" 			=> __("Good","premiumpress"),
			"49" 			=> __("Excellent (100%)","premiumpress"),
			"50" 			=> __("Submit","premiumpress"),
			"51" 			=> __("Bidding Options","premiumpress"),
			"52" 			=> __("What would you like to do?","premiumpress"),
			"53" 			=> __("Bid","premiumpress"),
			"54" 			=> __("You cannot bid on your own item.","premiumpress"),
			"55" 			=> __("Buy Now","premiumpress"),
			"56" 			=> __("Please login to bid.","premiumpress"),
			"57" 			=> __("Reserve price has not yet met.","premiumpress"),
			"58" 			=> __("Contact seller","premiumpress"),
			"59" 			=> __("Positive Feedback","premiumpress"),
			"60" 			=> __("View other items by this seller","premiumpress"),
			"61" 			=> __("Joined","premiumpress"),
			"62" 			=> __("Confirm Bidding","premiumpress"),
			"63" 			=> __("Please check and confirm your bid amount.","premiumpress"),
			"64" 			=> __("Bid Amount:","premiumpress"),
			"65" 			=> __("By making a bid you confirm to our website terms and conditions","premiumpress"),
			// "66" 			=> __("Confirm Bid","premiumpress"),
			"66"		  => __("Place Bid", "premiumpress"),
			"67" 			=> __("Shipping Price","premiumpress"),
			"68" 			=> __("Here you enter an amount for shipping this item.","premiumpress"),
			"69" 			=> __("Reserve price met.","premiumpress"),
			"70" 			=> __("Bid Now","premiumpress"),
			"71" 			=> __("Seller","premiumpress"),
			"72" 			=> __("You cannot bid on your own auctions.","premiumpress"),
			"73" 			=> __("Please bid greater than.","premiumpress"),
			"74" 			=> __("Starting Price","premiumpress"),
			"75" 			=> __("This is the price the bidding will start at.","premiumpress"),
			"76" 			=> __("finished","premiumpress"),
			"77" 			=> __("active","premiumpress"),
			"78" 			=> __("now","premiumpress"),
			"79" 			=> __("<b>Now What?</b><br />The user has not specified a payment method. Please contact the user for payment instructions.","premiumpress"),
			"80" 			=> __("Would you like to re-list this item?","premiumpress"),
			"81" 			=> __("You can re-list this item free for %a days.","premiumpress"),
			"82" 			=> __("Re-list Item","premiumpress"),
			"83"			=> __("Bidding ends in;","premiumpress"),
			"84"			=> __("Current Price","premiumpress"),
			"85"			=> __("Sellers Details","premiumpress"),
			"86"			=> __("Bidding History","premiumpress"),
			"87"			=> __("No Bidding History","premiumpress"),
			"88"			=> __("bids","premiumpress"),
			//"89"			=> __("Enter your <b>max</b> bid","premiumpress"),
			"89"	    => __("Bid another " . STANDARD_BID_INCREASE),
			"90"			=> __("<p>To help you get the best price possible we have an automated bidding system. </p><p>Enter the maximum amount you are willing to pay for this item and our system will start at the lowest bid price and automatically re-bid for you up to your maximum bid.</p><p>This way you do not need to bid again and will always get the item for the best possible price.</p>
	","premiumpress"),
			"91"			=> __("Condition","premiumpress"),
			"92"			=> __("New","premiumpress"),
			"93"			=> __("Used","premiumpress"),
			"94"			=> __("available","premiumpress"),
			"95"			=> __("Buy Now Quantity","premiumpress"),
			"96"			=> __("Enter the number of items available for sale.","premiumpress"),
			"97"			=> __("Bids","premiumpress"),
			"98"			=> __("Won","premiumpress"),
			"99"			=> __("Messages","premiumpress"),
		);

		$c['english']['auction'] = $d;

		return $c;

	}













function actions_auctionended(){ global $post, $userdata, $CORE;

	// GET CURRENT BIDING DATA
	$current_bidding_data = get_post_meta($post->ID,'current_bid_data',true);
	if(!is_array($current_bidding_data)){ $current_bidding_data = array(); }

	//2. ORDER IT BY KEY (WHICH HOLDS THE BID AMOUNT)
	krsort($current_bidding_data);

	//3. GET THE CURRENT BIDDING DATA
	$checkme = current($current_bidding_data);

	// 4.PRICE DATA
	$current_price = get_post_meta($post->ID,'price_current',true);
	if($current_price == ""|| !is_numeric($current_price)){ $current_price = 0; }
	$reserve_price = get_post_meta($post->ID,'price_reserve',true);
	if($reserve_price == ""|| !is_numeric($reserve_price)){ $reserve_price = 0; }
	$price_shipping = get_post_meta($post->ID,'price_shipping',true);
	if($price_shipping == "" || !is_numeric($price_shipping)){$price_shipping = 0; }

	// RE-LIST BUTTON
	if( $post->post_author == $userdata->ID && isset($GLOBALS['CORE_THEME']['auction_relist']) && $GLOBALS['CORE_THEME']['auction_relist'] == '1' ){

		$days_renew = get_option($post->ID, 'listing_expiry_days', true);

			// GET DAYS FROM THE PACKAGE
			if(!is_numeric($days_renew)){
				$packageID =  get_post_meta($post->ID,'packageID',true);
				$packagefields = get_option("packagefields");
				if(isset($packagefields[$packageID]['expires']) && is_numeric($packagefields[$packageID]['expires']) ){
					$days_renew = $packagefields[$packageID]['expires'];
				}
			}

			if(!is_numeric($days_renew)){ $days_renew = 30; }

		?>

			<h4><?php echo $CORE->_e(array('auction','80')); ?></h4>

			<p><?php echo str_replace("%a", $days_renew, $CORE->_e(array('auction','81'))); ?></p>

			<a href="<?php echo get_permalink($post->ID); ?>?relistme=1" class="btn btn-success"><?php echo $CORE->_e(array('auction','82')); ?></a>

			<hr />

		<?php

	}



	// CHECK IF IM THE WINNING BIDDER THEN DISPLAY PAYMENT BUTTONS
	if($checkme['userid'] == $userdata->ID  && $current_price >= $reserve_price && ( get_post_meta($post->ID,'auction_price_paid',true) == "" ) ){

				// IF THE SELLER IS THE ADMIN, THEN ACCEPT ANY ADMIN PAMENT
				if(user_can($post->post_author, 'administrator')){

				?>

                <button href="#myPaymentOptions" role="button" class="btn btn-info btn-lg" data-toggle="modal"><?php echo $CORE->_e(array('button','22')); ?></button>

				<div id="myPaymentOptions" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
				  <div class="modal-dialog"><div class="modal-content">
				  <div class="modal-header">
					<button type="button" class="close" data-dismiss="modal" aria-hidden="true">x</button>
					<h4 id="myModalLabel"><?php echo $CORE->_e(array('single','13')); ?> (<?php echo hook_price($current_price+$price_shipping); ?>)</h4>
				  </div>
				  <div class="modal-body"><?php echo $CORE->PAYMENTS($current_price+$price_shipping, "CART-".$post->ID."-".$userdata->ID."-".date("Ymdi"), $post->post_title, $post->ID, $subscription = false); ?></div>
				  <div class="modal-footer">

				  <?php echo $CORE->admin_test_checkout(); ?>

				  <button class="btn" data-dismiss="modal" aria-hidden="true"><?php echo $CORE->_e(array('single','14')); ?></button>

                  </div></div></div></div>


				<?php }else{


				// CHECK IF THIS IS A USER AUCTION AND THEY HAVE SET A PAYPAL EMAIL
				$paypalemail = get_user_meta($post->post_author,'user_paypalemail',true);

				if($paypalemail == ""){

					?>

                    <br />
                    <div class="well">
                    <?php echo $CORE->_e(array('auction','79')); ?>
                    </div>

					<?php

				}else{

					$GLOBALS['A_TOTAL'] 	= $current_price+$price_shipping;
					$GLOBALS['A_ORDERID']	= 'USERPAYMENT-'.$post->ID.'-'.date('Ydm');
					$GLOBALS['A_DESC']	 	= strip_tags($post->post_title);

				 	echo hook_custom_paypal_payment('<form method="post"  action="https://www.paypal.com/cgi-bin/webscr" name="checkout_paypal" class="pull-left">
					<input type="hidden" name="lc" value="US">
					<input type="hidden" name="return" value="'.$GLOBALS['CORE_THEME']['links']['callback'].'/?status=thankyou">
					<input type="hidden" name="cancel_return" value="'.$GLOBALS['CORE_THEME']['links']['callback'].'">
					<input type="hidden" name="notify_url" value="'.$GLOBALS['CORE_THEME']['links']['callback'].'">
					<input type="hidden" name="discount_amount_cart" value="0">
					<input type="hidden" name="cmd" value="_xclick">
					<input type="hidden" name="amount" value="'.$GLOBALS['A_TOTAL'].'">
					<input type="hidden" name="item_name" value="'.$GLOBALS['A_DESC'].'">
					<input type="hidden" name="item_number" value="'.$GLOBALS['A_ORDERID'].'">
					<input type="hidden" name="business" value="'.$paypalemail.'">
					<input type="hidden" name="currency_code" value="'.$GLOBALS['CORE_THEME']['currency']['code'].'">
					<input type="hidden" name="charset" value="utf-8">
					<input type="hidden" name="custom" value="'.$GLOBALS['A_ORDERID'].'">
					<button  class="btn btn-lg btn-info">'.$CORE->_e(array('button','21')).'</button>
					</form>');

				}

		}

		// CONTACT SELLER & BUYER BUTTONS
		if(isset($GLOBALS['CORE_THEME']['message_system']) && $GLOBALS['CORE_THEME']['message_system'] != '0'){

					if($userdata->ID == $checkme['userid']){
					?>

                    <a href="<?php echo $GLOBALS['CORE_THEME']['links']['myaccount']; ?>/?u=<?php echo get_the_author_meta( 'user_login', $post->post_author ); ?>&tab=msg&show=1" class="btn btn-info btn-lg">
                    <?php echo $CORE->_e(array('auction','41')); ?>
                    </a>


                    <?php

					}else{
					?>

                    <a href="<?php echo $GLOBALS['CORE_THEME']['links']['myaccount']; ?>/?u=<?php echo $checkme['username']; ?>&tab=msg&show=1" class="btn btn-info btn-lg">
                    <?php echo $CORE->_e(array('auction','42')); ?>
                    </a>


					<?php
                    }

		}

		if($checkme['userid'] == $userdata->ID &&  $CORE->FEEDBACKEXISTS($post->ID, $userdata->ID) === false){

             ?>

             <div class="clearfix"></div>
             <hr>

                 <a href="<?php echo $GLOBALS['CORE_THEME']['links']['myaccount']; ?>/?fdid=<?php echo $post->ID; ?>" class="btn btn-success">
                    <?php echo $CORE->_e(array('auction','23')); ?>
                 </a>

             <?php
        }


}elseif($checkme['userid'] == $userdata->ID && get_post_meta($post->ID,'auction_price_paid',true) != ""){

	// LEAVE FEEDBACK
	if($CORE->FEEDBACKEXISTS($post->ID, $userdata->ID) == false){

		?>

            <a href="<?php echo $GLOBALS['CORE_THEME']['links']['myaccount']; ?>/?fdid=<?php echo $post->ID; ?>" class='btn btn-lg btn-info'>
                <?php echo $CORE->_e(array('feedback','1')); ?>
            </a>

        <?php

	}
}


}



}

?>
