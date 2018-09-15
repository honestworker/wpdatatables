<?php

defined('ABSPATH') or die('Access denied.');

function wdtValidateTableData($id) {
    global $wpdb;
    $updated_time = get_user_meta(get_current_user_id(), 'wpdatatable_update', true);
    if ($updated_time) {
        if ($updated_time < date('Y-m')) {            
            $tableData = WDTConfigController::loadTableFromDB($id);
            $mySqlTableName = $tableData->mysql_table_name;
            $wpdb->query( "DELETE FROM " .  $tableData->mysql_table_name. ' WHERE userid = ' . get_current_user_id());
            update_user_meta(get_current_user_id(), 'wpdatatable_update', date('Y-m'));
        }
    }
}

/**
 * Handler which returns the AJAX response
 */
function wdtGetAjaxData() {
    global $wdtVar1, $wdtVar2, $wdtVar3;

    $id = (int)$_GET['table_id'];

    wdtValidateTableData($id);

    do_action('wpdatatables_get_ajax_data', $id);

    $tableData = WDTConfigController::loadTableFromDB($id);
    $columnData = WDTConfigController::loadColumnsFromDB($id);

    $avgColumns = array();
    $columnEditorTypes = array();
    $columnFilterTypes = array();
    $columnFormulas = array();
    $columnOrder = array();
    $columnTitles = array();
    $columnTypes = array();
    $decimalPlaces = array();
    $exactFiltering = array();
    $foreignKeyRule = array();
    $idColumn = '';
    $linkTargetAttribute = array();
    $linkButtonAttribute = array();
    $linkButtonLabel = array();
    $linkButtonClass = array();
    $maxColumns = array();
    $minColumns = array();
    $possibleValuesAddEmpty = array();
    $skipThousands = array();
    $sumColumns = array();
    $userIdColumnHeader = '';

    $wdtVar1 = isset($_GET['wdt_var1']) ? wdtSanitizeQuery($_GET['wdt_var1']) : $tableData->var1;
    $wdtVar2 = isset($_GET['wdt_var2']) ? wdtSanitizeQuery($_GET['wdt_var2']) : $tableData->var2;
    $wdtVar3 = isset($_GET['wdt_var3']) ? wdtSanitizeQuery($_GET['wdt_var3']) : $tableData->var3;

    $tableView = isset($_POST['table']) ? sanitize_text_field($_POST['table']) : '';

    foreach ($columnData as $column) {
        $advancedSettings = json_decode($column->advanced_settings);

        $columnOrder[(int)$column->pos] = $column->orig_header;
        if ($column->display_header) {
            $columnTitles[$column->orig_header] = $column->display_header;
        } else {
            $columnTitles[$column->orig_header] = $column->orig_header;
        }
        if ($column->column_type != 'autodetect') {
            $columnTypes[$column->orig_header] = $column->column_type;
            if ($column->column_type == 'formula') {
                $columnFormulas[$column->orig_header] = $column->calc_formula;
            }
            if ($column->column_type == 'int' && $column->skip_thousands_separator) {
                $skipThousands[] = $column->orig_header;
            }
        } else {
            $columnTypes[$column->orig_header] = 'string';
        }
        if ($column->id_column) {
            $idColumn = $column->orig_header;
        }
        $columnFilterTypes[$column->orig_header] = $column->filter_type;
        $columnEditorTypes[$column->orig_header] = $column->input_type;
        if ($tableData->edit_only_own_rows
            && ($tableData->userid_column_id == $column->id)
        ) {
            $userIdColumnHeader = $column->orig_header;
        }
        if ($column->sum_column) {
            $sumColumns[] = $column->orig_header;
        }

        if (isset($advancedSettings->calculateAvg) && $advancedSettings->calculateAvg == 1) {
            $avgColumns[] = $column->orig_header;
        }
        if (isset($advancedSettings->calculateMin) && $advancedSettings->calculateMin == 1) {
            $minColumns[] = $column->orig_header;
        }
        if (isset($advancedSettings->calculateMax) && $advancedSettings->calculateMax == 1) {
            $maxColumns[] = $column->orig_header;
        }

        $decimalPlaces[$column->orig_header] = isset($advancedSettings->decimalPlaces) ? $advancedSettings->decimalPlaces : null;
        $exactFiltering[$column->orig_header] = isset($advancedSettings->exactFiltering) ? $advancedSettings->exactFiltering : null;
        $linkTargetAttribute[$column->orig_header] = isset($advancedSettings->linkTargetAttribute) ? $advancedSettings->linkTargetAttribute : null;
        $linkButtonAttribute[$column->orig_header] = isset($advancedSettings->linkButtonAttribute) ? $advancedSettings->linkButtonAttribute : null;
        $linkButtonLabel[$column->orig_header] = isset($advancedSettings->linkButtonLabel) ? $advancedSettings->linkButtonLabel : null;
        $linkButtonClass[$column->orig_header] = isset($advancedSettings->linkButtonClass) ? $advancedSettings->linkButtonClass : null;
        $possibleValuesAddEmpty[$column->orig_header] = isset($advancedSettings->possibleValuesAddEmpty) ? $advancedSettings->possibleValuesAddEmpty : null;
        $foreignKeyRule[$column->orig_header] = isset($advancedSettings->foreignKeyRule) ? $advancedSettings->foreignKeyRule : null;

    }

    if ($tableView == 'excel') {
        $tbl = new WPExcelDataTable();
    } else {
        $tbl = new WPDataTable();
    }

    $tbl->setSumFooterColumns($sumColumns);
    $tbl->setAvgFooterColumns($avgColumns);
    $tbl->setMinFooterColumns($minColumns);
    $tbl->setMaxFooterColumns($maxColumns);

    if (isset($_POST['sumColumns'])) {
        foreach ($_POST['sumColumns'] as $sumColumnHeader) {
            if (!in_array($sumColumnHeader, $sumColumns)) {
                $sumColumns[] = $sumColumnHeader;
            }
        }
    }

    if (isset($_POST['avgColumns'])) {
        foreach ($_POST['avgColumns'] as $avgColumnHeader) {
            if (!in_array($avgColumnHeader, $avgColumns)) {
                $avgColumns[] = $avgColumnHeader;
            }
        }
    }

    if (isset($_POST['minColumns'])) {
        foreach ($_POST['minColumns'] as $minColumnHeader) {
            if (!in_array($minColumnHeader, $minColumns)) {
                $minColumns[] = $minColumnHeader;
            }
        }
    }

    if (isset($_POST['maxColumns'])) {
        foreach ($_POST['maxColumns'] as $maxColumnHeader) {
            if (!in_array($maxColumnHeader, $maxColumns)) {
                $maxColumns[] = $maxColumnHeader;
            }
        }
    }

    $tbl->setWpId($id);
    $tbl->setTableType($tableData->table_type);
    $tbl->setTableContent($tableData->content);
    $tbl->enableServerProcessing();
    if ($tableData->edit_only_own_rows) {
        $tbl->setOnlyOwnRows(true);
        $tbl->setUserIdColumn($userIdColumnHeader);
    }
    $tbl->setSumColumns($sumColumns);
    $tbl->setAvgColumns($avgColumns);
    $tbl->setMinColumns($minColumns);
    $tbl->setMaxColumns($maxColumns);
    $tbl->setAjaxReturn(true);

    $json = $tbl->queryBasedConstruct(
        $tableData->content,
        array(),
        array(
            'columnFormulas' => $columnFormulas,
            'columnOrder' => $columnOrder,
            'columnTitles' => $columnTitles,
            'data_types' => $columnTypes,
            'decimalPlaces' => $decimalPlaces,
            'exactFiltering' => $exactFiltering,
            'filterTypes' => $columnFilterTypes,
            'foreignKeyRule' => $foreignKeyRule,
            'idColumn' => $idColumn,
            'input_types' => $columnEditorTypes,
            'linkTargetAttribute' => $linkTargetAttribute,
            'linkButtonAttribute' => $linkButtonAttribute,
            'linkButtonLabel' => $linkButtonLabel,
            'linkButtonClass' => $linkButtonClass,
            'skip_thousands' => $skipThousands
        )
    );

    $json = apply_filters('wpdatatables_filter_server_side_data', $json, $id, $_GET);

    echo $json;
    exit();
}

add_action('wp_ajax_get_wdtable', 'wdtGetAjaxData');
add_action('wp_ajax_nopriv_get_wdtable', 'wdtGetAjaxData');

/**
 * Saves the table from frontend
 */
function wdtSaveTableFrontend() {
    global $wpdb, $wp_version;
    $formData = $_POST['formdata'];

    if (!wp_verify_nonce($_POST['wdtNonce'], 'wdtFrontendEditTableNonce')) {
        exit();
    }

    $returnResult = array('success' => '', 'error' => '', 'is_new' => false);

    $tableId = (int)$formData['table_id'];
    unset($formData['table_id'], $formData['nonce']);

    $formData = apply_filters('wpdatatables_filter_frontend_formdata', $formData, $tableId);

    $tableData = WDTConfigController::loadTableFromDB($tableId);
    $mySqlTableName = WDTTools::applyPlaceholders($tableData->mysql_table_name);

    $columnsData = WDTConfigController::loadColumnsFromDB($tableId);
    $idKey = '';
    $idVal = '';
    $dateFormat = get_option('wdtDateFormat');
    $timeFormat = get_option('wdtTimeFormat');

    // NULL value for different wordpress versions
    $nullValue = (!get_option('wdtUseSeparateCon')) ? NULL : "NULL";

    if ($wp_version < 4.4) {
        $nullValue = (!get_option('wdtUseSeparateCon')) ? WDTTools::wrapQuotes('NULL') : "NULL";
    }

    foreach ($columnsData as $column) {
        $advancedSettings = json_decode($column->advanced_settings);
        if ($column->id_column) {
            $idKey = $column->orig_header;
            $idVal = (int)$formData[$idKey];
            unset($formData[$idKey]);
        } else {
            // Defining the values for User ID columns and for "none" input types
            if ($column->id == $tableData->userid_column_id) {
                $formData[$column->orig_header] = get_current_user_id();
            } elseif ($column->input_type == 'none') {
                if ($idVal == '0') {
                    // For new values we take the default value (if defined)
                    if (!empty($advancedSettings->editingDefaultValue)) {
                        $formData[$column->orig_header] = $advancedSettings->editingDefaultValue;
                    } else {
                        unset($formData[$column->orig_header]);
                    }
                } else {
                    // For updating values we do not modify the cell at all
                    unset($formData[$column->orig_header]);
                }
            }

            if (isset($formData[$column->orig_header])) {

                // Sanitize data
                $formData[$column->orig_header] = strip_tags(
	                    $formData[$column->orig_header],
		                '<br/><br><b><strong><h1><h2><h3><a><i><em><ol><ul><li><img><blockquote><div><hr><p><span><select><option><sup><sub>'
                );

                // Formatting for DB based on column type
                switch ($column->column_type) {
                    case 'date':
                        if ($formData[$column->orig_header] != '') {

                            $formData[$column->orig_header] =
                                WDTTools::wrapQuotes(
                                    DateTime::createFromFormat(
                                        $dateFormat,
                                        $formData[$column->orig_header]
                                    )->format('Y-m-d')
                                );
                        } else {
                            $formData[$column->orig_header] = $nullValue;
                        }
                        break;
                    case 'datetime':
                        if ($formData[$column->orig_header] != '') {

                            $formData[$column->orig_header] =
                                WDTTools::wrapQuotes(
                                    DateTime::createFromFormat(
                                        $dateFormat . ' ' . $timeFormat,
                                        $formData[$column->orig_header]
                                    )->format('Y-m-d H:i:s')
                                );
                        } else {
                            $formData[$column->orig_header] = $nullValue;
                        }
                        break;
                    case 'float':
                        $number_format = get_option('wdtNumberFormat') ? get_option('wdtNumberFormat') : 1;
                        if ($number_format == 1) {
                            $formData[$column->orig_header] = str_replace('.', '', $formData[$column->orig_header]);
                            $formData[$column->orig_header] = str_replace(',', '.', $formData[$column->orig_header]);
                        } else {
                            $formData[$column->orig_header] = str_replace(',', '', $formData[$column->orig_header]);
                        }
                        $formData[$column->orig_header] = WDTTools::wrapQuotes((float)$formData[$column->orig_header]);
                        if ($formData[$column->orig_header] == '') {
                            $formData[$column->orig_header] = NULL;
                        }
                        break;
                    case 'int':
                        $formData[$column->orig_header] = WDTTools::wrapQuotes((int)$formData[$column->orig_header]);
                        if ($formData[$column->orig_header] == '') {
                            $formData[$column->orig_header] = NULL;
                        }
                        break;
                    case 'email':
                        $formData[$column->orig_header] = WDTTools::prepareStringCell($formData[$column->orig_header]);
                        break;
                    case 'string':
                        if ($column->input_type === 'textarea') {
                            $formData[$column->orig_header] = str_replace("\n", '<br/>', $formData[$column->orig_header]);
                        }
                        $formData[$column->orig_header] = WDTTools::prepareStringCell($formData[$column->orig_header]);
                        break;
                    case 'link':
                        $formData[$column->orig_header] = WDTTools::prepareStringCell($formData[$column->orig_header]);
                        break;
                    case 'image':
                        $formData[$column->orig_header] = WDTTools::prepareStringCell(esc_url($formData[$column->orig_header]));
                        break;
                    case 'time':
                        if ($formData[$column->orig_header] != '') {
                            $formData[$column->orig_header] =
                                WDTTools::wrapQuotes(
                                    DateTime::createFromFormat(
                                        $timeFormat,
                                        $formData[$column->orig_header]
                                    )->format('H:i:s')
                                );
                        } else {
                            $formData[$column->orig_header] = $nullValue;
                        }
                        break;
                    default:
                        $formData[$column->orig_header] = WDTTools::wrapQuotes(sanitize_text_field($formData[$column->orig_header]));
                        break;
                }

            }

        }
    }

    $formData = apply_filters('wpdatatables_filter_formdata_before_save', $formData, $tableId);

    // If the plugin is using WP DB
    if (!get_option('wdtUseSeparateCon')) {
        $formData = stripslashes_deep($formData);
        if ($idVal != '0') {
            $res = $wpdb->update($mySqlTableName,
                $formData,
                array(
                    $idKey => $idVal
                )
            );

            if (!$res) {
                if (!empty($wpdb->last_error)) {
                    $returnResult['error'] = __('There was an error trying to update the row! Error: ', 'wpdatatables') . $wpdb->last_error;
                } else {
                    $returnResult['success'] = $idVal;
                }
            } else {
                $returnResult['success'] = $idVal;
            }
        } else {
            $returnResult['is_new'] = true;
            $res = $wpdb->insert($mySqlTableName,
                $formData);
            if (!$res) {
                $returnResult['error'] = __('There was an error trying to insert a new row! Error: ', 'wpdatatables') . $wpdb->last_error;
            } else {
                $returnResult['success'] = $wpdb->insert_id;
                $idVal = $wpdb->insert_id;
            }
        }
    } else {
        // If plugin is using a separate DB
        $sql = new PDTSql(WDT_MYSQL_HOST, WDT_MYSQL_DB, WDT_MYSQL_USER, WDT_MYSQL_PASSWORD, WDT_MYSQL_PORT);
        if ($idVal != '0') {
            $query = 'UPDATE ' . $mySqlTableName . ' SET ';
            $i = 1;
            foreach ($formData AS $columnKey => $columnValue) {
                if ($columnValue == "''") {
                    $columnValue = $nullValue;
                }
                $query .= '`' . $columnKey . '` = ' . $columnValue . ' ';
                if ($i < count($formData)) {
                    $query .= ', ';
                }
                $i++;
            }
            $query .= ' WHERE `' . $idKey . '` = ' . $idVal;
            $query = apply_filters('wpdatatables_query_before_save_frontend', $query, $tableId);
            if ($sql->doQuery($query)) {
                $idVal = $sql->getLastInsertId();
                $returnResult['success'] = $idVal;
            } else {
                if ($sql->getLastError() !== '') {
                    $returnResult['error'] = __('There was an error trying to update the row! Error: ', 'wpdatatables') . $sql->getLastError();
                } else {
                    $returnResult['success'] = $idVal;
                }
            }
        } else {
            $returnResult['is_new'] = true;
            $query = 'INSERT INTO ' . $mySqlTableName . ' ';
            $columns = array();
            $values = array();

            foreach ($formData AS $columnKey => $columnValue) {
                if ($columnValue == "''") {
                    $columnValue = $nullValue;
                }
                $columns[] = '`' . $columnKey . '`';
                $values[] = $columnValue;
            }
            $query .= ' (' . implode(',', $columns) . ') VALUES ';
            $query .= ' (' . implode(',', $values) . ')';
            $query = apply_filters('wpdatatables_query_before_save_frontend', $query, $tableId);
            $sql->doQuery($query);
            if ($sql->getLastError() == '') {
                $returnResult['success'] = $sql->getLastInsertId();
            } else {
                $returnResult['error'] = __('There was an error trying to insert a new row! Error: ', 'wpdatatables') . $sql->getLastError();
            }
        }
    }

    do_action('wpdatatables_after_frontent_edit_row', $formData, $idVal, $tableId);

    echo json_encode($returnResult);

    exit();
}

add_action('wp_ajax_wdt_save_table_frontend', 'wdtSaveTableFrontend');
add_action('wp_ajax_nopriv_wdt_save_table_frontend', 'wdtSaveTableFrontend');

/**
 * Save changes on excel table cells
 */
function wdtSaveTableCellsFrontend() {
    global $wpdb;

    // Permissions check
    if (!wp_verify_nonce($_POST['wdtNonce'], 'wdtFrontendEditTableNonce')) {
        exit();
    }

    $returnResult = array('success' => array(), 'error' => '', 'has_new' => false);

    $tableId = (int)$_POST['table_id'];

    $cellsData = apply_filters('wpdatatables_excel_filter_frontend_formdata', $_POST['cells'], $tableId);

    $tableData = WDTConfigController::loadTableFromDB($tableId);
    $mySqlTableName = $tableData->mysql_table_name;

    // If current user cannot edit - do nothing
    if (!wdtCurrentUserCanEdit($tableData->editor_roles, $tableId)) {
        exit();
    }

    //getting distinct column names from sent data for change
    $columnNames = call_user_func_array('array_merge', $cellsData);
    $columnNames = array_keys($columnNames);

    //taking meta for changing columns
    $columnsMeta = WDTConfigController::loadColumnsFromDB($tableId);
    $idColumnKey = null;

    $formulaColumns = array();
    $allColumnsNames = array();
    $allColumnsTypes = array();

    //extracting key for id column
    foreach ($columnsMeta as $columnMeta) {
        $allColumnsNames[] = $columnMeta->orig_header;
        $allColumnsTypes[$columnMeta->orig_header] = $columnMeta->column_type;

        if ($columnMeta->id_column) {
            $idColumnKey = $columnMeta->orig_header;
        }

        if ($columnMeta->column_type == 'formula') {
            $formulaColumns[] = $columnMeta;
        }
    }

    $nonExistingCols = array_diff($columnNames, $allColumnsNames);

    //if some column not exist, error is returned
    if (!empty($nonExistingCols)) {
        $returnResult['error'] = __('Bad column names supplied: ', 'wpdatatables') . implode(', ', $nonExistingCols);
    } else if (!in_array($idColumnKey, $columnNames)) { //if id column not found among sent data, error is returned
        $returnResult['error'] = __('ID column not supplied', 'wpdatatables');
    } else {
        foreach ($cellsData as $cellData) {
            //if there is no id column sent in cell data, error is returned
            if (!key_exists($idColumnKey, $cellData)) {
                $returnResult['error'] = __('ID column not supplied for a cell', 'wpdatatables');
                break;
            } else {
                //this is id column's value of changing cell's row
                $cellIdValue = $cellData[$idColumnKey];

                unset($cellData[$idColumnKey]);
                reset($cellData);

	            foreach( array_keys($cellData) as $columnName ){
		            if( in_array( $allColumnsTypes[$columnName], array( 'string', 'email', 'link', 'image' ) ) ){
			            $cellData[$columnName] = strip_tags(
				            $cellData[$columnName],
				            '<br/><br><b><strong><h1><h2><h3><a><i><em><ol><ul><li><img><blockquote><div><hr><p><span><select><option><sup><sub>'
			            );
			            $cellData[$columnName] = WDTTools::prepareStringCell($cellData[$columnName]);
		            }
	            }

                if (empty($cellIdValue)) {
                    $qActionFlag = 'insert';
                } else {
	                $qActionFlag = 'update';
                }

                if (get_option('wdtUseSeparateCon')) {
                    if ($qActionFlag == 'insert') {
                        $insert_column_names = array_keys($cellData);
                        $qColumnNames = '`' . implode('`,`', $insert_column_names) . '`';
                        $qValues = array_values($cellData);
                        $qValues = implode(', ', $qValues);

                        $query = "INSERT INTO $mySqlTableName ($qColumnNames) VALUES ($qValues)";
                    } else {
                        $qSet = '';
                        foreach ($cellData as $cell_column_key => $cell_value) {
                            $qSet .= (!empty($qSet)) ? ', ' : '';
                            $qSet .= "`$cell_column_key` = $cell_value";
                        }
                        $qWhere = "`$idColumnKey` = $cellIdValue";
                        $query = "UPDATE $mySqlTableName SET $qSet WHERE $qWhere";
                    }

                    $query = apply_filters('wpdatatables_filter_excel_editor_query', $query, $tableId);

                    $sql = new PDTSql(WDT_MYSQL_HOST, WDT_MYSQL_DB, WDT_MYSQL_USER, WDT_MYSQL_PASSWORD);
                    $sql->doQuery($query);
                    $sqlLastError = $sql->getLastError();

                    if ($sqlLastError != '') {
                        $returnResult['error'] = __('There was an error trying to insert a new row! Error: ', 'wpdatatables') . $sqlLastError;
                        break;
                    }

                    $cellIdValue = ($qActionFlag == 'update') ? $cellIdValue : $sql->getLastInsertId();
                    $returnResult['success'][] = array(
                        "$idColumnKey" => $cellIdValue,
                        'action' => $qActionFlag
                    );

                    if ($qActionFlag == 'insert') {
                        $returnResult['has_new'] = true;
                    }

                    do_action('wpdatatables_excel_after_frontent_edit_row', $tableId, $cellIdValue, $cellData, $qActionFlag, $sqlLastError);
                } else {

                    if ($qActionFlag == 'insert') {
                        $res = $wpdb->insert($mySqlTableName, array_map('stripslashes_deep', $cellData));
                    } else {
                        $res = $wpdb->update(
                            $mySqlTableName,
                            array_map('stripslashes_deep', $cellData),
                            array(
                                $idColumnKey => $cellIdValue
                            )
                        );
                    }

                    if ($res === false) {
                        $returnResult['error'] = __('There was an error trying to update the row! Error: ', 'wpdatatables') . $wpdb->last_error;
                    } else {
                        $cellIdValue = ($qActionFlag == 'update') ? $cellIdValue : $wpdb->insert_id;
                        $returnResult['success'][] = array("$idColumnKey" => ($qActionFlag == 'update') ? $cellIdValue : $wpdb->insert_id,
                            'action' => $qActionFlag
                        );

                        if ($qActionFlag == 'insert') {
                            $returnResult['has_new'] = true;
                        }
                    }

                    do_action('wpdatatables_excel_after_frontent_edit_row', $tableId, $cellIdValue, $cellData, $qActionFlag, $wpdb->last_error);
                }
            }
        }
    }


    if (empty($returnResult['error'])) {
        $calculatedFormulaRows = array();
        $rowsData = $_POST['rows'];

        if (!empty($formulaColumns) && !empty($rowsData)) {
            foreach ($formulaColumns as $formula_col) {
                $formula = $formula_col->calc_formula;
                $colKey = $formula_col->orig_header;

                $headers = array();
                $headersInFormula = WDTTools::getColHeadersInFormula($formula, $allColumnsNames);
	            $headers = WDTTools::sanitizeHeaders($headersInFormula);

                foreach ($rowsData as $rowData) {
                    try {
                        $formulaValue =
                            WPDataTable::solveFormula(
                                $formula,
                                $headers,
                                $rowData
                            );
                    } catch (Exception $e) {
                        $formulaValue = 0;
                    }

                    $idColValue = $rowData[$idColumnKey];

                    if (!isset($calculatedFormulaRows["$idColValue"])) {
                        $calculatedFormulaRows["$idColValue"] = array(
                            $idColumnKey => $idColValue,
                            $colKey => $formulaValue
                        );
                    } else {
                        $calculatedFormulaRows["$idColValue"][$colKey] = $formulaValue;
                    }
                }
            }

        }
    }

    $returnResult['formula_cells'] = array_values($calculatedFormulaRows);

    do_action('wpdatatables_excel_after_frontent_edit_cells', $_POST['cells'], $returnResult, $tableId);

    echo json_encode($returnResult);

    exit();

}

add_action('wp_ajax_wdt_save_table_cells_frontend', 'wdtSaveTableCellsFrontend');
add_action('wp_ajax_nopriv_wdt_save_table_cells_frontend', 'wdtSaveTableCellsFrontend');

/**
 * Handle table row delete
 */
function wdtDeleteTableRow() {
    global $wpdb;

    if (!wp_verify_nonce($_POST['wdtNonce'], 'wdtFrontendEditTableNonce')) {
        exit();
    }

    $tableId = (int)$_POST['table_id'];
    $idKey = sanitize_text_field($_POST['id_key']);
    $idVal = (int)$_POST['id_val'];

    $tableData = WDTConfigController::loadTableFromDB($tableId);
    $mySqlTableName = $tableData->mysql_table_name;

    // If current user cannot edit - do nothing
    if (!wdtCurrentUserCanEdit($tableData->editor_roles, $tableId)) {
        exit();
    }

    do_action('wpdatatables_before_delete_row', $idVal, $tableId, $idKey);

    // If the plugin is using WP DB
    if (!get_option('wdtUseSeparateCon')) {
        $wpdb->delete($mySqlTableName, array($idKey => $idVal));
    } else {
        $sql = new PDTSql(WDT_MYSQL_HOST, WDT_MYSQL_DB, WDT_MYSQL_USER, WDT_MYSQL_PASSWORD, WDT_MYSQL_PORT);
        $query = "DELETE FROM " . $mySqlTableName . " WHERE `" . $idKey . "`='" . $idVal . "'";
        $sql->doQuery($query);
    }

    exit();
}

add_action('wp_ajax_wdt_delete_table_row', 'wdtDeleteTableRow');
add_action('wp_ajax_nopriv_wdt_delete_table_row', 'wdtDeleteTableRow');

/**
 * Handle table multiple rows delete
 */
function wdtDeleteTableRows() {
    global $wpdb;

    if (!wp_verify_nonce($_POST['wdtNonce'], 'wdtFrontendEditTableNonce')) {
        exit();
    }

    $returnResult = array('success' => array(), 'error' => '');

    $tableId = (int)$_POST['table_id'];

    $rows = apply_filters('wpdatatables_excel_filter_delete_rows', $_POST['rows'], $tableId);

    if (empty($rows)) {
        $returnResult['error'] = __('Nothing to delete.', 'wpdatatables');
        echo json_encode($returnResult);
        exit();
    } else if (!is_array($rows)) {
        $returnResult['error'] = __('Bad request format.', 'wpdatatables');
        echo json_encode($returnResult);
        exit();
    }

    //first key(should be only key) as a id column name
    reset($rows);
    $idColName = sanitize_text_field(key($rows));

    if (empty($rows[$idColName])) {
        $returnResult['error'] = __('Nothing to delete.', 'wpdatatables');
        echo json_encode($returnResult);
        exit();
    } else if (!is_array($rows[$idColName])) {
        $returnResult['error'] = __('Bad request format.', 'wpdatatables');
        echo json_encode($returnResult);
        exit();
    }

    $tableData = WDTConfigController::loadTableFromDB($tableId);
    $mySqlTableName = $tableData->mysql_table_name;

    // If current user cannot edit - do nothing
    if (!wdtCurrentUserCanEdit($tableData->editor_roles, $tableId)) {
        $returnResult['error'] = __('You don\'t have permission to change this table.', 'wpdatatables');
        echo json_encode($returnResult);
        exit();
    }

    $columnsMeta = WDTConfigController::loadColumnsFromDB($tableId, array($idColName));

    if (count($columnsMeta) == 0) {
        $returnResult['error'] = __('Supplied id column not exist.', 'wpdatatables');
        echo json_encode($returnResult);
        exit();
    } else {
        $columnMeta = $columnsMeta[0];

        if ($columnMeta->id_column) {
            $idColumnKey = sanitize_text_field($columnMeta->orig_header);

            $deleteRowIds = $rows[$idColumnKey];

            foreach ($deleteRowIds as $rowId) {
                $rowId = (int)$rowId;

                if (empty($rowId)) {
                    continue;
                }

                do_action('wpdatatables_excel_before_delete_row', $rowId, $tableId);

                // If the plugin is using WP DB
                if (!get_option('wdtUseSeparateCon')) {
                    $res = $wpdb->delete($mySqlTableName, array($idColumnKey => $rowId));

                    if ($res === false) {
                        $returnResult['error'] = __('There was an error trying to delete row! Error: ', 'wpdatatables') . $wpdb->last_error;
                    } else {
                        if (!isset($returnResult['success']['deleted'])) {
                            $returnResult['success']['deleted'] = array();
                        }

                        $returnResult['success']['deleted'][] = $rowId;
                    }

                    do_action('wpdatatables_excel_after_delete_row', $rowId, $tableId, $wpdb->last_error);
                } else {
                    $sql = new PDTSql(WDT_MYSQL_HOST, WDT_MYSQL_DB, WDT_MYSQL_USER, WDT_MYSQL_PASSWORD);
                    $query = "DELETE FROM " . $mySqlTableName . " WHERE `" . $idColumnKey . "`='" . $rowId . "'";
                    $sql->doQuery($query);
                    $sqlLastError = $sql->getLastError();
                    if ($sqlLastError != '') {
                        $returnResult['error'] = __('There was an error trying to delete row! Error: ', 'wpdatatables') . $sqlLastError;
                        break;
                    } else {
                        if (!isset($returnResult['success']['deleted'])) {
                            $returnResult['success']['deleted'] = array();
                        }

                        $returnResult['success']['deleted'][] = $rowId;
                    }

                    do_action('wpdatatables_excel_after_delete_row', $rowId, $tableId, $sqlLastError);
                }
            }

            do_action('wpdatatables_excel_after_delete_all_rows', $tableId, $returnResult);
        } else {
            $returnResult['error'] = 'Supplied column is not id column.';
            echo json_encode($returnResult);
            exit();
        }
    }

    echo json_encode($returnResult);
    exit();
}

add_action('wp_ajax_wdt_delete_table_rows', 'wdtDeleteTableRows');
add_action('wp_ajax_nopriv_wdt_delete_table_rows', 'wdtDeleteTableRows');