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

global $post, $CORE, $userdata;

ob_start();
?>

<a name="toplisting"></a>

<div class="row" style="margin-top:10px;">
    <div class="col-md-8">
    <h1 style="margin-top:0px;">[TITLE]</h1>

    	[CATEGORY] / [RATING small=1 rating=1 style=1] / [FAVS] /  <i class="fa fa-signal"></i> [hits]

    </div>
    <div class="col-md-4">
    	<div class="timeleftbox clearfix">[BIDDINGTIMELEFT]</div>
    </div>
</div>

<hr />

<div class="row">
<div class="col-md-6">

[IMAGES gallery=1]

</div>
<div class="col-md-6">


[BIDDINGFORM]

</div>
</div>

<hr />


<ul class="nav nav-tabs" id="Tabs">

        <li class="active"><a href="#t1" data-toggle="tab">{Description}</a></li>

        <li><a href="#t2" data-toggle="tab">{Details}</a></li>

        <li><a href="#t4" data-toggle="tab" > <?php echo $CORE->_e(array('single','37')); ?> </a></li>

        <?php if(isset($GLOBALS['CORE_THEME']['feedback_enable']) && $GLOBALS['CORE_THEME']['feedback_enable'] == '1'){ ?>

        <li><a href="#t5" data-toggle="tab" > <?php echo $CORE->_e(array('feedback','0')); ?> </a></li>

        <?php } ?>
</ul>

<div class="tab-content">

        <div class="tab-pane active" id="t1"> [CONTENT]  [GOOGLEMAP]  </div>

        <div class="tab-pane" id="t2">[FIELDS hide="map"]</div>

        <div class="tab-pane fade" id="t4">[COMMENTS tab=0]</div>

        <?php if(isset($GLOBALS['CORE_THEME']['feedback_enable']) && $GLOBALS['CORE_THEME']['feedback_enable'] == '1'){ ?>

        <div class="tab-pane fade" id="t5">  [D_FEEDBACK] <div class="text-center">[FEEDBACK class="btn btn-lg btn-success"]</div> </div>

        <?php } ?>

</div>

 <hr />

[RELATED]



<?php $SavedContent = ob_get_clean();
echo hook_item_cleanup($CORE->ITEM_CONTENT($post, hook_content_single_listing($SavedContent)));

?>
