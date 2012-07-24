<?php
/*****************************************************************************************/

/*
 * Post/Page Edit Widget
 *
 */

/*****************************************************************************************/

   $Settings = $this->getOptions();

   $results_html = __('Results: ', 'amazon-link'). 
                  '<img style="float:right" alt="" title="" id="amazon-link-status" class="ajax-feedback " src="images/wpspin_light.gif" />'.
                  '<div style="clear:both" id="amazon-link-result-list"></div>';

   $item_details = '';
   foreach ($this->get_keywords() as $keyword => $details) {
      if (isset($details['Live'])) {
         if ($item_details == '') {
            $item_details = $keyword. ': \'%' . $keyword . '%S#\'';
         } else {
            $item_details .= ', '. $keyword. ': \'%' . $keyword . '%S#\'';
         }
      }
   }

   $aws_api_info = $this->search->get_aws_info();
   $search_indexes = $aws_api_info['SearchIndexByLocale'][$Settings['default_cc']];

   /* This is the template used for generating each line of the search results */
   $results_template = htmlspecialchars ('
<div class="al_found%FOUND%">
 <div class="amazon_prod">
  <div class="amazon_img_container">%LINK_OPEN%<img src="%THUMB%" class="%IMAGE_CLASS%">%LINK_CLOSE%</div>

  <div class="amazon_text_container">
   <p>%LINK_OPEN%%TITLE%%LINK_CLOSE%</p>

   <div class="amazon_details">
      <div style="float:right">
       <div style="width:100%">
        <input style="float:left" type="button" title="'. __('Add ASIN to list of ASINs above','amazon-link'). '"onClick="return wpAmazonLinkAd.addASIN(this.form, {asin: \'%ASIN%\'} );" value="'.__('+', 'amazon-link').'" class="button-secondary">
        <input style="float:left" type="button" title="'. __('Insert a link into the post, based on the selected template','amazon-link'). '"onClick="return wpAmazonLinkAd.sendToEditor(this.form, { '. $item_details.' } );" value="'.__('Insert', 'amazon-link').'" class="button-secondary">
        <input style="float:right" id="upload-button-%ASIN%" type="button" title="'. __('Upload cover image into media library','amazon-link'). '"onClick="return wpAmazonLinkSearch.grabMedia(this.form, {asin: \'%ASIN%\'} );" value="'.__('Upload', 'amazon-link').'" class="al_hide-%DOWNLOADED% button-secondary">
        <input style="float:right" id="uploaded-button-%ASIN%" type="button" title="'. __('Remove image from media library','amazon-link'). '"onClick="return wpAmazonLinkSearch.removeMedia(this.form, {asin: \'%ASIN%\'} );" value="'.__('Delete', 'amazon-link').'" class="al_show-%DOWNLOADED% button-secondary">
       </div>
      </div>
   
     <p>'. __('by %ARTIST% [%MANUFACTURER%]', 'amazon-link') .'<br />
     '. __('Rank/Rating: %RANK%/%RATING%', 'amazon-link').'<br />
     <b>' .__('Price', 'amazon-link').': <span style="color:red;">%PRICE%</span></b>
    </p>
   </div>

  </div>
 </div>
</div>');

   /* This defines the options table shown in the Amazon link widget */
   $optionList = array(
         'subhd1' => array ( 'Type' => 'title', 'Value' => __('Enter the following settings for a simple Amazon Link', 'amazon-link'), 'Title_Class' => 'sub-head'),
         'asin' => array( 'Id' => 'AmazonLinkOpt', 'Name' => __('ASIN', 'amazon-link'), 'Default' => '', 'Type' => 'text', 'Hint' => __('Amazon product ASIN', 'amazon-link'), 'Size' => '30', 
                           'Buttons' => array( __('Insert Link', 'amazon-link' ) => array( 'Type' => 'button', 'Class' => 'button-primary', 'Script' => 'return wpAmazonLinkAd.sendToEditor(this.form);'))),
         'text' => array( 'Id' => 'AmazonLinkOpt', 'Name' => __('Link Text', 'amazon-link'), 'Hint' => __('Amazon Link text', 'amazon-link'), 'Default' => 'Amazon', 'Type' => 'text', 'Size' => '40'),
         'template' => array( 'Id' => 'AmazonLinkOpt', 'Name' => __('Template', 'amazon-link'), 'Hint' => __('Choose which template is used to display the item.', 'amazon-link'), 'Default' => ' ', 'Type' => 'selection'),
         'chan' => array( 'Id' => 'AmazonLinkOpt', 'Name' => __('Channel', 'amazon-link'), 'Hint' => __('Choose which set of Amazon Tracking IDs to use.', 'amazon-link'), 'Default' => ' ', 'Type' => 'selection'));

   if ( $Settings['aws_valid'])
   {
      $optionList = array_merge($optionList,array(
         'subhd2' => array ( 'Type' => 'title', 'Value' => __('Search Amazon for Products', 'amazon-link'), 'Title_Class' => 'sub-head'),
         'template_content' => array( 'Id' => 'amazon-link-search', 'Default' => $results_template, 'Type' => 'hidden'),
         'post' => array( 'Id' => 'amazon-link-search', 'Default' => $post->ID, 'Type' => 'hidden'),
         's_index' => array( 'Id' => 'amazon-link-search', 'Name' => __('Product Index', 'amazon-link'), 'Hint' => __('Which Amazon Product Index to Search through', 'amazon-link'), 'Default' => 'Books', 'Type' => 'selection', 
                           'Options' => $search_indexes ),
         's_author' => array('Id' => 'amazon-link-search', 'Name' => __('Author', 'amazon-link'), 'Hint' => __('Author or Artist to search for', 'amazon-link'), 'Type' => 'text', 'Default' => ''),
         's_title' => array('Id' => 'amazon-link-search', 'Name' => __('Title', 'amazon-link'), 'Hint' => __('Items Title to search for', 'amazon-link'), 'Type' => 'text', 'Default' => ''),
         's_page' => array('Id' => 'amazon-link-search', 'Name' => __('Page', 'amazon-link'), 'Hint' => __('Page of Search Results', 'amazon-link'), 'Default' => '1', 'Type' => 'text',
                         'Buttons' => array(__('-', 'amazon-link' ) => array( 'Type' => 'button', 'Id' => 'amazon-link-search', 'Class' => 'button-secondary', 'Script' => 'return wpAmazonLinkSearch.decPage(this.form);'),
                                            __('+', 'amazon-link' ) => array( 'Type' => 'button', 'Id' => 'amazon-link-search', 'Class' => 'button-secondary', 'Script' => 'return wpAmazonLinkSearch.incPage(this.form);'),
                                            __('Search', 'amazon-link' ) => array( 'Type' => 'button', 'Id' => 'amazon-link-search', 'Class' => 'button-secondary', 'Script' => 'return wpAmazonLinkSearch.searchAmazon(this.form);'),
                                            __('x', 'amazon-link' ) => array( 'Type' => 'button', 'Id' => 'amazon-link-search', 'Class' => 'button-secondary', 'Script' => 'return wpAmazonLinkSearch.clearResults(this.form);') )),
         'results' => array ('Id' => 'amazon-link-results', 'Type' => 'title', 'Value' => $results_html, 'Title_Class' => 'hide-if-js'),
         'error' => array ('Id' => 'amazon-link-error', 'Type' => 'title', 'Value' => __('Error - No results returned from your query.', 'amazon-link'), 'Title_Class' => 'hide-if-js')));
   }

   $optionList = array_merge($optionList,array(
         'subhd3' => array ( 'Type' => 'title', 'Value' => __('Enter the following settings for an Amazon Wishlist', 'amazon-link'), 'Title_Class' => 'sub-head'),
         'cat' => array( 'Id' => 'AmazonListOpt', 'Name' => __('Post Category', 'amazon-link'), 'Hint' => __('List of Categories to search through for amazon links', 'amazon-link'), 'Type' => 'text', 'Size' => '40', 'Default' => 'local',
                         'Buttons' => array( __('Insert Wishlist', 'amazon-link' ) => array( 'Type' => 'button', 'Class' => 'button-primary', 'Script' => 'return wpAmazonLinkAd.sendToEditor(this.form, {wishlist: \'1\'});'))),
         'last' => array( 'Id' => 'AmazonListOpt', 'Name' => __('Number of Posts', 'amazon-link'), 'Hint' => __('Number of posts to search back through for amazon links', 'amazon-link'),  'Type' => 'text', 'Size' => '5'),
         'wishlist_type' => array ( 'Id' => 'AmazonListOpt', 'Name' => __('Wishlist Type', 'amazon-link'), 'Hint' => __('Default type of wishlist to display, \'Similar\' shows items similar to the ones found, \'Random\' shows a random selection of the ones found ', 'amazon-link'), 'Default' => 'Similar', 'Options' => array('Similar', 'Random', 'Multi'), 'Type' => 'selection'  ),
         'subhd4' => array ( 'Type' => 'title', 'Value' => __('Advanced settings', 'amazon-link'), 'Title_Class' => 'sub-head'),
         'defaults' => array( 'Id' => 'AmazonLinkOpt', 'Name' => __('Use Defaults', 'amazon-link'), 'Hint' => __('Use the site default settings for the options below', 'amazon-link'), 'Default' => '1', 'Type' => 'checkbox', 'Script' => 'return wpAmazonLinkAd.toggleAdvanced(this.form);'),
         'localise' => array( 'Id' => 'AmazonLinkOpt', 'Name' => __('Localise Amazon Link', 'amazon-link'), 'Hint' => __('Make the link point to the users local Amazon website', 'amazon-link'), 'Default' => '0', 'Type' => 'checkbox', 'Class' => 'hide-if-js'),
         'search_link' => array( 'Id' => 'AmazonLinkOpt', 'Name' => __('Create Search Links', 'amazon-link'), 'Hint' => __('Make the link point to a search result on the Amazon site', 'amazon-link'), 'Default' => '0', 'Type' => 'checkbox', 'Class' => 'hide-if-js'),
         'multi_cc' => array( 'Id' => 'AmazonLinkOpt', 'Name' => __('Multinational Link', 'amazon-link'), 'Hint' => __('Insert links to all other Amazon sites after primary link.', 'amazon-link'), 'Default' => '0', 'Type' => 'checkbox', 'Class' => 'hide-if-js'),
         'live' => array( 'Id' => 'AmazonLinkOpt', 'Name' => __('Live Data', 'amazon-link'), 'Hint' => __('When displaying the link, use live data from amazon to populate the template', 'amazon-link'), 'Default' => '0', 'Type' => 'checkbox', 'Class' => 'hide-if-js'))
         );

   $optionList['template']['Options'] = array(' ');
   $optionList['T_' . ' '] = array( 'Id' => 'AmazonLinkTemplates', 'Type' => 'hidden', 'Value' => 'text');
   $Templates = $this->getTemplates();
   foreach ($Templates as $templateName => $Details) {
      $optionList['template']['Options'][$templateName]['Name'] = $Details['Name']. '  -  ' . $Details['Description'];
 
      $template_data = array();
      foreach ($this->get_keywords() as $keyword => $details) {
         if ((isset($details['Live']) || isset($details['User'])) && preg_match('/%'.$keyword.'%/i', $Details['Content']))
            $template_data[] = $keyword;
      }
      $optionList['T_' . $templateName] = array( 'Id' => 'AmazonLinkTemplates', 'Type' => 'hidden', 'Value' => implode(',',$template_data));
   }

   $optionList['chan']['Options'] = array(' ');
   $channels = $this->get_channels();
   foreach ($channels as $channel_id => $details) {
      $optionList['chan']['Options'][$channel_id]['Name'] = $details['Name']. '  -  ' . $details['Description'];
   }

   $live_data = array();
   $user_data = array();
   foreach ($this->get_keywords() as $keyword => $details) {
      if (isset($details['Live']))
         $live_data[] = $keyword;
       if (isset($details['User']))
         $user_data[] = $keyword;
   }
   $optionList['template_live_keywords'] = array( 'Id' => 'AmazonLinkTemplates', 'Type' => 'hidden', 'Value' => implode(',',$live_data ));
   $optionList['template_user_keywords'] = array( 'Id' => 'AmazonLinkTemplates', 'Type' => 'hidden', 'Value' => implode(',',$user_data ));

/*****************************************************************************************/

   // **********************************************************
   // Now display the options editing screen
   $Settings['asin'] = (isset($Settings['asin']) && is_array($Settings['asin'])) ? implode(',', $Settings['asin']): '';
   $this->form->displayForm($optionList, $Settings, True, True);

?>