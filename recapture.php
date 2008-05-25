<?php
/*
Plugin Name: Recapture
Plugin URI: http://ben.subparcitizens.net/recapture
Description: Recapture displays a reCAPTCHA on the user registration form in an attempt to combat user registration spam.
Version: 0.2beta
Author: Ben Masters
Author URI: http://ben.subparcitizens.net/

Contributer: Josh Moles
*/

// We need to include the reCAPTCHA library...
// But not if you have the reCAPTCHA comment plugin installed.
// That causes problems, because the library has already been included
// So we'll just use that one if it exists.
if ( !function_exists( '_recaptcha_qsencode' )) {
	require_once( dirname(__FILE__) . '/recaptchalib.php' );
}

global $wp_version;

$recaptureOptions = get_option( 'recapture_options' );

// Display the reCAPTCHA on the registration form
function display_recaptcha() {
	global $recaptureOptions;
	global $wp_version;
	  
  $lang = $recaptureOptions[ 'captchaLang' ];
  $theme = $recaptureOptions['captchaTheme'];

	$format = <<<END
	<script type='text/javascript'>
		var RecaptchaOptions = { theme: '$theme', lang: '$lang', tabindex : 30};
	</script>
END;
	echo $format . recaptcha_get_html( $recaptureOptions[ 'publicKey' ], $error );
}

// Hook the display_recaptcha function into WordPress
add_action( 'register_form', 'display_recaptcha' );

// Check the captcha
function check_recaptcha( ) {
	global $recaptureOptions, $errors;
	$privateKey = $recaptureOptions[ 'privateKey' ];
	
	$response = recaptcha_check_answer( $privateKey,
										$_SERVER[ 'REMOTE_ADDR' ],
										$_POST[ 'recaptcha_challenge_field' ],
										$_POST[ 'recaptcha_response_field' ] );

	if ( !$response->is_valid ) {
		$recaptureOptions[ 'failedAttempts' ]++;
		update_option( 'recapture_options', $recaptureOptions );
		if ( $response->error == 'incorrect-captcha-sol' ) {
			$errors[ 'captcha_wrong' ] = 'That reCAPTCHA was incorrect.';
		}
	}
}

// Check the captcha
function check_recaptcha_new( $user_login, $user_email, $errors ) {
	global $recaptureOptions;
	$privateKey = $recaptureOptions[ 'privateKey' ];
	
	$response = recaptcha_check_answer( $privateKey,
										$_SERVER[ 'REMOTE_ADDR' ],
										$_POST[ 'recaptcha_challenge_field' ],
										$_POST[ 'recaptcha_response_field' ] );

	if ( !$response->is_valid ) {
		$recaptureOptions[ 'failedAttempts' ]++;
		update_option( 'recapture_options', $recaptureOptions );
		if ( $response->error == 'incorrect-captcha-sol' ) {
			$errors->add( 'captcha_wrong', 'That reCAPTCHA was incorrect.' );
		}
	}
}

// Hook the check_recaptcha function into WordPress
if ( $wp_version >= '2.5' ) {
	add_action( 'register_post', 'check_recaptcha_new', 10, 3 );
} else {
	add_action( 'register_post', 'check_recaptcha' );
}

function recapture_domain ( ) {
	$uri = parse_url(get_settings('siteurl'));
	return $uri['host'];
}

// Add a link to the configuration options in the WordPress options menu
function add_recapture_config_page( ) {
	add_options_page( 'Recapture Configuration', 'Recapture', 8, __FILE__, 'recapture_config_page' );
}

// Display the configuration options for Recapture
function recapture_config_page( ) {
	$recaptureOptionsArray = array(
		'publicKey'			=> '',
		'privateKey'		=> '',
		'captchaLang'   => '',
		'captchaTheme'  => '',
		'failedAttempts'	=> 0 );

	add_option( 'recapture_options', $recaptureOptionsArray );

	if (isset($_POST[ 'submit' ])) {
		$recaptureOptionsArray[ 'publicKey' ] = $_POST[ 'publicKey' ];
		$recaptureOptionsArray[ 'privateKey' ] = $_POST[ 'privateKey' ];
		$recaptureOptionsArray['captchaTheme'] = $_POST[ 'captchaTheme' ];
		$recaptureOptionsArray[ 'captchaLang' ] = $_POST[ 'captchaLang' ];
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
	<?php echo "\n"; ?>
	<p>Recapture requires a free set of api keys from <a href="<?php echo recaptcha_get_signup_url(recapture_domain(), 'wordpress'); ?>" target="_blank">http://recaptcha.net</a></p>
<?php
global $wp_version;
if ( $wp_version < '2.5' ) {
	echo "\t<table class=\"optiontable\">\n";
} else {
	echo "\t<table class=\"form-table\">\n";
}
?>
	<tr valign="top">
	<th scope="row">Public Key:</th>
	<td><input type="text" name="publicKey" size="45" maxlength="40" value="<?php echo $recaptureOptions[ 'publicKey' ]; ?>" /></td>
	</tr>
	<tr valign="top">
	<th scope="row">Private Key:</th>
	<td><input type="text" name="privateKey" size="45" maxlength="40" value="<?php echo $recaptureOptions[ 'privateKey' ]; ?>" /></td>
	</tr>
	<tr valign="top">
	<th scope="row">CAPTCHA Theme:</th>
	<td>
	  <select name="captchaTheme">
	  	<option value="blackglass" <?php if($recaptureOptions['captchaTheme'] == 'blackglass') echo "selected=\"selected\""; ?>>Black Glass</option>
	    <?php if($wp_version >= '2.5') { ?><option value="clean" <?php if($recaptureOptions['captchaTheme'] == 'clean' || $recaptureOptions['captchaTheme'] == '') echo "selected=\"selected\""; ?>>Clean</option><?php } ?>
      <option value="red" <?php if($recaptureOptions['captchaTheme'] == 'red') echo "selected=\"selected\""; ?>>Red</option>
      <option value="white" <?php if($recaptureOptions['captchaTheme'] == 'white') echo "selected=\"selected\""; ?>>White</option>
    </select>
  </td>
	</tr>
	<tr valign="top">
	<th scope="row">CAPTCHA Language:</th>
	<td>
	  <select name="captchaLang">
      <option value="en" <?php if($recaptureOptions['captchaLang'] == 'en') echo "selected=\"selected\""; ?>>English</option>
      <option value="nl" <?php if($recaptureOptions['captchaLang'] == 'nl') echo "selected=\"selected\""; ?>>Dutch</option>
      <option value="fr" <?php if($recaptureOptions['captchaLang'] == 'fr') echo "selected=\"selected\""; ?>>French</option>
      <option value="de" <?php if($recaptureOptions['captchaLang'] == 'de') echo "selected=\"selected\""; ?>>German</option>
      <option value="pt" <?php if($recaptureOptions['captchaLang'] == 'pt') echo "selected=\"selected\""; ?>>Portuguese</option>
      <option value="ru" <?php if($recaptureOptions['captchaLang'] == 'ru') echo "selected=\"selected\""; ?>>Russian</option>
      <option value="es" <?php if($recaptureOptions['captchaLang'] == 'es') echo "selected=\"selected\""; ?>>Spanish</option>
      <option value="tr" <?php if($recaptureOptions['captchaLang'] == 'tr') echo "selected=\"selected\""; ?>>Turkish</option>
    </select>
    <?php if($wp_version >= '2.5') echo "The language selection does not make a difference with the \"clean\" theme."; ?>
  </td>
	</tr>
	</table>
<?php
global $wp_version;
if ( $wp_version < '2.5' ) {
	echo "<div class=\"narrow\">\n";
}
	switch ( $recaptureOptions[ 'failedAttempts' ] ) {
		case 0:
			echo "\t<p>There have been <code>(zero)</code> failed attempts at the reCAPTCHA on your user registration page.</p>\n";
			break;
		case 1:
			echo "\t<p>There has been <code>(one)</code> failed attempt at the reCAPTCHA on your user registration page.</p>\n";
			break;
		default:
			echo "\t<p>There have been <code>(" . $recaptureOptions[ 'failedAttempts' ] . ")</code> failed attempts at the reCAPTCHA on your user registration page.</p>\n";
			break;
	}
	
if ( $wp_version < '2.5' ) {
	echo "</div><!-- narrow -->\n";
}
?>
	<p class="submit">
	<input type="submit" name="submit" value="<?php _e('Update Options &raquo;'); ?>" />
	</p>
	</form>
</div><!-- wrap -->
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

// Adds the link to the stylesheet with the custom login page.
function recapture_custom_header( ) {

global $recaptureOptions;
global $wp_version;

// Only make the register page custom if it is the register page and is WP 2.5 or later.
if ( $wp_version >= '2.5' && $_GET['action'] == 'register') {
  if($recaptureOptions['captchaTheme'] == 'clean')
    echo '<style type="text/css">#login { width: 480px; }</style>';
  else if($recaptureOptions['captchaTheme'] == 'red' || $recaptureOptions['captchaTheme'] == 'white' || $recaptureOptions['captchaTheme'] == 'blackglass')
    echo '<style type="text/css">#login { width: 359px; }</style>';
}

}

// Hook the recapture_custom_header function into Wordpress login/register page
add_action('login_head', 'recapture_custom_header');
?>