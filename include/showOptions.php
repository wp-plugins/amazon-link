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
   $NotifyUpdate = False;
   if(  $Action == __('Update Options', 'amazon-link') ) {

      // Update Current Wishlist settings

      foreach ($optionList as $optName => $optDetails) {
         if (isset($optDetails['Name'])) {
            // Read their posted value
            $Opts[$optName] = stripslashes($_POST[$optName]);
            }
      }
      $this->saveOptions($Opts);
      $NotifyUpdate = True;
    } else if ( $Action == __('Install Database','amazon-link')) {

      // User requested installation of the ip2nation database
?>

<div class="updated">
 <p><strong><?php echo $this->ip2n->install(); ?></strong></p>
</div>

<?php
    }

/*****************************************************************************************/

   /*
    * If first run need to create a default settings
    */
   $Update=False;
   foreach ($optionList as $optName => $optDetails) {
      if(!isset($Opts[$optName]) && isset($optDetails['Default']) && (!$optDetails['Name'])) {
         $Opts[$optName] = $optDetails['Default'];
         $Update=True;
      }
   }
   if ($Update && current_user_can('manage_options'))
      $this->saveOptions($Opts);


/*****************************************************************************************/

   if ($NotifyUpdate) {
      // **********************************************************
      // Put an options updated message on the screen
?>

<div class="updated">
 <p><strong><?php _e('Options saved.', 'amazon-link' ); ?></strong></p>
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

?>