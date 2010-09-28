<?php

/*
Plugin Name: Amazon Link
Plugin URI: http://www.houseindorset.co.uk/plugins/amazon-link
Description: Insert a link to Amazon using the passed ASIN number, with the required affiliate info.
Version: 1.2
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

require_once("aws_signed_request.php");

require_once("include/displayForm.php");

if (!class_exists('AmazonWishlist_For_WordPress')) {
   class AmazonWishlist_For_WordPress {

/*****************************************************************************************/
      /// Settings:
/*****************************************************************************************/
      var $optionList = null;

      // String to insert into Posts to indicate where to insert the amazon items
      var $TagHead    = '[amazon';
      var $TagTail    = ']';

      var $optionName = 'AmazonLinkOptions';
      var $Opts       = null;

      function AmazonWishlist_For_WordPress() {
         $this->__construct();
      }

      function __construct() {
         $this->URLRoot = plugins_url("", __FILE__);
         $this->base_name  = plugin_basename( __FILE__ );
         $this->plugin_dir = dirname( $this->base_name );
         $this->form = new AmazonWishlist_Options;

         add_filter('plugin_row_meta', array($this, 'registerPluginLinks'),10,2);
         add_filter('the_content', array($this, 'contentFilter'));
         add_filter('the_posts', array($this, 'stylesNeeded'));
         add_action('admin_menu', array($this, 'optionsMenu'));
         add_action('init', array($this, 'loadLang'));
 
      }

      function registerPluginLinks($links, $file) {
         if ($file == $this->base_name) {
            $links[] = '<a href="options-general.php?page=' . $this->base_name .'">' . __('Settings','amazon-link') . '</a>';
         }
         return $links;
      }

      function optionsMenu() {

         $my_page = add_options_page(__('Manage Amazon Wishlist', 'amazon-link'), __('Amazon Link', 'amazon-link'), 'manage_options', __FILE__, array($this, 'showOptions'));
         add_action( "admin_print_styles-$my_page", array($this,'headerContent') );
      }

      /// Load styles only on Our Admin page or when Wishlist is displayed...

      function headerContent() {
         // Allow the user to override our default styles. 
         if (file_exists(dirname (__FILE__).'/user_styles.css')) {
            $stylesheet = plugins_url("user_styles.css", __FILE__); 
         } else {
            $stylesheet = plugins_url("Amazon.css", __FILE__);
         }

         wp_enqueue_style('amazonlink-style', $stylesheet);
      }

      function loadLang() {
         /* load localisation  */
         load_plugin_textdomain('amazon-link', $this->plugin_dir . '/i18n', $this->plugin_dir . '/i18n');

         /* Move Option List construction here so we can localise the strings */
         $this->optionList = array(
         'title' => array ( 'Type' => 'title', 'Value' => __('Amazon Link Plugin Options')),
         'nonce' => array ( 'Type' => 'nonce', 'Name' => 'update-WishPics-options' ),
         'cat' => array ( 'Type' => 'hidden' ),
         'last' => array ( 'Type' => 'hidden' ),
         'asin' => array( 'Default' => '0', 'Type' => 'hidden'),
         'text' => array( 'Name' => __('Link Text', 'amazon-link'), 'Description' => __('Default text to display if none specified', 'amazon-link'), 'Default' => 'www.amazon.co.uk', 'Type' => 'text', 'Size' => '40'),
         'tld' => array( 'Name' => __('Amazon Domain', 'amazon-link'), 'Description' => __('Which country\'s Amazon domain to use', 'amazon-link'), 'Default' => 'co.uk', 'Type' => 'selection', 'Options' => array ('co.uk', 'com', 'ca', 'de', 'jp', 'fr')),
         'tag' => array( 'Name' => __('Affiliate Tag', 'amazon-link'), 'Description' => __('Amazon associates ID used to assign Amazon referral commissions', 'amazon-link'), 'Default' => 'livpauls-21', 'Type' => 'text'),
         'pub_key' => array( 'Name' => __('AWS Public Key', 'amazon-link'), 'Description' => __('Public key provided by your AWS Account', 'amazon-link'), 'Default' => '', 'Type' => 'text', 'Size' => '40'),
         'priv_key' => array( 'Name' => __('AWS Private key', 'amazon-link'), 'Description' => __('Private key provided by your AWS Account.', 'amazon-link'), 'Default' => "", 'Type' => 'text', 'Size' => '40'),
         'button' => array( 'Type' => 'buttons', 'Buttons' => array( __('Update Options', 'amazon-link' ) => array( 'Class' => 'button-primary', 'Action' => 'WishPicsAction'))));
      }

      function stylesNeeded($posts){
         if (empty($posts)) return $posts;
       
         $this->stylesNeeded = False;
         foreach ($posts as $post) {
            $this->contentFilter($post->post_content, False, False);
            if ($this->stylesNeeded) {
               $this->headerContent();
               break;
            }
         }
         return $posts;
      }

/*****************************************************************************************/
      /// Options
/*****************************************************************************************/

      function getOptions() {
         if (null === $this->Opts) {
            $this->Opts= get_option($this->optionName, array());
         }
         return $this->Opts;
      }

      function saveOptions($Opts) {
         if (!is_array($Opts)) {
            return;
         }
         update_option($this->optionName, $Opts);
         $this->Opts = $Opts;
      }

      function deleteOptions() {
         delete_option($this->optionName);
      }

      /*
       * Parse the arguments passed in.
       */
      function parseArgs($arguments) {

         $args = array();
         parse_str(html_entity_decode($arguments), $args);

         $Opts = $this->getOptions();
         unset($this->Settings);
         /*
          * Check for each setting, local overides saved option, otherwise fallback to default.
          */
         foreach ($this->optionList as $key => $details) {
            if (isset($args[$key])) {
               $this->Settings[$key] = $args[$key];              // Local setting
            } else if (isset($Opts[$key])) {
               $this->Settings[$key] = $Opts[$key];   // Global setting
            } else if (isset ($details['Default'])) {
               $this->Settings[$key] = $details['Default'];      // Use default
            }
         }
      }

/*****************************************************************************************/
      /// Searches through the_content for our 'Tag' and replaces it with the lists or links
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

               if (isset($this->Settings['cat']) && isset($this->Settings['last'])) {
                  if ($doRecs) {
                     $output = $this->showRecommendations($this->Settings['cat'],$this->Settings['last']);
                  } else {
                     $output = substr($content, $found, ($tagEnd - $found) + strlen($this->TagTail));
                     $this->stylesNeeded = True;
                  }
               } else if ($doLinks) {
                  // Generate Amazon Link
                  $this->tags[] = $this->Settings['asin'];
                  $output = $this->makeLink($this->Settings['text']);
               } else {
                  $this->tags[] = $this->Settings['asin'];
                  $output = substr($content, $found, ($tagEnd - $found) + strlen($this->TagTail));
               }

               $newContent = $newContent . substr($content, $index, ($found-$index));
               $newContent = $newContent . $output;
               $index = $tagEnd + strlen($this->TagTail);
            }
         }
         return $newContent;
      }

/*****************************************************************************************/
      /// Display Content
/*****************************************************************************************/

      function showRecommendations ($categories='1', $last='30') {
         return include('include/showRecommendations.php');
      }

      function showOptions() {
         include('include/showOptions.php');
      }

      public function getURL()
      {
         $text="http://www.amazon." . $this->Settings['tld']. "/gp/product/". $this->Settings['asin']. "?ie=UTF8&tag=" . $this->Settings['tag'] ."&linkCode=as2&camp=1634&creative=6738&creativeASIN=". $this->Settings['asin'];
         return $text;
      }

      public function doQuery($request)
      {
         return aws_signed_request($this->Settings['tld'], $request, $this->Settings['pub_key'], $this->Settings['priv_key']);
      }

      function makeLink($link_text)
      {
         $text='<a href="' . $this->getURL() .'">' .$link_text. '</a>';
         return $text;
      }

/////////////////////////////////////////////////////////////////////


   } // End Class

   $awlfw = new AmazonWishlist_For_WordPress();

} // End if exists

function amazon_get_link($args)
{
  global $awlfw;
  $awlfw->parseArgs($args);       // Get the default settings
  return $awlfw->getURL();        // Return a URL
}

function amazon_query($request)
{
  global $awlfw;
  $awlfw->parseArgs("");              // Get the default settings
  return $awlfw->doQuery($request);   // Return response

}

function amazon_recommends($categories='1', $last='30')
{
  global $awlfw;
  $awlfw->parseArgs("");              // Get the default settings
  return $awlfw->showRecommendations ($categories, $last);
}

?>