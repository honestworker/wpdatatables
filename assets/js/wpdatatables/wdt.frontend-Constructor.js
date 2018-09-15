var constructedTableData = {
    name: 'new table',
    method: '',
    columnCount: 0,
    columns: []
};

var defaultPostColumns = [
    'ID',
    'post_date',
    'post_date_gmt',
    'post_author',
    'post_title',
    'title_with_link_to_post',
    'thumbnail_with_link_to_post',
    'post_content',
    'post_content_limited_100_chars',
    'post_excerpt',
    'post_status',
    'comment_status',
    'ping_status',
    'post_password',
    'post_name',
    'to_ping',
    'pinged',
    'post_modified',
    'post_modified_gmt',
    'post_content_filtered',
    'post_parent',
    'guid',
    'menu_order',
    'post_type',
    'post_mime_type',
    'comment_count'
];

var aceEditor = null;

(function ($) {

    var wdtNonce = $('#wdtNonce').val();
    var customUploader;
    var importButton = $('#wdt-import');
    var parseButton = $('#wdt-parse');
    var previousStepButton = $('#wdt-constructor-previous-step');

    /**
     * Default column data
     * @type {{name: *, type: string}}
     */
    var defaultColumnData = {
        'name': wdtConstructorStrings.newColumnName,
        'type': 'input'
    };

    /**
     * Add dragging/reordering for column blocks
     */
    function wdtApplyColumnReordering() {
        dragula({
            isContainer: function (el) {
                return el.classList.contains('wdt-constructor-columns-container');
            }
        });
    }

    /**
     * Apply selectpicker and taginput to input fields
     */
    function wdtApplyBootstrapElements() {
        $('.wdt-constructor-column-type').selectpicker();
        $('.wdt-constructor-date-input-format').selectpicker();
        $('.wdt-constructor-possible-values').tagsinput({
            tagClass: 'label label-primary'
        });
    }

    /**
     * Next step handler
     */
    importButton.click(function (e) {
        
        e.preventDefault();
        e.stopImmediatePropagation();

        // Validation
        if (!$('#wdt-constructor-input-url').val()) {
            wdtNotify(wpdatatables_edit_strings.error, wdtConstructorStrings.fileUploadEmptyFile, 'danger');
            return;
        }
        constructedTableData.file = $('#wdt-constructor-input-url').val();
        
        wdtGenerateAndPreviewFileTable();
    });

    /**
     * Next step handler
     */
    parseButton.click(function (e) {
        $('.wdt-preload-layer').show();
        $('.Manrox-table').addClass('overlayed');
        $.ajax({
            url: ajaxUrl,
            data: {
                action: 'wpdatatables_frontend_parse_table',
                table_id: $('.Manrox-table').data('wpdatatable_id'),
                wdtNonce: wdtNonce
            },
            type: 'POST',
            dataType: 'json',
            success: function (data) {
                if (data.res == 'success') {
                    $('.wdt-preload-layer').hide();
                    $('.Manrox-table').removeClass('overlayed');
                    location.reload(true);
                } else {

                }
            },
            error: function (data) {
                wdtNotify(wpdatatables_edit_strings.error, '', 'danger');
            }
        });
    });

    /**
     * Get HTML for a column block
     */
    var wdtGetColumnHtml = function (columnData) {
        var columnTemplate = $.templates("#wdt-constructor-column-block-template");
        return columnTemplate.render(columnData);
    };

    function UploadFile(data)
    {
        return
    }

    function wdtGenerateAndPreviewFileTable() {
        $('.wdt-preload-layer').show();
        /*Form File Upload */
        var file_data = $('#wdt-constructor-input-url').prop('files')[0];  
        var formdata = new FormData();
        formdata.append('file', file_data);        
        formdata.append('action','wpdatatables_upload_file_formData');
        $('.Manrox-table').addClass('overlayed');
        //Ajax Call for File Uploading
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: formdata,
            contentType:false,
            processData:false,
            dataType:'json',
            success: function (data) {
                ///Now Update That Table
                constructedTableData.file = siteUrl + '/wp-content/uploads/tempDatatable/' + data.file;
                constructedTableData.filePath = data.filePath;
                constructedTableData.method = 'file';
                if (!$('.Manrox-table').data('wpdatatable_id')) {
                    return;
                }
                constructedTableData.tableId = $('.Manrox-table').data('wpdatatable_id');
                
                //Ajax for Update the Table
                // alert(ajaxUrl);
                $.ajax({
                    url: ajaxUrl,
                    data: {
                        action: 'wpdatatables_frontend_preview_file_table',
                        tableData: constructedTableData,
                        wdtNonce: wdtNonce
                    },
                    type: 'post',
                    dataType: 'json',
                    success: function (data) {
                        if (data.result == 'error') {
                            $('.wdt-preload-layer').hide();
                            importButton.prop('disabled', false);
                            wdtNotify(wpdatatables_edit_strings.error, data.message, 'danger')
                        } else {
                            $('.Manrox-table').removeClass('overlayed');
                            $('div.manrox-wdt-constructor-columns-container').html(data.message);
                            constructedTableData.columnCount = parseInt($('div.wdt-constructor-column-block').length);
                            $('#wdt-constructor-number-of-columns').val(constructedTableData.columnCount);
                            $('.wdt-constructor-column-type').change();
                            importButton.prop('disabled', 'disabled');
                            //$('.wdt-constructor-create-buttons').show();
                            wdtApplyBootstrapElements();
                            $('.wdt-preload-layer').hide();
                            
                            wdtImportDatatable();
                        }
                    },
                    error: function (data) {
                        alert("Login with Admin")
                        $('#wdt-error-modal .modal-body').html('There was an error while trying to generate the table! ' + data.statusText + ' ' + data.responseText);
                        $('#wdt-error-modal').modal('show');
                        $('.wdt-preload-layer').animateFadeOut();
                    }
                })
            },
            error: function (data) {
                wdtNotify(wpdatatables_edit_strings.error, 'Uploading of file is failure!', 'danger')
            }
        });
    }

    function wdtImportDatatable() {
        var tableView = '';
        if ($(this).prop('id') == 'wdt-constructor-create-table-excel') {
            tableView = '&table_view=excel';
        }

        if (constructedTableData.method == 'file') {
            // Validation
            var valid = true;
            $('.wdt-constructor-column-name').each(function () {
                if ($(this).val() == '') {
                    $(this).click();
                    valid = false;
                }
            });

            if (valid) {
                $('.wdt-preload-layer').show();
                constructedTableData.columns = [];
                $('.wdt-constructor-column-block').each(function () {
                    constructedTableData.columns.push({
                        orig_header: $(this).find('.wdt-constructor-column-name').val(),
                        name: $(this).find('.wdt-constructor-column-name').val(),
                        type: $(this).find('.wdt-constructor-column-type').selectpicker('val'),
                        possible_values: $(this).find('.wdt-constructor-possible-values').val().replace(/,/g, '|'),
                        default_value: $(this).find('.wdt-constructor-default-value').val(),
                        dateInputFormat: typeof $(this).find('.wdt-constructor-date-input-format').val() !== 'undefined' ?
                            $(this).find('.wdt-constructor-date-input-format').selectpicker('val') : ''
                    });
                });

                $('#wdt-constructor-file-table-name').change();

                wdtReadFileDataAndEditTable(tableView);
            }
        }
    }

    /**
     * Handler which creates the table for manual and file method
     */
    $('#wdt-constructor-create-table, #wdt-constructor-create-table-excel').click(function (e) {
        e.preventDefault();

        var tableView = '';
        if ($(this).prop('id') == 'wdt-constructor-create-table-excel') {
            tableView = '&table_view=excel';
        }

        if (constructedTableData.method == 'file') {
            // Validation
            var valid = true;
            $('.wdt-constructor-column-name').each(function () {
                if ($(this).val() == '') {
                    $(this).click();
                    valid = false;
                }
            });

            if (valid) {
                $('div.wdt-constructor-step[data-step="2-2"]').hide();
                $('.wdt-preload-layer').show();
                constructedTableData.columns = [];
                $('.wdt-constructor-column-block').each(function () {
                    constructedTableData.columns.push({
                        orig_header: $(this).find('.wdt-constructor-column-name').val(),
                        name: $(this).find('.wdt-constructor-column-name').val(),
                        type: $(this).find('.wdt-constructor-column-type').selectpicker('val'),
                        possible_values: $(this).find('.wdt-constructor-possible-values').val().replace(/,/g, '|'),
                        default_value: $(this).find('.wdt-constructor-default-value').val(),
                        dateInputFormat: typeof $(this).find('.wdt-constructor-date-input-format').val() !== 'undefined' ?
                            $(this).find('.wdt-constructor-date-input-format').selectpicker('val') : ''
                    });
                });

                $('#wdt-constructor-file-table-name').change();

                wdtReadFileDataAndEditTable(tableView);
            }
        } 

    });

    /**
     * Generate the Excel/CSV/Google and open table settings
     * @param tableView
     */
    function wdtReadFileDataAndEditTable(tableView) {
        $('.Manrox-table').addClass('overlayed');
            $.ajax({
            url: ajaxUrl,
            dataType: 'json',
            data: {
                action: 'wpdatatables_constructor_read_file_data_frontend',
                tableData: constructedTableData,
                wdtNonce: wdtNonce
            },
            type: 'post',
            success: function (data) {
                console.log(data);
                if (data.res == 'success') {
                    //window.location = data.link + tableView;
                    if (data.errors) {
                        var error_msg = 'Items(SKU) you import occure some errors!\n';
                        if (typeof data.errors.overflow !== 'undefined' && data.errors.overflow !== null) {
                            if (data.errors.overflow.length >= 1) {
                                error_msg = error_msg + '\nOverflow Items(SKUs):\n';
                                for (var n = 0; n < data.errors.overflow.length; n++) {
                                    if (n !== 0) {
                                        error_msg = error_msg + ', ';
                                    }
                                    error_msg = error_msg + data.errors.overflow[n];
                                }
                            }
                        }
                        if (typeof data.errors.skip !== 'undefined' && data.errors.skip !== null) {
                            if (data.errors.skip.length >= 1) {
                                error_msg = error_msg + '\nSkip Items(SKUs):\n';
                                for (var n = 0; n < data.errors.skip.length; n++) {
                                    if (n !== 0) {
                                        error_msg = error_msg + ', ';
                                    }
                                    error_msg = error_msg + data.errors.skip[n];
                                }
                            }
                        }
                        alert(error_msg);
                    }
                    $('.Manrox-table').removeClass('overlayed');
                    location.reload(true);
                } else {                  
                    $('.wdt-preload-layer').hide();
                    wdtNotify(wpdatatables_edit_strings.error, data.text, 'danger');
                }
            },
            error: function (data) {
                console.log(data);
                $('#wdt-error-modal .modal-body').html('There was an error while trying to save the table! ' + data.statusText + ' ' + data.responseText);
                $('#wdt-error-modal').modal('show');
                $('.wdt-preload-layer').animateFadeOut();
            }
        })
    }
})(jQuery);