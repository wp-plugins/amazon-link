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
            if ((($optName == 'pub_key') || ($optName == 'priv_key')) &&
                ($Opts[$optName] != stripslashes($_POST[$optName]))) {
               $AWS_keys_updated = 1;
               $Opts[$optName] = trim(stripslashes($_POST[$optName]));
            } else {
               $Opts[$optName] = stripslashes($_POST[$optName]);
            }
         }
      }
      $this->saveOptions($Opts);
      $update = __('Options saved.', 'amazon-link' );

/*****************************************************************************************/

    } else if ( $Action == __('Install Database','amazon-link')) {

      // User requested installation of the ip2nation database
?>

<div class="updated">
 <p><strong><?php echo $this->ip2n->install(); ?></strong></p>
</div>

<?php
    } else if ( $Action == __('Remove Database','amazon-link')) {

      // User requested Uninstallation of the ip2nation database
?>

<div class="updated">
 <p><strong><?php echo $this->ip2n->uninstall(); ?></strong></p>
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
  // echo "<PRE>"; print_r($Opts); echo "</pRE>";
   /* AWS Keys not yet validate, do a dummy request to see if we get any errors */
   if (strlen($Opts['pub_key']) > 0) {
      if ((isset($AWS_keys_updated) || !$Opts['aws_valid'])) {
         $result = $this->validate_keys($Opts);
         $Opts['aws_valid'] = $result['Valid'];
         if (current_user_can('manage_options')) $this->saveOptions($Opts);
         if (!$result['Valid']) {
            $optionList['aws_valid']['Description'] = '<span style="color:red">' .
                                                       __('AWS Request Failed, please check keys - Error Message: ','amazon-link') .
                                                       $result['Message'] . 
                                                       '</span>';
         }
      }
   } else {
      $Opts['aws_valid'] = 0;
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
  

   $optionList['ip2n_message']['Value'] = $ip2n_status['Message'];
   if ($ip2n_status['Install'] == True) {
      $optionList['ip2n_message']['Buttons'][__('Install Database','amazon-link')] = 
                    array('Class' => 'button-secondary', 'Action' => 'AmazonLinkAction', 'Hint' => __( 'Install/Update the ip2nation database', 'amazon-link'));
   }
   if ($ip2n_status['Uninstall'] == True) {
      $optionList['ip2n_message']['Buttons'][__('Remove Database','amazon-link')] = 
                    array('Class' => 'button-secondary', 'Hint' => __( 'Remove the ip2nation database', 'amazon-link'), 'Action' => 'AmazonLinkAction');
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

?>