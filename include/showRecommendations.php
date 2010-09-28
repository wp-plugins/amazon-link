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
      $counter=1;
      foreach ($this->tags as $asin)
      {
          $request["Item." . $counter . ".ASIN"] = $asin;
          $request["Item." . $counter . ".Quantity"] = 1;
          $counter++;
      }

      $pxml = aws_signed_request($this->Settings['tld'], $request, $this->Settings['pub_key'], $this->Settings['priv_key']);
      if ($pxml === False) {
         $output .= __('Amazon query failed to return any results - Have you configured the AWS settings?', 'wish-pics');
      } else {
         $SimilarProducts=$pxml['Cart']['SimilarProducts']['SimilarProduct'];
      }


      for ($counter = 0; $counter < 4; $counter++) {
         $ASIN = $SimilarProducts[$counter]['ASIN'];
         $request = array("Operation"=>"ItemLookup","ItemId"=>$ASIN,"ResponseGroup"=>"Small,Images,Offers,Reviews,SalesRank","IdType"=>"ASIN","MerchantId"=>"Amazon","AssociateTag"=>$this->Settings['tag']);

         $pxml = aws_signed_request($this->Settings['tld'], $request, $this->Settings['pub_key'], $this->Settings['priv_key']);
         $result = $pxml['Items']['Item'];
         $r_title  = $result['ItemAttributes']['Title'];
         $r_artist = isset($result['ItemAttributes']['Artist'])  ? $result['ItemAttributes']['Artist'] :
                     (isset($result['ItemAttributes']['Author'])  ? $result['ItemAttributes']['Author'] :
                      (isset($result['ItemAttributes']['Creator']) ? $result['ItemAttributes']['Creator'] : '-'));

         if (isset($result['MediumImage']))
           $r_s_url  = $result['MediumImage']['URL'];
         else
           $r_s_url  = "http://images-eu.amazon.com/images/G/02/misc/no-img-lg-uk.gif";

         $r_url    = $result['DetailPageURL'];
         $r_rank   = $result['SalesRank'];
         $r_rating = $result['CustomerReviews']['AverageRating'];
         $r_price  = $result['Offers']['Offer']['OfferListing']['Price']['FormattedPrice'];

         $output .= "<div class='amazon_prod'>\n";
         $output .= "<div class='amazon_img_container'><A href='$r_url'><IMG class='amazon_pic' src='$r_s_url'></a></div>\n";
         $output .= "<div class='amazon_text_container'><p><a href='$r_url'>$r_title</a></p>";
         $output .= "<div class='amazon_details'><p>". sprintf(__('by %s', 'amazon-link'),$r_artist). "<br />";
         $output .= sprintf(__('Rank/Rating : %1$s/%2$s', 'amazon-link'),$r_rank,$r_rating) ."<br />";
         $output .= "<b>". __('Price', 'amazon-link'). " <span class='amazon_price'>$r_price</span></b></p></div></div></div>\n";
      } 
   } else {
      $output .= "<p>". sprintf(__('No [amazon] tags found in the last %1$s posts in categories %2$s', 'amazon-link'), $last, $categories). "</p>";
   }
   $output .= "</div>";
   return $output;

?>