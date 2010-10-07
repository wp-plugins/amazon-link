<?php
/*****************************************************************************************/

/*
 * Admin Panel Supporting Functions
 *

optionList['0'] => array ('Setting' => array( 'Name', 'Type' => [text|       'Size', 'Description', 'Buttons' => array()
                                                        nonce|      'Value'
                                                        hidden|     'Value'
                                                        title|      'Class', 'Value'
                                                        checkbox|   'Description', 'Buttons' => array()
                                                        selection|  'Description', 'Options' => array('Value', 'Name'), 'Buttons' => array()
                                                        buttons|    'Buttons' => ('Value' => ('Action', 'Class')))
 
Opts => Actual settings
Title = 'Title of Form'
Open  = True if want Form Header <div><form>
Close = True if want Form Footer, </form></div>
Body  = True if want to process options
 */

if (!class_exists('AmazonWishlist_Options')) {
   class AmazonWishlist_Options {

function displayForm($optionList, $Opts, $Open = True, $Body = True, $Close = True) {

   if ($Open) {

?>
<div class="wrap">
 <form name="form1" method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
  <table class="form-table">
<?php

   }

   if ($Body) {

      // Loop through the options table, display a row for each.
      foreach ($optionList as $optName => $optDetails) {
         if ($optDetails['Type'] == 'checkbox') {

            // Insert a Check Box Item
            //////////////////////////////////////////////////////////////////////////////////////////////////////////////

?>
   <tr valign="top">
    <th scope="row"><label for="<?php echo $optName; ?>"><?php echo $optDetails['Name']; ?></label></th>
    <td>
     <div>
      <div style="float:left; width:210px">
       <input name="<?php echo $optName; ?>" type="checkbox" value="1" <?php checked($Opts[$optName] == '1') ?>/>
      </div>
      <?php if (isset($optDetails['Buttons'])) displayButtons($optDetails['Buttons']); ?>
     </div>
     <div style="clear:both"><?php echo $optDetails['Description']; ?></div>
    </td>
   </tr>

<?php
         } else if ($optDetails['Type'] == 'selection') {

            // Insert a Dropdown Box Item
            //////////////////////////////////////////////////////////////////////////////////////////////////////////////

?>
   <tr valign="top">
    <th scope="row"><label for="<?php echo $optName; ?>"><?php echo $optDetails['Name']; ?></label></th>
    <td>
     <div>
      <div style="float:left; width:210px">
       <select style="width:200px;" name="<?php echo $optName; ?>" id="<?php echo $optName; ?>" class='postform'>

        <?php
         foreach ($optDetails['Options'] as $Value => $Details) {
            if (is_array($Details)) {
               $Name = $Details['Name'];
            } else {
               $Name = $Details;
               $Value= $Details;
            }
            echo "<option value='$Value' ". selected( $Opts[$optName], $Value, False). " >" . $Name . "</option>";
         }
        ?>
       </select>
      </div>
      <?php if (isset($optDetails['Buttons'])) $this->displayButtons($optDetails['Buttons']); ?>
     </div>
     <div style="clear:both"><?php echo $optDetails['Description']; ?></div>
    </td>
   </tr>

<?php
         } else if ($optDetails['Type'] == 'radio') {

            // Insert a Radio Selection
            //////////////////////////////////////////////////////////////////////////////////////////////////////////////

?>
   <tr valign="top">
    <th scope="row"><label for="<?php echo $optName; ?>"><?php echo $optDetails['Name']; ?></label></th>
    <td>
     <div>
      <div style="float:left; width:610px">
       <ul>
        <?php
         foreach ($optDetails['Options'] as $Value => $Details) {
            if (is_array($Details)) {
               $Name = $Details['Name'];
            } else {
               $Name = $Details;
               $Value= $Details;
            }
            echo "<li><input name='$optName' type='radio' value='$Value' ". checked( $Opts[$optName], $Value, False). " >" . $Name . "</input>";
            if (isset($Details['Input'])) $this->displayInput($optionList[$Details['Input']], $Details['Input'], $Opts);
            echo "</li>\n";
         }
        ?>
       </ul>
      </div>
      <?php if (isset($optDetails['Buttons'])) $this->displayButtons($optDetails['Buttons']); ?>
     </div>
     <div style="clear:both"><?php echo $optDetails['Description']; ?></div>
    </td>
   </tr>

<?php


         } else if ($optDetails['Type'] == 'buttons') {

            // Insert a set of Buttons
            //////////////////////////////////////////////////////////////////////////////////////////////////////////////
?>
    <tr valign="top">
     <td colspan="2">
       <?php $this->displayButtons($optDetails['Buttons']); ?><br />
       <?php if (isset($optDetails['Description'])) echo $optDetails['Description']; ?>
     </td>
    </tr>

<?php
         } else if ($optDetails['Type'] == 'hidden') {

            // Insert a hidden Item
            //////////////////////////////////////////////////////////////////////////////////////////////////////////////
            $Value = isset($optDetails['Value']) ? $optDetails['Value'] : $Opts[$optName];
?>
    <input name="<?php echo $optName; ?>" type="hidden" value="<?php echo $Value; ?>" />
<?php

         } else if ($optDetails['Type'] == 'text') {

            // Insert a Text Item
            //////////////////////////////////////////////////////////////////////////////////////////////////////////////
            $size = isset($optDetails['Size']) ? $optDetails['Size'] : '20';
?>
   <tr valign="top">
    <th scope="row"><label for="<?php echo $optName; ?>"> <?php echo $optDetails['Name']; ?></label></th>
    <td>
     <div>
      <div style="float:left; width:210px">
       <input name="<?php echo $optName; ?>" type="text" value="<?php echo $Opts[$optName]; ?>" size="<?php echo $size ?>" />
      </div>
      <?php if (isset($optDetails['Buttons'])) $this->displayButtons($optDetails['Buttons']); ?>
     </div>
     <div style="clear:both"><?php echo $optDetails['Description']; ?></div>
    </td>
   </tr>

<?php
         } else if ($optDetails['Type'] == 'nonce') {

            // Insert a Nonce Item
            //////////////////////////////////////////////////////////////////////////////////////////////////////////////

            wp_nonce_field($optDetails['Name']);

         } else if ($optDetails['Type'] == 'title') {

            // Insert a Title Item
            //////////////////////////////////////////////////////////////////////////////////////////////////////////////
            if (isset($optDetails['Class'])) {
               $Title = '<div class="' . $optDetails['Class'] . '">'. $optDetails['Value'] . '</div>';
            } else {
               $Title = '<h2>'. $optDetails['Value'] . '</h2>';
            }
?>
    <tr valign="top">
     <td colspan="2">
      <?php echo $Title ?>
     </td>
    </tr>
<?php
         }
      }
   }

   if ($Close) {
?>

  </table>
 </form>
</div>
<?php

   }
}

function displayButtons ($buttons) {

   foreach ($buttons as $Value => $details) {
?>
   <input type="submit" class="<?php echo $details['Class']; ?>" name="<?php echo $details['Action'] ?>" value="<?php echo $Value; ?>" />
<?php
   }
}

function displayInput ($optDetails, $optName, $Opts) {
   $size = isset($optDetails['Size']) ? $optDetails['Size'] : '20';
?>
     <input name="<?php echo $optName; ?>" type="text" value="<?php echo $Opts[$optName]; ?>" size="<?php echo $size ?>" />
     <?php if (isset($optDetails['Buttons'])) $this->displayButtons($optDetails['Buttons']); ?>
     <?php echo $optDetails['Description']; ?>
<?php
}

   }
}
?>