//--- file by Anysoft ---
//--- https://anysoft.us ---

function centerImageAtSingle(){
  var body = jQuery("body.single");

  // if body page
  if(body.length > 0){

    // if not a small screen
    if(jQuery(window).width() > 991){
      // calcualte padding
      var box = jQuery(".singleimg");
      var row = box.closest(".row");
      var margin = (row.height() - box.height()) / 2;
      box.css("padding-top", margin + "px");

    }
  }
}

function addBidNowButtonsAtHome(){
  var body = jQuery("body.home");

  if(body.length > 0){
    var thumbs = jQuery("div.thumbnail");

    jQuery(thumbs).each(function(i, thumb){
      var link = jQuery(thumb).find(".content").find("a");
      var img = jQuery('<img src="http://www.barnburn.com/wp-content/uploads/2017/02/New-Bid-Now-Button.png">');
      img.css("width", "100px").css("margin-bottom", "20px");
      link.prepend(img);

      var frame = jQuery(thumb).find(".frame");
      jQuery(frame).hover(function(e){
        jQuery(link).addClass('psudo_hover');
      }, function(){
        jQuery(link).removeClass('psudo_hover');
      });
    });
  }
}

function styleMoney(){
  styleMoneyAtHome();
  styleMoneyAtSingle();
}

function styleMoneyAtSingle(){
  var money_row = jQuery('body.single .pricebits').first();

  var money = money_row.find('strong');
  money.addClass('money');

  var btn = money_row.find('button');
  btn.css('margin-top', '13px');
}

function styleMoneyAtHome(){
  var money = jQuery('body.home .thumbnail .content b');
  money.addClass('money');
};


jQuery(document).ready(function(){
  centerImageAtSingle();
  addBidNowButtonsAtHome();
  styleMoney();
});
