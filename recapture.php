<?php
/*
Plugin Name: Recapture
Plugin URI: http://ben.subparcitizens.net/recapture
Description: Recapture displays a reCAPTCHA on the user registration form in an attempt to combat user registration spam.
Version: 0.1
Author: Ben Masters
Author URI: http://ben.subparcitizens.net/
*/

// We need to include the reCAPTCHA library...
// But not if you have the reCAPTCHA comment plugin installed.
// That causes problems, because the library has already been included
// So we'll just use that one if it exists.
if ( !function_exists( '_recaptcha_qsencode' )) {
	require_once( dirname(__FILE__) . '/recaptchalib.php' );
}

$recaptureOptions = get_option( 'recapture_options' );

// Display the reCAPTCHA on the registration form
function display_recaptcha() {
	global $recaptureOptions;
	echo recaptcha_get_html( $recaptureOptions[ 'publicKey' ], $error );
}

// Hook the display_recaptcha function into WordPress
add_action( 'register_form', 'display_recaptcha' );

// Check the captcha
function check_recaptcha() {
	global $errors, $recaptureOptions;
	$privateKey = $recaptureOptions[ 'privateKey' ];
	
	$response = recaptcha_check_answer( $privateKey,
										$_SERVER[ 'REMOTE_ADDR' ],
										$_POST[ 'recaptcha_challenge_field' ],
										$_POST[ 'recaptcha_response_field' ] );

	if (!$response->is_valid) {
		$recaptureOptions[ 'failedAttempts' ]++;
		update_option( 'recapture_options', $recaptureOptions );
		if ($response->error == 'incorrect-captcha-sol') {
			$errors = 'That reCAPTCHA was incorrect.';
		}
	}
}

// Hook the check_recaptcha function into WordPress
add_action( 'register_post', 'check_recaptcha' );

function recapture_domain ()
{
	$uri = parse_url(get_settings('siteurl'));
	return $uri['host'];
}

// Add a link to the configuration options in the WordPress options menu
function add_recapture_config_page() {
	add_options_page( 'Recapture Configuration', 'Recapture', 8, __FILE__, 'recapture_config_page' );
}

// Display the configuration options for Recapture
function recapture_config_page() {
	$recaptureOptionsArray = array(
		'publicKey'			=> '',
		'privateKey'		=> '',
		'failedAttempts'	=> 0 );

	add_option( 'recapture_options', $recaptureOptionsArray );

	if (isset($_POST[ 'submit' ])) {
		$recaptureOptionsArray[ 'publicKey' ] = $_POST[ 'publicKey' ];
		$recaptureOptionsArray[ 'privateKey' ] = $_POST[ 'privateKey' ];
		$failedAttempts = get_option( 'recapture_options' );
		$recaptureOptionsArray[ 'failedAttempts' ] = $failedAttempts[ 'failedAttempts' ]; 
		
		update_option( 'recapture_options', $recaptureOptionsArray);
	}
	
	$recaptureOptions = get_option( 'recapture_options' );
?>
	<div class="wrap">
	<h2>Recapture Configuration</h2>
	<form method="post" action="<?php echo $_SERVER[ 'PHP_SELF' ] . '?page=' . plugin_basename(__FILE__); ?>&updated=true">
	<?php wp_nonce_field( 'update-options' ); ?>
	<p>Recapture requires a free set of api keys from <a href="<?php echo recaptcha_get_signup_url(recapture_domain(), 'wordpress'); ?>" target="_blank">http://recaptcha.net</a></p>
	<table class="optiontable">
	<tr valign="top">
	<th scope="row">Public Key:</th>
	<td><input type="text" name="publicKey" size="45" maxlength="40" value="<?php echo $recaptureOptions[ 'publicKey' ]; ?>" /></td>
	</tr>
	<tr valign="top">
	<th scope="row">Private Key:</th>
	<td><input type="text" name="privateKey" size="45" maxlength="40" value="<?php echo $recaptureOptions[ 'privateKey' ]; ?>" /></td>
	</tr>
	</table>
	<div class="narrow">
	<p>There have been <code>(<?php echo $recaptureOptions[ 'failedAttempts' ]; ?>)</code> failed attempt(s) at the reCAPTCHA on your user registration page.</p>
	</div>
	<p class="submit">
	<input type="submit" name="submit" value="<?php _e('Update Options &raquo;'); ?>" />
	</p>
	</form>
	</div>
<?php
}

// Hook the add_config_page function into WordPress
add_action( 'admin_menu', 'add_recapture_config_page' );

// Display a warning if the public and private keys are missing
if ( !($recaptureOptions['publicKey'] && $recaptureOptions['privateKey']) && !isset($_POST['submit']) ) {
	function recapture_warning() {
		$path = plugin_basename(__FILE__);
		echo "<div id='recapture-warning' class='updated fade-ff0000'><p><strong>Recapture is not active</strong> You must <a href='options-general.php?page=" . $path . "'>enter your reCAPTCHA API keys</a> for it to work</p></div>";
	}
        add_action('admin_notices', 'recapture_warning');
        return;
}
?>