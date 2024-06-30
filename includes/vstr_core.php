<?php

if (!defined('ABSPATH'))
    exit;

class vstr_core {

    /**
     * The single instance
     * @var 	object
     * @access  private
     * @since 	1.0.0
     */
    private static $_instance = null;

    /**
     * Settings class object
     * @var     object
     * @access  public
     * @since   1.0.0
     */
    public $settings = null;

    /**
     * The version number.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $_version;

    /**
     * The token.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $_token;

    /**
     * The main plugin file.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $file;

    /**
     * The main plugin directory.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $dir;

    /**
     * The plugin assets directory.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $assets_dir;

    /**
     * The plugin assets URL.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $assets_url;

    /**
     * Suffix for Javascripts.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $templates_url;

    /**
     * Suffix for Javascripts.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $script_suffix;

    /**
     * For menu instance
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $menu;

    /**
     * For template
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $plugin_slug;

    /**
     * Constructor function.
     * @access  public
     * @since   1.0.0
     * @return  void
     */
    public function __construct($file = '', $version = '1.0.0') {
        $this->_version = $version;
        $this->_token = 'vstr';

        $this->file = $file;
        $this->dir = dirname($this->file);
        $this->assets_dir = trailingslashit($this->dir) . 'assets';
        $this->assets_url = esc_url(trailingslashit(plugins_url('/assets/', $this->file)));
        add_action('wp_enqueue_scripts', array($this, 'frontend_enqueue_scripts'), 10, 1);
        add_action('wp_enqueue_scripts', array($this, 'frontend_enqueue_styles'), 10, 1);

        add_action('wp_ajax_nopriv_vstr_newVisit', array($this, 'new_visit'));
        add_action('wp_ajax_vstr_newVisit', array($this, 'new_visit'));
        add_action('wp_ajax_nopriv_vstr_newStep', array($this, 'new_step'));
        add_action('wp_ajax_vstr_newStep', array($this, 'new_step'));
        add_action('plugins_loaded', array($this, 'init_localization'));
        
    }
    

    /**
     * Load frontend CSS.
     * @access  public
     * @since   1.0.0
     * @return void
     */
    public function frontend_enqueue_styles($hook = '') {
        wp_register_style($this->_token . '-frontend', esc_url($this->assets_url) . 'css/vstr_frontend.min.css', array(), $this->_version);
        wp_enqueue_style($this->_token . '-frontend');
    }
    
    /*
     * Plugin init localization
     */
    public function init_localization()
    {
        $moFiles = scandir(trailingslashit($this->dir) . 'languages/');
        foreach ($moFiles as $moFile) {
            if (strlen($moFile) > 3 && substr($moFile, -3) == '.mo' && strpos($moFile, get_locale()) > -1) {
                load_textdomain('vstr', trailingslashit($this->dir) . 'languages/' . $moFile);
            }
        }
    }

    /**
     * Load frontend Javascript.
     * @access  public
     * @since   1.0.0
     * @return void
     */
    public function frontend_enqueue_scripts($hook = '') {
        global $wpdb;
        $settings = $this->getSettings();
        $dontTrackIPs = explode(',', $settings->filterIP);
        $dontTrack = false;
        if(!isset($_SERVER['HTTP_REFERER']) || strpos($_SERVER['HTTP_REFERER'],"page=vstr-visits") === false){            
            foreach ($dontTrackIPs as $ip) {
                if ($ip == $_SERVER['REMOTE_ADDR']) {
                    $dontTrack = true;
                }
            }
            if (is_user_logged_in() && $settings->dontTrackAdmins && current_user_can('manage_options')) {
                $dontTrack = true;
            }            
        }

        if (!$dontTrack) {
            wp_register_script($this->_token . '-frontend', esc_url($this->assets_url) . 'js/vstr_frontend.min.js', array('jquery'), $this->_version);
            wp_enqueue_script($this->_token . '-frontend');
            wp_localize_script($this->_token . '-frontend', 'vstr_ajaxurl', array(admin_url('admin-ajax.php')));
            $userID = 0;
            if (is_user_logged_in()) {
                $current_user = wp_get_current_user();
                $userID = $current_user->ID;
            }
            wp_localize_script($this->_token . '-frontend', 'vstr_userID', array($userID));
            $mode = 'all';
            $settings = $this->getSettings();
            $mode = $settings->customersTarget;
            wp_localize_script($this->_token . '-frontend', 'vstr_noTactile', array($settings->dontTrackTactile));
            wp_localize_script($this->_token . '-frontend', 'vstr_mode', array($mode));
            wp_localize_script($this->_token . '-frontend', 'vstr_ip', array($_SERVER['REMOTE_ADDR']));
        }
    }

    /**
     * Save new visit
     */
    public function new_visit() {
        global $wpdb;
        $table_name = $wpdb->prefix . "vstr_visits";
        $wpdb->insert($table_name, array('date' => date('Y-m-d H:i:s'), 'screenWidth' => sanitize_text_field($_POST['screenWidth']), 'screenHeight' => sanitize_text_field($_POST['screenHeight']), 'browser' => sanitize_text_field($_POST['browser']), 'ip' => sanitize_text_field($_POST['ip'])));
        $visitID = $wpdb->insert_id;
        echo $visitID;
        die();
    }

    /**
     * Save new step
     */
    public function new_step() {
        global $wpdb;
        $_POST['visitID'] = str_replace(" ", "", sanitize_text_field($_POST['visitID']));
        $_POST['visitID'] = trim(preg_replace('/\s\s+/', '', $_POST['visitID']));
        $table_name = $wpdb->prefix . "vstr_steps";
        $wpdb->insert($table_name, array('visitID' => $_POST['visitID'], 'value'=>sanitize_text_field($_POST['value']), 'type' => sanitize_text_field($_POST['type']), 'domElement' => sanitize_text_field($_POST['domElement']), 'page' => sanitize_text_field($_POST['page']), 'date' => date('Y-m-d H:i:s')));
        echo $_POST['visitID'];
        if ($_POST['userID'] > 0) {
            $table_nameV = $wpdb->prefix . "vstr_visits";
            $wpdb->update($table_nameV, array('userID' => sanitize_text_field($_POST['userID'])), array('id' => sanitize_text_field($_POST['visitID'])));
        }
        die();
    }

    /*
     * Return plugin settings
     */

    private function getSettings() {
        global $wpdb;
        $table_name = $wpdb->prefix . "vstr_settings";
        $rows = $wpdb->get_results("SELECT * FROM $table_name WHERE id=1");
        return $rows[0];
    }

    /**
     * Main Instance
     *
     *
     * @since 1.0.0
     * @static
     * @return Main instance
     */
    public static function instance($file = '', $version = '1.0.0') {
        if (is_null(self::$_instance)) {
            self::$_instance = new self($file, $version);
        }
        return self::$_instance;
    }

// End instance()

    /**
     * Cloning is forbidden.
     *
     * @since 1.0.0
     */
    public function __clone() {
        _doing_it_wrong(__FUNCTION__,'', $this->_version);
    }

// End __clone()

    /**
     * Unserializing instances of this class is forbidden.
     *
     * @since 1.0.0
     */
    public function __wakeup() {
        _doing_it_wrong(__FUNCTION__, '', $this->_version);
    }

// End __wakeup()

    /**
     * Log the plugin version number.
     * @access  public
     * @since   1.0.0
     * @return  void
     */
    private function _log_version_number() {
        update_option($this->_token . '_version', $this->_version);
    }

}
