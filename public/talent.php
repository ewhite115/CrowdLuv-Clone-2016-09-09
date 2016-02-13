<?php 
    require_once "../inc/cl_bootstrap.php";
 	
	$CL_SITE_SECTION = "landing";
 
	if(isset($CL_CUR_TGT_TALENT)) $pageTitle = "Want " . $CL_CUR_TGT_TALENT['fb_page_name'] . " in your Town? -  CrowdLuv";
	
	
	//Get the landing page settings for this talent  (do this before printing header)
	$tlpgsettings = $CL_model->get_talent_landingpage_settings($CL_CUR_TGT_TALENT['crowdluv_tid']);   //var_dump($tlpgsettings); exit;
    //Set the URL for the image that will be used on the page and in the og: meta tags
    //if($tlpgsettings['image'] == "facebookprofile") $tlpimg = "https://graph.facebook.com/" . $CL_CUR_TGT_TALENT['fb_pid'] . "/picture?type=large&access_token=" . $facebookSession->getToken();
    if($tlpgsettings['image'] == "facebookprofile") $tlpimg = "https://graph.facebook.com/" . $CL_CUR_TGT_TALENT['fb_pid'] . "/picture?type=large";
    //else if ($tlpgsettings['image'] != "" && $tlpgsettings['image'] != "default")  $tlpimg = BASE_URL . 'crowdluvdata/talent/' . $CL_ACTIVE_MANAGED_TALENT["crowdluv_tid"] . '/landingpage_images/' . $tlpgsettings["image"];
 	else if ($tlpgsettings['image'] != "" && $tlpgsettings['image'] != "default") $tlpimg = CLADDR . 'crowdluvdata/talent/' . $CL_CUR_TGT_TALENT["crowdluv_tid"] . '/landingpage_images/' . $tlpgsettings["image"];
    else $tlpimg = CLADDR . 'res/crowdluv_fbtab_defaulthero_820.jpg';
    $CL_OG_IMAGE = $tlpimg;
    $CL_OG_DESCRIPTION = $CL_CUR_TGT_TALENT['fb_page_name'] . " wants to know where to visit. Vote for your town"; 


	// and check that a crowdluv_tid has been passed in
	include(ROOT_PATH . 'inc/partial_confirm_target_talent_set.php');


	//See if the user has arrived from a referral/share, and if so, save the referrer's user id 
	if(isset($_GET['ref_uid'])) $ref_uid = $_GET['ref_uid']; else $ref_uid="";




	include(ROOT_PATH . 'inc/cl_html_leader.php'); 	

?>

	

	<div class="talent_landingpage_hero"  
		style="background-image: url('<?php echo $tlpimg; if($tlpgsettings['image'] == "facebookprofile" && isset($CL_LOGGEDIN_USER_UID)) echo "&access_token=" . $facebookSession->getToken();?>');"> 	</div> 
	
	<div class="row crowdluvsection text-center">
		<div class="col-xs-12">
			<h1>Want <?php echo $CL_CUR_TGT_TALENT['fb_page_name']; ?> in your town?</h1><br>
			<a href="#" id="fbtab_cometomytown">
				<img src="../res/yescometomytown.jpg">
			</a><br><br>
			
		</div>
	</div>


	<div class="row talent_landingpage_talentmessagesection">
		<div class="col-xs-offset-1  crowdluvsection ">
			<h1>New Message from <?php echo $CL_CUR_TGT_TALENT['fb_page_name']; ?>:</h1>
			<p><?php echo $tlpgsettings['message'];?></p>
		</div>

	</div>


	<div class="row talent_landingpage_whathappens_section">
			
			<div class="col-xs-offset-1 crowdluvsection ">
				<h1>What hapens when I click the big red button?</h1>	
	
				<div class="row">
					<div class="text-right col-xs-1 crowdluvsection "><img src="../res/allow.jpg"></div>
					<div class="col-xs-10 "> <p>Facebook wil ask you to share info with CrowdLuv.com, a safe application that allows fans like you to privately and safely entreat musicians, speakers, performers and travelling shows to come to your town. </p>	</div>
				</div>
				<div class="row">
					<div class="text-right col-xs-1 crowdluvsection "><img src="../res/graypadlock.jpg"></div>
					<div class="col-xs-10 "> <p>The Facebook information you share will remain private and will not be shared with anyone else. Your contact information will not even be shared with <?php echo $CL_CUR_TGT_TALENT['fb_page_name']; ?>! <?php echo $CL_CUR_TGT_TALENT['fb_page_name']; ?> has to log into CrowdLuv in order to send you text messages or emails. Your contact information is hidden from all talent . </p></div>
				</div>
				<div class="row">
					<div class="text-right col-xs-1 crowdluvsection "><img src="../res/grayletter.jpg"></div>
					<div class="col-xs-10 "> <p><?php echo $CL_CUR_TGT_TALENT['fb_page_name']; ?> will keep in touch via email or text message to let you know when he comes to your town. By Luv'ing <?php echo $CL_CUR_TGT_TALENT['fb_page_name']; ?>, you get to decide where he goes next! </p>	</div>
				</div>
			</div>

		

	</div> 
	


<script>             
 	//click-handler for the "come to my town" button
   	$(document).ready(function(){
   		//console.log("tal docready");
	    $("#fbtab_cometomytown").click(function () {
	    	//console.log("clickhandl fired");
		    FB.login(function(response) {
		        if (response.authResponse) {
		            console.log("User authorized - redirecting to luv.php");
		            window.open('<?php echo BASE_URL;?>luv.php?crowdluv_tid=<?php echo $CL_CUR_TGT_TALENT["crowdluv_tid"] . "&ref_uid=" . $ref_uid;?>', "_top").focus();
		            return false;
		          } //end if
		          else {// The person cancelled the login dialog 
		          	console.log("user didnt authorize");
		          	//TODO:  do something if the user declined the permissions?
		          }//end else
		      }, 
		      {scope: "<?php echo CL_FB_PERMISSION_SCOPE_STRING_JS_SDK; ?>"} 
		    ); //end of fb.login() call
		}); //end of .click() handler
	});
</script>


<?php include(ROOT_PATH . 'inc/footer.php') ?>



