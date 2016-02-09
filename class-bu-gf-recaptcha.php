<?php 

/**
 * Handles adding one or many reCAPTCHAs to a page containing GravityForms embed(s) 
 */

class BU_GF_Google_reCAPTCHA {
	private $disabledForSite = FALSE;
	private $version = BU_GF_VERSION;

	public $jsURL	= 'https://www.google.com/recaptcha/api.js?onload=buGoogleCAPTCHACallback&render=explicit';
	public $template = '<div class="g-recaptcha" data-sitekey="%s"></div>';
	public $errorText;

	public $buCAPTCHAIdentifier = 'bu_google_recaptcha';

	private function setRendered( $isRendered ){
		$this->alreadyRendered = $isRendered;
	}

	private function setErrorText( $txt ){
		$this->errorText = $txt;
		add_filter( 'gform_validation_message', array( $this, 'appendErrorToForm' ), 10, 2 );
	}

	private function is_last_page( $form ){
		if( class_exists( 'GFFormDisplay' ) ){
			return GFFormDisplay::is_last_page( $form );
		}

		return true;
	}
	public function setDisabled( $disabled ){
		$this->disabledForSite = (bool) $disabled;
	}
	public function enqueueScripts( $form, $ajax ){
		// Can't use vanilla reCAPTCHA with multiple instances on a page. Much sad.		
		wp_deregister_script( 'google-recaptcha' );

		wp_enqueue_script( 
			$this->buCAPTCHAIdentifier, 
			plugins_url( 'js/bu_google_recaptcha.min.js', __FILE__ ), 
			array( 'jquery' ),
			$this->version 
		);

		wp_enqueue_script( 'google-recaptcha', apply_filters( 'bu_gravityforms_global_recaptcha_source', $this->jsURL ), array( $this->buCAPTCHAIdentifier ) );
	}

	public function appendErrorToForm( $message, $form ){
		$end = strripos( $message, '</div>' );
		if ( FALSE === $end ) {
			return $message;
		}
		return substr_replace( $message, " {$this->errorText}", $end, 0 );
	}

	public function isDisabled(){
		return ( $this->disabledForSite || is_user_logged_in() );
	}

	public function checkSubmission( $validation_result ) {
		if ( self::isDisabled() || ! self::is_last_page( $validation_result['form'] ) ) {
			return $validation_result;
		}

		if( empty( $_POST['g-recaptcha-response'] ) ){
			$validation_result['is_valid'] = false;
			self::setErrorText('Please check the "I\'m not a robot" box at the bottom of the form.');
			return $validation_result;
		}

		$r = wp_remote_post( 
			'https://www.google.com/recaptcha/api/siteverify', 
			array(
				'body' => array(
					'secret' 	=> get_option( 'rg_gforms_captcha_private_key' ),
					'response'	=> $_POST['g-recaptcha-response'],
					'remoteip'	=> $_SERVER['REMOTE_ADDR'],
					)
				)
			);

		if ( is_wp_error( $r ) ) {
			error_log( __FUNCTION__ . ' : ' . $r->get_error_message() );
			$validation_result['is_valid'] = false;
			self::setErrorText('Error validating form. Please try again.');
		} else {
			$captcha_result = json_decode( $r['body'] );
			if( true !== $captcha_result->success ){
				$validation_result['is_valid'] = false;
				self::setErrorText('Please check the "I\'m not a robot" box at the bottom of the form.');
			}
		}

		return $validation_result;
	}

	public function render( $form, $ajax ) {
		if( ! class_exists( 'GFFormDisplay' ) || self::isDisabled() ){
			return $form;
		}

		self::enqueueScripts( $form, $ajax );
		self::updateRenderedCount();

		$next = GFFormDisplay::get_max_field_id( $form ) + 1;
		$template = sprintf( $this->template, get_option( 'rg_gforms_captcha_public_key' ) );
		
		$opts = array(
			'type'              => 'section',
			'_is_entry_detail'  => NULL,
			'id'                => $next,
			'label'             => '',
			'adminLabel'        => '',
			'isRequired'        => false,
			'size'              => 'medium',
			'errorMessage'      => '',
			'inputs'            => NULL,
			'displayOnly'       => true,
			'labelPlacement'    => '',
			'content'           => '',
			'formId'            => $form['id'],
			'pageNumber'        => GFFormDisplay::get_current_page( $form['id'] ),
			'conditionalLogic'  => '',
			'cssClass'          => $this->buCAPTCHAIdentifier . '_section',
			);

		$form['fields'][] = GF_Fields::create( $opts );

		$captcha_opts = array(
			'type' 			=> 'html',
			'id'			=> $next + 1,
			'content'		=> apply_filters( 'bu_gravityforms_global_recaptcha_div', $template ),
			'cssClass'		=> $this->buCAPTCHAIdentifier,
			);

		$form['fields'][] = GF_Fields::create( array_merge( $opts, $captcha_opts ) );

		return $form;
	}
}