<?php
/*****************************************************************************************/

/*
 * Admin Panel Processing
 *
 */
   $Opts       = $this->getOptions();
   $optionList = $this->get_option_list();

/*****************************************************************************************/

   $Action = (isset($_POST[ 'AmazonLinkAction' ]) && check_admin_referer( 'update-AmazonLink-options')) ?
                      $_POST[ 'AmazonLinkAction' ] : 'No Action';

   // See if the user has posted us some information
   // If they did, the admin Nonce should be set.
   $update = False;
   if(  $Action == __('Update Options', 'amazon-link') ) {

      // Update Current Wishlist settings

      foreach ($optionList as $optName => $optDetails) {
         if (isset($optDetails['Name'])) {
            // Read their posted value
            $Opts[$optName] = stripslashes($_POST[$optName]);
            }
      }
      $this->saveOptions($Opts);
      $update = __('Options saved.', 'amazon-link' );
    } else if ( $Action == __('Install Database','amazon-link')) {

      // User requested installation of the ip2nation database
?>

<div class="updated">
 <p><strong><?php echo $this->ip2n->install(); ?></strong></p>
</div>

<?php
/*****************************************************************************************/

   // Cache Options
   } else if ( $Action == __('Enable Cache', 'amazon-link')) {
      if ($this->cache_install()) {
         $update = __('Amazon Data Cache Enabled', 'amazon-link');
         $Opts['cache_enabled'] = 1;
      }
   } else if ( $Action == __('Disable Cache', 'amazon-link')) {
      if ($this->cache_remove()) {
         $update = __('Amazon Data Cache Disabled and Removed', 'amazon-link');
         $Opts['cache_enabled'] = 0;
      }
   } else if ( $Action == __('Flush Cache', 'amazon-link')) {
      if ($this->cache_empty()) {
         $update = __('Amazon Data Cache Emptied', 'amazon-link');
      }
   }

   
   // If Enabled then take the opportunity to flush old data
   if ($Opts['cache_enabled']) {
      $this->cache_flush();
      $optionList['cache_c']['Buttons'][__('Enable Cache', 'amazon-link' )]['Disabled'] = 1;
   } else {
      $optionList['cache_c']['Buttons'][__('Disable Cache', 'amazon-link' )]['Disabled'] = 1;
      $optionList['cache_c']['Buttons'][__('Flush Cache', 'amazon-link' )]['Disabled'] = 1;
   }

/*****************************************************************************************/

   /*
    * If first run need to create a default settings
    */
   $Update=False;
   foreach ($optionList as $optName => $optDetails) {
      if(!isset($Opts[$optName]) && isset($optDetails['Default']) && (!$optDetails['Name'])) {
         $Opts[$optName] = $optDetails['Default'];
         $Update = True;
      }
   }
   if ($Update && current_user_can('manage_options'))
      $this->saveOptions($Opts);


/*****************************************************************************************/

   if ($update !== False) {
      // **********************************************************
      // Put an options updated message on the screen
?>

<div class="updated">
 <p><strong><?php echo $update; ?></strong></p>
</div>

<?php
   }

/*****************************************************************************************/

   $ip2n_status = $this->ip2n->status();
  
   if ($ip2n_status['Install'] == True) {
      $optionList['ip2n_message']['Type'] = 'buttons';
      $optionList['ip2n_message']['Description'] = $ip2n_status['Message'];
      $optionList['ip2n_message']['Buttons'][__('Install Database','amazon-link')] = 
                    array('Class' => 'button-secondary', 'Action' => 'AmazonLinkAction');
   } else {
      $optionList['ip2n_message']['Type'] = 'title';
      $optionList['ip2n_message']['Value'] = $ip2n_status['Message'];
      $optionList['ip2n_message']['Title_Class'] = 'sub-head';
   }

/*****************************************************************************************/

   unset($optionList['wishlist_template']['Options']);
   $Templates = $this->getTemplates();
   foreach ($Templates as $templateName => $Details) {
      $optionList['wishlist_template']['Options'][] = $templateName;
   }


   // **********************************************************
   // Now display the options editing screen

   $this->form->displayForm($optionList, $Opts);


//         $pxml = $this->search->do_search(array('s_title' => 'boot', 's_artist' =>'', 's_index' => 'Shoes' ));
//         echo "<!--PXML:"; print_r($pxml); echo "-->";
//$pxml = $this->itemLookup('0141194529,B000H2X2EW,0340993766,B002V092EC');
//echo "<!--ITEMLOOKUP:"; print_r($pxml); echo "-->";
//$request = array('Operation'     => 'ItemLookup',
// 'ResponseGroup' => 'Offers,ItemAttributes,Large,Reviews,Images,SalesRank,EditorialReview',
// 'ResponseGroup' => 'ItemAttributes',
// 'ItemId'        => 'B006FTFGUY', 
// 'IdType'        => 'ASIN');
//$settings = $this->getSettings();
//$pxml = $this->doQuery($request, $settings);
//echo "<PRE>"; print_r($pxml); echo "</PRE>";

?>