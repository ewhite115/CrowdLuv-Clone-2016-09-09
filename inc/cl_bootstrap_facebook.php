<?php

  require_once(ROOT_PATH . 'vendor/autoload.php');
  use Facebook\FacebookSession;
  use Facebook\FacebookRequest;
  use Facebook\FacebookRedirectLoginHelper;
  use Facebook\FacebookJavaScriptLoginHelper;

  //var_dump($_SESSION);


  /**
   * TODO:  is this still relvant in api 4.0?
   * Facebook Permissions Denied
   * If this was the first time the user tried to login, but they denied
   * the facebook permission dialog, the query string will include the following
   * provided by facebook
   *     ?error=access_denied&error_code=200&error_description=Permissions+error&error_reason=user_denied&state=939d1a76d41e3612ff16087f39afc14c#_=_
   * So, check for this, and if found, redirect to the home page with
   * a flad to include explanation that the permission are required
   */
  if((isset( $_GET['error_reason'] ) && $_GET['error_reason'] == 'user_denied')){
     header('Location: ' . CLADDR . "?fb_user_denied_permissions=1" );
     die(); 
  }


//TODO:  figure out how we intially ask user//followers for only perms 
//     we need and not manage_pages
/*$followerFacebookPermissionScope = array(
   'scope' => 'email',
   'user_location',
   'user_birthday',
   'user_relationships'
    );*/

$talentFacebookPermissionScope = array(
   'scope' => 'email',
   'user_friends',
   'user_likes',
   'user_location',
   'user_birthday',
   'user_relationships',
   'manage_pages'
    );

/**
 * [$facebookLikeCategoriesToCreateStubsFor Contains the list of Facebook like categories that should be automatically added to the CL DB as new talent]
 * @var array
 */
$facebookLikeCategoriesToCreateStubsFor = array (
  //'Community',
  'Musician/band',
  'Author'

  );



/**
 * [checkFacebookPermissions Makes graph API call to facebook to get permissions, and returns true/false if the user has granted sufficient permissions]
 * @param  [FacebookSession] $fbSession [FacebookSession to use for making the API call]
 * @param  [Array]           $required_perms [Array of required permissions]
 * @return [bool]        [true if the user has granted sufficient permissions]
 */
  function checkFacebookPermissions($fbSession, $required_perms) {
    try { 
        // graph api request for user permissions
        $request = new FacebookRequest( $fbSession, 'GET', '/me/permissions' );
        $response = $request->execute();
        // get response
        $fb_user_permissions = $response->getGraphObject()->asArray();
        //echo "<pre> Response to facebook graph call /me/permissions :"; var_dump($fb_user_permissions); echo "</pre>"; die;

        foreach($fb_user_permissions as $perm){
          //echo "<pre> perm :"; var_dump($perm); echo "</pre>"; die();
          if($perm->permission == "installed"  && $perm->status != "granted" ) return false;
          if($perm->permission == "public_profile"  && $perm->status != "granted" ) return false;
          if(in_array($perm->permission, $required_perms) && $perm->status != "granted" ) return false;

        }

    } catch (FacebookApiException $e) {
        //error_log($e);
        cldbgmsg("FacebookAPIException in cl_init.php requesting user permissions:  " . $e);// var_dump($e);
    }     
    return true;

  }//ChekFacebookPermissions





  /**
   * Initialize the Facebook PHP SDK 4
   */
  
  FacebookSession::setDefaultApplication( CL_FB_APP_ID, CL_FB_APP_SECRET);
  
  $facebookLoginHelper = new FacebookRedirectLoginHelper(CLADDR);
  $facebookJavascriptLoginHelper = new FacebookJavaScriptLoginHelper();

  $facebookSession= null;
  
  // see if we've previously saved a facebook session token
  if ( isset( $_SESSION ) && isset( $_SESSION['fb_token'] ) ) {
    // create new fb session object from saved access_token
    cldbgmsg("Found fb_token in session");
    $facebookSession = new FacebookSession( $_SESSION['fb_token'] );
    
    // validate the access_token to make sure it's still valid
    try {
      if ( !$facebookSession->validate() ) {
        cldbgmsg("fb_token in session no longer valid");
        $facebookSession = null;
      }
    } catch ( Exception $e ) {
      // catch any exceptions, nullify the session variable if encountered
      cldbgmsg("Exception validating fb_token found in session" . $e);
      $facebookSession = null;
    }
  }  

  //We didnt find a previously saved session token, so check to see if this is a new 
  //facebook login from a redirect
  $isNewSession = false;  // This flag will be used later to conditionally execute code only if it's a 'new' session
  if ( !isset( $facebookSession ) || $facebookSession === null ) {
    try {
      //Check for a new sessions coming from a redirect
      cldbgmsg("Checking for new facebook session from redirect");
      $facebookSession = $facebookLoginHelper->getSessionFromRedirect();
      //echo "facebooksession from redirect:"; echo "<pre>"; var_dump($facebookSession); echo "</pre>";
      if($facebookSession) cldbgmsg("Found new facebook session from redirect");
      //If no new session from redirect, see if there is a new session set on the client side 
      //  facebook javascript SDK
      if($facebookSession === null) {
          cldbgmsg("checking for new facebook session from javascript SDK");
          $facebookJavascriptLoginHelper->getSession();
          if($facebookSession) cldbgmsg("Found new facebook session from Javascript SDK");
      }
      //echo "facebooksession from javascript:"; echo "<pre>"; var_dump($facebookSession); echo "</pre>";
      //If this was in fact a newly-logged-in session, get facebook Permissions, check for minimums
      if($facebookSession){
        if(!checkFacebookPermissions($facebookSession, $talentFacebookPermissionScope)){
          //If the user declined any required permissions, redirect to home page and set a flag
          header('Location: ' . CLADDR . "?fb_user_denied_permissions=1" );
          die(); 
        }
        $isNewSession = true;
      }
    } catch( Facebook\FacebookAuthorizationException $ex ) {
      
      //Auth Code expired, so nullify the facebooksession and delete the stored token
      echo "FacebookAuthorizationException getting session in init_facebook";
      //echo "<pre>"; var_dump($ex); echo "</pre>";
      $facebookSession = null;
      $_SESSION['fb_token'] = null;
      //die;
    } catch( FacebookRequestException $ex ) {
      echo "FacebookRequestException getting session in init_facebook";
      echo "<pre>"; var_dump($ex); echo "</pre>";
      die;
    } catch( Exception $ex ) {
      // When validation fails or other local issues
      echo "Exception getting session in init_facebook";
      echo "<pre>"; var_dump($ex); echo "</pre>";
      die;
    }
    
  }   


  //If we dont have a facebook session, generate a login URL that can be used where needed
  if(! $facebookSession){
    cldbgmsg(" no fb session: generating url");
    //Get the login URL - 
    $talentLoginURL = $facebookLoginHelper->getLoginUrl($talentFacebookPermissionScope);
    //if user previously declined, set rerequest flag to true
    if( isset( $_GET['fb_user_denied_permissions'] ) && $_GET['fb_user_denied_permissions'] == '1'){
      //echo "getting rerequest url"; 
      $talentLoginURL = $talentLoginURL . "&auth_type=rerequest";
    }   
    //Save the fb login URL in a session var (mainly to be accessible by crowdluv admin app)
    $_SESSION['CL_fb_talentLoginURL'] = $talentLoginURL;

  }


  /**
   * If we have a logged-in facebook user - Look up their crowdluv profile or
   *     create a new one if they are new to crowdluv
   */
  if ($facebookSession) {  // Proceed thinking you have a logged in user who's authenticated.
      //echo "we have a session";
      $CL_model->setFacebookSession($facebookSession);
      cldbgmsg("Active Facebook session with token<br>" . $facebookSession->getToken());
      //cldbgmsg("Active Facebook session <br>" . $facebookSession);

      // save the facebook session token to persistent session storage 
      $_SESSION['fb_token'] = $facebookSession->getToken();

      // create a session using saved token or the new one we generated at login
      $facebookSession = new FacebookSession( $facebookSession->getToken() );

      $fb_user = $facebookSession->getSessionInfo()->asArray()['user_id'];
      //Set a session global with the fb user id
      //TODO:  Remove this. Nowhere else shouuld be relying or referring ro fb_user
      //  fb_user is just used to determine logged in follower, and set CL_LOGGEDIN_USER accordingly
      //  everywhere else should rely on CL_LOGGEDIN_USER
      $_SESSION["fb_user"] = $fb_user;

      //Check to see if this fb user exists in CL db.... Set a global variable containing the crowdluv_uid
      $CL_LOGGEDIN_USER_UID = $_SESSION["CL_LOGGEDIN_USER_UID"] = $CL_model->get_crowdluv_uid_by_fb_uid($fb_user);
      //TODO:  make the call to /api/me on every "new" session, check against our existing info,
              //and later prompt user for updates if anything has changed?
      //if this is new user to CrowdLuv.. 
      if($CL_LOGGEDIN_USER_UID==0){          
          //..request profile info from facebook and create a stub entry based on available info
          //TODO: pull this out into a separate function?
          try { 
            // graph api request for user data
            $request = new FacebookRequest( $facebookSession, 'GET', '/me' );
            $response = $request->execute();
            // get response
            $fb_user_profile = $response->getGraphObject()->asArray();
            //echo "<pre> Response to facebook graph cal /me :"; var_dump($fb_user_profile); echo "</pre>"; die;
            $CL_model->create_new_cl_follower_record_from_facebook_user_profile($fb_user_profile);
            $CL_LOGGEDIN_USER_UID = $_SESSION["CL_LOGGEDIN_USER_UID"] = $CL_model->get_crowdluv_uid_by_fb_uid($fb_user);
          } catch (FacebookApiException $e) {
            //error_log($e);
            cldbgmsg("FacebookAPIException in cl_init.php requesting new user info:  " . $e);// var_dump($e);
            $fb_user = null;
          }                   
      } 
      //set global var for the user's info, whether they are new or returning
      $CL_LOGGEDIN_USER_OBJ = $_SESSION['CL_LOGGEDIN_USER_OBJ'] = $CL_model->get_follower_object_by_uid($CL_LOGGEDIN_USER_UID);

  }


  /**
   * Now check for facebook pages the user is an administrator of,
   * add them to CL db if new, and store them in 'global' var 
   */
  if($facebookSession){

      try{
        // graph api request for user data
        $request = new FacebookRequest( $facebookSession, 'GET', '/me/accounts' );
        $response = $request->execute();
        // get response
        $fb_user_pages = $response->getGraphObject()->asArray();
        //echo "<pre>"; var_dump($fb_user_pages); echo "</pre>";
        
        if(sizeof($fb_user_pages['data'])==0){$fb_user_pages=null;}
        else{  
          //Clear the global and session variable for talent array
          $_SESSION['CL_LOGGEDIN_TALENTS_ARR'] = $CL_LOGGEDIN_TALENTS_ARR = "";
          foreach ($fb_user_pages['data'] as $fbupg) {
            //Check to see if this talent exists in the cl db. 
            $cltid = $CL_model->get_crowdluv_tid_by_fb_pid($fbupg->id);
            //If not, create a stub entry
            if(! $cltid) {
              cldbgmsg("Found new facebook page to add: " . $fbupg->id); 
              $CL_model->create_new_cl_talent_record_from_facebook_page_profile($fbupg);
            }
            //Get the tid for the newly created talent record, and
            //  Add the talent obj to a global 
            $cltid = $CL_model->get_crowdluv_tid_by_fb_pid($fbupg->id);
            $CL_LOGGEDIN_TALENTS_ARR[] = $CL_model->get_talent_object_by_tid($cltid);
          }
          //Set (or update) the session var with the array we were able to build this time since we had a valid token
          $_SESSION['CL_LOGGEDIN_TALENTS_ARR'] = $CL_LOGGEDIN_TALENTS_ARR;
        }    
      } catch (FacebookApiException $e) {        
        cldbgmsg("FacebookAPIException in cl_init.php requesting page info:-------<br>" . $e->getMessage() . "<br>" . $e->getTraceAsString() . "<br>-----------"); 
        $fb_user_pages = null;
        //we should still be able to proceed, since the rest of the pages do not rely on 
        //  fb_user_pages, and should continue to use the talent array in the session var

      }       

  }//if fbUser



  /**
   * Check for facebook pages the user 'likes',
   *   add those pages to CL db (as new talent) if not already
   *   add an entry in db indicating this user "likes" that page/talent 
   */
  if($facebookSession and $isNewSession){
    //We may need to make multiple requests to get all the likes.
    //  Loop making api call ..  
    $done=false;
    //Create the initial request object for retrieving user's likes
    $request = new FacebookRequest( $facebookSession, 'GET', '/me/likes?fields=id,name,category,link&limit=100' );
    do{  
      try{          
          $response = $request->execute();
          // get response
          $fb_user_likes = $response->getGraphObject()->asArray();
          //echo "<pre>"; var_dump($fb_user_likes); echo "</pre>"; die;
          
          if(isset($fb_user_likes['data']) && sizeof($fb_user_likes['data']) > 0) {  
              
              foreach ($fb_user_likes['data'] as $fbupg) {
                  //...See if it already exists as a talent in the CL DB
                  $cltid = $CL_model->get_crowdluv_tid_by_fb_pid($fbupg->id);
                  //If not, and it's in an "enabled" category, add it
                  if(! $cltid && (in_array($fbupg->category, $facebookLikeCategoriesToCreateStubsFor))) {
                      cldbgmsg("Found new facebook like page to add: " . $fbupg->id . ":" . $fbupg->name . ":" . $fbupg->category); 
                      $CL_model->create_new_cl_talent_record_from_facebook_user_like($fbupg);
                      $cltid = $CL_model->get_crowdluv_tid_by_fb_pid($fbupg->id);
                      
                  }
                  //Make sure DB is updated to reflect that this user facebook-likes the talent
                  if($cltid) $CL_model->setFollower_FacebookLikes_Talent($CL_LOGGEDIN_USER_UID, $cltid, 1); 


              }//foreach
          } //if we got data back fro api call

      }catch (FacebookApiException $e) {
        cldbgmsg("FacebookAPIException requesting /me/likes -------<br>" . $e->getMessage() . "<br>" . $e->getTraceAsString() . "<br>-----------"); 
        $fb_user_likes = null;
        //we should still be able to proceed, since the rest of the pages do not rely on 
        //  fb_user_likes, and should continue to use the talent array in the session var
      } 
      //Create a new request object and start over if there are more likes
    } while (($response) && $request = $response->getRequestForNextPage());


  }//if fbsession and isnewsession

//Clear the 'response' object created above so that it doesnt cause problems for 
//  ajax files which use the same variable name
$response = array();



//Check for the object that fb passes into tabs to determine if we are being loaded
//as a facebook tab - if so, decode the passed in object and use it to set 
//the target talent object
if(isset($_REQUEST["signed_request"])) {
    $CL_INSIDE_FACEBOOK_TAB = true;
    $signed_request = $_REQUEST["signed_request"];
    list($encoded_sig, $payload) = explode('.', $signed_request, 2); 
    $data = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
     
    $talentpageid = $data["page"]["id"]; 
    //$cl_tobj = $CL_model->get_talent_object_by_tid( $CL_model->get_crowdluv_tid_by_fb_pid($talentpageid)  );
    $CL_CUR_TGT_TALENT = $CL_model->get_talent_object_by_tid( $CL_model->get_crowdluv_tid_by_fb_pid($talentpageid));
    //$app_data is any information that was passed in the query string for the app_data param
    $app_data="Empty";
    if(array_key_exists("app_data", $data)) $app_data = $data["app_data"];
} else{
  //Otherwise -- we are on the crowdluv website. 
  $CL_INSIDE_FACEBOOK_TAB = false;

}