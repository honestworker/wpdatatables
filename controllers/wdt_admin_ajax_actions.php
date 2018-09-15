<?php

defined('ABSPATH') or die('Access denied.');

/**
 * Test the MySQL connection settings
 */
function wdtTestMySqlSettings() {

    $returnArray = array('success' => array(), 'errors' => array());

    $mysqlConnectionSettings = $_POST['mysql_settings'];

    try {
        $Sql = new PDTSql(
            $mysqlConnectionSettings['host'],
            $mysqlConnectionSettings['db'],
            $mysqlConnectionSettings['user'],
            $mysqlConnectionSettings['password'],
            $mysqlConnectionSettings['port']
        );
        if ($Sql->isConnected()) {
            $returnArray['success'][] = __('Successfully connected to the MySQL server.', 'wpdatatables');
        } else {
            $returnArray['errors'][] = __('wpDataTables could not connect to MySQL server.', 'wpdatatables');
        }
    } catch (Exception $e) {
        $returnArray['errors'][] = __('wpDataTables could not connect to MySQL server. MySQL said: ', 'wpdatatables') . $e->getMessage();
    }
    echo json_encode($returnArray);
    exit();
}

add_action('wp_ajax_wpdatatables_test_mysql_settings', 'wdtTestMySqlSettings');

/**
 * Method to save the config for the table and columns
 */
function wdtSaveTableWithColumns() {
    if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['wdtNonce'], 'wdtEditNonce')) {
        exit();
    }

    $table = apply_filters(
        'wpdatatables_before_save_table',
        json_decode(
            stripslashes_deep($_POST['table'])
        )
    );

    WDTConfigController::saveTableConfig($table);
}

add_action('wp_ajax_wpdatatables_save_table_config', 'wdtSaveTableWithColumns');

/**
 * Save plugin settings
 */
function wdtSavePluginSettings() {
    if (!current_user_can('manage_options')) {
        exit();
    }

    WDTSettingsController::saveSettings(apply_filters('wpdatatables_before_save_settings', $_POST['settings']));
    exit();
}

add_action('wp_ajax_wpdatatables_save_plugin_settings', 'wdtSavePluginSettings');

/**
 * Duplicate the table
 */
function wdtDuplicateTable() {
    global $wpdb;

    if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['wdtNonce'], 'wdtDuplicateTableNonce')) {
        exit();
    }

    $tableId = (int)$_POST['table_id'];
    if (empty($tableId)) {
        return false;
    }
    $manualDuplicateInput = (int)$_POST['manual_duplicate_input'];
    $newTableName = sanitize_text_field($_POST['new_table_name']);

    // Getting the table data
    $tableData = WDTConfigController::loadTableFromDB($tableId);
    $mySqlTableName = $tableData->mysql_table_name;
    $content = $tableData->content;

    // Create duplicate version of input table if checkbox is selected
    if ($manualDuplicateInput) {

        // Generating new input table name
        $cnt = 1;
        $newNameGenerated = false;
        while (!$newNameGenerated) {
            $newName = $tableData->mysql_table_name . '_' . $cnt;
            $checkTableQuery = "SHOW TABLES LIKE '{$newName}'";
            if (!get_option('wdtUseSeparateCon')) {
                $res = $wpdb->get_results($checkTableQuery);
            } else {
                $sql = new PDTSql(WDT_MYSQL_HOST, WDT_MYSQL_DB, WDT_MYSQL_USER, WDT_MYSQL_PASSWORD, WDT_MYSQL_PORT);
                $res = $sql->getRow($checkTableQuery);
            }
            if (!empty($res)) {
                $cnt++;
            } else {
                $newNameGenerated = true;
            }
        }

        // Input table queries
        $query1 = "CREATE TABLE {$newName} LIKE {$tableData->mysql_table_name};";
        $query2 = "INSERT INTO {$newName} SELECT * FROM {$tableData->mysql_table_name};";

        if (!get_option('wdtUseSeparateCon')) {
            $wpdb->query($query1);
            $wpdb->query($query2);
        } else {
            $sql->doQuery($query1);
            $sql->doQuery($query2);
        }
        $mySqlTableName = $newName;
        $content = str_replace($tableData->mysql_table_name, $newName, $tableData->content);
    }

    // Creating new table
    $wpdb->insert(
        $wpdb->prefix . 'wpdatatables',
        array(
            'title' => $newTableName,
            'show_title' => $tableData->show_title,
            'table_type' => $tableData->table_type,
            'content' => $content,
            'filtering' => $tableData->filtering,
            'filtering_form' => $tableData->filtering_form,
            'sorting' => $tableData->sorting,
            'tools' => $tableData->tools,
            'server_side' => $tableData->server_side,
            'editable' => $tableData->editable,
            'inline_editing' => $tableData->inline_editing,
            'popover_tools' => $tableData->popover_tools,
            'editor_roles' => $tableData->editor_roles,
            'mysql_table_name' => $mySqlTableName,
            'edit_only_own_rows' => $tableData->edit_only_own_rows,
            'userid_column_id' => $tableData->userid_column_id,
            'display_length' => $tableData->display_length,
            'auto_refresh' => $tableData->auto_refresh,
            'fixed_columns' => $tableData->fixed_columns,
            'fixed_layout' => $tableData->fixed_layout,
            'responsive' => $tableData->responsive,
            'scrollable' => $tableData->scrollable,
            'word_wrap' => $tableData->word_wrap,
            'hide_before_load' => $tableData->hide_before_load,
            'var1' => $tableData->var1,
            'var2' => $tableData->var2,
            'var3' => $tableData->var3,
            'tabletools_config' => serialize($tableData->tabletools_config),
            'advanced_settings' => $tableData->advanced_settings
        )
    );

    $newTableId = $wpdb->insert_id;

    // Getting the column data
    $columns = WDTConfigController::loadColumnsFromDB($tableId);

    // Creating new columns
    foreach ($columns as $column) {
        $wpdb->insert(
            $wpdb->prefix . 'wpdatatables_columns',
            array(
                'table_id' => $newTableId,
                'orig_header' => $column->orig_header,
                'display_header' => $column->display_header,
                'filter_type' => $column->filter_type,
                'column_type' => $column->column_type,
                'input_type' => $column->input_type,
                'input_mandatory' => $column->input_mandatory,
                'id_column' => $column->id_column,
                'group_column' => $column->group_column,
                'sort_column' => $column->sort_column,
                'hide_on_phones' => $column->hide_on_phones,
                'hide_on_tablets' => $column->hide_on_tablets,
                'visible' => $column->visible,
                'sum_column' => $column->sum_column,
                'skip_thousands_separator' => $column->skip_thousands_separator,
                'width' => $column->width,
                'possible_values' => $column->possible_values,
                'default_value' => $column->default_value,
                'css_class' => $column->css_class,
                'text_before' => $column->text_before,
                'text_after' => $column->text_after,
                'formatting_rules' => $column->formatting_rules,
                'calc_formula' => $column->calc_formula,
                'color' => $column->color,
                'pos' => $column->pos,
                'advanced_settings' => $column->advanced_settings
            )
        );

        if ($column->id == $tableData->userid_column_id) {
            $userIdColumnNewId = $wpdb->insert_id;

            $wpdb->update(
                $wpdb->prefix . 'wpdatatables',
                array('userid_column_id' => $userIdColumnNewId),
                array('id' => $newTableId)
            );
        }

    }

    exit();

}

add_action('wp_ajax_wpdatatables_duplicate_table', 'wdtDuplicateTable');

/**
 * Duplicate the chart
 */

function wdtDuplicateChart () {
	global $wpdb;

	if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['wdtNonce'], 'wdtDuplicateChartNonce')) {
		exit();
	}

	$chartId = (int)$_POST['chart_id'];
	if (empty($chartId)) {
		return false;
	}
	$newChartName = sanitize_text_field($_POST['new_chart_name']);

	$chartQuery = $wpdb->prepare(
		'SELECT * FROM ' . $wpdb->prefix . 'wpdatacharts WHERE id = %d',
		$chartId
	);

	$wpDataChart = $wpdb->get_row($chartQuery);

	// Creating new table
	$wpdb->insert(
		$wpdb->prefix . "wpdatacharts",
		array(
			'wpdatatable_id'    => $wpDataChart->wpdatatable_id,
			'title'             => $newChartName,
			'engine'            => $wpDataChart->engine,
			'type'              => $wpDataChart->type,
			'json_render_data'  => $wpDataChart->json_render_data
		)
	);

	exit();
}

add_action('wp_ajax_wpdatatables_duplicate_chart', 'wdtDuplicateChart');

/**
 * Create a manually built table and open in Edit Page
 */
function wdtCreateManualTable() {

    if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['wdtNonce'], 'wdtConstructorNonce')) {
        exit();
    }

    $tableData = $_POST['tableData'];
    $tableData = apply_filters('wpdatatables_before_create_manual_table', $tableData);

    // Create a new Constructor object
    $constructor = new wpDataTableConstructor();

    // Generate and return a new 'Manual' type table
    $newTableId = $constructor->generateManualTable($tableData);

    // Generate a link for new table
    echo admin_url('admin.php?page=wpdatatables-constructor&source&table_id=' . $newTableId);

    exit();
}

add_action('wp_ajax_wpdatatables_create_manual_table', 'wdtCreateManualTable');

/**
 * Action for generating a WP-based MySQL query
 */
function wdtGenerateWPBasedQuery() {
    if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['wdtNonce'], 'wdtConstructorNonce')) {
        exit();
    }

    $tableData = $_POST['tableData'];
    $tableData = apply_filters('wpdatatables_before_generate_wp_based_query', $tableData);

    $constructor = new wpDataTableConstructor();
    $constructor->generateWPBasedQuery($tableData);
    $result = array(
        'query' => $constructor->getQuery(),
        'preview' => $constructor->getQueryPreview()
    );

    echo json_encode($result);
    exit();
}

add_action('wp_ajax_wpdatatables_generate_wp_based_query', 'wdtGenerateWPBasedQuery');

/**
 * Action for refreshing the WP-based query
 */
function wdtRefreshWPQueryPreview() {
    if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['wdtNonce'], 'wdtConstructorNonce')) {
        exit();
    }

    $query = sanitize_text_field($_POST['query']);

    $constructor = new wpDataTableConstructor();
    $constructor->setQuery($query);

    echo $constructor->getQueryPreview();
    exit();
}

add_action('wp_ajax_wpdatatables_refresh_wp_query_preview', 'wdtRefreshWPQueryPreview');

/**
 * Action for generating the table from query/constructed table data
 */
function wdtConstructorGenerateWDT() {
    if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['wdtNonce'], 'wdtConstructorNonce')) {
        exit();
    }

    $tableData = $_POST['table_data'];

    $constructor = new wpDataTableConstructor();
    $res = $constructor->generateWdtBasedOnQuery($tableData);
    if (empty($res->error)) {
        $res->link = get_admin_url() . "admin.php?page=wpdatatables-constructor&source&table_id={$res->table_id}";
    }

    echo json_encode($res);
    exit();
}

add_action('wp_ajax_wpdatatables_constructor_generate_wdt', 'wdtConstructorGenerateWDT');

/**
 * Request the column list for the selected tables
 */
function wdtConstructorGetMySqlTableColumns() {
    if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['wdtNonce'], 'wdtConstructorNonce')) {
        exit();
    }

    $tables = array_map('sanitize_text_field', $_POST['tables']);
    $columns = wpDataTableConstructor::listMySQLColumns($tables);

    echo json_encode($columns);
    exit();
}

add_action('wp_ajax_wpdatatables_constructor_get_mysql_table_columns', 'wdtConstructorGetMySqlTableColumns');

/**
 * Action for generating a WP-based MySQL query
 */
function wdtGenerateMySqlBasedQuery() {
    if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['wdtNonce'], 'wdtConstructorNonce')) {
        exit();
    }

    $tableData = $_POST['tableData'];
    $tableData = apply_filters('wpdatatables_before_generate_mysql_based_query', $tableData);

    $constructor = new wpDataTableConstructor();
    $constructor->generateMySQLBasedQuery($tableData);
    $result = array(
        'query' => $constructor->getQuery(),
        'preview' => $constructor->getQueryPreview()
    );

    echo json_encode($result);
    exit();
}

add_action('wp_ajax_wpdatatables_generate_mysql_based_query', 'wdtGenerateMySqlBasedQuery');

/**
 * Generate a file-based table preview (first 4 rows)
 */
function wdtConstructorPreviewFileTable() {
    if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['wdtNonce'], 'wdtConstructorNonce')) {
        exit();
    }

    $tableData = $_POST['tableData'];
    $tableData = apply_filters('wpdatatables_before_preview_file_table', $tableData);

    $constructor = new wpDataTableConstructor();
    $result = $constructor->previewFileTable($tableData);

    echo json_encode($result);
    exit();
}

add_action('wp_ajax_wpdatatables_preview_file_table', 'wdtConstructorPreviewFileTable');

add_action('wp_ajax_nopriv_wpdatatables_preview_file_table', 'wdtConstructorPreviewFileTable');

add_action('wp_ajax_wpdatatables_preview_file_table_formData', 'calback_wdtConstructorPreviewFileTable');
add_action('wp_ajax_nopriv_wpdatatables_preview_file_table_formData', 'calback_wdtConstructorPreviewFileTable');
function calback_wdtConstructorPreviewFileTable(){
   
if(!empty($_FILES['file']['name'])){
    $uploadedFile = '';
    if(!empty($_FILES["file"]["type"])){
        $fileName = time().'_'.$_FILES['file']['name'];
        $valid_extensions = array("jpeg", "jpg", "png",'xlsx');
        $temporary = explode(".", $_FILES["file"]["name"]);
        $file_extension = end($temporary);
        if(in_array($file_extension, $valid_extensions)){
            $sourcePath = $_FILES['file']['tmp_name'];
            $uploadss = wp_upload_dir();
            $targetPath = $uploadss['basedir'] . "/tempDatatable/". $fileName;
            if(move_uploaded_file($sourcePath, $targetPath)) {
                $uploadedFile = $fileName;
                echo json_encode(array('status'=>true,'name'=>$uploadedFile,'path'=>$targetPath));
            }
        }
    }
}
   die;
}

/**
 * Generate a file-based table preview (first 4 rows)
 */
function wdtConstructorFrontendPreviewFileTable() {
    $tableData = $_POST['tableData'];
    $tableData = apply_filters('wpdatatables_before_preview_file_table', $tableData);
    $constructor = new wpDataTableConstructor();
    $result = $constructor->previewFileTable($tableData);

    echo json_encode($result);
    exit();
}
add_action('wp_ajax_wpdatatables_frontend_preview_file_table', 'wdtConstructorFrontendPreviewFileTable');
add_action('wp_ajax_nopriv_wpdatatables_frontend_preview_file_table', 'wdtConstructorFrontendPreviewFileTable');

function calback_wdtUploadTable() {   
    if(!empty($_FILES['file']['name'])) {
        $uploadedFile = '';
        if (!empty($_FILES["file"]["type"])) {
            $fileName = time().'_'.$_FILES['file']['name'];
            $valid_extensions = array("jpeg", "jpg", "png",'xlsx');
            $temporary = explode(".", $_FILES["file"]["name"]);
            $file_extension = end($temporary);
            if (in_array($file_extension, $valid_extensions)) {
                $sourcePath = $_FILES['file']['tmp_name'];
                $uploadss = wp_upload_dir();
                $targetPath = $uploadss['basedir']."/tempDatatable/".$fileName;
                if (move_uploaded_file($sourcePath, $targetPath)) {
                    $uploadedFile = $fileName;
                    echo json_encode(array('status'=>true, 'file'=> $uploadedFile, 'filePath'=> $targetPath));
                }
            }
        }
    }
    die;
}
add_action('wp_ajax_wpdatatables_upload_file_formData', 'calback_wdtUploadTable');
add_action('wp_ajax_nopriv_wpdatatables_upload_file_formData', 'calback_wdtUploadTable');

/**
 * Read data from file and generate the table
 */
function wdtConstructorReadFileData() {
    if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['wdtNonce'], 'wdtConstructorNonce')) {
        exit();
    }

    $result = array();
    $tableData = $_POST['tableData'];
    $tableData = apply_filters('wpdatatables_before_read_file_data', $tableData);

    $constructor = new wpDataTableConstructor();
    
    try {
        $constructor->readFileData($tableData);
        if ($constructor->getTableId() != false) {
            $result['res'] = 'success';
            $result['link'] = get_admin_url() . "admin.php?page=wpdatatables-constructor&source&table_id=" . $constructor->getTableId();
        } else {
            $result['res'] = 'error';
            $result['text'] = __('There was an error while trying to import table', 'wpdatatables');
        }
    } catch (Exception $e) {
        $result['res'] = 'error';
        $result['text'] = __('There was an error while trying to import table. Exception: ', 'wpdatatables') . $e->getMessage();
    }

    echo json_encode($result);
    exit();
}

add_action('wp_ajax_wpdatatables_constructor_read_file_data', 'wdtConstructorReadFileData');

/**
 * Read data from file and generate the table
 */
function wdtConstructorReadFileDataFrontend() {
    if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['wdtNonce'], 'wdtConstructorNonce')) {
       // exit();
    }

    $result = array();
    $tableData = $_POST['tableData'];
    $tableData = apply_filters('wpdatatables_before_read_file_data', $tableData);

    $constructor = new wpDataTableConstructor();
      $constructor->setTableId($tableData['tableId']);
      $constructor->generateTableName_for_update();
      
    try {
        $result['errors'] = $constructor->readFileData_update($tableData);
        if ($constructor->getTableId() != false) {
            $result['res'] = 'success';
            $result['link'] = get_admin_url() . "admin.php?page=wpdatatables-constructor&source&table_id=" . $constructor->getTableId();
            wp_delete_file($tableData['filePath']);
        } else {
            $result['res'] = 'error';
            $result['text'] = __('There was an error while trying to import table', 'wpdatatables');
        }
    } catch (Exception $e) {
        $result['res'] = 'error';
        $result['text'] = __('There was an error while trying to import table. Exception: ', 'wpdatatables') . $e->getMessage();
    }

    echo json_encode($result);
    exit();
}
add_action('wp_ajax_wpdatatables_constructor_read_file_data_frontend', 'wdtConstructorReadFileDataFrontend');

/**
 * Read data from file and generate the table
 */
function wdtConstructorParseTable() {
    if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['wdtNonce'], 'wdtConstructorNonce')) {
       // exit();
    }
    $table_id = $_POST['table_id'];
    $constructor = new wpDataTableConstructor();
    $constructor->setTableId($table_id);
    try {
         $constructor->parseTable($table_id);
        if ($constructor->getTableId() != false) {
            $result['res'] = 'success';
            $result['link'] = get_admin_url() . "admin.php?page=wpdatatables-constructor&source&table_id=" . $table_id;
        } else {
            $result['res'] = 'error';
            $result['text'] = __('There was an error while trying to parse table', 'wpdatatables');
        }
    } catch (Exception $e) {
        $result['res'] = 'error';
        $result['text'] = __('There was an error while trying to parse table. Exception: ', 'wpdatatables') . $e->getMessage();
    }

    echo json_encode($result);
    exit();
}
add_action('wp_ajax_wpdatatables_frontend_parse_table', 'wdtConstructorParseTable');


/**
 * Add a column to a manually  created table
 */
function wdtAddNewManualColumn() {
    if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['wdtNonce'], 'wdtFrontendEditTableNonce')) {
        exit();
    }

    $tableId = (int)$_POST['table_id'];
    $columnData = $_POST['column_data'];
    wpDataTableConstructor::addNewManualColumn($tableId, $columnData);

    exit();
}

add_action('wp_ajax_wpdatatables_add_new_manual_column', 'wdtAddNewManualColumn');

/**
 * Delete a column from a manually created table
 */
function wdtDeleteManualColumn() {
    if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['wdtNonce'], 'wdtFrontendEditTableNonce')) {
        exit();
    }

    $tableId = (int)$_POST['table_id'];
    $columnName = sanitize_text_field($_POST['column_name']);
    wpDataTableConstructor::deleteManualColumn($tableId, $columnName);

    exit();
}
add_action('wp_ajax_wpdatatables_delete_manual_column', 'wdtDeleteManualColumn');

/**
 * Return all columns for a provided table
 */
function wdtGetColumnsDataByTableId() {
    if (!current_user_can('manage_options') ||
        !(wp_verify_nonce($_POST['wdtNonce'], 'wdtChartWizardNonce') ||
            wp_verify_nonce($_POST['wdtNonce'], 'wdtEditNonce'))
    ) {
        exit();
    }

    $tableId = (int)$_POST['table_id'];

    echo json_encode(WDTConfigController::loadColumnsFromDB($tableId));
    exit();
}

add_action('wp_ajax_wpdatatables_get_columns_data_by_table_id', 'wdtGetColumnsDataByTableId');

/**
 * Returns the complete table for the range picker
 */
function wdtGetCompleteTableJSONById() {
    if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['wdtNonce'], 'wdtChartWizardNonce')) {
        exit();
    }

    $tableId = (int)$_POST['table_id'];
    $wpDataTable = WPDataTable::loadWpDataTable($tableId, null, true);

    echo json_encode($wpDataTable->getDataRowsFormatted());
    exit();
}

add_action('wp_ajax_wpdatatables_get_complete_table_json_by_id', 'wdtGetCompleteTableJSONById');


function wdtShowChartFromData() {
    if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['wdtNonce'], 'wdtChartWizardNonce')) {
        exit();
    }

    $chartData = $_POST['chart_data'];
    $wpDataChart = WPDataChart::factory($chartData, false);

    echo json_encode($wpDataChart->returnData(), JSON_NUMERIC_CHECK);
    exit();
}

add_action('wp_ajax_wpdatatable_show_chart_from_data', 'wdtShowChartFromData');


function wdtSaveChart() {
    if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['wdtNonce'], 'wdtChartWizardNonce')) {
        exit();
    }

    $chartData = $_POST['chart_data'];
    $wpDataChart = WPDataChart::factory($chartData, false);
    $wpDataChart->save();

    echo json_encode(array('id' => $wpDataChart->getId(), 'shortcode' => $wpDataChart->getShortCode()));
    exit();
}

add_action('wp_ajax_wpdatatable_save_chart_get_shortcode', 'wdtSaveChart');

/**
 * List all tables in JSON
 */
function wdtListAllTables() {
    if (!current_user_can('manage_options')) {
        exit();
    }

    echo json_encode(WPDataTable::getAllTables());
    exit();
}

add_action('wp_ajax_wpdatatable_list_all_tables', 'wdtListAllTables');

/**
 * List all charts in JSON
 */
function wdtListAllCharts() {
    if (!current_user_can('manage_options')) {
        exit();
    }

    echo json_encode(WPDataChart::getAllCharts());
    exit();
}

add_action('wp_ajax_wpdatatable_list_all_charts', 'wdtListAllCharts');

/**
 * Read Distinct Values from the table for column
 * Used to populate possible values list for Server Side tables
 */
function wdtReadDistinctValuesFromTable() {
    $tableId = (int)$_POST['tableId'];
    $columnId = (int)$_POST['columnId'];

    $wpDataTable = WPDataTable::loadWpDataTable($tableId);
    $tableData = WDTConfigController::loadTableFromDB($tableId);

    $columnData = WDTConfigController::loadSingleColumnFromDB($columnId);
    $column = $wpDataTable->getColumn($columnData['orig_header']);

    $distValues = WDTColumn::getColumnDistinctValues($column, $tableData, false);

    echo json_encode($distValues);
    exit();
}

add_action('wp_ajax_wpdatatable_get_column_distinct_values', 'wdtReadDistinctValuesFromTable');

/**
 * Get the preview for formula column
 */
function wdtPreviewFormulaResult() {
    $tableId = (int)$_POST['table_id'];
    $formula = sanitize_text_field($_POST['formula']);

    $wpDataTable = WPDataTable::loadWpDataTable($tableId);

    echo $wpDataTable->calcFormulaPreview($formula);
    exit();
}

add_action('wp_ajax_wpdatatables_preview_formula_result', 'wdtPreviewFormulaResult');