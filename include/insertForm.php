<?php
/*****************************************************************************************/

/*
 * Post/Page Edit Widget
 *
 */

/*****************************************************************************************/

   $results_html = __('Results: ', 'amazon-link'). 
                  '<img style="float:right" alt="" title="" id="amazon-link-status" class="ajax-feedback " src="images/wpspin_light.gif" />'.
                  '<div style="clear:both" id="amazon-link-result-list"></div>';

   /* This is the template used for generating each line of the search results */
   $results_template = htmlspecialchars ('
<div class="amazon_prod">
 <div class="amazon_img_container">
  <A href="%URL%">
   <IMG class="amazon_pic" src="%THUMB%">
  </a>
 </div>

 <div class="amazon_text_container">
  <p>
   <a href="%URL%">%TITLE%</a>
  </p>

  <div class="amazon_details">
     <div style="float:right">
      <input style="float:left" type="button" title="'. __('Insert a text only link into the post','amazon-link'). '" onClick="return wpAmazonLinkAd.sendToEditor(this.form, {asin: \'%ASIN%\', thumb_url: \'%THUMB%\', image_url:\'%IMAGE%\'} );" value="'.__('Text', 'amazon-link').'" class="button-secondary">
      <input style="float:right" type="button" title="'. __('Insert as a thumbnail link into the post','amazon-link'). '"onClick="return wpAmazonLinkAd.sendToEditor(this.form, {asin: \'%ASIN%\', thumb_override: \'1\', thumb_url: \'%THUMB%\', image_url:\'%IMAGE%\'} );" value="'.__('Thumbnail', 'amazon-link').'" class="button-secondary"></br>
      <input style="float:left" type="button" title="'. __('Insert a fullsize image link into the post','amazon-link'). '"onClick="return wpAmazonLinkAd.sendToEditor(this.form, {asin: \'%ASIN%\', image_override: \'1\', thumb_url: \'%THUMB%\', image_url:\'%IMAGE%\'} );" value="'.__('Image', 'amazon-link').'" class="button-secondary">
      <div style="float:right">
       <img style="margin-left:auto;margin-right:auto" alt="" title="" id="upload-progress-%ASIN%" class="ajax-feedback" src="images/wpspin_light.gif" />
       <input id="upload-button-%ASIN%" type="button" title="'. __('Upload cover image into media library','amazon-link'). '"onClick="return wpAmazonLinkSearch.grabMedia(this.form, {asin: \'%ASIN%\'} );" value="'.__('Upload', 'amazon-link').'" class="al_hide-%DOWNLOADED% button-secondary">
       <input id="uploaded-button-%ASIN%" type="button" title="'. __('Remove image from media library','amazon-link'). '"onClick="return wpAmazonLinkSearch.removeMedia(this.form, {asin: \'%ASIN%\'} );" value="'.__('Delete', 'amazon-link').'" class="al_show-%DOWNLOADED% button-secondary">
      </div>
     </div>
   
    <p>'. __('by %ARTIST% [%MANUFACTURER%]', 'amazon-link') .'<br />
    '. __('Rank/Rating: %RANK%/%RATING%', 'amazon-link').'<br />
    <b>' .__('Price', 'amazon-link').': <span style="color:red;">%PRICE%</span></b>
   </p>
  </div>

 </div>

</div>');

   /* This defines the options table shown in the Amazon link widget */
   $optionList = array(
         'subhd1' => array ( 'Type' => 'title', 'Value' => __('Enter the following two settings for a standard Amazon Link', 'amazon-link'), 'Class' => 'sub-head'),
         'asin' => array( 'Id' => 'AmazonLinkOpt', 'Name' => __('ASIN', 'amazon-link'), 'Default' => '', 'Type' => 'text', 'Hint' => __('Amazon product ASIN', 'amazon-link'), 'Size' => '30', 
                           'Buttons' => array( __('Send To Editor', 'amazon-link' ) => array( 'Type' => 'button', 'Class' => 'button-primary', 'Script' => 'return wpAmazonLinkAd.sendToEditor(this.form);'))),
         'text' => array( 'Id' => 'AmazonLinkOpt', 'Name' => __('Link Text', 'amazon-link'), 'Hint' => __('Amazon Link text', 'amazon-link'), 'Default' => 'Amazon', 'Type' => 'text', 'Size' => '40'),
         'subhd2' => array ( 'Type' => 'title', 'Value' => __('Search Amazon for Products', 'amazon-link'), 'Class' => 'sub-head'),



         'template' => array( 'Id' => 'amazon-link-search', 'Default' => $results_template, 'Type' => 'hidden'),
         'post' => array( 'Id' => 'amazon-link-search', 'Default' => $post->ID, 'Type' => 'hidden'),
         'index' => array( 'Id' => 'amazon-link-search', 'Name' => __('Product Index', 'amazon-link'), 'Hint' => __('Which Amazon Product Index to Search through', 'amazon-link'), 'Default' => 'Books', 'Type' => 'selection', 
                           'Options' => array ( 'Apparel', 'Baby','Beauty','Blended','Books','Classical','DigitalMusic','DVD','Electronics','ForeignBooks','GourmetFood','HealthPersonalCare','HomeGarden',
                                               'Jewelry','Kitchen','Magazines','Merchants','Miscellaneous','Music','MusicalInstruments','MusicTracks','OfficeProducts','OutdoorLiving','PCHardware',
                                               'Photo','Restaurants','Software','SoftwareVideoGames','SportingGoods','Tools','Toys','VHS','Video','VideoGames','Wireless','WirelessAccessories') ),
         'author' => array('Id' => 'amazon-link-search', 'Name' => __('Author', 'amazon-link'), 'Hint' => __('Author or Artist to search for', 'amazon-link'), 'Type' => 'text', 'Default' => ''),
         'title' => array('Id' => 'amazon-link-search', 'Name' => __('Title', 'amazon-link'), 'Hint' => __('Items Title to search for', 'amazon-link'), 'Type' => 'text', 'Default' => ''),
         'page' => array('Id' => 'amazon-link-search', 'Name' => __('Page', 'amazon-link'), 'Hint' => __('Page of Search Results', 'amazon-link'), 'Default' => '1', 'Type' => 'text',
                         'Buttons' => array( 
__('-', 'amazon-link' ) => array( 'Type' => 'button', 'Id' => 'amazon-link-search', 'Class' => 'button-secondary', 'Script' => 'return wpAmazonLinkSearch.decPage(this.form);'),
__('+', 'amazon-link' ) => array( 'Type' => 'button', 'Id' => 'amazon-link-search', 'Class' => 'button-secondary', 'Script' => 'return wpAmazonLinkSearch.incPage(this.form);'),
__('Search', 'amazon-link' ) => array( 'Type' => 'button', 'Id' => 'amazon-link-search', 'Class' => 'button-secondary', 'Script' => 'return wpAmazonLinkSearch.searchAmazon(this.form);'),
__('x', 'amazon-link' ) => array( 'Type' => 'button', 'Id' => 'amazon-link-search', 'Class' => 'button-secondary', 'Script' => 'return wpAmazonLinkSearch.clearResults(this.form);') )),
         'results' => array ('Id' => 'amazon-link-results', 'Type' => 'title', 'Value' => $results_html, 'Class' => 'hide-if-js'),
         'error' => array ('Id' => 'amazon-link-error', 'Type' => 'title', 'Value' => __('Error - No results returned from your query.', 'amazon-link'), 'Class' => 'hide-if-js'),
//         'endhd2' => array('Type' => 'end'),

         'subhd3' => array ( 'Type' => 'title', 'Value' => __('Enter the following two settings for an Amazon Wishlist', 'amazon-link'), 'Class' => 'sub-head'),
         'cat' => array( 'Id' => 'AmazonLinkOpt', 'Name' => __('Post Category', 'amazon-link'), 'Hint' => __('List of Categories to search through for amazon links', 'amazon-link'), 'Type' => 'text', 'Size' => '40',
                         'Buttons' => array( __('Send To Editor', 'amazon-link' ) => array( 'Type' => 'button', 'Class' => 'button-primary', 'Script' => 'return wpAmazonLinkAd.sendToEditor(this.form);'))),
         'last' => array( 'Id' => 'AmazonLinkOpt', 'Name' => __('Number of Posts', 'amazon-link'), 'Hint' => __('Number of posts to search back through for amazon links', 'amazon-link'),  'Type' => 'text', 'Size' => '5'),
         'subhd4' => array ( 'Type' => 'title', 'Value' => __('Advanced settings', 'amazon-link'), 'Class' => 'sub-head'),
         'defaults' => array( 'Id' => 'AmazonLinkOpt', 'Name' => __('Use Defaults', 'amazon-link'), 'Hint' => __('Use the site default settings for the options below', 'amazon-link'), 'Default' => '1', 'Type' => 'checkbox', 'Script' => 'return wpAmazonLinkAd.toggleAdvanced(this.form);'),
         'localise' => array( 'Id' => 'AmazonLinkOpt', 'Name' => __('Localise Amazon Link', 'amazon-link'), 'Hint' => __('Make the link point to the users local Amazon website', 'amazon-link'), 'Default' => '0', 'Type' => 'checkbox', 'Class' => 'hide-if-js'),
         'multi_cc' => array( 'Id' => 'AmazonLinkOpt', 'Name' => __('Multinational Link', 'amazon-link'), 'Hint' => __('Insert links to all other Amazon sites after primary link.', 'amazon-link'), 'Default' => '0', 'Type' => 'checkbox', 'Class' => 'hide-if-js'),
         'thumb' => array( 'Id' => 'AmazonLinkOpt', 'Name' => __('Display Item Thumbnail', 'amazon-link'), 'Hint' => __('Display thumbnail Cover Image instead of Text.', 'amazon-link'), 'Default' => '0', 'Type' => 'checkbox', 'Class' => 'hide-if-js'),
         'image' => array( 'Id' => 'AmazonLinkOpt', 'Name' => __('Display Item Image', 'amazon-link'), 'Hint' => __('Display full size Cover Image instead of Text.', 'amazon-link'), 'Default' => '0', 'Type' => 'checkbox', 'Class' => 'hide-if-js'),
         'remote_images' => array( 'Id' => 'AmazonLinkOpt', 'Name' => __('Amazon Images', 'amazon-link'), 'Hint' => __('When displaying images use ones hosted on Amazon, not in local media library.', 'amazon-link'), 'Default' => '0', 'Type' => 'checkbox', 'Class' => 'hide-if-js'),
         );

/*****************************************************************************************/

   // **********************************************************
   // Now display the options editing screen
   $this->parseArgs("");
   $this->form->displayForm($optionList, $this->Settings, True, True);

?>