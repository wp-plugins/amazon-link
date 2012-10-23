<?php return array (
  'extras' => 
  array (
    'id' => 'amazon-link-extras-extras',
    'page' => 'extras',
    'title' => 'Extras',
    'content' => '
<p>On the Amazon Link Extras Settings page you can manage plugins that add extra functionality to the main Amazon Link plugin. These plugins are either user provided or have been requested by users of the Amazon Link plugin. However although useful they may come with some performance or database impact. As such they are not built into the Amazon Link plugin by default.</p>
<p>The plugins use the filters and action hooks built into the main Amazon Link plugin to modify its behaviour (see the \'Filters\' section), any changes made on this page will cause the Amazon Link Cache to be emptied.</p>
<p>It is recommended that if you wish to modify the behaviour of Amazon Link plugin then create your own plugins (using the provided ones as a template). They are independent of the main plugin and so will survive any upgrades to the main plugin.</p>
<p>Currently there are two Amazon Link Extra plugins:</p>
<ul>
<li>Editorial Content</li>
<li>Spoof Locale</li>
</ul>
',
  ),
  'filters' => 
  array (
    'id' => 'amazon-link-extras-filters',
    'page' => 'extras',
    'title' => 'Filters',
    'content' => '
<p>The plugin exposes three filters that can be accessed via the standard WordPress <a href="http://codex.wordpress.org/Plugin_API#Filters">Filter</a> API:</p>
<ul>
<li>amazon_link_keywords</li>
<li>amazon_link_opts_list</li>
<li>amazon_link_default_templates</li>
</ul>
<p>It is also possible to add your own filters to process individual data items returned via Amazon by adding a \'Filter\' item using the \'amazon_link_keywords\' filter. See the \'Editorial Content\' plugin for an example of how to do this.</p>
<p>The plugin exposes one action hook that can be used via the standard WordPress Action API:</p>
<ul>
<li>amazon_link_init($settings)</li>
</ul>
',
  ),
  'amazon_link_keywords' => 
  array (
    'id' => 'amazon-link-extras-amazon-link-keywords',
    'page' => 'extras',
    'title' => 'amazon_link_keywords',
    'content' => '
<p>This filter allows developers the ability to change the template keywords used by the plugin, it passes an array with a entry for each keyword. This allows developers to add new keywords, change existing ones or remove unwanted keywords.</p>
<p>Each keyword has the following elements:</p>
<p><em>keyword</em><br />
This is the index in the keywords array and is used to identify the keyword and is what is searched for in the template. Must be lower case.</p>
<p><em>Description</em><br />
This is the textual description that is displayed in the Template Help section.</p>
<p><em>User</em><br />
This indicates that this is a text field that the user can populate.</p>
<p><em>Live</em><br />
This is set if the keyword is retrieved from the Amazon Web Service API.</p>
<p>The following elements are only required for \'Live\' items.</p>
<p><em>Position</em><br />
This is an array of arrays (in order of preference) determining how to traverse the AWS Item to get the the AWS information.</p>
<p><em>Group</em><br />
This is a comma separated list of the AWS Response Group(s) needed to return this item\'s details in the AWS data.</p>
<p><em>Default</em><br />
This is the default value if no data is returned from the AWS query.</p>
<p><em>Filter</em><br />
This is any filter that should be applied to the returned AWS data before storing in the cache and being used in the template. See the \'amazon_link_editorial\' example below.</p>
<p>Example:</p>
<pre lang=\'php\'>

function my_keywords_filter($keywords) {
 $keywords[\'artist\'] = array(\'Description\' => \'Item\'s Author, Artist or Creator\',
                             \'live\' => \'1\', 
                             \'Group\' => \'Small\', 
                             \'Default\' => \'-\',
                             \'Position\' => array( array(\'ItemAttributes\',\'Artist\'),
                                                  array(\'ItemAttributes\',\'Author\'),
                                                  array(\'ItemAttributes\',\'Director\'),
                                                  array(\'ItemAttributes\',\'Creator\'),
                                                  array(\'ItemAttributes\',\'Brand\')))
 return $keywords;
}
add_filter(\'amazon_link_keywords\', \'my_keywords_filter\', 1);
</pre>
<p>If you add any filters of your own you must flush the Plugin\'s Product Cache to remove stale data.</p>
',
  ),
  'amazon_link_option_list' => 
  array (
    'id' => 'amazon-link-extras-amazon-link-option-list',
    'page' => 'extras',
    'title' => 'amazon_link_option_list',
    'content' => '
<p>This filter allows developers the ability to change the options used by the plugin, it passes an array with a entry for each option. This allows developers to add new options (or even change existing ones or remove unwanted options - not recommended!).</p>
<p>Each option has the following elements:</p>
<p><em>Name</em><br />
Name of the Option.<br />
<em>Description</em><br />
Short Description of the option.<br />
<em>Hint</em><br />
Hint that is shown if the user hovers the mouse over this option (e.g. on a selection option).<br />
<em>Default</em><br />
The default value this option has if it is not set.<br />
<em>Type</em><br />
What type of option is this. Can be one of:</p>
<ul>
<li>text</li>
<li>checkbox</li>
<li>selection</li>
<li>hidden</li>
<li>title</li>
<li>textbox</li>
<li>radio</li>
</ul>
<p><em>Class</em><br />
Class of the option as displayed on the options page.<br />
<em>Options</em><br />
An array of options for the \'selection\' and \'radio\' type of option.<br />
<em>Length</em><br />
Length of the \'text\' option type.<br />
<em>Rows</em><br />
Number of rows in the \'textbox\' option type.<br />
<em>Read_Only</em><br />
Set to 1 if this option can not be modified by the user.</p>
',
  ),
  'amazon_link_default_templates' => 
  array (
    'id' => 'amazon-link-extras-amazon-link-default-templates',
    'page' => 'extras',
    'title' => 'amazon_link_default_templates',
    'content' => '
<p>If you have built up a library of templates you can use this filter to add those templates to the defaults the Amazon Link plugin provides. If you do a new install or have multiple sites it provides a way to keep the same templates on all sites.</p>
<p>The filter is passed the default templates array in the form:</p>
<pre lang=\'php\'>
   \'image\' =>     array ( \'Name\' => \'Image\', 
                          \'Description\' => \'Localised Image Link\', 
                          \'Content\' => $image_template, 
                          \'Type\' => \'Product\',
                          \'Version\' => \'2\', 
                          \'Notice\' => \'Add impression tracking\', 
                          \'Preview_Off\' => 0 ),
   \'mp3 clips\' => array ( \'Name\' => \'MP3 Clips\', 
                          \'Description\' => \'Amazon MP3 Clips Widget (limited locales)\',
                          \'Content\' => $mp3_clips_template, 
                          \'Version\' => \'1\', 
                          \'Notice\' => \'\', 
                          \'Type\' => \'Multi\', 
                          \'Preview_Off\' => 0 )
</pre>
<p>Use the filter to change the defaults or add your own default templates. Each template has the following elements:</p>
<p><em>Name</em><br />
The name of the template usually matches the template ID used in the index.<br />
<em>Description</em><br />
A short description of the template.<br />
<em>Content</em><br />
The actual template content it is recommend that it is run through the \'htmlspecialchars\' function to ensure any odd characters are escaped properly.<br />
<em>Version</em><br />
The current version of this template, should be a number, e.g. \'2.1\'.<br />
<em>Notice</em><br />
An upgrade notice, what has changed since the last version.<br />
<em>Type</em><br />
The type of the template usually \'Product\', can be:</p>
<ul>
<li>Product</li>
<li>No ASIN</li>
<li>Multi</li>
</ul>
<p><em>Preview_Off</em><br />
If this template should not be previewed on the Options page, e.g. it is javascript.</p>
',
  ),
);?>