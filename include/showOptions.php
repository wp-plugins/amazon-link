<?php
/*****************************************************************************************/

/*
 * Admin Panel Supporting Functions
 *
 * Require settings for:

Array containing items matching globals:


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

   $this->form->displayForm($this->optionList, $Opts, __('Amazon Link Plugin Options'));

/*
?>

<div class="wrap">
 <h2><?php _e('Amazon Link Plugin Options') ?></h2>
 <form name="form1" method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">

<?php wp_nonce_field('update-WishPics-options'); ?>

  <table class="form-table">


<?php 

   // Loop through the options table, display a row for each.
   foreach ($this->optionList as $optName => $optDetails) {
   if (isset($optDetails['Name'])) {
      if ($optDetails['Type'] == "checkbox") {

   // Insert a Check Box Item

?>
   <tr valign="top">
    <th scope="row"><label for="<?php echo $optName; ?>"><?php echo $optDetails['Name']; ?></label></th>
    <td>
     <input name="<?php echo $optName; ?>" type="checkbox" value="1" <?php checked($Opts[$optName] == "1") ?>/>
     <br />
     <?php echo $optDetails['Description']?>

    </td>
  </tr>

<?php
      } else if ($optDetails['Type'] == "selection") {

   // Insert a Dropdown Box Item

?>
   <tr valign="top">
    <th scope="row"><label for="<?php echo $optName; ?>"><?php echo $optDetails['Name']; ?></label></th>
    <td>
     <select style="width:200px;" name="<?php echo $optName; ?>" id="<?php echo $optName; ?>" class='postform'>

<?php
   foreach ($optDetails['Options'] as $key => $Details) {
      echo "<option value='$Details' ". selected( $Opts[$optName] == $Details ). " >" . $Details . "</option>";
   }
?>
     </select>
     <br />
   <?php echo $optDetails['Description']; ?>

    </td>
  </tr>

<?php      } else {

   // Insert a Text Item
   $size = isset($optDetails['Size']) ? $optDetails['Size'] : '20';
?>
   <tr valign="top">
    <th scope="row"><label for="<?php echo $optName; ?>"> <?php echo $optDetails['Name']; ?></label></th>
    <td>
     <input name="<?php echo $optName; ?>" type="text" value="<?php echo $Opts[$optName]; ?>" size="<?php echo $size ?>" />
     <br />
<?php echo $optDetails['Description']?>
    </td>
   </tr>

<?php
         }
      }
   }
?>

  </table>

  <p class="submit">
   <input type="submit" class="button-primary" name="WishPicsAction" value="<?php _e('Update Options', 'amazon-link' ); ?>" />
  </p>
 </form>
</div>

<?php
*/
?>