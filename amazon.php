<?php

/*
Plugin Name: Amazon Link
Plugin URI: http://www.houseindorset.co.uk/plugins/Amazon-Link
Description: Insert a link to Amazon using the passed ASIN number, with the required affiliate info.
Version: 1.0
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
	CSS span:						amz_span
	CSS link:						        amz_link
*/

require_once("aws_signed_request.php");

if (!class_exists('AmazonWishlist_For_WordPress')) {
   class AmazonWishlist_For_WordPress {

/*****************************************************************************************/
      /// Settings:
/*****************************************************************************************/
      var $optionList = array(
         'cat' => array ( 'Type' => 'hidden' ),
         'last' => array ( 'Type' => 'hidden' ),
         'asin' => array( 'Default' => '0', 'Type' => 'hidden'),
         'text' => array( 'Name' => "Link Text", 'Description' => "Default text to display if none specified", 'Default' => 'www.amazon.co.uk', 'Type' => 'text'),
         'tld' => array( 'Name' => "Amazon Domain", 'Description' => "Which country's Amazon domain to use", 'Default' => 'co.uk', 'Type' => 'selection', 'Options' => array ('co.uk', 'com', 'ca', 'de', 'jp', 'fr')),
         'tag' => array( 'Name' => "Affiliate Tag", 'Description' => "Amazon associates ID used to assign Amazon referral commissions", 'Default' => 'livpauls-21', 'Type' => 'text'),
         'pub_key' => array( 'Name' => "AWS Public Key", 'Description' => "Public key provided by your AWS Account", 'Default' => '', 'Type' => 'text'),
         'priv_key' => array( 'Name' => "AWS Private key", 'Description' => "Private key provided by your AWS Account.", 'Default' => "", 'Type' => 'text'));

      // String to insert into Posts to indicate where to insert the amazon items
      var $TagHead    = '[amazon';
      var $TagTail    = ']';

      var $optionName = 'AmazonLinkOptions';
      var $Opts       = null;

      function AmazonWishlist_For_WordPress() {
         $this->__construct();
      }

      function __construct() {
         add_filter('the_content', array($this, 'contentFilter'));
         add_filter('the_posts', array($this, 'stylesNeeded'));
         add_action('admin_menu', array($this, 'optionsMenu'));
         $this->URLRoot = plugins_url("", __FILE__);
      }

      function optionsMenu() {
         $mypage = add_management_page('Manage Amazon Wishlist', 'AmazonLink', 8, __FILE__, array($this,'showOptions'));
         add_action( "admin_print_styles-$mypage", array($this,'headerContent') );
      }

      /// Load styles only on Our Admin page or when Wishlist is displayed...

      function headerContent() {
         $stylesheet = plugins_url("Amazon.css", __FILE__);
         wp_enqueue_style('amazonlink-style', $stylesheet);
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