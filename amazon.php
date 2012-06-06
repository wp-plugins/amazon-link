<?php

/*
Plugin Name: Amazon Link
Plugin URI: http://www.houseindorset.co.uk/plugins/amazon-link
Description: Insert a link to Amazon using the passed ASIN number, with the required affiliate info.
Version: 3.0.1
Text Domain: amazon-link
Author: Paul Stuttard
Author URI: http://www.houseindorset.co.uk
License: GPL2
*/

/*
Copyright 2012-2013 Paul Stuttard (email : wordpress_amazonlink@ redtom.co.uk)

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

/*******************************************************************************************************

To serve a page containing amazon links the plugin performs the following:

* Queue the Amazon javascript and styles

* Search through the content and widget text for Amazon links, for each one:
  * Parse arguments (get_options_list(>cached), get_country_data (>cached), get_options(>cached))
  * Make Links:
    * get_templates (>cached)
    * for each ASIN:
      * (if live) perform itemLookup
    * parse results:
      * Get local Info:
        * get channel
        * get country [ip2n lookup >cached]
        * get country data
        * return country specific data
      * For each ASIN:
        * Check for local images
        * Make links * 5:
          * get URL (get local info)
          * get local info
        * Fill in template

* If 'multinational' link found when doing the above then:
  * Return all channels and user channels(>cached), create the javascript for the multinational popup.

*******************************************************************************************************/

define (TIMING, False);

require_once('include/displayForm.php');

//require_once('include/translate.php');

if (!class_exists('AmazonWishlist_ip2nation'))
   include_once ( 'include/ip2nation.php');

if (!class_exists('AmazonLinkSearch'))
   include_once ( 'include/amazonSearch.php');

if (!class_exists('AmazonWishlist_For_WordPress')) {
   class AmazonWishlist_For_WordPress {

/*****************************************************************************************/
      /// Settings:
/*****************************************************************************************/
      // String to insert into Posts to indicate where to insert the amazon items
      var $TagHead       = '[amazon';
      var $TagTail       = ']';
      var $cache_table   = 'amazon_link_cache';
      var $option_version= 5;
      var $plugin_version= '3.0.1';
      var $optionName    = 'AmazonLinkOptions';
      var $user_options  = 'amazonlinkoptions';
      var $templatesName = 'AmazonLinkTemplates';
      var $channels_name = 'AmazonLinkChannels';
      var $settings_slug = 'amazon-link-options';

      var $plugin_home   = 'http://www.houseindorset.co.uk/plugins/amazon-link/';

      var $multi_id      = 0;
      var $scripts_done  = False;
      var $tags          = array();

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
         add_filter('the_content', array($this, 'content_filter'),15);               // Process the content
         add_action('admin_menu', array($this, 'admin_menu'));                       // Add options page and page/post edit hooks
         add_filter('widget_text', array($this, 'widget_filter'), 16 );              // Filter widget text (after the content?)
         add_action('show_user_profile', array($this, 'show_user_options') );        // Display User Options
         add_action('edit_user_profile', array($this, 'show_user_options') );        // Display User Options
         add_action('personal_options_update', array($this, 'update_user_options')); // Update User Options
         add_action('edit_user_profile_update', array($this, 'update_user_options'));// Update User Options
      }

/*****************************************************************************************/

      // Functions for the above hooks
 
      // On activation of plugin - used to create default settings
      function activate() {

         $Opts = $this->getOptions();
         $this->saveOptions($Opts);
      }

      function upgrade_settings($Opts) {

         // Options structure changed so need to update the 'version' option and upgrade as appropriate...
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

         if ($Opts['version'] == 1) {

            /* Upgrade from 1 to 2:
             * force Template ids to lower case & update 'wishlist_template'.
             */
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

         if ($Opts['version'] == 2) {
            /* Upgrade from 2 to 3:
             * copy affiliate Ids to new channels section.
             */
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

         if ($Opts['version'] == 3) {
            /* Upgrade from 3 to 4:
             * Add Template 'Type' field and 'Version'
             */
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
         
         if ($Opts['version'] == 4) {
            /* Upgrade from 4 to 5:
             * Add 'aws_valid' to indicate validity of the AWS keys.
             * Correct invalid %AUTHOR% keyword in search_text option.
             */
            $result = $this->validate_keys($Opts);
            $Opts['aws_valid'] = $result['Valid'];
            $Opts['search_text'] = preg_replace( '!%AUTHOR%!', '%ARTIST%', $Opts['search_text']);
            $Opts['version'] = 5;
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
         $edit_script = plugins_url("postedit.js", __FILE__);
         $admin_script = plugins_url("include/amazon-admin.js", __FILE__);
         // Allow the user to override our default styles. 
         if (file_exists(dirname (__FILE__).'/user_styles.css')) {
            $stylesheet = plugins_url("user_styles.css", __FILE__); 
         } else {
            $stylesheet = plugins_url("Amazon.css", __FILE__);
         }

         wp_register_style('amazon-link-style', $stylesheet, false, $this->plugin_version);
         wp_register_script('amazon-link-script', $script, false, $this->plugin_version);
         wp_register_script('amazon-link-edit-script', $edit_script, array('jquery', 'amazon-link-search'), $this->plugin_version);
         wp_register_script('amazon-link-admin-script', $admin_script, false, $this->plugin_version);

         add_action('wp_enqueue_scripts', array($this, 'amazon_styles'));             // Add base stylesheet
      }

      // If in admin section then register options page and required styles & metaboxes
      function admin_menu() {

         // Add plugin options page, with load hook to bring in meta boxes and scripts and styles
         $this->opts_page = add_options_page( __('Manage Amazon Link Options', 'amazon-link'), __('Amazon Link', 'amazon-link'), 'manage_options', $this->settings_slug, array($this, 'show_options_page'));
         add_action('load-'.$this->opts_page, array(&$this, 'load_options_page'));
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

      function admin_help($contextual_help, $page, $screen) {
         if ($page== $this->opts_page ) {
            $contextual_help = __('Use this page to set up the global settings, provide Affiliate tags for each country locale, configure your AWS keys and select and edit any Templates you might require.','amazon-link');
         }
         return $contextual_help;
      }

      // Hooks required to bring up options page with meta boxes:
      function load_options_page() {

         $screen = get_current_screen();

         add_filter('screen_layout_columns', array(&$this, 'adminColumns'), 10, 2);

         wp_enqueue_script('common');
         wp_enqueue_script('wp-lists');
         wp_enqueue_script('postbox');

         add_meta_box( 'alOptions', __( 'Options', 'amazon-link' ), array (&$this, 'show_options' ), $this->opts_page, 'normal', 'core' );
         add_meta_box( 'alChannels', __( 'Amazon Tracking ID Channels', 'amazon-link' ), array (&$this, 'show_channels' ), $this->opts_page, 'normal', 'core' );
         add_meta_box( 'alInfo', __( 'About', 'amazon-link' ), array (&$this, 'show_info' ), $this->opts_page, 'side', 'core' );
         add_meta_box( 'alTemplateHelp', __( 'Template Help', 'amazon-link' ), array (&$this, 'displayTemplateHelp' ), $this->opts_page, 'side', 'low' );
         add_meta_box( 'alTemplates', __( 'Templates', 'amazon-link' ), array (&$this, 'show_templates' ), $this->opts_page, 'advanced', 'core' );
         add_meta_box( 'alManageTemplates', __( 'Default Templates', 'amazon-link' ), array (&$this, 'show_default_templates' ), $this->opts_page, 'advanced', 'low' );

         if ( $screen->id != $this->opts_page)
            return;

         // Add Contextual Help

         $screen->add_help_tab( array( 'id'      => 'amazon-options-tab',
                                       'title'   => __('Options', 'amazon-link'),
                                       'content' => '<p>' . __('Use this section to set up the global <b>Display Options</b> that change how links are displayed and their behaviour, to enable advance options like \'<code>live data</code>\', the Product search tool or \'<code>Wishlist</code>\' facility you must also configure your AWS keys in the <b>Amazon Associate Information</b> sub section.','amazon-link') . '</p>' .
                                                    '<p>' . __('Within this section you can also set up the Amazon Product Data Cache, select Enable to install the cache, Disable to remove it, and Flush to empty any cached data.','amazon-link') . '</p>' .
                                                    '<p>' . __('The status of the ip2nation database is displayed at the bottom of this section, with options to Install the database if it is not already installed or a new version is available on the ip2nation website.','amazon-link') . '</p>')
                              );
         $screen->add_help_tab( array( 'id'      => 'amazon-channels-tab',
                                       'title'   => __('Channels', 'amazon-link'),
                                       'content' => '<p>' . __('Use this section to set up the global default <b>Amazon Affiliate Tags</b>, as well as create named Channels that can be used on the Amazon Affiliate Site to track the performance of different sections of your site. </p><p> You can also set Affiliate tags in the WordPress User\'s Profile page, these will automatically be used on any post authored by that user','amazon-link') . '</p>')
                              );
         $screen->add_help_tab( array( 'id'      => 'amazon-templates-tab',
                                       'title'   => __('Templates', 'amazon-link'),
                                       'content' => '<p>' . __('Use this section to edit, create and delete Templates used to create the Amazon Links on your site, the content is standard HTML with special <code>%</code> delimited keywords that are replaced by appropriate product information, see the \'Template Help\' section for a list of all valid keywords.','amazon-link') . '</p>')
                              );
         $screen->add_help_tab( array( 'id'      => 'amazon-templates-help-tab',
                                       'title'   => __('Templates Keywords', 'amazon-link'),
                                       'callback'=> array($this, 'displayTemplateHelp'))
                              );
         $screen->add_help_tab( array( 'id'      => 'amazon-templates-default-tab',
                                       'title'   => __('Default Templates', 'amazon-link'),
                                       'content'=> '<p>' . __('This section lists all the default templates included with the plugin, use it to re-install or your update your active templates.','amazon-link') . '</p>')
                              );
         $screen->set_help_sidebar('<p><b>'. __('For more information:'). '</b></p>' .
                                   '<p><a target="_blank" href="'. $this->plugin_home . '">' . __('Plugin Home Page','amazon-link') . '</a></p>' .
                                   '<p><a target="_blank" href="'. $this->plugin_home . 'faq/">' . __('Plugin FAQ','amazon-link') . '</a></p>' .
                                   '<p><a target="_blank" href="'. $this->plugin_home . 'faq/#channels">' . __('Channels Help','amazon-link') . '</a></p>' .
                                   '<p><a target="_blank" href="'. $this->plugin_home . 'faq/#templates">' . __('Template Help','amazon-link') . '</a></p>');
      }

      function adminColumns($columns, $screen) {
         if ($screen == $this->opts_page) {
	    $columns[$this->opts_page] = 2;
         }
	return $columns;
      }

      function amazon_admin_styles() {
         wp_enqueue_style('amazon-link-style');
         $this->form->enqueue_styles();
      }

      function amazon_admin_scripts() {
         wp_enqueue_script('amazon-link-admin-script');
      }

      function amazon_styles() {
         wp_enqueue_style('amazon-link-style');
      }

      function edit_scripts() {
         wp_enqueue_script('amazon-link-edit-script');
      }

      function generate_multi_script() {

         wp_print_scripts('amazon-link-script');

         $Settings     = $this->getSettings();
         $country_data = $this->get_country_data();
         $channels     = $this->get_channels(True, True);

         $TARGET = $Settings['new_window'] ? 'target="_blank"' : '';
         ?>
<span id="al_popup" onmouseover="al_div_in()" onmouseout="al_div_out()"></span>
<script type='text/javascript'> 
function al_gen_multi (id, term, def, chan) {
   var country_data = new Array();
<?php
         foreach ($country_data as $cc => $data) {
            echo "   country_data['". $cc ."'] = { 'flag' : '" . $data['flag'] . "', ";
            foreach ($channels as $id => $chan_data) {
               echo " 'tag_" .$id . "' : '". $chan_data['tag_' .$cc]. "', ";
            }
            echo "'tld' : '" . $data['tld'] . "'};\n";
         }
?>
   var content = "";

   for (var cc in country_data) {
      var type = term[cc].substr(0,1);
      var arg  = term[cc].substr(2);
      if (cc != def) {
         if ( type == 'A' ) {
            var url = 'http://www.amazon.' + country_data[cc].tld + '/gp/product/' + arg+ '?ie=UTF8&linkCode=as2&camp=1634&creative=6738&tag=' + country_data[cc]['tag_'+chan] + '&creativeASIN='+ arg;
         } else {
            var url = 'http://www.amazon.' + country_data[cc].tld + '/mn/search/?_encoding=UTF8&linkCode=ur2&camp=1634&creative=19450&tag=' + country_data[cc]['tag_'+chan] + '&field-keywords=' + arg;
         }
         content = content +'<a <?php echo $TARGET; ?> href="' + url + '"><img src="' + country_data[cc].flag + '"></a>';
      }
   }
   al_link_in (id, content);
}
</script> 


<?php
      remove_action('wp_print_footer_scripts', array($this, 'generate_multi_script'));

      }

      function registerPluginLinks($links, $file) {
         if ($file == $this->base_name) {
            $links[] = '<a href="options-general.php?page=' . $this->settings_slug .'">' . __('Settings','amazon-link') . '</a>';
         }
         return $links;
      }


      function create_popup (){
         if (!$this->scripts_done) {
             $this->scripts_done = True;
             add_action('wp_print_footer_scripts', array($this, 'generate_multi_script'));
             return;
         }
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
            $this->country_data = array('uk' => array('name' => __('United Kingdom', 'amazon-link'), 'lang' => 'en', 'flag' => $this->URLRoot. '/'. 'images/flag_uk.gif', 'market' => 'GB', 'm_id' => 2, 'tld' => 'co.uk', 'rcm' => 'rcm-uk.amazon.co.uk', 'site' => 'https://affiliate-program.amazon.co.uk', 'default_tag' => 'livpauls-21'),
                                        'us' => array('name' => __('United States', 'amazon-link'), 'lang' => 'en', 'flag' => $this->URLRoot. '/'. 'images/flag_us.gif', 'market' => 'US', 'm_id' => 1, 'tld' => 'com', 'rcm' => 'rcm.amazon.com', 'site' => 'https://affiliate-program.amazon.com', 'default_tag' => 'lipawe-20'),
                                        'de' => array('name' => __('Germany', 'amazon-link'), 'lang' => 'de', 'flag' => $this->URLRoot. '/'. 'images/flag_de.gif', 'market' => 'DE', 'm_id' => 3, 'tld' => 'de', 'rcm' => 'rcm-de.amazon.de', 'site' => 'https://partnernet.amazon.de', 'default_tag' => 'lipas03-21'),
                                        'es' => array('name' => __('Spain', 'amazon-link'), 'lang' => 'es', 'flag' => $this->URLRoot. '/'. 'images/flag_es.gif', 'market' => 'ES', 'm_id' => 30, 'tld' => 'es', 'rcm' => 'rcm-es.amazon.es', 'site' => 'https://afiliados.amazon.es', 'default_tag' => 'livpauls0b-21'),
                                        'fr' => array('name' => __('France', 'amazon-link'), 'lang' => 'fr', 'flag' => $this->URLRoot. '/'. 'images/flag_fr.gif', 'market' => 'FR', 'm_id' => 8, 'tld' => 'fr', 'rcm' => 'rcm-fr.amazon.fr', 'site' => 'https://partenaires.amazon.fr', 'default_tag' => 'lipas-21'),
                                        'jp' => array('name' => __('Japan', 'amazon-link'), 'lang' => 'ja', 'flag' => $this->URLRoot. '/'. 'images/flag_jp.gif', 'market' => 'JP', 'm_id' => 9, 'tld' => 'jp', 'rcm' => 'rcm-jp.amazon.co.jp', 'site' => 'https://affiliate.amazon.co.jp', 'default_tag' => 'livpaul21-22'),
                                        'it' => array('name' => __('Italy', 'amazon-link'), 'lang' => 'it', 'flag' => $this->URLRoot. '/'. 'images/flag_it.gif', 'market' => 'IT', 'm_id' => 29, 'tld' => 'it', 'rcm' => 'rcm-it.amazon.it', 'site' => 'https://programma-affiliazione.amazon.it', 'default_tag' => 'livpaul-21'),
                                        'cn' => array('name' => __('China', 'amazon-link'), 'lang' => 'zh-CHS', 'flag' => $this->URLRoot. '/'. 'images/flag_cn.gif', 'market' => 'CN', 'm_id' => 28, 'rcm' => 'rcm-cn.amazon.cn', 'tld' => 'cn', 'site' => 'https://associates.amazon.cn', 'default_tag' => 'livpaul-23'),
                                        'ca' => array('name' => __('Canada', 'amazon-link'), 'lang' => 'en', 'flag' => $this->URLRoot. '/'. 'images/flag_ca.gif', 'market' => 'CA', 'm_id' => 15, 'rcm' => 'rcm-ca.amazon.ca', 'tld' => 'ca', 'site' => 'https://associates.amazon.ca', 'default_tag' => 'lipas-20'));
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
            'template' => array(  'Type' => 'hidden' ),
            'chan' => array(  'Type' => 'hidden' ),

            /* Options that change how the items are displayed */
            'hd1s' => array ( 'Type' => 'section', 'Value' => __('Display Options', 'amazon-link'), 'Section_Class' => 'al_subhead1'),

            'text' => array( 'Name' => __('Link Text', 'amazon-link'), 'Description' => __('Default text to display if none specified', 'amazon-link'), 'Default' => 'www.amazon.co.uk', 'Type' => 'text', 'Size' => '40', 'Class' => 'al_border' ),
            'image_class' => array ( 'Name' => __('Image Class', 'amazon-link'), 'Description' => __('Style Sheet Class of image thumbnails', 'amazon-link'), 'Default' => 'wishlist_image', 'Type' => 'text', 'Size' => '40', 'Class' => 'alternate al_border' ),
            'wishlist_template' => array (  'Default' => 'Wishlist', 'Name' => __('Wishlist Template', 'amazon-link') , 'Description' => __('Default template to use for the wishlist <em>* <a href="#aws_notes" title="AWS Access keys required for full functionality">AWS</a> *</em>', 'amazon-link'), 'Type' => 'selection', 'Class' => 'al_border'  ),
            'wishlist_items' => array (  'Name' => __('Wishlist Length', 'amazon-link'), 'Description' => __('Maximum number of items to display in a wishlist (Amazon only returns a maximum of 5, for the \'Similar\' type of list) <em>* <a href="#aws_notes" title="AWS Access keys required for full functionality">AWS</a> *</em>', 'amazon-link'), 'Default' => 5, 'Type' => 'text', 'Class' => 'alternate al_border' ),
            'wishlist_type' => array (  'Name' => __('Wishlist Type', 'amazon-link'), 'Description' => __('Default type of wishlist to display, \'Similar\' shows items similar to the ones found, \'Random\' shows a random selection of the ones found <em>* <a href="#aws_notes" title="AWS Access keys required for full functionality">AWS</a> *</em>', 'amazon-link'), 'Default' => 'Similar', 'Options' => array('Similar', 'Random', 'Multi'), 'Type' => 'selection', 'Class' => 'al_border'  ),

            /* Options that change the behaviour of the links */

            'multi_cc' => array('Name' => __('Multinational Link', 'amazon-link'), 'Description' => __('Insert links to all other Amazon sites after primary link.', 'amazon-link'), 'Default' => '1', 'Type' => 'checkbox', 'Class' => 'alternate al_border'),
            'localise' => array('Name' => __('Localise Amazon Link', 'amazon-link'), 'Description' => __('Make the link point to the user\'s local Amazon website, (you must have ip2nation installed for this to work).', 'amazon-link'), 'Default' => '1', 'Type' => 'checkbox', 'Class' => 'al_border' ),
            'global_over' => array('Name' => __('Global Defaults', 'amazon-link'), 'Description' => __('Default values in the shortcode "title=xxxx" affect all locales, if not set only override the default locale.', 'amazon-link'), 'Default' => '1', 'Type' => 'checkbox', 'Class' => 'alternate al_border' ),
            'search_link' => array('Name' => __('Create Search Links', 'amazon-link'), 'Description' => __('Generate links to search for the items by "Author Title" for non local links, rather than direct links to the product by ASIN.', 'amazon-link'), 'Default' => '0', 'Type' => 'checkbox', 'Class' => 'al_border' ),
            'search_text' => array( 'Name' => __('Default Search String', 'amazon-link'), 'Description' => __('Default items to search for with "Search Links", uses the same system as the Templates below.', 'amazon-link'), 'Default' => '%ARTIST% | %TITLE%', 'Type' => 'text', 'Size' => '40', 'Class' => 'alternate al_border' ),
            'live' => array ( 'Name' => __('Live Data', 'amazon-link'), 'Description' => __('When creating Amazon links, use live data from the Amazon site, otherwise populate the shortcode with static information. <em>* <a href="#aws_notes" title="AWS Access keys required for full functionality">AWS</a> *</em>', 'amazon-link'), 'Default' => '1', 'Type' => 'checkbox', 'Class' => 'al_border' ),
            'new_window' => array('Name' => __('New Window Link', 'amazon-link'), 'Description' => __('When link is clicked on, open it in a new browser window', 'amazon-link'), 'Default' => '0', 'Type' => 'checkbox', 'Class' => 'alternate' ),
   
            'hd1e' => array ( 'Type' => 'end'),
   
            /* Options related to the Amazon backend */
            'hd2s' => array ( 'Type' => 'section', 'Value' => __('Amazon Associate Information','amazon-link'), 'Section_Class' => 'al_subhead1'),
            'default_cc' => array( 'Name' => __('Default Country', 'amazon-link'), 'Hint' => __('The Amazon Affiliate Tags should be entered in the \'Channels\' section below', 'amazon-link'),'Description' => __('Which country\'s Amazon site to use by default', 'amazon-link'), 'Default' => 'uk', 'Type' => 'selection', 'Class' => 'al_border' ),
            'aws_help' => array( 'Value' => __('AWS Access Keys', 'amazon-link'), 'Description' => __('The AWS Keys are required for some of the features of the plugin to work (The ones marked with AWS above), visit <a href="http://aws.amazon.com/">Amazon Web Services</a> to sign up to get your own keys.', 'amazon-link'), 'Title_Class' => 'al_subheading', 'Id' => 'aws_notes', 'Default' => '', 'Type' => 'title', 'Class' => 'alternate al_border' ),
            'pub_key' => array( 'Name' => __('AWS Public Key', 'amazon-link'), 'Description' => __('Access Key ID provided by your AWS Account, found under Security Credentials/Access Keys of your AWS account', 'amazon-link'), 'Default' => '', 'Type' => 'text', 'Size' => '40', 'Class' => 'alternate' ),
            'priv_key' => array( 'Name' => __('AWS Private key', 'amazon-link'), 'Description' => __('Secret Access Key ID provided by your AWS Account.', 'amazon-link'), 'Default' => '', 'Type' => 'text', 'Size' => '40', 'Class' => '' ),
            'aws_valid' => array ( 'Type' => 'checkbox', 'Read_Only' => 1, 'Name' => 'AWS Keys Validated', 'Default' => '0', 'Class' => 'al_border'),
            'debug' => array( 'Name' => __('Debug Output', 'amazon-link'), 'Description' => __('Adds hidden debug output to the page source to aid debugging. <b>Do not enable on live sites</b>.', 'amazon-link'), 'Default' => '0', 'Type' => 'checkbox', 'Size' => '40', 'Class' => 'al_border' ),
            'hd2e' => array ( 'Type' => 'end'),

            'hd3s' => array ( 'Type' => 'section', 'Value' => __('Amazon Data Cache','amazon-link'), 'Section_Class' => 'al_subhead1'),
            'cache_age' => array ( 'Name' => __('Cache Data Age', 'amazon-link'), 'Description' => __('Max age in hours of the data held in the Amazon Link Cache', 'amazon-link'), 'Type' => 'text', 'Default' => '48', 'Class' => 'al_border'),
            'cache_enabled' => array ( 'Type' => 'backend', 'Default' => '0'),
            'cache_c' => array( 'Type' => 'buttons', 'Buttons' => array( __('Enable Cache', 'amazon-link' ) => array( 'Hint' => __('Install the sql database table to cache data retrieved from Amazon.', 'amazon-link'), 'Class' => 'button-secondary', 'Action' => 'AmazonLinkAction'),
                                                     __('Disable Cache', 'amazon-link' ) => array( 'Hint' => __('Remove the Amazon Link cache database table.', 'amazon-link'),'Class' => 'button-secondary', 'Action' => 'AmazonLinkAction'),
                                                     __('Flush Cache', 'amazon-link' ) => array( 'Hint' => __('Delete all data in the Amazon Link cache.', 'amazon-link'),'Class' => 'button-secondary', 'Action' => 'AmazonLinkAction'),
                                                                        )),
            'hd3e' => array ( 'Type' => 'end'),
            'button' => array( 'Type' => 'buttons', 'Buttons' => array( __('Update Options', 'amazon-link' ) => array( 'Class' => 'button-primary', 'Action' => 'AmazonLinkAction'))));

            $country_data = $this->get_country_data();
            // Populate Country related options
            foreach ($country_data as $cc => $data) {
               $this->optionList['default_cc']['Options'][$cc]['Name'] = $data['name'];
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

      function get_user_option_list() {
        $option_list = array( 
            'title'       => array ( 'Type' => 'subhead', 'Value' => __('Amazon Link Affiliate IDs', 'amazon-link'), 'Description' => __('Valid affiliate IDs from all Amazon locales can be obtained from the relevant Amazon sites: ', 'amazon-link'), 'Class' => 'al_pad al_border'),
         );

         $country_data = $this->get_country_data();
         // Populate Country related options
         foreach ($country_data as $cc => $data) {
            $option_list ['tag_' . $cc] = array('Type' => 'text', 'Default' => '',
                                                'Name' => '<img style="height:14px;" src="'. $data['flag'] . '"> ' . $data['name'],
                                                'Hint' => sprintf(__('Enter your affiliate tag for %1$s.', 'amazon-link'), $data['name'] ));
            $option_list ['title']['Description'] .= '<a href="' . $data['site']. '">'. $data['name']. '</a>, ';
         }
         return $option_list;
      }

      function getOptions() {
         if (!isset($this->Opts)) {
            $this->Opts = get_option($this->optionName, array());
            if (!isset($this->Opts['version']) || ($this->Opts['version'] < $this->option_version))
            {
               $this->upgrade_settings($this->Opts);
               $this->Opts = get_option($this->optionName, array());
            }
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
         if (!isset($this->Templates)) {
            $this->Templates = get_option($this->templatesName, array());
         }
         return $this->Templates;
      }

      function saveTemplates($Templates) {
         if (!is_array($Templates)) {
            return;
         }
         ksort($Templates);
         update_option($this->templatesName, $Templates);
         $this->Templates = $Templates;
      }

      function get_channels($override = False, $user_channels = False) {
         if (!$override || !isset($this->channels)) {
            $channels = get_option($this->channels_name, array());
            if ($user_channels) {
               $users = get_users();
               foreach ($users as $user => $data) {
                  $user_options = $this->get_user_options($data->ID);
                  if (is_array($user_options)) {
                     $channels['al_user_' . $data->ID] = $user_options;
                     $channels['al_user_' . $data->ID]['user_channel'] = 1;
                  }
               }
            }

            if (!$override) return $channels;

            $country_data = $this->get_country_data();
            $this->channels = array();
            foreach ( $channels as $channel_id => $channel_data) {
               $this->channels[$channel_id] = $channel_data;
               $this->channels[$channel_id]['ID'] = $channel_id;
               foreach ( $country_data as $cc => $data) {
                  if ($channel_data['tag_'. $cc] == '') {
                     $this->channels[$channel_id]['tag_'. $cc] = ($channels['default']['tag_' .$cc] != '') ? 
                                                                  $channels['default']['tag_' .$cc] : $data['default_tag'];
                  }
               }
            }
         }

         return $this->channels;         
      }

      function save_channels($channels) {
         if (!is_array($channels)) {
            return;
         }
         $defaults = $channels['default'];
         unset($channels['default']);
         ksort($channels);
         $channels = array('default' => $defaults) + $channels;
         update_option($this->channels_name, $channels);
         $this->channels = $channels;
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
               if (is_array($args[$key])) {
                  $this->Settings[$key] = array_map("trim", $args[$key]);
               } else {
                  $this->Settings[$key] = trim(stripslashes($args[$key]),"\x22\x27");              // Local setting
               }
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
         if (!is_array($this->Settings['asin']))  $this->Settings['asin'] = isset($this->Settings['asin']) ? explode (',', $this->Settings['asin']) : array();
      }

      /*
       * Normally Settings are populated from parsing user arguments, however some
       * external calls do not cause argument parsing (e.g. amazon_query). So this
       * ensures we have the defaults.
       */
      function getSettings() {
         if (!isset($this->Settings)) {
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

      function get_user_options($ID) {
         $options = get_the_author_meta( $this->user_options, $ID );
         return $options;
      }

      function save_user_options($ID, $options ) {
	 update_usermeta( $ID, $this->user_options,  $options );
      }

      function valid_keys() {
         $Settings = $this->getSettings();

         if ( (strlen($Settings['pub_key']) > 10) && (strlen($Settings['priv_key']) > 10))
            return True;
         return False;
      }

      function validate_keys($Settings = NULL) {
        if (Settings === NULL) $Settings = $this->getSettings();

        $result['Valid'] = 0;
        $result['Message'] = 'AWS query failed to get a response - try again later.';
        $request = array('Operation'     => 'ItemLookup', 
                         'ResponseGroup' => 'ItemAttributes',
                         'IdType'        => 'ASIN', 'ItemId' => 'B000H2X2EW');
         $pxml = $this->doQuery($request, $Settings);
         if (isset($pxml['Items'])) {
            $result['Valid'] = 1;
         } else if (isset($pxml['Error'])) {
            $result['Valid'] = 0;
            $result['Message'] = $pxml['Error']['Message'];
         }
         return $result;
      }

/*****************************************************************************************/

      function cache_install() {
         global $wpdb;
         $settings = $this->getOptions();
         if ($settings['cache_enabled']) return False;
         $cache_table = $wpdb->prefix . $this->cache_table;
         $sql = "CREATE TABLE $cache_table (
                 asin varchar(10) NOT NULL,
                 cc varchar(5) NOT NULL,
                 updated datetime NOT NULL,
                 xml longtext NOT NULL,
                 PRIMARY KEY  (asin, cc)
                 );";
         require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
         dbDelta($sql);
         $settings['cache_enabled'] = 1;
         $this->saveOptions($settings);
         return True;
      }

      function cache_remove() {
         global $wpdb;

         $settings = $this->getOptions();
         if (!$settings['cache_enabled']) return False;
         $settings['cache_enabled'] = 0;
         $this->saveOptions($settings);

         $cache_table = $wpdb->prefix . $this->cache_table;
         $sql = "DROP TABLE $cache_table;";
         $wpdb->query($sql);
         return True;
      }

      function cache_empty() {
         global $wpdb;

         $settings = $this->getOptions();
         if (!$settings['cache_enabled']) return False;

         $cache_table = $wpdb->prefix . $this->cache_table;
         $sql = "TRUNCATE TABLE $cache_table;";
         $wpdb->query($sql);
         return True;
      }

      function cache_flush() {
         global $wpdb;
         $settings = $this->getOptions();
         $cache_table = $wpdb->prefix . $this->cache_table;
         $sql = "DELETE FROM $cache_table WHERE updated < DATE_SUB(NOW(),INTERVAL " . $settings['cache_age']. " HOUR);";
         $wpdb->query($sql);
      }

/*****************************************************************************************/
      /// Affiliate Tracking ID Channels
/*****************************************************************************************/

      /*
       * Check the channels in order until we get a match
       *
       * Filter rules:
       *    cat = [category slug|category id]
       *    parent_cat = [category slug| category id]
       *    author = [author name|author id]
       *    tag = [tag name|tag id]
       *    type = [page|post|other = widget|template, etc]
       *    parent = [page/post id]
       *    random = 1-99
       *    empty filter = always use.
       */
      function get_channel($settings = NULL) {
         
         // Need $GLOBALS & Channels

         if ($settings === NULL)
            $settings = $this->getSettings();

         $channels = $this->get_channels(True, True);
         if (isset($settings['in_post']) && $settings['in_post']) {
            $post = $GLOBALS['post'];
         } else {
            $post = '';
         }

         // If manually set always respect.
         if (isset($settings['chan']) && isset($channels[strtolower($settings['chan'])]))
         {
            return $channels[strtolower($settings['chan'])];
         }

         // If post or page, check $this->channel_ids[ID] to see if already processed

         // For each channel (excluding default)

         // For each filter check for match

         // Switch on rule type [cat|parent_cat|author|tag|type]

         // cat
         // if !isset($cats) grab array(cat_id => cat_slug)
         // check cat in array or array_keys

         // parent_cat = recursive list of all category parents
         // if !isset($cats) grab array(cat_id => cat_slug)
         // if !isset($parent_cats) grab array(cat_id => cat_slug)
         // check cat in either cats/parent_cats array or array_keys

         // author
         // if !isset($author) grab array(author_id => author_name)
         // check author in array or array_keys

         // tag
         // if !isset($tags) grab array(tag_id => tag_name)
         // check tag in array or array_keys

         // type
         // if !isset($type) post_type / other
         // check type == type

         // If any rule fails drop to next channel
         // If all rules pass use this channel, save in $this->channel_ids[ID]
         
         // If no specific channel detected then check for author specific IDs via get_the_author_meta
         if (isset($post->post_author) && isset($channels['al_user_'.$post->post_author])) {
            return $channels['al_user_'.$post->post_author];
         }

         // No match found return default channel.
         return $channels['default'];

      }
      
/*****************************************************************************************/
      /// Localise Link Facility
/*****************************************************************************************/

      function get_country($settings = NULL) {

         if ($settings === NULL)
            $settings = $this->getSettings();

         // Pretty arbitrary mapping of domains to Amazon sites, default to 'com' - the 'international' site.
         $country_map = array('uk' => array('uk', 'ie', 'gi', 'gl', 'nl', 'vg', 'cy', 'gb', 'dk'),
                              'fr' => array('fr', 'be', 'bj', 'bf', 'bi', 'cm', 'cf', 'td', 'km', 'cg', 'dj', 'ga', 'gp',
                                            'gf', 'gr', 'pf', 'tf', 'ht', 'ci', 'lu', 'mg', 'ml', 'mq', 'yt', 'mc', 'nc',
                                            'ne', 're', 'sn', 'sc', 'tg', 'vu', 'wf'),
                              'de' => array('de', 'at', 'ch', 'no', 'dn', 'li', 'sk'),
                              'es' => array('es'),
                              'it' => array('it'),
                              'cn' => array('cn'),
                              'ca' => array('ca', 'pm'),
                              'jp' => array('jp')
                             );
                          
         $country = False;

         if ($settings['localise'])
         {
            if (!isset($this->local_country)) {
               $cc = $this->ip2n->get_cc();
               if ($cc === NULL) return $settings['default_cc'];
               $country = 'us';
               foreach ($country_map as $key => $countries) {
                  if (in_array($cc, $countries)) {
                     $country = $key;
                     continue;
                  }
               }
               $this->local_country = $country;
            }

            return $this->local_country;
         }

         return $settings['default_cc'];
      }

      function widget_filter($content) {
         return $this->content_filter($content, TRUE, FALSE);
      }
/*****************************************************************************************/
      /// Searches through the_content for our 'Tag' and replaces it with the lists or links
      /*
       * Performs 2 functions:
       *   1. Process the content and replace the shortcode with amazon links and wishlists
       *   2. Search through the content and record any Amazon ASIN numbers ready to generate a wishlist.
       */
/*****************************************************************************************/
      function content_filter($content, $doLinks=TRUE, $in_post=TRUE) {

         $new_content='';

         $reg_ex = '\[amazon +((?:[^\[\]]*(?:\[[a-z]*\]){0,1})*)\]';
         $split_content = preg_split('/'.$reg_ex.'/', $content, NULL, PREG_SPLIT_DELIM_CAPTURE );

         if ($doLinks) {
            $index = 0;
            while ($index <= count($split_content)) {

               // Non-matching content - just add to output
               if (isset($split_content[$index])) $new_content .= $split_content[$index];
               $index++;
 
               $output='';
               // Matching content - parse arguments
               if (isset($split_content[$index])) {
                  $this->parseArgs($split_content[$index]);
                  if (isset($this->Settings['cat'])) {
                     $this->Settings['in_post'] = $in_post;
                     if ($this->Settings['debug']) {
                        $output .= '<!-- Amazon Link: Version:' . $this->plugin_version . ' - Args: ' . $split_content[$index] . "\n";
                        $output .= print_r($this->Settings, true) . ' -->';
                     }
                     $output .= $this->showRecommendations($this->Settings['cat'], $this->Settings['last']);
                  } else {
                     // Generate Amazon Link
                     if (isset($this->Settings['asin'][0])) {
                        $this->tags = array_merge($this->Settings['asin'], $this->tags);
                     }else{
                        $this->tags = array_merge(array($this->Settings['asin']), $this->tags);
                     }
                     $this->Settings['in_post'] = $in_post;
                     if ($this->Settings['debug']) {
                        $output .= '<!-- Amazon Link: Version:' . $this->plugin_version . ' - Args: ' . $split_content[$index] . "\n";
                        $output .= print_r($this->Settings, true) . ' -->';
                     }
                     $output .= $this->make_links($this->Settings['asin'], $this->Settings['text']);
                  }
                  $new_content .= $output;
               }
               $index++;
            }
         } else {

            $index = 1;
            while ($index <= count($split_content)) {
               $this->parseArgs($split_content[$index]);
               if (isset($this->Settings['asin'][0])){
                  $this->tags = array_merge($this->Settings['asin'], $this->tags);
               }else{
                  $this->tags = array_merge(array($this->Settings['asin']), $this->tags);
               }
               $index += 2;
            }
         }

         return $new_content;
      }

/*****************************************************************************************/
      /// Display Content, Widgets and Pages
/*****************************************************************************************/

      // Top level function to display options
      function show_options_page() {
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
      function show_options() {
         include('include/showOptions.php');
      }

      // User Options Page Hooks
      function show_user_options($user) {
         include('include/showUserOptions.php');
      }
      function update_user_options($user) {
         include('include/updateUserOptions.php');
      }

/*****************************************************************************************/

      function show_default_templates() {
         include('include/showDefaultTemplates.php');
      }

      function show_templates() {
         include('include/showTemplates.php');
      }

      function show_channels() {
         include('include/showChannels.php');
      }

      function show_info() {
         include('include/showInfo.php');
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

      // Page/Post Edit Screen Widget
      function insertForm($post) {
         include('include/insertForm.php');
      }

/*****************************************************************************************/
      /// Helper Functions
/*****************************************************************************************/

      function aws_signed_request($region, $params, $public_key, $private_key)
      {
         return include('include/awsRequest.php');
      }

/*****************************************************************************************/
      /// Do Amazon Link Constructs
/*****************************************************************************************/

      function showRecommendations ($categories='1', $last='30') {
         return include('include/showRecommendations.php');
      }

      function get_local_info($settings = NULL) {

         if ($settings === NULL)
            $settings = $this->getSettings();

         $channel      = $this->get_channel($settings);
         $top_cc       = $this->get_country($settings);
         $country_data = $this->get_country_data();
         $info         = array( 'flag' => $country_data[$top_cc]['flag'], 'cc' => $top_cc, 'rcm' => $country_data[$top_cc]['rcm'], 'mplace_id' => $country_data[$top_cc]['m_id'], 'mplace' => $country_data[$top_cc]['market'], 'tld' => $country_data[$top_cc]['tld'], 'tag' => $channel['tag_' . $top_cc], 'channel' => $channel['ID']);
         return $info;
      }

      function getURL($term, $tld, $tag) {
         $type = substr($term,0,1);
         $arg  = substr($term,2);
         if ($type == 'A')
            $text='http://www.amazon.' . $tld . '/gp/product/'. $arg. '?ie=UTF8&linkCode=as2&camp=1634&creative=6738&tag=' . $tag .'&creativeASIN='. $arg;
         else if ($type == 'S') {
            $text='http://www.amazon.' . $tld . '/mn/search/?_encoding=UTF8&linkCode=ur2&camp=1634&creative=19450&tag=' . $tag. '&field-keywords=' . $arg;
         } else {
            $text='http://www.amazon.' . $tld . '/review/'. $arg. '?_encoding=UTF8&linkCode=ur2&camp=1634&creative=19450&tag=' . $tag. '&field-keywords=' . $arg;
         }
         return $text;
      }

      function itemLookup($asin, $settings = NULL ) {
         global $wpdb;
         $cache_table = $wpdb->prefix . $this->cache_table;

         if ($settings === NULL)
            $settings = $this->getSettings();
if (TIMING) $time_start = microtime(true);
         $result = NULL;
         if ($settings['cache_enabled']) {
            // Check if asin is already in the cache
            $li = $this->get_local_info($settings);
            $cc = $li['cc'];
            $sql = "SELECT xml FROM $cache_table WHERE asin LIKE '$asin' AND cc LIKE '$cc' AND  updated >= DATE_SUB(NOW(),INTERVAL " . $settings['cache_age']. " HOUR)";
            $result = $wpdb->get_row($sql, ARRAY_A);
            if ($result !== NULL) {
               $items = unserialize($result['xml']);
               $items[0]['cached'] = 1;
            }
         }
if (TIMING) {$time_taken = microtime(true)-$time_start;echo "<!--Cache Lookup: $time_taken -->";}

         if ($result === NULL) {

if (TIMING) $time_start = microtime(true);
            // Create query to retrieve the an item
            $request = array('Operation'     => 'ItemLookup',
                             'ResponseGroup' => 'Offers,ItemAttributes,Small,Reviews,Images,SalesRank',
                             'ItemId'        => $asin, 
                             'IdType'        => 'ASIN');

            $pxml = $this->doQuery($request, $settings);
if (TIMING) {$time_taken = microtime(true)-$time_start;echo "<!--AWS Lookup: $time_taken -->";}

            if (($pxml === False) || !isset($pxml['Items']['Item'])) {
               // Failed to return any results
               $items = array(array('ASIN' => $asin, 'found' => 0, 'error' => (isset($pxml['Error']['Message'])? $pxml['Error']['Message'] : 'No Items Found') ));
               return $items;
            } else {
               if (array_key_exists('ASIN', $pxml['Items']['Item'])) {
                  // Returned a single result (not in an array)
if (TIMING) $time_start = microtime(true);
                  $items =array($pxml['Items']['Item']);

                  if ($settings['cache_enabled']) {
                     $sql = "DELETE FROM $cache_table WHERE asin LIKE '$asin' AND cc LIKE '$cc'";
                     $wpdb->query($sql);
                     $data = array( 'asin' => $asin, 'cc' => $cc, 'xml' => serialize($items), updated => current_time('mysql'));
                     $wpdb->insert($cache_table, $data);
if (TIMING) {$time_taken = microtime(true)-$time_start;echo "<!--Cache Save: $time_taken -->";}
                  }
               } else {
                  // Returned several results
                  $items =$pxml['Items']['Item'];
               }
            }
         }

         for ($index=0; $index < count($items); $index++ ) {
            $items[$index]['found'] = 1;
         }
         return $items;

      }

      function doQuery($request, $Settings = NULL)
      {

         if ($Settings === NULL)
            $Settings = $this->getSettings();
         else
            $this->Settings = $Settings;

         $li  = $this->get_local_info($Settings);
         $tld = $li['tld'];

         if (!isset($request['AssociateTag'])) $request['AssociateTag'] = $li['tag'];

         return $this->aws_signed_request($tld, $request, $Settings['pub_key'], $Settings['priv_key']);
      }

      function make_link($asin, $object, $settings = NULL, $local_info = NULL, $search = '', $type = 'product')
      {
         if ($settings === NULL)
            $settings = $this->getSettings();

         if ($local_info === NULL)
            $local_info = $this->get_local_info($settings);

         if (!isset($settings['home_cc'])) $settings['home_cc'] = $settings['default_cc'];
         if ($settings['multi_cc']) {
            // Need to check all locales...
            $sep = '{';
            $term ='';
            $countries = $this->get_country_data();
            foreach ($countries as $country => $country_data) {
               if (isset($asin[$country])) {
                  $term .= $sep. $country .' : \'A-'. $asin[$country].'\'';
               } else if ($settings['search_link']) {
                  $term .= $sep. $country .' : \'S-'. $search .'\'';
               } else {
                  $term .= $sep. $country .' : \'A-'. $asin[$settings['home_cc']].'\'';
               }
               $sep = ',';
            }
         }
         $term .= '}';

         if ($type == 'review') {
            $type = 'R-';
         } else if ($type == 'product') {
            $type = 'A-';
         }

         if (isset($asin[$local_info['cc']])) {

            // User Specified ASIN always use
            $url_term = $type . $asin[$local_info['cc']];

         } else if ($settings['search_link']) {
            $url_term = 'S-'. ($search);
         } else {
            $url_term = $type . $asin[$settings['home_cc']];
         }

         /*
          * Generate a localised/multinational link, wrapped around '$object'
          */
         $TARGET = $settings['new_window'] ? 'target="_blank"' : '';
         $URL    = $this->getURL($url_term, $local_info['tld'], $local_info['tag']);

         if ($settings['multi_cc']) {
           $this->create_popup();
            $text ='<a rel="nofollow" '. $TARGET .' onMouseOut="al_link_out()" href="' . $URL .'" onMouseOver="al_gen_multi('. $this->multi_id . ', ' . $term. ', \''. $local_info['cc']. '\', \''. $local_info['channel'] .'\');">';
            $text .= $object. '</a>';
            $this->multi_id++;
         } else {
            $text='<a rel="nofollow" '. $TARGET .' href="' . $URL .'">' . $object . '</a>';
         }
         return $text;
      }

      function make_links($asins, $link_text, $Settings = NULL)
      {
         global $post;
         
         if ($Settings === NULL)
            $Settings = $this->getSettings();
         $local_info = $this->get_local_info($Settings);

         $output = '';
         /*
          * If a template is specified and exists then populate it
          */
         if (isset($Settings['template'])) {
            $template = strtolower($Settings['template']);
            $Templates = $this->getTemplates();
            if (isset($Templates[$template])) {
               $Settings['template_content'] = $Templates[$template]['Content'];
               $Settings['template_type'] = $Templates[$template]['Type'];
            }
         }
         if (isset($Settings['template_content'])) {
            $details = array();
            unset($Settings['asin']);
            if ($Settings['template_type'] == 'Multi') {
               $sep = ''; $list='';
               foreach ($asins as $i => $asin) {
                  $list .= $sep .(is_array($asin) ? (isset($asin[$local_info['cc']]) ? $asin[$local_info['cc']] : $asin[$Settings['default_cc']]) : $asin);
                  $sep=',';
               }
               $details[] = array_merge( array('asins' => $list),  $Settings);
            } elseif ($Settings['template_type'] == 'No ASIN') {
               $details[] = array_merge(array('found' => 1),  $Settings);
            } elseif (!isset($asins[0])) {
               $details[] = array_merge($Settings, array( 'asin' => $asins, 'live' => 1));
            } else {
               foreach ($asins as $asin) {
                  if (count($asins) > 1) {
                     $details[] = array_merge($Settings, array( 'asin' => $asin, 'live' => 1));
                  } else {
                     $details[] = array_merge($Settings, array( 'asin' => $asin));
                  }
               }
            }

            if (!empty($details))
            {
               foreach ($details as $item) {
                  $output .= $this->search->parse_template($item);
               }
            }

            return $output;
         }

         foreach ($asins as $asin) {

            /*
             * This code required to maintain backward compatibility
             */
            $object = stripslashes($link_text);
            // Do we need to display or link to an image ?
            if (!empty($Settings['image']) || !empty($Settings['thumb'])) {
               $media_ids = $this->search->find_attachments($asin);
               if (!is_wp_error($media_ids)) {
                  $media_id = $media_ids[0]->ID;
               }

               if (!empty($Settings['thumb'])) {
                  if (isset($media_id)) {
                     $thumb = wp_get_attachment_thumb_url($media_id);
                  } elseif (strlen($Settings['thumb']) > 4) {
                     $thumb = $Settings['thumb'];
                  }
               }
               if (!empty($Settings['image'])) {
                  if (isset($media_id)) {
                     $image = wp_get_attachment_url($media_id);
                  } elseif (strlen($Settings['image']) > 4) {
                     $image = $Settings['image'];
                  }
               }
            }

            // If both thumb and image are specified then just insert the image
            if (isset($thumb) && isset($image)) {
               $object = '<a href = "'. $image .'"><img class="'. $Settings['image_class'] .'" src="'. $thumb. '" alt="'. $link_text .'"></a>';
               return $object;
            }

            if (isset($image))
               $object = '<img class="'. $Settings['image_class'] .'" src="'. $image . '" alt="'. $link_text .'">';
            if (isset($thumb))
               $object = '<img class="'. $Settings['image_class'] .'" src="'. $thumb . '" alt="'. $link_text .'">';

            $output .= $this->make_link(array($Settings['default_cc'] =>$asin), $object, $Settings, $local_info);

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
   $li  = $awlfw->get_local_info();
   foreach ($awlfw->Settings['asin'] as $asin) {
      return $awlfw->getURL('A:'.$asin, $li['tld'], $li['tag']);        // Return a URL
   }
}

function amazon_scripts()
{
  global $awlfw;
  $this->generate_multi_script();
}

function amazon_make_links($args)
{
  global $awlfw;
  $awlfw->parseArgs($args);       // Get the default settings
  return $awlfw->make_links($awlfw->Settings['asin'], $awlfw->Settings['text'], $awlfw->Settings);        // Return html
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