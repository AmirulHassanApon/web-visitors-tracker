<?php
if (!defined('ABSPATH'))
    exit;

class vstr_admin {

    /**
     * The single instance
     * @var 	object
     * @access  private
     * @since 	1.0.0
     */
    private static $_instance = null;

    /**
     * The main plugin object.
     * @var 	object
     * @access  public
     * @since 	1.0.0
     */
    public $parent = null;

    /**
     * Prefix for plugin settings.
     * @var     string
     * @access  publicexport
     *
     * @since   1.0.0
     */
    public $base = '';

    /**
     * Available settings for plugin.
     * @var     array
     * @access  public
     * @since   1.0.0
     */
    public $settings = array();

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

    public function __construct($parent) {
        $this->_token = 'vstr';
        $this->parent = $parent;
        $this->dir = dirname($parent->file);
        $this->assets_dir = trailingslashit($this->dir) . 'assets';
        $this->assets_url = esc_url(trailingslashit(plugins_url('/assets/', $parent->file)));
        add_action('admin_menu', array($this, 'add_menu_item'));
        add_action('wp_ajax_nopriv_vstr_settings_save', array($this, 'settings_save'));
        add_action('wp_ajax_vstr_settings_save', array($this, 'settings_save'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'), 10, 1);
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_styles'), 10, 1);
        $this->check_visitsToDelete();
    }

    /**
     * Add menu to admin
     * @return void
     */
    public function add_menu_item() {
        add_menu_page('Visitors Tracker', esc_html__('Visitors Tracker','vstr'), 'manage_options', 'vstr-visits', array($this, 'submenu_visits'), 'dashicons-search');
        add_submenu_page('vstr-visits', 'Settings', esc_html__('Settings','vstr'), 'manage_options', 'vstr-settings', array($this, 'submenu_settings'));
    }


    /**
     * Load admin CSS.
     * @access  public
     * @since   1.0.0
     * @return void
     */
    public function admin_enqueue_styles($hook = '') {
        wp_register_style($this->_token . '-jqueryui', esc_url($this->assets_url) . 'css/jquery-ui-theme/jquery-ui.min.css', array(), $this->_version);
        wp_enqueue_style($this->_token . '-jqueryui');

        wp_register_style($this->_token . '-admin', esc_url($this->assets_url) . 'css/vstr_admin.min.css', array('wp-pointer'), $this->_version);
        wp_enqueue_style($this->_token . '-admin');
    }

// End admin_enqueue_styles()

    /**
     * Load admin Javascript.
     * @access  public
     * @since   1.0.0
     * @return void
     */
    public function admin_enqueue_scripts($hook = '') {
        if (isset($_GET['page']) && strrpos($_GET['page'], 'vstr') !== false) {
                                    
            wp_register_script($this->_token . '-admin', esc_url($this->assets_url) . 'js/vstr_admin.min.js', array('jquery','jquery-ui-core','jquery-ui-tooltip','jquery-ui-mouse','jquery-ui-position','jquery-effects-core'), $this->_version);
            wp_enqueue_script($this->_token . '-admin');

            $visitDatasArray = array();
            if (isset($_GET['view-visit'])) {
                $visitDatasArray = $this->get_visitArray($_GET['view-visit']);
            }
            wp_localize_script($this->_token . '-admin', 'vstr_visitDatas', $visitDatasArray);
            $settings = $this->getSettings();
            $settings->txt_settingsSaved = esc_html__('Settings saved','vstr');
            wp_localize_script($this->_token . '-admin', 'vstr_settings', array($settings));
        }
    }

    /*
     * display visits page
     */

    public function submenu_visits() {
        global $wpdb;
        if (isset($_GET['remove'])) {
            $this->remove_visit($_GET['remove']);
        }
        if (isset($_GET['remove-group'])) {
            $ids = explode(',', $_GET['remove-group']);
            foreach ($ids as $removeID) {
                $this->remove_visit($removeID);
            }
        }

        $user = 0;
        if (isset($_GET['user']) && $_GET['user'] != '0') {
            $user = $_GET['user'];
        }


                $this->check_visitsTime();
                $this->check_visitsCountries();
                $visitsTable = new vstr_Visits_List_Table();
                $visitsTable->user = $user;
                $visitsTable->prepare_items();
                ?>
            <div class="wrap">
                <div id="vstr_icon-users" class="icon32"></div>
                <h2><?php echo esc_html__('Last Visits','vstr');?></h2>
                <p>
                    <label><?php echo esc_html__('Filter by User','vstr');?> : </label>
                    <?php
                    if (isset($_GET['user']) && $_GET['user'] != '0') {
                        wp_dropdown_users(array('show_option_all' => "All", 'selected' => $_GET['user']));
                    } else {
                        wp_dropdown_users(array('show_option_all' => "All"));
                    }
                    ?>
                    <label><?php echo esc_html__('Filter by IP','vstr');?> : </label>
                    <select id="vstr_filter_ip">
                        <option value=""><?php echo esc_html__('All','vstr');?></option>
                    <?php

                        $table_name = $wpdb->prefix . "vstr_visits";
                        $ips = $wpdb->get_results("SELECT DISTINCT ip FROM $table_name ORDER BY id DESC");
                        foreach ($ips as $ip){
                            $sel = '';
                            if (isset($_GET['filterIP']) && $_GET['filterIP'] == $ip->ip) {
                                $sel = 'selected';
                            }
                            echo '<option value="'.$ip->ip.'" '.$sel.'>'.$ip->ip.'</option>';
                        }

                    ?>
                    </select>
                    <span>&nbsp;</span>
                    <span>&nbsp;</span>
                    <label><?php echo esc_html__('Filter by Role','vstr');?> : </label>
                    <select id="vstr_roles">
                        <option value="0"><?php echo esc_html__('All','vstr');?></option>
                        <?php
                        if (isset($_GET['role']) && $_GET['role'] != '0') {
                            wp_dropdown_roles($_GET['role']);
                        } else {
                            wp_dropdown_roles();
                        }
                        ?>
                    </select>
                </p>
                <?php $visitsTable->display(); ?>
                <select id="vstr_bulk_actions">
                    <option value="-1" selected="selected"><?php echo esc_html__('Bulk Actions','vstr');?></option>
                    <option value="trash"><?php echo esc_html__('Move to Trash','vstr');?></option>
                </select>
                <input type="submit" name="" id="doaction2" class="button action" value="Apply">
            </div>
            <script>
                jQuery('.vstr_allcheck').click(function() {
                    if (jQuery(this).is(':checked')) {
                        jQuery('.wp-list-table .column-checkbox input[type=checkbox],.vstr_allcheck').prop('checked', 'checked');
                    } else {
                        jQuery('.wp-list-table .column-checkbox input[type=checkbox],.vstr_allcheck').removeProp('checked');
                    }
                });
                jQuery('#doaction2').click(function() {
                    if (jQuery('#vstr_bulk_actions').val() == 'trash') {
                        var ids = '';
                        jQuery('.wp-list-table .column-checkbox input[type=checkbox][data-id]:checked').each(function() {
                            ids += jQuery(this).data('id') + ',';
                        });
                        document.location.href = 'admin.php?page=vstr-visits&remove-group=' + ids;
                    }
                });

                jQuery('#vstr_filter_ip').change(function() {
                    if (jQuery('#vstr_filter_ip').val() != '') {
                        document.location.href = 'admin.php?page=vstr-visits&filterIP=' + jQuery('#vstr_filter_ip').val();
                    } else {
                        document.location.href = 'admin.php?page=vstr-visits';

                    }
                });
                jQuery('#user').change(function() {
                    if (jQuery('#user').val() != '0') {
                        document.location.href = 'admin.php?page=vstr-visits&user=' + jQuery('#user').val();
                    } else {
                        document.location.href = 'admin.php?page=vstr-visits';
                    }
                });
                jQuery('#vstr_roles').change(function() {
                    if (jQuery('#vstr_roles').val() != '0') {
                        document.location.href = 'admin.php?page=vstr-visits&role=' + jQuery('#roles').val();
                    } else {
                        document.location.href = 'admin.php?page=vstr-visits';
                    }
                });
                jQuery(document).ready(function() {
                    sessionStorage.vstrAdminStep = 0;
            <?php
            if (isset($_GET['role']) && $_GET['role'] != '0') {
                ?>
                        jQuery('table.wp-list-table td.column-role').each(function() {
                            if (jQuery(this).html() != '<?php echo $_GET['role']; ?>') {
                                jQuery(this).parent('tr').hide();
                            }
                        });
                <?php
            }
            ?>
                    <?php
           if (isset($_GET['filterIP']) && $_GET['filterIP'] != '') {
               ?>
                    jQuery('table.wp-list-table td.column-ip').each(function() {
                        if (jQuery(this).html() != '<?php echo $_GET['filterIP']; ?>') {
                            jQuery(this).parent('tr').hide();
                        }
                    });
                    <?php
                }
                ?>
                });
            </script>
            <?php
        
    }

    /*
     * Remove specific visit
     */

    public function remove_visit($visit_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . "vstr_steps";
        $wpdb->delete($table_name, array('visitID' => $visit_id));

        $table_name = $wpdb->prefix . "vstr_visits";
        $wpdb->delete($table_name, array('id' => $visit_id));
    }

    /*
     * Get specific visit datas
     */

    private function get_visit($visit_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . "vstr_visits";
        $rows = $wpdb->get_results("SELECT * FROM $table_name WHERE id=$visit_id LIMIT 1");
        return $rows[0];
    }

    /*
     * Get specific visit datas
     */

    private function get_visitArray($visit_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . "vstr_visits";
        $rep = array();
        
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE id='%s' LIMIT 1", $visit_id));
        if ($rows[0]->userID > 0) {
            $user = get_userdata($rows[0]->userID);
            $user = $user->user_login;
        } else {
            $user = 'unknow';
        }
        $rows[0]->user = $user;
        foreach ($rows[0] as $column => $value) {
            $rep[$column] = $value;
        }
        $table_name = $wpdb->prefix . "vstr_steps";
        $rowsS = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE visitID=%s ORDER BY id ASC", $visit_id));
        $rep['steps'] = array();
        $lastStep = false;
        foreach ($rowsS as $row) {
            if ($lastStep) {
                $timePast = abs(strtotime($row->date) - strtotime($lastStep->date));
                $row->timePast = $timePast;
            }
            $lastStep = $row;
            $row->dateJS = date(date('D M d Y H:i:s O'), strtotime($row->date));
            $rep['steps'][] = $row;
        }
        return $rep;
    }


    /**
     * display settings page
     * @return void
     */
    public function submenu_settings() {
        global $wpdb;
        $table_name = $wpdb->prefix . "vstr_settings";
        $settings = $wpdb->get_results("SELECT * FROM $table_name WHERE id=1 LIMIT 1");
        $settings = $settings[0];
        ?>
        <div class="wrap wpeSettings">
            <div class="wrap">
                <h2>Settings</h2>
                <div id="vstr_response"></div>
                <form id="vstr_form_settings" method="post" action="#">
                    <input id="id" type="hidden" name="id" value="1">
                    <table class="form-table">
                        <tbody>
                            
                            <tr>
                                <th scope="row"><?php echo esc_html__('Targeted customers','vstr');?> </th>
                                <td>
                                    <select id="vstr_customersTarget" name="customersTarget">
                                        <?php
                                        $sel1 = '';
                                        $sel2 = '';
                                        if ($settings->customersTarget == 'registred') {
                                            $sel2 = 'selected';
                                        } else {
                                            $sel1 = 'selected';
                                        }
                                        echo '<option ' . $sel1 . ' value="all">'.esc_html__('All','vstr').'</option>';
                                        echo '<option ' . $sel2 . ' value="registred">'.esc_html__('Registered','vstr').'</option>';
                                        ?>
                                    </select>
                                    <label for="vstr_customersTarget"> <span class="description"><?php echo esc_html__('Defines the targeted customers','vstr');?></span> </label></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Retain visitor data for','vstr');?> :</th>
                                <td>
                                    <select id="vstr_removeDays" name="removeDays">
                                        <?php
                                        $sel1 = '';
                                        $sel2 = '';
                                        $sel3 = '';
                                        $sel4 = '';
                                        $sel5 = '';
                                        $sel6 = '';
                                        $sel7 = '';
                                        if ($settings->removeDays == 3) {
                                            $sel1 = 'selected';
                                        } else if ($settings->removeDays == 7) {
                                            $sel2 = 'selected';
                                        } else if ($settings->removeDays == 30) {
                                            $sel3 = 'selected';
                                        } else if ($settings->removeDays == 60) {
                                            $sel4 = 'selected';
                                        } else if ($settings->removeDays == 90) {
                                            $sel5 = 'selected';
                                        } else if ($settings->removeDays == 0) {
                                            $sel6 = 'selected';
                                        } else if ($settings->removeDays == 1) {
                                            $sel7 = 'selected';
                                        } else {
                                            $sel2 = 'selected';
                                        }
                                        echo '<option ' . $sel7 . ' value="1">'.esc_html__('1 day','vstr').'</option>';
                                        echo '<option ' . $sel1 . ' value="3">'.esc_html__('3 days','vstr').'</option>';
                                        echo '<option ' . $sel2 . ' value="7">'.esc_html__('7 days','vstr').'</option>';
                                        echo '<option ' . $sel3 . ' value="30">'.esc_html__('1 month','vstr').'</option>';
                                        echo '<option ' . $sel4 . ' value="60">'.esc_html__('2 months','vstr').'</option>';
                                        echo '<option ' . $sel5 . ' value="90">'.esc_html__('3 months','vstr').'</option>';
                                        echo '<option ' . $sel6 . ' value="0">'.esc_html__('Forever','vstr').'</option>';
                                        ?>
                                    </select>
                                    <label for="vstr_removeDays"> <span class="description"><?php echo esc_html__('Visits will be deleted after this period','vstr');?></span> </label></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Information panel position','vstr');?></th>
                                <td>
                                    <select id="vstr_panelPosition" name="panelPosition">
                                        <?php
                                        $sel1 = '';
                                        $sel2 = '';
                                        if ($settings->panelPosition == 'right') {
                                            $sel2 = 'selected';
                                        } else {
                                            $sel1 = 'selected';
                                        }
                                        echo '<option ' . $sel1 . ' value="left">'.esc_html__('Left','vstr').'</option>';
                                        echo '<option ' . $sel2 . ' value="right">'.esc_html__('Right','vstr').'</option>';
                                        ?>
                                    </select>
                                    <label for="vstr_panelPosition"> <span class="description"><?php echo esc_html__('Defines the information panel position','vstr'); ?></span> </label></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Remove 00:00 duration visits','vstr'); ?> </th>
                                <td>
                                    <select id="vstr_noBots" name="noBots">
                                        <?php
                                        $sel1 = '';
                                        $sel2 = '';
                                        if ($settings->noBots) {
                                            $sel2 = 'selected';
                                        } else {
                                            $sel1 = 'selected';
                                        }
                                        echo '<option ' . $sel1 . ' value="0">'.esc_html__('No','vstr').'</option>';
                                        echo '<option ' . $sel2 . ' value="1">'.esc_html__('Yes','vstr').'</option>';
                                        ?>
                                    </select>
                                    <label for="vstr_noBots"> <span class="description"><?php echo esc_html__('Remove useless visits from list','vstr'); ?></span> </label></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__("Don't record visits from administrators",'vstr'); ?> :</th>
                                <td>
                                     <select id="dontTrackAdmins" name="dontTrackAdmins">
                                        <?php
                                        $sel1 = '';
                                        $sel2 = '';
                                        if ($settings->dontTrackAdmins) {
                                            $sel2 = 'selected';
                                        } else {
                                            $sel1 = 'selected';
                                        }
                                        echo '<option ' . $sel1 . ' value="0">'.esc_html__('No','vstr').'</option>';
                                        echo '<option ' . $sel2 . ' value="1">'.esc_html__('Yes','vstr').'</option>';
                                        ?>
                                    </select>
                                    <label for="dontTrackAdmins"> <span class="description"><?php echo esc_html__("The users having the admin role will not be tracked",'vstr'); ?></span> </label>

                                </td>
                            </tr>
                             <tr>
                                <th scope="row"><?php echo esc_html__("Don't record visits from tactile devices",'vstr'); ?> :</th>
                                <td>
                                     <select id="vstr_dontTrackTactile" name="dontTrackTactile">
                                        <?php
                                        $sel1 = '';
                                        $sel2 = '';
                                        if ($settings->dontTrackTactile) {
                                            $sel2 = 'selected';
                                        } else {
                                            $sel1 = 'selected';
                                        }
                                        echo '<option ' . $sel1 . ' value="0">'.esc_html__('No','vstr').'</option>';
                                        echo '<option ' . $sel2 . ' value="1">'.esc_html__('Yes','vstr').'</option>';
                                        ?>
                                    </select>
                                    <label for="vstr_dontTrackTactile"> <span class="description"><?php echo esc_html__("Users using mobiles and tablets will not be tracked",'vstr'); ?></span> </label>

                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__("Don't record visits from these IP",'vstr'); ?> :</th>
                                <td>
                                    <textarea id="vstr_filerIP" name="filterIP" placeholder="<?php echo esc_html__("Enter the IP adresses, separated by commas",'vstr'); ?>"><?php
                                        echo $settings->filterIP;
                                        ?></textarea>
                                    <label for="vstr_filerIP"> <span class="description"><?php echo esc_html__("Enter the IP adresses, separated by commas",'vstr'); ?></span> </label>

                                </td>
                            </tr>
                            
                           
                            <tr>
                                <th scope="row"></th>
                                <td>
                                    <input type="submit" value="Save" class="button-primary"/>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </form>
            </div>
        </div>
        <?php
    }


    /*
     * Return plugin settings
     */

    private function getSettings() {
        global $wpdb;
        $table_name = $wpdb->prefix . "vstr_settings";
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
            $rows = $wpdb->get_results("SELECT * FROM $table_name WHERE id=1");
            return $rows[0];
        } else {
            return false;
        }
    }

    /**
     * save settings
     * @return void
     */
    public function settings_save() {
        global $wpdb;
        
        if (current_user_can('manage_options')) {
        $response = "Error, try again later.";
        $table_name = $wpdb->prefix . "vstr_settings";
        $sqlDatas = array();
        foreach ($_POST as $key => $value) {
            if ($key != 'action') {
                if ($key == 'filterIP') {
                    $value = str_replace(" ", "", $value);
                    $value = trim(preg_replace('/\s\s+/', '', $value));
                }
                $sqlDatas[$key] = stripslashes(sanitize_text_field($value));
            }
        }
        $wpdb->update($table_name, $sqlDatas, array('id' => 1));
        $response = '<div id="message" class="updated"><p>'.esc_html__('Settings saved','vstr').'</strong>.</p></div>';

        echo $response;
        }
        die();
    }
    private function get_country_city_from_ip($ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new InvalidArgumentException("IP is not valid");
        }
        $response = @file_get_contents('https://ipinfo.io/' . $ip);
        if (empty($response)) {
            return "error";
        }
        
        $response = json_decode($response);
        $ipInfo = array();
        $ipInfo['country'] = $response->country;
        $ipInfo['city'] = $response->city;
        $ipInfo['region'] = $response->region;
        
        return $ipInfo;
    }



    /*
     * Find the country from ip
     */
    private function check_visitsCountries() {
        global $wpdb;
         $table_name = $wpdb->prefix . "vstr_visits";
        $rows = $wpdb->get_results("SELECT * FROM $table_name WHERE country='' ORDER BY id DESC LIMIT 100");
        foreach ($rows as $row => $value) {
	    $ipInfo = $this->get_country_city_from_ip($value->ip);
            $wpdb->update($table_name, array('country' => $ipInfo['country'], 'city' => $ipInfo['city']), array('id' => $value->id));
            
        }
    }

    /*
     * Save the total time of visits
     */

    private function check_visitsTime() {
        global $wpdb;
        $settings = $this->getSettings();
        $table_name = $wpdb->prefix . "vstr_visits";
        $rows = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id DESC");
        foreach ($rows as $row => $value) {
            $table_nameS = $wpdb->prefix . "vstr_steps";
            $rowsS = $wpdb->get_results("SELECT * FROM $table_nameS WHERE visitID=$value->id ORDER BY id DESC LIMIT 1");
            $timePast = 0;
            $lastDate = false;
            if (count($rowsS) > 0) {
                $step = $rowsS[0];
                $timePast = (strtotime($step->date) - strtotime($value->date));
                $wpdb->update($table_name, array('timePast' => $timePast), array('id' => $value->id));
            }
        }
        if($settings->noBots){
            $wpdb->delete($table_name, array('timePast' => 0));      
        }
    }

    /*
     * Save the total time of visits
     */

    private function check_visitsToDelete() {
        global $wpdb;
        $settings = $this->getSettings();
        if ($settings) {
            if ($settings->removeDays > 0) {
                $table_name = $wpdb->prefix . "vstr_visits";
                $rows = $wpdb->get_results("SELECT * FROM $table_name  ORDER BY id DESC");
                foreach ($rows as $row) {
                    if (abs(strtotime(date('Ymd h:i:s')) - strtotime($row->date)) > $settings->removeDays * 24 * 60 * 60) {
                        $wpdb->delete($table_name, array('id' => $row->id));
                    }
                }
            }
        }
    }

    /**
     * Main Instance
     *
     *
     * @since 1.0.0
     * @static
     * @return Main instance
     */
    public static function instance($parent) {
        if (is_null(self::$_instance)) {
            self::$_instance = new self($parent);
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
        _doing_it_wrong(__FUNCTION__, '', $this->parent->_version);
    }

// End __clone()

    /**
     * Unserializing instances of this class is forbidden.
     *
     * @since 1.0.0
     */
    public function __wakeup() {
        _doing_it_wrong(__FUNCTION__, '', $this->parent->_version);
    }

// End __wakeup()
}
