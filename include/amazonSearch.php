<?php
/*****************************************************************************************/

/*
 * Amazon Link Search Class
 *
 * Provides a facility to do simple Amazon Searches via the ajax interface and return results in
 * an array.
 *
 * To use the default script and styles you must add the following on init (before the header).
 *    - wp_enqueue_script('amazon-link-search')
 *    - wp_enqueue_styles('amazon-link-styles')
 *
 * The page must consist of a form with input elements all with the id='amazon-link-search', and
 * with the following names:
 *    - title
 *    - index
 *    - author
 *    - page
 *    - template
 *
 * To initiate a search there must be an element in the form which triggers the javascript:
 * 'return wpAmazonLinkSearch.searchAmazon(this.form);'
 * 
 * The results are inserted into the html element on the page with the id='amazon-link-result-list'.
 * Which should be contained within an element of id='amazon-link-results', there should also be a hidden
 * element with the id='amazon-link-error' to report any errors that occur. As well as an element with the
 * id='amazon-link-status' to indicate a search in progress.
 *
 * The values of the form input items are used to control the search, 'title', 'author' are used as search terms,
 * 'index' should be a valid amazon search index (e.g. Books). 'page' should be used to set which page of the results
 * is to be displayed.
 * 'template' can be used to get the search engine to populate a predefined html template with values - this should be htmlencoded.
 * the following terms are replaced with values relevant to the search results:
 *    - %ASIN%
 *    - %TITLE%
 *    - %ARTIST%
 *    - %MANUFACTURER%
 *    - %THUMB%
 *    - %IMAGE%
 *    - %URL%
 *    - %RANK%
 *    - %RATING%
 *    - %PRICE%
 */

if (!class_exists('AmazonLinkSearch')) {
   class AmazonLinkSearch {

      function AmazonLinkSearch() {
         $this->__construct();
      }

      function __construct() {
         $this->URLRoot = plugins_url("", __FILE__);
         $this->base_name  = plugin_basename( __FILE__ );
         $this->plugin_dir = dirname( $this->base_name );
         add_action('init', array($this,'init'));
      }

      function init() {
         $script = plugins_url("amazon-link-search.js", __FILE__);
         wp_register_script('amazon-link-search', $script, array('jquery'), '1.0.0');
         add_action('wp_ajax_amazon-link-search', array($this, 'performSearch'));    // Handle ajax search requests
      }

      function performSearch() {
         $Opts = $_POST;

         if ($Opts['index'] == 'Books') {
            $Term = "Author";
         } else if ($Opts['index'] == 'Music') {
            $Term = "Artist";
         } else if ($Opts['index'] == 'DVD') {
            $Term = "Publisher";
         } else {
            $Term = "Manufacturer";
         }

         // Create query to retrieve the first 10 matching items
         $request = array("Operation" => "ItemSearch",
                          "ResponseGroup" => "Small,Reviews,Images,Offers,SalesRank",
                          $Term=>$Opts['author'],
                          "Title"=>$Opts['title'],
                          "SearchIndex"=>$Opts['index'],
                          "Sort"=>"salesrank",
                          "MerchantId"=>"Amazon",
                          "ItemPage"=>$Opts['page']);

         $pxml = amazon_query($request);

         if (($pxml === False) || !isset($pxml['Items']['Item'])) {
            $results = array('success' => false);
            $Items = array();
         } else {
            $results = array('success' => true);
            $Items=$pxml['Items']['Item'];
         }
 
         if (count($Items) > 0) {
            for ($counter = 0; $counter < count($Items) ; $counter++) {
               $result = $Items[$counter];
               $data = array();
               $data['asin']   = $result['ASIN'];
               $data['title']  = $result['ItemAttributes']['Title'];
               $data['artist'] = isset($result['ItemAttributes']['Artist']) ? $result['ItemAttributes']['Artist'] :
                           (isset($result['ItemAttributes']['Author']) ? $result['ItemAttributes']['Author'] :
                            (isset($result['ItemAttributes']['Creator']) ? $result['ItemAttributes']['Creator'] : '-'));
               $data['manufacturer'] = isset($result['ItemAttributes']['Manufacturer']) ? $result['ItemAttributes']['Manufacturer'] : '-';

               if (isset($result['MediumImage']))
                 $data['thumb'] = $result['MediumImage']['URL'];
               else
                 $data['thumb'] = "http://images-eu.amazon.com/images/G/02/misc/no-img-lg-uk.gif";

               if (isset($result['LargeImage']))
                  $data['image'] = $result['LargeImage']['URL'];
               else
                  $data['image'] = $r_s_url;

               $data['url']     = $result['DetailPageURL'];
               $data['rank']    = $result['SalesRank'];
               $data['rating']  = isset($result['CustomerReviews']['AverageRating']) ? $result['CustomerReviews']['AverageRating'] : '-';
               $data['price']   = $result['Offers']['Offer']['OfferListing']['Price']['FormattedPrice'];
               $data['id']      = $ASIN;
               $data['type']    = 'Amazon';
               $data['template'] = $this->process_template($data, htmlspecialchars_decode (stripslashes($Opts['template'])));
               $results['items'][$data['asin']] = $data;
            }
         }
         print json_encode($results);
         exit();
      }

      function process_template ($data, $template) {
         foreach ($data as $key => $string)
            $template = str_replace('%'. strtoupper($key) . '%', $string, $template);
         return $template;
      }

   }
}
?>