<?php return array (
  'settings' => 
  array (
    'id' => 'amazon-link-settings-settings',
    'page' => 'settings',
    'title' => 'Settings',
    'content' => '
<p>The plugin Settings page is where the author can modify the behaviour of all the links embedded in their site.</p>
<p>Note any changes here will affect all existing links that do not explicitly set these options via the <a href=\'#shortcode\'>shortcode</a> content. These options can be viewed as the \'default\' behaviour any of which can be overridden locally by adding the appropriate parameter to the shortcode.</p>
<p>The Settings are split into 5 sections dealing with different aspects of the plugin:</p>
<ul>
<li>Display Options</li>
<li>Localisation Options</li>
<li>Amazon Associate Information</li>
<li>Amazon Data Cache</li>
<li>Advanced Options</li>
</ul>
',
  ),
  'display options' => 
  array (
    'id' => 'amazon-link-settings-display-options',
    'page' => 'settings',
    'title' => 'Display Options',
    'content' => '
<p>These options affect the default appearance and behaviour of standard text links and the type of Amazon Wishlist that is displayed.</p>
<h5>Link Text</h5>
<p>If you do not specify the \'text\' argument in your &#91;amazon] shortcode, then this text will be used by default. This option is equivalent to the \'text\' shortcode argument, and the %TEXT% keyword in the template.</p>
<h5>Image Class</h5>
<p>Allows the author to change the default class used when the plugin inserts a thumbnail or image into a post. This option is equivalent to the \'image_class\' shortcode argument, and the %IMAGE_CLASS% keyword in the template.</p>
<h5>Wishlist Template</h5>
<p>This is the default template used by the plugin when it auto generates a list of products using the wishlist facility. This option can be overridden in the shortcode by specifying a valid template using the \'template\' argument.</p>
<h5>Wishlist Length</h5>
<p>This is the maximum number of items to display in a wishlist. This option can be overridden in the shortcode by specifying the \'wishlist_items\' argument.</p>
<p>Note: Amazon only returns a maximum of 5, for the \'Similar\' type of list.</p>
<h5>Wishlist Type</h5>
<p>This sets the default type of wishlist to generate.  A wishlist of type \'Similar\' will show items similar to the ones found in the content. A wishlist of type \'Random\' will show a random selection of the ones found. A wishlist of type \'Multi\' simply lists the items in the order they were found. This option can be overridden in the shortcode by specifying the \'wishlist_type\' argument.</p>
<h5>New Window Link</h5>
<p>If this option is enabled then when generating links, the plugin will ensure that if they are clicked on it will open it in a new browser window. This option is equivalent to the \'new_window=1\' shortcode argument.</p>
',
  ),
  'localisation options' => 
  array (
    'id' => 'amazon-link-settings-localisation-options',
    'page' => 'settings',
    'title' => 'Localisation Options',
    'content' => '
<p>The localisation options control how the plugin deals with visitors from countries other than your default locale. The majority of these options require the ip2nation database to be installed.</p>
<p>At the top of this section is the current status of the ip2nation database and buttons to allow you to Uninstall or Install the database.</p>
<h5>Default Country</h5>
<p>If localisation is not enabled, or has failed for some reason, then this is the default Amazon site to use for the link.</p>
<h5>Localise Amazon Link</h5>
<p>If this option is selected and the <a href="http://www.ip2nation.com/">ip2nation</a> database has been installed then the plugin will attempt to use the most appropriate Amazon site when creating the link, currently supports <a href="http://www.amazon.co.uk">www.amazon.co.uk</a>, <a href="http://www.amazon.com">www.amazon.com</a>, <a href="http://www.amazon.ca">www.amazon.ca</a>, <a href="http://www.amazon.cn">www.amazon.cn</a>, <a href="http://www.amazon.de">www.amazon.de</a>, <a href="http://www.amazon.es">www.amazon.es</a>, <a href="http://www.amazon.fr">www.amazon.fr</a>, <a href="http://www.amazon.it">www.amazon.it</a> and <a href="http://www.amazon.co.jp/">www.amazon.jp</a>.</p>
<h5>Global Defaults</h5>
<p>When creating Amazon Link items the plugin will use data provided in the shortcode in preference to data retrieved from Amazon (e.g. Setting the \'<code>Title=My Favourite Book</code>\' in the shortcode will override the Title retrieved from Amazon. If you have localisation enabled, by default the data in the shortcode will only override your default locale. Enable this option if you want shortcode data to override the Amazon data in <em>all</em> locales.</p>
<h5>Create Search Links</h5>
<p>If localisation or the Multinational popup are enabled then the plugin will create links to Amazon sites other than your default locale. Sometimes these links either do not work - for example that exact product ASIN is not available in that country. Or is not suitable - for example it is an incorrect region DVD or a book in the wrong language.</p>
<p>Selecting this option will force the plugin to generate links to non-local Amazon sites that search for your product rather than an exact ASIN link. The exact terms of the search are determined by the next option.</p>
<p>This option can be overridden in the shortcode by specifying the \'search_link=1\' or \'search_link=0\' argument.</p>
<h5>Default Search String</h5>
<p>If the \'Create Search Links\' option is enabled then this string determines what keywords are used to create the search link. Any of the keywords used in the Templates can be used to generate the term, for example the keyword \'%ARTIST%\' will be expanded by the plugin to the Author/Artist/Director of the linked item.</p>
<p>This option can be overridden in the shortcode by specifying the \'search_text=Specific Search String\' argument.</p>
<h5>Multinational Link</h5>
<p>If this option is selected then the plugin will enable a small popup menu of country specific links whenever the user\'s mouse rolls over the Amazon link, enabling them to select the site they feel is most appropriate.</p>
',
  ),
  'amazon associate information' => 
  array (
    'id' => 'amazon-link-settings-amazon-associate-information',
    'page' => 'settings',
    'title' => 'Amazon Associate Information',
    'content' => '
<p>To get the most out of the Amazon Link plugin it is highly recommended that you have access to the Amazon Product Advertising API and populate the \'Amazon Web Services\' (AWS) key settings.</p>
<p>This will enable a number of features of the plugin that require access to Amazon Web Services (AWS). These include the generation of live data when displaying the links, providing a product search facility on the post/page edit admin screen, and the ability to generate product wishlists & recommendation. </p>
<p>If you wish to use any of these features then you must have the appropriate AWS Access IDs and enter them in these two settings. To get these keys simply register with the <a href="http://aws.amazon.com/">Amazon Web Service</a> site and this will provide you with the appropriate strings. Note you must also enable the Product Advertising API in your AWS account for this to work correctly.</p>
<p>See the \'<strong>Getting Started</strong>\' section for a guide to joining the various Amazon Affiliate programmes & the Amazon Advertising API.</p>
<h5>AWS Public Key</h5>
<p>Enter the \'Access Key ID\' found in your AWS Account under \'Access Credentials\'</p>
<h5>AWS Private Key</h5>
<p>Enter the \'Secret Access Key\' found in your AWS Account under \'Access Credentials\'</p>
<p>Once you have entered your AWS Access keys and updated the options the <strong>AWS Keys Validated</strong> checkbox should be ticked. If it is not the Error Message displayed should provide a clue to why the keys are not working.</p>
<h5>Live Data</h5>
<p>If this option is enabled then the plugin will attempt to retrieve up to date (and localised) information on the product when generating the product link. For this to work the AWS Access keys in the global settings must be configured with valid keys. If not enabled then the plugin will only use the information included in the shortcode. This option can be overridden in the shortcode by specifying the \'live=1\' or \'live=0\' argument.</p>
<p>Note this option also changes the behaviour of the Amazon Search Tool. When the tool is used to insert shortcodes into the post it will automatically prefill the keywords needed for the selected template. It will only do this if live data is not enabled.</p>
',
  ),
  'amazon data cache' => 
  array (
    'id' => 'amazon-link-settings-amazon-data-cache',
    'page' => 'settings',
    'title' => 'Amazon Data Cache',
    'content' => '
<p>If you use the plugin to its full extent and display alot of content retrieved from the Amazon Web Service it is recommended that you enable the Amazon Data Cache.</p>
<p>This will improve page load times for pages that retrieve live data from the Amazon Web Service by keeping a local copy of the data.</p>
<p>At the bottom of this section are buttons to Enable, Disable (and remove) and Flush the content of the Amazon Data Cache.</p>
<h5>Cache Data Age</h5>
<p>Amazon recommend that data is not stored in caches for too long, especially price and availability information. Adjust this setting depending on the volatility of the data you display (e.g. Titles List Prices and Artist information rarely change, however Offer prices may change more frequently).</p>
',
  ),
  'advanced options' => 
  array (
    'id' => 'amazon-link-settings-advanced-options',
    'page' => 'settings',
    'title' => 'Advanced Options',
    'content' => '
<h5>Template ASINs</h5>
<p>This setting only affects the Template Previews in the Template Manager section of the Amazon Link Settings page. Change this list of ASINs to change which ASIN(s) are used to generate the Template Previews.</p>
<h5>Debug</h5>
<p>If you are having problems with the plugin and need to contact me, it may be helpful if you could enable this option briefly. It causes the plugin to put extra hidden output in your sites pages that are displaying amazon links, which I can use to diagnose any problems.</p>
<p>It is not recommended that this option is enabled for any length of time as it will show your AWS access keys in the page html source.</p>
<h5>Purge on Uninstall</h5>
<p>If you want to permanently uninstall the plugin then select this option before uninstalling on the \'Installed Plugins\' page. This will ensure that all Amazon Link Settings, Templates, Associate Tracking IDs, Cache Data and the ip2nation data are removed from the WordPress database.</p>
',
  ),
);?>