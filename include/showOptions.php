<?php
/*****************************************************************************************/

/*
 * Admin Panel Processing
 *
 */

   $Opts = $this->getOptions();

/*****************************************************************************************/

   // See if the user has posted us some information
   // If they did, the admin Nonce should be set.
   $NotifyUpdate = False;
   if( isset($_POST[ 'WishPicsAction' ]) && ($_POST[ 'WishPicsAction' ] == __('Update Options', 'amazon-link')) && 
       check_admin_referer( 'update-WishPics-options')) {

      // Update Current Wishlist settings

      foreach ($this->optionList as $optName => $optDetails) {
         if (isset($optDetails['Name'])) {
            // Read their posted value
            $Opts[$optName] = stripslashes($_POST[$optName]);
            }
      }
      $this->saveOptions($Opts);
      $NotifyUpdate = True;
    }

/*****************************************************************************************/

   /*
    * If first run need to create a default settings
    */
   $Update=False;
   foreach ($this->optionList as $optName => $optDetails) {
      if(!isset($Opts[$optName]) && isset($optDetails['Default'])) {
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

   // **********************************************************
   // Now display the options editing screen

   $this->form->displayForm($this->optionList, $Opts);

?>