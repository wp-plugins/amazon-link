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

      function __construct() {
          add_action('init', array($this,'init'));
      }

      function init() {
         $stylesheet = plugins_url("form.css", __FILE__);
         wp_register_style('amazon-link-form', $stylesheet);
      }

      function displayForm($optionList, $Opts, $Open = True, $Body = True, $Close = True) {

         if ($Open) {
?>
<div class="wrap">
 <form name="form1" method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
<?php
         }

         if ($Body) {

            // Loop through the options table, display a row for each.
            foreach ($optionList as $optName => $optDetails) {
               if (!isset($Opts[$optName]) && isset($optDetails['Default']))
                   $Opts[$optName] = $optDetails['Default'];

               if ($optDetails['Type'] == 'checkbox') {

                  // Insert a Check Box Item
                  //////////////////////////////////////////////////////////////////////////////////////////////////////////////
                  $hint   = isset($optDetails['Hint']) ? $optDetails['Hint'] : '';
                  $id     = isset($optDetails['Id']) ? 'id="'.$optDetails['Id'].'"' : '';
                  $class  = isset($optDetails['Class']) ? 'class="'.$optDetails['Class'].' al_opt_container"' : 'class="al_opt_container"';
                  $script = isset($optDetails['Script']) ? ' onClick="'.$optDetails['Script'].'" ' : '';

?>
   <dl <?php echo $class ?>>
    <dt class="al_label"><label for="<?php echo $optName; ?>"><?php echo $optDetails['Name']; ?></label><dt>
    <dd class="al_opt_details">
      <input style="float:left" <?php echo $id. ' '. $script ?> name="<?php echo $optName; ?>" title="<?php echo stripslashes($hint); ?>" type="checkbox" value="1" <?php checked($Opts[$optName] == '1') ?>/>&nbsp;
      <?php if (isset($optDetails['Buttons'])) displayButtons($optDetails['Buttons']); ?>
      <?php if (isset($optDetails['Description'])) echo '<div class="al_description">'.$optDetails['Description'].'</div>'; ?>
    </dd>
   </dl>
<?php
               } else if ($optDetails['Type'] == 'selection') {

                  // Insert a Dropdown Box Item
                  //////////////////////////////////////////////////////////////////////////////////////////////////////////////
                  $id = isset($optDetails['Id']) ? 'id="'.$optDetails['Id'].'"' : '';

?>
   <dl class="al_opt_container">
    <dt class="al_label"><label for="<?php echo $optName; ?>"><?php echo $optDetails['Name']; ?></label></dt>
    <dd class="al_opt_details">
     <div class="al_input">
      <select <?php echo $id; ?> style="width:200px;" name="<?php echo $optName; ?>" class='postform'>
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
     <?php if (isset($optDetails['Description'])) echo '<div class="al_description">'.$optDetails['Description'].'</div>'; ?>
    </dd>
   </dl>

<?php
               } else if ($optDetails['Type'] == 'radio') {

                  // Insert a Radio Selection
                  //////////////////////////////////////////////////////////////////////////////////////////////////////////////

?>
   <dl class="al_opt_container">
    <dt class="al_label"><label for="<?php echo $optName; ?>"><?php echo $optDetails['Name']; ?></label>
    <dd class="al_opt_details">
       <div class="al_input">
       <ul>
        <?php
         foreach ($optDetails['Options'] as $Value => $Details) {
            if (is_array($Details)) {
               $Name = $Details['Name'];
               $id = isset($Details['Id']) ? 'id="'.$Details['Id'].'"' : '';
            } else {
               $Name = $Details;
               $Value= $Details;
               $id = '';
            }
            echo "<li><input ".$id." name='$optName' type='radio' value='$Value' ". checked( $Opts[$optName], $Value, False). " >" . $Name;
            if (isset($Details['Input'])) $this->displayInput($optionList[$Details['Input']], $Details['Input'], $Opts);
            echo "</li>\n";
         }
        ?>
       </ul>
      </div>
      <?php if (isset($optDetails['Buttons'])) $this->displayButtons($optDetails['Buttons']); ?>
      <?php if (isset($optDetails['Description'])) echo '<div class="al_description">'.$optDetails['Description'].'</div>'; ?>
    </dd>
   </dl>

<?php


               } else if ($optDetails['Type'] == 'buttons') {

                  // Insert a set of Buttons
                  //////////////////////////////////////////////////////////////////////////////////////////////////////////////
?>
    <div class="al_opt_container">
       <?php $this->displayButtons($optDetails['Buttons']); ?><br />
      <?php if (isset($optDetails['Description'])) echo '<div style="font-size:80%;clear:both">'.$optDetails['Description'].'</div>'; ?>
    </div>

<?php
               } else if ($optDetails['Type'] == 'hidden') {

                  // Insert a hidden Item
                  //////////////////////////////////////////////////////////////////////////////////////////////////////////////
                  $Value = isset($optDetails['Value']) ? $optDetails['Value'] : $Opts[$optName];
                  $id = isset($optDetails['Id']) ? 'id="'.$optDetails['Id'].'"' : '';
?>
    <input <?php echo $id ?> name="<?php echo $optName; ?>" type="hidden" value="<?php echo $Value; ?>" />
<?php

               } else if ($optDetails['Type'] == 'text') {

                  // Insert a Text Item
                  //////////////////////////////////////////////////////////////////////////////////////////////////////////////
                  $size = isset($optDetails['Size']) ? $optDetails['Size'] : '20';
                  $hint = isset($optDetails['Hint']) ? $optDetails['Hint'] : '';
                  $id = isset($optDetails['Id']) ? 'id="'.$optDetails['Id'].'"' : '';
?>
   <dl class="al_opt_container">
    <dt class="al_label"><span><label for="<?php  echo $optName; ?>"> <?php echo $optDetails['Name']; ?></label></span></dt>
    <dd class="al_opt_details">
     <div class="al_input">
      <input style="width:200px" <?php  echo $id ?> name="<?php echo $optName; ?>" title="<?php echo stripslashes($hint); ?>" type="text" value="<?php echo $Opts[$optName]; ?>" size="<?php echo $size ?>" />
     </div>
     <?php if (isset($optDetails['Buttons'])) $this->displayButtons($optDetails['Buttons']); ?>
     <?php if (isset($optDetails['Description'])) echo '<div class="al_description">'.$optDetails['Description'].'</div>'; ?>
    </dd>
   </dl>

<?php
               } else if ($optDetails['Type'] == 'nonce') {

                  // Insert a Nonce Item
                  //////////////////////////////////////////////////////////////////////////////////////////////////////////////

                  wp_nonce_field($optDetails['Name']);

               } else if ($optDetails['Type'] == 'title') {
                  $id = isset($optDetails['Id']) ? 'id="'.$optDetails['Id'].'"' : '';

                  // Insert a Title Item
                  //////////////////////////////////////////////////////////////////////////////////////////////////////////////
                  if (isset($optDetails['Class'])) {
                     $Title = '<div '.$id.' class="' . $optDetails['Class'] . '">'. $optDetails['Value'] . '</div>';
                  } else {
                     $Title = '<h2 '.$id.'>'. $optDetails['Value'] . '</h2>';
                  }
?>
    <div class="al_opt_container">
      <?php echo $Title ?>
    </div>
<?php
               } else if ($optDetails['Type'] == 'section') {
                  $id = isset($optDetails['Id']) ? 'id="'.$optDetails['Id'].'"' : '';

                  // Insert a Section
                  //////////////////////////////////////////////////////////////////////////////////////////////////////////////
                  $Title = '<h3 '.$id.'>'. $optDetails['Value'] . '</h3>';
?>
    <div style="al_description" class="<?php echo $optDetails['Class']; ?>">
      <?php echo $Title ?>
<?php
               } else if ($optDetails['Type'] == 'end') {

                  // End a Section
                  //////////////////////////////////////////////////////////////////////////////////////////////////////////////
                  echo "</div>";
               }
            }
         }

         if ($Close) {
?>

 </form>
</div>
<?php

         }
      }

      function displayButtons ($buttons) {

         foreach ($buttons as $Value => $details) {
            $type = isset($details['Type']) ? $details['Type'] : 'submit';
            $script = isset($details['Script']) ? ' onClick="'.$details['Script'].'" ' : '';
            $id = isset($details['Id']) ? 'id="'.$details['Id'].'"' : '';
?>
   <input <?php echo $id;?> type="<?php echo $type;?>" <?php echo $script; ?> class="<?php echo $details['Class']; ?>" name="<?php echo $details['Action'] ?>" value="<?php echo $Value; ?>" />
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