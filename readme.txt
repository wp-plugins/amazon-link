=== Amazon Link ===
Contributors: paulstuttard
Donate link: http://www.houseindorset.co.uk/plugins/
Tags: Amazon, links, wishlist, recommend, shortcode
Requires at least: 2.9
Tested up to: 3.0.1
Stable tag: 1.2

Provides a shortcode for inserting links to Amazon products and also generate a short list (e.g. a wishlist) of related items into your site. 

== Description ==

This plugin has two main functions the first is used to quickly add a link to a product on the Amazon website
using a referrer URL so that commission can be earned. The second and more complex part is to create a short 
list of random items, related to other Amazon items that are linked to in the last few posts of a particular category. 
For example I use this feature to provide friends and family some ideas for presents, it is based on the Amazon Web Service
API and uses the 'CartSimilarities' feature to generate the list of items.


== Installation ==

1. Upload 'amazon-link.zip' to the '/wp-content/plugins/' directory and unzip it.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Update the settings (at least change the default Affiliate Tag), see the FAQ.
1. Place `[amazon asin=<ASIN>&text=<LINK TEXT>]` in a post or page to create a link.
1. Place `[amazon cat=<CATEGORY LIST>&last=<NUMBER OF POSTS>]` in a page or post to create a wishlist.
1. Optionally install your own style sheet in '/amazon-link/user_styles.css', see the FAQ.

== Frequently Asked Questions ==

= Why doesn't the Wishlist function does not work? =

For this to work you must have set up a working [Amazon Web Services](http://aws.amazon.com/) account and set the AWS Public and
Private key settings to those provided in the AWS->Security Credentials->Access Credentials section.

= The list seems random? =

You must have inserted at least a few links to Amazon products using the [amazon] tag for it to generate a list of related product
suggestions.

= Can I change the styling of the wishlist? =

You can add the file 'user_styles.css' to the plugins directory overriding the default stylesheet, the wishlist has the following style elements:

*   amazon_container      - Encloses whole wishlist.
*   amazon_prod           - Encloses each list item.
*   amazon_img_container  - Encloses the item thumbnail (link+img)
*   amazon_pic            - Class of the item thumbnail IMG element
*   amazon_text_container - Encloses the item description (Title paragraphs+link + Details paragraphs)
*   amazon_details        - Encloses the item details part of the description
*   amazon_price          - Spans the item's formatted price.

== Changelog ==

= 1.2 =
Improve options page processing.

= 1.1 =
Move options page into 'Options' section.
Corrected stylesheet content, updated styles & provide facility to override the stylesheet.
Add internationalisation hooks into plugin.

= 1.0 =
First Release

== Upgrade Notice ==

= 1.2 =
Minor internal structural changes, add options link from plugin page.

= 1.1 =
Upgrade if you wish to have internationalisation (i18n) support.

== Screenshots ==

1. This is the example wishlist taken from [www.HouseInDorset.co.uk](http://www.houseindorset.co.uk/plugins/amazon-link)

== Translations ==

The plugin comes with translation support but as yet no translations are included, please refer to the WordPress Codex for more information about activating the translation.
If you want to help to translate the plugin to your language, please have a look at the i18n/amazon-link.pot file which contains all definitions and may be used to create a language specific .po file, if you do then
contact me and I will add it to the plugin ready for the next update.
