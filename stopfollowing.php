<?php

	require_once("inc/cl_datafunctions.php");
	require_once("inc/cl_facebookinit.php");

	parse_str($_SERVER['QUERY_STRING']);
	remove_follower_from_talent(get_crowdluv_uid_by_fb_uid($fb_user), $cltid);
	header( 'Location: followerdashboard.php' ) ;

?>