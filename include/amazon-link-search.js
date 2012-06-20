/**
 * Handle: amazon-link-search
 * Version: 0.0.1
 * Deps: jquery
 * Enqueue: true
 */

var wpAmazonLinkSearcher = function () {}

wpAmazonLinkSearcher.prototype = {
    search_options    : {},
    options    : {},
    sendingAmazonRequest : false,

    incPage : function(event) {
      var page = jQuery(event).find("#amazon-link-search[name='s_page']");
	if( !this['sendingAmazonRequest'] ) {
           jQuery(page).val(parseInt(jQuery(page).val())+1);
          this.searchAmazon(event);
        }
    },

    decPage : function(event) {
	if( !this['sendingAmazonRequest'] ) {
          var page = jQuery(event).find("#amazon-link-search[name='s_page']");
          var p = parseInt(jQuery(page).val())-1;
          if (p == 0) p =1;
          jQuery(page).val(p);
          this.searchAmazon(event);
        }
    },

    clearResults : function(event) {
        jQuery(event).find('#amazon-link-result-list').empty();
    },

   grabMedia: function(event, options) {
        var collection = jQuery(event).find("[id^=amazon-link-search]");
        var $ths = this;

        collection.each(function () {
           $ths['options'][this.name] = jQuery(this).val();
        });
        $ths['options']['action'] = 'amazon-link-get-image';

        if (options != undefined) {
           jQuery.extend($ths['options'], options); 
        jQuery('#upload-button-'+options['asin']).attr("disabled", true);
        jQuery('#upload-progress-'+options['asin']).removeClass('ajax-feedback');
        jQuery.post('admin-ajax.php', $ths['options'] , $ths.mediaDone, 'json');
	}
   },

   removeMedia: function(event, options) {
        var collection = jQuery(event).find("[id^=amazon-link-search]");
        var $ths = this;

        collection.each(function () {
           $ths['options'][this.name] = jQuery(this).val();
        });
        $ths['options']['action'] = 'amazon-link-remove-image';

        if (options != undefined) {
           jQuery.extend($ths['options'], options); 
        jQuery('#uploaded-button-'+options['asin']).attr("disabled", true);
        jQuery('#upload-progress-'+options['asin']).removeClass('ajax-feedback');
        jQuery.post('admin-ajax.php', $ths['options'] , $ths.mediaDone, 'json');
	}
   },

   mediaDone: function (response, status){
      if( response["in_library"] == false ) {
         // Hide Delete button, Show Upload button
         jQuery('#upload-progress-'+response['asin']).addClass('ajax-feedback');
         jQuery('#uploaded-button-'+response['asin']).addClass('al_show-0');
         jQuery('#uploaded-button-'+response['asin']).attr("disabled", false);
         jQuery('#upload-button-'+response['asin']).attr("disabled", false);
         jQuery('#upload-button-'+response['asin']).removeClass('al_hide-1');
      } else {
         // Hide Upload button, Show Delete button
         jQuery('#upload-progress-'+response['asin']).addClass('ajax-feedback');
         jQuery('#upload-button-'+response['asin']).addClass('al_hide-1');
         jQuery('#upload-button-'+response['asin']).attr("disabled", false);
         jQuery('#uploaded-button-'+response['asin']).attr("disabled", false);
         jQuery('#uploaded-button-'+response['asin']).removeClass('al_show-0');
      }
   },

   searchAmazon : function(event) {
        var collection = jQuery(event).find("[id^=amazon-link-search],input[name=asin]");
        var $ths = this;
	if( !this['sendingAmazonRequest'] ) {
           this['sendingAmazonRequest'] = true;
           collection.each(function () {
              $ths['search_options'][this.name] = jQuery(this).val();
           });
           $ths['search_options']['action'] = 'amazon-link-search';
           jQuery('#amazon-link-result-list').empty();
           jQuery('#amazon-link-error').hide();
           jQuery('#amazon-link-results').show();
           jQuery('#amazon-link-status').removeClass('ajax-feedback');
           jQuery.post('admin-ajax.php', $ths['search_options'] , $ths.showResults, 'json');
	}
   },

   showResults : function (response, status){
      wpAmazonLinkSearch['sendingAmazonRequest'] = false;
      jQuery('#amazon-link-status').addClass('ajax-feedback');
      if( response["success"] == 0 ) {
         jQuery('#amazon-link-results').hide();
         jQuery('#amazon-link-error').show();
         jQuery('#amazon-link-error').text((response['message']));
      } else {
         jQuery('#amazon-link-error').hide();
         jQuery('#amazon-link-results').show();
         for (index in response['items'])
         {
            jQuery('#amazon-link-result-list').append(response['items'][index]['template']);
         }
      }
   }
}

var wpAmazonLinkSearch = new wpAmazonLinkSearcher();
