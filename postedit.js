/**
 * Handle: wpAmazonLinkAdmin
 * Version: 0.0.1
 * Deps: jquery
 * Enqueue: true
 */


var wpAmazonLinkAdmin = function () {}


wpAmazonLinkAdmin.prototype = {
    options           : {},
    default_options   : {},
    list_options      : {},

    toggleAdvanced : function(event) {
        var collection = jQuery(event).find("input[name='multi_cc'], input[name='localise'], input[name='live'], input[name='search_link']");
        var defaults   = jQuery(event).find("input[name='defaults']:checked").length;
        if (defaults) {
           jQuery(collection).parent().parent().hide();
        } else {
           jQuery(collection).parent().parent().show();
        }
    },

    addASIN : function(event, options) {
        var ASIN = jQuery(event).find("input[name='asin']");
        if (ASIN.val() == "") {
           ASIN.val(options['asin']);
        } else {
           ASIN.val( ASIN.val()+"," + options['asin']);
        }
    },

    generateShortCode : function() {
        var content = this['options']['content'];
        var options = this['options'];
        var list_options = this['list_options'];
        var d_options = this['default_options'];
        var keywords = this['template_user_keywords'].concat(',',this['template_live_keywords']).split(',');
        var live_keywords = new String(this['template_live_keywords']);
        var template_keywords = new String(this['template_keywords']);
        var template = new String(this['shortcode']);

        delete options['content'];

        /* If 'use defaults' is set then reset to the defaults */
        if (options['defaults'] == "1") {
           options['multi_cc'] = d_options['multi_cc'];
           options['localise'] = d_options['localise'];
           options['live'] = d_options['live'];
           options['search_link'] = d_options['search_link'];
        }

        /* If 'wishlist' is set then include wishlist specific options */
        if (options['wishlist'] == "1") {
           jQuery().extend(options, list_options);
           options['live'] = 1;
        } 
 
        if (options['asin'].indexOf(',') != -1) {
           options['live'] = 1;
        }

        var shortcode_options = jQuery().extend({}, options);
        /* Only put keywords relevant to the selected template */
        for(var i = 0; i < keywords.length; i++) {
           if ((keywords[i] != "asin") &&                             // If Not 'asin' - this is always inserted
               ((template_keywords.indexOf(keywords[i]) == -1) ||     // and Not in the current template
                ((options['live'] == "1") &&                          // or user wants live data and this is a live keyword
                 (live_keywords.indexOf(keywords[i]) != -1)))) {
              delete shortcode_options[keywords[i]];
           } else if (shortcode_options[keywords[i]] == undefined) {
              shortcode_options[keywords[i]] = '-';
              options[keywords[i]] = '-';
           }
        }

        /* If 'use defaults' is set then do not force these options */
        if (options['defaults'] == "1") {
           delete shortcode_options['multi_cc'];
           delete shortcode_options['localise'];
           delete shortcode_options['live'];
           delete shortcode_options['search_link'];
        }

        /* Delete temporary options only used by the java exchange */
        delete shortcode_options['image_url'];
        delete shortcode_options['thumb_url'];
        delete shortcode_options['defaults'];
        delete shortcode_options['wishlist'];
        delete shortcode_options['shortcode_template'];

        /* Now generate the short code with what is left */
        var attrs = '';
        var sep = '';
        jQuery.each(shortcode_options, function(name, value){
            if (value != ' ') {
                attrs += sep + name + '='+value;
                sep = '&';
            }
        });
        options['args'] = attrs;
        keywords.push('args');
        jQuery.each(keywords, function (id, keyword){
           template = template.replace( new RegExp( '%'+keyword+'%','gi'), options[keyword]);
        });
        return template;
    },

    sendToEditor      : function(f, options) {
        var link_options = jQuery(f).find("input[id^=AmazonLinkOpt], select[id^=AmazonLinkOpt]");
        var list_options = jQuery(f).find("input[id^=AmazonListOpt], select[id^=AmazonListOpt]");
        var $this = this;
        $this['options'] = {};
        $this['list_options'] = {};
        $this['default_options'] = {};
        link_options.each(function () {
            if (this.type == 'checkbox') {
               $this['options'][this.name] = this.checked ? "1" : "0";
               $this['default_options'][this.name] = (this.value != '1'? "1" : "0");
            } else if (this.type == "select-one") {
               $this['options'][this.name] = this[this.selectedIndex].value;
            } else {
               $this['options'][this.name] = this.value;
            }
        });

        list_options.each(function () {
            if (this.type == 'checkbox') {
               $this['list_options'][this.name] = this.checked ? "1" : "0";
            } else if (this.type == "select-one") {
               $this['list_options'][this.name] = this[this.selectedIndex].value;
            } else {
               $this['list_options'][this.name] = this.value;
            }
        });

        $this['shortcode']              = jQuery(f).find('#amazonLinkID input[name="shortcode_template"]').val();
        $this['template_user_keywords'] = jQuery(f).find('#amazonLinkID input[name="template_user_keywords"]').val();
        $this['template_live_keywords'] = jQuery(f).find('#amazonLinkID input[name="template_live_keywords"]').val();
        $this['template_keywords']      = jQuery(f).find('input[name="T_' + $this['options']['template'] + '"]').val();

        if (options != undefined) {
           jQuery().extend($this['options'], options);
        }
        send_to_editor(this.generateShortCode());
        return false;
    }
}

var wpAmazonLinkAd = new wpAmazonLinkAdmin();

jQuery(document).ready( function() {
    jQuery('.mypostbox h3').prepend('<a class="togbox">+</a> ');
    jQuery('.mypostbox').prepend('<div class="handlediv myhandle"><br></div> ');
    jQuery('.myhandle').click( function() {
        jQuery(jQuery(this).parent().get(0)).toggle();
    });
});
