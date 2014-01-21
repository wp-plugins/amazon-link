<?php

   $cc = $this->settings['local_cc'];

   // Search Query
   if (!empty($this->settings[$cc]['s_index'])) {
      if (empty($this->search)) {
         include ('amazonSearch.php');
         $this->search = new AmazonLinkSearch;
      }
      $request = $this->search->create_search_query($this->settings[$cc]);
      $request['ResponseGroup'] = 'ItemAttributes';
      $pxml = $this->doQuery($request);
      if (!empty($pxml['Items']['Item']))
      {
         $Items=$pxml['Items']['Item'];
         if (!array_key_exists('0', $Items)) {
            $Items = array('0'=> $Items);
         }
      } else {
         $output .= '<!--' . __('Amazon query failed to return any results - Have you configured the AWS settings?', 'amazon-link').'-->';
         $output .= '<!-- '. print_r($request, true) . '-->';
         $Items=array();
      }
      
      $ASINs = array();         
      foreach ($Items as $Item => $Details)
         $ASINs[] = array( $cc => $Details['ASIN']);
      $saved_tags = $this->tags;
      $this->tags = $ASINs;
      $this->settings[$cc]['wishlist_type'] = 'multi';

   // If using local tags then just process the ones on this page otherwise search categories.
   } else if (strcasecmp($this->settings[$cc]['cat'], 'local') != 0) {
      // First process all post content for the selected categories
      $content = '';
      $get_posts = new WP_Query;
      if (preg_match('!^[0-9,]*$!', $this->settings[$cc]['cat'])) {
         $lastposts = $get_posts->query(array('numberposts'=>$this->settings[$cc]['last'], 'cat'=> $this->settings[$cc]['cat']));
      } else {
         $lastposts = $get_posts->query(array('numberposts'=>$this->settings[$cc]['last'], 'category_name' => $this->settings[$cc]['cat']));
      }
      foreach ($lastposts as $id => $post) {
         $content .= $post->post_content;
      }
      unset($lastposts);
      $saved_tags = $this->tags;
      $settings = $this->settings;
      $this->tags = array();
      $this->content_filter($content, FALSE);
      unset($content);
      $this->settings = $settings;                   // Reset settings as content filter will overwrite them
      $this->Settings = &$this->settings['global'];
      unset($settings);

   }

   $output = '';
   
   if ((count($this->tags) != 0) && is_array($this->tags))
   {
      $output .= '<div class="amazon_container">';
      if (strcasecmp($this->settings[$cc]['wishlist_type'],'similar') == 0) {
 
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
             $asin = isset($asins[$cc]) ? $asins[$cc] : (isset($asins[$this->settings['default_cc']]) ? $asins[$this->settings['default_cc']] : '');

             if ((strlen($asin) > 8) && !in_array($asin,$unique_asins)) {
                $request["Item." . $counter . ".ASIN"] = $asin;
                $request["Item." . $counter . ".Quantity"] = 1;
                $counter++;
                $unique_asins[] = $asin;
             }
         }

         $pxml = $this->doQuery($request);
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
         if (strcasecmp($this->settings[$cc]['wishlist_type'],'random') == 0) {
            shuffle($this->tags);
            $ASINs = $this->tags;
         } else if (strcasecmp($this->settings[$cc]['wishlist_type'],'multi') == 0) {
            $ASINs = $this->tags;
         }
         $unique_asins = array();
         for ($index=0; $index < count($ASINs); $index++) {
            $asin = isset($ASINs[$index][$cc]) ? $ASINs[$index][$cc] : (isset($ASINs[$index][$this->settings['default_cc']]) ? $ASINs[$index][$this->settings['default_cc']] : '');
            if (in_array($asin, $unique_asins)) {
               unset($ASINs[$index]);
            } else {
               $unique_asins[] = $asin;
            }
         }
      }
      
      if ( is_array($ASINs) && !empty($ASINs)) {
         $this->settings[$cc]['live'] = 1;
         $this->settings['asin'] = array_slice($ASINs,0,$this->settings[$cc]['wishlist_items']);

         if (!isset($this->settings[$cc]['template'])) $this->settings[$cc]['template'] = $this->settings[$cc]['wishlist_template'];

         $output .= $this->make_links($this->settings);

      }
      $output .= "</div>";

   } else {
      $output .= "<!--". sprintf(__('No [amazon] tags found in the last %1$s posts in categories %2$s', 'amazon-link'), $this->settings[$cc]['last'], $this->settings[$cc]['cat']). "--!>";
   }
   if (isset($saved_tags)) {
      $this->tags = $saved_tags;
   }

   return $output;

?>
