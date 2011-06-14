<?php
/*****************************************************************************************/

/*
 * Template Panel Processing
 *
 */
   $Templates = $this->getTemplates();

   $templateOpts = array( 
         'nonce'       => array ( 'Type' => 'nonce', 'Name' => 'update-AmazonLink-templates' ),
         'nonce1'       => array ( 'Type' => 'nonce', 'Action' => 'closedpostboxes', 'Name' => 'closedpostboxesnonce', 'Referer' => false),
         'nonce2'       => array ( 'Type' => 'nonce', 'Action' => 'meta-box-order', 'Name' => 'meta-box-order-nonce', 'Referer' => false),

         'ID'          => array ( 'Default' => '', 'Type' => 'hidden'),
         'title'       => array ( 'Type' => 'section', 'Value' => '', 'Class' => 'hidden', 'Section_Class' => 'al_subhead'),
         'Name'        => array ( 'Type' => 'text', 'Name' => __('Template Name', 'amazon-link'), 'Default' => 'Template', 'Size' => '40'),
         'Description' => array ( 'Type' => 'text', 'Name' => __('Template Description', 'amazon-link'), 'Default' => 'Template Description', 'Size' => '80'),
         'Content'     => array ( 'Type' => 'textbox', 'Name' => __('The Template', 'amazon-link'), 'Rows' => 5, 'Description' => __('Template Content', 'amazon-link'), 'Default' => '' ),
         'Buttons1'    => array ( 'Type' => 'buttons', 'Buttons' => 
                                           array ( __('Copy', 'amazon-link') => array( 'Action' => 'ALTemplateAction', 'Class' => 'button-secondary'),
                                                   __('Update', 'amazon-link') => array( 'Action' => 'ALTemplateAction', 'Class' => 'button-secondary'),
                                                   __('New', 'amazon-link') => array( 'Action' => 'ALTemplateAction', 'Class' => 'button-secondary'),
                                                   __('Delete', 'amazon-link') => array( 'Action' => 'ALTemplateAction', 'Class' => 'button-secondary') )),
         'preview'     => array ( 'Type' => 'title', 'Value' => '', 'Title_Class' => ''),
         'end'         => array ( 'Type' => 'end')
         );

/*****************************************************************************************/


   $Action = (isset($_POST[ 'ALTemplateAction' ]) && check_admin_referer( 'update-AmazonLink-templates')) ?
                      $_POST[ 'ALTemplateAction' ] : 'No Action';

   // Get the Template ID if selected.
   if (isset($_POST['ID'])) {
      $templateID=$_POST['ID'];
   }

   $NotifyUpdate = False;
   // See if the user has posted us some information
   // If they did, the admin Nonce should be set.
   if(  $Action == __('Update', 'amazon-link') ) {

      // Update Template settings

      // Check for clash of ID with other templates
      $NewTemplateID = $_POST['Name'];
      if ($templateID !== $NewTemplateID) {
         $NewID = '';
         while (isset($Templates[ $NewTemplateID . $NewID ]))
            $NewID++;
         unset($Templates[$templateID]);
         $templateID = $NewTemplateID . $NewID;
         $_POST['Name'] = $templateID;
      }


      foreach ($templateOpts as $Setting => $Details) {
         if (isset($Details['Name'])) {
            // Read their posted value
            $Templates[$templateID][$Setting] = stripslashes($_POST[$Setting]);
         }
      }
      $NotifyUpdate = True;
      $UpdateMessage = sprintf (__('Template %s Updated','amazon-link'), $templateID);

   } else if (  $Action == __('Delete', 'amazon-link') ) {
      unset($Templates[$templateID]);
      $NotifyUpdate = True;
      $UpdateMessage = sprintf (__('Template "%s" deleted.','amazon-link'), $templateID);
   } else if (  $Action == __('Copy', 'amazon-link') ) {
      $NewID = 1;
      while (isset($Templates[ $templateID . $NewID ]))
         $NewID++;
      $Templates[$templateID. $NewID] = $Templates[$templateID];
      $Templates[$templateID. $NewID]['Name'] = $templateID. $NewID;
      $NotifyUpdate = True;
      $UpdateMessage = sprintf (__('Template "%s" created from "%s".','amazon-link'), $templateID. $NewID, $templateID);
   } else if (  $Action == __('New', 'amazon-link') ) {
      $NewID = '';
      while (isset($Templates[ __('Template', 'amazon-link') . $NewID ]))
         $NewID++;
      $Templates[__('Template', 'amazon-link') . $NewID] = array('Name' => __('Template', 'amazon-link') . $NewID, 'Content' => '', 'Description' => __('Template Description', 'amazon-link'));
      $NotifyUpdate = True;
      $UpdateMessage = sprintf (__('Template "%s" created.','amazon-link'), __('Template', 'amazon-link') . $NewID);
   }


/*****************************************************************************************/

   /*
    * If first run need to create a default templates
    */
   if(!isset($Templates['Wishlist'])) {
      $default_templates = $this->get_default_templates();
      foreach ($default_templates as $templateName => $templateDetails) {
         if(!isset($Templates[$templateName])) {
            $Templates[$templateName] = $templateDetails;
            $NotifyUpdate = True;
            $UpdateMessage = sprintf (__('Default Templates Created - Note: \'Wishlist\' template must exist.','amazon-link'));
         }
      }
   }


/*****************************************************************************************/

   if ($NotifyUpdate && current_user_can('manage_options')) {
      $this->saveTemplates($Templates);

      // **********************************************************
      // Put an options updated message on the screen
?>

<div class="updated">
 <p><strong><?php echo $UpdateMessage; ?></strong></p>
</div>

<?php
   }

/*****************************************************************************************/

   // **********************************************************
   // Now display the options editing screen
   unset($templateOpts['Template']);
   foreach ($Templates as $templateID => $templateDetails) {
      $templateOpts['ID']['Default'] = $templateID;
      $templateOpts['title']['Value'] = sprintf(__('<b>%s</b> - %s','amazon-link'), $templateID, $templateDetails['Description']);
      if (preg_match('/%ASINS%/i', $Templates[$templateID]['Content'])) {
         $asins = 'asin=B001L4GBXY,B001L2EZNY,B001LR3576,B001KSJNWC,B001LWZCKY,B001GTPI7O,B001GTAGS0';
         $live='';
      } else {
         $asins = 'asin=0340993766';
         $live='&live=1';
      }
      $templateOpts['preview']['Value'] = amazon_make_links( $asins.'&text=Text Item&text1=Text1&text2=Text 2&text3=Text 3&text4=Text 4&template='.$templateID.$live). '<br style="clear:both"\>';
      $this->form->displayForm($templateOpts, $Templates[$templateID]);
      unset($templateOpts['nonce1']);
      unset($templateOpts['nonce2']);
   }


?>