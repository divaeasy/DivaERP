(function($) {
    'use strict';

    if (!$) {
        return;
    }

    $(function() {
        var $tables = $('table.js-crud-list-table[data-crud-resource]');
        if ($tables.length === 0) {
            return;
        }

        ensureCrudEnhancerModals();

        $tables.each(function() {
            enhanceCrudTable($(this));
        });

        function enhanceCrudTable($table) {
            var resource = String($table.data('crudResource') || '').trim();
            if (resource === '') {
                return;
            }

            var config = {
                resource: resource,
                bulkDeleteToken: String($table.data('bulkDeleteToken') || '').trim(),
                updateToken: String($table.data('updateToken') || '').trim(),
                createToken: String($table.data('createToken') || '').trim(),
                importToken: String($table.data('importToken') || '').trim(),
                enableQuickCreate: String($table.data('enableQuickCreate') || '') === '1',
                enableImport: String($table.data('enableImport') || '') === '1',
                placeholder: String($table.data('placeholder') || '____'),
                rowSelector: 'tr[data-row-id]'
            };

            if (config.bulkDeleteToken !== '') {
                setupRowSelection($table, config);
                setupBulkDeletion($table, config);
            }

            if (config.updateToken !== '') {
                setupInlineEditing($table, config);
            }

            if (config.enableQuickCreate && config.createToken !== '') {
                setupQuickCreateTrigger($table, config);
            }

            if (config.enableImport && config.importToken !== '') {
                setupImportTrigger($table, config);
            }
        }

        function setupRowSelection($table, config) {
            var $theadRow = $table.find('thead tr').first();
            if ($theadRow.length === 0) {
                return;
            }

            if ($theadRow.find('th.js-row-selector-head').length === 0) {
                $theadRow.prepend(
                    '<th class="js-row-selector-head text-center" style="width:42px;">' +
                        '<input type="checkbox" class="js-select-all-rows" aria-label="Tout selectionner">' +
                    '</th>'
                );
            }

            $table.find('tbody tr[data-row-id]').each(function() {
                var $row = $(this);
                if ($row.find('td.js-row-selector-cell').length > 0) {
                    return;
                }

                $row.prepend(
                    '<td class="js-row-selector-cell text-center">' +
                        '<input type="checkbox" class="js-row-selector" aria-label="Selectionner la ligne">' +
                    '</td>'
                );
            });

            var $bulkBar = buildOrGetBulkBar($table);
            refreshBulkState($table, $bulkBar);

            $table.on('change', '.js-select-all-rows', function() {
                var isChecked = $(this).is(':checked');
                $table.find('tbody .js-row-selector').prop('checked', isChecked).trigger('change');
            });

            $table.on('change', '.js-row-selector', function() {
                var $row = $(this).closest('tr');
                $row.toggleClass('table-active', $(this).is(':checked'));

                var totalRows = $table.find('tbody .js-row-selector').length;
                var checkedRows = $table.find('tbody .js-row-selector:checked').length;
                var $selectAll = $table.find('.js-select-all-rows');

                $selectAll.prop('checked', totalRows > 0 && totalRows === checkedRows);
                refreshBulkState($table, $bulkBar);
            });

            $table.on('click', 'tbody tr[data-row-id]', function(event) {
                if ($(event.target).closest('a, button, input, select, textarea, label').length > 0) {
                    return;
                }

                var $checkbox = $(this).find('.js-row-selector').first();
                if ($checkbox.length === 0) {
                    return;
                }

                $checkbox.prop('checked', !$checkbox.is(':checked')).trigger('change');
            });
        }

        function buildOrGetBulkBar($table) {
            var $existing = $table.prev('.js-crud-bulk-bar').first();
            if ($existing.length > 0) {
                return $existing;
            }

            var tableTitle = String($('.page-header h1').first().text() || 'elements').trim().toLowerCase();
            var $bar = $(
                '<div class="alert alert-light border d-flex flex-wrap align-items-center justify-content-between mb-3 js-crud-bulk-bar" style="display:none;">' +
                    '<div class="font-weight-semibold">' +
                        '<i class="fas fa-layer-group mr-2 text-primary"></i>' +
                        '<span class="js-bulk-label">0 ligne selectionnee</span>' +
                    '</div>' +
                    '<div class="d-flex align-items-center" style="gap:8px;">' +
                        '<button type="button" class="btn btn-sm btn-outline-danger no-loading js-bulk-delete-btn">' +
                            '<i class="fas fa-trash mr-1"></i>Supprimer' +
                        '</button>' +
                        '<button type="button" class="btn btn-sm btn-outline-secondary no-loading js-bulk-clear-btn">Effacer</button>' +
                    '</div>' +
                '</div>'
            );

            $bar.attr('data-list-title', tableTitle);
            $table.before($bar);

            $bar.on('click', '.js-bulk-clear-btn', function() {
                $table.find('.js-select-all-rows').prop('checked', false);
                $table.find('.js-row-selector').prop('checked', false).trigger('change');
            });

            return $bar;
        }

        function refreshBulkState($table, $bulkBar) {
            var selectedCount = $table.find('.js-row-selector:checked').length;
            var label = selectedCount > 1
                ? selectedCount + ' lignes selectionnees'
                : (selectedCount === 1 ? '1 ligne selectionnee' : '0 ligne selectionnee');

            $bulkBar.find('.js-bulk-label').text(label);
            if (selectedCount > 0) {
                $bulkBar.show();
            } else {
                $bulkBar.hide();
            }
        }

        function setupBulkDeletion($table, config) {
            var $bulkBar = buildOrGetBulkBar($table);

            $bulkBar.on('click', '.js-bulk-delete-btn', function() {
                var selectedIds = collectSelectedIds($table);
                if (selectedIds.length === 0) {
                    return;
                }

                if (!window.confirm('Supprimer ' + selectedIds.length + ' element(s) selectionne(s) ?')) {
                    return;
                }

                $.ajax({
                    url: '/api/crud/' + encodeURIComponent(config.resource) + '/bulk-delete',
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        _token: config.bulkDeleteToken,
                        ids: selectedIds
                    }
                }).done(function(response) {
                    if (!response || response.success !== true) {
                        showToast((response && response.message) ? response.message : 'Suppression impossible.', 'error');
                        return;
                    }

                    showToast(response.message || 'Elements supprimes avec succes.', 'success');
                    window.location.reload();
                }).fail(function(xhr) {
                    var message = 'Suppression impossible.';
                    if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }
                    showToast(message, 'error');
                });
            });
        }

        function collectSelectedIds($table) {
            var ids = [];
            $table.find('tbody .js-row-selector:checked').each(function() {
                var id = parseInt($(this).closest('tr').data('rowId'), 10);
                if (!isNaN(id) && id > 0) {
                    ids.push(id);
                }
            });
            return ids;
        }

        function setupInlineEditing($table, config) {
            var editableFields = getEditableFields($table);
            if (editableFields.length === 0) {
                return;
            }

            $table.on('click', '.btn-edit', function(event) {
                var $row = $(this).closest('tr[data-row-id]');
                if ($row.length === 0) {
                    return;
                }

                event.preventDefault();

                if ($row.hasClass('js-inline-editing')) {
                    return;
                }

                cancelAllEditingRows($table);
                beginInlineEdit($row, editableFields, config);
            });

            $table.on('click', '.js-inline-cancel-btn', function() {
                var $row = $(this).closest('tr[data-row-id]');
                cancelInlineEdit($row);
            });

            $table.on('click', '.js-inline-save-btn', function() {
                var $row = $(this).closest('tr[data-row-id]');
                saveInlineEdit($row, editableFields, config);
            });

            $table.on('keydown', '.js-inline-input', function(event) {
                if (event.key === 'Escape') {
                    event.preventDefault();
                    cancelInlineEdit($(this).closest('tr[data-row-id]'));
                    return;
                }

                if (event.key === 'Enter') {
                    event.preventDefault();
                    saveInlineEdit($(this).closest('tr[data-row-id]'), editableFields, config);
                }
            });
        }

        function beginInlineEdit($row, editableFields, config) {
            var snapshot = {};

            editableFields.forEach(function(fieldMeta) {
                var $cell = $row.find('td[data-field="' + fieldMeta.field + '"]').first();
                if ($cell.length === 0) {
                    return;
                }

                var currentValue = String($cell.data('fieldValue') || '').trim();
                if (currentValue === '') {
                    currentValue = normalizeDisplayValue($cell.text(), config.placeholder);
                }

                snapshot[fieldMeta.field] = {
                    html: $cell.html(),
                    value: currentValue
                };

                var inputType = fieldMeta.type === 'int' ? 'number' : 'text';
                var requiredAttr = fieldMeta.required ? ' required' : '';
                var escapedValue = escapeHtml(currentValue);
                $cell.html('<input type="' + inputType + '" class="form-control form-control-sm js-inline-input" data-field="' + fieldMeta.field + '" value="' + escapedValue + '"' + requiredAttr + '>');
            });

            var $actionsCell = findActionsCell($row);
            if ($actionsCell.length > 0) {
                $actionsCell.find('.btn-edit, .btn-delete').addClass('d-none');
                $actionsCell.append(
                    '<span class="js-inline-actions">' +
                        '<button type="button" class="btn btn-action btn-view js-inline-save-btn" title="Enregistrer"><i class="fas fa-check"></i></button>' +
                        '<button type="button" class="btn btn-action btn-delete js-inline-cancel-btn" title="Annuler"><i class="fas fa-times"></i></button>' +
                    '</span>'
                );
            }

            $row.addClass('js-inline-editing').data('inlineSnapshot', snapshot);
            $row.find('.js-inline-input').first().trigger('focus');
        }

        function saveInlineEdit($row, editableFields, config) {
            if ($row.length === 0 || !$row.hasClass('js-inline-editing')) {
                return;
            }

            var id = parseInt($row.data('rowId'), 10);
            if (isNaN(id) || id <= 0) {
                showToast('Ligne invalide.', 'error');
                return;
            }

            var payload = { _token: config.updateToken };
            var hasError = false;

            editableFields.forEach(function(fieldMeta) {
                var $input = $row.find('.js-inline-input[data-field="' + fieldMeta.field + '"]');
                if ($input.length === 0) {
                    return;
                }

                var value = String($input.val() || '').trim();
                if (fieldMeta.required && value === '') {
                    hasError = true;
                    $input.addClass('is-invalid');
                    return;
                }

                $input.removeClass('is-invalid');
                payload[fieldMeta.field] = value;
            });

            if (hasError) {
                showToast('Merci de renseigner les champs obligatoires.', 'error');
                return;
            }

            $row.addClass('js-inline-saving');

            $.ajax({
                url: '/api/crud/' + encodeURIComponent(config.resource) + '/' + id + '/update',
                method: 'POST',
                dataType: 'json',
                data: payload
            }).done(function(response) {
                if (!response || response.success !== true) {
                    showToast((response && response.message) ? response.message : 'Mise a jour impossible.', 'error');
                    $row.removeClass('js-inline-saving');
                    return;
                }

                showToast(response.message || 'Element mis a jour.', 'success');
                window.location.reload();
            }).fail(function(xhr) {
                var message = 'Mise a jour impossible.';
                if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                showToast(message, 'error');
                $row.removeClass('js-inline-saving');
            });
        }

        function cancelInlineEdit($row) {
            if ($row.length === 0 || !$row.hasClass('js-inline-editing')) {
                return;
            }

            var snapshot = $row.data('inlineSnapshot') || {};
            Object.keys(snapshot).forEach(function(fieldName) {
                var fieldSnapshot = snapshot[fieldName] || null;
                var $cell = $row.find('td[data-field="' + fieldName + '"]').first();
                if ($cell.length === 0 || !fieldSnapshot) {
                    return;
                }
                $cell.html(fieldSnapshot.html || '');
            });

            var $actionsCell = findActionsCell($row);
            $actionsCell.find('.js-inline-actions').remove();
            $actionsCell.find('.btn-edit, .btn-delete').removeClass('d-none');

            $row.removeClass('js-inline-editing js-inline-saving').removeData('inlineSnapshot');
        }

        function cancelAllEditingRows($table) {
            $table.find('tbody tr.js-inline-editing').each(function() {
                cancelInlineEdit($(this));
            });
        }

        function findActionsCell($row) {
            var $cell = $row.find('td[data-role="actions"]').first();
            if ($cell.length > 0) {
                return $cell;
            }

            var $editButton = $row.find('.btn-edit').first();
            return $editButton.length > 0 ? $editButton.closest('td') : $row.find('td').last();
        }

        function getEditableFields($table) {
            var fields = [];
            $table.find('thead th[data-field]').each(function() {
                var $th = $(this);
                var field = String($th.data('field') || '').trim();
                if (field === '') {
                    return;
                }

                fields.push({
                    field: field,
                    label: String($th.text() || field).trim(),
                    required: String($th.data('required') || '') === '1',
                    type: String($th.data('type') || 'string').trim()
                });
            });
            return fields;
        }

        function setupQuickCreateTrigger($table, config) {
            var editableFields = getEditableFields($table);
            if (editableFields.length === 0) {
                return;
            }

            var $actionBarButtons = $('.action-bar .action-buttons').first();
            if ($actionBarButtons.length === 0 || $actionBarButtons.find('.js-open-quick-create[data-resource="' + config.resource + '"]').length > 0) {
                return;
            }

            var $button = $(
                '<button type="button" class="btn btn-sm btn-outline-primary ml-2 no-loading js-open-quick-create" data-resource="' + config.resource + '">' +
                    '<i class="fas fa-bolt mr-1"></i>Creation rapide' +
                '</button>'
            );
            $actionBarButtons.append($button);

            $button.on('click', function() {
                openQuickCreateModal(config, editableFields);
            });
        }

        function openQuickCreateModal(config, editableFields) {
            var $modal = $('#crudQuickCreateModal');
            var $title = $modal.find('.modal-title');
            var $fieldsWrap = $modal.find('.js-quick-create-fields');
            var $submit = $modal.find('.js-quick-create-submit');

            $title.text('Creation rapide');
            $fieldsWrap.empty();

            editableFields.forEach(function(fieldMeta) {
                var inputType = fieldMeta.type === 'int' ? 'number' : 'text';
                var requiredHtml = fieldMeta.required ? ' <span class="text-danger">*</span>' : '';
                var requiredAttr = fieldMeta.required ? ' required' : '';

                var fieldHtml =
                    '<div class="form-group">' +
                        '<label>' + escapeHtml(fieldMeta.label) + requiredHtml + '</label>' +
                        '<input type="' + inputType + '" class="form-control js-quick-create-input" data-field="' + fieldMeta.field + '"' + requiredAttr + '>' +
                    '</div>';

                $fieldsWrap.append(fieldHtml);
            });

            $submit.off('click').on('click', function() {
                var payload = { _token: config.createToken };
                var hasError = false;

                $fieldsWrap.find('.js-quick-create-input').each(function() {
                    var $input = $(this);
                    var field = String($input.data('field') || '').trim();
                    var value = String($input.val() || '').trim();
                    var required = $input.prop('required');

                    if (required && value === '') {
                        $input.addClass('is-invalid');
                        hasError = true;
                        return;
                    }

                    $input.removeClass('is-invalid');
                    payload[field] = value;
                });

                if (hasError) {
                    showToast('Merci de renseigner les champs obligatoires.', 'error');
                    return;
                }

                $submit.prop('disabled', true);

                $.ajax({
                    url: '/api/crud/' + encodeURIComponent(config.resource) + '/create',
                    method: 'POST',
                    dataType: 'json',
                    data: payload
                }).done(function(response) {
                    if (!response || response.success !== true) {
                        showToast((response && response.message) ? response.message : 'Creation impossible.', 'error');
                        $submit.prop('disabled', false);
                        return;
                    }

                    showToast(response.message || 'Element cree avec succes.', 'success');
                    $modal.modal('hide');
                    window.location.reload();
                }).fail(function(xhr) {
                    var message = 'Creation impossible.';
                    if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }
                    showToast(message, 'error');
                    $submit.prop('disabled', false);
                });
            });

            $modal.on('hidden.bs.modal', function() {
                $submit.prop('disabled', false);
            });

            $modal.modal('show');
            window.setTimeout(function() {
                $fieldsWrap.find('input').first().trigger('focus');
            }, 120);
        }

        function setupImportTrigger($table, config) {
            var $actionBarButtons = $('.action-bar .action-buttons').first();
            if ($actionBarButtons.length === 0 || $actionBarButtons.find('.js-open-import[data-resource="' + config.resource + '"]').length > 0) {
                return;
            }

            var $button = $(
                '<button type="button" class="btn btn-sm btn-outline-primary ml-2 no-loading js-open-import" data-resource="' + config.resource + '">' +
                    '<i class="fas fa-file-import mr-1"></i>Importer' +
                '</button>'
            );
            $actionBarButtons.append($button);

            $button.on('click', function() {
                openImportModal(config);
            });
        }

        function openImportModal(config) {
            var $modal = $('#crudImportModal');
            var $fileInput = $modal.find('input[name="file"]');
            var $submit = $modal.find('.js-import-submit');

            $fileInput.val('');
            $submit.prop('disabled', false);

            $submit.off('click').on('click', function() {
                var file = $fileInput[0] && $fileInput[0].files ? $fileInput[0].files[0] : null;
                if (!file) {
                    showToast('Selectionnez un fichier a importer.', 'error');
                    return;
                }

                var formData = new FormData();
                formData.append('_token', config.importToken);
                formData.append('file', file);

                $submit.prop('disabled', true);

                $.ajax({
                    url: '/api/crud/' + encodeURIComponent(config.resource) + '/import',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false
                }).done(function(response) {
                    if (!response || response.success !== true) {
                        showToast((response && response.message) ? response.message : 'Import impossible.', 'error');
                        $submit.prop('disabled', false);
                        return;
                    }

                    showToast(response.message || 'Import termine.', 'success');
                    $modal.modal('hide');
                    window.location.reload();
                }).fail(function(xhr) {
                    var message = 'Import impossible.';
                    if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }
                    showToast(message, 'error');
                    $submit.prop('disabled', false);
                });
            });

            $modal.modal('show');
        }

        function normalizeDisplayValue(value, placeholder) {
            var normalized = String(value || '').trim();
            if (normalized === '' || normalized === placeholder) {
                return '';
            }
            return normalized;
        }

        function ensureCrudEnhancerModals() {
            if ($('#crudQuickCreateModal').length === 0) {
                $('body').append(
                    '<div class="modal fade" id="crudQuickCreateModal" tabindex="-1" role="dialog" aria-hidden="true">' +
                        '<div class="modal-dialog" role="document">' +
                            '<div class="modal-content">' +
                                '<div class="modal-header">' +
                                    '<h5 class="modal-title">Creation rapide</h5>' +
                                    '<button type="button" class="close" data-dismiss="modal" aria-label="Close">' +
                                        '<span aria-hidden="true">&times;</span>' +
                                    '</button>' +
                                '</div>' +
                                '<div class="modal-body">' +
                                    '<div class="js-quick-create-fields"></div>' +
                                '</div>' +
                                '<div class="modal-footer">' +
                                    '<button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>' +
                                    '<button type="button" class="btn btn-primary js-quick-create-submit">Enregistrer</button>' +
                                '</div>' +
                            '</div>' +
                        '</div>' +
                    '</div>'
                );
            }

            if ($('#crudImportModal').length === 0) {
                $('body').append(
                    '<div class="modal fade" id="crudImportModal" tabindex="-1" role="dialog" aria-hidden="true">' +
                        '<div class="modal-dialog" role="document">' +
                            '<div class="modal-content">' +
                                '<div class="modal-header">' +
                                    '<h5 class="modal-title">Importer des donnees</h5>' +
                                    '<button type="button" class="close" data-dismiss="modal" aria-label="Close">' +
                                        '<span aria-hidden="true">&times;</span>' +
                                    '</button>' +
                                '</div>' +
                                '<div class="modal-body">' +
                                    '<div class="alert alert-info py-2 mb-3">Fichiers supportes: CSV, XLS, XLSX</div>' +
                                    '<div class="form-group mb-0">' +
                                        '<label>Fichier</label>' +
                                        '<input type="file" name="file" class="form-control-file" accept=".csv,.txt,.xls,.xlsx">' +
                                    '</div>' +
                                '</div>' +
                                '<div class="modal-footer">' +
                                    '<button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>' +
                                    '<button type="button" class="btn btn-primary js-import-submit">Importer</button>' +
                                '</div>' +
                            '</div>' +
                        '</div>' +
                    '</div>'
                );
            }
        }

        function showToast(message, type) {
            if (typeof window.showAppToast === 'function') {
                window.showAppToast({ message: message, type: type || 'info' });
                return;
            }
            window.alert(message);
        }

        function escapeHtml(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }
    });
})(window.jQuery);
