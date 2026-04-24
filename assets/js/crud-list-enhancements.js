(function($) {
    'use strict';

    if (!$) {
        return;
    }

    var crudBulkDeleteState = null;
    var crudGlobalHandlersBound = false;

    function bootCrudListEnhancements() {
        var $tables = $('table.js-crud-list-table[data-crud-resource]');
        if ($tables.length === 0) {
            return;
        }

        ensureCrudEnhancerModals();

        if (!crudGlobalHandlersBound) {
            bindCrudBulkDeleteConfirmHandler();
            crudGlobalHandlersBound = true;
        }

        $tables.each(function() {
            var $table = $(this);
            if ($table.attr('data-crud-enhanced') === '1') {
                return;
            }

            enhanceCrudTable($table);
            $table.attr('data-crud-enhanced', '1');
        });
    }

    $(bootCrudListEnhancements);
    document.addEventListener('turbo:load', bootCrudListEnhancements);
    document.addEventListener('turbo:render', bootCrudListEnhancements);

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

            var tableId = resolveTableId($table, config.resource);
            var rowCheckboxSelector = '.js-row-selector, [data-row-checkbox]';
            var selectAllSelector = '.js-select-all-rows, [data-select-all-checkbox]';

            if ($theadRow.find(selectAllSelector).length === 0 && $theadRow.find('th.js-row-selector-head, th.row-selector-head').length === 0) {
                $theadRow.prepend(
                    '<th class="row-selector-head js-row-selector-head text-center" data-column-key="selector" style="width:42px;">' +
                        '<div class="form-check d-flex justify-content-center mb-0">' +
                            '<input type="checkbox" class="form-check-input position-static js-select-all-rows" data-select-all-checkbox aria-label="Tout selectionner">' +
                        '</div>' +
                    '</th>'
                );
            }

            $table.find('tbody tr[data-row-id]').each(function() {
                var $row = $(this);
                if ($row.find(rowCheckboxSelector).length > 0 || $row.find('td.js-row-selector-cell, td.row-selector-cell').length > 0) {
                    if (!$row.attr('data-item-id')) {
                        $row.attr('data-item-id', String($row.attr('data-row-id') || ''));
                    }
                    return;
                }

                var rowId = String($row.attr('data-row-id') || '');
                $row.prepend(
                    '<td class="row-selector-cell js-row-selector-cell text-center" data-column-key="selector">' +
                        '<div class="form-check d-flex justify-content-center mb-0">' +
                            '<input type="checkbox" class="form-check-input position-static js-row-selector" data-row-checkbox value="' + escapeHtml(rowId) + '" aria-label="Selectionner la ligne">' +
                        '</div>' +
                    '</td>'
                );

                if (!$row.attr('data-item-id')) {
                    $row.attr('data-item-id', rowId);
                }
            });

            var $bulkBar = buildOrGetBulkBar($table, tableId);
            refreshBulkState($table, $bulkBar);

            $table.on('change', selectAllSelector, function() {
                var isChecked = $(this).is(':checked');
                $table.find('tbody ' + rowCheckboxSelector).prop('checked', isChecked).trigger('change');
            });

            $table.on('change', rowCheckboxSelector, function() {
                var $row = $(this).closest('tr');
                var isChecked = $(this).is(':checked');
                $row.toggleClass('table-active', isChecked);
                $row.toggleClass('is-selected', isChecked);

                var totalRows = $table.find('tbody ' + rowCheckboxSelector).length;
                var checkedRows = $table.find('tbody ' + rowCheckboxSelector + ':checked').length;
                var $selectAll = $table.find(selectAllSelector);

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

        function buildOrGetBulkBar($table, tableId) {
            var normalizedTableId = String(tableId || '').trim();

            if (normalizedTableId !== '') {
                var $linked = $('[data-bulk-bar-for="' + normalizedTableId + '"]').first();
                if ($linked.length > 0) {
                    return $linked;
                }
            }

            var $existing = $table.prev('.js-crud-bulk-bar').first();
            if ($existing.length > 0) {
                return $existing;
            }

            var tableTitle = String($('.page-header h1').first().text() || 'elements').trim().toLowerCase();
            var $bar = $(
                '<div class="table-bulk-actions js-crud-bulk-bar" data-bulk-bar-for="' + escapeHtml(normalizedTableId) + '">' +
                    '<div class="table-bulk-actions__content">' +
                        '<div class="table-bulk-actions__left">' +
                            '<span class="table-bulk-actions__badge">' +
                                '<i class="fas fa-check-circle"></i>' +
                                '<strong data-selection-count>0</strong>' +
                            '</span>' +
                            '<span class="table-bulk-actions__label" data-selection-label>0 ligne selectionnee</span>' +
                        '</div>' +
                        '<div class="table-bulk-actions__buttons">' +
                            '<button type="button" class="btn btn-sm table-bulk-actions__delete-btn no-loading js-bulk-delete-btn" data-bulk-action="delete">' +
                                '<i class="fas fa-trash-alt mr-1"></i> Supprimer' +
                            '</button>' +
                            '<button type="button" class="btn btn-sm table-bulk-actions__clear-btn no-loading js-bulk-clear-btn" data-bulk-clear>' +
                                'Annuler' +
                            '</button>' +
                        '</div>' +
                    '</div>' +
                '</div>'
            );

            $bar.attr('data-list-title', tableTitle);
            var $actionBar = $table.closest('.table-container').prevAll('.action-bar').first();
            if ($actionBar.length > 0) {
                var $actionTools = $actionBar.find('.action-tools').first();
                if ($actionTools.length > 0) {
                    $bar.insertBefore($actionTools);
                } else {
                    $actionBar.append($bar);
                }
            } else {
                $table.before($bar);
            }

            $bar.on('click', '[data-bulk-clear], .js-bulk-clear-btn', function() {
                $table.find('.js-select-all-rows, [data-select-all-checkbox]').prop('checked', false);
                $table.find('.js-row-selector, [data-row-checkbox]').prop('checked', false).trigger('change');
            });

            return $bar;
        }

        function refreshBulkState($table, $bulkBar) {
            var selectedCount = $table.find('.js-row-selector:checked, [data-row-checkbox]:checked').length;
            var totalRows = $table.find('tbody tr[data-row-id], tbody tr[data-item-id]').length;
            var label = selectedCount > 1
                ? selectedCount + ' lignes selectionnees'
                : (selectedCount === 1 ? '1 ligne selectionnee' : '0 ligne selectionnee');

            var $selectAll = $table.find('.js-select-all-rows, [data-select-all-checkbox]').first();
            if ($selectAll.length > 0) {
                $selectAll.prop('checked', totalRows > 0 && selectedCount === totalRows);
                $selectAll.prop('indeterminate', selectedCount > 0 && selectedCount < totalRows);
            }

            if ($bulkBar.length === 0) {
                return;
            }

            if ($bulkBar.find('[data-selection-count]').length > 0) {
                $bulkBar.find('[data-selection-count]').text(selectedCount);
            }
            if ($bulkBar.find('[data-selection-label]').length > 0) {
                $bulkBar.find('[data-selection-label]').text(label);
            }
            if ($bulkBar.find('.js-bulk-label').length > 0) {
                $bulkBar.find('.js-bulk-label').text(label);
            }

            if ($bulkBar.hasClass('table-bulk-actions')) {
                $bulkBar.toggleClass('is-visible', selectedCount > 0);
            } else if (selectedCount > 0) {
                $bulkBar.show();
            } else {
                $bulkBar.hide();
            }
        }

        function setupBulkDeletion($table, config) {
            var $bulkBar = buildOrGetBulkBar($table, resolveTableId($table, config.resource));

            $bulkBar.on('click', '[data-bulk-action="delete"], .js-bulk-delete-btn', function() {
                var selectedIds = collectSelectedIds($table);
                if (selectedIds.length === 0) {
                    return;
                }

                openCrudBulkDeleteDialog($table, config, selectedIds);
            });
        }

        function collectSelectedIds($table) {
            var ids = [];
            $table.find('tbody .js-row-selector:checked, tbody [data-row-checkbox]:checked').each(function() {
                var $row = $(this).closest('tr');
                var rowIdValue = String($row.attr('data-row-id') || $(this).val() || '').trim();
                var id = parseInt(rowIdValue, 10);
                if (!isNaN(id) && id > 0) {
                    ids.push(id);
                }
            });
            return ids;
        }

        function resolveTableId($table, fallbackResource) {
            var tableId = String($table.attr('data-table-id') || '').trim();
            if (tableId !== '') {
                return tableId;
            }

            tableId = String(fallbackResource || '').trim();
            if (tableId !== '') {
                $table.attr('data-table-id', tableId);
            }
            return tableId;
        }

        function bindCrudBulkDeleteConfirmHandler() {
            $(document).off('click.crudBulkDelete', '#confirmDeleteBtn').on('click.crudBulkDelete', '#confirmDeleteBtn', function(event) {
                if (String($(this).attr('data-crud-bulk-delete') || '') !== '1') {
                    return;
                }

                event.preventDefault();

                if (!crudBulkDeleteState || !crudBulkDeleteState.table || crudBulkDeleteState.ids.length === 0) {
                    resetCrudBulkDeleteState();
                    return;
                }

                submitCrudBulkDelete();
            });

            $('#deleteConfirmModal').off('hidden.bs.modal.crudBulkDelete').on('hidden.bs.modal.crudBulkDelete', function() {
                if (!crudBulkDeleteState || !crudBulkDeleteState.submitting) {
                    resetCrudBulkDeleteState();
                }
            });
        }

        function openCrudBulkDeleteDialog($table, config, selectedIds) {
            var ids = Array.isArray(selectedIds) ? selectedIds.slice() : [];
            if (ids.length === 0) {
                return;
            }

            var $modal = $('#deleteConfirmModal');
            if ($modal.length === 0 || $('#confirmDeleteBtn').length === 0) {
                fallbackBulkDeleteWithConfirm($table, config, ids);
                return;
            }

            crudBulkDeleteState = {
                table: $table,
                config: config,
                ids: ids,
                submitting: false
            };

            $('#deleteModalItemName').text(ids.length > 1 ? (ids.length + ' elements selectionnes') : 'cet element selectionne');
            $('#confirmDeleteBtn')
                .attr('href', '#')
                .attr('data-crud-bulk-delete', '1')
                .removeClass('bulk-delete-loading')
                .css('pointer-events', '');

            $modal.modal('show');
        }

        function submitCrudBulkDelete() {
            if (!crudBulkDeleteState || crudBulkDeleteState.submitting) {
                return;
            }

            var state = crudBulkDeleteState;
            var config = state.config || {};
            var $confirmDeleteBtn = $('#confirmDeleteBtn');
            var $deleteModal = $('#deleteConfirmModal');

            state.submitting = true;
            $confirmDeleteBtn.addClass('bulk-delete-loading').css('pointer-events', 'none');

            $.ajax({
                url: '/api/crud/' + encodeURIComponent(config.resource) + '/bulk-delete',
                method: 'POST',
                dataType: 'json',
                data: JSON.stringify({
                    _token: config.bulkDeleteToken,
                    ids: state.ids
                }),
                contentType: 'application/json; charset=UTF-8',
                processData: false,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).done(function(response) {
                if (!response || response.success !== true) {
                    showToast((response && response.message) ? response.message : 'Suppression impossible.', 'error');
                    return;
                }

                var deletedIds = Array.isArray(response.deletedIds) && response.deletedIds.length
                    ? response.deletedIds
                    : state.ids;

                removeRowsByIds(state.table, deletedIds);

                var $selectAll = state.table.find('.js-select-all-rows, [data-select-all-checkbox]').first();
                if ($selectAll.length > 0) {
                    $selectAll.prop('checked', false).prop('indeterminate', false);
                }

                refreshBulkState(state.table, buildOrGetBulkBar(state.table, resolveTableId(state.table, config.resource)));
                $deleteModal.modal('hide');
                showToast(response.message || 'Elements supprimes avec succes.', 'success');
            }).fail(function(xhr) {
                var message = 'Suppression impossible.';
                if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                showToast(message, 'error');
            }).always(function() {
                if (crudBulkDeleteState) {
                    crudBulkDeleteState.submitting = false;
                }
                $confirmDeleteBtn.removeClass('bulk-delete-loading').css('pointer-events', '');
                if (!$deleteModal.hasClass('show')) {
                    resetCrudBulkDeleteState();
                }
            });
        }

        function fallbackBulkDeleteWithConfirm($table, config, ids) {
            if (!window.confirm('Supprimer ' + ids.length + ' element(s) selectionne(s) ?')) {
                return;
            }

            $.ajax({
                url: '/api/crud/' + encodeURIComponent(config.resource) + '/bulk-delete',
                method: 'POST',
                dataType: 'json',
                data: {
                    _token: config.bulkDeleteToken,
                    ids: ids
                }
            }).done(function(response) {
                if (!response || response.success !== true) {
                    showToast((response && response.message) ? response.message : 'Suppression impossible.', 'error');
                    return;
                }
                removeRowsByIds($table, ids);
                refreshBulkState($table, buildOrGetBulkBar($table, resolveTableId($table, config.resource)));
                showToast(response.message || 'Elements supprimes avec succes.', 'success');
            }).fail(function(xhr) {
                var message = 'Suppression impossible.';
                if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                showToast(message, 'error');
            });
        }

        function removeRowsByIds($table, ids) {
            var idMap = {};
            ids.forEach(function(id) {
                idMap[String(id)] = true;
            });

            $table.find('tbody tr').each(function() {
                var $row = $(this);
                var rowId = String($row.attr('data-row-id') || $row.attr('data-item-id') || '').trim();
                if (rowId !== '' && idMap[rowId]) {
                    $row.remove();
                }
            });
        }

        function resetCrudBulkDeleteState() {
            crudBulkDeleteState = null;
            $('#confirmDeleteBtn')
                .removeAttr('data-crud-bulk-delete')
                .removeClass('bulk-delete-loading')
                .css('pointer-events', '');
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
})(window.jQuery);
