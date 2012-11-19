/**
 * Handle: wpAmazonLinkAdmin
 * Version: 0.0.1
 * Deps: jquery
 * Enqueue: true
 */


var wpAmazonLinkAdmin = function () {}


wpAmazonLinkAdmin.prototype = {
    options           : {},
    keywords          : {},
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
        if (!options['cc']) options['cc'] = '';
        var ASIN = jQuery(event).find("input[name='asin"+options['cc']+"']");
        if (ASIN.val() == "") {
           ASIN.val(options['asin']);
        } else {
           ASIN.val( ASIN.val()+"," + options['asin']);
        }
    },

    trans_update: function(result) {
        var s_title_trans = jQuery("input[name='s_title_trans']");
        s_title_trans.val( result );
    },

    translate : function(event, options) {

        var s_title = jQuery(event).find("input[name='s_title']").val();
        var s_title_trans = jQuery(event).find("input[name='s_title_trans']");
        var default_cc = jQuery(event).find("select[name='default_cc']").val();
        var home_cc = jQuery(event).find("input[name='home_cc']").val();
        var $ths = this;

        $ths['options']['action'] = 'amazon-link-translate';
        $ths['options']['Text'] = s_title;
        $ths['options']['To'] = AmazonLinkData[default_cc]['lang'];
        $ths['options']['From'] = AmazonLinkData[home_cc]['lang'];

        if (options != undefined) {
           jQuery.extend($ths['options'], options); 
        }
        jQuery.post('admin-ajax.php', $ths['options'] , $ths.trans_update, 'json');

    },


    generateArgs : function(cc) {
        var content = this['options']['content'];
        var list_options = this['list_options'];
        var d_options = this['default_options'];

        var live_keywords = new String(this['template_live_keywords']);
        var template_keywords = new String(this['template_keywords']);

        delete this['options']['content'];

        /* If 'use defaults' is set then reset to the defaults */
        if (this['options']['defaults'] == "1") {
           this['options']['multi_cc'] = d_options['multi_cc'];
           this['options']['localise'] = d_options['localise'];
           this['options']['live'] = d_options['live'];
           this['options']['search_link'] = d_options['search_link'];
        }

        /* If 'wishlist' is set then include wishlist specific options */
        if (this['options']['wishlist'] == "1") {
           jQuery().extend(this['options'], list_options);
           this['options']['live'] = 1;
        } 
 
        if (this['options']['asin'].indexOf(',') != -1) {
           this['options']['live'] = 1;
        }

        var shortcode_options = jQuery().extend({}, this.options);
        /* Only put keywords relevant to the selected template */
        for(var i = 0; i < this['keywords'].length; i++) {
           if ( ( (template_keywords.indexOf(this['keywords'][i]) == -1) ||     // If Not in the current template
                  ( (this['options']['live'] == "1") &&                          // or user wants live data and this is a live keyword
                    (live_keywords.indexOf(this['keywords'][i]) != -1) )
              ) ) {
              delete shortcode_options[this['keywords'][i]];
           } else if (shortcode_options[this['keywords'][i]] == undefined) {
              shortcode_options[this['keywords'][i]] = '-';
              this['options'][this['keywords'][i]] = '-';
           }
        }
        if (this['options']['ref']) shortcode_options['ref'] =this['options']['ref'];
        if (this['options']['asin']) shortcode_options['asin'] =this['options']['asin'];

        /* If 'use defaults' is set then do not force these options */
        if (this['options']['defaults'] == "1") {
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
                attrs += sep + name + cc + '='+value;
                sep = '&';
            }
        });
        return attrs;
    },


    generateShortCode : function() {

        var template = new String(this['shortcode']);
        args = this.generateArgs('');
        var $this = this;
        this['options']['args'] = args;
        this['options']['unused_args'] = args;
        this['keywords'].push('args');
        this['keywords'].push('unused_args');
        jQuery.each(this['keywords'], function (id, keyword){
           var match = template.match( new RegExp( '%'+keyword+'%','i'));
           template = template.replace( new RegExp( '%'+keyword+'%','gi'), $this['options'][keyword]);
           if (match) {
              $this['options']['unused_args'] = $this['options']['unused_args'].replace( new RegExp( '(&?)'+keyword+'=[^&]*(\\1?)&?','i'), '$2');
           }
        });
        return template;
    },

    grabSettings: function(f, options) {
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
        if ($this['template_user_keywords'] != undefined) {
           this['keywords'] = $this['template_user_keywords'].concat(',',$this['template_live_keywords']).split(',');
        }

        if (options != undefined) {
           jQuery().extend($this['options'], options);
        }
    },


    addShortcode: function(f, options) {

        var shortcode = jQuery("input[name='Shortcode']");
        this.grabSettings(f,options);
        shortcode.val(escape(this.generateArgs('['+this['options']['default_cc']+']')));
        return false;
    },

    sendToEditor      : function(f, options) {

        this.grabSettings(f,options);
        send_to_editor(this.generateShortCode(''));
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
