<?php

/*
Plugin Name: Amazon Link
Plugin URI: http://www.houseindorset.co.uk/plugins/amazon-link
Description: Insert a link to Amazon using the passed ASIN number, with the required affiliate info.
Version: 2.0.1
Text Domain: amazon-link
Author: Paul Stuttard
Author URI: http://www.houseindorset.co.uk
License: GPL2
*/

/*
Copyright 2005-2006 Paul Stuttard (email : wordpress_amazonlink@ redtom.co.uk)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

/*
Usage:
	Add one of the following lines into an entry:

	[amazon asin=<ASIN Number>&text=<link text>]           --> inserts link to amazon item
	[amazon cat=<Category List>&last=<Number of Posts>]    --> inserts table of random items

Layout:

   amazon_container      - Encloses whole wishlist.
   amazon_prod           - Encloses each list item.
   amazon_img_container  - Encloses the item thumbnail (link+img)
   amazon_pic            - Class of the item thumbnail IMG element
   amazon_text_container - Encloses the item description (Title paragraphs+link + Details paragraphs)
   amazon_details        - Encloses the item details part of the description
   amazon_price          - Spans the item's formatted price.

*/

require_once('aws_signed_request.php');

require_once('include/displayForm.php');

if (!class_exists('AmazonWishlist_ip2nation'))
   include_once ( 'include/ip2nation.php');

if (!class_exists('AmazonLinkSearch'))
   include_once ( 'include/amazonSearch.php');

if (!class_exists('AmazonWishlist_For_WordPress')) {
   class AmazonWishlist_For_WordPress {

/*****************************************************************************************/
      /// Settings:
/*****************************************************************************************/
      var $tags          = array();
      // String to insert into Posts to indicate where to insert the amazon items
      var $TagHead       = '[amazon';
      var $TagTail       = ']';

      var $optionVer     = 1;
      var $optionName    = 'AmazonLinkOptions';
      var $templatesName = 'AmazonLinkTemplates';
      var $settings_slug = 'amazon-link-options';
      var $Opts          = null;
      var $Settings      = null;
      var $Templates     = null;

      var $multi_id      = 0;

/*****************************************************************************************/
      // Constructor for the Plugin

      function AmazonWishlist_For_WordPress() {
         $this->__construct();
      }

      function __construct() {
         $this->URLRoot = plugins_url("", __FILE__);
         $this->base_name  = plugin_basename( __FILE__ );
         $this->plugin_dir = dirname( $this->base_name );
         $this->form = new AmazonWishlist_Options;
         $this->ip2n = new AmazonWishlist_ip2nation;
         $this->search = new AmazonLinkSearch;

         register_activation_hook(__FILE__, array($this, 'activate'));               // To perform options upgrade
         add_action('init', array($this, 'init'));                                   // Load i18n and initialise translatable vars
         add_filter('plugin_row_meta', array($this, 'registerPluginLinks'),10,2);    // Add extra links to plugins page
         add_filter('the_posts', array($this, 'stylesNeeded'));                      // Run once to determine if styles needed
         add_filter('the_content', array($this, 'contentFilter'),15);                // Process the content
         add_action('admin_menu', array($this, 'optionsMenu'));                      // Add options page hooks
         add_filter('widget_text', array($this, 'contentFilter'), 16 );              // Filter widget text (after the content?)
      }

/*****************************************************************************************/

      // Functions for the above hooks
 
      // On activation of plugin - used to upgrade settings of previous versions
      function activate() {

         // Options structure changed so need to add the 'version' option and upgrade as appropriate...
         $Opts = $this->getOptions();

         if (!isset($Opts['version'])) {

            $cc_map = array('co.uk' => 'uk', 'com' => 'us', 'fr' => fr, 'de' => 'de', 'ca' => 'ca', 'jp' => 'jp');

            // Move from version 1.2 to 1.3 of the plugin (Option Version Null => 1)
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
      }

      // On wordpress initialisation - load text domain and register styles & scripts
      function init() {

         /* load localisation  */
         load_plugin_textdomain('amazon-link', $this->plugin_dir . '/i18n', $this->plugin_dir . '/i18n');

         // Initialise dependent classes
         $this->search->init($this);
         $this->form->init($this);
         $this->ip2n->init($this);

         // Register our styles and scripts
         $script = plugins_url("amazon.js", __FILE__);
         $admin_script = plugins_url("include/amazon-admin.js", __FILE__);
         // Allow the user to override our default styles. 
         if (file_exists(dirname (__FILE__).'/user_styles.css')) {
            $stylesheet = plugins_url("user_styles.css", __FILE__); 
         } else {
            $stylesheet = plugins_url("Amazon.css", __FILE__);
         }

         wp_register_style('amazon-link-style', $stylesheet);
         wp_register_script('amazon-link-script', $script);
         wp_register_script('amazon-admin-script', $admin_script);
      }

      // If in admin section then register options page and required styles & metaboxes
      function optionsMenu() {

         // Add plugin options page, with load hook to bring in meta boxes and scripts and styles
         $this->opts_page = add_options_page( __('Manage Amazon Link Options', 'amazon-link'), __('Amazon Link', 'amazon-link'), 'manage_options', $this->settings_slug, array($this, 'showOptionsPage'));
         add_action('load-'.$this->opts_page, array(&$this, 'optionsLoad'));
         add_action( "admin_print_styles-" . $this->opts_page, array($this,'amazon_admin_styles') );
         add_action( "admin_print_scripts-" . $this->opts_page, array($this,'amazon_admin_scripts') );

         // Add support for Post/Page edit metabox, this requires our styles and post edit AJAX scripts.
         add_meta_box('amazonLinkID', 'Add Amazon Link', array($this,'insertForm'), 'post', 'normal');
         add_meta_box('amazonLinkID', 'Add Amazon Link', array($this,'insertForm'), 'page', 'normal');

         add_action( "admin_print_scripts-post.php", array($this,'edit_scripts') );
         add_action( "admin_print_scripts-post-new.php", array($this,'edit_scripts') );
         add_action( "admin_print_styles-post-new.php", array($this,'amazon_admin_styles') );
         add_action( "admin_print_styles-post.php", array($this,'amazon_admin_styles') );

      }

      // Hooks required to bring up options page with meta boxes:
      function optionsLoad () {

         add_filter('screen_layout_columns', array(&$this, 'adminColumns'), 10, 2);

         wp_enqueue_script('common');
         wp_enqueue_script('wp-lists');
         wp_enqueue_script('postbox');

         add_meta_box( 'alOptions', __( 'Options', 'amazon-link' ), array (&$this, 'showOptions' ), $this->opts_page, 'normal', 'core' );
         add_meta_box( 'alTemplateHelp', __( 'Template Help', 'amazon-link' ), array (&$this, 'displayTemplateHelp' ), $this->opts_page, 'side', 'low' );
         add_meta_box( 'alTemplates', __( 'Templates', 'amazon-link' ), array (&$this, 'showTemplates' ), $this->opts_page, 'advanced', 'core' );
      }

      function adminColumns($columns, $screen) {
         if ($screen == $this->opts_page) {
	    $columns[$this->opts_page] = 2;
         }
	return $columns;
      }

      /// We only need the styles and scripts when a Wishlist or the Multinational popup is displayed...

      function amazon_admin_styles() {
         wp_enqueue_style('amazon-link-style');
         $this->form->enqueue_styles();
      }

      function amazon_admin_scripts() {
         wp_enqueue_script('amazon-admin-script');
      }

      function amazon_scripts() {
         wp_enqueue_script('amazon-link-script');
      }

      function edit_scripts() {
         $script = plugins_url("postedit.js", __FILE__);
         wp_enqueue_script('wpAmazonLinkAdmin', $script, array('jquery', 'amazon-link-search'), '1.0.0');
      }

      function generate_multi_script() {
         $Settings     = $this->getSettings();
         $country_data = $this->get_country_data();

         $TARGET = $Settings['new_window'] ? 'target="_blank"' : '';
         ?>

<script type='text/javascript'> 
function al_gen_multi (id, asin, def) {
   var country_data = new Array();
<?php
         foreach ($country_data as $cc => $data) {
            echo "   country_data['". $cc ."'] = { 'flag' : '" . $this->URLRoot . "/" . $data['flag'] . "', 'tld' : '" . $data['tld'] . "',  'tag' : '" . $Settings['tag_' . $cc] ."'};\n";
         }
?>
   var content = "";
   for (var cc in country_data) {
      if (cc != def) {
         var url = 'http://www.amazon.' + country_data[cc].tld + '/gp/product/' + asin + '?ie=UTF8&tag=' + country_data[cc].tag + '&linkCode=as2&camp=1634&creative=6738&creativeASIN='+ asin;
         content = content +'<a <?php echo $TARGET; ?> href="' + url + '"><img src="' + country_data[cc].flag + '"></a>';
      }
   }
   al_link_in (id, content);
}
</script> 


<?php
      }

      function registerPluginLinks($links, $file) {
         if ($file == $this->base_name) {
            $links[] = '<a href="options-general.php?page=' . $this->settings_slug .'">' . __('Settings','amazon-link') . '</a>';
         }
         return $links;
      }


      function stylesNeeded($posts){

         // To determine if styles are required is too long winded, always bring them in.
         wp_enqueue_style('amazon-link-style');

         if (empty($posts)) return $posts;
         $this->stylesNeeded = False;
         foreach ($posts as $post) {
            $post->post_content = $this->contentFilter($post->post_content, False, False);
            if ($this->stylesNeeded) {
               $this->amazon_scripts();
               add_action('wp_print_scripts', array($this, 'generate_multi_script'));
               break;
            }
         }
         remove_filter('the_posts', array($this, 'stylesNeeded'));
         return $posts;
      }

/*****************************************************************************************/
      /// Options & Templates Handling
/*****************************************************************************************/

      function get_country_data() {

         if (!isset($this->country_data)) {
            /* Move Country Data construction here so we can localise the strings */
            // Country specific aspects:
            // full name of country,
            // country flag image
            // market place of amazon site
            // tld of main amazon site
            // link to affiliate program site
            // Default tag if none set up
            $this->country_data = array('uk' => array('name' => __('United Kingdom', 'amazon-link'), 'flag' => 'images/flag_uk.gif', 'market' => 'GB', 'tld' => 'co.uk', 'site' => 'https://affiliate-program.amazon.co.uk', 'default_tag' => 'livpauls-21'),
                                        'us' => array('name' => __('United States', 'amazon-link'), 'flag' => 'images/flag_us.gif', 'market' => 'US', 'tld' => 'com', 'site' => 'https://affiliate-program.amazon.com', 'default_tag' => 'lipawe-20'),
                                        'de' => array('name' => __('Germany', 'amazon-link'), 'flag' => 'images/flag_de.gif', 'market' => 'DE', 'tld' => 'de', 'site' => 'https://partnernet.amazon.de', 'default_tag' => 'lipas03-21'),
                                        'fr' => array('name' => __('France', 'amazon-link'), 'flag' => 'images/flag_fr.gif', 'market' => 'FR', 'tld' => 'fr', 'site' => 'https://partenaires.amazon.fr', 'default_tag' => 'lipas-21'),
                                        'jp' => array('name' => __('Japan', 'amazon-link'), 'flag' => 'images/flag_jp.gif', 'market' => 'JP', 'tld' => 'jp', 'site' => 'https://affiliate.amazon.co.jp', 'default_tag' => 'livpaul21-22'),
                                        'it' => array('name' => __('Italy', 'amazon-link'), 'flag' => 'images/flag_it.gif', 'market' => 'IT', 'tld' => 'it', 'site' => 'https://programma-affiliazione.amazon.it', 'default_tag' => 'livpaul-21'),
                                        'cn' => array('name' => __('China', 'amazon-link'), 'flag' => 'images/flag_cn.gif', 'market' => 'CN', 'tld' => 'cn', 'site' => 'https://associates.amazon.cn', 'default_tag' => 'livpaul-21'),
                                        'ca' => array('name' => __('Canada', 'amazon-link'), 'flag' => 'images/flag_ca.gif', 'market' => 'CA', 'tld' => 'ca', 'site' => 'https://associates.amazon.ca', 'default_tag' => 'lipas-20'));
         }
         return $this->country_data;
      }

      function get_default_templates() {

         if (!isset($this->default_templates)) {
            // Default templates
            include('include/defaultTemplates.php');
         }
         return $this->default_templates;
      }

      function get_option_list() {
     
         if (!isset($this->optionList)) {

            $this->optionList = array(

            /* Hidden Options - not saved in Settings */

            'nonce' => array ( 'Type' => 'nonce', 'Name' => 'update-AmazonLink-options' ),
            'cat' => array ( 'Type' => 'hidden' ),
            'last' => array ( 'Type' => 'hidden' ),
            'template' => array(  'Type' => 'hidden'),

            /* Options that change how the items are displayed */
            'hd1s' => array ( 'Type' => 'section', 'Value' => __('Display Options', 'amazon-link'), 'Section_Class' => 'al_subhead1'),

            'text' => array( 'Name' => __('Link Text', 'amazon-link'), 'Description' => __('Default text to display if none specified', 'amazon-link'), 'Default' => 'www.amazon.co.uk', 'Type' => 'text', 'Size' => '40', 'Class' => 'al_border' ),
            'image_class' => array ( 'Name' => __('Image Class', 'amazon-link'), 'Description' => __('Style Sheet Class of image thumbnails', 'amazon-link'), 'Default' => 'wishlist_image', 'Type' => 'text', 'Size' => '40', 'Class' => 'alternate al_border' ),
            'wishlist_template' => array (  'Default' => 'Wishlist', 'Name' => __('Wishlist Template', 'amazon-link') , 'Description' => __('Default template to use for the wishlist', 'amazon-link'), 'Type' => 'selection', 'Class' => 'al_border'  ),
            'wishlist_items' => array (  'Name' => __('Wishlist Length', 'amazon-link'), 'Description' => __('Maximum number of items to display in a wishlist (Amazon only returns a maximum of 5, for the \'Similar\' type of list)', 'amazon-link'), 'Default' => 5, 'Type' => 'text', 'Class' => 'alternate al_border' ),
            'wishlist_type' => array (  'Name' => __('Wishlist Type', 'amazon-link'), 'Description' => __('Default type of wishlist to display, \'Similar\' shows items similar to the ones found, \'Random\' shows a random selection of the ones found ', 'amazon-link'), 'Default' => 'Similar', 'Options' => array('Similar', 'Random', 'Multi'), 'Type' => 'selection', 'Class' => 'al_border'  ),

            /* Options that change the behaviour of the links */

            'multi_cc' => array('Name' => __('Multinational Link', 'amazon-link'), 'Description' => __('Insert links to all other Amazon sites after primary link.', 'amazon-link'), 'Default' => '1', 'Type' => 'checkbox', 'Class' => 'alternate al_border'),
            'localise' => array('Name' => __('Localise Amazon Link', 'amazon-link'), 'Description' => __('Make the link point to the users local Amazon website, (you must have ip2nation installed for this to work).', 'amazon-link'), 'Default' => '1', 'Type' => 'checkbox', 'Class' => 'al_border' ),
            'live' => array ( 'Name' => __('Live Data', 'amazon-link'), 'Description' => __('When creating Amazon links, use live data from the Amazon site, otherwise populate the shortcode with static information.', 'amazon-link'), 'Default' => '0', 'Type' => 'checkbox', 'Class' => 'al_border' ),
            'new_window' => array('Name' => __('New Window Link', 'amazon-link'), 'Description' => __('When link is clicked on open it in a new browser window', 'amazon-link'), 'Default' => '0', 'Type' => 'checkbox', 'Class' => 'alternate' ),
   
            'hd1e' => array ( 'Type' => 'end'),
   
            /* Options related to the Amazon backend */

            'hd2s' => array ( 'Type' => 'section', 'Value' => __('Amazon Associate Information','amazon-link'), 'Section_Class' => 'al_subhead1'),
            'default_cc' => array( 'Name' => __('Default Country', 'amazon-link'), 'Description' => __('Which country\'s Amazon site to use by default', 'amazon-link'), 'Default' => 'uk', 'Type' => 'radio', 'Class' => 'al_border' ),
            'pub_key' => array( 'Name' => __('AWS Public Key', 'amazon-link'), 'Description' => __('Public key provided by your AWS Account', 'amazon-link'), 'Default' => '', 'Type' => 'text', 'Size' => '40', 'Class' => 'alternate al_border' ),
            'priv_key' => array( 'Name' => __('AWS Private key', 'amazon-link'), 'Description' => __('Private key provided by your AWS Account.', 'amazon-link'), 'Default' => "", 'Type' => 'text', 'Size' => '40' ),
            'hd2e' => array ( 'Type' => 'end'),

            'button' => array( 'Type' => 'buttons', 'Buttons' => array( __('Update Options', 'amazon-link' ) => array( 'Class' => 'button-primary', 'Action' => 'AmazonLinkAction'))));

            $country_data = $this->get_country_data();
            // Populate Country related options
            foreach ($country_data as $cc => $data) {
               $this->optionList['default_cc']['Options'][$cc]['Name'] = '<img style="height:14px;" src="'. $this->URLRoot. '/'. $data['flag'] . '">';
               $this->optionList['default_cc']['Options'][$cc]['Input'] = 'tag_' . $cc;
               $this->optionList['tag_' . $cc] = array('Type' => 'option', 'OverrideBlank' => $data['default_tag'], 'Name' => $data['name'] . __(' Affiliate Tag', 'amazon-link'),
                                                       'Description' => sprintf(__('Enter your affiliate tag for %1$s.', 'amazon-link'), '<a href="'. $data['site']. '">'.$data['name'].'</a>' ));
            }

            if (isset($this->search->keywords)) {
               // Populate the hidden Template Keywords
               foreach ($this->search->keywords as $keyword => $details) {
                  if (!isset($this->optionList[$keyword]))
                     $this->optionList[$keyword] = array( 'Type' => 'hidden' );
               }
            }
         }
         return $this->optionList;
      }

      function getOptions() {
         if (null === $this->Opts) {
            $this->Opts = get_option($this->optionName, array());
         }
         return $this->Opts;
      }

      function saveOptions($Opts) {
         $optionList = $this->get_option_list();
         if (!is_array($Opts)) {
            return;
         }
         // Ensure hidden items are not stored in the database
         foreach ( $optionList as $optName => $optDetails ) {
            if ($optDetails['Type'] == 'hidden') unset($Opts[$optName]);
         }
         update_option($this->optionName, $Opts);
         $this->Opts = $Opts;
      }

      function deleteOptions() {
         delete_option($this->optionName);
      }


      function getTemplates() {
         if (null === $this->Templates) {
            $this->Templates = get_option($this->templatesName, array());
            ksort($this->Templates);

         }
         return $this->Templates;
      }

      function saveTemplates($Templates) {
         if (!is_array($Templates)) {
            return;
         }
         update_option($this->templatesName, $Templates);
         $this->Templates = $Templates;
      }

      /*
       * Parse the arguments passed in.
       */
      function parseArgs($arguments) {

         $optionList = $this->get_option_list();

         $args = array();
         parse_str(html_entity_decode($arguments), $args);

         $Opts = $this->getOptions();
         unset($this->Settings);
         /*
          * Check for each setting, local overides saved option, otherwise fallback to default.
          */
         foreach ($optionList as $key => $details) {
            if (isset($args[$key])) {
               $this->Settings[$key] = trim(stripslashes($args[$key]),"\x22\x27");              // Local setting
            } else if (isset($Opts[$key])) {
               $this->Settings[$key] = $Opts[$key];   // Global setting
               if (isset($details['OverrideBlank']) && ($Opts[$key] == '')) {
                  $this->Settings[$key] = $details['OverrideBlank'];      // Use default if Global Setting is blank
               }
            } else if (isset ($details['Default'])) {
               $this->Settings[$key] = $details['Default'];      // Use default
            } else if (isset ($details['OverrideBlank'])) {
               $this->Settings[$key] = $details['OverrideBlank'];      // Use default
            }
         }
         $this->Settings['asin'] = explode (',', $this->Settings['asin']);
      }

      /*
       * Normally Settings are populated from parsing user arguments, however some
       * external calls do not cause argument parsing (e.g. amazon_query). So this
       * ensures we have the defaults.
       */
      function getSettings() {
         if (null === $this->Settings) {
            $this->Settings = $this->getOptions();
         }
         $optionList = $this->get_option_list();
         foreach ($optionList as $key => $details) {
            if ((!isset($this->Settings[$key]) || ($this->Settings[$key] == '')) && isset($details['OverrideBlank'])) {
               $this->Settings[$key] = $details['OverrideBlank'];      // Use default
            }
         }
         return $this->Settings;
      }
/*****************************************************************************************/
      /// Localise Link Facility
/*****************************************************************************************/

      function get_country() {

        // Pretty arbitrary mapping of domains to Amazon sites, default to 'com' - the 'international' site.
        $country_map = array('uk' => array('uk', 'ie', 'gi', 'gl', 'nl', 'vg', 'cy', 'gb'),
                             'fr' => array('fr', 'be', 'bj', 'bf', 'bi', 'cm', 'cf', 'td', 'km', 'cg', 'dj', 'ga', 'gp',
                                           'gf', 'gr', 'pf', 'tf', 'ht', 'ci', 'lu', 'mg', 'ml', 'mq', 'yt', 'mc', 'nc',
                                           'ne', 're', 'sn', 'sc', 'tg', 'vu', 'wf', 'es'),
                             'de' => array('de', 'at', 'ch', 'no', 'dn', 'li', 'sk'),
                             'it' => array('it'),
                             'cn' => array('cn'),
                             'ca' => array('ca', 'pm'),
                             'jp' => array('jp')
                            );
                          
        $country = False;

        if (!isset($this->country)) {

           if ($this->Settings['localise']) {
              $cc = $this->ip2n->get_cc();
              $country = 'us';
              foreach ($country_map as $key => $countries) {
                 if (in_array($cc, $countries)) {
                    $country = $key;
                    continue;
                 }
              }
           }

           if ($country)
              $this->country = $country;
           else
              $this->country = $this->Settings['default_cc'];
         }	

         return $this->country;
      }

/*****************************************************************************************/
      /// Searches through the_content for our 'Tag' and replaces it with the lists or links
      /*
       * Performs 3 functions:
       *   1. Searches through the posts, and if multinational link required or a wishlist then bring in the styles.
       *   2. Process the content and replace the shortcode with amazon links and wishlists
       *   3. Search through the content and record any Amazon ASIN numbers ready to generate a wishlist.
       */
/*****************************************************************************************/
      function contentFilter($content, $doRecs=TRUE, $doLinks=TRUE) {

         $newContent='';
         $index=0;
         $found = 0;

         while ($found !== FALSE) {
            $found = strpos($content, $this->TagHead, $index);
            if ($found === FALSE) {
               // Add the remaining content to the output
               $newContent = $newContent . substr($content, $index);
               break;
            } else {
               // Need to parse any arguments
               $tagEnd = strpos($content, $this->TagTail, $found);
               $arguments = substr($content, $found + strlen($this->TagHead), ($tagEnd-$found-strlen($this->TagHead)));

               $this->parseArgs($arguments);

               if (isset($this->Settings['cat'])) {
                  if ($doRecs) {
                     $output = $this->showRecommendations($this->Settings['cat'],$this->Settings['last']);
                  } elseif (!$this->stylesNeeded) {
                     $output = '<span id="al_popup" onmouseover="al_div_in()" onmouseout="al_div_out()"></span>' .
                               substr($content, $found, ($tagEnd - $found) + strlen($this->TagTail));
                     $this->stylesNeeded = True;
                  } else {
                     $output = substr($content, $found, ($tagEnd - $found) + strlen($this->TagTail));
                  }
               } else if ($doLinks) {
                  // Generate Amazon Link
                  $this->tags = array_merge($this->Settings['asin'], $this->tags);
                  $output = $this->make_links($this->Settings['asin'], $this->Settings['text']);
               } else {
                  $this->tags = array_merge($this->Settings['asin'], $this->tags);
                  if ($this->Settings['multi_cc'] && !$this->stylesNeeded) {
                     $this->stylesNeeded = True;
                     $output = '<span id="al_popup" onmouseover="al_div_in()" onmouseout="al_div_out()"></span>' .
                               substr($content, $found, ($tagEnd - $found) + strlen($this->TagTail));
                  } else {
                     $output = substr($content, $found, ($tagEnd - $found) + strlen($this->TagTail));
                  }
               }

               $newContent = $newContent . substr($content, $index, ($found-$index));
               $newContent = $newContent . $output;
               $index = $tagEnd + strlen($this->TagTail);
            }
         }
         return $newContent;
      }

/*****************************************************************************************/
      /// Display Content, Widgets and Pages
/*****************************************************************************************/

      // Top level function to display options
      function showOptionsPage() {
         global $screen_layout_columns;
?>
<div class="wrap">
		<?php screen_icon('options-general'); ?>
		<h2><?php echo __('Amazon Link Options', 'amazon-link') ?></h2>
	<div id="poststuff" class="metabox-holder">
		<?php do_meta_boxes($this->opts_page, 'normal',0); ?>
   </div>
			<div id="poststuff" class="metabox-holder<?php echo 2 == $screen_layout_columns ? ' has-right-sidebar' : ''; ?>">
				<div id="post-body" class="has-sidebar" >
					<div id="post-body-content" class="has-sidebar-content">
						<?php do_meta_boxes($this->opts_page, 'advanced',0); ?>
					</div>
				</div>
				<div id="side-info-column" class="inner-sidebar">
					<?php do_meta_boxes($this->opts_page, 'side',0); ?>
				</div>
				<br class="clear"/>
			</div>	
		</div>
	<script type="text/javascript">
		//<![CDATA[
		jQuery(document).ready( function($) {
			// close postboxes that should be closed
			$('.if-js-closed').removeClass('if-js-closed').addClass('closed');
			// postboxes setup
			postboxes.add_postbox_toggles('<?php echo $this->opts_page; ?>');
		});
		//]]>
	</script>
<?php
      }

/*****************************************************************************************/

      // Main Options Page
      function showOptions() {
         include('include/showOptions.php');
      }


/*****************************************************************************************/

      function showTemplates() {
         include('include/showTemplates.php');
      }


/*****************************************************************************************/

      function displayTemplateHelp () {
         /*
          * Populate the help popup.
          */
         $text = __('<p>Hover the mouse pointer over the keywords for more information.</p>', 'amazon-link');
         foreach ($this->search->keywords as $keyword => $details) {
            $text = $text . '<p><abbr title="'. htmlspecialchars($details['Description']) .'">%' . strtoupper($keyword) . '%</abbr></p>';
         }
         echo $text;
      }


/*****************************************************************************************/


/*****************************************************************************************/

      // Page/Post Edit Screen Widget
      function insertForm($post) {
         include('include/insertForm.php');
      }

/*****************************************************************************************/
      /// Do Amazon Link Constructs
/*****************************************************************************************/

      function showRecommendations ($categories='1', $last='30') {
         return include('include/showRecommendations.php');
      }

      function get_local_info() {
         $top_cc       = $this->get_country();
         $country_data = $this->get_country_data();
         $info         = array( 'cc' => $top_cc, 'mplace' => $country_data[$top_cc]['market'], 'tld' => $country_data[$top_cc]['tld'], 'tag' => $this->Settings['tag_' . $top_cc]);
         return $info;
      }

      function getURL($asin) {
         $li  = $this->get_local_info();
         
         $text='http://www.amazon.' . $li['tld'] . '/gp/product/'. $asin. '?ie=UTF8&tag=' . $li['tag'] .'&linkCode=as2&camp=1634&creative=6738&creativeASIN='. $asin;
         return $text;
      }

      function getURLs($asin)
      {
         $country_data = $this->get_country_data();
         $li           = $this->get_local_info();

         $text[$li['cc']]='http://www.amazon.' . $li['tld'] . '/gp/product/'. $asin. '?ie=UTF8&tag=' . $li['tag'] .'&linkCode=as2&camp=1634&creative=6738&creativeASIN='. $asin;
         foreach ($country_data as $cc => $data) {
            if ($cc != $li['cc']) {
               $tld = $data['tld'];
               $tag = $this->Settings['tag_'.$cc];
               $text[$cc]='http://www.amazon.' . $tld . '/gp/product/'. $asin. '?ie=UTF8&tag=' . $tag .'&linkCode=as2&camp=1634&creative=6738&creativeASIN='. $asin;
            }
         }
         return $text;
      }

      function doQuery($request)
      {
         $Settings     = $this->getSettings();
         $country_data = $this->get_country_data();
         $li           = $this->get_local_info();

         $cc = $li['cc'];
         if ($cc == 'it')
            $cc = "fr";
         if ($cc == 'cn')
            $cc = 'com';
         $tld = $country_data[$cc]['tld'];
         if (!isset($request['AssociateTag'])) $request['AssociateTag'] = $Settings['tag_' . $cc];

         return aws_signed_request($tld, $request, $Settings['pub_key'], $Settings['priv_key']);
      }

      function make_links($asins, $link_text)
      {
         global $post;
         
         $Settings = $this->getSettings();
         $output = '';
         /*
          * If a template is specified and exists then populate it
          */
         if (isset($Settings['template'])) {
            $Templates = $this->getTemplates();
            if (isset($Templates[$Settings['template']])) {
               $details = array();
               unset($Settings['asin']);
               $Settings['template'] = $Templates[$this->Settings['template']]['Content'];
               if (preg_match('/%ASINS%/i', $Settings['template'])) {
                  $details[] = array('ASINS' => implode(',', $asins));
               } else {
                  foreach ($asins as $asin) {
                     if (strlen($asin) > 8) {
                        if (isset($Settings['live']) || (count($asins) > 1)) {
                           $details[] = $this->search->itemLookup($asin);
                        } else {
                           $details[] = array('ASIN' => $asin );
                        }
                     }
                  }
               }
               $items = array_shift($this->search->parse_results($details, $Settings));

//             echo "<PRE>Items:"; print_r($items); echo "</pre>";
               foreach ($items as $item => $details) {
                  $output .= $details['template'];
               }
            return $output;
            }
         }

         foreach ($asins as $asin) { // PS
//                  echo "<PRE>Details:"; print_r($this->Settings); echo "</pre>";

         /*
          * This code required to maintain backward compatibility
          */
         $object = stripslashes($link_text);

         // Do we need to display or link to an image ?
         if ($this->Settings['image'] || $this->Settings['thumb']) {

            $media_ids = $this->search->find_attachments($asin);
            if (!is_wp_error($media_ids)) {
               $media_id = $media_ids[0]->ID;
            }

            if ($this->Settings['thumb']) {
               if (isset($media_id)) {
                  $thumb = wp_get_attachment_thumb_url($media_id);
               } elseif (strlen($this->Settings['thumb']) > 4) {
                  $thumb = $this->Settings['thumb'];
               }
            }
            if ($this->Settings['image']) {
               if (isset($media_id)) {
                  $image = wp_get_attachment_url($media_id);
               } elseif (strlen($this->Settings['image']) > 4) {
                  $image = $this->Settings['image'];
               }
            }
         }

         // If both thumb and image are specified then just insert the image
         if (isset($thumb) && isset($image)) {
            $object = '<a href = "'. $image .'"><img class="'. $this->Settings['image_class'] .'" src="'. $thumb. '" alt="'. $link_text .'"></a>';
            return $object;
         }

         if (isset($image))
            $object = '<img class="'. $this->Settings['image_class'] .'" src="'. $image . '" alt="'. $link_text .'">';
         if (isset($thumb))
            $object = '<img class="'. $this->Settings['image_class'] .'" src="'. $thumb . '" alt="'. $link_text .'">';

         /*
          * Generate a localised/multinational link, wrapped around '$object'
          */
         $TARGET = $this->Settings['new_window'] ? 'target="_blank"' : '';
         $URL    = $this->getURL($asin);

         if ($this->Settings['multi_cc']) {
            $def = $this->get_country();
            $text='<a '. $TARGET .' onMouseOut="al_link_out()" href="' . $URL .'" onMouseOver="al_gen_multi('. $this->multi_id . ', \'' . $asin . '\', \''. $def .'\');">';
            $text .= $object. '</a>';
            $this->multi_id++;
         } else {
            $text='<a '. $TARGET .' href="' . $URL .'">' . $object . '</a>';
         }
         $output .= $text;
         }
         return $output;
      }

/////////////////////////////////////////////////////////////////////


   } // End Class

   $awlfw = new AmazonWishlist_For_WordPress();

} // End if exists

function amazon_get_link($args)
{
   global $awlfw;
   $awlfw->parseArgs($args);       // Get the default settings
   foreach ($awlfw->Settings['asin'] as $asin) {
      return $awlfw->getURL($asin);        // Return a URL
   }
}

function amazon_get_links($args)
{
  global $awlfw;
  $awlfw->parseArgs($args);       // Get the default settings
   foreach ($awlfw->Settings['asin'] as $asin) {
      return $awlfw->getURLs($asin);        // Return a URL
   }
}

function amazon_make_links($args)
{
  global $awlfw;
  $awlfw->parseArgs($args);       // Get the default settings
  return $awlfw->make_links($awlfw->Settings['asin'], $awlfw->Settings['text']);        // Return html
}

function amazon_query($request)
{
  global $awlfw;
  return $awlfw->doQuery($request);   // Return response

}

function amazon_recommends($categories='1', $last='30')
{
  global $awlfw;
  return $awlfw->showRecommendations ($categories, $last);
}

?>