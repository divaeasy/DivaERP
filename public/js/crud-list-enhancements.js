(function($) {
    'use strict';

    if (!$) {
        return;
    }

    var crudBulkDeleteState = null;
    var crudGlobalHandlersBound = false;
    var crudUnsavedConfirmAction = null;

    function bootCrudListEnhancements() {
        var $tables = $('table.js-crud-list-table[data-crud-resource]');
        if ($tables.length === 0) {
            return;
        }

        ensureCrudEnhancerModals();
        ensureUnsavedChangesModal();

        if (!crudGlobalHandlersBound) {
            bindCrudBulkDeleteConfirmHandler();
            bindUnsavedChangesConfirmHandler();
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
                enableInlineEdit: isInlineEditEnabled($table),
                placeholder: String($table.data('placeholder') || '____'),
                rowSelector: 'tr[data-row-id]'
            };

            // Prevent loader lock only where edit is intercepted inline.
            if (config.enableInlineEdit && config.updateToken !== '') {
                $table.find('.btn-edit').addClass('no-loading');
            } else {
                $table.find('.btn-edit').removeClass('no-loading');
            }

            if (config.bulkDeleteToken !== '') {
                setupRowSelection($table, config);
                setupBulkDeletion($table, config);
            }

            if (config.enableInlineEdit && config.updateToken !== '') {
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
            bindBulkClearHandler($table, $bulkBar, rowCheckboxSelector, selectAllSelector);
            refreshBulkState($table, $bulkBar);

            $table.on('change', selectAllSelector, function() {
                var isChecked = $(this).is(':checked');
                $table.find('tbody tr[data-row-id]').each(function() {
                    var $row = $(this);
                    if ($row.hasClass('js-inline-editing')) {
                        return;
                    }

                    var $checkbox = $row.find(rowCheckboxSelector).first();
                    if ($checkbox.length === 0 || $checkbox.prop('disabled')) {
                        return;
                    }

                    $checkbox.prop('checked', isChecked).trigger('change');
                });
            });

            $table.on('change', rowCheckboxSelector, function() {
                var $row = $(this).closest('tr');
                if ($row.hasClass('js-inline-editing')) {
                    $(this).prop('checked', false);
                    showToast('Annulez la modification en cours avant de selectionner cette ligne.', 'error');
                    return;
                }

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
                if ($(event.target).closest('a, button, input, select, textarea, label, [contenteditable="true"], .inline-dropdown-shell, .inline-dropdown-menu, .inline-dropdown-option, .inline-dropdown-trigger').length > 0) {
                    return;
                }

                if ($(this).hasClass('js-inline-editing')) {
                    return;
                }

                var $checkbox = $(this).find(rowCheckboxSelector).first();
                if ($checkbox.length === 0 || $checkbox.prop('disabled')) {
                    return;
                }

                $checkbox.prop('checked', !$checkbox.is(':checked')).trigger('change');
            });
        }

        function bindBulkClearHandler($table, $bulkBar, rowCheckboxSelector, selectAllSelector) {
            if (!$bulkBar || $bulkBar.length === 0) {
                return;
            }

            $bulkBar.off('click.crudBulkClear', '[data-bulk-clear], .js-bulk-clear-btn');
            $bulkBar.on('click.crudBulkClear', '[data-bulk-clear], .js-bulk-clear-btn', function(event) {
                event.preventDefault();
                $table.find(selectAllSelector).prop('checked', false).prop('indeterminate', false);
                $table.find('tbody ' + rowCheckboxSelector).prop('checked', false).trigger('change');
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

            return $bar;
        }

        function refreshBulkState($table, $bulkBar) {
            var selectedCount = $table.find('.js-row-selector:checked, [data-row-checkbox]:checked').length;
            var totalRows = $table.find('tbody .js-row-selector:not(:disabled), tbody [data-row-checkbox]:not(:disabled)').length;
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

            var tableId = resolveTableId($table, config.resource);
            var guardNamespace = sanitizeEventNamespace('crudInlineUnsaved_' + tableId);

            function requestActionAfterUnsavedCheck(action) {
                var $activeRow = getActiveEditingRow($table);
                if (!$activeRow.length) {
                    action();
                    return;
                }

                if (!hasUnsavedChanges($activeRow, editableFields, config)) {
                    cancelInlineEdit($activeRow);
                    action();
                    return;
                }

                openUnsavedChangesDialog(function() {
                    cancelInlineEdit($activeRow);
                    action();
                });
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

                requestActionAfterUnsavedCheck(function() {
                    beginInlineEdit($row, editableFields, config);
                });
            });

            $table.on('dblclick', 'tbody tr[data-row-id]', function(event) {
                if ($(event.target).closest('a, button, input, select, textarea, label, [data-row-checkbox], [data-select-all-checkbox]').length > 0) {
                    return;
                }

                var $row = $(this);
                if ($row.length === 0 || $row.hasClass('js-inline-editing')) {
                    return;
                }

                requestActionAfterUnsavedCheck(function() {
                    beginInlineEdit($row, editableFields, config);
                });
            });

            $table.on('click', '.js-inline-cancel-btn', function() {
                var $row = $(this).closest('tr[data-row-id]');
                cancelInlineEdit($row);
            });

            $table.on('click', '.js-inline-save-btn', function() {
                var $row = $(this).closest('tr[data-row-id]');
                saveInlineEdit($row, editableFields, config);
            });

            $table.on('click', '.inline-dropdown-trigger, .inline-dropdown-label', function(event) {
                event.preventDefault();
                event.stopPropagation();

                var $shell = $(this).closest('.inline-dropdown-shell');
                if ($shell.length === 0) {
                    return;
                }

                if ($shell.hasClass('is-open')) {
                    $shell.removeClass('is-open');
                    return;
                }

                openInlineDropdownShell($table, $shell);
            });

            $table.on('click', '.inline-dropdown-option', function(event) {
                event.preventDefault();
                event.stopPropagation();

                var $option = $(this);
                var $shell = $option.closest('.inline-dropdown-shell');
                if ($shell.length === 0) {
                    return;
                }

                var value = String($option.attr('data-value') || '');
                var label = String($option.attr('data-label') || $option.text() || '');
                setInlineDropdownValue($shell, value, label);
                $shell.removeClass('is-open');
            });

            $table.on('keydown', '.js-inline-editor', function(event) {
                var $editor = $(this);
                var editorType = String($editor.attr('data-type') || 'string').toLowerCase();

                if (event.key === 'Escape') {
                    event.preventDefault();
                    cancelInlineEdit($editor.closest('tr[data-row-id]'));
                    return;
                }

                if (event.key === 'Enter' && !event.shiftKey) {
                    event.preventDefault();
                    saveInlineEdit($editor.closest('tr[data-row-id]'), editableFields, config);
                    return;
                }

                if (editorType === 'digits' && !isAllowedDigitKey(event)) {
                    event.preventDefault();
                }
            });

            $table.on('paste', '.js-inline-editor[data-type="digits"]', function(event) {
                var clipboard = event.originalEvent && event.originalEvent.clipboardData
                    ? event.originalEvent.clipboardData.getData('text')
                    : '';
                var digits = String(clipboard || '').replace(/\D+/g, '');
                if (digits === '') {
                    event.preventDefault();
                    return;
                }

                event.preventDefault();
                insertTextAtCursor(digits);
            });

            $(document).off('click.' + guardNamespace);
            $(document).on('click.' + guardNamespace, function(event) {
                var $activeRow = getActiveEditingRow($table);
                if (!$activeRow.length) {
                    return;
                }

                var $target = $(event.target);
                if ($target.closest($activeRow).length > 0) {
                    if ($target.closest('.inline-dropdown-shell').length === 0 && $target.closest('.inline-dropdown-menu').length === 0) {
                        $table.find('.inline-dropdown-shell.is-open').removeClass('is-open');
                    }
                    return;
                }

                var $interactive = $target.closest('a, button, input, select, textarea, label');
                if ($interactive.length === 0) {
                    return;
                }

                if ($interactive.closest('.modal').length > 0 || $interactive.closest('#crudUnsavedChangesModal').length > 0) {
                    return;
                }

                if ($interactive.is('.js-inline-save-btn, .js-inline-cancel-btn')) {
                    return;
                }

                if ($interactive.data('skipUnsavedGuard') === 1) {
                    $interactive.removeData('skipUnsavedGuard');
                    return;
                }

                if (!hasUnsavedChanges($activeRow, editableFields, config)) {
                    cancelInlineEdit($activeRow);
                    return;
                }

                event.preventDefault();
                event.stopImmediatePropagation();

                var $element = $interactive.first();
                openUnsavedChangesDialog(function() {
                    cancelInlineEdit($activeRow);
                    triggerDeferredInteraction($element);
                });
            });

            $(document).off('submit.' + guardNamespace);
            $(document).on('submit.' + guardNamespace, 'form', function(event) {
                var $activeRow = getActiveEditingRow($table);
                if (!$activeRow.length) {
                    return;
                }

                var $form = $(this);
                if ($form.closest($activeRow).length > 0 || $form.closest('.modal').length > 0) {
                    return;
                }

                if ($form.data('skipUnsavedGuard') === 1) {
                    $form.removeData('skipUnsavedGuard');
                    return;
                }

                if (!hasUnsavedChanges($activeRow, editableFields, config)) {
                    cancelInlineEdit($activeRow);
                    return;
                }

                event.preventDefault();
                var formElement = this;
                openUnsavedChangesDialog(function() {
                    cancelInlineEdit($activeRow);
                    $form.data('skipUnsavedGuard', 1);
                    if (typeof formElement.submit === 'function') {
                        formElement.submit();
                    }
                });
            });
        }

        function beginInlineEdit($row, editableFields, config) {
            var snapshot = {};
            var values = {};

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
                values[fieldMeta.field] = currentValue;

                $cell.html(buildInlineEditorHtml(fieldMeta, currentValue, config.placeholder || '____'));
            });

            var $actionsCell = findActionsCell($row);
            if ($actionsCell.length > 0) {
                $actionsCell.children().addClass('d-none js-inline-hidden-action');
                $actionsCell.append(
                    '<span class="js-inline-actions">' +
                        '<button type="button" class="btn btn-action btn-view js-inline-save-btn" title="Enregistrer"><i class="fas fa-check"></i></button>' +
                        '<button type="button" class="btn btn-action btn-delete js-inline-cancel-btn" title="Annuler"><i class="fas fa-times"></i></button>' +
                    '</span>'
                );
            }

            setRowSelectionAvailability($row, false);
            $row
                .addClass('js-inline-editing')
                .data('inlineSnapshot', snapshot)
                .data('inlineValues', values);
            focusInlineEditor($row.find('.js-inline-editor').first());
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
                var $input = $row.find('.js-inline-editor[data-field="' + fieldMeta.field + '"]');
                if ($input.length === 0) {
                    return;
                }

                var value = normalizeInlineEditorValue($input);
                if (fieldMeta.required && value === '') {
                    hasError = true;
                    $input.addClass('is-invalid');
                    return;
                }
                if (String(fieldMeta.type || '').toLowerCase() === 'digits' && value !== '' && !/^\d+$/.test(value)) {
                    hasError = true;
                    $input.addClass('is-invalid');
                    return;
                }

                $input.removeClass('is-invalid');
                payload[fieldMeta.field] = value;
            });

            if (hasError) {
                showToast('Merci de verifier les champs obligatoires et numeriques.', 'error');
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

                applyInlineSavedValues($row, editableFields, payload, config);
                showToast(response.message || 'Element mis a jour.', 'success');
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
            $actionsCell.find('.js-inline-hidden-action').removeClass('d-none js-inline-hidden-action');

            setRowSelectionAvailability($row, true);
            $row
                .removeClass('js-inline-editing js-inline-saving')
                .removeData('inlineSnapshot')
                .removeData('inlineValues');
        }

        function applyInlineSavedValues($row, editableFields, payload, config) {
            editableFields.forEach(function(fieldMeta) {
                var fieldName = String(fieldMeta.field || '').trim();
                if (fieldName === '') {
                    return;
                }

                var $cell = $row.find('td[data-field="' + fieldName + '"]').first();
                if ($cell.length === 0) {
                    return;
                }

                var value = String(payload[fieldName] || '').trim();
                $cell.attr('data-field-value', value);
                if (isSelectEditorType(fieldMeta.type)) {
                    var label = value;
                    var options = Array.isArray(fieldMeta.options) ? fieldMeta.options : [];
                    for (var i = 0; i < options.length; i += 1) {
                        var option = options[i] || {};
                        if (String(option.value || '').trim() === value) {
                            label = String(option.label || value).trim();
                            break;
                        }
                    }
                    $cell.text(label === '' ? String(config.placeholder || '____') : label);
                } else {
                    $cell.text(value === '' ? String(config.placeholder || '____') : value);
                }
            });

            var $actionsCell = findActionsCell($row);
            $actionsCell.find('.js-inline-actions').remove();
            $actionsCell.find('.js-inline-hidden-action').removeClass('d-none js-inline-hidden-action');

            setRowSelectionAvailability($row, true);
            $row
                .removeClass('js-inline-editing js-inline-saving')
                .removeData('inlineSnapshot')
                .removeData('inlineValues');
        }

        function normalizeInlineEditorValue($editor) {
            if ($editor.hasClass('inline-dropdown-shell')) {
                return String($editor.attr('data-value') || '').trim();
            }

            if ($editor.is('select')) {
                return String($editor.val() || '').trim();
            }

            if ($editor.is('input, textarea')) {
                return String($editor.val() || '').trim();
            }

            var placeholder = String($editor.attr('data-placeholder') || '____').trim();
            var value = String($editor.text() || '')
                .replace(/\u00a0/g, ' ')
                .replace(/\s+/g, ' ')
                .trim();

            if (value === placeholder) {
                return '';
            }

            return value;
        }

        function focusInlineEditor($editor) {
            if (!$editor || $editor.length === 0) {
                return;
            }

            if ($editor.hasClass('inline-dropdown-shell')) {
                openInlineDropdownShell($editor.closest('table'), $editor);
                $editor.find('.inline-dropdown-trigger').trigger('focus');
                return;
            }

            if ($editor.is('select, input, textarea')) {
                $editor.trigger('focus');
                return;
            }

            var node = $editor.get(0);
            if (!node) {
                return;
            }

            $editor.trigger('focus');

            var selection = window.getSelection ? window.getSelection() : null;
            if (!selection || !document.createRange) {
                return;
            }

            var range = document.createRange();
            range.selectNodeContents(node);
            range.collapse(false);
            selection.removeAllRanges();
            selection.addRange(range);
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
            var fieldMap = {};

            $table.find('thead th[data-field]').each(function() {
                var $th = $(this);
                var field = String($th.data('field') || '').trim();
                if (field === '') {
                    return;
                }

                var meta = {
                    field: field,
                    label: String($th.text() || field).trim(),
                    required: String($th.data('required') || '') === '1',
                    type: String($th.data('type') || 'string').trim()
                };

                if (isSelectEditorType(meta.type)) {
                    meta.options = collectFieldOptions($table, field, String($table.data('placeholder') || '____'));
                }

                fields.push(meta);
                fieldMap[field] = meta;
            });

            $table.find('tbody tr').first().find('td[data-field]').each(function() {
                var $cell = $(this);
                var field = String($cell.data('field') || '').trim();
                if (field === '' || fieldMap[field]) {
                    return;
                }

                var columnKey = String($cell.data('columnKey') || '').trim();
                var $header = columnKey !== '' ? $table.find('thead th[data-column-key="' + columnKey + '"]').first() : $();
                var inferredType = $header.length > 0 ? String($header.data('type') || 'string').trim() : 'string';
                var inferredRequired = $header.length > 0 ? String($header.data('required') || '') === '1' : false;
                var inferredLabel = $header.length > 0 ? String($header.text() || field).trim() : field;

                var inferredMeta = {
                    field: field,
                    label: inferredLabel,
                    required: inferredRequired,
                    type: inferredType
                };

                if (isSelectEditorType(inferredMeta.type)) {
                    inferredMeta.options = collectFieldOptions($table, field, String($table.data('placeholder') || '____'));
                }

                fields.push(inferredMeta);
                fieldMap[field] = inferredMeta;
            });

            return fields;
        }

        function buildInlineEditorHtml(fieldMeta, currentValue, placeholder) {
            var normalizedType = String(fieldMeta.type || 'string').toLowerCase();
            var escapedField = escapeHtml(String(fieldMeta.field || '').trim());
            var escapedType = escapeHtml(normalizedType);
            var safePlaceholder = String(placeholder || '____');

            if (isSelectEditorType(normalizedType)) {
                var options = Array.isArray(fieldMeta.options) ? fieldMeta.options.slice() : [];
                var optionMap = {};
                options.forEach(function(option) {
                    var key = String((option && option.value) || '').trim().toLowerCase();
                    if (key !== '') {
                        optionMap[key] = true;
                    }
                });

                var currentKey = String(currentValue || '').trim().toLowerCase();
                if (currentKey !== '' && !optionMap[currentKey]) {
                    options.push({ value: currentValue, label: currentValue });
                }

                var selectedValue = String(currentValue || '').trim();
                var selectedLabel = selectedValue;
                for (var i = 0; i < options.length; i += 1) {
                    var currentOption = options[i] || {};
                    if (String(currentOption.value || '').trim() === selectedValue) {
                        selectedLabel = String(currentOption.label || selectedValue).trim();
                        break;
                    }
                }

                var html =
                    '<div class="inline-dropdown-shell js-inline-editor" data-field="' + escapedField + '" data-type="' + escapedType + '" data-value="' + escapeHtml(selectedValue) + '">' +
                        '<span class="inline-dropdown-label">' + escapeHtml(selectedLabel === '' ? safePlaceholder : selectedLabel) + '</span>' +
                        '<button type="button" class="inline-dropdown-trigger" aria-label="Ouvrir la liste"><i class="fas fa-chevron-down"></i></button>' +
                        '<div class="inline-dropdown-menu">';

                var emptyActive = selectedValue === '' ? ' is-active' : '';
                html += '<button type="button" class="inline-dropdown-option' + emptyActive + '" data-value="" data-label="' + escapeHtml(safePlaceholder) + '">' + escapeHtml(safePlaceholder) + '</button>';
                options.forEach(function(option) {
                    var value = String((option && option.value) || '').trim();
                    var label = String((option && option.label) || value).trim();
                    if (value === '') {
                        return;
                    }
                    var isActiveClass = value === selectedValue ? ' is-active' : '';
                    html += '<button type="button" class="inline-dropdown-option' + isActiveClass + '" data-value="' + escapeHtml(value) + '" data-label="' + escapeHtml(label) + '">' + escapeHtml(label) + '</button>';
                });

                html += '</div></div>';
                return html;
            }

            return '<span class="js-inline-editor" contenteditable="true" spellcheck="false" data-field="' + escapedField + '" data-type="' + escapedType + '" data-placeholder="' + escapeHtml(safePlaceholder) + '">' +
                escapeHtml(currentValue) +
            '</span>';
        }

        function isSelectEditorType(type) {
            var normalized = String(type || '').toLowerCase().trim();
            return normalized === 'entity' || normalized === 'enum' || normalized === 'select';
        }

        function collectFieldOptions($table, fieldName, placeholder) {
            var options = [];
            var valueMap = {};
            var ph = String(placeholder || '____').trim().toLowerCase();

            $table.find('tbody td[data-field="' + fieldName + '"]').each(function() {
                var $cell = $(this);
                var rawValue = String($cell.attr('data-field-value') || '').trim();
                var label = String($cell.text() || '').replace(/\s+/g, ' ').trim();

                if (rawValue === '') {
                    rawValue = normalizeDisplayValue(label, placeholder);
                }

                if (label === '' || label.toLowerCase() === ph) {
                    label = rawValue;
                }

                var normalized = String(rawValue || '').trim();
                if (normalized === '' || normalized.toLowerCase() === ph) {
                    return;
                }

                var key = normalized.toLowerCase();
                if (valueMap[key]) {
                    return;
                }
                valueMap[key] = true;
                options.push({ value: normalized, label: label || normalized });
            });

            options.sort(function(a, b) {
                return String(a.label || '').localeCompare(String(b.label || ''), 'fr');
            });

            return options;
        }

        function isInlineEditEnabled($table) {
            var rawAttr = String($table.attr('data-enable-inline-edit') || '').trim();
            if (rawAttr === '') {
                return true;
            }

            var normalized = rawAttr.toLowerCase();
            if (normalized === '0' || normalized === 'false' || normalized === 'off' || normalized === 'no') {
                return false;
            }
            return true;
        }

        function openInlineDropdownShell($table, $shell) {
            if (!$shell || $shell.length === 0) {
                return;
            }

            var $ctxTable = $table && $table.length ? $table : $shell.closest('table');
            if ($ctxTable && $ctxTable.length) {
                $ctxTable.find('.inline-dropdown-shell.is-open').not($shell).removeClass('is-open');
            }
            $shell.addClass('is-open');
        }

        function setInlineDropdownValue($shell, value, label) {
            if (!$shell || $shell.length === 0) {
                return;
            }

            var normalizedValue = String(value || '');
            var normalizedLabel = String(label || '');
            $shell.attr('data-value', normalizedValue);
            $shell.find('.inline-dropdown-label').text(normalizedLabel);
            $shell.find('.inline-dropdown-option').removeClass('is-active');
            $shell.find('.inline-dropdown-option').filter(function() {
                return String($(this).attr('data-value') || '') === normalizedValue;
            }).first().addClass('is-active');
        }

        function setupQuickCreateTrigger($table, config) {
            var editableFields = getEditableFields($table);
            if (editableFields.length === 0) {
                return;
            }

            var $actionBar = resolveActionBar($table);
            var $actionBarButtons = $actionBar.find('.action-buttons').first();
            if ($actionBarButtons.length === 0) {
                return;
            }

            var $existingQuickButton = findQuickCreateButton($actionBarButtons);
            if ($existingQuickButton.length > 0) {
                bindQuickCreateButton($existingQuickButton, config, editableFields);
                return;
            }

            var $button = $(
                '<button type="button" class="btn btn-sm btn-outline-primary ml-2 no-loading js-open-quick-create" data-resource="' + config.resource + '">' +
                    '<i class="fas fa-bolt mr-1"></i>Creation rapide' +
                '</button>'
            );
            $actionBarButtons.append($button);
            bindQuickCreateButton($button, config, editableFields);
        }

        function openQuickCreateModal(config, editableFields) {
            var $modal = $('#crudQuickCreateModal');
            var $title = $modal.find('.modal-title');
            var $fieldsWrap = $modal.find('.js-quick-create-fields');
            var $submit = $modal.find('.js-quick-create-submit');

            $title.text('Creation rapide');
            $fieldsWrap.empty();

            editableFields.forEach(function(fieldMeta) {
                var normalizedType = String(fieldMeta.type || 'string').toLowerCase();
                var inputType = (normalizedType === 'int' || normalizedType === 'number' || normalizedType === 'float') ? 'number' : 'text';
                var requiredHtml = fieldMeta.required ? ' <span class="text-danger">*</span>' : '';
                var requiredAttr = fieldMeta.required ? ' required' : '';
                var extraAttrs = '';
                if (normalizedType === 'digits') {
                    extraAttrs = ' inputmode="numeric" pattern="[0-9]*"';
                } else if (normalizedType === 'float') {
                    extraAttrs = ' step="any"';
                }

                var controlHtml = '';
                if (isSelectEditorType(normalizedType)) {
                    controlHtml = '<select class="form-control js-quick-create-input" data-field="' + escapeHtml(fieldMeta.field) + '" data-type="' + escapeHtml(normalizedType) + '"' + requiredAttr + '>' +
                        '<option value="">' + escapeHtml(config.placeholder || '____') + '</option>';

                    var selectOptions = Array.isArray(fieldMeta.options) ? fieldMeta.options : [];
                    selectOptions.forEach(function(option) {
                        var optionValue = String((option && option.value) || '').trim();
                        if (optionValue === '') {
                            return;
                        }
                        var optionLabel = String((option && option.label) || optionValue).trim();
                        controlHtml += '<option value="' + escapeHtml(optionValue) + '">' + escapeHtml(optionLabel) + '</option>';
                    });
                    controlHtml += '</select>';
                } else {
                    controlHtml = '<input type="' + inputType + '" class="form-control js-quick-create-input" data-field="' + escapeHtml(fieldMeta.field) + '" data-type="' + escapeHtml(normalizedType) + '"' + requiredAttr + extraAttrs + '>';
                }

                var fieldHtml =
                    '<div class="form-group">' +
                        '<label>' + escapeHtml(fieldMeta.label) + requiredHtml + '</label>' +
                        controlHtml +
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
                    var inputType = String($input.data('type') || 'string').toLowerCase();

                    if (required && value === '') {
                        $input.addClass('is-invalid');
                        hasError = true;
                        return;
                    }

                    if (inputType === 'digits' && value !== '' && !/^\d+$/.test(value)) {
                        $input.addClass('is-invalid');
                        hasError = true;
                        return;
                    }

                    if ((inputType === 'int' || inputType === 'number') && value !== '' && !/^-?\d+$/.test(value)) {
                        $input.addClass('is-invalid');
                        hasError = true;
                        return;
                    }

                    if (inputType === 'float' && value !== '' && !/^-?\d+(?:[.,]\d+)?$/.test(value)) {
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

            $modal.off('hidden.bs.modal.crudQuickCreate').on('hidden.bs.modal.crudQuickCreate', function() {
                $submit.prop('disabled', false);
            });

            $modal.modal('show');
            window.setTimeout(function() {
                $fieldsWrap.find('input').first().trigger('focus');
            }, 120);
        }

        function setupImportTrigger($table, config) {
            var $actionBar = resolveActionBar($table);
            var $actionBarButtons = $actionBar.find('.action-buttons').first();
            if ($actionBarButtons.length === 0) {
                return;
            }

            var $existingImportButton = findImportButton($actionBarButtons);
            if ($existingImportButton.length > 0) {
                bindImportButton($existingImportButton, config);
                return;
            }

            var $button = $(
                '<button type="button" class="btn btn-sm btn-outline-primary ml-2 no-loading js-open-import" data-resource="' + config.resource + '">' +
                    '<i class="fas fa-file-import mr-1"></i>Importer' +
                '</button>'
            );
            $actionBarButtons.append($button);
            bindImportButton($button, config);
        }

        function resolveActionBar($table) {
            return $table.closest('.table-container').prevAll('.action-bar').first();
        }

        function findQuickCreateButton($actionBarButtons) {
            var $explicitQuickButton = $actionBarButtons.find('[data-crud-action="quick-create"]').first();
            if ($explicitQuickButton.length > 0) {
                return $explicitQuickButton;
            }

            return $actionBarButtons.find('.btn-add').first();
        }

        function findImportButton($actionBarButtons) {
            var $explicitImportButton = $actionBarButtons.find('[data-crud-action="import"]').first();
            if ($explicitImportButton.length > 0) {
                return $explicitImportButton;
            }

            return $actionBarButtons.find('a, button').filter(function() {
                return $(this).find('.fa-file-import').length > 0;
            }).first();
        }

        function bindQuickCreateButton($button, config, editableFields) {
            if (!$button || $button.length === 0) {
                return;
            }

            if ($button.attr('data-crud-quick-bound') === '1') {
                return;
            }

            $button
                .attr('data-crud-quick-bound', '1')
                .attr('data-crud-action', 'quick-create')
                .removeAttr('data-toggle')
                .removeAttr('data-target')
                .off('click.crudQuickCreate')
                .on('click.crudQuickCreate', function(event) {
                    event.preventDefault();
                    openQuickCreateModal(config, editableFields);
                });
        }

        function bindImportButton($button, config) {
            if (!$button || $button.length === 0) {
                return;
            }

            if ($button.attr('data-crud-import-bound') === '1') {
                return;
            }

            $button
                .attr('data-crud-import-bound', '1')
                .attr('data-crud-action', 'import')
                .removeAttr('data-toggle')
                .removeAttr('data-target')
                .off('click.crudImport')
                .on('click.crudImport', function(event) {
                    event.preventDefault();
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

    function getActiveEditingRow($table) {
        if (!$table || $table.length === 0) {
            return $();
        }

        return $table.find('tbody tr.js-inline-editing').first();
    }

    function hasUnsavedChanges($row, editableFields, config) {
        if (!$row || $row.length === 0 || !$row.hasClass('js-inline-editing')) {
            return false;
        }

        var snapshot = $row.data('inlineValues') || {};
        var fields = Array.isArray(editableFields) ? editableFields : [];
        if (fields.length === 0) {
            fields = Object.keys(snapshot).map(function(fieldName) {
                return { field: fieldName };
            });
        }

        for (var i = 0; i < fields.length; i += 1) {
            var fieldMeta = fields[i] || {};
            var fieldName = String(fieldMeta.field || '').trim();
            if (fieldName === '') {
                continue;
            }

            var $editor = $row.find('.js-inline-editor[data-field="' + fieldName + '"]').first();
            if ($editor.length === 0) {
                continue;
            }

            var currentValue = normalizeInlineEditorValue($editor);
            var originalValue = String(snapshot[fieldName] || '');
            if (String(currentValue) !== String(originalValue)) {
                return true;
            }
        }

        return false;
    }

    function setRowSelectionAvailability($row, enabled) {
        if (!$row || $row.length === 0) {
            return;
        }

        var $table = $row.closest('table');
        var $checkbox = $row.find('.js-row-selector, [data-row-checkbox]').first();
        if ($checkbox.length === 0) {
            return;
        }

        if (!enabled) {
            $checkbox.prop('checked', false);
            $checkbox.prop('disabled', true);
            $row.removeClass('table-active is-selected');
        } else {
            $checkbox.prop('disabled', false);
        }

        var $bulkBar = buildOrGetBulkBar($table, resolveTableId($table, String($table.data('crudResource') || '')));
        refreshBulkState($table, $bulkBar);
    }

    function isAllowedDigitKey(event) {
        if (!event) {
            return true;
        }

        if (event.ctrlKey || event.metaKey || event.altKey) {
            return true;
        }

        var key = String(event.key || '');
        if (key === '') {
            return true;
        }

        var allowedKeys = [
            'Backspace',
            'Delete',
            'ArrowLeft',
            'ArrowRight',
            'ArrowUp',
            'ArrowDown',
            'Home',
            'End',
            'Tab',
            'Escape',
            'Enter'
        ];
        if (allowedKeys.indexOf(key) >= 0) {
            return true;
        }

        return /^[0-9]$/.test(key);
    }

    function insertTextAtCursor(text) {
        var safeText = String(text || '');
        if (safeText === '') {
            return;
        }

        if (document.queryCommandSupported && document.queryCommandSupported('insertText')) {
            document.execCommand('insertText', false, safeText);
            return;
        }

        var selection = window.getSelection ? window.getSelection() : null;
        if (!selection || selection.rangeCount === 0) {
            return;
        }

        selection.deleteFromDocument();
        selection.getRangeAt(0).insertNode(document.createTextNode(safeText));
        selection.collapseToEnd();
    }

    function sanitizeEventNamespace(value) {
        return String(value || 'crudInline')
            .replace(/[^a-zA-Z0-9_]/g, '_')
            .replace(/^_+/, '')
            .replace(/_+$/, '') || 'crudInline';
    }

    function triggerDeferredInteraction($element) {
        if (!$element || $element.length === 0) {
            return;
        }

        var $target = $element.first();
        var node = $target.get(0);

        $target.data('skipUnsavedGuard', 1);

        if (node && typeof node.click === 'function') {
            node.click();
            return;
        }

        $target.trigger('click');
    }

    function ensureUnsavedChangesModal() {
        if ($('#crudUnsavedChangesModal').length > 0) {
            return;
        }

        $('body').append(
            '<div class="modal fade unsaved-changes-modal" id="crudUnsavedChangesModal" tabindex="-1" role="dialog" aria-hidden="true">' +
                '<div class="modal-dialog modal-dialog-centered" role="document">' +
                    '<div class="modal-content">' +
                        '<div class="modal-header">' +
                            '<h5 class="modal-title">Confirmer l action</h5>' +
                            '<button type="button" class="close" data-dismiss="modal" aria-label="Fermer">' +
                                '<span aria-hidden="true">&times;</span>' +
                            '</button>' +
                        '</div>' +
                        '<div class="modal-body">' +
                            '<p class="mb-0" id="crudUnsavedChangesMessage">Des modifications non enregistrees seront perdues. Continuer ?</p>' +
                        '</div>' +
                        '<div class="modal-footer">' +
                            '<button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>' +
                            '<button type="button" class="btn btn-primary" id="crudUnsavedChangesConfirmBtn">Continuer</button>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>'
        );
    }

    function bindUnsavedChangesConfirmHandler() {
        $(document).off('click.crudUnsavedConfirm', '#crudUnsavedChangesConfirmBtn');
        $(document).on('click.crudUnsavedConfirm', '#crudUnsavedChangesConfirmBtn', function(event) {
            event.preventDefault();

            var action = crudUnsavedConfirmAction;
            crudUnsavedConfirmAction = null;

            var $modal = $('#crudUnsavedChangesModal');
            if ($modal.length > 0) {
                $modal.modal('hide');
            }

            if (typeof action === 'function') {
                action();
            }
        });

        $('#crudUnsavedChangesModal').off('hidden.bs.modal.crudUnsavedConfirm');
        $('#crudUnsavedChangesModal').on('hidden.bs.modal.crudUnsavedConfirm', function() {
            crudUnsavedConfirmAction = null;
        });
    }

    function openUnsavedChangesDialog(onConfirm, message) {
        var $modal = $('#crudUnsavedChangesModal');
        var $message = $('#crudUnsavedChangesMessage');
        if ($modal.length === 0) {
            if (window.confirm(String(message || 'Des modifications non enregistrees seront perdues. Continuer ?'))) {
                if (typeof onConfirm === 'function') {
                    onConfirm();
                }
            }
            return;
        }

        crudUnsavedConfirmAction = typeof onConfirm === 'function' ? onConfirm : null;
        if ($message.length > 0) {
            $message.text(String(message || 'Des modifications non enregistrees seront perdues. Continuer ?'));
        }
        $modal.modal('show');
    }

    function ensureCrudEnhancerModals() {
        if ($('#crudQuickCreateModal').length === 0) {
            $('body').append(
                '<div class="modal fade crud-quick-modal" id="crudQuickCreateModal" tabindex="-1" role="dialog" aria-hidden="true">' +
                    '<div class="modal-dialog modal-dialog-centered" role="document">' +
                        '<div class="modal-content border-0 shadow-lg">' +
                            '<button type="button" class="close crud-quick-modal-close" data-dismiss="modal" aria-label="Close">' +
                                '<span aria-hidden="true">&times;</span>' +
                            '</button>' +
                            '<div class="crud-quick-modal-body">' +
                                '<div class="crud-quick-modal-badge">' +
                                    '<i class="fas fa-plus"></i>' +
                                '</div>' +
                                '<h3 class="crud-quick-modal-title modal-title">Creation rapide</h3>' +
                                '<p class="crud-quick-modal-subtitle">Renseignez uniquement les champs requis.</p>' +
                                '<div class="js-quick-create-fields"></div>' +
                                '<div class="crud-quick-modal-actions">' +
                                    '<button type="button" class="btn crud-quick-cancel" data-dismiss="modal">Annuler</button>' +
                                    '<button type="button" class="btn crud-quick-submit js-quick-create-submit">' +
                                        '<i class="fas fa-check mr-1"></i>Enregistrer' +
                                    '</button>' +
                                '</div>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>'
            );
        }

        if ($('#crudImportModal').length === 0) {
            $('body').append(
                '<div class="modal fade crud-quick-modal" id="crudImportModal" tabindex="-1" role="dialog" aria-hidden="true">' +
                    '<div class="modal-dialog modal-dialog-centered" role="document">' +
                        '<div class="modal-content border-0 shadow-lg">' +
                            '<button type="button" class="close crud-quick-modal-close" data-dismiss="modal" aria-label="Close">' +
                                '<span aria-hidden="true">&times;</span>' +
                            '</button>' +
                            '<div class="crud-quick-modal-body">' +
                                '<div class="crud-quick-modal-badge">' +
                                    '<i class="fas fa-file-import"></i>' +
                                '</div>' +
                                '<h3 class="crud-quick-modal-title modal-title">Importer des donnees</h3>' +
                                '<p class="crud-quick-modal-subtitle">Fichiers supportes: CSV, XLS, XLSX.</p>' +
                                '<div class="form-group mb-0">' +
                                    '<label>Fichier</label>' +
                                    '<input type="file" name="file" class="form-control-file" accept=".csv,.txt,.xls,.xlsx">' +
                                '</div>' +
                                '<div class="crud-quick-modal-actions">' +
                                    '<button type="button" class="btn crud-quick-cancel" data-dismiss="modal">Annuler</button>' +
                                    '<button type="button" class="btn crud-quick-submit js-import-submit">' +
                                        '<i class="fas fa-upload mr-1"></i>Importer' +
                                    '</button>' +
                                '</div>' +
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
