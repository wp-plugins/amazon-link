<?php
/*****************************************************************************************/

/*
 * Default Template Panel Processing
 *
 */
   $Templates = $this->getTemplates();
   $default_templates = $this->get_default_templates();

   $templateOpts = array( 
         'nonce'       => array ( 'Type' => 'nonce', 'Name' => 'update-AmazonLink-def-templates' ),
         'nonce1'      => array ( 'Type' => 'nonce', 'Action' => 'closedpostboxes', 'Name' => 'closedpostboxesnonce', 'Referer' => false),
         'nonce2'      => array ( 'Type' => 'nonce', 'Action' => 'meta-box-order', 'Name' => 'meta-box-order-nonce', 'Referer' => false),

         'ID'          => array ( 'Default' => '', 'Type' => 'hidden'),
         'title'       => array ( 'Type' => 'section', 'Read_Only' => 1, 'Value' => '', 'Class' => 'hidden', 'Section_Class' => 'al_subhead'),
         'Name'        => array ( 'Type' => 'text', 'Read_Only' => 1, 'Name' => __('Template Name', 'amazon-link'), 'Default' => 'Template', 'Size' => '40'),
         'Description' => array ( 'Type' => 'text', 'Read_Only' => 1, 'Name' => __('Template Description', 'amazon-link'), 'Default' => 'Template Description', 'Size' => '80'),
         'Type'        => array ( 'Type' => 'selection', 'Read_Only' => 1, 'Name' => __('Template Type', 'amazon-link'), 'Hint' => __('Type of Template, \'Multi\' templates are for multi-product ones that contain the %ASINS% keyword, \'No ASIN\' templates are for non-product specific ones, e.g. Banners and Scripts', 'amazon-link'), 'Default' => 'Product', 'Options' => array('Product', 'No ASIN', 'Multi') ),
         'Version'     => array ( 'Type' => 'text', 'Read_Only' => 1, 'Name' => __('Template Version', 'amazon-link'), 'Default' => 'Template', 'Size' => '40'),
         'Notice'      => array ( 'Type' => 'text', 'Read_Only' => 1, 'Name' => __('Version Details', 'amazon-link'), 'Default' => '', 'Size' => '80'),
         'Content'     => array ( 'Type' => 'textbox', 'Read_Only' => 1, 'Name' => __('The Template', 'amazon-link'), 'Rows' => 5, 'Description' => __('Template Content', 'amazon-link'), 'Default' => '' ),
         'Buttons1'    => array ( 'Type' => 'buttons', 'Buttons' => 
                                           array ( __('Install', 'amazon-link') => array( 'Action' => 'ALDefTemplateAction', 'Hint' => __( 'Install a new Copy of this template', 'amazon-link'), 'Class' => 'button-secondary'))),
         'preview'     => array ( 'Type' => 'title', 'Value' => '', 'Title_Class' => ''),
         'end'         => array ( 'Type' => 'end')
         );

/*****************************************************************************************/


/*****************************************************************************************/

   // **********************************************************
   // Now display the options editing screen
   unset($templateOpts['Template']);
   foreach ($default_templates as $templateID => $templateDetails) {
      $templateOpts['ID']['Default'] = $templateID;
      $templateOpts['title']['Section_Class'] = 'al_subhead';
      if (isset($Templates[$templateID]) && ($Templates[$templateID]['Version'] < $templateDetails['Version'])) {
         $templateOpts['title']['Section_Class'] .= ' al_highlight';
         $templateOpts['Buttons1']['Buttons'][__('Upgrade', 'amazon-link')] = array( 'Action' => 'ALDefTemplateAction', 'Hint' => __( 'Overwrite the existing template with this version', 'amazon-link'), 'Class' => 'button-secondary');
      } else {
         unset($templateOpts['Buttons1']['Buttons'][__('Upgrade', 'amazon-link')]);
      }
      $templateOpts['title']['Value'] = sprintf(__('<b>%s</b> - %s','amazon-link'), $templateID, $templateDetails['Description'] . ' (Version ' . $templateDetails['Version'] .')');
      $options = $this->getSettings();
      unset($options['template']);
      $options['template_type'] = $templateDetails['Type'];
      $options['template_content'] = $templateDetails['Content'];
      $options['template_keywords'] = $this->search->keywords;
      $asins = explode(',',$options['template_asins']);
      if ( $templateDetails['Type'] == 'Multi' ) {
         $options['live'] = 0;
      } else if ( $templateDetails['Type'] == 'Product' ) {
         $asins = array($asins[0]);
         $options['live'] = 1;
      } else {
         $options['live'] = 0;
         $asins = array();
      }
      if (!$templateDetails['Preview_Off']) {
         $templateOpts['preview']['Value'] = $this->make_links( $asins,'Text Item', $options). '<br style="clear:both"\>';
      } else {
         $templateOpts['preview']['Value'] = '';
      }
      $this->form->displayForm($templateOpts, $default_templates[$templateID]);
      unset($templateOpts['nonce1']);
      unset($templateOpts['nonce2']);
   }


?>