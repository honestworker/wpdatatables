<?php

defined('ABSPATH') or die('Access denied.');

/**
 * Class Constructor contains methods and properties for constructing the tables
 * in wpDataTables WordPress plugin
 *
 * @author cjbug@ya.ru
 *
 * @since June 2014
 */
class wpDataTableConstructor
{

    private $_name;
    private $_index;
    private $_db;
    private $_id = false;

    /*** For the WP DB type query ***/

    private $_tables_fields = array();
    private $_select_arr = array();
    private $_where_arr = array();
    private $_group_arr = array();
    private $_from_arr = array();
    private $_inner_join_arr = array();
    private $_left_join_arr = array();
    private $_table_aliases = array();
    private $_column_aliases = array();
    private $_column_headers = array();
    private $_has_groups = false;

    /** Query text **/
    private $_query = '';

    /**
     * The constructor
     */
    public function __construct()
    {
        if (WDT_ENABLE_MYSQL && get_option('wdtUseSeparateCon')) {
            $this->_db = new PDTSql(WDT_MYSQL_HOST, WDT_MYSQL_DB, WDT_MYSQL_USER, WDT_MYSQL_PASSWORD, WDT_MYSQL_PORT);
        }
    }

    /**
     * Sets ID;
     *
     * @param int $id
     */
    public function setTableId($id)
    {
        $this->_id = $id;
    }

    /**
     * Gets table ID
     */
    public function getTableId()
    {
        return $this->_id;
    }

    /**
     * Generate the new unique table name (For MySQL)
     */
    public function generateTableName()
    {

        $this->_index = (int)get_option('wdtGeneratedTablesCount', 0);
        $this->_index += 1;

        $this->_name = 'wpdatatable_' . $this->_index;

        if (!get_option('wdtUseSeparateCon')) {
            global $wpdb;
            $this->_name = $wpdb->prefix . $this->_name;
        }

        $this->_name = apply_filters('wpdatatables_before_generate_constructed_table_name', $this->_name);

        return $this->_name;
    }

    public function generateTableName_for_update()
    {
        global $wpdb;
        $results =  $wpdb->get_results("SELECT mysql_table_name FROM ".$wpdb->prefix."wpdatatables WHERE id=".$this->_id);
        foreach($results as $result)
        {
            $this->_name = $result->mysql_table_name;
        }
        
        return  $this->_name;      
    }

    public function getInsertedCodeIdInTable_Manrox()
    {
        global $wpdb;
        $return  = [];
        if (!empty($this->_name))
        {
            $results = $wpdb->get_results("SELECT sku FROM " . $this->_name . ' WHERE userid = ' . get_current_user_id());
            foreach($results as $result)
            {
                $return[] =  $result->sku;
            }
        }
        return $return;
    }

    /**
     * Helper function to prepare the filter, editor, column types, and create statement
     *
     * @param $column_header
     * @param $column
     * @return array
     */
    private static function defineColumnProperties($column_header, $column)
    {

        switch ($column['type']) {
            case 'input':
                $columnProperties = array(
                    'editor_type' => 'text',
                    'column_type' => 'string',
                    'filter_type' => 'text',
                    'create_block' => "`{$column_header}` VARCHAR(255) "
                );
                break;
            case 'int':
                $columnProperties = array(
                    'editor_type' => 'text',
                    'column_type' => 'int',
                    'filter_type' => 'number',
                    'create_block' => "`{$column_header}` INT(11) "
                );
                break;
            case 'float':
                $columnProperties = array(
                    'editor_type' => 'text',
                    'column_type' => 'float',
                    'filter_type' => 'number',
                    'create_block' => "`{$column_header}` DECIMAL(16,4) "
                );
                break;
            case 'memo':
                $columnProperties = array(
                    'editor_type' => 'textarea',
                    'column_type' => 'string',
                    'filter_type' => 'text',
                    'create_block' => "`{$column_header}` TEXT "
                );
                break;
            case 'select':
                $columnProperties = array(
                    'editor_type' => 'selectbox',
                    'column_type' => 'string',
                    'filter_type' => 'select',
                    'create_block' => "`{$column_header}` VARCHAR(2000) "
                );
                break;
            case 'multiselect':
                $columnProperties = array(
                    'editor_type' => 'multi-selectbox',
                    'column_type' => 'string',
                    'filter_type' => 'select',
                    'create_block' => "`{$column_header}` VARCHAR(2000) "
                );
                break;
            case 'date':
                $columnProperties = array(
                    'editor_type' => 'date',
                    'column_type' => 'date',
                    'filter_type' => 'date-range',
                    'create_block' => "`{$column_header}` DATE "
                );
                break;
            case 'datetime':
                $columnProperties = array(
                    'editor_type' => 'datetime',
                    'column_type' => 'datetime',
                    'filter_type' => 'datetime-range',
                    'create_block' => "`{$column_header}` DATETIME "
                );
                break;
            case 'time':
                $columnProperties = array(
                    'editor_type' => 'time',
                    'column_type' => 'time',
                    'filter_type' => 'time-range',
                    'create_block' => "`{$column_header}` TIME "
                );
                break;
            case 'email':
                $columnProperties = array(
                    'editor_type' => 'email',
                    'column_type' => 'email',
                    'filter_type' => 'text',
                    'create_block' => "`{$column_header}` VARCHAR(2000) "
                );
                break;
            case 'link':
                $columnProperties = array(
                    'editor_type' => 'link',
                    'column_type' => 'link',
                    'filter_type' => 'text',
                    'create_block' => "`{$column_header}` VARCHAR(2000) "
                );
                break;
            case 'file':
                $columnProperties = array(
                    'editor_type' => 'attachment',
                    'column_type' => 'link',
                    'filter_type' => 'none',
                    'create_block' => "`{$column_header}` VARCHAR(2000) "
                );
                break;
            case 'image':
                $columnProperties = array(
                    'editor_type' => 'attachment',
                    'column_type' => 'image',
                    'filter_type' => 'none',
                    'create_block' => "`{$column_header}` VARCHAR(2000) "
                );
                break;
            default:
                $columnProperties = array(
                    'editor_type' => 'text',
                    'column_type' => 'string',
                    'filter_type' => 'text',
                    'create_block' => "`{$column_header}` VARCHAR(2000) "
                );
                break;
        }

        $columnProperties['advanced_settings'] = array(
            "sorting" => 1,
            "exactFiltering" => 0,
            "filterLabel" => "",
            'editingDefaultValue' => sanitize_text_field($column['default_value'])
        );

        if (!empty($column['possible_values'])) {
            $columnProperties['advanced_settings']['possibleValuesType'] = 'list';
        }

        return $columnProperties;

    }

    /**
     * Generates and saves a new MySQL table and a new wpDataTable
     */
    public function generateManualTable($tableData)
    {
        global $wpdb;

        $this->_table_data = apply_filters('wdt_before_generate_manual_table', $tableData);

        // Generate the MySQL table name
        $this->generateTableName();

        // Create the wpDataTable metadata
        $wpdb->insert(
            $wpdb->prefix . "wpdatatables",
            array(
                'title' => sanitize_text_field($this->_table_data['name']),
                'table_type' => 'manual',
                'content' => 'SELECT * FROM ' . $this->_name,
                'server_side' => 1,
                'mysql_table_name' => $this->_name,
                'tabletools_config' => serialize(array(
                    'print' => 1,
                    'copy' => 1,
                    'excel' => 1,
                    'csv' => 1,
                    'pdf' => 0
                ))
            )
        );

        // Store the new table metadata ID
        $wpdatatable_id = $wpdb->insert_id;

        // Prepare the create statement for the table itself
        $create_statement = "CREATE TABLE " . $this->_name . " (
 	 							wdt_ID INT( 11 ) NOT NULL AUTO_INCREMENT,";

        $column_headers = array();

        $column_index = 0;

        // Add metadata for ID column
        $wpdb->insert(
            $wpdb->prefix . "wpdatatables_columns",
            array(
                'table_id' => $wpdatatable_id,
                'orig_header' => 'wdt_ID',
                'display_header' => 'wdt_ID',
                'filter_type' => 'none',
                'column_type' => 'int',
                'visible' => 0,
                'pos' => $column_index,
                'id_column' => 1
            )
        );
        $column_index++;

        $additional_statement = '';
        $additional_statement = apply_filters('wpdt_add_default_columns', $additional_statement, $wpdatatable_id, $column_index);
        if (!empty($additional_statement)) {
            $create_statement .= $additional_statement['statment'];
            $column_index = $additional_statement['column_index'];
        }

        foreach ($this->_table_data['columns'] as $column) {

            $column_header = WDTTools::generateMySQLColumnName(sanitize_text_field($column['name']), $column_headers);

            $column_headers[] = $column_header;
            if (!isset($column['orig_header'])) {
                $column['orig_header'] = sanitize_text_field($column['name']);
            }
            $this->_column_headers[$column['orig_header']] = $column_header;

            $columnProperties = self::defineColumnProperties($column_header, $column);

            // Create the column metadata in WPDB
            $wpdb->insert(
                $wpdb->prefix . "wpdatatables_columns",
                array(
                    'table_id' => $wpdatatable_id,
                    'orig_header' => $column_header,
                    'display_header' => sanitize_text_field($column['name']),
                    'filter_type' => sanitize_text_field($columnProperties['filter_type']),
                    'column_type' => sanitize_text_field($columnProperties['column_type']),
                    'pos' => $column_index,
                    'possible_values' => str_replace(',,;,|', '|', sanitize_text_field($column['possible_values'])),
                    'advanced_settings' => json_encode($columnProperties['advanced_settings']),
                    'input_type' => sanitize_text_field($columnProperties['editor_type'])
                )
            );

            $create_statement .= $columnProperties['create_block'] . ', ';

            $column_index++;
        }

        // Add the ID unique key
        $create_statement .= " UNIQUE KEY wdt_ID (wdt_ID)) CHARACTER SET=utf8 COLLATE utf8_general_ci";

        // Call the create statement on WPDB or on external DB if it is defined
        if (get_option('wdtUseSeparateCon')) {
            // External DB
            $this->_db->doQuery($create_statement, array());
            if ($this->_db->getLastError() != '') {
                WPDataTable::deleteTable($wpdatatable_id);
                throw new Exception(__('There was an error when trying to create the table on MySQL side', 'wpdatatables') . ': ' . $this->_db->getLastError());
            }
        } else {
            $wpdb->query($create_statement);
            $db_error = $wpdb->last_error;
            if ($db_error != '') {
                WPDataTable::deleteTable($wpdatatable_id);
                throw new Exception(__('There was an error when trying to create the table on MySQL side', 'wpdatatables') . ': ' . $db_error);
            }
        }

        // Update the index in WPDB
        update_option('wdtGeneratedTablesCount', $this->_index);

        return $wpdatatable_id;

    }
     public function generateManualTableFrontend($tableData)
    {
        global $wpdb;

        $this->_table_data = $tableData;
     
        $column_headers = array();
        $column_index = 0;      
        $column_index++;

        foreach ($this->_table_data['columns'] as $column) {

            $column_header = WDTTools::generateMySQLColumnName(sanitize_text_field($column['name']), $column_headers);

            $column_headers[] = $column_header;
            if (!isset($column['orig_header'])) {
                $column['orig_header'] = sanitize_text_field($column['name']);
            }
            $this->_column_headers[$column['orig_header']] = $column_header;


            $column_index++;
        }
    }

    /**
     * Generates and returns a MySQL query based on user's visual input
     *
     * @param $tableData
     */
    public function generateMySQLBasedQuery($tableData)
    {
        global $wpdb;

        $this->_table_data = apply_filters('wdt_before_generate_mysql_based_query', $tableData);

        if (!isset($this->_table_data['whereConditions'])) {
            $this->_table_data['whereConditions'] = array();
        }

        if (isset($this->_table_data['groupingRules'])) {
            $this->_has_groups = true;
        }

        if (!isset($tableData['mySqlColumns'])) {
            $tableData['mySqlColumns'] = array();
        }

        // Initializing structure for the SELECT part of query
        $this->_prepareMySQLSelectBlock();

        // Initializing structure for the WHERE part of query
        $this->_prepareMySQLWhereBlock();

        // Prepare the GROUP BY block
        $this->_prepareMySQLGroupByBlock();

        // Prepare the join rules
        $this->_prepareMySQLJoinedQueryStructure();

        // Prepare the query itself
        $this->_query = $this->_buildMySQLQuery();
    }

    /**
     * Generates and returns a WP based MySQL query based on user's visual input
     *
     * @param $tableData
     */
    public function generateWPBasedQuery($tableData)
    {
        global $wpdb;
        $this->_table_data = apply_filters('wdt_before_generate_wp_based_query', $tableData);

        if (!isset($this->_table_data['whereConditions'])) {
            $this->_table_data['whereConditions'] = array();
        }

        if (isset($this->_table_data['groupingRules'])) {
            $this->_has_groups = true;
        }

        $this->_tables_fields = self::generateTablesFieldsStructureWPBased($this->_table_data['postColumns']);

        // Initializing structure for the SELECT part of query
        $this->_prepareWPSelectBlock();

        // We need to go through the rest of where conditions and add 'inner join' parts for them if needed
        $this->_prepareWPWhereBlock();

        // We need to add 'GROUP BY' blocks
        $this->_prepareWPGroupByBlock();

        if (($this->_table_data['handlePostTypes'] == 'join')
            || count($this->_table_data['postTypes']) == 1
        ) {
            // We do JOINs
            $this->_prepareWPJoinedQueryStructure();

            $this->_query = $this->_buildWPJoinedQuery();

        } else {
            // We do UNIONs
            $this->_query = $this->_buildWPJoinedQuery();
        }
    }

    /**
     * Helper function to generate the fields structure from MySQL tables
     */
    private function _prepareMySQLSelectBlock()
    {
        foreach ($this->_table_data['mySqlColumns'] as $mysql_column) {

            $mysql_column_arr = explode('.', $mysql_column);
            if (!isset($this->_select_arr[$mysql_column_arr[0]])) {
                $this->_select_arr[$mysql_column_arr[0]] = array();
            }
            $this->_select_arr[$mysql_column_arr[0]][] = $mysql_column;

            if (!in_array($mysql_column_arr[0], $this->_from_arr)) {
                $this->_from_arr[] = $mysql_column_arr[0];
            }

        }
    }

    /**
     * Helper function to generate the fields structire from WP Posts data
     */
    public static function generateTablesFieldsStructureWPBased($columns)
    {
        $tables_fields = array();

        // Parse the columns list, generate table aliases and the columns
        foreach ($columns as $post_column) {
            $post_column_arr = explode('.', $post_column);

            if (count($post_column_arr) == 2) {
                // This is a column of a post table
                if (!isset($tables_fields[$post_column_arr[0]])) {
                    $tables_fields[$post_column_arr[0]] = array(
                        'table' => 'posts',
                        'post_type' => $post_column_arr[0],
                        'sql_alias' => self::prepareSqlAlias('posts_' . $post_column_arr[0]),
                        'columns' => array()
                    );
                }
                $tables_fields[$post_column_arr[0]]['columns'][] = array(
                    'field' => $post_column_arr[1],
                    'col_alias' => self::prepareSqlAlias($post_column),
                    'col_internal_name' => $post_column
                );
            } else {
                // This is a taxonomy or a meta value
                if ($post_column_arr[1] == 'meta') {
                    // This is a meta value
                    $tables_fields[$post_column_arr[2]] = array(
                        'table' => 'postmeta',
                        'sql_alias' => self::prepareSqlAlias($post_column . '_tbl'),
                        'col_alias' => self::prepareSqlAlias($post_column),
                        'col_internal_name' => $post_column,
                        'post_type' => $post_column_arr[0]
                    );
                } elseif ($post_column_arr[1] == 'taxonomy') {
                    // This is a taxonomy value
                    $tables_fields[$post_column_arr[2]] = array(
                        'table' => 'taxonomy',
                        'sql_alias' => self::prepareSqlAlias($post_column . '_tbl'),
                        'col_alias' => self::prepareSqlAlias($post_column),
                        'col_internal_name' => $post_column,
                        'post_type' => $post_column_arr[0]
                    );
                }
            }
        }

        return $tables_fields;
    }

    public static function prepareSqlAlias($alias)
    {
        $sqlAlias = str_replace('.', '_', $alias);
        $sqlAlias = str_replace('-', '_', $sqlAlias);

        return $sqlAlias;
    }

    public static function buildWhereCondition($leftOperand, $operator, $rightOperand, $isValue = true)
    {
        $rightOperand = stripslashes_deep($rightOperand);
        $wrap = $isValue ? "'" : "";
        switch ($operator) {
            case 'eq':
                return "{$leftOperand} = {$wrap}{$rightOperand}{$wrap}";
            case 'neq':
                return "{$leftOperand} != {$wrap}{$rightOperand}{$wrap}";
            case 'gt':
                return "{$leftOperand} > {$wrap}{$rightOperand}{$wrap}";
            case 'gtoreq':
                return "{$leftOperand} >= {$wrap}{$rightOperand}{$wrap}";
            case 'lt':
                return "{$leftOperand} < {$wrap}{$rightOperand}{$wrap}";
            case 'ltoreq':
                return "{$leftOperand} <= {$wrap}{$rightOperand}{$wrap}";
            case 'in':
                return "{$leftOperand} IN ({$rightOperand})";
            case 'like':
                return "{$leftOperand} LIKE {$wrap}{$rightOperand}{$wrap}";
            case 'plikep':
                return "{$leftOperand} LIKE {$wrap}%{$rightOperand}%{$wrap}";
        }
    }

    /**
     * Prepares the SELECT part for the WP-based tables
     */
    private function _prepareWPSelectBlock()
    {
        global $wpdb;

        if (empty($this->_tables_fields)) {
            return;
        }

        $thumbSizeString = self::getThumbSizeString();

        foreach ($this->_tables_fields as $valueName => &$fields) {
            // Fill in the SQL alias of the table
            $this->_table_aliases[] = $fields['sql_alias'];

            if ($fields['table'] == 'posts') {

                foreach ($fields['columns'] as $table_column) {
                    if (!isset($this->_select_arr[$fields['sql_alias']])) {
                        $this->_select_arr[$fields['sql_alias']] = array();
                    }

                    if ($table_column['field'] == 'title_with_link_to_post') {
                        // Generating an "<a href="..."" link to the post
                        $this->_select_arr[$fields['sql_alias']][] = 'CONCAT(\'<a href="\',' . $fields['sql_alias'] . '.guid,\'">\',' . $fields['sql_alias'] . '.post_title,\'</a>\') AS ' . $table_column['col_alias'];
                    } elseif ($table_column['field'] == 'thumbnail_with_link_to_post') {
                        // Generating an "<a href="..."" link to the post and a thumbnail URL depending on WP settings
                        $this->_select_arr[$fields['sql_alias'] . '_img'][] = 'CONCAT(
                                    \'<a href="\',
                                    ' . $fields['sql_alias'] . '.guid,
                                    \'"><img src="\', 
                                    REPLACE( 
                                        ' . $fields['sql_alias'] . '_img' . '.guid,
                                        CONCAT(
                                            \'.\',
                                            SUBSTRING_INDEX(  
                                                ' . $fields['sql_alias'] . '_img' . '.guid,
                                                \'.\',
                                                -1
                                            )
                                        ),
                                        CONCAT(
                                            \'' . $thumbSizeString . '\' ,
                                            SUBSTRING_INDEX(  
                                                ' . $fields['sql_alias'] . '_img' . '.guid,
                                                \'.\',
                                                -1
                                            )
                                        )
                                        ), 
                                    \'" /></a>\'
                                  ) AS ' . $table_column['col_alias'];
                        $this->_left_join_arr[$fields['sql_alias'] . '_img'] = '(SELECT ' . $fields['sql_alias'] . '_imgposts.guid AS guid, ' . $fields['sql_alias'] . '_imgpostmeta.post_id AS post_id
                                        FROM ' . $wpdb->postmeta . ' AS ' . $fields['sql_alias'] . '_imgpostmeta 
                                        INNER JOIN ' . $wpdb->posts . ' AS ' . $fields['sql_alias'] . '_imgposts 
                                            ON ' . $fields['sql_alias'] . '_imgpostmeta.meta_value = ' . $fields['sql_alias'] . '_imgposts.ID
                                        WHERE ' . $fields['sql_alias'] . "_imgpostmeta.meta_key = '_thumbnail_id' " .
                            ') AS ' . $fields['sql_alias'] . '_img';
                        $this->_where_arr[$fields['sql_alias'] . '_img'][] = $fields['sql_alias'] . '_img.post_id = ' . $fields['sql_alias'] . '.ID';
                    } elseif ($table_column['field'] == 'post_author') {
                        // Get the author nicename instead of ID
                        $this->_select_arr[$fields['sql_alias'] . '_author'][] = $fields['sql_alias'] . '_author.display_name AS ' . $table_column['col_alias'];
                        $this->_inner_join_arr[$fields['sql_alias'] . '_author'] = $wpdb->users . ' AS ' . $fields['sql_alias'] . '_author';
                        $this->_where_arr[$fields['sql_alias'] . '_author'][] = $fields['sql_alias'] . '_author.ID = ' . $fields['sql_alias'] . '.post_author';
                    } elseif ($table_column['field'] == 'post_content_limited_100_chars') {
                        // Get post content limited to 100 chars
                        $this->_select_arr[$fields['sql_alias']][] = 'LEFT( ' . $fields['sql_alias'] . '.post_content, 100) AS ' . $table_column['col_alias'];
                    } else {
                        $this->_select_arr[$fields['sql_alias']][] = $fields['sql_alias'] . '.' . $table_column['field'] . ' AS ' . $table_column['col_alias'];
                    }

                    $this->_column_aliases[$table_column['col_internal_name']] = $table_column['col_alias'];

                    // Look up for this column in additional 'where' conditions
                    foreach ($this->_table_data['whereConditions'] as $where_key => &$where_condition) {
                        $where_column_arr = explode('.', $where_condition['column']);
                        if ((count($where_column_arr) == 2)
                            && ($valueName == $where_column_arr[0])
                        ) {
                            if (!isset($this->_where_arr[$fields['sql_alias']])) {
                                $this->_where_arr[$fields['sql_alias']] = array();
                            }
                            $this->_where_arr[$fields['sql_alias']][] = self::buildWhereCondition(
                                'posts_' . $where_condition['column'],
                                $where_condition['operator'],
                                $where_condition['value']
                            );
                            unset($this->_table_data['whereConditions'][$where_key]);
                        }
                    }
                }
                $this->_from_arr[$fields['sql_alias']] = $wpdb->posts . ' AS ' . $fields['sql_alias'];
                if ($fields['post_type'] != 'all') {
                    $this->_where_arr[$fields['sql_alias']][] = $fields['sql_alias'] . ".post_type = '" . $fields['post_type'] . "'";
                }
            } elseif ($fields['table'] == 'postmeta') {
                if (!isset($this->_select_arr[$fields['sql_alias']])) {
                    $this->_select_arr[$fields['sql_alias']] = array();
                }
                if (!$this->_has_groups) {
                    $this->_select_arr[$fields['sql_alias']][] = $fields['sql_alias'] . '.meta_value AS ' . $fields['col_alias'];
                } else {
                    $this->_select_arr[$fields['sql_alias']][] = 'GROUP_CONCAT(distinct ' . $fields['sql_alias'] . '.meta_value) AS ' . $fields['col_alias'];
                }
                $this->_inner_join_arr[$fields['sql_alias']] = self::preparePostMetaSubquery($fields['sql_alias'], $fields['post_type']);

                if (!isset($this->_where_arr[$fields['sql_alias']])) {
                    $this->_where_arr[$fields['sql_alias']] = array();
                }
                $this->_where_arr[$fields['sql_alias']][] = $fields['sql_alias'] . ".meta_key = '" . $valueName . "' AND " . $fields['sql_alias'] . ".id = posts_" . $fields['post_type'] . ".ID ";

                $this->_column_aliases[$fields['col_internal_name']] = $fields['col_alias'];

                foreach ($this->_table_data['whereConditions'] as $where_key => &$where_condition) {
                    $where_column_arr = explode('.', $where_condition['column']);
                    if ((count($where_column_arr) == 3)
                        && ($where_column_arr[1] == 'meta')
                        && ($valueName == $where_column_arr[2])
                    ) {
                        if (!isset($this->_where_arr[$fields['sql_alias']])) {
                            $this->_where_arr[$fields['sql_alias']] = array();
                        }
                        $this->_where_arr[$fields['sql_alias']][] = self::buildWhereCondition(
                            $fields['col_alias'],
                            $where_condition['operator'],
                            $where_condition['value']
                        );
                        unset($this->_table_data['whereConditions'][$where_key]);
                    }
                }

            } elseif ($fields['table'] == 'taxonomy') {
                if (!isset($this->_select_arr[$fields['sql_alias']])) {
                    $this->_select_arr[$fields['sql_alias']] = array();
                }
                if (!$this->_has_groups) {
                    $this->_select_arr[$fields['sql_alias']][] = $fields['sql_alias'] . '.name AS ' . $fields['col_alias'];
                } else {
                    $this->_select_arr[$fields['sql_alias']][] = 'GROUP_CONCAT(distinct ' . $fields['sql_alias'] . '.name) AS ' . $fields['col_alias'];
                }
                $this->_inner_join_arr[$fields['sql_alias']] = self::preparePostTaxonomySubquery($fields['sql_alias'], $valueName);
                $this->_where_arr[$fields['sql_alias']][] = $fields['sql_alias'] . ".ID = posts_" . $fields['post_type'] . ".id ";

                $this->_column_aliases[$fields['col_internal_name']] = $fields['col_alias'];

                foreach ($this->_table_data['whereConditions'] as $where_key => &$where_condition) {
                    $where_column_arr = explode('.', $where_condition['column']);
                    if ((count($where_column_arr) == 3)
                        && ($where_column_arr[1] == 'taxonomy')
                        && ($valueName == $where_column_arr[2])
                    ) {
                        if (!isset($this->_where_arr[$fields['sql_alias']])) {
                            $this->_where_arr[$fields['sql_alias']] = array();
                        }
                        $this->_where_arr[$fields['sql_alias']][] = self::buildWhereCondition(
                            $fields['col_alias'],
                            $where_condition['operator'],
                            $where_condition['value']
                        );
                        unset($this->_table_data['whereConditions'][$where_key]);
                    }
                }
            }
        }
    }

    private function _prepareMySQLWhereBlock()
    {
        if (empty($this->_table_data['whereConditions'])) {
            return;
        }

        foreach ($this->_table_data['whereConditions'] as $where_condition) {

            $where_column_arr = explode('.', $where_condition['column']);

            if (!in_array($where_column_arr[0], $this->_from_arr)) {
                $this->_from_arr[] = $where_column_arr[0];
            }

            $this->_where_arr[$where_column_arr[0]][] = self::buildWhereCondition(
                $where_condition['column'],
                $where_condition['operator'],
                $where_condition['value']
            );
        }
    }

    /**
     * Prepares the WHERE block for WP-based query
     */
    private function _prepareWPWhereBlock()
    {
        global $wpdb;

        if (empty($this->_table_data['whereConditions'])) {
            return;
        }

        foreach ($this->_table_data['whereConditions'] as $where_condition) {

            $where_column_arr = explode('.', $where_condition['column']);
            if (count($where_column_arr) == 2) {
                $tbl_alias = 'posts_' . $where_column_arr[0];
                $tbl_alias = str_replace('-', '_', $tbl_alias);
                $this->_from_arr[$tbl_alias] = $wpdb->posts . '_' . $where_column_arr[0] . ' AS ' . $tbl_alias;
                $this->_where_arr[$tbl_alias] = array();
                $this->_where_arr[$tbl_alias][] = self::buildWhereCondition(
                    $where_column_arr[1],
                    $where_condition['operator'],
                    $where_condition['value']
                );
            } else {
                if (count($where_column_arr) == 3) {
                    $tbl_alias = str_replace('.', '_', $where_condition['column']) . '_tbl';
                    $tbl_alias = str_replace('-', '_', $tbl_alias);
                    if ($where_column_arr[1] == 'meta') {
                        $this->_inner_join_arr[$tbl_alias] = self::preparePostMetaSubquery(
                            $tbl_alias,
                            $where_column_arr[0],
                            $where_column_arr[2]
                        );
                        $this->_where_arr[$tbl_alias] = array();
                        $this->_where_arr[$tbl_alias][] = self::buildWhereCondition(
                            $tbl_alias . '.meta_value',
                            $where_condition['operator'],
                            $where_condition['value']
                        );
                    } elseif ($where_column_arr[1] == 'taxonomy') {

                        $this->_inner_join_arr[$tbl_alias] = self::preparePostTaxonomySubquery($tbl_alias, $where_column_arr[2]);

                        $this->_where_arr[$tbl_alias] = array();
                        $this->_where_arr[$tbl_alias][] = self::buildWhereCondition(
                            $tbl_alias . '.name',
                            $where_condition['operator'],
                            $where_condition['value']
                        );
                    }
                    $this->_where_arr[$tbl_alias][] = self::buildWhereCondition(
                        $tbl_alias . '.id',
                        'eq',
                        'posts_' . $where_column_arr[0] . '.ID'
                    );
                }
            }
        }
    }

    public static function preparePostMetaSubquery($alias, $post_type, $meta_key = '')
    {
        global $wpdb;

        if (empty($alias) || empty($post_type)) {
            return '';
        }

        $post_meta_subquery = "(SELECT {$alias}_posts.ID as id, meta_value, meta_key ";
        $post_meta_subquery .= " FROM {$wpdb->postmeta} AS {$alias}_postmeta ";
        $post_meta_subquery .= " INNER JOIN {$wpdb->posts} AS {$alias}_posts ";
        $post_meta_subquery .= "  ON {$alias}_postmeta.post_id = {$alias}_posts.ID ";
        if (!empty($meta_key)) {
            $post_meta_subquery .= "  AND {$alias}_postmeta.meta_key = '{$meta_key}'";
        }
        $post_meta_subquery .= "  AND {$alias}_posts.post_type = '{$post_type}') AS {$alias}";

        return $post_meta_subquery;
    }

    /**
     * Prepare the query text for the WP based wpDataTable
     */
    private function _buildWPJoinedQuery()
    {
        // Build the final output
        $query = "SELECT ";
        $i = 0;
        foreach ($this->_select_arr as $table_alias => $select_block) {
            $query .= implode(",\n       ", $select_block);
            $i++;
            if ($i < count($this->_select_arr)) {
                $query .= ",\n       ";
            }
        }
        $query .= "\nFROM ";
        $query .= implode(', ', $this->_from_arr) . "\n";
        if (!empty($this->_inner_join_arr)) {
            $i = 0;
            foreach ($this->_inner_join_arr as $table_alias => $inner_join_block) {
                $query .= "  INNER JOIN " . $inner_join_block . "\n";
                if (!empty($this->_where_arr[$table_alias])) {
                    $query .= "     ON " . implode("\n     AND ", $this->_where_arr[$table_alias]) . "\n";
                    unset($this->_where_arr[$table_alias]);
                }
            }
        }
        if (!empty($this->_left_join_arr)) {

            foreach ($this->_left_join_arr as $table_alias => $left_join_block) {
                $query .= "  LEFT JOIN " . $left_join_block . "\n";
                if (!empty($this->_where_arr[$table_alias])) {
                    $query .= "     ON " . implode("\n     AND ", $this->_where_arr[$table_alias]) . "\n";
                    unset($this->_where_arr[$table_alias]);
                }
            }
        }
        if (!empty($this->_where_arr)) {
            $query .= "WHERE 1=1 \n   AND ";
            $i = 0;
            foreach ($this->_where_arr as $table_alias => $where_block) {
                $query .= implode("\n   AND ", $where_block);
                $i++;
                if ($i < count($this->_where_arr)) {
                    $query .= "\n   AND ";
                }
            }
        }
        if (!empty($this->_group_arr)) {
            $query .= "\nGROUP BY " . implode(', ', $this->_group_arr);
        }
        return $query;
    }

    /**
     * Prepares the structure of the JOIN rules for MySQL based tables
     */
    private function _prepareMySQLJoinedQueryStructure()
    {
        if (!isset($this->_table_data['joinRules'])) {
            return;
        }

        foreach ($this->_table_data['joinRules'] as $join_rule) {
            if (empty($join_rule['initiatorColumn'])
                || empty($join_rule['connectedColumn'])
            ) {
                continue;
            }

            $connected_column_arr = explode('.', $join_rule['connectedColumn']);

            if (in_array($connected_column_arr[0], $this->_from_arr)
                && count($this->_from_arr) > 1
            ) {
                if ($join_rule['type'] == 'left') {
                    $this->_left_join_arr[$connected_column_arr[0]] = $connected_column_arr[0];
                } else {
                    $this->_inner_join_arr[$connected_column_arr[0]] = $connected_column_arr[0];
                }
                unset($this->_from_arr[array_search($connected_column_arr[0], $this->_from_arr)]);
            } else {
                if ($join_rule['type'] == 'left') {
                    $this->_left_join_arr[$connected_column_arr[0]] = $connected_column_arr[0];
                } else {
                    $this->_inner_join_arr[$connected_column_arr[0]] = $connected_column_arr[0];
                }
            }

            $this->_where_arr[$connected_column_arr[0]][] = self::buildWhereCondition(
                $join_rule['initiatorTable'] . '.' . $join_rule['initiatorColumn'],
                'eq',
                $join_rule['connectedColumn'],
                false
            );
        }
    }

    /**
     * Prepares the query text for MySQL based table
     */
    private function _buildMySQLQuery()
    {
        // Build the final output
        $query = "SELECT ";
        $i = 0;
        foreach ($this->_select_arr as $table_alias => $select_block) {
            $query .= implode(",\n       ", $select_block);
            $i++;
            if ($i < count($this->_select_arr)) {
                $query .= ",\n       ";
            }
        }
        $query .= "\nFROM ";
        $query .= implode(', ', $this->_from_arr) . "\n";
        if (!empty($this->_inner_join_arr)) {
            $i = 0;
            foreach ($this->_inner_join_arr as $table_alias => $inner_join_block) {
                $query .= "  INNER JOIN " . $inner_join_block . "\n";
                if (!empty($this->_where_arr[$table_alias])) {
                    $query .= "     ON " . implode("\n     AND ", $this->_where_arr[$table_alias]) . "\n";
                    unset($this->_where_arr[$table_alias]);
                }
            }
        }
        if (!empty($this->_left_join_arr)) {

            foreach ($this->_left_join_arr as $table_alias => $left_join_block) {
                $query .= "  LEFT JOIN " . $left_join_block . "\n";
                if (!empty($this->_where_arr[$table_alias])) {
                    $query .= "     ON " . implode("\n     AND ", $this->_where_arr[$table_alias]) . "\n";
                    unset($this->_where_arr[$table_alias]);
                }
            }
        }
        if (!empty($this->_where_arr)) {
            $query .= "WHERE 1=1 \n   AND ";
            $i = 0;
            foreach ($this->_where_arr as $table_alias => $where_block) {
                $query .= implode("\n   AND ", $where_block);
                $i++;
                if ($i < count($this->_where_arr)) {
                    $query .= "\n   AND ";
                }
            }
        }
        if (!empty($this->_group_arr)) {
            $query .= "\nGROUP BY " . implode(', ', $this->_group_arr);
        }
        return $query;
    }

    /**
     * Prepares the Joined query structure for WP-based wpDataTables
     */
    private function _prepareWPJoinedQueryStructure()
    {
        global $wpdb;

        if (!isset($this->_table_data['joinRules'])) {
            return;
        }

        // Need to go through each post type and define the join rule
        foreach ($this->_table_data['joinRules'] as $join_rule) {
            if (empty($join_rule['initiatorColumn'])
                || empty($join_rule['connectedColumn'])
            ) {
                continue;
            }

            $connected_column_arr = explode('.', $join_rule['connectedColumn']);
            if (count($connected_column_arr) == 2) {
                // Joining by posts table column

                $tbl_alias = self::prepareSqlAlias('posts_' . $connected_column_arr[0]);

                if (!isset($this->_where_arr[$tbl_alias])) {
                    $this->_where_arr[$tbl_alias] = array();
                }
                if (isset($this->_from_arr[$tbl_alias])
                    && count($this->_from_arr) > 1
                ) {
                    if ($join_rule['type'] == 'left') {
                        $this->_left_join_arr[$tbl_alias] = $this->_from_arr[$tbl_alias];
                    } else {
                        $this->_inner_join_arr[$tbl_alias] = $this->_from_arr[$tbl_alias];
                    }
                    unset($this->_from_arr[$tbl_alias]);
                } else {
                    if ($join_rule['type'] == 'left') {
                        $this->_left_join_arr[$tbl_alias] = $wpdb->posts . ' AS ' . $tbl_alias;
                    } else {
                        $this->_inner_join_arr[$tbl_alias] = $wpdb->posts . ' AS ' . $tbl_alias;
                    }
                    $this->_where_arr[$tbl_alias][] = $tbl_alias . ".post_type = '" . $connected_column_arr[0] . "'";
                }

                $this->_where_arr[$tbl_alias][] = self::buildWhereCondition(
                    str_replace('-', '_', 'posts_' . $join_rule['connectedColumn']),
                    'eq',
                    self::prepareSqlAlias('posts_' . $join_rule['initiatorPostType'])
                    . '.' . self::prepareSqlAlias($join_rule['initiatorColumn']),
                    false
                );
            } else {
                if ($connected_column_arr[1] == 'meta') {
                    // joining by a meta value
                    $tbl_alias = self::prepareSqlAlias($join_rule['connectedColumn'] . '_tbl');

                    if (!isset($this->_where_arr[$tbl_alias])) {
                        $this->_where_arr[$tbl_alias] = array();
                    }
                    if (isset($this->_from_arr[$tbl_alias])
                        && count($this->_from_arr) > 1
                    ) {
                        if ($join_rule['type'] == 'left') {
                            $this->_left_join_arr[$tbl_alias] = $this->_from_arr[$tbl_alias];
                        } else {
                            $this->_inner_join_arr[$tbl_alias] = $this->_from_arr[$tbl_alias];
                        }
                        unset($this->_from_arr[$tbl_alias]);
                    } elseif (!isset($this->_inner_join_arr[$tbl_alias]) && !isset($this->_left_join_arr[$tbl_alias])) {
                        $rule = self::preparePostMetaSubquery($tbl_alias, $connected_column_arr[0], $connected_column_arr[2]);
                        $this->_where_arr[$tbl_alias] = array();
                        if ($join_rule['type'] == 'left') {
                            $this->_left_join_arr[$tbl_alias] = $rule;
                            if (isset($this->_from_arr['posts_' . $connected_column_arr[0]]) && count($this->_from_arr) > 1) {
                                $this->_left_join_arr['posts_' . $connected_column_arr[0]] = $this->_from_arr['posts_' . $connected_column_arr[0]];
                                unset($this->_from_arr['posts_' . $connected_column_arr[0]]);
                            } else {
                                $this->_left_join_arr['posts_' . $connected_column_arr[0]] = $wpdb->posts . 'AS posts_' . $connected_column_arr[0];
                            }
                        } else {
                            $this->_inner_join_arr[$tbl_alias] = $rule;
                            if (isset($this->_from_arr['posts_' . $connected_column_arr[0]]) && count($this->_from_arr) > 1) {
                                $this->_inner_join_arr['posts_' . $connected_column_arr[0]] = $this->_from_arr['posts_' . $connected_column_arr[0]];
                                unset($this->_from_arr['posts_' . $connected_column_arr[0]]);
                            } else {
                                $this->_inner_join_arr['posts_' . $connected_column_arr[0]] = $wpdb->posts . 'AS posts_' . $connected_column_arr[0];
                            }
                        }
                    }
                    $this->_where_arr[$tbl_alias][] = self::buildWhereCondition(
                        $tbl_alias . '.meta_value',
                        'eq',
                        'posts_' . $join_rule['initiatorPostType']
                        . '.' . $join_rule['initiatorColumn'],
                        false
                    );

                } elseif ($connected_column_arr[1] == 'taxonomy') {
                    // joining by taxonomy

                    $tbl_alias = self::prepareSqlAlias($join_rule['connectedColumn'] . '_tbl');

                    if (!isset($this->_where_arr[$tbl_alias])) {
                        $this->_where_arr[$tbl_alias] = array();
                    }
                    if (isset($this->_from_arr[$tbl_alias])
                        && count($this->_from_arr) > 1
                    ) {
                        if ($join_rule['type'] == 'left') {
                            $this->_left_join_arr[$tbl_alias] = $this->_from_arr[$tbl_alias];
                        } else {
                            $this->_inner_join_arr[$tbl_alias] = $this->_from_arr[$tbl_alias];
                        }
                        unset($this->_from_arr[$tbl_alias]);
                    } elseif (!isset($this->_inner_join_arr[$tbl_alias]) && !isset($this->_left_join_arr[$tbl_alias])) {
                        $rule = self::preparePostTaxonomySubquery($tbl_alias, $connected_column_arr[2]);

                        $this->_where_arr[$tbl_alias] = array();
                        $this->_where_arr[$tbl_alias][] = self::buildWhereCondition(
                            $tbl_alias . '.name',
                            'eq',
                            $where_condition['value']
                        );
                        $this->_where_arr[$tbl_alias][] = self::buildWhereCondition(
                            $tbl_alias . '.id',
                            'eq',
                            'posts_' . $where_column_arr[0] . '.ID',
                            false
                        );
                        if ($join_rule['type'] == 'left') {
                            $this->_left_join_arr[$tbl_alias] = $rule;
                            if (isset($this->_from_arr['posts_' . $connected_column_arr[0]]) && count($this->_from_arr) > 1) {
                                $this->_left_join_arr['posts_' . $connected_column_arr[0]] = $this->_from_arr['posts_' . $connected_column_arr[0]];
                                unset($this->_from_arr['posts_' . $connected_column_arr[0]]);
                            } else {
                                $this->_left_join_arr['posts_' . $connected_column_arr[0]] = $wpdb->posts . 'AS posts_' . $connected_column_arr[0];
                            }
                        } else {
                            $this->_inner_join_arr[$tbl_alias] = $rule;
                            if (isset($this->_from_arr['posts_' . $connected_column_arr[0]]) && count($this->_from_arr) > 1) {
                                $this->_inner_join_arr['posts_' . $connected_column_arr[0]] = $this->_from_arr['posts_' . $connected_column_arr[0]];
                                unset($this->_from_arr['posts_' . $connected_column_arr[0]]);
                            } else {
                                $this->_inner_join_arr['posts_' . $connected_column_arr[0]] = $wpdb->posts . 'AS posts_' . $connected_column_arr[0];
                            }
                        }
                    }
                    $this->_where_arr[$tbl_alias][] = self::buildWhereCondition(
                        $tbl_alias . '.meta_value',
                        'eq',
                        'posts_' . $join_rule['initiatorPostType']
                        . '.' . $join_rule['initiatorColumn'],
                        false
                    );
                }
            }
        }
    }

    /**
     * Prepare a GROUP BY block for MySQL based wpDataTables
     */
    private function _prepareMySQLGroupByBlock()
    {
        if (!$this->_has_groups) {
            return;
        }

        foreach ($this->_table_data['groupingRules'] as $grouping_rule) {
            if (empty($grouping_rule)) {
                continue;
            }
            $this->_group_arr[] = $grouping_rule;
        }
    }

    /**
     * Prepare a GROUP BY block for WP based wpDataTables
     */
    private function _prepareWPGroupByBlock()
    {
        if (!$this->_has_groups) {
            return;
        }

        foreach ($this->_table_data['groupingRules'] AS $grouping_rule) {
            if (empty($grouping_rule)) {
                continue;
            }
            $this->_group_arr[] = $this->_column_aliases[$grouping_rule];
        }
    }

    public static function preparePostTaxonomySubquery($alias, $taxonomy)
    {
        global $wpdb;

        if (empty($alias) || empty($taxonomy)) {
            return '';
        }

        $taxonomy_subquery = "(SELECT name, object_id as id";
        $taxonomy_subquery .= " FROM {$wpdb->terms} AS {$alias}_terms";
        $taxonomy_subquery .= " INNER JOIN {$wpdb->term_taxonomy} AS {$alias}_termtaxonomy";
        $taxonomy_subquery .= " ON {$alias}_termtaxonomy.term_id = {$alias}_terms.term_id ";
        $taxonomy_subquery .= " AND {$alias}_termtaxonomy.taxonomy = '{$taxonomy}'";
        $taxonomy_subquery .= " INNER JOIN {$wpdb->term_relationships} AS rel_{$alias}";
        $taxonomy_subquery .= "  ON {$alias}_termtaxonomy.term_taxonomy_id = rel_{$alias}.term_taxonomy_id";
        $taxonomy_subquery .= ") AS {$alias}";

        return $taxonomy_subquery;
    }

    public function setQuery($query)
    {
        $this->_query = wdtSanitizeQuery($query);
    }

    public function getQuery()
    {
        return $this->_query;
    }

    public function getQueryPreview()
    {
        if (get_option('wdtUseSeparateCon')) {
            $sql = new PDTSql(WDT_MYSQL_HOST, WDT_MYSQL_DB, WDT_MYSQL_USER, WDT_MYSQL_PASSWORD, WDT_MYSQL_PORT);
            $result = $sql->getAssoc($this->_query . ' LIMIT 5', array());
        } else {
            global $wpdb;
            $wpdb->hide_errors();
            $result = $wpdb->get_results($this->_query . ' LIMIT 5', ARRAY_A);

        }

        if (!empty($result)) {
            ob_start();
            include(WDT_TEMPLATE_PATH . '/admin/constructor/constructor_table_preview.inc.php');
            $ret_val = ob_get_contents();
            ob_end_clean();
        } else {
            $ret_val = __('<div class="alert alert-danger m-15">No results found. Please check if this query is correct! Table Constructor needs a query that returns data to build a wpDataTable.', 'wpdatatables');
            if (!get_option('wdtUseSeparateCon') && !empty($wpdb->last_error)) {
                $ret_val .= '<br>Error: ' . $wpdb->last_error . '</div>';
            }
        }
        return $ret_val;
    }

    /**
     * Returns the ending of the thumbnail URL string
     *
     * @return string
     */
    public static function getThumbSizeString()
    {
        return '-' . get_option('thumbnail_size_w') . 'x' . get_option('thumbnail_size_h') . '.';
    }

    /**
     * Generates a wpDataTable based on WP data query
     */
    public function generateWdtBasedOnQuery($tableData)
    {
        global $wpdb;

        $tableData['query'] = wdtSanitizeQuery(($tableData['query']));

        $table_array = array(
            'title' => '',
            'table_type' => 'mysql',
            'content' => '',
            'filtering' => 1,
            'filtering_form' => 0,
            'sorting' => 1,
            'fixed_layout' => 0,
            'responsive' => 0,
            'word_wrap' => 1,
            'tools' => 1,
            'display_length' => 10,
            'fixed_columns' => 0,
            'server_side' => 0,
            'editable' => 0,
            'editor_roles' => '',
            'mysql_table_name' => '',
            'hide_before_load' => 1,
            'tabletools_config' => serialize(array(
                'print' => 1,
                'copy' => 1,
                'excel' => 1,
                'csv' => 1,
                'pdf' => 0
            ))
        );

        $table_array['content'] = $tableData['query'];

        $res = WDTConfigController::tryCreateTable('mysql', $table_array['content']);

        if (empty($res->error)) {
            // get the newly generated table ID
            $wpdb->insert($wpdb->prefix . 'wpdatatables', $table_array);
            $tableId = $wpdb->insert_id;
            $res->table_id = $tableId;
            WDTConfigController::saveColumns(null, $res->table, $res->table_id);
            $res->columns = WDTConfigController::getColumnsConfig($tableId);
            do_action('wpdatatables_after_save_table', $tableId);
        }

        return $res;
    }


    /**
     * Returns a list of tables in the chosen DB
     *
     * @return array
     */
    public static function listMySQLTables()
    {
        $tables = array();

        if (get_option('wdtUseSeparateCon')) {
            try {
                $sql = new PDTSql(WDT_MYSQL_HOST, WDT_MYSQL_DB, WDT_MYSQL_USER, WDT_MYSQL_PASSWORD, WDT_MYSQL_PORT);
            } catch (Exception $ex) {
                return $tables;
            }
            $result = $sql->getArray('SHOW TABLES', array());
            if (empty($result)) {
                return $tables;
            }
        } else {
            global $wpdb;
            $result = $wpdb->get_results('SHOW TABLES', ARRAY_N);
        }
        // Formatting the result to plain array
        foreach ($result as $row) {
            $tables[] = $row[0];
        }

        return $tables;
    }

    /**
     * Return a list of columns for the selected tables
     */
    public static function listMySQLColumns($tables)
    {
        $columns = array('allColumns' => array(), 'sortedColumns' => array());
        if (!empty($tables)) {
            if (get_option('wdtUseSeparateCon')) {
                try {
                    $sql = new PDTSql(WDT_MYSQL_HOST, WDT_MYSQL_DB, WDT_MYSQL_USER, WDT_MYSQL_PASSWORD, WDT_MYSQL_PORT);
                } catch (Exception $ex) {
                    return $columns;
                }
                foreach ($tables as $table) {
                    $columns['sortedColumns'][$table] = array();
                    $columns_query = "SHOW COLUMNS FROM {$table}";
                    $table_columns = $sql->getAssoc($columns_query);
                    foreach ($table_columns as $table_column) {
                        $columns['sortedColumns'][$table][] = "{$table}.{$table_column['Field']}";
                        $columns['allColumns'][] = "{$table}.{$table_column['Field']}";
                    }
                }
            } else {
                global $wpdb;
                foreach ($tables as $table) {
                    $columns['sortedColumns'][$table] = array();
                    $table_columns = $wpdb->get_results("SHOW COLUMNS FROM {$table};", ARRAY_A);
                    foreach ($table_columns as $table_column) {
                        $columns['sortedColumns'][$table][] = "{$table}.{$table_column['Field']}";
                        $columns['allColumns'][] = "{$table}.{$table_column['Field']}";
                    }
                }
            }
        }

        return $columns;
    }

    /**
     * Generates a table based on the provided file and shows a preview
     *
     * @param $tableData
     * @return array
     */
    public function previewFileTable($tableData)
    {
        if (!empty($tableData['file'])) {
            $xls_url = urldecode(esc_url($tableData['file']));
            $uploads_dir = wp_upload_dir();
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $xls_url = str_replace($uploads_dir['baseurl'], str_replace('\\', '/', $uploads_dir['basedir']), $xls_url);
            } else {
                $xls_url = str_replace($uploads_dir['baseurl'], $uploads_dir['basedir'], $xls_url);
            }
        } else {
            return array('result' => 'error', 'message' => __('Empty file', 'wpdatatables'));
        }

        if (strpos(strtolower($xls_url), 'https://docs.google.com/spreadsheets') !== false) {
            // Preview from Google Spreadsheet
            $namedDataArray = WDTTools::extractGoogleSpreadsheetArray($xls_url);
            if (!empty($namedDataArray)) {
                $headingsArray = array_keys($namedDataArray[0]);
                $namedDataArray = array_slice($namedDataArray, 0, 5);
            } else {
                return array(
                    'result' => 'error',
                    'message' => __(
                        'Could not read Google spreadsheet, please check if the URL is correct and the spreadsheet is published to everyone',
                        'wpdatatables'
                    )
                );
            }
        } else {
            require_once(WDT_ROOT_PATH . '/lib/phpExcel/PHPExcel.php');
            $objPHPExcel = new PHPExcel();
            if (strpos(strtolower($xls_url), '.xlsx')) {
                $objReader = new PHPExcel_Reader_Excel2007();
            } elseif (strpos(strtolower($xls_url), '.xls')) {
                $objReader = new PHPExcel_Reader_Excel5();
            } elseif (strpos(strtolower($xls_url), '.ods')) {
                $objReader = new PHPExcel_Reader_OOCalc();
            } elseif (strpos(strtolower($xls_url), '.csv')) {
                $objReader = new PHPExcel_Reader_CSV();
            } else {
                return array('result' => 'error', 'message' => __('Could not read input file!', 'wpdatatables'));
            }

            $objPHPExcel = $objReader->load($xls_url);
            $objWorksheet = $objPHPExcel->getActiveSheet();
            $highestRow = $objWorksheet->getHighestRow();
            $highestRow = $highestRow > 6 ? 6 : $highestRow;
            $highestColumn = $objWorksheet->getHighestDataColumn();

            $headingsArray = $objWorksheet->rangeToArray('A1:' . $highestColumn . '1', null, true, true, true);
            $headingsArray = array_map('trim', $headingsArray[1]);

            $r = 0;
            $namedDataArray = array();
            $dataRows = $objWorksheet->rangeToArray('A2:' . $highestColumn . $highestRow, null, true, true, true);
            for ($row = 2; $row <= $highestRow; ++$row) {
                if (max($dataRows[$row]) !== null) {
                    ++$r;
                    foreach ($headingsArray as $dataColumnIndex => $dataColumnHeading) {
                        $namedDataArray[$r][$dataColumnHeading] = $dataRows[$row][$dataColumnIndex];
                    }
                }
            }
        }

        /** @noinspection PhpUnusedLocalVariableInspection */
        $columnTypeArray = WDTTools::detectColumnDataTypes($namedDataArray, $headingsArray);
        /** @noinspection PhpUnusedLocalVariableInspection */
        $possibleColumnTypes = WDTTools::getPossibleColumnTypes();

        $ret_val = '';

        if (!empty($namedDataArray)) {
            ob_start();
            include(WDT_TEMPLATE_PATH . 'admin/constructor/constructor_file_preview.inc.php');
            $ret_val = ob_get_contents();
            ob_end_clean();
        }

        return array('result' => 'success', 'message' => $ret_val);
    }

    /**
     * Reads the data from file in the DB and generates a wpDataTable
     *
     * @param $tableData
     * @return mixed|string
     */
    public function readFileData($tableData)
    {
        $columnTypes = array();
        $columnDateInputFormat = array();
        $numberFormat = get_option('wdtNumberFormat') ? get_option('wdtNumberFormat') : 1;

        if (!empty($tableData['file'])) {
            $xls_url = urldecode(esc_url($tableData['file']));
            $uploads_dir = wp_upload_dir();
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $xls_url = str_replace($uploads_dir['baseurl'], str_replace('\\', '/', $uploads_dir['basedir']), $xls_url);
            } else {
                $xls_url = str_replace($uploads_dir['baseurl'], $uploads_dir['basedir'], $xls_url);
            }
        } else {
            return _('Empty file', 'wpdatatables');
        }

        for ($i = 0; $i < count($tableData['columns']); $i++) {
            if ($tableData['columns'][$i]['orig_header'] == '%%NEW_COLUMN%%') {
                $tableData['columns'][$i]['orig_header'] = 'column' . $i;
            }
            $columnTypes[$tableData['columns'][$i]['orig_header']] = sanitize_text_field($tableData['columns'][$i]['type']);
            $columnDateInputFormat[$tableData['columns'][$i]['orig_header']] = sanitize_text_field($tableData['columns'][$i]['dateInputFormat']);
        }

        $this->_id = $this->generateManualTable($tableData);

        if (strpos(strtolower($xls_url), 'https://docs.google.com/spreadsheets') !== false) {
            $table_type = 'google';
            $namedDataArray = WDTTools::extractGoogleSpreadsheetArray($xls_url);
            $headingsArray = array_keys($namedDataArray[0]);
            $highestRow = count($namedDataArray) - 1;
        } else {
            $table_type = 'excel';
            require_once(WDT_ROOT_PATH . '/lib/phpExcel/PHPExcel.php');
            $objPHPExcel = new PHPExcel();
            if (strpos(strtolower($xls_url), '.xlsx')) {
                $objReader = new PHPExcel_Reader_Excel2007();
            } elseif (strpos(strtolower($xls_url), '.xls')) {
                $objReader = new PHPExcel_Reader_Excel5();
            } elseif (strpos(strtolower($xls_url), '.ods')) {
                $objReader = new PHPExcel_Reader_OOCalc();
            } elseif (strpos(strtolower($xls_url), '.csv')) {
                $objReader = new PHPExcel_Reader_CSV();
            } else {
                return _('File format not supported!', 'wpdatatables');
            }

            $objPHPExcel = $objReader->load($xls_url);
            $objWorksheet = $objPHPExcel->getActiveSheet();
            $highestRow = $objWorksheet->getHighestRow();
            $highestColumn = $objWorksheet->getHighestDataColumn();

            $headingsArray = $objWorksheet->rangeToArray('A1:' . $highestColumn . '1', null, true, true, true);
            $headingsArray = array_map('trim', $headingsArray[1]);
        }

        $r = -1;

        $this->_column_headers = apply_filters('wpdt_insert_additional_column_header', $this->_column_headers);

        // Insert statement default beginning
        $insert_statement_beginning = "INSERT INTO "
            . $this->_name . " ("
            . implode(
                ', ',
                array_map(
                    function ($header) {
                        return "`{$header}`";
                    },
                    array_values($this->_column_headers)
                )
            )
            . ") ";
        $insert_blocks = array();
        if ($table_type != 'google') {
            $dataRows = $objWorksheet->rangeToArray('A2:' . $highestColumn . $highestRow, null, true, true, true);
        }
        for ($row = 0; $row <= $highestRow; ++$row) {

            $insertArray = array();

            if (($row <= 1) && ($table_type == 'excel')) {
                continue;
            }

            if ($table_type == 'google') {
                // Set all cells in the row to their defaults
                foreach ($tableData['columns'] as $column) {
                    $insertArray[$this->_column_headers[$column['orig_header']]] = "'" . sanitize_text_field($column['default_value']) . "'";
                }

                foreach ($headingsArray as $dataColumnIndex => $dataColumnHeading) {

                    $dataColumnHeading = addslashes($dataColumnHeading);

                    if (!in_array($dataColumnHeading, array_keys($this->_column_headers))) {
                        continue;
                    }

                    if (in_array($columnTypes[$dataColumnHeading], array('date', 'datetime', 'time'))) {
                        if ($columnTypes[$dataColumnHeading] == 'date') {
                            $date = WDTTools::wdtConvertStringToUnixTimestamp($namedDataArray[$row][$dataColumnHeading], $columnDateInputFormat[$dataColumnHeading]);
                            $insertArray[$this->_column_headers[$dataColumnHeading]] = "'" . date('Y-m-d', $date) . "'";
                        } elseif ($columnTypes[$dataColumnHeading] == 'datetime') {
                            $date = strtotime($namedDataArray[$row][$dataColumnHeading]);
                            $insertArray[$this->_column_headers[$dataColumnHeading]] = "'" . date('Y-m-d H:i:s', $date) . "'";
                        } elseif ($columnTypes[$dataColumnHeading] == 'time') {
                            $insertArray[$this->_column_headers[$dataColumnHeading]] = "'" . date('H:i:s', strtotime($namedDataArray[$row][$dataColumnHeading])) . "'";
                        }
                    } elseif ($columnTypes[$dataColumnHeading] == 'float') {
                        if ($numberFormat == 1) {
                            $insertArray[$this->_column_headers[$dataColumnHeading]] = "'" . esc_sql(str_replace(',', '.', str_replace('.', '', $namedDataArray[$row][$dataColumnHeading]))) . "'";
                        } else {
                            $insertArray[$this->_column_headers[$dataColumnHeading]] = "'" . esc_sql(str_replace(',', '', $namedDataArray[$row][$dataColumnHeading])) . "'";
                        }
                    } elseif ($columnTypes[$dataColumnHeading] == 'int') {
                        if ($numberFormat == 1) {
                            $insertArray[$this->_column_headers[$dataColumnHeading]] = "'" . esc_sql(str_replace('.', '', $namedDataArray[$row][$dataColumnHeading])) . "'";
                        } else {
                            $insertArray[$this->_column_headers[$dataColumnHeading]] = "'" . esc_sql(str_replace(',', '', $namedDataArray[$row][$dataColumnHeading])) . "'";
                        }
                    } else {
                        $insertArray[$this->_column_headers[$dataColumnHeading]] = "'" . esc_sql($namedDataArray[$row][$dataColumnHeading]) . "'";
                    }
                }
            } else {
                if (max($dataRows[$row]) !== null) {
                    // Set all cells in the row to their defaults
                    foreach ($tableData['columns'] as $column) {
                        $insertArray[$this->_column_headers[$column['orig_header']]] = "'" . sanitize_text_field($column['default_value']) . "'";
                    }
                    ++$r;
                    foreach ($headingsArray as $dataColumnIndex => $dataColumnHeading) {

                        $dataColumnHeading = addslashes($dataColumnHeading);

                        if (!in_array($dataColumnHeading, array_keys($this->_column_headers))) {
                            continue;
                        }

                        if (in_array($columnTypes[$dataColumnHeading], array('date', 'datetime', 'time'))) {
                            if ($objReader instanceof PHPExcel_Reader_CSV) {
                                $date = WDTTools::wdtConvertStringToUnixTimestamp($dataRows[$row][$dataColumnIndex], $columnDateInputFormat[$dataColumnHeading]);
                            } else {
                                if ($dataRows[$row][$dataColumnIndex] == null) {
                                    $date = null;
                                } else {
                                    $cell = $objPHPExcel->getActiveSheet()->getCell($dataColumnIndex . '' . $row);
                                    if (PHPExcel_Shared_Date::isDateTime($cell)) {
                                        $date = PHPExcel_Shared_Date::ExcelToPHP($cell->getValue());
                                    } else {
                                        $date = WDTTools::wdtConvertStringToUnixTimestamp($dataRows[$row][$dataColumnIndex], $columnDateInputFormat[$dataColumnHeading]);
                                    }
                                }
                            }

                            if ($columnTypes[$dataColumnHeading] == 'date') {
                                $insertArray[$this->_column_headers[$dataColumnHeading]] = ($date == null) ? 'NULL' : "'" . date('Y-m-d', $date) . "'";
                            } elseif ($columnTypes[$dataColumnHeading] == 'datetime') {
                                $insertArray[$this->_column_headers[$dataColumnHeading]] = ($date == null) ? 'NULL' : "'" . date('Y-m-d H:i:s', $date) . "'";
                            } elseif ($columnTypes[$dataColumnHeading] == 'time') {
                                $insertArray[$this->_column_headers[$dataColumnHeading]] = ($date == null) ? 'NULL' : "'" . date('H:i:s', $date) . "'";
                            }
                        } elseif ($columnTypes[$dataColumnHeading] == 'float' && gettype($dataRows[$row][$dataColumnIndex]) === 'string') {
                            if ($numberFormat == 1) {
                                $insertArray[$this->_column_headers[$dataColumnHeading]] = $dataRows[$row][$dataColumnIndex] !== null ? "'" . esc_sql(str_replace(',', '.', str_replace('.', '', $dataRows[$row][$dataColumnIndex]))) . "'" : 'NULL';
                            } else {
                                $insertArray[$this->_column_headers[$dataColumnHeading]] = $dataRows[$row][$dataColumnIndex] !== null ? "'" . esc_sql(str_replace(',', '', $dataRows[$row][$dataColumnIndex])) . "'" : 'NULL';
                            }
                        } elseif ($columnTypes[$dataColumnHeading] == 'int') {
                            if ($numberFormat == 1) {
                                $insertArray[$this->_column_headers[$dataColumnHeading]] = $dataRows[$row][$dataColumnIndex] !== null ? "'" . esc_sql(str_replace('.', '', $dataRows[$row][$dataColumnIndex])) . "'" : 'NULL';
                            } else {
                                $insertArray[$this->_column_headers[$dataColumnHeading]] = $dataRows[$row][$dataColumnIndex] !== null ? "'" . esc_sql(str_replace(',', '', $dataRows[$row][$dataColumnIndex])) . "'" : 'NULL';
                            }
                        } else {
                            if ($columnTypes[$dataColumnHeading] === 'float') {
                                $insertArray[$this->_column_headers[$dataColumnHeading]] = $dataRows[$row][$dataColumnIndex] !== null ? "'" . esc_sql($dataRows[$row][$dataColumnIndex]) . "'" : 'NULL';
                            } else {
                                $insertArray[$this->_column_headers[$dataColumnHeading]] =  "'" . esc_sql($dataRows[$row][$dataColumnIndex]) . "'";
                            }
                        }

                    }
                }
            }

            $insertArray = apply_filters('wpdt_insert_additional_column_value', $insertArray, $row, $table_type);

            if (!empty($insertArray)) {
                $insert_blocks[] = '(' . implode(', ', $insertArray) . ')';
            }

            if ($row % 100 == 0) {
                $this->insertRowsChunk($insert_statement_beginning, $insert_blocks);
                $insert_blocks = array();
            }

        }

        $this->insertRowsChunk($insert_statement_beginning, $insert_blocks);
    }

    /********* Manrox ew Read Write  *******/
    public function readFileData_update($tableData)
    {
        $columnTypes = array();
        $columnDateInputFormat = array();
        $numberFormat = get_option('wdtNumberFormat') ? get_option('wdtNumberFormat') : 1;

        if (!empty($tableData['file'])) {
            $xls_url = urldecode(esc_url($tableData['file']));
            $uploads_dir = wp_upload_dir();
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $xls_url = str_replace($uploads_dir['baseurl'], str_replace('\\', '/', $uploads_dir['basedir']), $xls_url);
            } else {
                $xls_url = str_replace($uploads_dir['baseurl'], $uploads_dir['basedir'], $xls_url);
            }
        } else {
            return _('Empty file', 'wpdatatables');
        }

        $columnTypes[0]  = array('origin_header' => 'SKU', 'name' => 'sku', 'type' => 'string');
        $columnTypes[1]  = array('origin_header' => 'User ID', 'name' => 'userid', 'type' => 'string');
        $columnTypes[2]  = array('origin_header' => 'Asin', 'name' => 'asin', 'type' => 'string');
        $columnTypes[3]  = array('origin_header' => 'links not to use(Amazon)', 'name' => 'wdtcolumn', 'type' => 'link');
        $columnTypes[4]  = array('origin_header' => 'links to use', 'name' => 'ebay',  'type' => 'link');
        $columnTypes[5]  = array('origin_header' => 'Variations separated by comma', 'name' => 'wdtcolumn1', 'type' => 'string');
        $columnTypes[6]  = array('origin_header' => 'Seller', 'name' => 'seller', 'type' => 'string');
        $columnTypes[7]  = array('origin_header' => 'Estado', 'name' => 'wdtcolumn2', 'type' => 'string');
        $columnTypes[8]  = array('origin_header' => 'quantity', 'name' => 'wdtcolumn3', 'type' => 'int');
        $columnTypes[9]  = array('origin_header' => 'price', 'name' => 'wdtcolumn4', 'type' => 'int');
        $columnTypes[10]  = array('origin_header' => 'delivery price', 'name' => 'wdtcolumn5', 'type' => 'int');
        $columnTypes[11] = array('origin_header' => 'Total Price', 'name' => 'wdtcolumn6', 'type' => 'int');
        //$columnTypes[12] = array('origin_header' => 'Wdt Column1', 'name' => 'Wdt Column1', 'type' => 'int');
        //$columnTypes[13] = array('origin_header' => 'Wdt Column2', 'name' => 'Wdt Column2', 'type' => 'int');
        
        ///Assign THe Table Name
        $this->generateManualTableFrontend($tableData);
        ///Get All inserted List
        $alreadyIntheList = $this->getInsertedCodeIdInTable_Manrox();

        if (strpos(strtolower($xls_url), 'https://docs.google.com/spreadsheets') !== false) {
            $table_type = 'google';
            $namedDataArray = WDTTools::extractGoogleSpreadsheetArray($xls_url);
            $headingsArray = array_keys($namedDataArray[0]);
            $highestRow = count($namedDataArray) - 1;
        } else {
            $table_type = 'excel';
            require_once(WDT_ROOT_PATH . '/lib/phpExcel/PHPExcel.php');
            $objPHPExcel = new PHPExcel();
            if (strpos(strtolower($xls_url), '.xlsx')) {
                $objReader = new PHPExcel_Reader_Excel2007();
            } elseif (strpos(strtolower($xls_url), '.xls')) {
                $objReader = new PHPExcel_Reader_Excel5();
            } elseif (strpos(strtolower($xls_url), '.ods')) {
                $objReader = new PHPExcel_Reader_OOCalc();
            } elseif (strpos(strtolower($xls_url), '.csv')) {
                $objReader = new PHPExcel_Reader_CSV();
            } else {
                return _('File format not supported!', 'wpdatatables');
            }

            $objPHPExcel = $objReader->load($xls_url);
            $objWorksheet = $objPHPExcel->getActiveSheet();
            $highestRow = $objWorksheet->getHighestRow();
            $highestColumn = $objWorksheet->getHighestDataColumn();

            $headingsArray = $objWorksheet->rangeToArray('A1:' . $highestColumn . '1', null, true, true, true);
            $headingsArray = array_map('trim', $headingsArray[1]);
        }

        $r = -1;

        $this->_column_headers = apply_filters('wpdt_insert_additional_column_header', $this->_column_headers);

        $insert_statement_beginning = "INSERT INTO "
            . $this->_name . " (";
        for ($columnIdx = 0; $columnIdx < count($columnTypes); $columnIdx++) {
            if ($columnIdx) $insert_statement_beginning .= ', ';
            $insert_statement_beginning .= $columnTypes[$columnIdx]['name'];
        }
        $insert_statement_beginning .=  ')';

        $insert_blocks = array();
        if ($table_type != 'google') {
            $dataRows = $objWorksheet->rangeToArray('A2:' . $highestColumn . $highestRow, null, true, true, true);
        }

        ////// get user level
        $user_level_item_count = array('4' => 5000, '2' => 2000, '5' => 500, '1' => 2000);
        //$user_level_item_count = array('4' => 50, '2' => 40, '5' => 30, '1' => 25);
        if (!current_user_can('administrator')) {
            global $wpdb;
            //$user_level_statement = "SELECT * FROM " . $wpdb->prefix . 'ihc_user_levels WHERE user_id = ' . get_current_user_id(); 
            $user_level_statement = "SELECT * FROM " . $wpdb->prefix . 'ihc_user_levels WHERE user_id = 2'; 
            $user_levels = $wpdb->get_results($user_level_statement);
            $user_item_count = 0;
            //$user_item_count = $user_level_item_count['1'];
            $now_time = date('Y-m-d h:i:s');
        
            if ($user_levels) {
                foreach ($user_levels as $user_level) {
                    if ($user_level->expire_time > $now_time) {
                        foreach ($user_level_item_count as $level_key => $level_count) {
                            if ($user_level->level_id == $level_key) {
                                if ($user_item_count < $level_count)
                                    $user_item_count = $level_count;
                                break;
                            }
                        }
                    }
                }
            }
        }

        /////
        $skip_result = array();
        $overflow_result = array();
        $user_new_item_count = 0;
        $ebay_datas = array();
        $item_count = 0;
        $item_infos = array();
        $itemsEbayData = array();
        for ($row = 0; $row <= $highestRow; ++$row) {
            if (($row <= 1) && ($table_type == 'excel')) {
                continue;
            }
            $item_infos[basename($dataRows[$row]['D'])] = $dataRows[$row]['E'];
            $item_count = $item_count + 1;
            if ($item_count == 1000) {
                $itemsEbayDataTmp =  getDataByItemIdsAndVariations($item_infos);
                if ($itemsEbayDataTmp['state'] == 'ok') {
                    if ($itemsEbayData) {
                        $itemsEbayData = $itemsEbayData + $itemsEbayDataTmp['items'];
                    } else {
                        $itemsEbayData = $itemsEbayDataTmp['items'];
                    }
                }
                $item_count = 0;
            }
        }
        if ($item_count) {
            $itemsEbayDataTmp =  getDataByItemIdsAndVariations($item_infos);
            if ($itemsEbayDataTmp['state'] == 'ok') {
                if ($itemsEbayData) {
                    $itemsEbayData = $itemsEbayData + $itemsEbayDataTmp['items'];
                } else {
                    $itemsEbayData = $itemsEbayDataTmp['items'];
                }
            }
        }
        for ($row = 0; $row <= $highestRow; ++$row) {
            $insertArray = array();
            if (($row <= 1) && ($table_type == 'excel')) {
                continue;
            }

            if (max($dataRows[$row]) !== null) {                
                // Set all cells in the row to their defaults
                // foreach ($tableData['columns'] as $column) {
                //     $insertArray[$this->_column_headers[$column['orig_header']]] = "'" . sanitize_text_field($column['default_value']) . "'";
                // }
                ++$r;
                //Check if already Inserted 
                if (in_array($dataRows[$row]['A'], $alreadyIntheList)) {
                    $skip_result[] = isset($dataRows[$row]['A']) ? $dataRows[$row]['A'] : "";
                    continue;
                }
                
                if (!current_user_can('administrator')) {
                    if ($user_item_count <= $user_new_item_count + count($alreadyIntheList)) {
                        $overflow_result[] = isset($dataRows[$row]['A']) ? $dataRows[$row]['A'] : "";
                        continue;
                    }
                }

                //Get Data from Url
                $seller = $estado = "";
                $quantity = $price = $delivery_price = $total_price = 0;
                if (isset($itemsEbayData[basename($dataRows[$row]['D'])])) {
                    $item_data = $itemsEbayData[basename($dataRows[$row]['D'])];
                    $seller = $item_data['seller'];
                    $estado = $item_data['estado'];
                    $quantity = $item_data['quantity'];
                    $price = $item_data['price'];
                    $delivery_price = $item_data['delivery price'];
                    $total_price = $item_data['Total price'];
                }
                $insertArray[$columnTypes[0]['name']] = "'" . esc_sql($dataRows[$row]['A']) . "'";
                $insertArray[$columnTypes[1]['name']] = "'" . esc_sql(get_current_user_id()) . "'"; 
                $insertArray[$columnTypes[2]['name']] = "'" . esc_sql($dataRows[$row]['B']) . "'"; 
                $insertArray[$columnTypes[3]['name']] = "'" . esc_sql($dataRows[$row]['C']) . "'";
                $insertArray[$columnTypes[4]['name']] = "'" . esc_sql($dataRows[$row]['D']) . "'"; 
                $insertArray[$columnTypes[5]['name']] = "'" . esc_sql($dataRows[$row]['E']) . "'";
                $insertArray[$columnTypes[6]['name']] = "'" . esc_sql($seller) . "'";
                $insertArray[$columnTypes[7]['name']] = "'" . esc_sql($estado) . "'";
                $insertArray[$columnTypes[8]['name']] = "'" . esc_sql($quantity) . "'";
                $insertArray[$columnTypes[9]['name']] = "'" . esc_sql($price) . "'";
                $insertArray[$columnTypes[10]['name']] = "'" . esc_sql($delivery_price) . "'";
                $insertArray[$columnTypes[11]['name']] = "'" . esc_sql($total_price) . "'";
                // $insertArray[$columnTypes[11]['column_header']] = "'" . esc_sql($itemEbayData['Total price'] * 1.25) . "'";
                // $insertArray[$columnTypes[12]['column_header']] = "'" . esc_sql($itemEbayData['Total price'] * 1.5) . "'";
                
                //$insertArray = apply_filters('wpdt_insert_additional_column_value', $insertArray, $row, $table_type);   
                if (!empty($insertArray)) {
                    $insert_blocks[] = '(' . implode(', ', $insertArray) . ')';
                }

                if ($row % 100 == 0) {
                    $this->insertRowsChunk($insert_statement_beginning, $insert_blocks);
                    $insert_blocks = array();
                }
                $user_new_item_count = $user_new_item_count + 1;
            }
        }
        $this->insertRowsChunk($insert_statement_beginning, $insert_blocks);

        return array('skip' => $skip_result, 'overflow' => $overflow_result);
    }

    public function parseTable($table_id) {
        global $wpdb;
        $update_array = array();
        $this->setTableId($table_id);
        $this->generateTableName_for_update();
        $results =  $wpdb->get_results("SELECT * FROM " . $this->_name . " WHERE userid=" . get_current_user_id());
        $item_infos = array();
        $item_count = 0;
        foreach($results as $result_row) {
            $item_infos[basename($result_row->ebay)] = array('variation' => $result_row->wdtcolumn1, 'row_id' => $result_row->wdt_ID);
            $item_count = $item_count + 1;
            if ($item_count == 1000) {
                parseDataByItemIdsAndVariations($this->_name, $item_infos);
                $item_count = 0;
            }
        }

        if ($item_count) {
            parseDataByItemIdsAndVariations($this->_name, $item_infos);
        }
        ///
        //     $itemEbayData =  getDataByItemId(basename($result_row->ebay), $result_row->wdtcolumn1);
        //     $update_statement = "UPDATE {$this->_name}
        //                             SET wdtcolumn2 = \"{$itemEbayData['estado']}\", wdtcolumn3 = \"{$itemEbayData['quantity']}\",
        //                             wdtcolumn4 = \"{$itemEbayData['price']}\", wdtcolumn5 = \"{$itemEbayData['delivery price']}\",
        //                             wdtcolumn6 = \"{$itemEbayData['Total price']}\"
        //                             WHERE userid = " . get_current_user_id() . " AND wdt_ID = {$result_row->wdt_ID}";            
        //     $wpdb->query($update_statement);
        // }
    }

    /********* Manrox ew Read Write  *******/
    /**
     * Inserts a blocks of rows read from file
     *
     * @param $insert_statement_beginning
     * @param $insert_blocks
     */
    private function insertRowsChunk($insert_statement_beginning, $insert_blocks)
    {
        global $wpdb;

        if (count($insert_blocks) > 0) {
            $insert_statement = $insert_statement_beginning . " VALUES " . implode(', ', $insert_blocks);
            if (get_option('wdtUseSeparateCon')) {
                // External DB                
                $this->_db->doQuery($insert_statement, array());
            } else {
                $wpdb->query($insert_statement);
            }
        }
    }

    /**
     * Delete a column from a manually generated table
     *
     * @param $tableId
     * @param $columnName
     */
    public static function deleteManualColumn($tableId, $columnName)
    {
        global $wpdb;

        $tableData = WDTConfigController::loadTableFromDB($tableId);
        $existing_columns = WDTConfigController::loadColumnsFromDB($tableId);

        $delete_column_id = 0;
        $delete_column_index = 0;

        foreach ($existing_columns as $existing_column) {
            if ($existing_column->orig_header == $columnName) {
                $delete_column_index = $existing_column->pos;
                $delete_column_id = $existing_column->id;
                break;
            }
        }

        $drop_statement = "ALTER TABLE {$tableData->mysql_table_name} DROP COLUMN {$columnName}";

        // First delete the column from the MySQL table
        if (get_option('wdtUseSeparateCon')) {
            // External DB
            $Sql = new PDTSql(WDT_MYSQL_HOST, WDT_MYSQL_DB, WDT_MYSQL_USER, WDT_MYSQL_PASSWORD, WDT_MYSQL_PORT);
            $Sql->doQuery($drop_statement, array());
        } else {
            $wpdb->query($drop_statement);
        }

        if ($delete_column_id != 0) {
            // Delete the column from wp_wpdatatable_columns
            $wpdb->delete(
                $wpdb->prefix . 'wpdatatables_columns',
                array('id' => $delete_column_id)
            );

            // Update the order of other columns
            $update_statement = "UPDATE " . $wpdb->prefix . "wpdatatables_columns 
                                      SET pos = pos - 1 
                                      WHERE table_id = {$tableId} 
                                          AND pos >= " . (int)$delete_column_index;
            $wpdb->query($update_statement);

        }
    }

    /**
     * Add a new column to manually generated table
     */
    public static function addNewManualColumn($tableId, $column_data)
    {
        global $wpdb;

        $tableData = WDTConfigController::loadTableFromDB($tableId);
        $existing_columns = WDTConfigController::loadColumnsFromDB($tableId);
        $dateFormat = get_option('wdtDateFormat');
        $timeFormat = get_option('wdtTimeFormat');

        $existing_headers = array();
        $column_index = 0;
        foreach ($existing_columns as $existing_column) {
            $existing_headers[] = $existing_column->orig_header;
            if ($existing_column->orig_header == $column_data['insert_after']) {
                $column_index = $existing_column->pos + 1;
            }
        }

        $new_column_mysql_name = WDTTools::generateMySQLColumnName($column_data['name'], $existing_headers);
        $columnProperties = self::defineColumnProperties($new_column_mysql_name, $column_data);

        // Add the column to MySQL table
        $alter_table_statement = "ALTER TABLE {$tableData->mysql_table_name} 
                                        ADD COLUMN {$columnProperties['create_block']} ";
        if ($column_data['insert_after'] == '%%beginning%%') {
            $alter_table_statement .= " FIRST";
        } else if ($column_data['insert_after'] != '%%end%%') {
            $alter_table_statement .= " AFTER `{$column_data['insert_after']}`";
        }

        // Call the create statement on WPDB or on external DB if it is defined
        if (get_option('wdtUseSeparateCon')) {
            // External DB
            $Sql = new PDTSql(WDT_MYSQL_HOST, WDT_MYSQL_DB, WDT_MYSQL_USER, WDT_MYSQL_PASSWORD, WDT_MYSQL_PORT);
            $Sql->doQuery($alter_table_statement, array());
        } else {
            $wpdb->query($alter_table_statement);
        }

        // Fill in with default value if requested
        $column_data['default_value'] = sanitize_text_field($column_data['default_value']);
        if ($column_data['fill_default'] == 1 && $column_data['default_value']) {

            if ($column_data['type'] == 'date') {
                $column_data['default_value'] =
                    DateTime::createFromFormat(
                        $dateFormat,
                        $column_data['default_value']
                    )->format('Y-m-d');
            } else if ($column_data['type'] == 'datetime') {
                $column_data['default_value'] =
                    DateTime::createFromFormat(
                        $dateFormat . ' ' . $timeFormat,
                        $column_data['default_value']
                    )->format('Y-m-d H:i:s');
            } else if ($column_data['type'] == 'time') {
                $column_data['default_value'] =
                    DateTime::createFromFormat(
                        $timeFormat,
                        $column_data['default_value']
                    )->format('H:i:s');
            }

            $update_fill_default = "UPDATE {$tableData->mysql_table_name} 
                                            SET `{$new_column_mysql_name}` = '{$column_data['default_value']}' 
                                            WHERE 1";
            if (get_option('wdtUseSeparateCon')) {
                // External DB
                $Sql->doQuery($update_fill_default, array());
            } else {
                $wpdb->query($update_fill_default);
            }
        }

        // Move the existing columns if necessary
        if ($column_data['insert_after'] == '%%end%%') {
            $column_index = count($existing_columns);
        } else {
            $update_statement = "UPDATE " . $wpdb->prefix . "wpdatatables_columns 
                                        SET pos = pos + 1 
                                        WHERE table_id = {$tableId} 
                                            AND pos >= " . (int)$column_index;
            $wpdb->query($update_statement);
        }
        // Add the column to wp_wpdatatables_columns
        $wpdb->insert(
            $wpdb->prefix . "wpdatatables_columns",
            array(
                'table_id' => $tableId,
                'orig_header' => $new_column_mysql_name,
                'display_header' => sanitize_text_field($column_data['name']),
                'filter_type' => $columnProperties['filter_type'],
                'column_type' => $columnProperties['column_type'],
                'pos' => $column_index,
                'possible_values' => str_replace(',,;,|', '|', sanitize_text_field($column_data['possible_values'])),
                'advanced_settings' => json_encode($columnProperties['advanced_settings']),
                'input_type' => $columnProperties['editor_type']
            )
        );
    }

    /**
     * Prepare a list of all possible meta keys for provided post types
     * arranged in multidimensional array
     */
    public static function wdtGetPostMetaKeysForPostTypes()
    {
        global $wpdb;

        $query = "SELECT $wpdb->postmeta.meta_key, $wpdb->posts.post_type
	        FROM $wpdb->posts
	        LEFT JOIN $wpdb->postmeta 
	        ON $wpdb->posts.ID = $wpdb->postmeta.post_id 
                AND $wpdb->postmeta.meta_key != '' 
	        AND $wpdb->postmeta.meta_key NOT RegExp '(^[_0-9].+$)' 
	        AND $wpdb->postmeta.meta_key NOT RegExp '(^[0-9]+$)'
	        GROUP BY $wpdb->postmeta.meta_key;
	    ";

        $metaRes = $wpdb->get_results($query);

        $metaKeys = array();

        foreach ($metaRes as $metaRow) {
            if (!isset($metaKeys[$metaRow->post_type])) {
                $metaKeys[$metaRow->post_type] = array();
            }
            $metaKeys[$metaRow->post_type][] = $metaRow->meta_key;
        }

        echo json_encode($metaKeys);
    }

    /**
     * Prepare a list of all possible meta keys for provided post types
     * arranged in multidimensional array
     */
    public static function wdtGetTaxonomiesForPostTypes()
    {
        global $wp_taxonomies;

        $returnTaxonomies = array();

        foreach ($wp_taxonomies as $tax_name => $tax_obj) {
            foreach ($tax_obj->object_type as $post_type) {
                if (!isset($returnTaxonomies[$post_type])) {
                    $returnTaxonomies[$post_type] = array();
                }
                $returnTaxonomies[$post_type][] = $tax_name;
            }
        }
        echo json_encode($returnTaxonomies);
    }
}
