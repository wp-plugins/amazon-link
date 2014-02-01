<?php

   $cc = $settings['local_cc'];

   // Search Query
   if (!empty($settings[$cc]['s_index'])) {
      if (empty($this->search)) {
         include ('amazonSearch.php');
         $this->search = new AmazonLinkSearch;
      }
      $request = $this->search->create_search_query($settings[$cc]);
      $request['ResponseGroup'] = 'ItemAttributes';
      $pxml = $this->doQuery( $request, $settings[$cc] );
      if (!empty($pxml['Items']['Item']))
      {
         $Items=$pxml['Items']['Item'];
         if (!array_key_exists('0', $Items)) {
            $Items = array('0'=> $Items);
         }
      } else {
         $output  = '<!--' . __('Amazon query failed to return any results - Have you configured the AWS settings?', 'amazon-link').'-->';
         $output .= '<!-- '. print_r($request, true) . '-->';
         $Items=array();
      }
      
      $ASINs = array();         
      foreach ($Items as $Item => $Details)
         $ASINs[] = array( $cc => $Details['ASIN']);
      $saved_tags = $this->tags;
      $this->tags = $ASINs;
      $settings[$cc]['wishlist_type'] = 'multi';
      // Force to non-localised as the search results are from this locale not the default one
      $settings[$cc]['default_cc'] = $cc;
      $settings[$cc]['home_cc'] = $cc;

   // If using local tags then just process the ones on this page otherwise search categories.
   } else if (strcasecmp($settings[$cc]['cat'], 'local') != 0) {
      // First process all post content for the selected categories
      $content = '';
      $get_posts = new WP_Query;
      if (preg_match('!^[0-9,]*$!', $settings[$cc]['cat'])) {
         $lastposts = $get_posts->query(array('numberposts'=>$settings[$cc]['last'], 'cat'=> $settings[$cc]['cat']));
      } else {
         $lastposts = $get_posts->query(array('numberposts'=>$settings[$cc]['last'], 'category_name' => $settings[$cc]['cat']));
      }
      foreach ($lastposts as $id => $post) {
         $content .= $post->post_content;
      }
      unset($lastposts);
      $saved_tags = $this->tags;
      $this->tags = array();
      $this->content_filter($content, FALSE);
      unset($content);
      $this->Settings = &$settings['global'];

   }

   $output = '';
   
   if ((count($this->tags) != 0) && is_array($this->tags))
   {
      $output .= '<div class="amazon_container">';
      if (strcasecmp($settings[$cc]['wishlist_type'],'similar') == 0) {
 
         $request = array("Operation" => "CartCreate",
                          "MergeCart" => "True",
                          "ResponseGroup" => "CartSimilarities",
                          "IdType"=>"ASIN",
                          "MerchantId"=>"Amazon");
         // Get the Cart Similarities for the items found
         $counter=1;
         $unique_asins = array();

         foreach ($this->tags as $asins)
         {
             $asin = isset($asins[$cc]) ? $asins[$cc] : (isset($asins[$settings['default_cc']]) ? $asins[$settings['default_cc']] : '');

             if ((strlen($asin) > 8) && !in_array($asin,$unique_asins)) {
                $request["Item." . $counter . ".ASIN"] = $asin;
                $request["Item." . $counter . ".Quantity"] = 1;
                $counter++;
                $unique_asins[] = $asin;
             }
         }

         $pxml = $this->doQuery( $request, $settings[$cc] );
         if (!empty($pxml['Cart']['SimilarProducts']['SimilarProduct']))
         {
            $Items=$pxml['Cart']['SimilarProducts']['SimilarProduct'];
            if (!array_key_exists('0', $Items)) {
               $Items = array('0'=>$Items);
            }
         } else {
            $output .= '<!--' . __('Amazon query failed to return any results - Have you configured the AWS settings?', 'amazon-link').'-->';
            $output .= '<!-- '. print_r($request, true) . '-->';
            $Items=array();
         }
        
         $ASINs = array();         
         foreach ($Items as $Item => $Details)
            $ASINs[] = $Details['ASIN'];

      } else {
         if (strcasecmp($settings[$cc]['wishlist_type'],'random') == 0) {
            shuffle($this->tags);
            $ASINs = $this->tags;
         } else if (strcasecmp($settings[$cc]['wishlist_type'],'multi') == 0) {
            $ASINs = $this->tags;
         }
         $unique_asins = array();
         for ($index=0; $index < count($ASINs); $index++) {
            $asin = isset($ASINs[$index][$cc]) ? $ASINs[$index][$cc] : (isset($ASINs[$index][$settings['default_cc']]) ? $ASINs[$index][$settings['default_cc']] : '');
            if (in_array($asin, $unique_asins)) {
               unset($ASINs[$index]);
            } else {
               $unique_asins[] = $asin;
            }
         }
      }
      
      if ( is_array($ASINs) && !empty($ASINs)) {
         $settings[$cc]['live'] = 1;
         $settings['asin'] = array_slice($ASINs,0,$settings[$cc]['wishlist_items']);

         if (!isset($settings[$cc]['template'])) $settings[$cc]['template'] = $settings[$cc]['wishlist_template'];

         $output .= $this->make_links($settings);

      }
      $output .= "</div>";

   } else {
      $output .= "<!--". sprintf(__('No [amazon] tags found in the last %1$s posts in categories %2$s', 'amazon-link'), $settings[$cc]['last'], $settings[$cc]['cat']). "--!>";
   }
   if (isset($saved_tags)) {
      $this->tags = $saved_tags;
   }

   return $output;

?>
