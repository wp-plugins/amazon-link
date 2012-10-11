jQuery(document).ready(function(){
		jQuery('.al_section h4').click(function(){
			jQuery(this).parent().next('.al_options').toggle();
		});
});
