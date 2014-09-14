  <?php

  
  
  require_once("facebook-sdk/facebook.php");

  $fbconfig = array();
  $fbconfig['appId'] = CL_FB_APP_ID;
  $fbconfig['secret'] = CL_FB_APP_SECRET;
  $fbconfig['fileUpload'] = false; // optional
  $fbconfig['scope'] = CL_FB_PERMISSION_SCOPE_STRING;

  $facebook = new Facebook($fbconfig);   
  //var_dump($facebook);
  //Get fb user ID  
  $fb_user = $facebook->getUser();
  //var_dump($fb_user);
  cldbgmsg("  *** facebook->getUser():" . $fb_user); //var_dump($fb_user);
  //If we have an fb userid for the current user.... 
  if ($fb_user) {  // Proceed thinking you have a logged in user who's authenticated.
      $_SESSION["fb_user"] = $fb_user;
  
      //Check to see if this fb user exists in CL db.... Set a global variable containing the crowdluv_uid
      $CL_LOGGEDIN_USER_UID = $_SESSION["CL_LOGGEDIN_USER_UID"] = $CL_model->get_crowdluv_uid_by_fb_uid($fb_user);
      
      //if new.. 
      if($CL_LOGGEDIN_USER_UID==0){
          // ...request profile info from facebook and create a stub entry based on available info
          try { 
            $fb_user_profile = $facebook->api('/me');  //var_dump($fb_user_profile); 
            $CL_model->create_new_cl_follower_record_from_facebook_user_profile($fb_user_profile);
            $CL_LOGGEDIN_USER_UID = $_SESSION["CL_LOGGEDIN_USER_UID"] = $CL_model->get_crowdluv_uid_by_fb_uid($fb_user);
          } catch (FacebookApiException $e) {
            //error_log($e);
            cldbgmsg("FacebookAPIException in cl_init.php requesting new user info:  " . $e);// var_dump($e);
            $fb_user = null;
          }                   
      } 
      //set global var for the user's info
      $CL_LOGGEDIN_USER_OBJ = $_SESSION['CL_LOGGEDIN_USER_OBJ'] = $CL_model->get_follower_object_by_uid($CL_LOGGEDIN_USER_UID);

  }

  if($fb_user){

      //Now check for facebook pages the user is an administrator of,
      //add them to CL db if new, and store them in 'global' var 
      try{
        $fb_user_pages = $facebook->api('/me/accounts');
        if(sizeof($fb_user_pages['data'])==0){$fb_user_pages=null;}
        else{  
          //Clear the global and session variable for talent array
          $_SESSION['CL_LOGGEDIN_TALENTS_ARR'] = $CL_LOGGEDIN_TALENTS_ARR = "";
          foreach ($fb_user_pages['data'] as $fbupg) {
            //Check to see if this talent exists in the cl db. If not, create a stub entry
            $cltid = $CL_model->get_crowdluv_tid_by_fb_pid($fbupg['id']);
            if(! $cltid) $CL_model->create_new_cl_talent_record_from_facebook_page_profile($fbupg);
            //Add the talent obj to a global and session var
            $CL_LOGGEDIN_TALENTS_ARR[] = $CL_model->get_talent_object_by_tid($cltid);
          }
          //Set (or update) the session var with the array we were able to build this time since we had a valid token
          $_SESSION['CL_LOGGEDIN_TALENTS_ARR'] = $CL_LOGGEDIN_TALENTS_ARR;
        }    
      }catch (FacebookApiException $e) {        
        cldbgmsg("FacebookAPIException in cl_init.php requesting page info:  " . $e); //var_dump($e);
        $fb_user_pages = null;
        //we should still be able to proceed, since the rest of the pages do not rely on fb_user_pages and shud continue to use the talent array in the session var

        //if(isset($_GET["expfbtoken"]) ) {  cldbgmsg("<BR>Redirected home due to facebookexception (?expired fb token?)"); } 
        //else {
          //header('Location: ' . CLADDR . "?expfbtoken=1" ); 
          //********  trying this for handling epxpired tokens
          //$loginUrl = $facebook->getLoginUrl() . "&expfbtoken=1";
          //echo "loginurl: " . $loginUrl;
          //header('Location: ' . $loginUrl );
          //echo "<script type='text/javascript'>top.location.href = '$loginUrl';</script>";
        //}
      }
          

  }//if fbUser