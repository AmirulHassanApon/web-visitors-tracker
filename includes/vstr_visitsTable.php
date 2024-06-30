<?php

if (!class_exists('Visits_List_Table')) {
    require_once (ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class vstr_Visits_List_Table extends WP_List_Table {

    public $user = 0;

    /**
     * Prepare the items for the table to process
     *
     * @return Void
     */
    public function prepare_items() {
        $columns = $this->get_columns();
        $hidden = $this->get_hidden_columns();
        $sortable = $this->get_sortable_columns();
        $data = $this->table_data();
        $this->_column_headers = array($columns, $hidden, $sortable);
        $this->items = $data;
    }

    /**
     * Override the parent columns method. Defines the columns to use in your listing table
     *
     * @return Array
     */
    public function get_columns() {
        $columns = array('id' => __('ID','vstr'),'checkbox'=>'<input type="checkbox" id="vstr_allcheck" class="vstr_allcheck" />', 'date' => __('Date','vstr'), 'user' => __('User','vstr'), 'role' => __('Role','vstr'), 'timePast' => __('Time past','vstr'), 'ip' => __('IP','vstr'), 'browser' => __('Browser','vstr'),'country' => __('Country','vstr'),'city'=> __('City','vstr'), 'view' => __('View the visit','vstr'), 'remove' => '');

        return $columns;
    }

    /**
     * Define which columns are hidden
     *
     * @return Array
     */
    public function get_hidden_columns() {
        return array('id');
        return null;
    }

    /**
     * Define the sortable columns
     *
     * @return Array
     */
    public function get_sortable_columns() {
        return null;
    }

    /**
     * Get the table data
     *
     * @return Array
     */
    private function table_data() {
        global $wpdb;
        $userF = $this->user;
        $table_name = $wpdb->prefix . "vstr_visits";
        if ($userF > 0) {
            $rows = $wpdb->get_results("SELECT * FROM $table_name WHERE userID=$userF ORDER BY id DESC");
        } else {
            $rows = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id DESC");
        }



        $data = array();
        foreach ($rows as $row) {
            $timePast = intval($row->timePast);
            $hour = 0;
            $mins = 0;
            $sec = 0;
            if ($timePast >= 3600) {
                $hour = floor($timePast / 3600);
            }
            if ($timePast >= 60) {
                $mins = $timePast - $hour * 3600;
                $mins = floor($mins / 60);
            }
            $sec = $timePast - (($hour * 3600) + ($mins * 60));
            $timePast = sprintf("%02d", $hour) . ':' . sprintf("%02d", $mins) . ':' . sprintf("%02d", $sec);

            $hasStep = false;
            $table_nameSteps = $wpdb->prefix . "vstr_steps";
            $rowSteps = $wpdb->get_results("SELECT visitID FROM $table_nameSteps WHERE visitID=$row->id LIMIT 1");
            if (count($rowSteps)>0){
                $hasStep = true;
            }

            $user = 'unknow';
            $role = '';
            if ($row->userID > 0) {
                $user = get_userdata($row->userID);
                $role = $user->roles[0];
                $user = $user->user_login;
            }
            $data[] = array('id' => $row->id,'checkbox'=>'<input type="checkbox" data-id="'.$row->id.'"/>', 'date' => $row->date,'hasStep'=>$hasStep, 'timePast' => $timePast, 'userID' => $row->userID, 'user' => $user, 'role' => $role, 'browser' => $row->browser, 'ip' => $row->ip, 'country' => $row->country, 'city'=>$row->city,'remove' => '', 'view' => '');
        }
        return $data;
    }

    // Used to display the value of the id column
    public function column_id($item) {
        return $item['id'];
    }

    /**
     * Define what data to show on each column of the table
     *
     * @param  Array $item        Data
     * @param  String $column_name - Current column name
     *
     * @return Mixed
     */
    public function column_default($item, $column_name) {
        global $wpdb;
        switch ($column_name) {
            case 'remove' :
                return '<a href="admin.php?page=vstr-visits&remove=' . $item['id'] . '">Delete</a>';
                break;
            case 'id' :
            case 'date':
            case 'role':
            case 'browser':
            case 'country':
            case 'city':
            case 'ip':
            case 'checkbox':
                return $item[$column_name];
            case 'timePast':
                $rand = date('His');
                return $item[$column_name];
                break;
            case 'view':
                if ($item['hasStep']){
                   
                $urls = '';
                if ($item['hasStep']){
                     $table_nameSteps = $wpdb->prefix . "vstr_steps";
                     $i=0;
                     $lastPage = "";
                     $urls = "";
                     $rowSteps = $wpdb->get_results("SELECT * FROM $table_nameSteps WHERE visitID=".$item['id']." ORDER BY id ASC");
                     foreach ($rowSteps as $step) {
                         if($step->page == ""){
                             $step->page = home_url();
                         }
                         if($i ==0){
                             $urls.=  $step->page.' <br/>-><br/> ';
                         }
                         $lastPage = $step->page;
                         $i++;
                     }
                     $urls.= $lastPage;
                }
                    $rand = date('His');
                 $urlsBtn = '<span id="infoUrl_'.$rand.'" class="infoUrls dashicons dashicons-info" data-urls="'.$urls.'"></span>';
                    return '<a href="admin.php?page=vstr-visits&view-visit=' . $item['id'] . '" class="button button-primary button-small">'.__('View the visit','vstr').'</a> '.$urlsBtn;
                } else {  }
                break;
            case 'user':
                if ($item['userID'] > 0) {
                    return '<a href="user-edit.php?user_id=' . $item['userID'] . '">' . $item[$column_name] . '</a>';
                } else {
                    return $item[$column_name];
                }
                break;

            default :
                return print_r($item, true);
        }
    }

}
