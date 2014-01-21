<?php

/*
Plugin Name: Amazon Link
Plugin URI: http://www.houseindorset.co.uk/plugins/amazon-link
Description: A plugin that provides a facility to insert Amazon product links directly into your site's Pages, Posts, Widgets and Templates.
Version: 3.1.3
Text Domain: amazon-link
Author: Paul Stuttard
Author URI: http://www.houseindorset.co.uk
License: GPL2
*/

/*
Copyright 2013-2014 Paul Stuttard (email : wordpress_amazonlink@ redtom.co.uk)

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
   * Parse arguments:
      - get_settings (>cached),
        - get_options_list (>cached), 
      - get_country_data (>cached), 
      - get country [ip2n lookup (>cached)]
   
   * Make Links:
    * get_templates (>cached)
    * for each ASIN:
      * parse template:
      * (if live) perform [DB cached] itemLookup & process data returned
      * Fill in template
        * Keyword specific actions for:
           - links, 
           - urls, 
           - tags & channels,
           - [optionally] Check for local images

* If 'multinational' link found when doing the above then:
    * Return all channels and user channels(>cached), create the javascript for the multinational popup.

*******************************************************************************************************/

include ('include/ip2nation.php');

if (!class_exists('AmazonWishlist_For_WordPress')) {
   class AmazonWishlist_For_WordPress {

/*****************************************************************************************/
      /// Settings:
/*****************************************************************************************/
      const cache_table      = 'amazon_link_cache';
      const sc_cache_table   = 'amazon_link_sc_cache';
      const refs_table       = 'amazon_link_refs';
      const ip2n_table       = 'ip2nation';
      const optionName       = 'AmazonLinkOptions';
      const user_options     = 'amazonlinkoptions';
      const templatesName    = 'AmazonLinkTemplates';
      const channels_name    = 'AmazonLinkChannels';

      var $option_version    = 8;
      var $plugin_version    = '3.1.3';
      var $menu_slug         = 'amazon-link-settings';
      var $plugin_home       = 'http://www.houseindorset.co.uk/plugins/amazon-link/';

      var $stats             = array();

      var $scripts_done      = False;
      var $tags              = array();

      /*****************************************************************************************/
      // Constructor for the Plugin
      function __construct() {
         
         $this->URLRoot    = plugins_url('', __FILE__);
         $this->icon       = plugins_url('images/amazon-icon.png', __FILE__);
         $this->filename   = __FILE__;
         $this->base_name  = plugin_basename( __FILE__ );
         $this->plugin_dir = dirname( $this->base_name );
         $this->extras_dir = WP_PLUGIN_DIR . '/'. $this->plugin_dir. '/extras/';
         $this->ip2n       = new AmazonWishlist_ip2nation;

         // Register Initialisation Hook
         add_action( 'init', array( $this, 'init' ) );
         
         // Register filters to process the content and widget text
         add_filter( 'the_content', array( $this, 'content_filter' ),15,1 );
         add_filter( 'widget_text', array( $this, 'widget_filter' ), 16,1 );

      }

      /*****************************************************************************************/
      // Functions for the above hooks
 

      /*
       * Initialise the Plugin
       *
       * Called on wordpress initialisation. Do all Frontend related aspects:
       * - register styles, scripts & standard filters.
       */
      function init() {

         $settings = $this->getSettings();
         
         // Create and Initialise Dependent Class Instances:
         
         if ( ! is_admin() && ! empty( $settings['media_library'] ) ) {

            /*
             * If user is using the media_library to store Amazon images then
             * we need to initialise the Amazon Link Search class.
             */
            include( 'include/amazonSearch.php' );
            $this->search = new AmazonLinkSearch;
            $this->search->init( $this );
         }
         // ip2nation needed on Frontend
         $this->ip2n->init( $this );

         // Register our frontend styles and scripts:

         // Optional / Override-able stylesheet
         $stylesheet = apply_filters( 'amazon_link_style_sheet', plugins_url( "Amazon.css", __FILE__ ) ); 
         if ( ! empty( $stylesheet ) ) {
            wp_register_style ( 'amazon-link-style', $stylesheet, false, $this->plugin_version );
            add_action( 'wp_enqueue_scripts', array( $this, 'amazon_styles' ) );
         }

         // Multinational popup script - printed in page footer if required.
         $script     = plugins_url( "amazon.js", __FILE__ );
         wp_register_script( 'amazon-link-script', $script, false, $this->plugin_version );

         if ( ! empty($settings['sc_cache_enabled']) ) {
            
            // We can't tell if the multinational popup is needed so just load the script
            $this->scripts_done = True;
            add_action( 'wp_print_footer_scripts', array( $this, 'footer_scripts' ) );
         }

         // Set up default plugin filters:
         
         // Add default url generator - low priority
         add_filter( 'amazon_link_url',                     array( $this, 'get_url' ), 20, 6 );
         
         /* Set up the default channel filters - priority determines order */
         add_filter( 'amazon_link_get_channel' ,            array( $this, 'get_channel_by_setting' ), 10,5 );
         add_filter( 'amazon_link_get_channel' ,            array( $this, 'get_channel_by_rules' ), 12,5 );
         if (!empty($settings['user_ids'])) {
            add_filter( 'amazon_link_get_channel' ,         array( $this, 'get_channel_by_user' ), 14,5 );
         }
        
         /* Set up the default link and channel filters */
         add_filter( 'amazon_link_template_get_link_open',  array( $this, 'get_links_filter' ), 12, 6 );
         add_filter( 'amazon_link_template_get_rlink_open', array( $this, 'get_links_filter' ), 12, 6 );
         add_filter( 'amazon_link_template_get_slink_open', array( $this, 'get_links_filter' ), 12, 6 );
         add_filter( 'amazon_link_template_get_url',        array( $this, 'get_urls_filter' ), 12, 6 );
         add_filter( 'amazon_link_template_get_rurl',       array( $this, 'get_urls_filter' ), 12, 6 );
         add_filter( 'amazon_link_template_get_surl',       array( $this, 'get_urls_filter' ), 12, 6 );
         add_filter( 'amazon_link_template_get_tag',        array( $this, 'get_tags_filter' ), 12, 6 );
         add_filter( 'amazon_link_template_get_chan',       array( $this, 'get_channel_filter' ), 12, 6 );

         // Call any user hooks - passing the current plugin Settings and the Amazon Link Instance.
         do_action( 'amazon_link_init', $settings, $this );
      }

      /*
       * Enqueue Amazon Link Style Sheet.
       */
      function amazon_styles() {
         wp_enqueue_style( 'amazon-link-style' );
      }

      /*
       * Print Amazon Link Footer Scripts.
       *
       * Only done if multinational popup is used in a link.
       */
      function footer_scripts() {
         
         $settings       = $this->getSettings();
         $link_templates = $this->get_link_templates();
         
         // Create Element used to display the popup
         echo '<span id="al_popup" onmouseover="al_div_in()" onmouseout="al_div_out()"></span>';
         
         // Pass required data to the multinational popup script and print it.
         wp_localize_script( 'amazon-link-script', 
                             'AmazonLinkMulti',
                             array('link_templates' => $link_templates, 
                                   'country_data'   => $this->get_country_data(),
                                   'channels'       => $this->get_channels( True ), 
                                   'target'         => ( $settings['new_window'] ? 'target="_blank"' : '' ))
         );
         wp_print_scripts( 'amazon-link-script' );
         
         // If called directly then don't need to print again
         remove_action( 'wp_print_footer_scripts', array( $this, 'footer_scripts' ) );

      }

      /*****************************************************************************************/
      // Various Arrays to Control the Plugin
      /*****************************************************************************************/

      function get_keywords() {

         /*
          * Keyword array arguments:
          *   - Description: For Keyword Help Display
          *   - Live:        [1|0] Indicates if keyword is retrieved via AWS
          *   - Position:    Array of arrays to determine location of data in AWS XML
          *   - Group:       Which ResponseGroup needed for AWS to return item data
          *   - User:        [1|0] Indicates if keyword is supplied by User
          *   - Link:        [1|0] Indicates keyword should not have \r & \n replaced
          *   - Default:     If not provided/found use this value, if not provided '-' is used
          *   - Calculated:  If keyword should not be substituted during first template run
          *   - Strip:       [0|1] Indicates keyword should have \r \n replaced before insertion.
          */
         
         if (!isset($this->keywords)) {

            $this->keywords = array(
             'link_open'    => array( 'Description' => __('Create an Amazon link to a product with user defined content, of the form %LINK_OPEN%My Content%LINK_CLOSE%', 'amazon-link'), 'Link' => 1 ),
             'rlink_open'   => array( 'Description' => __('Create an Amazon link to product reviews with user defined content, of the form %RLINK_OPEN%My Content%LINK_CLOSE%', 'amazon-link'), 'Link' => 1),
             'slink_open'   => array( 'Description' => __('Create an Amazon link to a search page with user defined content, of the form %SLINK_OPEN%My Content%LINK_CLOSE%', 'amazon-link'), 'Link' => 1),
             'link_close'   => array( 'Description' => __('Must follow a LINK_OPEN (translates to "</a>").', 'amazon-link'), 'Default' => '</a>'),

             'asin'         => array( 'Description' => __('Item\'s unique ASIN', 'amazon-link'), 'Live' => '1', 'Group' => 'ItemAttributes', 'Default' => '0',
                                      'Position' => array(array('ASIN'))),
             'asins'        => array( 'Description' => __('Comma seperated list of ASINs', 'amazon-link'), 'Default' => ''),
             'product'      => array( 'Description' => __('Item\'s Product Group', 'amazon-link'), 'Live' => '1', 'Group' => 'ItemAttributes',
                                      'Position' => array(array('ItemAttributes','ProductGroup'))),
             'binding'      => array( 'Description' => __('Item\'s Format (Paperbook, MP3 download, etc.)', 'amazon-link'), 'Live' => '1', 'Group' => 'ItemAttributes', 'Default' => ' ',
                                      'Position' => array(array('ItemAttributes','Binding'))),
             'features'     => array( 'Description' => __('Item\'s Features', 'amazon-link'), 'Live' => '1', 'Group' => 'ItemAttributes', 'Callback' => array($this,'format_list'), 'Default' => ' ',
                                      'Position' => array(array('ItemAttributes','Feature'))),
             'title'        => array( 'Description' => __('Item\'s Title', 'amazon-link'), 'Live' => '1', 'Group' => 'ItemAttributes', 'Default' => ' ',
                                      'Position' => array(array('ItemAttributes','Title'))),
             'artist'       => array( 'Description' => __('Item\'s Author, Artist or Creator', 'amazon-link'), 'Live' => '1', 'Group' => 'ItemAttributes', 
                                      'Position' => array(array('ItemAttributes','Artist'),
                                                          array('ItemAttributes','Author'),
                                                          array('ItemAttributes','Director'),
                                                          array('ItemAttributes','Creator'),
                                                          array('ItemAttributes','Brand'))),
             'manufacturer' => array( 'Description' => __('Item\'s Manufacturer', 'amazon-link'), 'Live' => '1', 'Group' => 'ItemAttributes',
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
             'url'          => array( 'Description' => __('The raw URL for a item\'s product page', 'amazon-link'), 'Link' => 1),
             'surl'         => array( 'Description' => __('The raw URL for a item\'s search page', 'amazon-link'), 'Link' => 1),
             'rurl'         => array( 'Description' => __('The raw URL for a item\'s review page', 'amazon-link'), 'Link' => 1),
             'rank'         => array( 'Description' => __('Amazon Rank', 'amazon-link'), 'Live' => '1', 'Group' => 'SalesRank',
                                      'Position' => array(array('SalesRank'))),
             'rating'       => array( 'Description' => __('Numeric User Rating - (No longer Available)', 'amazon-link'), 'Live' => '1',
                                      'Position' => array(array('CustomerReviews','AverageRating'))),
             'offer_price'  => array( 'Description' => __('Best Offer Price of Item', 'amazon-link'), 'Live' => '1', 'Group' => 'Offers',
                                      'Position' => array(array('Offers','Offer','OfferListing','Price','FormattedPrice'),
                                                          array('OfferSummary','LowestNewPrice','FormattedPrice'),
                                                          array('OfferSummary','LowestUsedPrice','FormattedPrice'))),
             'list_price'   => array( 'Description' => __('List Price of Item', 'amazon-link'), 'Live' => '1', 'Group' => 'ItemAttributes',
                                      'Position' => array(array('ItemAttributes','ListPrice','FormattedPrice'))),
             'price'        => array( 'Description' => __('Price of Item (Combination of Offer then List Price)', 'amazon-link'), 'Live' => '1', 'Group' => 'Offers',
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
             'mplace'       => array( 'Description' => __('Localised Amazon Marketplace Code (US, GB, etc.)', 'amazon-link') ),
             'mplace_id'    => array( 'Description' => __('Localised Numeric Amazon Marketplace Code (2=uk, 8=fr, etc.)', 'amazon-link')),
             'rcm'          => array( 'Description' => __('Localised RCM site host domain (rcm.amazon.com, rcm-uk.amazon.co.uk, etc.)', 'amazon-link')),
             'buy_button'   => array( 'Description' => __('Localised Buy from Amazon Button URL', 'amazon-link')),
             'language'     => array( 'Description' => __('Localised language (English,  etc.)', 'amazon-link')),
                                                                               
             'tag'          => array( 'Description' => __('Localised Amazon Associate Tag', 'amazon-link')),
             'chan'         => array( 'Description' => __('The ID of the channel used to generate this link', 'amazon-link')),
             'cc'           => array( 'Description' => __('Localised Country Code (us, uk, etc.)', 'amazon-link')),
             'flag'         => array( 'Description' => __('Localised Country Flag Image URL', 'amazon-link')),
             'tld'          => array( 'Description' => __('Localised Top Level Domain (.com, .co.uk, etc.)', 'amazon-link')),

             'downloaded'   => array( 'Description' => __('1 if Images are in the local WordPress media library', 'amazon-link'), 'Calculated' => '1'),
             'found'        => array( 'Description' => __('1 if product was found doing a live data request (also 1 if live not enabled).', 'amazon-link'), 'Calculated' => '1', 'Default' => '1'),
             'timestamp'    => array( 'Description' => __('Date and time of when the Amazon product data was retrieved from Amazon.', 'amazon-link'), 'Calculated' => 1, 'Default' => '0')
            );
            $this->keywords = apply_filters('amazon_link_keywords', $this->keywords, $this);
         }
         return $this->keywords;
      }

      function get_country_data($cc = NULL) {

         if (!isset($this->country_data)) {

            /*
             * Country specific aspects:
             * 
             * Some needed in the plugin code:
             * - cc           -> the country code (also the index).
             * - lang         -> language identifier (see Microsoft Translate)
             * - flag         -> country flag image, used in settings pages (also a keyword)
             * - tld          -> tld of amazon site, used when making AWS request (also a keyword)
             * - site         -> link to affiliate program site, used on settings pages
             * - default_tag  -> Default tag if none set up, used when making AWS request
             * - country_name -> full name of country, used in settings pages (also a keyword)
             *
             * Some only needed for templates:
             * - mplace       -> market place of amazon site, used in Amazon Scripts
             * - mplace_id    -> market place id of amazon locale, used in Amazon Scripts
             * - rcm          -> amazon domain for location of scripts - backward compatible
             * - ads          -> partial amazon domain for location of scripts, prepend:
             *   - add 'wms' => for serving source widgets (US
             *   - add 'ir'  => for serving impression tracking images (US, not EU but country specific)
             *   - add 'rcm' => for serving iframe images, banners, (US)
             * - buy_button   -> example buy button stored on Amazon Servers
             * - language     -> Language of each locale.
             */
            $this->country_data = array(
               'uk' => array( 'cc' => 'uk', 'mplace' => 'GB', 'mplace_id' => '2',  'lang' => 'en',     'flag' => $this->URLRoot. '/'. 'images/flag_uk.gif', 'tld' => 'co.uk', 'language' => 'English',  'rcm' => 'rcm-eu.amazon-adsystem.com',   'ads' => '-eu.amazon-adsystem.com', 'site' => 'https://affiliate-program.amazon.co.uk', 'buy_button' => 'https://images-na.ssl-images-amazon.com/images/G/02/buttons/buy-from-tan.gif', 'default_tag' => 'al-uk-21', 'country_name' => 'United Kingdom'),
               'us' => array( 'cc' => 'us', 'mplace' => 'US', 'mplace_id' => '1',  'lang' => 'en',     'flag' => $this->URLRoot. '/'. 'images/flag_us.gif', 'tld' => 'com',   'language' => 'English',  'rcm' => 'rcm.amazon-adsystem.com',      'ads' => '-na.amazon-adsystem.com', 'site' => 'https://affiliate-program.amazon.com', 'buy_button' => 'https://images-na.ssl-images-amazon.com/images/G/01/buttons/buy-from-tan.gif', 'default_tag' => 'al-us-20', 'country_name' => 'United States'),
               'de' => array( 'cc' => 'de', 'mplace' => 'DE', 'mplace_id' => '3',  'lang' => 'de',     'flag' => $this->URLRoot. '/'. 'images/flag_de.gif', 'tld' => 'de',    'language' => 'Deutsch',  'rcm' => 'rcm-de.amazon-adsystem.de',    'ads' => '-eu.amazon-adsystem.com', 'site' => 'https://partnernet.amazon.de', 'buy_button' => 'https://images-na.ssl-images-amazon.com/images/G/03/buttons/buy-from-tan.gif', 'default_tag' => 'al-de-21', 'country_name' => 'Germany'),
               'es' => array( 'cc' => 'es', 'mplace' => 'ES', 'mplace_id' => '30', 'lang' => 'es',     'flag' => $this->URLRoot. '/'. 'images/flag_es.gif', 'tld' => 'es',    'language' => 'Español',  'rcm' => 'rcm-es.amazon-adsystem.es',    'ads' => '-eu.amazon-adsystem.com', 'site' => 'https://afiliados.amazon.es', 'buy_button' => 'https://images-na.ssl-images-amazon.com/images/G/30/buttons/buy-from-tan.gif', 'default_tag' => 'al-es-21', 'country_name' => 'Spain'),
               'fr' => array( 'cc' => 'fr', 'mplace' => 'FR', 'mplace_id' => '8',  'lang' => 'fr',     'flag' => $this->URLRoot. '/'. 'images/flag_fr.gif', 'tld' => 'fr',    'language' => 'Français', 'rcm' => 'rcm-fr.amazon-adsystem.fr',    'ads' => '-eu.amazon-adsystem.com', 'site' => 'https://partenaires.amazon.fr', 'buy_button' => 'https://images-na.ssl-images-amazon.com/images/G/08/buttons/buy-from-tan.gif', 'default_tag' => 'al-fr-21', 'country_name' => 'France'),
               'jp' => array( 'cc' => 'jp', 'mplace' => 'JP', 'mplace_id' => '9',  'lang' => 'ja',     'flag' => $this->URLRoot. '/'. 'images/flag_jp.gif', 'tld' => 'jp',    'language' => '日本語',    'rcm' => 'rcm-jp.amazon-adsystem.co.jp', 'ads' => '-fe.amazon-adsystem.com', 'site' => 'https://affiliate.amazon.co.jp', 'buy_button' => 'https://images-na.ssl-images-amazon.com/images/G/09/buttons/buy-from-tan.gif', 'default_tag' => 'al-jp-22', 'country_name' => 'Japan'),
               'it' => array( 'cc' => 'it', 'mplace' => 'IT', 'mplace_id' => '31', 'lang' => 'it',     'flag' => $this->URLRoot. '/'. 'images/flag_it.gif', 'tld' => 'it',    'language' => 'Italiano', 'rcm' => 'rcm-it.amazon-adsystem.it',    'ads' => '-eu.amazon-adsystem.com', 'site' => 'https://programma-affiliazione.amazon.it', 'buy_button' => 'https://images-na.ssl-images-amazon.com/images/G/29/buttons/buy-from-tan.gif', 'default_tag' => 'al-it-21', 'country_name' => 'Italy'),
               'cn' => array( 'cc' => 'cn', 'mplace' => 'CN', 'mplace_id' => '29', 'lang' => 'zh-CHS', 'flag' => $this->URLRoot. '/'. 'images/flag_cn.gif', 'tld' => 'cn',    'language' => '简体中文',   'rcm' => 'rcm-cn.amazon-adsystem.cn',   'ads' => '-eu.amazon-adsystem.com', 'site' => 'https://associates.amazon.cn', 'buy_button' => 'https://images-na.ssl-images-amazon.com/images/G/28/buttons/buy-from-tan.gif', 'default_tag' => 'al-cn-23', 'country_name' => 'China'),
               'in' => array( 'cc' => 'in', 'mplace' => 'IN', 'mplace_id' => '28', 'lang' => 'hi',     'flag' => $this->URLRoot. '/'. 'images/flag_in.gif', 'tld' => 'in',    'language' => 'Hindi',    'rcm' => 'rcm-in.amazon-adsystem.com',   'ads' => '-eu.amazon-adsystem.com', 'site' => 'https://associates.amazon.in', 'buy_button' => 'https://images-na.ssl-images-amazon.com/images/G/31/buttons/buy-from-tan.gif', 'default_tag' => 'al-in-21', 'country_name' => 'India'),
               'ca' => array( 'cc' => 'ca', 'mplace' => 'CA', 'mplace_id' => '15', 'lang' => 'en',     'flag' => $this->URLRoot. '/'. 'images/flag_ca.gif', 'tld' => 'ca',    'language' => 'English',  'rcm' => 'rcm-ca.amazon-adsystem.ca',    'ads' => '-na.amazon-adsystem.com', 'site' => 'https://associates.amazon.ca', 'buy_button' => 'https://images-na.ssl-images-amazon.com/images/G/15/buttons/buy-from-tan.gif', 'default_tag' => 'al-ca-20', 'country_name' => 'Canada'));
         }
         if (empty($cc)) {
            return $this->country_data;
         } else {
            return $this->country_data[$cc];
         }
      }

      function get_link_templates() {

         if (!isset($this->link_templates)) {
            
            $this->link_templates = apply_filters('amazon_link_multi_link_templates', 
                                                  array('A'=>'http://www.amazon.%TLD%%CC%#/gp/product/%ARG%?ie=UTF8&linkCode=as2&camp=1634&creative=6738&tag=%TAG%%CC%#&creativeASIN=%ARG%',
                                                        'S'=>'http://www.amazon.%TLD%%CC%#/mn/search/?_encoding=UTF8&linkCode=ur2&camp=1634&creative=19450&tag=%TAG%%CC%#&field-keywords=%ARG%',
                                                        'R'=>'http://www.amazon.%TLD%%CC%#/review/%ARG%?ie=UTF8&linkCode=ur2&camp=1634&creative=6738&tag=%TAG%%CC%#',
                                                        'U'=>'%ARG%',
                                                        'X'=>'%ARG%'),
                                                  $this);
         }
         return $this->link_templates;
      }

      /*
       * Get all possible plugin options, these are also the arguments accepted by the shortcode.
       *
       * option_list array arguments:
       * Backend
       *    - Type:           Indicates how displayed on Options page (hidden options not saved to DB)
       *    - Value:          Usually the Data to be displayed (e.g. for title/nonce/section)
       *    - Class:          Class of Item in Form
       *    - Title_Class:    Class of Title in Form
       *    - Section_Class:  Class of a Section in Form
       *    - Name:           Label in Form for Item
       *    - Description:    Detailed Description of Item
       *    - Size:           Size of Text Item
       *    - Hint:           Detailed hint on mouse over
       *    - Options:        Options for Selection Item
       * Frontend
       *    - Default:        Default Value if Not Set
       *
       * TODO: Create the Frontend Array then array_merge_recursive with the Backend Array if is_admin()
       */
      function get_option_list() {
     
         if (!isset($this->option_list)) {

            if (is_admin()) {
                           
               $this->option_list = array(

            /* Hidden Options - not saved in Settings */

            'nonce'             => array( 'Type' => 'nonce', 'Value' => 'update-AmazonLink-options' ),
            'cat'               => array( 'Type' => 'hidden' ),
            'last'              => array( 'Type' => 'hidden' ),
            'template'          => array( 'Type' => 'hidden' ),
            'chan'              => array( 'Type' => 'hidden' ),
            's_index'           => array( 'Type' => 'hidden' ),
            's_title'           => array( 'Type' => 'hidden' ),
            's_author'          => array( 'Type' => 'hidden' ),
            's_page'            => array( 'Type' => 'hidden' ),
            'template_content'  => array( 'Type' => 'hidden' ),

            /* Options that change how the items are displayed */
            'hd1s'              => array( 'Type' => 'section', 'Value' => __('Display Options', 'amazon-link'), 'Title_Class' => 'al_section_head', 'Description' => __('Change the default appearance and behaviour of the Links.','amazon-link'), 'Section_Class' => 'al_subhead1'),
            'text'              => array( 'Name' => __('Link Text', 'amazon-link'), 'Description' => __('Default text to display if none specified', 'amazon-link'), 'Default' => 'Amazon', 'Type' => 'text', 'Size' => '40', 'Class' => 'al_border' ),
            'image_class'       => array( 'Name' => __('Image Class', 'amazon-link'), 'Description' => __('Style Sheet Class of image thumbnails', 'amazon-link'), 'Default' => 'wishlist_image', 'Type' => 'text', 'Size' => '40', 'Class' => 'alternate al_border' ),
            'wishlist_template' => array( 'Name' => __('Wishlist Template', 'amazon-link') , 'Description' => __('Default template to use for the wishlist <em>* <a href="#aws_notes" title="AWS Access keys required for full functionality">AWS</a> *</em>', 'amazon-link'), 'Default' => 'Wishlist', 'Type' => 'selection', 'Class' => 'al_border'  ),
            'wishlist_items'    => array( 'Name' => __('Wishlist Length', 'amazon-link'), 'Description' => __('Maximum number of items to display in a wishlist (Amazon only returns a maximum of 5, for the \'Similar\' type of list) <em>* <a href="#aws_notes" title="AWS Access keys required for full functionality">AWS</a> *</em>', 'amazon-link'), 'Default' => 5, 'Type' => 'text', 'Class' => 'alternate al_border' ),
            'wishlist_type'     => array( 'Name' => __('Wishlist Type', 'amazon-link'), 'Description' => __('Default type of wishlist to display, \'Similar\' shows items similar to the ones found, \'Random\' shows a random selection of the ones found <em>* <a href="#aws_notes" title="AWS Access keys required for full functionality">AWS</a> *</em>', 'amazon-link'), 'Default' => 'Similar', 'Options' => array('Similar', 'Random', 'Multi'), 'Type' => 'selection', 'Class' => 'al_border'  ),
            'new_window'        => array( 'Name' => __('New Window Link', 'amazon-link'), 'Description' => __('When link is clicked on, open it in a new browser window', 'amazon-link'), 'Default' => '0', 'Type' => 'checkbox', 'Class' => 'alternate al_border' ),
            'link_title'        => array( 'Name' => __('Link Title Text', 'amazon-link'), 'Description' => __('The text to put in the link \'title\' attribute, can use the same keywords as in the Templates (e.g. %TITLE% %ARTIST%), leave blank to not have a link title.', 'amazon-link'), 'Default' => '', 'Type' => 'text', 'Size' => '40', 'Class' => 'al_border' ),
            'media_library'     => array( 'Name' => __('Use Media Library', 'amazon-link'), 'Description' => __('The plugin will look for and use thumbnails and images in the WordPress media library that are marked with an Amazon ASIN.', 'amazon-link'), 'Default' => '0', 'Type' => 'checkbox', 'Class' => 'alternate' ),
            'hd1e'              => array( 'Type' => 'end'),

             /* Options that control localisation */
            'hd2s'          => array( 'Type' => 'section', 'Value' => __('Localisation Options', 'amazon-link'), 'Title_Class' => 'al_section_head', 'Description' => __('Control the localisation of data displayed and links created.','amazon-link'), 'Section_Class' => 'al_subhead1'),
            'ip2n_message'  => array( 'Type' => 'title', 'Title_Class' => 'al_para', 'Class' => 'al_pad al_border'),
            'default_cc'    => array( 'Name' => __('Default Country', 'amazon-link'), 'Hint' => __('The Amazon Associate Tags should be entered in the \'Associate IDs\' settings page.', 'amazon-link'),'Description' => __('Which country\'s Amazon site to use by default', 'amazon-link'), 'Default' => 'uk', 'Type' => 'selection', 'Class' => 'alternate al_border' ),
            'localise'      => array( 'Name' => __('Localise Amazon Link', 'amazon-link'), 'Description' => __('Make the link point to the user\'s local Amazon website, (you must have ip2nation installed for this to work).', 'amazon-link'), 'Default' => '1', 'Type' => 'checkbox', 'Class' => 'al_border' ),
            'global_over'   => array( 'Name' => __('Global Defaults', 'amazon-link'), 'Description' => __('Default values in the shortcode "title=xxxx" affect all locales, if not set only override the default locale.', 'amazon-link'), 'Default' => '1', 'Type' => 'checkbox', 'Class' => 'alternate al_border' ),
            'search_link'   => array( 'Name' => __('Create Search Links', 'amazon-link'), 'Description' => __('Generate links to search for the items by "Artist Title" for non local links, rather than direct links to the product by ASIN.', 'amazon-link'), 'Default' => '0', 'Type' => 'checkbox', 'Class' => 'al_border' ),
            'search_text'   => array( 'Name' => __('Default Search String', 'amazon-link'), 'Description' => __('Default items to search for with "Search Links", uses the same system as the Templates below.', 'amazon-link'), 'Default' => '%ARTIST% | %TITLE%', 'Type' => 'text', 'Size' => '40', 'Class' => 'alternate al_border' ),
            'search_text_s' => array( 'Default' => '%ARTIST%S# | %TITLE%S#', 'Type' => 'calculated' ),
            'multi_cc'      => array( 'Name' => __('Multinational Link', 'amazon-link'), 'Description' => __('Insert links to all other Amazon sites after primary link.', 'amazon-link'), 'Default' => '1', 'Type' => 'checkbox', 'Class' => 'al_border'),
            'hd2e'          => array( 'Type' => 'end'),

            /* Options related to the Amazon backend */
            'hd3s'          => array( 'Type' => 'section', 'Id' => 'aws_notes', 'Value' => __('Amazon Associate Information','amazon-link'), 'Title_Class' => 'al_section_head', 'Description' => __('The AWS Keys are required for some of the features of the plugin to work (The ones marked with AWS above), visit <a href="http://aws.amazon.com/">Amazon Web Services</a> to sign up to get your own keys.', 'amazon-link'), 'Section_Class' => 'al_subhead1'),
            'pub_key'       => array( 'Name' => __('AWS Public Key', 'amazon-link'), 'Description' => __('Access Key ID provided by your AWS Account, found under Security Credentials/Access Keys of your AWS account', 'amazon-link'), 'Default' => '', 'Type' => 'text', 'Size' => '40', 'Class' => '' ),
            'priv_key'      => array( 'Name' => __('AWS Private key', 'amazon-link'), 'Description' => __('Secret Access Key ID provided by your AWS Account.', 'amazon-link'), 'Default' => '', 'Type' => 'text', 'Size' => '40', 'Class' => 'alternate' ),
            'aws_valid'     => array( 'Type' => 'checkbox', 'Read_Only' => 1, 'Name' => 'AWS Keys Validated', 'Default' => '0', 'Class' => 'al_border'),
            'live'          => array( 'Name' => __('Live Data', 'amazon-link'), 'Description' => __('When creating Amazon links, use live data from the Amazon site, otherwise populate the shortcode with static information. <em>* <a href="#aws_notes" title="AWS Access keys required for full functionality">AWS</a> *</em>', 'amazon-link'), 'Default' => '1', 'Type' => 'checkbox', 'Class' => 'al_border' ),
            'condition'     => array( 'Name' => __('Condition', 'amazon-link'), 'Description' => __('By default Amazon only returns Offers for \'New\' items, change this to return items of a different condition.', 'amazon-link'), 'Default' => '', 'Type' => 'selection',
                                      'Options' => array( '' => array('Name' => 'Use Default'), 'All' => array ('Name' => 'All'), 'New' => array('Name' => 'New'),'Used' => array('Name' => 'Used'),'Collectible' => array('Name' => 'Collectible'),'Refurbished' => array('Name' => 'Refurbished')),
                                      'Class' => 'alternate al_border' ),
            'prefetch'      => array( 'Name' => __('Prefetch Data', 'amazon-link'), 'Description' => __('For every product link, prefetch the data from the Amazon Site - use of the cache essential for this option! <em>* <a href="#aws_notes" title="AWS Access keys required for full functionality">AWS</a> *</em>', 'amazon-link'), 'Default' => '0', 'Type' => 'checkbox', 'Class' => '' ),
            'user_ids'      => array( 'Name' => __('User Affiliate IDs', 'amazon-link'), 'Description' => __('Allow all users to have their own Affiliate IDs accessible from their profile page', 'amazon-link'), 'Default' => '0', 'Type' => 'checkbox', 'Class' => 'alternate' ),
            'hd3e'          => array( 'Type' => 'end'),

            'hd4s'          => array( 'Type' => 'section', 'Value' => __('Amazon Caches','amazon-link'), 'Title_Class' => 'al_section_head', 'Description' => __('Improve page performance by caching Amazon product data and shortcode output.','amazon-link'), 'Section_Class' => 'al_subhead1'),
            'title3'        => array( 'Type' => 'title', 'Value' => __(' Product Cache','amazon-link'), 'Title_Class' => 'al_section_head', 'Description' => __(' Improve page performance when using large numbers of links by caching Amazon Product lookups.','amazon-link'), 'Class' => 'alternate'),
            'cache_age'     => array( 'Name' => __('Cache Data Age', 'amazon-link'), 'Description' => __('Max age in hours of the data held in the Amazon Link Cache', 'amazon-link'), 'Type' => 'text', 'Default' => '48'),
            'cache_enabled' => array( 'Type' => 'backend', 'Default' => '0'),
            'cache_c'       => array( 'Type' => 'buttons', 'Class' => 'al_border', 'Buttons' => array( __('Enable Cache', 'amazon-link' ) => array( 'Hint' => __('Install the sql database table to cache data retrieved from Amazon.', 'amazon-link'), 'Class' => 'button-secondary', 'Action' => 'AmazonLinkAction'),
                                                     __('Disable Cache', 'amazon-link' ) => array( 'Hint' => __('Remove the Amazon Link cache database table.', 'amazon-link'),'Class' => 'button-secondary', 'Action' => 'AmazonLinkAction'),
                                                     __('Flush Cache', 'amazon-link' ) => array( 'Hint' => __('Delete all data in the Amazon Link cache.', 'amazon-link'),'Class' => 'button-secondary', 'Action' => 'AmazonLinkAction'),
                                                                        )),
            'title4'           => array( 'Type' => 'title', 'Value' => __(' Shortcode Cache','amazon-link'), 'Title_Class' => 'al_section_head', 'Description' => __(' Reduce server load for high traffic sites by caching the shortcode expansion.','amazon-link'), 'Class' => 'alternate'),
            'sc_cache_age'     => array( 'Name' => __('SC Cache Data Age', 'amazon-link'), 'Description' => __('Max age in hours of the data held in the Amazon Link Shortcode Cache.', 'amazon-link'), 'Type' => 'text', 'Default' => '1'),
            'sc_cache_enabled' => array( 'Type' => 'backend', 'Default' => '0'),
            'sc_cache_c'       => array( 'Type' => 'buttons', 'Class' => 'al_border', 'Buttons' => array( __('Enable SC Cache', 'amazon-link' ) => array( 'Hint' => __('Install the sql database table to cache shortcode output.', 'amazon-link'), 'Class' => 'button-secondary', 'Action' => 'AmazonLinkAction'),
                                                     __('Disable SC Cache', 'amazon-link' ) => array( 'Hint' => __('Remove the Amazon Link Shortcode cache database table.', 'amazon-link'),'Class' => 'button-secondary', 'Action' => 'AmazonLinkAction'),
                                                     __('Flush SC Cache', 'amazon-link' ) => array( 'Hint' => __('Delete all data in the Amazon Link Shortcode cache.', 'amazon-link'),'Class' => 'button-secondary', 'Action' => 'AmazonLinkAction'),
                                                                        )),
            'hd4e'          => array( 'Type' => 'end'),

            'hd5s'           => array( 'Type' => 'section', 'Value' => __('Advanced Options','amazon-link'), 'Title_Class' => 'al_section_head', 'Description' => __('Further options for debugging and Amazon Extras.','amazon-link'), 'Section_Class' => 'al_subhead1'),
            'template_asins' => array( 'Name' => __('Template ASINs', 'amazon-link'), 'Description' => __('ASIN values to use when previewing the templates in the templates manager.', 'amazon-link'), 'Default' => '0893817449,0500410607,050054199X,0500286426,0893818755,050054333X,0500543178,0945506562', 'Type' => 'text', 'Size' => '40', 'Class' => 'al_border' ),
            'debug'          => array( 'Name' => __('Debug Output', 'amazon-link'), 'Description' => __('Adds hidden debug output to the page source to aid debugging. <b>Do not enable on live sites</b>.', 'amazon-link'), 'Default' => '0', 'Type' => 'checkbox', 'Size' => '40', 'Class' => 'alternate al_border' ),
            'full_uninstall' => array( 'Name' => __('Purge on Uninstall', 'amazon-link'), 'Description' => __('On uninstalling the plugin remove all Settings, Templates, Associate Tracking IDs, Cache Data & ip2nation Data .<b>Use when removing the plugin for good</b>.', 'amazon-link'), 'Default' => '0', 'Type' => 'checkbox', 'Size' => '40', 'Class' => '' ),
            'hd5e'           => array( 'Type' => 'end')
             );
            } else {
            $this->option_list = array(

            'text'              => array( 'Default' => 'Amazon'),
            'image_class'       => array( 'Default' => 'wishlist_image' ),
            'wishlist_template' => array( 'Default' => 'Wishlist' ),
            'wishlist_items'    => array( 'Default' => 5 ),
            'wishlist_type'     => array( 'Default' => 'Similar' ),
            'new_window'        => array( 'Default' => '0' ),
            'link_title'        => array( 'Default' => '' ),
            'media_library'     => array( 'Default' => '0' ),
            'default_cc'    => array( 'Default' => 'uk' ),
            'localise'      => array( 'Default' => '1' ),
            'global_over'   => array( 'Default' => '1' ),
            'search_link'   => array( 'Default' => '0' ),
            'search_text'   => array( 'Default' => '%ARTIST% | %TITLE%' ),
            'search_text_s' => array( 'Default' => '%ARTIST%S# | %TITLE%S#' ),
            'multi_cc'      => array( 'Default' => '1' ),
            'live'          => array( 'Default' => '1' ),
            'condition'     => array( 'Default' => '' ),
            'prefetch'      => array( 'Default' => '0' ),
            'user_ids'      => array( 'Default' => '0' ),
            'cache_age'     => array( 'Default' => '48'),
            'cache_enabled' => array( 'Default' => '0'),
            'sc_cache_age'     => array( 'Default' => '1'),
            'sc_cache_enabled' => array( 'Default' => '0'),
            'debug'          => array( 'Default' => '0' ),
             );
            } 
            if (is_admin()) {
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
            }

            $this->option_list = apply_filters('amazon_link_option_list', $this->option_list);

            if (is_admin()) {
               $this->option_list['button'] = array( 'Type' => 'buttons', 'Buttons' => array( __('Update Options', 'amazon-link' ) => array( 'Class' => 'button-primary', 'Action' => 'AmazonLinkAction')));
            }
         }
         return $this->option_list;
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
         if ( ! isset( $this->Opts ) ) {
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

      /*
       * Normally Settings are populated from parsing user arguments, however some
       * external calls do not cause argument parsing (e.g. amazon_query). So this
       * ensures we have the defaults.
       */
      function getSettings() {
         if (!isset($this->Settings)) {
            $this->Settings = $this->getOptions();
            $option_list = $this->get_option_list();
            foreach ($option_list as $key => $details) {
               if (!isset($this->Settings[$key]) && isset($details['Default'])) {
                  $this->Settings[$key] = $details['Default'];
               }
            }
         }

         return $this->Settings;
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
         update_usermeta( $ID, self::user_options,  array_filter($options) );
      }

      /*****************************************************************************************/
      // Templates

      function getTemplates () {
         if ( ! isset ( $this->Templates ) ) {
            $this->Templates = get_option ( self::templatesName, array() );
         }
         return $this->Templates;
      }

      /*
       * Store Templates array in WordPress options
       *   - Used in upgrade_settings
       */
      function saveTemplates ( $templates ) {
         if ( ! is_array ( $templates ) ) {
            return;
         }
         ksort ( $templates );
         update_option ( self::templatesName, $templates );
         $this->Templates = $templates;
      }

      /*****************************************************************************************/
      // Channels
      
      function get_channels($override = False) {
         if (!$override || !isset($this->channels)) {
            $channels = get_option(self::channels_name, array());

            if (!$override) return $channels;

            $country_data = $this->get_country_data();
            foreach( $country_data as $cc => $data) {
               $default_tags['tag_'.$cc] = $data['default_tag'];
            }
            $channels['default'] = array_filter($channels['default']);
            $this->channels = array();
            foreach ( $channels as $channel_id => $channel_data) {
               $this->channels[$channel_id] = array_filter($channel_data) + $channels['default'] + $default_tags;
               $this->channels[$channel_id]['ID'] = $channel_id;
            }
         }

         return $this->channels;         
      }
      
      /*
       * Check the channels in order until we get a match
       *
       */
      function get_channel($settings) {
         
         // Need $GLOBALS & Channels

         if (!empty($settings['in_post'])) {
            $post = $GLOBALS['post'];
            $post_ID = $post->ID;
         } else {
            $post = NULL;
            $post_ID = '';
         }

         $channels = $this->get_channels(True);
         if (isset($this->channel_cache[$settings['asin'] . $post_ID])) {
            $channel_data = $channels[$this->channel_cache[$settings['asin'] . $post_ID]];
         } else {
            $channel_data = apply_filters ('amazon_link_get_channel', array(), $channels, $post, $settings, $this);
            // No match found return default channel.
            if (empty($channel_data)) $channel_data = $channels['default'];
            $this->channel_cache[$settings['asin'] . $post_ID] = $channel_data['ID'];
         }      
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

         if (!empty($channel_data)) return $channel_data;

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
      // Frontend Cache Facility
/*****************************************************************************************/

      function cache_update_item($asin, $cc, &$data) {
         global $wpdb;
         $settings = $this->getOptions();
         if (!empty($settings['cache_enabled'])) {
            /* Use SQL timestamp to avoid timezone difference between SQL and PHP */
            $result= $wpdb->get_row("SELECT NOW() AS timestamp", ARRAY_A);
            $data['timestamp'] = $updated = $result['timestamp'];
            
            $cache_table = $wpdb->prefix . self::cache_table;
            $sql = "DELETE FROM $cache_table WHERE asin LIKE '$asin' AND cc LIKE '$cc'";
            $wpdb->query($sql);
            $sql_data = array( 'asin' => $asin, 'cc' => $cc, 'xml' => serialize($data), 'updated' => $updated);
            $wpdb->insert($cache_table, $sql_data);
         }
      }

      function cache_lookup_item($asin, $cc, $settings = NULL) {
         global $wpdb;
         if ($settings === NULL)
            $settings = $this->getOptions();

         if (!empty($settings['cache_enabled'])) {
            // Check if asin is already in the cache
            $cache_table = $wpdb->prefix . self::cache_table;
            if (!empty($settings['cache_age'])) {
               $sql = "SELECT xml FROM $cache_table WHERE asin LIKE '$asin' AND cc LIKE '$cc' AND  updated >= DATE_SUB(NOW(),INTERVAL " . $settings['cache_age']. " HOUR)";
            } else {
               $sql = "SELECT xml FROM $cache_table WHERE asin LIKE '$asin' AND cc LIKE '$cc'";
            }
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
      // Frontend Shortcode Cache Facility
/*****************************************************************************************/

      function sc_cache_update_item($args, $cc, $postid, &$data) {
         global $wpdb;
         $settings = $this->getOptions();
         
         if (!empty($settings['sc_cache_enabled'])) {
            /* Use SQL timestamp to avoid timezone difference between SQL and PHP */
            $result= $wpdb->get_row("SELECT NOW() AS timestamp", ARRAY_A);
            $updated = $result['timestamp'];
            $hash = hash('md5', $args);
            $postid = (!empty($postid) ? $postid : '0');
            $cache_table = $wpdb->prefix . self::sc_cache_table;
            $sql = "DELETE FROM $cache_table WHERE hash = '$hash' AND cc = '$cc' AND postid = '$postid'";
            $wpdb->query($sql);
            $sql_data = array( 'hash' => $hash, 'cc' => $cc, 'postid' => $postid, 'args' => $args, 'content' => $data, 'updated' => $updated);
            $wpdb->insert($cache_table, $sql_data);
         }
      }

      function sc_cache_lookup_item($args, $cc, $postid, $settings = NULL) {
         global $wpdb;
         if ($settings === NULL)
            $settings = $this->getOptions();
         if (!empty($settings['sc_cache_enabled'])) {
            $postid = (!empty($postid) ? $postid : '0');
            $hash = hash('md5', $args);
            // Check if shortcode is already in the cache
            $cache_table = $wpdb->prefix . self::sc_cache_table;
            if (!empty($settings['sc_cache_age'])) {
               $sql = "SELECT content FROM $cache_table WHERE hash = '$hash' AND cc = '$cc' AND postid = '$postid' AND  updated >= DATE_SUB(NOW(),INTERVAL " . $settings['sc_cache_age']. " HOUR)";
            } else {
               $sql = "SELECT content FROM $cache_table WHERE hash = '$hash' AND cc = '$cc' AND postid = '$postid'";
            }
            $result = $wpdb->get_row($sql, ARRAY_A);
            if ($result !== NULL) {
               return $result['content'];
            }

         }
         return NULL;
      }

/*****************************************************************************************/
      /// Localise Link Facility
/*****************************************************************************************/

      // Pretty arbitrary mapping of domains to Amazon sites, default to 'com' - the 'international' site.
      var $country_map = array( 'uk' => array('uk', 'ie', 'gi', 'gl', 'nl', 'vg', 'cy', 'gb', 'dk'),
                               'fr' => array('fr', 'be', 'bj', 'bf', 'bi', 'cm', 'cf', 'td', 'km', 'cg', 'dj', 'ga', 'gp',
                                             'gf', 'gr', 'pf', 'tf', 'ht', 'ci', 'lu', 'mg', 'ml', 'mq', 'yt', 'mc', 'nc',
                                             'ne', 're', 'sn', 'sc', 'tg', 'vu', 'wf'),
                               'de' => array('de', 'at', 'ch', 'no', 'dn', 'li', 'sk'),
                               'es' => array('es'),
                               'it' => array('it'),
                               'cn' => array('cn'),
                               'ca' => array('ca', 'pm'),
                               'jp' => array('jp'),
                               'in' => array('in')
                              );

      function get_country($settings) {

         if (!empty($settings['localise']))
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

      function get_local_info($settings) {
         
         $top_cc       = $this->get_country($settings);
         return $this->get_country_data($top_cc);
      }
      
      /*****************************************************************************************/
      /* Actual Mechanics of the Shortcode Processing
       *
       *   - Filter the Content and Widget Text for Shortcodes
       *     * content_filter
       *     * widget_filter
       *
       *   - Either action the shortcode, or just extract ASINs
       *     * shortcode_expand
       *     * shortcode_extract_asins
       *       - parse_shortcode [to turn argument string into settings array
       *
       *   - Generate Output
       *     * make_links
       *     * show_recommendations
       *
      /*****************************************************************************************/      

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
         if ($in_post) {
            $this->post_ID = $GLOBALS['post']->ID;
         } else {
            $this->post_ID = '0';
         }
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
      
/*****************************************************************************************/
     
      /*
       * Expand shortcode arguments and action accordingly
       */
      function shortcode_expand ($split_content) {

         $split_content['in_post'] = $this->in_post;
         $args = $this->parse_shortcode($split_content);
         $this->inc_stats('shortcodes',0);

         $output='';
         $cc = $this->settings['local_cc'];
         
         if ($this->settings[$cc]['debug']) {
            $output .= '<!-- Amazon Link: Version:' . $this->plugin_version . ' - Args: ' . $args . "\n";
            $output .= print_r($this->settings, true) . ' -->';
         }

         if (empty($this->settings[$cc]['cat']) && empty($this->settings[$cc]['s_index'])) {

            if (!empty($this->settings['asin'])) $this->tags = array_merge($this->settings['asin'], $this->tags);

            // Lookup Shortcode in Shortcode Cache
            $cached_output = $this->sc_cache_lookup_item($args, $cc, $this->post_ID);
            if (!empty($cached_output)) return $cached_output;

            // Generate Amazon Link
            $output .= $this->make_links($this->settings);

            // Save Shortcode
            $this->sc_cache_update_item($args, $cc, $this->post_ID, $output);
         } else {

            $output .= $this->showRecommendations();
         }
         return apply_filters('amazon_link_shortcode_output', $output, $this);
      }

      /*
       * Expand shortcode arguments and record ASINs listed
      */
      function shortcode_extract_asins ($split_content) {
         
         $this->parse_shortcode($split_content);
         $this->tags = array_merge($this->settings['asin'], $this->tags);
         return $split_content[0];
      }

      /*
       * Extract settings from shortcode arguments
       */
      function parse_shortcode($split) {

         // Clear old Settings
         unset($this->settings);
         unset($this->Settings);

         // Get global settings and default country data
         $countries = $this->get_country_data();
         $this->settings = $countries;
         $this->settings['global'] = $this->getSettings();
         $this->settings['global']['home_cc'] = $this->settings['global']['default_cc'];

         /*
          * First get the main arguments string
          */
         if (empty($split['args'])) {
            $args  = html_entity_decode(!empty($split[1]) ? $split[1] : '=', ENT_QUOTES, 'UTF-8');
            unset ($split[1]);
         } else {
            $args  = html_entity_decode($split['args'], ENT_QUOTES, 'UTF-8');
            unset ($split['args']);
         }

         /*
          * Reverse some of the WordPress filters efforts & ensure '&#8217; => '�' characters are decoded
          */
          foreach ($split as $arg => $data) {
            if (!is_int($arg) && !empty($data)) {
               if ($arg == 'asin') {
                  // ASIN we store outside the main settings
                  $asins = explode (',', $data);
                  foreach ($asins as $i => $asin) $this->settings['asin'][$i][$this->settings['global']['default_cc']] = $asin;
               } else {
                  $this->settings['global'][$arg] = html_entity_decode($data, ENT_QUOTES, 'UTF-8');
               }
            }
         }
       
         // Split string into arguments arg[cc]=data at each '&'
         $arguments = explode('&', $args);

         foreach ($arguments as $argument) {
                  
            // Split argument into arg[cc] and data at '='
            list($arg,$data) = explode('=', $argument, 2);
            list($arg,$cc) = preg_split('/(\]|\[|$)/', $arg,3);
            if (empty($cc)) $cc = 'global';
            $arg = strtolower($arg);
            $cc  = strtolower($cc);
            
            if ($arg == 'asin') {

               // ASIN we store outside the main settings
               $asins = explode (',', $data);
               if ($cc == 'global') $cc = $this->settings['global']['default_cc'];
               foreach ($asins as $i => $asin) $this->settings['asin'][$i][$cc] = $asin;
               
            } else if ($arg == 'template_content') {
               
               // TEMPLATE_CONTENT does not want to be urldecoded
               $this->settings[$cc][$arg] = $data;

            } else if (!empty($arg)) {
               // Strip off quotes and urldecode arguments
               $this->settings[$cc][$arg] = trim(urldecode($data),'x22x27 ');
            }
         }
         
         $this->settings = apply_filters('amazon_link_process_args', $this->settings, $this);

         $this->settings['local_cc']   = $this->get_country($this->settings['global']);
         $this->settings['default_cc'] = $this->settings['global']['default_cc'];
         $this->settings['global']['local_cc'] = $this->settings['local_cc'];
         foreach ($countries as $cc => $data) {
            // copy global settings into each locale
            $this->settings[$cc] += (array)$this->settings['global'];
         }
        
         // For backwards compatibility
         $this->Settings = &$this->settings['global'];

         return $args;
     
      }

      /*****************************************************************************************/
      /*
       * Generate Content
       */
      /*****************************************************************************************/

      function make_links($settings)
      {
         global $post;
         
         $cc = $settings['local_cc'];

         /*
          * If a template is specified and exists then populate it
          */
         if (isset($settings[$cc]['template'])) {
            $template = strtolower($settings[$cc]['template']);
            $Templates = $this->getTemplates();
            if (isset($Templates[$template])) {
               $settings[$cc]['template_content'] = $Templates[$template]['Content'];
               $settings[$cc]['template_type'] = $Templates[$template]['Type'];
            }
         }

         if (!isset($settings[$cc]['template_content'])) {

            // Backward Compatible Shortcode, just has image,thumb and text

            if (!empty($settings[$cc]['image'])) {
               $image = True;
               if (strlen($settings[$cc]['image']) < 5) unset($settings[$cc]['image']);
            }

            if (!empty($settings[$cc]['thumb'])) {
               $thumb = True;
               if (strlen($settings[$cc]['thumb']) < 5) unset($settings[$cc]['thumb']);
            }

            if (isset($thumb) && isset($image)) {
               $settings[$cc]['template_content'] = '<a href="%IMAGE%"><img class="%IMAGE_CLASS%" src="%THUMB%" alt="%TEXT%"></a>';
            } else if (isset($image)) {
               $settings[$cc]['template_content']= '%LINK_OPEN%<img class="%IMAGE_CLASS%" src="%THUMB%" alt="%TEXT%">%LINK_CLOSE%';
            } else if (isset($thumb)) {
               $settings[$cc]['template_content']= '%LINK_OPEN%<img class="%IMAGE_CLASS%" src="%THUMB%" alt="%TEXT%">%LINK_CLOSE%';
            } else {
               $settings[$cc]['template_content']= '%LINK_OPEN%%TEXT%%LINK_CLOSE%';
            }
            $settings[$cc]['template_type'] = 'Product';
         }

         $details = array();

         if (empty($settings[$cc]['template_type'])) $settings[$cc]['template_type'] = 'Product';
             
         if ($settings[$cc]['template_type'] == 'Multi') {
            /* Multi-product template collapse array back to a list, respecting country specific selection */
            $sep = ''; $list='';
            foreach ($settings['asin'] as $i => $asin) {
               $list .= $sep .(is_array($asin) ? (isset($asin[$cc]) ? $asin[$cc] : $asin[$settings['default_cc']]) : $asin);
               $sep=',';
            }
            $settings[$cc]['asins'] = $list;

            $output = $this->parse_template($settings);
         } elseif ($settings[$cc]['template_type'] == 'No ASIN') {
            /* No asin provided so don't try and parse it */
            $settings[$cc]['found'] = 1;
            $settings['asin'] = array();
            $output = $this->parse_template($settings);
         } else {
            $asins = $settings['asin'];
            /* Usual case where user provides asin=X or asin=X,Y,Z */
            if (count($asins) > 1) {
               $settings[$cc]['live'] = 1;
            }
            $output = '';

            $countries = $this->get_country_data();
            foreach ($asins as $asin) {
               foreach ($countries as $cc => $data) {
                  $settings[$cc]['asin'] = !empty($asin[$cc])? $asin[$cc]:NULL;
               }
               $settings['asin'] = $asin;

               $output .= $this->parse_template($settings);
            }
         }
         return $output;
      }

      function showRecommendations () {
         return include('include/showRecommendations.php');
      }
      
/*****************************************************************************************/
      /*
       * Parse Template - keyword filters
       */
/*****************************************************************************************/
      
      function get_channel_filter ($channel, $keyword, $country, $data, $settings, $al) {

         if (!empty($channel)) return $channel;

         $channel = $this->get_channel($data[$country]);
         return $channel['ID'];
      }

      function get_tags_filter ($tag, $keyword, $country, $data, $settings, $al) {

         if (!empty($tag)) return $tag;

         $channel = $this->get_channel($data[$country]);
         return $channel['tag_'.$country];
      }

      function get_urls_filter ($url, $keyword, $country, $data, $settings, $al) {

         if (!empty($url)) return $url;

         $map = array( 'url' => 'A', 'rurl' => 'R', 'surl' => 'S');
         $type = $map[$keyword];

         $url = apply_filters('amazon_link_url', '', $type, $data['asin'], $data[$country]['search_text'], $data[$country], $settings, $al);
         return $url;

      }

      function get_links_filter ($link, $keyword, $country, $data, $settings, $al) {

         if (empty($this->temp_settings['multi_cc']) && !empty($link)) return $link;

         $map = array( 'link_open' => 'A', 'rlink_open' => 'R', 'slink_open' => 'S');
         $type = $map[$keyword];

         $attributes = 'rel="nofollow"' . ($settings['new_window'] ? ' target="_blank"' : '');
         $attributes .= !empty($data[$country]['link_title']) ? ' title="'.addslashes($data[$country]['link_title']).'"' : '';
         $url = apply_filters('amazon_link_url', '', $type, $data, $data[$country]['search_text'], $country, $settings, $this);
         $text="<a $attributes href=\"$url\">";
         if ($settings['multi_cc']) {
            $multi_data = array('settings' => $settings, 'asin' => $data['asin'], 'type' => $type, 'search' => $data[$country]['search_text_s'], 'cc' => $country );
            $text = $this->create_popup($multi_data, $text);
         }
         return $text;
      }

      /*
       * We need to run the regex multiple times to catch new template tags replacing old ones (LINK_OPEN)
       */
      function parse_template ($item) {

         $start_time = microtime(true);

         $countries_a = array_keys($this->get_country_data());

         $keywords_data = $this->get_keywords();
         $sep = $sepc = $keywords = $keywords_c = '';
         foreach ($keywords_data as $keyword => $key_data) {
            if (empty($key_data['Calculated'])) {
               $keywords .= $sep.$keyword;
               $sep = '|';
            } else {
               $keywords_c .= $sepc.$keyword;
               $sepc= '|';
            }
         }
         
         $input = htmlspecialchars_decode (stripslashes($item[$item['local_cc']]['template_content']));

         $this->temp_settings = $item[$item['local_cc']];
         $this->temp_settings['asin'] = $item['asin'];
         $this->temp_data     = $item;

         $countries = implode('|',$countries_a);
         do {
            $input = preg_replace_callback( "!(?>%($keywords)%)(?:(?>($countries))?(?>(S))?([0-9]+)?#)?!i", array($this, 'parse_template_callback'), $input, -1, $count);
         } while ($count);

         $input = preg_replace_callback( "!(?>%($keywords_c)%)(?:(?>($countries))?(?>(S))?([0-9]+)?#)?!i", array($this, 'parse_template_callback'), $input);

         $this->Settings['default_cc'] = $item[$item['local_cc']]['default_cc'];
         $this->Settings['multi_cc'] = $item[$item['local_cc']]['multi_cc'];
         $this->Settings['localise'] = $item[$item['local_cc']]['localise'];

         $time = microtime(true) - $start_time;

         if (!empty($item[$item['local_cc']]['debug'])) $input .="<!-- Time Taken: $time. -->";
         
         // Clear out local settings and data, no longer needed
         unset($this->temp_settings, $this->temp_data);

         return $input;
      }

      /*
       * Callback to process the preg_replace result where:
       *
       * - $args[1] => 'KEYWORD'
       * - $args[2] => 'CC'
       * - $args[3] => 'ESCAPE'
       * - $args[4] => 'INDEX'
       */
      function parse_template_callback ($args) {

         $keyword  = strtolower($args[1]);

         $keywords = $this->get_keywords();
         $settings = $this->temp_settings;

         $default_country  = $settings['home_cc'];

         $key_data  = $keywords[$keyword];

         /*
          * Process Modifiers
          */
         if (empty($args[2])) {
            $country     = $settings['local_cc'];
         } else {
            // Manually set country, hard code to not localised
            $country = strtolower($args[2]);
            $settings['multi_cc']  = 0;
            $settings['localise']  = 0;
            $settings['default_cc'] = $country;
         }
         $escaped        = !empty($args[3]);
         $keyword_index  = (!empty($args[4]) ? $args[4] : 0);

         /*
          * Select the most appropriate ASIN for the locale
          */
         if (empty($this->temp_data[$country]['asin'])) {
            $this->temp_data[$country]['asin'] = isset($this->temp_data[$default_country]['asin']) ? $this->temp_data[$default_country]['asin'] : NULL;
         }
         $asin = $this->temp_data[$country]['asin'];

         /*
          * Prefetch product data if not already fetched and prefetch is enabled
          */
         if ($settings['live'] && $settings['prefetch'] && empty($this->temp_data[$country]['prefetched'])) {

            $item_data = $this->cached_query($asin, $settings, True);

            if ($item_data['found']) {
               if (empty($settings['asin'][$country])) {
                  $settings['asin'][$country] = $asin;
                  $this->temp_settings['asin'][$country] = $asin;
               }
            } else if (!empty($settings['localise']) && ($country != $default_country)) {

               $settings['default_cc'] = $default_country;
               $settings['localise']   = 0;
               $item_data = $this->cached_query($asin, $settings, True);
               $item_data['not_found'] = 1;
            }
            $this->temp_data[$country] = array_merge($item_data, (array)$this->temp_data[$country]);
            $this->temp_data[$country]['prefetched'] = 1;
         }

         /*
          * Apply any template_get filters for this keyword
          */
         $phrase = apply_filters( 'amazon_link_template_get_'. $keyword, isset($this->temp_data[$country][$keyword])?$this->temp_data[$country][$keyword]:NULL, $keyword, $country, $this->temp_data, $settings, $this);
         if ($phrase !== NULL) $this->temp_data[$country][$keyword] = $phrase;
   
         /*
          * If the keyword is not yet set then we need to populate it
          */
         if (!isset($this->temp_data[$country][$keyword])) {

            /*
             * If we can get it from Amazon then try and get it
             */
            if (!empty($key_data['Live']) && ($settings['live'])) {
                  $item_data = $this->cached_query($asin, $settings, True);
               if ($item_data['found']) {
                  
                  if (empty($settings['asin'][$country])) {
                     $settings['asin'][$country] = $asin;
                     $this->temp_settings['asin'][$country] = $asin;
                  }
               } else if (!$item_data['found'] && $settings['localise'] && ($country != $settings['default_cc'])) {
                  
                  $settings['localise']   = 0;
                  $item_data = $this->cached_query($asin, $settings, True);
                  $item_data['not_found'] = 1;
               }
                  
               if ($settings['debug'] && isset($item_data['Error'])) {
                  echo "<!-- amazon-link ERROR: "; print_r($item_data); echo "-->";
               }
               
               $this->temp_data[$country] = array_merge($item_data, (array)$this->temp_data[$country]);

            } else {
               
               /*
                * We can't retreive it, so just use the default if set
                */
               $this->temp_data[$country][$keyword] = isset($key_data['Default']) ? ( is_array($key_data['Default']) ? $key_data['Default'][$country] : $key_data['Default'] ) : '-';
            }
         }

         /*
          * Run the 'process' filters to post process the keyword
          */
         $this->temp_data[$country][$keyword] = apply_filters( 'amazon_link_template_process_'. $keyword, isset($this->temp_data[$country][$keyword])?$this->temp_data[$country][$keyword]:NULL, $keyword, $country, $this->temp_data, $settings, $this);

         /*
          * If multiple results returned then select the one requested in the template
          */
         $phrase = $this->temp_data[$country][$keyword];
         if (is_array($phrase)) {
            $phrase = $phrase[$keyword_index];
         }

         /*
          * This just needs to get the data through to the javascript, typical HTML looks like:
          * <a onmouseover="Function( {'arg': '%KEYWORD%'} )">
          * Need to ensure there are no unescaped ' or " characters or new lines
          *
          * It is up to the receiving javascript to ensure that the data is present correctly for the next stage
          *  - in postedit -> strip out > and " and & and [ to ensure the shortcode is parsed correctly
          *  - in popup (do nothing?).
          */
         if ($escaped) $phrase = str_ireplace(array( "'", '&'), array("\'", '%26'), $phrase);
         if (!empty($key_data['Live']) && empty($key_data['Link'])) $phrase = str_ireplace(array( '"', "'", "\r", "\n"), array('&#34;', '&#39;','&#13;','&#10;'), $phrase);

         /*
          * Update unused_args to remove used keyword.
          */
         if (!empty($this->temp_data[$default_country]['unused_args'])) $this->temp_data[$default_country]['unused_args'] = preg_replace('!(&?)'.$keyword.'=[^&]*(\1?)&?!','\2', $this->temp_data[$default_country]['unused_args']);

         return $phrase;
      }
      

/*****************************************************************************************/
      /// Helper Functions
/*****************************************************************************************/

      function inc_stats($array, $element) {
         $this->stats[$array][$element] = isset($this->stats[$array][$element]) ? $this->stats[$array][$element]+1 : 1;
      }

      function aws_signed_request($region, $params, $public_key, $private_key)
      {
         return include('include/awsRequest.php');
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

/*****************************************************************************************/
      /// Do Amazon Link Constructs
/*****************************************************************************************/

      function grab($data, $keys, $default) {
          foreach ($keys as $location) {
             $result = $data;
             foreach ($location as $key) if (isset($result[$key])) {$result = $result[$key];} else {$result=NULL;break;}
             if (isset($result)) return $result;
          }
          if (empty($keys)) return $data; // If no keys then return the whole item
          return $default;
      }

      function cached_query($request, $settings, $first_only = False) {

         $cc = $this->get_country($settings);
         $data = NULL;
         
         /* If not a request then must be a standard ASIN Lookup */
         if (!is_array($request)) {
            $asin = $request;
            $this->inc_stats('lookups',$asin);
            
            // Try and retrieve from the cache
            $data[0] = $this->cache_lookup_item($asin, $cc);
            if ($data[0] !== NULL) {
               $this->inc_stats('cache_hit',$asin);
               return $first_only ? $data[0] : $data;
            }
            $this->inc_stats('cache_miss',$asin);

            // Create query to retrieve the an item
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
         if (!empty($pxml['Items']['Item'])) {
            $data = array();

            if (array_key_exists('ASIN', $pxml['Items']['Item'])) {
               // Returned a single result (not in an array)
               $items = array($pxml['Items']['Item']);
               $this->inc_stats('aws_hit',$asin);
            } else {
               // Returned several results
               $items =$pxml['Items']['Item'];
            }

         } else {

            $this->inc_stats('aws_miss',$asin);
            // Failed to return any results

            $data['Error'] = (isset($pxml['Error'])? $pxml['Error'] : 
                                 (isset($pxml['Items']['Request']['Errors']['Error']) ? 
                                      $pxml['Items']['Request']['Errors']['Error'] : array( 'Message' => 'No Items Found', 'Code' => 'NoResults') ) );
            $items = array(array('ASIN' => $asin, 'found' => 0, 'Error' => $data['Error'] ));
         }

         $keywords = $this->get_keywords();
         $partial = False;

         /* Extract useful information from the xml */
         for ($index=0; $index < count($items); $index++ ) {
            $result = $items[$index];
            foreach ($keywords as $keyword => $key_info) {
               if (!empty($key_info['Live']) &&                                     // Is a Live Keyword
                   isset($key_info['Position']) && is_array($key_info['Position'])) // Has a pointer to what data to use
               {

                  if (!empty($settings['skip_slow']) && !empty($key_info['Slow'])) {
                     /* Slow Callbacks skipped so flag partial data so as not to cache it */
                     $partial = True;
                  } else {
                     $key_data = $this->grab($result, 
                                             $key_info['Position'], 
                                             isset($key_info['Default']) ? ( is_array($key_info['Default']) ? $key_info['Default'][$cc] : $key_info['Default'] ) : '-');
                     $key_info['Keyword'] = $keyword;
                     if (isset($key_info['Callback'])) {
                        $key_data = call_user_func($key_info['Callback'], $key_data, $key_info, $this, $data[$index]);
                     } else if (isset($key_info['Filter'])) {
                        $key_data = apply_filters($key_info['Filter'], $key_data, $key_info, $this, $data[$index]);
                     }
                     $data[$index][$keyword] = $key_data;
                  }
               }
            }
            $data[$index]['asins']  = 0;
            $data[$index]['found']  = isset($result['found']) ? $result['found'] : '1';
            $data[$index]['partial'] = $partial;
            
            /* Save each item to the cache if it is enabled and got complete data */
            if (!$partial &&
                ($data[$index]['found'] || 
                 ($result['Error']['Code'] == 'AWS.InvalidParameterValue') ||
                 ($result['Error']['Code'] == 'AWS.ECommerceService.ItemNotAccessible')))
               $this->cache_update_item($data[$index]['asin'], $cc, $data[$index]);
         }

         return $first_only ? $data[0] : $data;
      }

      function doQuery($request, $settings = NULL)
      {

         if ($settings === NULL)
            $settings = $this->getSettings();

         $li  = $this->get_local_info($settings);
         $tld = $li['tld'];

         if (!isset($request['AssociateTag'])) $request['AssociateTag'] = $li['default_tag'];

         return $this->aws_signed_request($tld, $request, $settings['pub_key'], $settings['priv_key']);
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
       * Offers:           http://www.amazon.<TLD>/gp/offer-listing/<ASIN>?SubscriptionId=<PUBLIC_ID>&tag=<TAG>&linkCode=xm2&camp=2025&creative=12742&creativeASIN=<ASIN>
       * Search:           http://www.amazon.com/gp/redirect.html?camp=2025&creative=386001&location=http%3A%2F%2Fwww.amazon.com%2Fgp%2Fsearch%3Fkeywords%3Dkeyword%26url%3Dsearch-alias%253Daws-amazon-aps&linkCode=xm2&tag=andilike-21&SubscriptionId=AKIAJ47GXTAGH4BSN3PQ
       *                   http://www.amazon.<TLD>/gp/redirect.html?camp=2025&creative=386001&location=http://www.amazon.<TLD>&gp=search&keywords=<KEYWORD>&url=search-alias%3Daws-amazon-aps&linkCode=xm2&tag=<TAG>&SubscriptionId=<PUBLIC_ID>

       * Common Elements:  http://www.amazon.<TLD>/[search type]?SubscriptionId=<PUBLIC_ID>&tag=<TAG>&camp=2025&linkCode=xm2
       * Product Related:  creativeASIN=<ASIN>
       * Search Related:   keywords=<KEYWORD>&url=search-alias&aws-amazon-aps
    
       * examples from Amazon Web Tool:
       * Search:           http://www.amazon.co.uk/gp/search?ie=UTF8&camp=1634&creative=6738&index=aps&keywords=<KEYWORDS>&linkCode=ur2&tag=<TAG>
       *
       * From Amazon Web Site:
       * Book Search:      http://www.amazon.com/gp/search/ref=sr_adv_b/?search-alias=stripbooks&unfiltered=1&field-keywords=%3CKEYWORD%3E&field-author=%3CARTIST%3E&field-title=%3CTITLE%3E&field-isbn=%3CISBN%3E&field-publisher=%3CPUBLISHER%3E&node=45&field-p_n_condition-type=1294423011&field-feature_browse-bin=2656020011&field-subject=9-12&field-language=English&field-dateop=During&field-datemod=1&field-dateyear=2012&sort=relevanceexprank&Adv-Srch-Books-Submit.x=28&Adv-Srch-Books-Submit.y=7
       *      Terms:       field-keywords=
                           field-author=
                           field-title=
                           field-isbn=
                           field-publisher=
                           node=45
                           field-p_n_condition-type=1294423011
                           field-feature_browse-bin=2656020011
                           field-subject=9-12
                           field-language=English
                           field-dateop=During
                           field-datemod=1
                           field-dateyear=2012
                           sort=relevanceexprank
                           Adv-Srch-Books-Submit.x=28&Adv-Srch-Books-Submit.y=7
       * Music Search:     http://www.amazon.com/gp/search/ref=sr_adv_m_pop/?search-alias=popular&unfiltered=1&field-keywords=keyword&field-artist=&field-title=&field-label=&field-binding=&sort=relevancerank&Adv-Srch-Music-Album-Submit.x=32&Adv-Srch-Music-Album-Submit.y=8
       *       Terms:      field-keywords=
                           field-artist=
                           field-title=
                           field-label=
                           field-binding=
                           sort=relevancerank
                           Adv-Srch-Music-Album-Submit.x=32&Adv-Srch-Music-Album-Submit.y=8
       * Toys Search:      http://www.amazon.com/gp/search/ref=sr_adv_toys/?search-alias=toys-and-games&unfiltered=1&field-keywords=keyword&field-brand=&node=165993011&field-price=&field-age_range=&sort=relevancerank&Adv-Srch-Toys-Submit.x=41&Adv-Srch-Toys-Submit.y=5
       *      Terms:       field-keywords=
                           field-brand=
                           node=165993011
                           field-price=
                           field-age_range=
                           sort=relevancerank
                           Adv-Srch-Toys-Submit.x=41&Adv-Srch-Toys-Submit.y=5
       */
      function get_url($url, $type, $data, $search, $country, $settings) {

         // URL already created just drop out.
         if ($url != '') return $url;

         $link  = $this->get_link_type ($type, $settings['asin'], $country, $search, $settings);
         /* If not standard localisation then populate the %MANUAL_CC% keyword */
         if (empty($setting['localise']) && ($country != $settings['home_cc'])) {
            $manual_cc = $country;
         } else {
            $manual_cc = '';
         }
         $links = $this->get_link_templates();
         $text  = $links[$link['type']];
         $text  = str_replace(array('%ARG%', '%TYPE%', '%CC%', '%MANUAL_CC%'), array($link['term'], $link['type'], $country, $manual_cc), $text);
         return $text;

      }

      function create_popup ($data, $text){

         if (!$this->scripts_done) {
             $this->scripts_done = True;
             add_action('wp_print_footer_scripts', array($this, 'footer_scripts'));
         }

         // Need to check all locales...
         $sep = '';
         $term ='{';
         $countries = $this->get_country_data();

         foreach ($countries as $country => $country_data) {
            $link = $this->get_link_type ($data['type'], $data['asin'], $country, $data['search'], $data['settings']);
            $term .= $sep. $country .' : \''.$link['type'].'-' . $link['term'] . '\'';
            $sep = ',';
         }
         $term .= '}';

         $script = 'onMouseOut="al_link_out()" onMouseOver="al_gen_multi('. rand() . ', ' . $term. ', \''. $data['cc']. '\', \'%CHAN%\');" ';
         $script = str_replace ('<a', '<a ' . $script, $text);
         return $script;
      }

      function get_link_type ($type, $asin, $cc, $search, $settings) {
         $home_cc = $settings['home_cc'];

         if ($type == 'S' ) {
            $term = $search;
         } else {
            if (!empty($asin[$cc])) {
               $term = $asin[$cc];
            } else if ( ($type == 'A') && 
                        !empty($settings['url']) &&
                        ( (is_array($settings['url']) && array_key_exists($cc,$settings['url'])) ||
                          ($cc == $home_cc) )) {
               $type = 'U';
               $term = is_array($settings['url']) ? $settings['url'][$cc] : $settings['url'];
            } else if ($settings['search_link'] && !empty($asin[$home_cc])) {
               $type = 'S';
               $term = $search;
            } else if (empty($asin[$home_cc]) && 
                        !empty($settings['url']) &&
                        ( (is_array($settings['url']) && array_key_exists($cc,$settings['url'])) ||
                          ($cc == $home_cc) )) {
               $type = 'U';
               $term = is_array($settings['url']) ? $settings['url'][$cc] : $settings['url'];
            } else if (!empty($asin[$home_cc])) {
               $term = $asin[$home_cc];
            } else {
               $type = 'X';
               $term = isset($settings['url']) ? (is_array($settings['url']) ? (empty($settings['url'][$cc]) ? '' : $settings['url'][$cc]) : $settings['url']) : '';
            }
         }
         return array('type' => $type, 'term' => $term);
      }    
   } // End Class
      
   // Create either Admin instance or Frontend install of the Amazon Link Class.
   if (is_admin()) {
      include ('include/amazonSearch.php');
      include ('include/displayForm.php');
      include ('include/amazon-link-admin-support.php');
      $awlfw = new Amazon_Link_Admin_Support();
   } else {
      $awlfw = new AmazonWishlist_For_WordPress();
   }

} // End if exists

function amazon_get_link($args)
{
   global $awlfw;
   return $awlfw->shortcode_expand(array('args'=>$args, 'template_content'=>'%URL%'));
}

function amazon_scripts()
{
  global $awlfw;
  $awlfw->footer_scripts();
}

function amazon_query($request)
{
  global $awlfw;
  return $awlfw->doQuery($request);   // Return response
}

function amazon_cached_query($request, $settings = NULL, $first_only = False)
{
   global $awlfw;
   
   if ($settings === NULL)
      $settings = $awlfw->getSettings();
   
   return $awlfw->cached_query($request, $settings, $first_only);
}

function amazon_shortcode($args)
{
   global $awlfw;
   $awlfw->in_post = False;
   return $awlfw->shortcode_expand(array('args'=>$args));
}

function amazon_recommends($categories='1', $last='30')
{
   global $awlfw;
   return $awlfw->shortcode_expand(array('cat'=>$categories, 'last'=>$last));   
}

function amazon_make_links($args)
{
   return amazon_shortcode($args);
}
// vim:set ts=4 sts=4 sw=4 st:
?>
