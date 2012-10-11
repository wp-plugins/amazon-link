<?php

// Options structure changed so need to update the 'version' option and upgrade as appropriate...

/*
 * Move from version 1.2 to 1.3 of the plugin (Option Version Null => 1)
 */
if (!isset($Opts['version'])) {
   $cc_map = array('co.uk' => 'uk', 'com' => 'us', 'fr' => fr, 'de' => 'de', 'ca' => 'ca', 'jp' => 'jp');

   if (isset($Opts['tld'])) {
      $cc = isset($cc_map[$Opts['tld']]) ? $cc_map[$Opts['tld']] : 'uk';
      $Opts['default_cc'] = $cc;
      if (isset($Opts['tag'])) $Opts['tag_' . $cc] = $Opts['tag'];
   }
   unset($Opts['tld']);
   unset($Opts['tag']);
   $Opts['version'] = 1;
   $this->saveOptions($Opts);
}

/*
 * Upgrade from 1 to 2:
 * force Template ids to lower case & update 'wishlist_template'.
 */
if ($Opts['version'] == 1) {
   $Templates = $this->getTemplates();
   foreach ($Templates as $Name => $value)
   {
      $renamed_templates[strtolower($Name)] = $value;
   }
   $this->saveTemplates($renamed_templates);
   $Templates = $renamed_templates;
   if (isset($Opts['wishlist_template']))
       $Opts['wishlist_template'] = strtolower($Opts['wishlist_template']);
   $Opts['version'] = 2;
   $this->saveOptions($Opts);
}

/*
 * Upgrade from 2 to 3:
 * copy affiliate Ids to new channels section.
 */
if ($Opts['version'] == 2) {
   $country_data = $this->get_country_data();
   foreach ($country_data as $cc => $data)
   {
      $channels['default']['tag_'.$cc] = isset($Opts['tag_'.$cc]) ? $Opts['tag_'.$cc] : '';
   }
   $channels['default']['Name'] = 'Default';
   $channels['default']['Description'] = 'Default Affiliate Tags';
   $channels['default']['Filter'] = '';
   $Opts['version'] = 3;
   $this->save_channels($channels);
   $this->saveOptions($Opts);
}

/* 
 * Upgrade from 3 to 4:
 * Add Template 'Type' field and 'Version'
 */
if ($Opts['version'] == 3) {
   $Templates = $this->getTemplates();
   foreach ($Templates as $Name => $Data)
   {
      if (preg_match('/%ASINS%/i', $Data['Content'])) {
         $Templates[$Name]['Type'] = 'Multi';
      } else {
         $Templates[$Name]['Type'] = 'Product';
      }
      $Templates[$Name]['Version'] = '1';
      $Templates[$Name]['Preview_Off'] = '0';
   }

   $this->saveTemplates($Templates);
   $Opts['version'] = 4;
   $this->saveOptions($Opts);
}

/*
 * Upgrade from 4 to 5:
 * Add 'aws_valid' to indicate validity of the AWS keys.
 * Correct invalid %AUTHOR% keyword in search_text option.
 */
if ($Opts['version'] == 4) {
   $result = $this->validate_keys($Opts);
   $Opts['aws_valid'] = $result['Valid'];
   $Opts['search_text'] = preg_replace( '!%AUTHOR%!', '%ARTIST%', $Opts['search_text']);
   $Opts['version'] = 5;
   $this->saveOptions($Opts);
}

/* 
 * Upgrade from 5 to 6:
 * Re-install the cache database ('xml' column now a blob, and content must be flushed)
 * revalidate keys as aws_valid not being saved in options screen
 */
if ($Opts['version'] == 5) {

   if ($Opts['cache_enabled']) {
      $this->cache_remove();
      $this->cache_install();
   }
   $result = $this->validate_keys($Opts);
   $Opts['aws_valid'] = $result['Valid'];
   $Opts['version'] = 6;
   $this->saveOptions($Opts);
}

?>