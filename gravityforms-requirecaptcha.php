<?php
/*
Plugin Name: Gravity Forms - Require reCAPTCHA Add-on
Plugin URI: http://developer.bu.edu
Description: Adds a mandatory reCAPTCHA to all GravitForms. Works great with multi-site.
Version: 0.1
Author: Boston University
Author URI: http://developer.bu.edu
*/

require 'class-bu-gf-recaptcha.php';

define( 'BU_GF_VERSION', '0.1' );

if ( class_exists( 'GFForms' ) ) {
    GFForms::include_addon_framework();

    class GFSimpleAddOn extends GFAddOn {
      protected $_version                   = BU_GF_VERSION;
      protected $_min_gravityforms_version  = '1.9';
      protected $_slug                      = 'requirecaptcha';
      protected $_path                      = 'gravityforms-requirecaptcha/gravityforms-requirecaptcha.php';
      protected $_full_path                 = __FILE__;
      protected $_title                     = 'Gravity Forms Require reCAPTCHA Add-On';
      protected $_short_title               = 'reCAPTCHA';
      protected $_capabilities_settings_page = 'delete_plugins';

      public function init_frontend(){
        parent::init_frontend();

        $recaptcha = new BU_GF_Google_reCAPTCHA();        
        $recaptcha->setDisabled( $this->get_plugin_setting( 'recaptcha_disabled' ) );

        add_filter( 'gform_validation', array( $recaptcha, 'checkSubmission' ) );
        add_filter( 'gform_pre_render', array( $recaptcha, 'render' ) );
      }

      /**
       * Fix permission check to allow restricting 
       * to only Super Admins on a network.
       **/
      public function current_user_can_any( $caps ) {
        if ( ! is_array( $caps ) ) {
          $has_cap = current_user_can( $caps );
          return $has_cap;
        }

        foreach ( $caps as $cap ) {
          if ( current_user_can( $cap ) ) {
            return true;
          }
        }

        return false;
      }

      public function plugin_settings_fields() {
        return array(
            array(
                'title'  => 'Global reCAPTCHA Settings',
                'fields' => array(
                    array(
                        'type'    => 'checkbox',
                        'name'    => '',
                        'label'   => 'Disable on this site',
                        'choices' => array(
                                       array(
                                           'label'         => '',
                                           'name'          => 'recaptcha_disabled',
                                           'tooltip'       => '',
                                           'default_value' => false,

                                       ),
                                   ),
                        ),
                )
            )
        );
      }

      public function render_uninstall() {
        return false;
      }

    }
    new GFSimpleAddOn();
}