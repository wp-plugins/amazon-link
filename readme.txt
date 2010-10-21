=== Amazon Link ===
Contributors: paulstuttard
Donate link: http://www.houseindorset.co.uk/plugins/
Tags: Amazon, links, wishlist, recommend, shortcode, ip2nation, localise
Requires at least: 2.9
Tested up to: 3.0.1
Stable tag: 1.3

Provides a shortcode for inserting links to Amazon products and also generate a short list (e.g. a wishlist) of related items into your site. 

== Description ==

This plugin has two main functions the first is used to quickly add a link to a product on the Amazon website using a referrer URL so that commission can be earned.

This is done by adding one of the following lines into an entry (page or post): `[amazon asin=<ASIN Number>&text=<link text>]`

Where ASIN Number is the unique amazon number used to identify products e.g. "1405235675". The Link Text is simply what you want to be shown for the link, e.g. "Mr. Good".

The second and more complex part is to create a short list of random items, related to other amazon items that are linked to in the last few posts of a particular category.

This is created by either putting the line `amazon_recommends(<Category>,<Number of Posts>)` in your template. Or putting the line `[amazon cat=<Category>&last=<Number of Posts>]` within a post or page.
Where 'Category' is a list of category ids to search within (e.g. as expected by the 'cat' argument of [query_posts](http://codex.wordpress.org/Template_Tags/query_posts#Parameters) function. The 'last' parameter is the number of posts to search through.

For example I use this feature to provide friends and family some ideas for presents, it is based on the Amazon Web Service
API and uses the 'CartSimilarities' feature to generate the list of items.

The links that are generated can optionally be localised to the Amazon store most likely to be used by the visitor to your site, this is achieved by installing the [ip2nation](http://www.ip2nation.com/) database and enabling 'localise links' option in the plugin settings.

Since the reliability of guessing your visitors best Amazon store is questionable, an alternative is to enable the 'Multinational Link' option, this will enable a small popup for each link allowing the site visitor to choose the most appropriate site (based on local or language).


On the Post and Page administrative pages there is also a box to help add the shortcodes, if you already know the ASIN then simply enter it into the ASIN input and click on 'Send To Editor'. If not then there is a facility to search Amazon, by Index, Product Title and or Product Author/Artist.

== Installation ==

1. Upload 'amazon-link.zip' to the '/wp-content/plugins/' directory and unzip it.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Update the settings (at least change the default Affiliate Tag), see the FAQ.
1. Place `[amazon asin=<ASIN>&text=<LINK TEXT>]` in a post or page to create a link.
1. Place `[amazon cat=<CATEGORY LIST>&last=<NUMBER OF POSTS>]` in a page or post to create a wishlist.
1. Optionally install your own style sheet in '/amazon-link/user_styles.css', see the FAQ.
1. Optionally install the ip2nation database to enable link localisation, see the FAQ.

*Warning!*

Installation of the ip2nation database is not validated, it simply downloads the latest database file from ip2nation and zaps it into your wordpress mysql database.
If the file is corrupted or maliciously changed then this operation could completely destroy your sites mysql database, if in doubt manually download the database from [ip2nation](http://www.ip2nation.com/) and check its content, currently it consists of 2 tables ip2nation and ip2nationCountries.

== Frequently Asked Questions ==

= Why doesn't the Wishlist function work? =

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

= Are the links localised to match my visitor's country of origin? =

The plugin has the option to install the ip2nation database which provides a lookup of visitors IP address to country code.
If you install the database and enable the 'localise links' option then it will point the amazon link to the 'best match' amazon site based on the visitors IP address location.

Obviously the database is not perfect and some people browse through proxies or through their company's firewall so it may get the wrong country of origin. Additionally there
are only 6 major Amazon sites (UK, France, Germany, US, Japan and Canada). So the plugin has to guess where a country's residents are most likely to shop online.

== Changelog ==

= 1.4 =
Added a simple widget to the post & page new/edit screen to assist in adding shortcodes to posts, providing a facility to search Amazon.

= 1.3 =
Add link localisation through IP address lookup, and support for all 6 amazon affiliate sites not just one via a image popup.

= 1.2 =
Improve options page processing.

= 1.1 =
Move options page into 'Options' section.
Corrected stylesheet content, updated styles & provide facility to override the stylesheet.
Add internationalisation hooks into plugin.

= 1.0 =
First Release

== Upgrade Notice ==

= 1.3 =
Upgrade if you want multinational link support or link localisation.

= 1.2 =
Minor internal structural changes, add options link from plugin page.

= 1.1 =
Upgrade if you wish to have internationalisation (i18n) support.

== Screenshots ==

1. This is the example wishlist taken from [www.HouseInDorset.co.uk](http://www.houseindorset.co.uk/plugins/amazon-link)

2. This shows the multinational Amazon link popup.

== Translations ==

The plugin comes with translation support but as yet no translations are included, please refer to the WordPress Codex for more information about activating the translation.
If you want to help to translate the plugin to your language, please have a look at the i18n/amazon-link.pot file which contains all definitions and may be used to create a language specific .po file, if you do then
contact me and I will add it to the plugin ready for the next update.

== Technical ==
The plugin relies upon the php script aws_signed_request kindly crafted by [Ulrich Mierendorff](http://mierendo.com/software/aws_signed_query/) to perform the requests to the Amazon service.

The plugin has two utility classes that might be of use to other plugin designers. The first is one for generating the options page (as well as the 'Add Amazon Link' meta box). The second is an AJAX facility for performing Amazon product searches and returning an array of product details, including a facility to fill in a HTML template with various attributes of the product using the patterns %TITLE%, %PRICE%, %AUTHOR%, etc. See the plugin source files for more details on how to utilise them.

== Settings ==

= Link Text =
If you do not specify the 'text' argument in your [amazon] shortcode, then this text will be used by default.
= Localise Amazon Link =
If this option is selected and the [ip2nation](http://www.ip2nation.com/) database has been installed then the plugin will attempt to use the most appropriate Amazon site when creating the link, currently supports <a href="http://www.amazon.co.uk">www.amazon.co.uk</a>, <a href="http://www.amazon.com">www.amazon.com</a>, <a href="http://www.amazon.ca">www.amazon.ca</a>, <a href="http://www.amazon.de">www.amazon.de</a>, <a href="http://www.amazon.fr">www.amazon.fr</a> and <a href="http://www.amazon.jp">www.amazon.jp</a>.
= Multinational Link =
If this option is selected then the plugin will enable a small popup menu of country specific links whenever the user's mouse rolls over the Amazon link, enabling them to select the site they feel is most appropriate.
= Default Country =
If localisation is not enabled, or has failed for some reason, then this is the default Amazon site to use for the link.
= AWS Public Key =
If you wish to use the wishlist/recommendations part of the plugin then you must have the appropriate AWS public and private key pair and enter them in these two settings. To get these keys simply register with the <a href="http://aws.amazon.com/">Amazon Web Service</a> site and this will provide you with the appropriate strings.
= AWS Private Key =
See above.
== Shortcode ==

= text =
The text used to generate the amazon link, Enter any plain string e.g. 'text=My Text'.

= asin =
The unique Amazon product ID, of the form '1405235675'. Enter as 'asin=1405235675'.

= cat =
When creating a wishlist you must specify the post category(s) through which to search for other Amazon links. Enter as 'cat=4,7'.

= last =
When creating a wishlist you must specify how many posts to search through for Amazon links. Enter as 'last=30'.

When using the shortcode it is possible to override the settings used in the options screen, currently available shortcode arguments include:

= localise =
Overides the 'Localise Amazon Link' setting. 0 to force the default country, 1 to force localisation.

= multi_cc =
Overides the 'Multinational Link' setting. 0 to disable the popup, 1 to enable the popup.

= default_cc =
Overides the 'Default Country' setting. Must be one of 'uk', 'us', 'ca', 'de', 'fr' or 'jp'.

= pub_key =
Overides the 'AWS Public Key' setting.

= priv_key =
Overides the 'AWS Private Key' setting.

== Disclosure ==

Note if you do not update the affiliate tags then I will earn the commission on any sales your links make! For which I would be very grateful, as soon as you change the settings this will ensure all links on your site will be credited to your affiliate account(s).

== Future Updates ==

There are a number of things I want to update the plugin to do, some of which have already been done in other plugins, but not quite how I would like. I would like to bring it all together in one plugin.
Features I will be adding to the plugin in the future:

* Allow the wishlist to search for legacy amazon links not just ones embedded in the shortcode.

