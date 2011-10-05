<?php

$wishlist_template = htmlspecialchars ('
<div class="al_found%FOUND%">
 <div class="amazon_prod">
  <div class="amazon_img_container">
   %LINK_OPEN%<img class="%IMAGE_CLASS%" src="%THUMB%">%LINK_CLOSE%
  </div>
  <div class="amazon_text_container">
   <p>%LINK_OPEN%%TITLE%%LINK_CLOSE%</p>
   <div class="amazon_details">
     <p>by %ARTIST% [%MANUFACTURER%]<br />
     Rank/Rating: %RANK%/%RATING%<br />
     <b>Price: <span class="amazon_price">%PRICE%</span></b>
    </p>
   </div>
  </div>
 </div>
</div>');


$carousel_template = htmlspecialchars ('
<script type=\'text/javascript\'>
var amzn_wdgt={widget:\'Carousel\'};
amzn_wdgt.tag=\'%TAG%\';
amzn_wdgt.widgetType=\'ASINList\';
amzn_wdgt.ASIN=\'%ASINs%\';
amzn_wdgt.title=\'%TEXT%\';
amzn_wdgt.marketPlace=\'%MPLACE%\';
amzn_wdgt.width=\'600\';
amzn_wdgt.height=\'200\';
</script>
<script type=\'text/javascript\' src=\'http://wms.assoc-amazon.%TLD%/20070822/%MPLACE%/js/swfobject_1_5.js\'>
</script>');


$iframe_template = htmlspecialchars ('
<iframe src="http://%RCM%/e/cm?lt1=_blank&bc1=000000&IS2=1&bg1=FFFFFF&fc1=000000&lc1=0000FF&t=%TAG%&o=%MPLACE_ID%&p=8&l=as4&m=amazon&f=ifr&ref=ss_til&asins=%ASIN%" style="width:120px;height:240px;" scrolling="no" marginwidth="0" marginheight="0" frameborder="0"></iframe>
');


$image_template = htmlspecialchars ('
<div class="al_found%FOUND%">
 %LINK_OPEN%<img alt="%TITLE%" title="%TITLE%" src="%IMAGE%" class="%IMAGE_CLASS%">%LINK_CLOSE%
</div>
');


$mp3_clips_template = htmlspecialchars ('
<script type=\'text/javascript\'>
var amzn_wdgt={widget:\'MP3Clips\'};
amzn_wdgt.tag=\'%TAG%\';
amzn_wdgt.widgetType=\'ASINList\';
amzn_wdgt.ASIN=\'%ASINS%\';
amzn_wdgt.title=\'%TEXT%\';
amzn_wdgt.width=\'250\';
amzn_wdgt.height=\'250\';
amzn_wdgt.shuffleTracks=\'True\';
amzn_wdgt.marketPlace=\'%MPLACE%\';
</script>
<script type=\'text/javascript\' src=\'http://wms.assoc-amazon.%TLD%/20070822/%MPLACE%/js/swfobject_1_5.js\'>
</script>');


$my_favourites_template = htmlspecialchars ('
<script type=\'text/javascript\'>
var amzn_wdgt={widget:\'MyFavorites\'};
amzn_wdgt.tag=\'%TAG%\';
amzn_wdgt.columns=\'1\';
amzn_wdgt.rows=\'3\';
amzn_wdgt.title=\'%TEXT%\';
amzn_wdgt.width=\'250\';
amzn_wdgt.ASIN=\'%ASINS%\';
amzn_wdgt.showImage=\'True\';
amzn_wdgt.showPrice=\'True\';
amzn_wdgt.showRating=\'True\';
amzn_wdgt.design=\'5\';
amzn_wdgt.colorTheme=\'White\';
amzn_wdgt.headerTextColor=\'#FFFFFF\';
amzn_wdgt.marketPlace=\'%MPLACE%\';
</script>
<script type=\'text/javascript\' src=\'http://wms.assoc-amazon.%TLD%/20070822/%MPLACE%/js/AmazonWidgets.js\'>
</script>');


$thumbnail_template = htmlspecialchars ('
<div class="al_found%FOUND%">
 %LINK_OPEN%<img alt="%TITLE%" title="%TITLE%" src="%THUMB%" class="%IMAGE_CLASS%">%LINK_CLOSE%
</div>');


         $this->default_templates = array (
            'carousel' => array ( 'Name' => 'Carousel', 'Description' => __('Amazon Carousel Widget', 'amazon-link'), 
                                  'Content' => $carousel_template ),
            'iframe image' => array ( 'Name' => 'Iframe Image', 'Description' => __('Standard Amazon Image Link', 'amazon-link'), 
                                  'Content' => $iframe_template ),
            'image' => array ( 'Name' => 'Image', 'Description' => __('Localised Image Link', 'amazon-link'), 
                                  'Content' => $image_template ),
            'mp3 clips' => array ( 'Name' => 'MP3 Clips', 'Description' => __('Amazon MP3 Clips Widget', 'amazon-link'), 
                                  'Content' => $mp3_clips_template ),
            'my favourites' => array ( 'Name' => 'My Favourites', 'Description' => __('Amazon My Favourites Widget', 'amazon-link'), 
                                  'Content' => $my_favourites_template),
            'thumbnail' => array ( 'Name' => 'Thumbnail', 'Description' => __('Localised Thumb Link', 'amazon-link'), 
                                  'Content' => $thumbnail_template),
            'wishlist' => array ( 'Name' => 'Wishlist', 'Description' => __('Used to generate the wishlist', 'amazon-link'), 
                                  'Content' => $wishlist_template)
         );

?>