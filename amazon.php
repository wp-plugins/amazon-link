<?php

/*
Plugin Name: Amazon Link
Plugin URI: http://www.houseindorset.co.uk/plugins/amazon-link
Description: A plugin that provides a facility to insert Amazon product links directly into your site's Pages, Posts, Widgets and Templates.
Version: 3.1.0-rc5
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
       &nbsp;* get channel
  &nbsp;     * get country [ip2n lookup >cached]
       &nbsp;* get country data
       &nbsp;* return country specific data
      * For each ASIN:
        * Check for local images
        * Make links * 5:
         &nbsp;* get URL (get local info)
         &nbsp;* get local info
        * Fill in template

* If 'multinational' link found when doing the above then:
&nbsp; * Return all channels and user channels(>cached), create the javascript for the multinational popup.

*******************************************************************************************************/

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
      const cache_table   = 'amazon_link_cache';
      const refs_table    = 'amazon_link_refs';
      const ip2n_table    = 'ip2nation';
      const optionName    = 'AmazonLinkOptions';
      const user_options  = 'amazonlinkoptions';
      const templatesName = 'AmazonLinkTemplates';
      const channels_name = 'AmazonLinkChannels';

      var $option_version= 7;
      var $plugin_version= '3.1.0-rc5';
      var $menu_slug     = 'amazon-link-settings';
      var $plugin_home   = 'http://www.houseindorset.co.uk/plugins/amazon-link/';

      var $multi_id      = 0;
      var $shortcodes    = 0;
      var $lookups       = array();
      var $cache_hit     = array();
      var $cache_miss    = array();
      var $aws_hit       = array();
      var $aws_miss      = array();
 
      var $scripts_done  = False;
      var $tags          = array();

/*****************************************************************************************/
      // Constructor for the Plugin

      function AmazonWishlist_For_WordPress() {
         $this->__construct();
      }

      function __construct() {
         $this->URLRoot = plugins_url("", __FILE__);
         $this->icon = plugins_url('images/amazon-icon.png', __FILE__);
         $this->filename  = __FILE__;
         $this->base_name  = plugin_basename( __FILE__ );
         $this->plugin_dir = dirname( $this->base_name );
         $this->extras_dir = WP_PLUGIN_DIR . '/'. $this->plugin_dir. '/extras/';
         $this->ip2n = new AmazonWishlist_ip2nation;

         // Frontend & Backend Related
         register_activation_hook(__FILE__, array($this, 'install'));                // To perform options installation
         register_uninstall_hook(__FILE__, array('AmazonWishlist_For_WordPress', 'uninstall'));               // To perform options removal
         add_action('init', array($this, 'init'));                                   // Load i18n and initialise translatable vars
         add_filter('the_content', array($this, 'content_filter'),15,1);             // Process the content
         add_filter('widget_text', array($this, 'widget_filter'), 16);               // Filter widget text (after the content?)

         // Backend only 
         add_action('admin_menu', array($this, 'admin_menu'));                       // Add options page and page/post edit hooks
      }

/*****************************************************************************************/

      // Functions for the above hooks
 
      // On activation of plugin - used to create default settings
      function install() {
         $opts = $this->getOptions();
         $this->saveOptions($opts);
      }

      // On removal of plugin - used to delete all related database entries
      function uninstall() {
         $opts = $this->getOptions();
         if ($opts['full_uninstall']) {
            $this->cache_remove();
            $this->ip2nation->uninstall();
            $this->delete_options();
         }
      }

      // On wordpress initialisation - load text domain and register styles & scripts
      function init() {

         /* load localisation  */
         load_plugin_textdomain('amazon-link', $this->plugin_dir . '/i18n', $this->plugin_dir . '/i18n');

         // Initialise dependent classes
         $this->form = new AmazonWishlist_Options;
         $this->form->init($this);                     // Need to register form styles
         $this->search = new AmazonLinkSearch;
         $this->search->init($this);                   // Need to register scripts & ajax callbacks
         $this->ip2n->init($this);                     // ip2nation needed on Frontend

         // Register our styles and scripts
         $script = plugins_url("amazon.js", __FILE__);
         $edit_script = plugins_url("postedit.js", __FILE__);
         $admin_script = plugins_url("include/amazon-admin.js", __FILE__);
         $multi_script = plugins_url("include/amazon-multi.js", __FILE__);
         // Allow the user to override our default styles. 
         if (file_exists(dirname (__FILE__).'/user_styles.css')) {
            $stylesheet = plugins_url("user_styles.css", __FILE__); 
         } else {
            $stylesheet = plugins_url("Amazon.css", __FILE__);
         }

         wp_register_style ('amazon-link-style', $stylesheet, false, $this->plugin_version);
         wp_register_script('amazon-link-script', $script, false, $this->plugin_version);
         wp_register_script('amazon-link-multi-script', $multi_script, false, $this->plugin_version);
         wp_register_script('amazon-link-edit-script', $edit_script, array('jquery', 'amazon-link-search'), $this->plugin_version);
         wp_register_script('amazon-link-admin-script', $admin_script, false, $this->plugin_version);

         // Add base stylesheet
         add_action('wp_enqueue_scripts', array($this, 'amazon_styles'));

         // Add default url generator - low priority
         add_filter('amazon_link_url', array($this, 'get_url'), 20, 6);
         add_filter('amazon_link_format_list', array($this, 'format_list'), 10, 2);

         add_filter('amazon_link_save_channel_rule', array($this, 'create_channel_rules'), 10,4);

         /* Set up the default channel filters - priority determines order */
         add_filter('amazon_link_get_channel' , array($this, 'get_channel_by_rules'), 10,5);
         add_filter('amazon_link_get_channel' , array($this, 'get_channel_by_setting'), 12,5);
         add_filter('amazon_link_get_channel' , array($this, 'get_channel_by_user'), 14,5);

         // Call any user hooks - passing the current plugin Settings and the Amazon Link Instance.
         do_action('amazon_link_init', $this->getSettings(), $this);
      }

      // If in admin section then register options page and required styles & metaboxes
      function admin_menu() {

         $submenus = $this->get_menus();

         // Add plugin options page, with load hook to bring in meta boxes and scripts and styles
         $this->menu = add_menu_page(__('Amazon Link Options', 'amazon-link'), __('Amazon Link', 'amazon-link'), 'manage_options',  $this->menu_slug, NULL, $this->icon, 81.375);
 
         foreach ($submenus as $slug => $menu) {
            $ID= add_submenu_page($this->menu_slug, $menu['Title'], $menu['Label'], $menu['Capability'],  $slug, array($this, 'show_settings_page'));
            $this->pages[$ID] = $menu;
            add_action('load-'.$ID, array(&$this, 'load_settings_page'));
            add_action( 'admin_print_styles-' . $ID, array($this,'amazon_admin_styles') );
            add_action( 'admin_print_scripts-' . $ID, array($this,'amazon_admin_scripts') );

            if (isset($menu['Scripts'])) {
               foreach ($menu['Scripts'] as $script)
                  add_action( 'admin_print_scripts-' . $ID, $script );

            }
            if (isset($menu['Styles'])) {
               add_action( 'admin_print_styles-' . $ID, $menu['Styles'] );
            }
         }

         // Add support for Post edit metabox, this requires our styles and post edit AJAX scripts.
         $post_types = get_post_types();
         foreach ( $post_types as $post_type ) {
            add_meta_box('amazonLinkID', 'Add Amazon Link', array($this,'insertForm'), $post_type, 'normal');
         }

         add_action( "admin_print_scripts-post.php", array($this,'edit_scripts') );
         add_action( "admin_print_scripts-post-new.php", array($this,'edit_scripts') );
         add_action( "admin_print_styles-post-new.php", array($this,'amazon_admin_styles') );
         add_action( "admin_print_styles-post.php", array($this,'amazon_admin_styles') );

         add_filter('plugin_row_meta', array($this, 'register_plugin_links'),10,2);  // Add extra links to plugins page
         add_action('show_user_profile', array($this, 'show_user_options') );        // Display User Options
         add_action('edit_user_profile', array($this, 'show_user_options') );        // Display User Options
         add_action('personal_options_update', array($this, 'update_user_options')); // Update User Options
         add_action('edit_user_profile_update', array($this, 'update_user_options'));// Update User Options

      }

      // Hooks required to bring up options page with meta boxes:
      function load_settings_page() {

         $screen = get_current_screen();

         if (!isset($this->pages[$screen->id])) return;

         $page = $this->pages[$screen->id];
         $slug = $page['Slug'];

         add_filter('screen_layout_columns', array(&$this, 'admin_columns'), 10, 2);

         wp_enqueue_script('common');
         wp_enqueue_script('wp-lists');
         wp_enqueue_script('postbox');

         if (isset($page['Metaboxes'])) {
            foreach($page['Metaboxes'] as $id => $data) {
               add_meta_box( $id, $data['Title'], $data['Callback'], $screen->id, $data['Context'], $data['Priority'], $this);
            }
         }

         add_meta_box( 'alInfo', __( 'About', 'amazon-link' ), array (&$this, 'show_info' ), $screen->id, 'side', 'core' );

         /* Help TABS only supported after version 3.3 */
         if (!method_exists( $screen, 'add_help_tab' )) {
            return;
         }

         // Add Contextual Help
         if (isset($page['Help'])) {
            $tabs = include( $page['Help']);
            foreach ($tabs as $tab) $screen->add_help_tab( $tab );
         }

         $screen->set_help_sidebar('<p><b>'. __('For more information:', 'amazon-link'). '</b></p>' .
                                   '<p><a target="_blank" href="'. $this->plugin_home . '">' . __('Plugin Home Page','amazon-link') . '</a></p>' .
                                   '<p><a target="_blank" href="'. $this->plugin_home . 'faq/">' . __('Plugin FAQ','amazon-link') . '</a></p>' .
                                   '<p><a target="_blank" title= "Guide on how to sign up for the various Amazon Programs" href="'. $this->plugin_home . 'getting-started">' . __('Getting Started','amazon-link') . '</a></p>' .
                                   '<p><a target="_blank" href="'. $this->plugin_home . 'faq/#channels">' . __('Channels Help','amazon-link') . '</a></p>' .
                                   '<p><a target="_blank" href="'. $this->plugin_home . 'faq/#templates">' . __('Template Help','amazon-link') . '</a></p>');

      }

      function admin_columns($columns, $id) {
         if (isset($this->pages[$id])) {
	    $columns[$id] = 2;
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
         wp_localize_script('amazon-link-edit-script', 'AmazonLinkData', $this->get_country_data());
      }

      function footer_scripts() {
         $settings     = $this->getSettings();

         wp_print_scripts('amazon-link-script');
         echo '<span id="al_popup" onmouseover="al_div_in()" onmouseout="al_div_out()"></span>';
         wp_localize_script('amazon-link-multi-script', 
                            'AmazonLinkMulti',
                            array('country_data' => $this->get_country_data(), 'channels' => $this->get_channels(True, True), 'target' => ($settings['new_window'] ? 'target="_blank"' : ''))
                           );
         wp_print_scripts('amazon-link-multi-script');
         remove_action('wp_print_footer_scripts', array($this, 'footer_scripts'));
      }


      function register_plugin_links($links, $file) {
         if ($file == $this->base_name) {
            foreach ($this->pages as $page => $data) {
               $links[] = '<a href="admin.php?page=' . $data['Slug'].'">' . $data['Label'] . '</a>';
            }
         }
         return $links;
      }


/*****************************************************************************************/
      // Various Arrays to Control the Plugin
/*****************************************************************************************/

      function get_keywords() {

         if (!isset($this->keywords)) {
            $this->keywords = array(
             'link_open'    => array( 'Description' => __('Create an Amazon link to a product with user defined content, of the form %LINK_OPEN%My Content%LINK_CLOSE%', 'amazon-link'), 'Link' => 1 ),
             'rlink_open'   => array( 'Description' => __('Create an Amazon link to product reviews with user defined content, of the form %RLINK_OPEN%My Content%LINK_CLOSE%', 'amazon-link'), 'Link' => 1),
             'slink_open'   => array( 'Description' => __('Create an Amazon link to a search page with user defined content, of the form %SLINK_OPEN%My Content%LINK_CLOSE%', 'amazon-link'), 'Link' => 1),
             'link_close'   => array( 'Description' => __('Must follow a LINK_OPEN (translates to "</a>").', 'amazon-link'), 'Default' => '</a>'),

             'asin'         => array( 'Description' => __('Item\'s unique ASIN', 'amazon-link'), 'Live' => '1', 'Group' => 'ItemAttributes', 'Default' => '0',
                                      'Position' => array(array('ASIN'))),
             'asins'        => array( 'Description' => __('Comma seperated list of ASINs', 'amazon-link'), 'Default' => '0'),
             'product'      => array( 'Description' => __('Item\'s Product Group', 'amazon-link'), 'Live' => '1', 'Group' => 'ItemAttributes', 'Default' => '-',
                                      'Position' => array(array('ItemAttributes','ProductGroup'))),
             'binding'      => array( 'Description' => __('Item\'s Format (Paperbook, MP3 download, etc.)', 'amazon-link'), 'Live' => '1', 'Group' => 'ItemAttributes', 'Default' => ' ',
                                      'Position' => array(array('ItemAttributes','Binding'))),
             'features'     => array( 'Description' => __('Item\'s Features', 'amazon-link'), 'Live' => '1', 'Group' => 'ItemAttributes', 'Filter' => 'amazon_link_format_list', 'Default' => ' ',
                                      'Position' => array(array('ItemAttributes','Feature'))),
             'title'        => array( 'Description' => __('Item\'s Title', 'amazon-link'), 'Live' => '1', 'Group' => 'ItemAttributes', 'Default' => ' ',
                                      'Position' => array(array('ItemAttributes','Title'))),
             'artist'       => array( 'Description' => __('Item\'s Author, Artist or Creator', 'amazon-link'), 'Live' => '1', 'Group' => 'ItemAttributes', 'Default' => '-',
                                      'Position' => array(array('ItemAttributes','Artist'),
                                                          array('ItemAttributes','Author'),
                                                          array('ItemAttributes','Director'),
                                                          array('ItemAttributes','Creator'),
                                                          array('ItemAttributes','Brand'))),
             'manufacturer' => array( 'Description' => __('Item\'s Manufacturer', 'amazon-link'), 'Live' => '1', 'Group' => 'ItemAttributes', 'Default' => '-',
                                      'Position' => array(array('ItemAttributes','Manufacturer'),
                                                          array('ItemAttributes','Brand'))),
             'thumb'        => array( 'Description' => __('URL to Thumbnail Image', 'amazon-link'), 'Live' => '1', 'Group' => 'Images', 'Default' => 'http://images-eu.amazon.com/images/G/02/misc/no-img-lg-uk.gif',
                                      'Position' => array(array('MediumImage','URL'))),
             'image'        => array( 'Description' => __('URL to Full size Image', 'amazon-link'), 'Live' => '1', 'Group' => 'Images', 'Default' => 'http://images-eu.amazon.com/images/G/02/misc/no-img-lg-uk.gif',
                                      'Position' => array(array('LargeImage','URL'),
                                                          array('MediumImage','URL'))),
             'image_class'  => array( 'Description' => __('Class of Image as defined in settings', 'amazon-link')),
             'search_text_s'=> array( 'Description' => __('Search Link Text (Escaped) from Settings Page', 'amazon-link')),
             'search_text'  => array( 'Description' => __('Search Link Text from Settings Page', 'amazon-link')),
             'url'          => array( 'Description' => __('The URL returned from the Item Search (not localised!)', 'amazon-link'), 'Live' => '1', 'Group' => 'Small', 'Default' => ' ',
                                      'Position' => array(array('DetailPageURL'))),
             'rank'         => array( 'Description' => __('Amazon Rank', 'amazon-link'), 'Live' => '1', 'Group' => 'SalesRank', 'Default' => '-',
                                      'Position' => array(array('SalesRank'))),
             'rating'       => array( 'Description' => __('Numeric User Rating - (No longer Available)', 'amazon-link'), 'Live' => '1', 'Default' => '-',
                                      'Position' => array(array('CustomerReviews','AverageRating'))),
             'offer_price'  => array( 'Description' => __('Best Offer Price of Item', 'amazon-link'), 'Live' => '1', 'Group' => 'Offers', 'Default' => '-',
                                      'Position' => array(array('Offers','Offer','OfferListing','Price','FormattedPrice'),
                                                          array('OfferSummary','LowestNewPrice','FormattedPrice'),
                                                          array('OfferSummary','LowestUsedPrice','FormattedPrice'))),
             'list_price'   => array( 'Description' => __('List Price of Item', 'amazon-link'), 'Live' => '1', 'Group' => 'ItemAttributes', 'Default' => '-',
                                      'Position' => array(array('ItemAttributes','ListPrice','FormattedPrice'))),
             'price'        => array( 'Description' => __('Price of Item (Combination of Offer then List Price)', 'amazon-link'), 'Live' => '1', 'Group' => 'Offers', 'Default' => '-',
                                      'Position' => array(array('Offers','Offer','OfferListing','Price','FormattedPrice'),
                                                          array('OfferSummary','LowestNewPrice','FormattedPrice'),
                                                          array('OfferSummary','LowestUsedPrice','FormattedPrice'),
                                                          array('ItemAttributes','ListPrice','FormattedPrice'))),

             'text'         => array( 'Description' => __('User Defined Text string', 'amazon-link'), 'User' => '1'),
             'text1'        => array( 'Description' => __('User Defined Text string', 'amazon-link'), 'User' => '1'),
             'text2'        => array( 'Description' => __('User Defined Text string', 'amazon-link'), 'User' => '1'),
             'text3'        => array( 'Description' => __('User Defined Text string', 'amazon-link'), 'User' => '1'),
             'text4'        => array( 'Description' => __('User Defined Text string', 'amazon-link'), 'User' => '1'),
             'pub_key'      => array( 'Description' => __('Amazon Web Service Public Access Key ID', 'amazon-link')),
             'mplace'       => array( 'Description' => __('Localised Amazon Marketplace Code (US, GB, etc.)', 'amazon-link'), 'Default' => array('uk' => 'GB', 'us' => 'US', 'de' => 'DE', 'es' => 'ES', 'fr' => 'FR', 'jp' => 'JP', 'it' => 'IT', 'cn' => 'CN', 'ca' => 'CA')),
             'mplace_id'    => array( 'Description' => __('Localised Numeric Amazon Marketplace Code (2=uk, 8=fr, etc.)', 'amazon-link'), 'Default' => array('uk' => '2', 'us' => '1', 'de' => '3', 'es' => '30', 'fr' => '8', 'jp' => '9', 'it' => '29', 'cn' => '28', 'ca' => '15')),
             'rcm'          => array( 'Description' => __('Localised RCM site host domain (rcm.amazon.com, rcm-uk.amazon.co.uk, etc.)', 'amazon-link'), 'Default' => array('uk' => 'rcm-uk.amazon.co.uk', 'us' => 'rcm.amazon.com', 'de' => 'rcm-de.amazon.de', 'es' => 'rcm-es.amazon.es', 'fr' => 'rcm-fr.amazon.fr', 'jp' => 'rcm-jp.amazon.co.jp', 'it' => 'rcm-it.amazon.it', 'cn' => 'rcm-cn.amazon.cn', 'ca' => 'rcm-ca.amazon.ca')),
             'buy_button'   => array( 'Description' => __('Localised Buy from Amazon Button URL', 'amazon-link'), 'Default' => array('uk' => 'https://images-na.ssl-images-amazon.com/images/G/02/buttons/buy-from-tan.gif', 'us' => 'https://images-na.ssl-images-amazon.com/images/G/01/buttons/buy-from-tan.gif', 'de' => 'https://images-na.ssl-images-amazon.com/images/G/03/buttons/buy-from-tan.gif', 'es' => 'https://images-na.ssl-images-amazon.com/images/G/30/buttons/buy-from-tan.gif', 'fr' => 'https://images-na.ssl-images-amazon.com/images/G/08/buttons/buy-from-tan.gif', 'jp' => 'https://images-na.ssl-images-amazon.com/images/G/09/buttons/buy-from-tan.gif', 'it' => 'https://images-na.ssl-images-amazon.com/images/G/29/buttons/buy-from-tan.gif', 'cn' => 'https://images-na.ssl-images-amazon.com/images/G/28/buttons/buy-from-tan.gif', 'ca' => 'https://images-na.ssl-images-amazon.com/images/G/15/buttons/buy-from-tan.gif')),

             'tag'          => array( 'Description' => __('Localised Amazon Associate Tag', 'amazon-link')),
             'cc'           => array( 'Description' => __('Localised Country Code (us, uk, etc.)', 'amazon-link')),
             'flag'         => array( 'Description' => __('Localised Country Flag Image URL', 'amazon-link')),
             'tld'          => array( 'Description' => __('Localised Top Level Domain (.com, .co.uk, etc.)', 'amazon-link')),

             'downloaded'   => array( 'Description' => __('1 if Images are in the local WordPress media library', 'amazon-link'), 'Image' => 1, 'Calculated' => '1'),
             'found'        => array( 'Description' => __('1 if product was found doing a live data request (also 1 if live not enabled).', 'amazon-link'), 'Calculated' => '1', 'Default' => 1),
             'timestamp'    => array( 'Description' => __('Date and time of when the Amazon product data was retrieved from Amazon.', 'amazon-link'), 'Calculated' => 1, 'Default' => '0')
            );

            $this->keywords = apply_filters('amazon_link_keywords', $this->keywords, $this);
         }
         return $this->keywords;
      }

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
            $this->country_data = array('uk' => array('country_name' => __('United Kingdom', 'amazon-link'), 'cc' => 'uk', 'lang' => 'en', 'flag' => $this->URLRoot. '/'. 'images/flag_uk.gif', 'tld' => 'co.uk', 'site' => 'https://affiliate-program.amazon.co.uk', 'default_tag' => 'livpauls-21'),
                                        'us' => array('country_name' => __('United States', 'amazon-link'), 'cc' => 'us', 'lang' => 'en', 'flag' => $this->URLRoot. '/'. 'images/flag_us.gif', 'tld' => 'com', 'site' => 'https://affiliate-program.amazon.com', 'default_tag' => 'lipawe-20'),
                                        'de' => array('country_name' => __('Germany', 'amazon-link'), 'cc' => 'de', 'lang' => 'de', 'flag' => $this->URLRoot. '/'. 'images/flag_de.gif', 'tld' => 'de', 'site' => 'https://partnernet.amazon.de', 'default_tag' => 'lipas03-21'),
                                        'es' => array('country_name' => __('Spain', 'amazon-link'), 'cc' => 'es', 'lang' => 'es', 'flag' => $this->URLRoot. '/'. 'images/flag_es.gif', 'tld' => 'es', 'site' => 'https://afiliados.amazon.es', 'default_tag' => 'livpauls0b-21'),
                                        'fr' => array('country_name' => __('France', 'amazon-link'), 'cc' => 'fr', 'lang' => 'fr', 'flag' => $this->URLRoot. '/'. 'images/flag_fr.gif', 'tld' => 'fr', 'site' => 'https://partenaires.amazon.fr', 'default_tag' => 'lipas-21'),
                                        'jp' => array('country_name' => __('Japan', 'amazon-link'), 'cc' => 'jp', 'lang' => 'ja', 'flag' => $this->URLRoot. '/'. 'images/flag_jp.gif', 'tld' => 'jp', 'site' => 'https://affiliate.amazon.co.jp', 'default_tag' => 'livpaul21-22'),
                                        'it' => array('country_name' => __('Italy', 'amazon-link'), 'cc' => 'it', 'lang' => 'it', 'flag' => $this->URLRoot. '/'. 'images/flag_it.gif', 'tld' => 'it', 'site' => 'https://programma-affiliazione.amazon.it', 'default_tag' => 'livpaul-21'),
                                        'cn' => array('country_name' => __('China', 'amazon-link'), 'cc' => 'cn', 'lang' => 'zh-CHS', 'flag' => $this->URLRoot. '/'. 'images/flag_cn.gif', 'tld' => 'cn', 'site' => 'https://associates.amazon.cn', 'default_tag' => 'livpaul-23'),
                                        'ca' => array('country_name' => __('Canada', 'amazon-link'), 'cc' => 'ca', 'lang' => 'en', 'flag' => $this->URLRoot. '/'. 'images/flag_ca.gif', 'tld' => 'ca', 'site' => 'https://associates.amazon.ca', 'default_tag' => 'lipas-20'));
         }
         return $this->country_data;
      }

      function get_default_templates() {

         if (!isset($this->default_templates)) {
            // Default templates
            include('include/defaultTemplates.php');
            $this->default_templates= apply_filters('amazon_link_default_templates', $this->default_templates);
         }
         return $this->default_templates;
      }

      function get_option_list() {
     
         if (!isset($this->option_list)) {

            $this->option_list = array(

            /* Hidden Options - not saved in Settings */

            'nonce' => array ( 'Type' => 'nonce', 'Value' => 'update-AmazonLink-options' ),
            'cat' => array ( 'Type' => 'hidden' ),
            'last' => array ( 'Type' => 'hidden' ),
            'template' => array(  'Type' => 'hidden' ),
            'chan' => array(  'Type' => 'hidden' ),

            /* Options that change how the items are displayed */
            'hd1s' => array ( 'Type' => 'section', 'Value' => __('Display Options', 'amazon-link'), 'Title_Class' => 'al_section_head', 'Description' => __('Change the default appearance and behaviour of the Links.','amazon-link'), 'Section_Class' => 'al_subhead1'),

            'text' => array( 'Name' => __('Link Text', 'amazon-link'), 'Description' => __('Default text to display if none specified', 'amazon-link'), 'Default' => 'www.amazon.co.uk', 'Type' => 'text', 'Size' => '40', 'Class' => 'al_border' ),
            'image_class' => array ( 'Name' => __('Image Class', 'amazon-link'), 'Description' => __('Style Sheet Class of image thumbnails', 'amazon-link'), 'Default' => 'wishlist_image', 'Type' => 'text', 'Size' => '40', 'Class' => 'alternate al_border' ),
            'wishlist_template' => array (  'Default' => 'Wishlist', 'Name' => __('Wishlist Template', 'amazon-link') , 'Description' => __('Default template to use for the wishlist <em>* <a href="#aws_notes" title="AWS Access keys required for full functionality">AWS</a> *</em>', 'amazon-link'), 'Type' => 'selection', 'Class' => 'al_border'  ),
            'wishlist_items' => array (  'Name' => __('Wishlist Length', 'amazon-link'), 'Description' => __('Maximum number of items to display in a wishlist (Amazon only returns a maximum of 5, for the \'Similar\' type of list) <em>* <a href="#aws_notes" title="AWS Access keys required for full functionality">AWS</a> *</em>', 'amazon-link'), 'Default' => 5, 'Type' => 'text', 'Class' => 'alternate al_border' ),
            'wishlist_type' => array (  'Name' => __('Wishlist Type', 'amazon-link'), 'Description' => __('Default type of wishlist to display, \'Similar\' shows items similar to the ones found, \'Random\' shows a random selection of the ones found <em>* <a href="#aws_notes" title="AWS Access keys required for full functionality">AWS</a> *</em>', 'amazon-link'), 'Default' => 'Similar', 'Options' => array('Similar', 'Random', 'Multi'), 'Type' => 'selection', 'Class' => 'al_border'  ),
            'new_window' => array('Name' => __('New Window Link', 'amazon-link'), 'Description' => __('When link is clicked on, open it in a new browser window', 'amazon-link'), 'Default' => '0', 'Type' => 'checkbox', 'Class' => 'alternate al_border' ),
            'link_title' => array( 'Name' => __('Link Title Text', 'amazon-link'), 'Description' => __('The text to put in the link \'title\' attribute, can use the same keywords as in the Templates (e.g. %TITLE% %ARTIST%), leave blank to not have a link title.', 'amazon-link'), 'Default' => '', 'Type' => 'text', 'Size' => '40', 'Class' => '' ),

            'hd1e' => array ( 'Type' => 'end'),

             /* Options that control localisation */
            'hd2s' => array ( 'Type' => 'section', 'Value' => __('Localisation Options', 'amazon-link'), 'Title_Class' => 'al_section_head', 'Description' => __('Control the localisation of data displayed and links created.','amazon-link'), 'Section_Class' => 'al_subhead1'),
            'ip2n_message' => array( 'Type' => 'title', 'Title_Class' => 'al_para', 'Class' => 'al_pad al_border'),
            'default_cc' => array( 'Name' => __('Default Country', 'amazon-link'), 'Hint' => __('The Amazon Affiliate Tags should be entered in the \'Afiliate IDs\' settings page.', 'amazon-link'),'Description' => __('Which country\'s Amazon site to use by default', 'amazon-link'), 'Default' => 'uk', 'Type' => 'selection', 'Class' => 'alternate al_border' ),
            'localise' => array('Name' => __('Localise Amazon Link', 'amazon-link'), 'Description' => __('Make the link point to the user\'s local Amazon website, (you must have ip2nation installed for this to work).', 'amazon-link'), 'Default' => '1', 'Type' => 'checkbox', 'Class' => 'al_border' ),
            'global_over' => array('Name' => __('Global Defaults', 'amazon-link'), 'Description' => __('Default values in the shortcode "title=xxxx" affect all locales, if not set only override the default locale.', 'amazon-link'), 'Default' => '1', 'Type' => 'checkbox', 'Class' => 'alternate al_border' ),
            'search_link' => array('Name' => __('Create Search Links', 'amazon-link'), 'Description' => __('Generate links to search for the items by "Artist Title" for non local links, rather than direct links to the product by ASIN.', 'amazon-link'), 'Default' => '0', 'Type' => 'checkbox', 'Class' => 'al_border' ),
            'search_text' => array( 'Name' => __('Default Search String', 'amazon-link'), 'Description' => __('Default items to search for with "Search Links", uses the same system as the Templates below.', 'amazon-link'), 'Default' => '%ARTIST% | %TITLE%', 'Type' => 'text', 'Size' => '40', 'Class' => 'alternate al_border' ),
            'search_text_s'=> array( 'Default' => '%ARTIST%S# | %TITLE%S#', 'Type' => 'calculated' ),
            'multi_cc' => array('Name' => __('Multinational Link', 'amazon-link'), 'Description' => __('Insert links to all other Amazon sites after primary link.', 'amazon-link'), 'Default' => '1', 'Type' => 'checkbox', 'Class' => 'al_border'),

            'hd2e' => array ( 'Type' => 'end'),

            /* Options related to the Amazon backend */
            'hd3s' => array ( 'Type' => 'section', 'Id' => 'aws_notes', 'Value' => __('Amazon Associate Information','amazon-link'), 'Title_Class' => 'al_section_head', 'Description' => __('The AWS Keys are required for some of the features of the plugin to work (The ones marked with AWS above), visit <a href="http://aws.amazon.com/">Amazon Web Services</a> to sign up to get your own keys.', 'amazon-link'), 'Section_Class' => 'al_subhead1'),

            'pub_key' => array( 'Name' => __('AWS Public Key', 'amazon-link'), 'Description' => __('Access Key ID provided by your AWS Account, found under Security Credentials/Access Keys of your AWS account', 'amazon-link'), 'Default' => '', 'Type' => 'text', 'Size' => '40', 'Class' => '' ),
            'priv_key' => array( 'Name' => __('AWS Private key', 'amazon-link'), 'Description' => __('Secret Access Key ID provided by your AWS Account.', 'amazon-link'), 'Default' => '', 'Type' => 'text', 'Size' => '40', 'Class' => 'alternate' ),
            'aws_valid' => array ( 'Type' => 'checkbox', 'Read_Only' => 1, 'Name' => 'AWS Keys Validated', 'Default' => '0', 'Class' => 'al_border'),
            'live' => array ( 'Name' => __('Live Data', 'amazon-link'), 'Description' => __('When creating Amazon links, use live data from the Amazon site, otherwise populate the shortcode with static information. <em>* <a href="#aws_notes" title="AWS Access keys required for full functionality">AWS</a> *</em>', 'amazon-link'), 'Default' => '1', 'Type' => 'checkbox', 'Class' => 'al_border' ),
            'condition' => array ( 'Name' => __('Condition', 'amazon-link'), 'Description' => __('By default Amazon only returns Offers for \'New\' items, change this to return items of a different condition.', 'amazon-link'), 'Default' => '', 'Type' => 'selection', 
                                   'Options' => array( '' => array('Name' => 'Use Default'), 'All' => array ('Name' => 'All'), 'New' => array('Name' => 'New'),'Used' => array('Name' => 'Used'),'Collectible' => array('Name' => 'Collectible'),'Refurbished' => array('Name' => 'Refurbished')),
                                   'Class' => 'alternate al_border' ),
            'prefetch' => array ( 'Name' => __('Prefetch Data', 'amazon-link'), 'Description' => __('For every product link, prefetch the data from the Amazon Site - use of the cache essential for this option! <em>* <a href="#aws_notes" title="AWS Access keys required for full functionality">AWS</a> *</em>', 'amazon-link'), 'Default' => '0', 'Type' => 'checkbox', 'Class' => '' ),
            'hd3e' => array ( 'Type' => 'end'),

            'hd4s' => array ( 'Type' => 'section', 'Value' => __('Amazon Data Cache','amazon-link'), 'Title_Class' => 'al_section_head', 'Description' => __('Improve page performance by caching Amazon product data.','amazon-link'), 'Section_Class' => 'al_subhead1'),
            'cache_age' => array ( 'Name' => __('Cache Data Age', 'amazon-link'), 'Description' => __('Max age in hours of the data held in the Amazon Link Cache', 'amazon-link'), 'Type' => 'text', 'Default' => '48', 'Class' => 'al_border'),
            'cache_enabled' => array ( 'Type' => 'backend', 'Default' => '0'),
            'cache_c' => array( 'Type' => 'buttons', 'Buttons' => array( __('Enable Cache', 'amazon-link' ) => array( 'Hint' => __('Install the sql database table to cache data retrieved from Amazon.', 'amazon-link'), 'Class' => 'button-secondary', 'Action' => 'AmazonLinkAction'),
                                                     __('Disable Cache', 'amazon-link' ) => array( 'Hint' => __('Remove the Amazon Link cache database table.', 'amazon-link'),'Class' => 'button-secondary', 'Action' => 'AmazonLinkAction'),
                                                     __('Flush Cache', 'amazon-link' ) => array( 'Hint' => __('Delete all data in the Amazon Link cache.', 'amazon-link'),'Class' => 'button-secondary', 'Action' => 'AmazonLinkAction'),
                                                                        )),
            'hd4e' => array ( 'Type' => 'end'),

            'hd5s' => array ( 'Type' => 'section', 'Value' => __('Advanced Options','amazon-link'), 'Title_Class' => 'al_section_head', 'Description' => __('Further options for debugging and Amazon Extras.','amazon-link'), 'Section_Class' => 'al_subhead1'),
            'template_asins' => array( 'Name' => __('Template ASINs', 'amazon-link'), 'Description' => __('ASIN values to use when previewing the templates in the templates manager.', 'amazon-link'), 'Default' => '0893817449,0500410607,050054199X,0500286426,0893818755,050054333X,0500543178,0945506562', 'Type' => 'text', 'Size' => '40', 'Class' => 'al_border' ),
            'debug' => array( 'Name' => __('Debug Output', 'amazon-link'), 'Description' => __('Adds hidden debug output to the page source to aid debugging. <b>Do not enable on live sites</b>.', 'amazon-link'), 'Default' => '0', 'Type' => 'checkbox', 'Size' => '40', 'Class' => 'alternate al_border' ),
            'full_uninstall' => array( 'Name' => __('Purge on Uninstall', 'amazon-link'), 'Description' => __('On uninstalling the plugin remove all Settings, Templates, Associate Tracking IDs, Cache Data & ip2nation Data <b>Use when removing the plugin for good</b>.', 'amazon-link'), 'Default' => '0', 'Type' => 'checkbox', 'Size' => '40', 'Class' => '' ),
            'hd5e' => array ( 'Type' => 'end')
             );

            $country_data = $this->get_country_data();
            // Populate Country related options
            foreach ($country_data as $cc => $data) {
               $this->option_list['default_cc']['Options'][$cc]['Name'] = $data['country_name'];
            }

            // Populate the hidden Template Keywords
            foreach ($this->get_keywords() as $keyword => $details) {
               if (!isset($this->option_list[$keyword]))
                  $this->option_list[$keyword] = array( 'Type' => 'hidden' );
            }
            $this->option_list = apply_filters('amazon_link_option_list', $this->option_list);

            $this->option_list['button'] = array( 'Type' => 'buttons', 'Buttons' => array( __('Update Options', 'amazon-link' ) => array( 'Class' => 'button-primary', 'Action' => 'AmazonLinkAction')));

         }
         return $this->option_list;
      }

      function get_user_option_list() {
        $option_list = array( 
            'title'       => array ( 'Type' => 'subhead', 'Value' => __('Amazon Link Affiliate IDs', 'amazon-link'), 'Description' => __('Valid affiliate IDs from all Amazon locales can be obtained from the relevant Amazon sites: ', 'amazon-link'), 'Class' => 'al_pad al_border'),
         );

         $country_data = $this->get_country_data();
         // Populate Country related options
         foreach ($country_data as $cc => $data) {
            $option_list ['tag_' . $cc] = array('Type' => 'text', 'Default' => '',
                                                'Name' => '<img style="height:14px;" src="'. $data['flag'] . '"> ' . $data['country_name'],
                                                'Hint' => sprintf(__('Enter your affiliate tag for %1$s.', 'amazon-link'), $data['country_name'] ));
            $option_list ['title']['Description'] .= '<a href="' . $data['site']. '">'. $data['country_name']. '</a>, ';
         }
         $option_list = apply_filters('amazon_link_user_option_list', $option_list, $this);
         return $option_list;
      }

      function get_menus() {
         $menus = array('amazon-link-settings'   => array( 'Slug' => 'amazon-link-settings', 
                                                           'Help' => 'help/settings.php',
                                                           'Description' => __('Use this page to update the main Amazon Link settings to control the basic behaviour of the plugin, the appearance of the links and control the additional features such as localisation and the data cache. Use the Contextual Help tab above for more information about the settings.','amazon-link'),
                                                           'Title' => __('Amazon Link Settings', 'amazon-link'), 
                                                           'Label' =>__('Settings', 'amazon-link'), 
                                                           'Capability' => 'manage_options',
                                                           'Metaboxes' => array( 'alOptions' => array( 'Title' => __( 'Options', 'amazon-link' ),
                                                                                                       'Callback' => array (&$this, 'show_options' ), 
                                                                                                       'Context' => 'normal', 
                                                                                                       'Priority' => 'core'))
                                                           ),
                        'amazon-link-channels'   => array( 'Slug' => 'amazon-link-channels', 
                                                           'Help' => 'help/channels.php',
                                                           'Description' => __('If you have joined the Amazon Affiliate Program then on this page you can enter your Amazon Associate Tracking Identities. If you have more than one Tracking ID on each locale then you can create extra Channels to manage them.','amazon-link'),
                                                           'Title' => __('Manage Amazon Associate IDs', 'amazon-link'), 
                                                           'Label' =>__('Associate IDs', 'amazon-link'), 
                                                           'Capability' => 'manage_options',
                                                           'Metaboxes' => array( 'alChannels' => array( 'Title' => __( 'Amazon Tracking ID Channels', 'amazon-link' ),
                                                                                                        'Callback' => array (&$this, 'show_channels' ), 
                                                                                                        'Context' => 'normal', 
                                                                                                        'Priority' => 'core'))
                                                           ),
                        'amazon-link-templates'  => array( 'Slug' => 'amazon-link-templates',
                                                           'Help' => 'help/templates.php',
                                                           'Description' => __('Use this page to manage your templates - pre-designed html and javascript code that can be used to quickly create consistant page content. Use the editor to modify existing templates, make copies, delete or add new ones of your own design.','amazon-link'),
                                                           'Title' => __('Manage Amazon Link Templates', 'amazon-link'), 
                                                           'Label' =>__('Templates', 'amazon-link'),
                                                           'Capability' => 'manage_options',
                                                           'Metaboxes' => array( 'alTemplateHelp' => array( 'Title' => __( 'Template Help', 'amazon-link' ),
                                                                                                       'Callback' => array (&$this, 'show_template_help' ), 
                                                                                                       'Context' => 'side', 
                                                                                                       'Priority' => 'low'),
                                                                                 'alTemplates' => array( 'Title' => __( 'Templates', 'amazon-link' ),
                                                                                                       'Callback' => array (&$this, 'show_templates' ), 
                                                                                                       'Context' => 'normal', 
                                                                                                       'Priority' => 'core'),
                                                                                 'alManageTemplates' => array( 'Title' => __( 'Default Templates', 'amazon-link' ),
                                                                                                       'Callback' => array (&$this, 'show_default_templates' ), 
                                                                                                       'Context' => 'normal', 
                                                                                                       'Priority' => 'low'))
                                                           ),
                        'amazon-link-extras'     => array( 'Slug' => 'amazon-link-extras',
                                                           'Help' => 'help/extras.php',
                                                           'Icon' => 'plugins',
                                                           'Description' => __('On this page you can manage user provided or requested extra functionality for the Amazon Link plugin. These items are not part of the main Amazon Link plugin as they provide features that not every user wants and may have a negative impact on your site (e.g. reduced performance, extra database usage, etc.).', 'amazon-link'),
                                                           'Title' => __('Manage Amazon Link Extras', 'amazon-link'), 
                                                           'Label' => __('Extras', 'amazon-link'), 
                                                           'Capability' => 'activate_plugins',
                                                           'Metaboxes' => array( 'alExtras' => array( 'Title' => __( 'Extras', 'amazon-link' ),
                                                                                                       'Callback' => array (&$this, 'show_extras' ), 
                                                                                                       'Context' => 'normal', 
                                                                                                       'Priority' => 'core'))
                                                           ));
         return apply_filters( 'amazon_link_admin_menus', $menus, $this);
      }

      function get_response_groups() {
         if (!isset($this->response_groups)) {
            $this->response_groups = array();
            foreach ($this->get_keywords() as $key => $key_data) {
               if (isset($key_data['Group']) && !in_array($key_data['Group'],$this->response_groups)) $this->response_groups[] = $key_data['Group'];
            }
            $this->response_groups=implode(',',$this->response_groups);
         }
         return $this->response_groups;
      }

/*****************************************************************************************/
      // Various Options, Arguments, Templates and Channels Handling
/*****************************************************************************************/

      // Options and Argument Handling

      function getOptions() {
         if (!isset($this->Opts)) {
            $this->Opts = get_option(self::optionName, array());
            if (!isset($this->Opts['version']) || ($this->Opts['version'] < $this->option_version))
            {
               $this->upgrade_settings($this->Opts);
               $this->Opts = get_option(self::optionName, array());
            }
         }
         return $this->Opts;
      }

      function saveOptions($options) {
         $option_list = $this->get_option_list();
         if (!is_array($options)) {
            return;
         }
         // Ensure hidden items are not stored in the database
         foreach ( $option_list as $optName => $optDetails ) {
            if ($optDetails['Type'] == 'hidden') unset($options[$optName]);
         }
   
         if (!empty($options['search_text'])) {

         $search_text_s = $options['search_text'];
         $keywords = $this->get_keywords();
         foreach ($keywords as $keyword => $key_data) {
            $keyword = strtoupper($keyword);
            $search_text_s = str_ireplace('%'.$keyword. '%', '%' .$keyword. '%S#', $search_text_s);
         }
         $options['search_text_s'] = $search_text_s;
      }

         update_option(self::optionName, $options);
         $this->Opts = $options;
      }

      function delete_options() {
         delete_option(self::optionName);
         delete_option(self::channels_name);
         delete_option(self::templatesName);
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
         $option_list = $this->get_option_list();
         foreach ($option_list as $key => $details) {
            if (!isset($this->Settings[$key]) && isset($details['Default'])) {
               $this->Settings[$key] = $details['Default'];
            }
         }

         return $this->Settings;
      }

      /*
       * Parse the arguments passed in.
       */
      function parseArgs($arguments) {

         $option_list = $this->get_option_list();

         $args = array();
         // Convert html encoded string back into raw characters (else parse_str does not see the '&'s).
         $arg_str = html_entity_decode($arguments, ENT_QUOTES, 'UTF-8'); // ensure '&#8217; => '’' characters are decoded

         parse_str($arg_str, $args);
         $args = apply_filters('amazon_link_process_args', $args, $this);

         $Opts = $this->getOptions();
         unset($this->Settings);

         /*
          * Check for each setting, local overides saved option, otherwise fallback to default.
          * Items not in the options_list are discarded.
          */
         foreach ($option_list as $key => $details) {

            /* If Local Settings is provided then use that */
            if (isset($args[$key])) {
               if (is_array($args[$key])) {
                  $this->Settings[$key] = array_map("trim", $args[$key]);
               } else {
                  $this->Settings[$key] = trim(stripslashes($args[$key]),"\x22\x27");
               }

            /* If No local setting but global setting is configured then use that. */
            } else if (isset($Opts[$key])) {
               $this->Settings[$key] = $Opts[$key];

            /* Fall-back to the default if configured */
            } else if (isset ($details['Default'])) {
               $this->Settings[$key] = $details['Default'];
            }

         }

         /*
          * Convert the ASIN setting into a multinational array:
          * FROM: array( 'cc1' => "1,,3", 'cc2' => "4,5")
          * TO:   array ( '0' => array( 'cc1' => 1, 'cc2' => 4),
          *               '1' => array( 'cc2' => 5),
          *               '2' => array( 'cc1' => 3))
          *
          * Ensuring all cc arrays are the same length.
          */
         if (empty($this->Settings['asin']) || !is_array($this->Settings['asin'])) {
            $this->Settings['asin'] = array( $this->Settings['default_cc'] => !empty($this->Settings['asin']) ? $this->Settings['asin'] : '');
         }
         $max = 0;
         foreach ($this->Settings['asin'] as $cc => $asins)
         {
            $temp_asins[$cc] = explode (',', $asins);
            if (count($temp_asins[$cc]) > $max) {
               $max = count($temp_asins[$cc]);
            }
         }
         $this->Settings['asin'] = array();
         for ($index=0; $index < $max; $index++) {
            foreach ($temp_asins as $cc => $cc_asins)
               if (isset($cc_asins[$index])) $this->Settings['asin'][$index][$cc] = $cc_asins[$index];
         }

         return $this->Settings;
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

      function upgrade_settings($Opts) {
         include('include/upgradeSettings.php');
      }


/*****************************************************************************************/

      // User Options

      function get_user_options($ID) {
         $options = get_the_author_meta( self::user_options, $ID );
         return $options;
      }

      function save_user_options($ID, $options ) {
	 update_usermeta( $ID, self::user_options,  $options );
      }

/*****************************************************************************************/

      // Templates

      function getTemplates() {
         if (!isset($this->Templates)) {
            $this->Templates = get_option(self::templatesName, array());
         }
         return $this->Templates;
      }

      function saveTemplates($Templates) {
         if (!is_array($Templates)) {
            return;
         }
         ksort($Templates);
         update_option(self::templatesName, $Templates);
         $this->Templates = $Templates;
      }

      function export_templates($filename) {
         $templates = $this->getTemplates();
         $slug = str_replace('-', '_', sanitize_title(get_bloginfo()));
         $content = '<?php
/*
Plugin Name: Amazon Link Extra - Exported Templates
Plugin URI: http://www.houseindorset.co.uk/plugins/amazon-link/
Description: Templates Exported from Amazon Link on ' . date("F j, Y, g:i a") . '
Version: 1.0
Author: Amazon Link User
Author URI: ' . get_site_url() .'
*/

function alx_'.$slug.'_default_templates ($templates) {
';
         foreach($templates as $id => $data) {
            if (!isset($data['Version'])) $data['Version'] = 1;
            if (!isset($data['Notice'])) $data['Notice'] = 'New Template';
            unset($data['nonce'], $data['nonce1'], $data['nonce2']);
            $content .= " \$templates['$id'] = \n  array(";
            foreach ($data as $item => $details) {
               if ($item == 'Content') {
                  $content .= "   '$item' => htmlspecialchars (". var_export($details, true) . "),\n";
               } else {
                  $content .= "   '$item' => ". var_export($details, true) . ",\n";
               }
            }
            $content .= "  );\n";
         }
         $content .= "  return \$templates;\n}\nadd_filter( 'amazon_link_default_templates', 'alx_${slug}_default_templates');\n?>";
         $result = file_put_contents( $filename, $content);
         if ($result === FALSE) {
            return array ( 'Success' => 0, 'Message' => "Export Failed could not write to: <em>$filename</em>" );
         } else {
            return array ( 'Success' => 1, 'Message' => "Templates exported to file: <em>$filename</em>, <em>$result</em> bytes written." );
         }
      }

/*****************************************************************************************/

      // Channels

      function get_channels($override = False, $user_channels = False) {
         if (!$override || !isset($this->channels)) {
            $channels = get_option(self::channels_name, array());
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
                  if (empty($channel_data['tag_'. $cc])) {
                     $this->channels[$channel_id]['tag_'. $cc] = (!empty($channels['default']['tag_' .$cc])) ? 
                                                                  $channels['default']['tag_' .$cc] : $data['default_tag'];
                  }
               }
            }
         }

         return $this->channels;         
      }

      function create_channel_rules($al, $rules, $channel, $data) // 888
      {
         // Extract rules 'rand = xx <CR> cat = aa,bb,cc <CR> tag = dd,ee,ff <CR> author = ID <CR> type = TYPE'

         if (empty($data['Filter'])) return $rules;

         preg_match('~rand\s*=\s*(?P<rand>\d*)~i', $data['Filter'], $matches);
         if (!empty($matches['rand']))
            $rules['rand'] = $matches['rand'];

         $author = preg_match('~author\s*=\s*(?P<author>\w*)~i', $data['Filter'], $matches);
         if (!empty($matches['author']))
            $rules['author'] = $matches['author'];

         $type   = preg_match('~type\s*=\s*(?P<type>\w*)~i', $data['Filter'], $matches);
         if (!empty($matches['type']))
            $rules['type'] = $matches['type'];

         $cat    = preg_match('~cat\s*=\s*(?P<cat>(\w*)(\s*,\s*(\w*))*)~i', $data['Filter'], $matches);
         if (!empty($matches['cat']))
            $rules['cat'] = array_map('trim',explode(",",$matches['cat']));

         $tag    = preg_match('~tag\s*=\s*(?P<tag>(\w*)(\s*,\s*(\w*))*)~i', $data['Filter'], $matches);
         if (!empty($matches['tag']))
            $rules['tag'] = array_map('trim',explode(",",$matches['tag']));

         return $rules;


      }

      function save_channels($channels) {
         if (!is_array($channels)) {
            return;
         }
         $defaults = $channels['default'];
         unset($channels['default']);
         ksort($channels);
         $channels = array('default' => $defaults) + $channels;
         foreach ($channels as $channel => $data) {
            $channels[$channel]['Rule'] = apply_filters('amazon_link_save_channel_rule', $this, array(), $channel, $data);
         }
         update_option(self::channels_name, $channels);
         $this->channels = $channels;
      }


      /*
       * Check the channels in order until we get a match
       *
       */
      function get_channel($settings = NULL) {
         
         // Need $GLOBALS & Channels

         if ($settings === NULL)
            $settings = $this->getSettings();

         $channels = $this->get_channels(True, True);

         if (isset($settings['in_post']) && $settings['in_post']) {
            $post = $GLOBALS['post'];
         } else {
            $post = NULL;
         }

         $channel_data = apply_filters ('amazon_link_get_channel', array(), $channels, $post, $settings, $this);
      
         // No match found return default channel.
         if (empty($channel_data)) $channel_data = $channels['default'];

         return $channel_data;

      }

      /*   
       * Filter rules:
       *    cat = [category slug|category id]
       *    parent_cat = [category slug| category id]
       *    author = [author name|author id]
       *    tag = [tag name|tag id]
       *    type = [page|post|other = widget|template, etc]
       *    parent = [page/post id]
       *    random = 1-99
       *    empty rule = won't be used by this filter
       */
      function get_channel_by_rules ($channel_data, $channels, $post, $settings, $al) {

         if (!empty($channel_data)) return $channel_data;

         foreach ($channels as $channel => $data) {

            // Process the rules if they are defined
            if (!empty($data['Rule'])) {
               
               if (isset($data['Rule']['rand']) && ($data['Rule']['rand'] > rand(0,99)))
                  return $data;

               // If manually set always respect.
               if (isset($settings['chan']) && isset($channels[strtolower($settings['chan'])])) {
                  continue;
               }

               if (isset($post)) {

                  if (isset($data['Rule']['cat']) && has_category($data['Rule']['cat'], $post))
                     return $data;

                  if (isset($data['Rule']['tag']) && has_tag($data['Rule']['tag'], $post))
                     return $data;

                  if (isset($data['Rule']['type']) && ($post->post_type == $data['Rule']['type']))
                     return $data;

                  if (isset($data['Rule']['author']) && ($post->post_author == $data['Rule']['author']))
                     return $data;
               }
            }
         }

         return $channel_data;

      }

      /*
       * If channel is manually set in the link then always apply here
       */
      function get_channel_by_setting ($channel_data, $channels, $post, $settings, $al) {

         /* This filter will override the 'rule' filter above if it is set. */

         if (isset($settings['chan']) && isset($channels[strtolower($settings['chan'])])) {
            return $channels[strtolower($settings['chan'])];
         }

         return $channel_data;

      }

      /*
       * If all previous filters have failed then look for User channel
       */
      function get_channel_by_user ($channel_data, $channels, $post, $settings, $al) {

         if (!empty($channel_data)) return $channel_data;

         // If no specific channel detected then check for author specific IDs via get_the_author_meta
         if (isset($post->post_author) && isset($channels['al_user_'.$post->post_author])) {
            return $channels['al_user_'.$post->post_author];
         }

         return $channel_data;

      }

/*****************************************************************************************/
      // Cache Facility
/*****************************************************************************************/

      function cache_install() {
         global $wpdb;
         $settings = $this->getOptions();
         if ($settings['cache_enabled']) return False;
         $cache_table = $wpdb->prefix . self::cache_table;
         $sql = "CREATE TABLE $cache_table (
                 asin varchar(10) NOT NULL,
                 cc varchar(5) NOT NULL,
                 updated datetime NOT NULL,
                 xml blob NOT NULL,
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

         $cache_table = $wpdb->prefix . self::cache_table;
         $sql = "DROP TABLE $cache_table;";
         $wpdb->query($sql);
         return True;
      }

      function cache_empty() {
         global $wpdb;

         $settings = $this->getOptions();
         if (!$settings['cache_enabled']) return False;

         $cache_table = $wpdb->prefix . self::cache_table;
         $sql = "TRUNCATE TABLE $cache_table;";
         $wpdb->query($sql);
         return True;
      }

      function cache_flush() {
         global $wpdb;
         $settings = $this->getOptions();
         $cache_table = $wpdb->prefix . self::cache_table;
         $sql = "DELETE FROM $cache_table WHERE updated < DATE_SUB(NOW(),INTERVAL " . $settings['cache_age']. " HOUR);";
         $wpdb->query($sql);
      }

      function cache_update_item($asin, $cc, &$data) {
         global $wpdb;
         $settings = $this->getOptions();
         $updated  = current_time('mysql');
         $data['timestamp'] = $updated;
         $cache_table = $wpdb->prefix . self::cache_table;
         if ($settings['cache_enabled']) {
            $sql = "DELETE FROM $cache_table WHERE asin LIKE '$asin' AND cc LIKE '$cc'";
            $wpdb->query($sql);
            $sql_data = array( 'asin' => $asin, 'cc' => $cc, 'xml' => serialize($data), 'updated' => $updated);
            $wpdb->insert($cache_table, $sql_data);
         }
      }

      function cache_lookup_item($asin, $cc) {
         global $wpdb;
         $settings = $this->getOptions();

         if (!empty($settings['cache_enabled'])) {
            // Check if asin is already in the cache
            $cache_table = $wpdb->prefix . self::cache_table;
            $sql = "SELECT xml FROM $cache_table WHERE asin LIKE '$asin' AND cc LIKE '$cc' AND  updated >= DATE_SUB(NOW(),INTERVAL " . $settings['cache_age']. " HOUR)";
            $result = $wpdb->get_row($sql, ARRAY_A);
            if ($result !== NULL) {
               $data = unserialize($result['xml']);
               $data['cached'] = 1;
               return $data;
            }
         }
         return NULL;
      }

/*****************************************************************************************/
      /// Localise Link Facility
/*****************************************************************************************/

      // Pretty arbitrary mapping of domains to Amazon sites, default to 'com' - the 'international' site.
      var $country_map = array('uk' => array('uk', 'ie', 'gi', 'gl', 'nl', 'vg', 'cy', 'gb', 'dk'),
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

      function get_country($settings = NULL) {

         if ($settings === NULL)
            $settings = $this->getSettings();

         if ($settings['localise'])
         {
            if (isset($this->local_country)) return $this->local_country;

            $cc = $this->ip2n->get_cc();
            if ($cc === NULL) return $settings['default_cc'];
            $country = 'us';
            foreach ($this->country_map as $key => $countries) {
               if (in_array($cc, $countries)) {
                  $country = $key;
                  continue;
               }
            }
            $this->local_country = $country;
            return $country;
         }

         return $settings['default_cc'];
      }

      function get_full_local_info($settings = NULL) {

         if ($settings === NULL)
            $settings = $this->getSettings();

         $channel = $this->get_channel($settings);
         $country_data    = $this->get_country_data();
         $info = array();
         foreach ($country_data as $cc => $data) {
            $country_data[$cc]['tag'] = $channel['tag_' . $cc];
            $country_data[$cc]['channel'] = $channel['ID'];
         }
         return $country_data;
      }

      function get_local_info($settings = NULL) {

         if ($settings === NULL)
            $settings = $this->getSettings();

         $channel      = $this->get_channel($settings);
         $top_cc       = $this->get_country($settings);
         $country_data = $this->get_country_data();
         $info         = array( 'flag' => $country_data[$top_cc]['flag'], 'cc' => $top_cc, 'tld' => $country_data[$top_cc]['tld'], 'tag' => $channel['tag_' . $top_cc], 'channel' => $channel['ID']);
         return $info;
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

         $this->in_post = $in_post;
         
         /* 
          * Default regex needs to match opening and closing brackets '['
          */
         $regex = '~
                   \[amazon\s+                  # "[amazon" with at least one space
                   (?P<args>                    # capture everything that follows as a named expression "args"
                    (?:(?>[^\[\]]*)             # argument name excluding any "[" or "]" character
                     (?:\[(?>[a-z]*)\])?        # optional "[alphaindex]" phrase
                    )*                          # 0 or more of these arguments
                   )                            # end of "args" group
                   \]                           # closing ]
                   ~sx';
         $regex = apply_filters('amazon_link_regex', $regex, $this);


         if ($doLinks) {
            $text = preg_replace_callback( $regex, array($this,'shortcode_expand'), $content);
            if ((preg_last_error() != PREG_NO_ERROR)) echo '<!-- amazon-link pattern error: '. var_export(preg_last_error()). '-->';
            return $text;
         } else {
            return preg_replace_callback( $regex, array($this,'shortcode_extract_asins'), $content);
         }
      }

      function widget_filter($content) {
         return $this->content_filter($content, TRUE, FALSE);
      }

      function shortcode_expand ($split_content) {

         $in_post = $this->in_post;

         // Get all named args
         $extra_args  = !empty($split_content['args']) ? $split_content['args'] : '';
         unset ($split_content['args']);
         $args = $sep ='';
         foreach ($split_content as $arg => $data) {
            if (!is_int($arg) && !empty($data)) {
               $args .= $sep. $arg .'='. $data;
               $sep = '&';
            }
         }
         if (!empty($extra_args)) $args .= $sep . $extra_args;

         $this->parseArgs($args);
         $this->shortcodes++;

         $output='';     
         if (empty($this->Settings['cat'])) {
            // Generate Amazon Link
            $this->tags = array_merge($this->Settings['asin'], $this->tags);
            $this->Settings['in_post'] = $in_post;
            if ($this->Settings['debug']) {
               $output .= '<!-- Amazon Link: Version:' . $this->plugin_version . ' - Args: ' . $args . "\n";
               $output .= print_r($this->Settings, true) . ' -->';
            }
            $output .= $this->make_links($this->Settings['asin'], $this->Settings['text']);
         } else {
            $this->Settings['in_post'] = $in_post;
            if ($this->Settings['debug']) {
               $output .= '<!-- Amazon Link: Version:' . $this->plugin_version . ' - Args: ' . $args . "\n";
               $output .= print_r($this->Settings, true) . ' -->';
            }
            $output .= $this->showRecommendations($this->Settings['cat'], $this->Settings['last']);
         }
         return $output;
      }

      function shortcode_extract_asins ($split_content) {
         // Get all named args
         $extra_args  = !empty($split_content['args']) ? $split_content['args'] : '';
         unset ($split_content['args']);
         $args = $sep ='';
         foreach ($split_content as $arg => $data) {
            if (!is_int($arg) && !empty($data)) {
               $args .= $sep. $arg .'='. $data;
               $sep = '&';
            }
         }
         if (!empty($extra_args)) $args .= $sep . $extra_args;

         $this->parseArgs($args);
         $this->tags = array_merge($this->Settings['asin'], $this->tags);
         return $split_content[0];
      }

/*****************************************************************************************/
      /// Display Content, Widgets and Pages
/*****************************************************************************************/

      function show_settings_page() {

         global $screen_layout_columns;
         $screen = get_current_screen();

         if (!isset($this->pages[$screen->id])) return;

         $page = $screen->id;
         $data = $this->pages[$page];
         $title = $data['Title'];
         $description = $data['Description'];
         $icon = isset($data['Icon']) ? $data['Icon'] : 'options-general';

         wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', false );
         wp_nonce_field('meta-box-order', 'meta-box-order-nonce', false );

?>
<div class="wrap">
 <?php screen_icon($icon); ?>
  <h2><?php echo $title ?></h2>
   <p><?php echo $description ?></p>
   <div id="poststuff">
    <div id="post-body" class="metabox-holder columns-<?php echo $screen_layout_columns; ?>" >
     <div id="post-body-content">
      <?php do_meta_boxes($page, 'normal',0); ?>
     </div>
     <div id="postbox-container-1" class="postbox-container">
      <?php do_meta_boxes($page, 'side',0); ?>
     </div>
     <div id="postbox-container-2" class="postbox-container">
      <?php do_meta_boxes($page, 'advanced',0); ?>
     </div>
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
  postboxes.add_postbox_toggles('<?php echo $page; ?>');
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

      // Extras Management Page
      function show_extras() {
         include('include/showExtras.php');
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

      function show_template_help() {
         /*
          * Populate the help popup.
          */
         $text = __('<p>Hover the mouse pointer over the keywords for more information.</p>', 'amazon-link');
         foreach ($this->get_keywords() as $keyword => $details) {
            $title = (!empty($details['Description']) ? 'title="'. htmlspecialchars($details['Description']) .'"' : '');
            $text = $text . '<p><abbr '.$title.'>%' . strtoupper($keyword) . '%</abbr></p>';
         }
         echo $text;
      }

/*****************************************************************************************/

      // Page/Post Edit Screen Widget
      function insertForm($post, $args) {
         include('include/insertForm.php');
      }

/*****************************************************************************************/
      /// Helper Functions
/*****************************************************************************************/

      function aws_signed_request($region, $params, $public_key, $private_key)
      {
         return include('include/awsRequest.php');
      }

      function remove_parents ($array) {
         if (is_array($array)) {
            return $this->remove_parents($array[0]);
         } else {
            return $array;
         }
      }

      function format_list ( $array, $key_info = array()) {

         /* Only process if it is an array, if it isn't then it probably has already been filtered. */
         if (!is_array($array)) return $array;

         $class = isset($key_info['Class']) ? $key_info['Class'] : 'al_'. $key_info['Keyword'];
         $ul = '<ul class="'. $class .'">';
         foreach ($array as $item) {
            $ul .= '<li>'. $item . '</li>';
         }
         $ul .= '</ul>';
         return $ul;
      }

      /*
       * Utility function to create some html based on an associative array.
       */
      function merge_items($items, $elements, $preserve_duplicates = False) {
         if (!isset($items[0])) {
            $items = array('0' => $items);
         }
         $unique_items = array();
         $primary = key($elements);
         $result = '';
         foreach ($items as $item) {
            if (isset($item[$primary]) && ($preserve_duplicates || !in_array($item[$primary], $unique_items))) {
               $unique_items[] = $item[$primary];
               foreach($elements as $element => $info) {
                  $result .= $info['Pre'] . $item[$element] . $info['Post'];
               }
            }
         }
         return $result;
      }

/*****************************************************************************************/
      /// Do Amazon Link Constructs
/*****************************************************************************************/

      function showRecommendations ($categories='1', $last='30') {
         return include('include/showRecommendations.php');
      }

      function grab($data, $keys, $default) {
          foreach ($keys as $location) {
             $result = $data;
             foreach ($location as $key) if (isset($result[$key])) {$result = $result[$key];} else {$result=NULL;break;}
             if (isset($result)) return $result;
          }
          if (empty($keys)) return $data; // If no keys then return the whole item
          return $default;
      }

      function cached_query($request, $settings = NULL) {

         if ($settings === NULL)
            $settings = $this->getSettings();

         $cc = $this->get_country($settings);

         /* If not a request then must be a standard ASIN Lookup */
         if (!is_array($request)) {
            $asin = $request;
            @$this->lookups[$asin]++;
         }
         $data = NULL;
         if (!is_array($request)) {
            $data[0] = $this->cache_lookup_item($asin, $cc);
            if ($data[0] !== NULL) {
               @$this->cache_hit[$asin]++;
               return $data;
            }
            @$this->cache_miss[$asin]++;
         }

         // Create query to retrieve the an item
         if (!is_array($request)) {
            $request = array();
            $request['Operation']     = 'ItemLookup';
            $request['ItemId']        = $asin;
            $request['IdType']        = 'ASIN';
            $request['ResponseGroup'] = $this->get_response_groups();
            if (!empty($settings['condition'])) {
               $request['Condition'] = $settings['condition'];
            }
         } else { 
            $request['ResponseGroup'] = $this->get_response_groups();
         }

         $pxml = $this->doQuery($request, $settings);
//unset($pxml['Items']['Item']['BrowseNodes']);
//echo "<PRE>"; print_r($pxml); echo "</PRE>";
         if (!empty($pxml['Items']['Item'])) {
            $data = array();

            if (array_key_exists('ASIN', $pxml['Items']['Item'])) {
               // Returned a single result (not in an array)
               $items = array($pxml['Items']['Item']);
               @$this->aws_hit[$asin]++;
            } else {
               // Returned several results
               $items =$pxml['Items']['Item'];
            }

         } else {

            @$this->aws_miss[$asin]++;
            // Failed to return any results

            $data['Error'] = (isset($pxml['Error'])? $pxml['Error'] : 
                                 (isset($pxml['Items']['Request']['Errors']['Error']) ? 
                                      $pxml['Items']['Request']['Errors']['Error'] : array( 'Message' => 'No Items Found', 'Code' => 'NoResults') ) );
            $items = array(array('ASIN' => $asin, 'found' => 0, 'Error' => $data['Error'] ));
         }

         $keywords = $this->get_keywords();

         /* Extract useful information from the xml */
         for ($index=0; $index < count($items); $index++ ) {
            $result = $items[$index];

            foreach ($keywords as $keyword => $key_info) {
               if (isset($key_info['Live']) && is_array($key_info['Position'])) {
                  $key_data = $this->grab($result, $key_info['Position'], (is_array($key_info['Default']) ? $key_info['Default'][$cc] : $key_info['Default']) );
                  $key_info['Keyword'] = $keyword;
                  if (isset($key_info['Callback'])) {
                     $key_data = call_user_func($key_info['Callback'], $key_data, $key_info, $this);
                  } else if (isset($key_info['Filter'])) {
                     $key_data = apply_filters($key_info['Filter'], $key_data, $key_info, $this);
                  }
                  $data[$index][$keyword] = $key_data;
               }
            }
            $data[$index]['asins']  = 0;
            $data[$index]['artist'] = $this->remove_parents($data[$index]['artist']);
            $data[$index]['found']  = isset($result['found']) ? $result['found'] : 1;

            /* Save each item to the cache if it is enabled */
            if ($data[$index]['found'] || ($result['Error']['Code'] == 'AWS.InvalidParameterValue'))
               $this->cache_update_item($data[$index]['asin'], $cc, $data[$index]);
         }

         return $data;
      }

      function doQuery($request, $settings = NULL)
      {

         if ($settings === NULL)
            $settings = $this->getSettings();

         $li  = $this->get_local_info($settings);
         $tld = $li['tld'];

         if (!isset($request['AssociateTag'])) $request['AssociateTag'] = $li['tag'];

         return $this->aws_signed_request($tld, $request, $settings['pub_key'], $settings['priv_key']);
      }

      function make_link($settings, $local_info, $type = 'product', $search = array('%SEARCH_TEXT%', '%SEARCH_TEXT%S#'))
      {
         /*
          * Generate a localised/multinational link open tag <a ... >
          */
         $attributes = 'rel="nofollow"' . $settings['new_window'] ? ' target="_blank"' : '';
         $attributes .= !empty($settings['link_title']) ? ' title="'.addslashes($settings['link_title']).'"' : '';
         $url = apply_filters('amazon_link_url', '', $type, $settings['asin'], $search[0], $local_info, $settings, $this);
         $text="<a $attributes href=\"$url\">";
         if ($settings['multi_cc']) {
            $multi_data = array('settings' => $settings, 'type' => $type, 'search' => $search[1], 'cc' => $local_info['cc'], 'channel' => $local_info['channel'] );
            $text = $this->create_popup($multi_data, $text);
         }
         return $text;
      }

      /*
       * Create a Standard Amazon URL, examples returned from the AWS Response include:
       * DetailPageURL:    http://www.amazon.fr/Les-Gens-Smiley-John-Carr%C3%A9/dp/2020479893%3FSubscriptionId%3DAKIAJ47GXTAGH4BSN3PQ%26tag%3Dhindorset-fr-21%26linkCode%3Dxm2%26camp%3D2025%26creative%3D165953%26creativeASIN%3D2020479893
       *                   http://www.amazon.<TLD>/<TITLE>/dp/<ASIN>?SubscriptionId=<PUBLIC ID>&tag=<TAG>&linkCode=xm2&camp=2025&creative=165953&creativeASIN=<ASIN>
       * Add To Wishlist:  http://www.amazon.fr/gp/registry/wishlist/add-item.html%3Fasin.0%3D2020479893%26SubscriptionId%3DAKIAJ47GXTAGH4BSN3PQ%26tag%3Dhindorset-fr-21%26linkCode%3Dxm2%26camp%3D2025%26creative%3D12742%26creativeASIN%3D2020479893
       * Add To Wishlist:  http://www.amazon.<TLD>/gp/registry/wishlist/add-item.html%3Fasin.0%3D2020479893%26SubscriptionId%3DAKIAJ47GXTAGH4BSN3PQ%26tag%3Dhindorset-fr-21%26linkCode%3Dxm2%26camp%3D2025%26creative%3D12742%26creativeASIN%3D2020479893
       * Tell A Friend:    http://www.amazon.fr/gp/pdp/taf/2020479893%3FSubscriptionId%3DAKIAJ47GXTAGH4BSN3PQ%26tag%3Dhindorset-fr-21%26linkCode%3Dxm2%26camp%3D2025%26creative%3D12742%26creativeASIN%3D2020479893
       * Tell A Friend:    http://www.amazon.<TLD>/gp/pdp/taf/2020479893%3FSubscriptionId%3DAKIAJ47GXTAGH4BSN3PQ%26tag%3Dhindorset-fr-21%26linkCode%3Dxm2%26camp%3D2025%26creative%3D12742%26creativeASIN%3D2020479893
       * Customer Reviews: http://www.amazon.fr/review/product/2020479893%3FSubscriptionId%3DAKIAJ47GXTAGH4BSN3PQ%26tag%3Dhindorset-fr-21%26linkCode%3Dxm2%26camp%3D2025%26creative%3D12742%26creativeASIN%3D2020479893
       *                   http://www.amazon.<TLD>/review/product/<ASIN>SubscriptionId=<PUBLIC ID>&tag=<TAG>&linkCode=xm2&camp=2025&creative=12742&creativeASIN=<ASIN>
       * Offers:           http://www.amazon.fr/gp/offer-listing/2020479893%3FSubscriptionId%3DAKIAJ47GXTAGH4BSN3PQ%26tag%3Dhindorset-fr-21%26linkCode%3Dxm2%26camp%3D2025%26creative%3D12742%26creativeASIN%3D2020479893
       * Offers:           http://www.amazon.<TLD>/gp/offer-listing/2020479893%3FSubscriptionId%3DAKIAJ47GXTAGH4BSN3PQ%26tag%3Dhindorset-fr-21%26linkCode%3Dxm2%26camp%3D2025%26creative%3D12742%26creativeASIN%3D2020479893
       * Search:           http://www.amazon.com/gp/redirect.html?camp=2025&creative=386001&location=http%3A%2F%2Fwww.amazon.com%2Fgp%2Fsearch%3Fkeywords%3Dkeyword%26url%3Dsearch-alias%253Daws-amazon-aps&linkCode=xm2&tag=andilike-21&SubscriptionId=AKIAJ47GXTAGH4BSN3PQ
       *                   http://www.amazon.<TLD>/gp/redirect.html?camp=2025&creative=386001&location=http://www.amazon.<TLD>&gp=search&keywords=<KEYWORD>&url=search-alias%3Daws-amazon-aps&linkCode=xm2&tag=<TAG>&SubscriptionId=<PUBLIC_ID>
       * examples from Amazon Web Tool:
       * Search:           http://www.amazon.co.uk/gp/search?ie=UTF8&camp=1634&creative=6738&index=aps&keywords=<KEYWORDS>&linkCode=ur2&tag=<TAG>
       */
      function get_url($url, $type, $asin, $search, $local_info, $settings) {

         // URL already created just drop out.
         if ($url != '') return $url;

         $link = $this->get_link_type ($type, $asin, $local_info['cc'], $search, $settings);

         if ($link['type'] == 'url' || $link['type'] == 'nolink') {
            return $link['term'];
         } else if ($link['type'] == 'product') {
            $text='http://www.amazon.' . $local_info['tld'] . '/gp/product/'. $link['term'] . '?ie=UTF8&linkCode=as2&camp=1634&creative=6738&tag=' . $local_info['tag'] .'&creativeASIN='. $link['term'];
         } else if ($link['type'] == 'search') {
            $text='http://www.amazon.' . $local_info['tld'] . '/mn/search/?_encoding=UTF8&linkCode=ur2&camp=1634&creative=19450&tag=' . $local_info['tag'] . '&field-keywords=' . $link['term'];
         } else {
            $text='http://www.amazon.' . $local_info['tld'] . '/review/'. $link['term']. '?_encoding=UTF8&linkCode=ur2&camp=1634&creative=19450&tag=' . $local_info['tag'];
         }
         return $text;

      }

      function create_popup ($data, $text){
         $type_map = array( 'product' => 'A', 'search' => 'S', 'review' => 'R', 'url' => 'U', 'nolink' => 'X');

         if (!$this->scripts_done) {
             $this->scripts_done = True;
             add_action('wp_print_footer_scripts', array($this, 'footer_scripts'));
         }

         // Need to check all locales...
         $sep = '';
         $term ='{';
         $countries = $this->get_country_data();

         foreach ($countries as $country => $country_data) {
            $link = $this->get_link_type ($data['type'], $data['settings']['asin'], $country, $data['search'], $data['settings']);
            $term .= $sep. $country .' : \''.$type_map[$link['type']].'-' . $link['term'] . '\'';
            $sep = ',';
         }
         $term .= '}';

         $script = 'onMouseOut="al_link_out()" onMouseOver="al_gen_multi('. $this->multi_id . ', ' . $term. ', \''. $data['cc']. '\', \''. $data['channel'] .'\');" ';
         $script = str_replace ('<a', '<a ' . $script, $text);
         $this->multi_id++;
         return $script;
      }

      function get_link_type ($type, $asin, $cc, $search, $settings) {
         $home_cc = $settings['home_cc'];

         if ($type == 'search' ) {
            $term = $search;
         } else {
            if (!empty($asin[$cc])) {
               $term = $asin[$cc];
            } else if (($type == 'product') && !empty($settings['url'][$cc]) ) {
               $type = 'url';
               $term = $settings['url'][$cc];
            } else if ($settings['search_link'] && !empty($asin[$home_cc])) {
               $type = 'search';
               $term = $search;
            } else if (empty($asin[$home_cc]) && !empty($settings['url'][$cc])){
               $type = 'url';
               $term = $settings['url'][$cc];
            } else if (!empty($asin[$home_cc])) {
               $term = $asin[$home_cc];
            } else {
               $type = 'nolink';
               $term = isset($settings['url'][$home_cc]) ? $settings['url'][$home_cc] :'';
            }
         }
         return array('type' => $type, 'term' => $term);
      }

      function make_links($asins, $link_text, $Settings = NULL)
      {
         global $post;
         
         if ($Settings === NULL)
            $Settings = $this->getSettings();
         $local_info = $this->get_local_info($Settings);

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

         if (!isset($Settings['template_content'])) {

            // Backward Compatible Shortcode, just has image,thumb and text

            if (!empty($Settings['image'])) {
               $image = True;
               if (strlen($Settings['image']) < 5) unset($Settings['image']);
            }

            if (!empty($Settings['thumb'])) {
               $thumb = True;
               if (strlen($Settings['thumb']) < 5) unset($Settings['thumb']);
            }

            if (isset($thumb) && isset($image)) {
               $Settings['template_content'] = '<a href="%IMAGE%"><img class="%IMAGE_CLASS%" src="%THUMB%" alt="%TEXT%"></a>';
            } else if (isset($image)) {
               $Settings['template_content']= '%LINK_OPEN%<img class="%IMAGE_CLASS%" src="%THUMB%" alt="%TEXT%">%LINK_CLOSE%';
            } else if (isset($thumb)) {
               $Settings['template_content']= '%LINK_OPEN%<img class="%IMAGE_CLASS%" src="%THUMB%" alt="%TEXT%">%LINK_CLOSE%';
            } else {
               $Settings['template_content']= '%LINK_OPEN%%TEXT%%LINK_CLOSE%';
            }
            $Settings['template_type'] = 'Product';
         }

         $details = array();
         unset($Settings['asin']);
            
         if ($Settings['template_type'] == 'Multi') {
            /* Multi-product template collapse array back to a list, respecting country specific selection */
            $sep = ''; $list='';
            foreach ($asins as $i => $asin) {
               $list .= $sep .(is_array($asin) ? (isset($asin[$local_info['cc']]) ? $asin[$local_info['cc']] : $asin[$Settings['default_cc']]) : $asin);
               $sep=',';
            }
            $details[0] = $Settings;
            $details[0]['asins'][$local_info['cc']] = $list;
         } elseif ($Settings['template_type'] == 'No ASIN') {
            /* No asin provided so don't try and parse it */
            $details[0] = $Settings;
            $details[0]['found'] = 1;
         } else {
            /* Usual case where user provides asin=X or asin=X,Y,Z */
            for ($index=0; $index < count($asins); $index++ ) {
               if (count($asins) > 1) {
                  $details[$index] = $Settings;
                  $details[$index]['asin'] = $asins[$index];
                  $details[$index]['live'] = 1;
               } else {
                  $details[$index] = $Settings;
                  $details[$index]['asin'] = $asins[$index];
               }
            }
         }

         $output = '';
         if (!empty($details))
         {

            foreach ($details as $item) {
               $output .= $this->search->parse_template($item);
            }
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
   $Settings = $awlfw->parseArgs($args);       // Get the default settings
   $li  = $awlfw->get_local_info();
   foreach ($Settings['asin'] as $asin) {
      return $awlfw->get_url('','product', $asin, '', $li, $Settings);    // Return a URL
   }
}

function amazon_scripts()
{
  global $awlfw;
  $this->footer_scripts();
}


function amazon_query($request)
{
  global $awlfw;
  return $awlfw->doQuery($request);   // Return response

}

function amazon_shortcode($args)
{
   global $awlfw;
   $awlfw->parseArgs($args);       // Get the default settings
   $awlfw->Settings['in_post'] = False;
   if (isset($awlfw->Settings['cat']))
      return $awlfw->showRecommendations($awlfw->Settings['cat'], $awlfw->Settings['last']);
   else
      return $awlfw->make_links($awlfw->Settings['asin'], $awlfw->Settings['text'], $awlfw->Settings);
}

function amazon_recommends($categories='1', $last='30')
{
  global $awlfw;
  return $awlfw->showRecommendations ($categories, $last);
}

function amazon_make_links($args)
{
   global $awlfw;
   $awlfw->parseArgs($args);       // Get the default settings
   $awlfw->Settings['in_post'] = False;
   return $awlfw->make_links($awlfw->Settings['asin'], $awlfw->Settings['text'], $awlfw->Settings);        // Return html
}

?>