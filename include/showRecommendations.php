<?php

   // First process all post content for the selected categories
   $content = '';
   $lastposts = get_posts("numberposts=$last&cat=$categories");
   foreach ($lastposts as $id => $post) {
      $content .= $post->post_content;
   }

   unset($this->tags);
   $this->tags = array();

   $this->contentFilter($content, FALSE, FALSE);

   $request = array("Operation" => "CartCreate",
                    "MergeCart" => "True",
                    "ResponseGroup" => "CartSimilarities",
                    "IdType"=>"ASIN",
                    "MerchantId"=>"Amazon",
                    "AssociateTag"=>$this->Settings['tag']);

   $output = '<div class="amazon_container">';
   if (count($this->tags) != 0)
   {

      // Get the Cart Similarities for the items found
      $counter=1;
      foreach ($this->tags as $asin)
      {
          $request["Item." . $counter . ".ASIN"] = $asin;
          $request["Item." . $counter . ".Quantity"] = 1;
          $counter++;
      }
      $pxml = $this->doQuery($request);
      if ($pxml === False) {
         $output .= __('Amazon query failed to return any results - Have you configured the AWS settings?', 'wish-pics');
      } else {
         $SimilarProducts=$pxml['Cart']['SimilarProducts']['SimilarProduct'];
      }

      // Get more detail for each item
      $results = array();
      for ($counter = 0; ($counter < $this->Settings['wishlist_items']) && ($counter < count($SimilarProducts)); $counter++) {
         $ASIN = $SimilarProducts[$counter]['ASIN'];
         $request = array("Operation"=>"ItemLookup","ItemId"=>$ASIN,"ResponseGroup"=>"Small,Images,Offers,Reviews,SalesRank","IdType"=>"ASIN","MerchantId"=>"Amazon","AssociateTag"=>$this->Settings['tag']);

         $pxml = $this->doQuery($request);
         if (isset($pxml['Items']['Item']))
            $results[] = $pxml['Items']['Item'];
      }

      // Use the parse_results facility in the Search class to format the output.
      $Settings = $this->Settings;
      $Settings['template'] = $this->Settings['wishlist_template'];
      $Settings['image_class'] = ' ';
      $data = $this->search->parse_results($results, $Settings);
      foreach ($data['items'] as $asin => $details) {
         $output .= $details['template'];
      }

   } else {
      $output .= "<p>". sprintf(__('No [amazon] tags found in the last %1$s posts in categories %2$s', 'amazon-link'), $last, $categories). "</p>";
   }
   $output .= "</div>";
   return $output;

?>