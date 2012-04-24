<?php
/*****************************************************************************************/

/*
 * User Channel Option Panel Processing
 *
 */
   $channel = $this->get_user_options($user->ID);
   
   $channel_opts = $this->get_user_option_list();

/*****************************************************************************************/

   // **********************************************************
   // Now display the options editing screen
   $this->form->displayForm($channel_opts , $channel, True, True, True, False);

?>