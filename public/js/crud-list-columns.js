/**
 * CRUD List Columns - Shared column toggle and resize system
 * Works with .js-crud-list-table tables and .crud-column-settings-floating gear buttons.
 */
(function($) {
    'use strict';

    if (!$) {
        return;
    }

    var isResizing = false;
    var $resizeTh = null;
    var resizeStartX = 0;
    var resizeStartWidth = 0;
    var resizeTableId = '';
    var globalHandlersBound = false;

    function init() {
        initColumnMenus();
        initColumnResizing();
        applyColumnPreferences();
        applyColumnWidths();

        if (!globalHandlersBound) {
            bindGlobalMenuHandlers();
            bindColumnToggleHandler();
            globalHandlersBound = true;
        }
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function getStorageKey(tableId, suffix) {
        return 'crud_columns_' + tableId + '_' + suffix;
    }

    function getColumnPrefs(tableId) {
        try {
            return JSON.parse(localStorage.getItem(getStorageKey(tableId, 'visible')) || '{}');
        } catch (e) {
            return {};
        }
    }

    function setColumnPref(tableId, key, visible) {
        var prefs = getColumnPrefs(tableId);
        prefs[key] = visible;
        localStorage.setItem(getStorageKey(tableId, 'visible'), JSON.stringify(prefs));
    }

    function initColumnMenus() {
        $('table.js-crud-list-table[data-table-id]').each(function() {
            var $table = $(this);
            var tableId = String($table.data('tableId') || '').trim();
            if (tableId === '') {
                return;
            }

            var $container = $table.closest('.table-container');
            var $floating = $container.find('.crud-column-settings-floating').first();
            if ($floating.length === 0) {
                return;
            }

            var $trigger = $floating.find('.crud-column-settings__trigger').first();
            if ($trigger.length === 0) {
                return;
            }

            var $menu = $floating.find('.crud-column-settings__menu').first();
            if ($menu.length === 0) {
                $menu = $('<div class="crud-column-settings__menu"></div>');
                $floating.append($menu);
            }

            $floating.attr('data-table-id', tableId);
            $menu.attr('data-table-id', tableId);

            if ($menu.find('[data-column-toggle]').length === 0) {
                generateMenuItems($table, tableId, $menu);
            } else {
                syncMenuState($table, tableId, $menu);
            }
        });
    }

    function generateMenuItems($table, tableId, $menu) {
        var prefs = getColumnPrefs(tableId);
        var staticItems = [];
        var toggleItems = [];
        var idToggleId = sanitizeDomId(tableId + '_column_id');

        if ($table.find('thead th[data-column-key="id"]').length > 0) {
            staticItems.push(
                '<div class="crud-column-settings__item is-static">' +
                    '<span>ID</span>' +
                    '<small>Obligatoire</small>' +
                    '<input type="checkbox" id="' + escapeHtml(idToggleId) + '" checked disabled>' +
                '</div>'
            );
        }

        $table.find('thead tr th[data-column-key]').each(function() {
            var $th = $(this);
            var key = String($th.data('columnKey') || '').trim();
            if (!key || key === 'selector' || key === 'actions' || key === 'id') {
                return;
            }

            var label = String($th.text() || '').replace(/\s+/g, ' ').trim();
            if (label === '') {
                label = key;
            }

            var isVisible = prefs.hasOwnProperty(key) ? !!prefs[key] : $th.is(':visible');
            var inputId = sanitizeDomId(tableId + '_column_' + key);

            toggleItems.push(
                '<label class="crud-column-settings__item" for="' + escapeHtml(inputId) + '">' +
                    '<span>' + escapeHtml(label) + '</span>' +
                    '<input type="checkbox" id="' + escapeHtml(inputId) + '" data-column-toggle="' + escapeHtml(key) + '" data-table-id="' + escapeHtml(tableId) + '"' + (isVisible ? ' checked' : '') + '>' +
                '</label>'
            );
        });

        var html = '<div class="crud-column-settings__title">Colonnes</div>';

        if (staticItems.length > 0) {
            html += '<div class="crud-column-settings__group">' + staticItems.join('') + '</div>';
        }

        if (toggleItems.length > 0) {
            if (staticItems.length > 0) {
                html += '<div class="crud-column-settings__divider"></div>';
            }
            html += '<div class="crud-column-settings__group">' + toggleItems.join('') + '</div>';
        } else {
            html += '<div class="crud-column-settings__empty">Aucune colonne configurable</div>';
        }

        $menu.html(html);
    }

    function syncMenuState($table, tableId, $menu) {
        var prefs = getColumnPrefs(tableId);

        $menu.find('[data-column-toggle]').each(function() {
            var $input = $(this);
            var key = String($input.data('columnToggle') || '').trim();
            if (key === '') {
                return;
            }

            var visible = prefs.hasOwnProperty(key)
                ? !!prefs[key]
                : $table.find('thead th[data-column-key="' + key + '"]').is(':visible');

            $input.prop('checked', visible);
        });
    }

    function bindGlobalMenuHandlers() {
        $(document).on('click.crudColumnMenu', '.crud-column-settings__trigger', function(event) {
            event.preventDefault();
            event.stopPropagation();

            var $floating = $(this).closest('.crud-column-settings-floating');
            if ($floating.length === 0) {
                return;
            }

            var isOpen = $floating.hasClass('is-open');
            closeAllMenus();

            if (!isOpen) {
                $floating.addClass('is-open');
                $(this).attr('aria-expanded', 'true');
            }
        });

        $(document).on('click.crudColumnMenu', function(event) {
            if ($(event.target).closest('.crud-column-settings-floating').length > 0) {
                return;
            }
            closeAllMenus();
        });

        $(document).on('keydown.crudColumnMenu', function(event) {
            if (event.key === 'Escape') {
                closeAllMenus();
            }
        });
    }

    function closeAllMenus() {
        $('.crud-column-settings-floating.is-open').removeClass('is-open');
        $('.crud-column-settings__trigger[aria-expanded="true"]').attr('aria-expanded', 'false');
    }

    function bindColumnToggleHandler() {
        $(document).on('change.crudColumnToggle', '.crud-column-settings__menu [data-column-toggle]', function(event) {
            event.stopPropagation();

            var $input = $(this);
            var key = String($input.data('columnToggle') || '').trim();
            var tableId = String($input.data('tableId') || '').trim();
            var visible = $input.is(':checked');

            if (key === '' || tableId === '') {
                return;
            }

            var $table = $('table[data-table-id="' + tableId + '"]').first();
            if ($table.length === 0) {
                return;
            }

            toggleColumn($table, key, visible);
            setColumnPref(tableId, key, visible);
        });
    }

    function applyColumnPreferences() {
        $('table.js-crud-list-table[data-table-id]').each(function() {
            var $table = $(this);
            var tableId = String($table.data('tableId') || '').trim();
            if (tableId === '') {
                return;
            }

            var prefs = getColumnPrefs(tableId);
            Object.keys(prefs).forEach(function(key) {
                if (key === 'id') {
                    return;
                }
                toggleColumn($table, key, !!prefs[key]);
            });
        });
    }

    function toggleColumn($table, key, visible) {
        if (!$table || $table.length === 0 || key === '' || key === 'selector' || key === 'actions') {
            return;
        }

        if (key === 'id') {
            visible = true;
        }

        var $ths = $table.find('th[data-column-key="' + key + '"]');
        var $tds = $table.find('td[data-column-key="' + key + '"]');

        if (visible) {
            $ths.show();
            $tds.show();
        } else {
            $ths.hide();
            $tds.hide();
        }
    }

    function initColumnResizing() {
        $('table.js-crud-list-table[data-table-id] thead tr th').each(function() {
            var $th = $(this);
            if ($th.find('.column-resize-handle').length > 0) {
                return;
            }

            var key = String($th.data('columnKey') || '').trim();
            if (!key || key === 'selector' || key === 'actions') {
                return;
            }

            $th.css('position', 'relative');
            $th.append('<div class="column-resize-handle"></div>');
        });
    }

    function getColumnWidths(tableId) {
        try {
            return JSON.parse(localStorage.getItem(getStorageKey(tableId, 'widths')) || '{}');
        } catch (e) {
            return {};
        }
    }

    function setColumnWidth(tableId, key, width) {
        var widths = getColumnWidths(tableId);
        widths[key] = width;
        localStorage.setItem(getStorageKey(tableId, 'widths'), JSON.stringify(widths));
    }

    function applyColumnWidths() {
        $('table.js-crud-list-table[data-table-id]').each(function() {
            var $table = $(this);
            var tableId = String($table.data('tableId') || '').trim();
            if (!tableId) {
                return;
            }

            var widths = getColumnWidths(tableId);
            Object.keys(widths).forEach(function(key) {
                var width = parseFloat(widths[key]);
                if (isNaN(width) || width <= 0) {
                    return;
                }
                $table.find('th[data-column-key="' + key + '"]').css({ width: width + 'px', minWidth: width + 'px' });
            });
        });
    }

    $(document).on('mousedown', '.column-resize-handle', function(event) {
        event.preventDefault();
        event.stopPropagation();

        isResizing = true;
        $resizeTh = $(this).closest('th');
        resizeStartX = event.pageX;
        resizeStartWidth = $resizeTh.outerWidth();
        resizeTableId = String($resizeTh.closest('table').data('tableId') || '').trim();

        $('body').css('cursor', 'col-resize').addClass('is-resizing-column');
    });

    $(document).on('mousemove', function(event) {
        if (!isResizing || !$resizeTh) {
            return;
        }

        var newWidth = resizeStartWidth + (event.pageX - resizeStartX);
        if (newWidth < 40) {
            newWidth = 40;
        }

        $resizeTh.css({ width: newWidth + 'px', minWidth: newWidth + 'px' });
    });

    $(document).on('mouseup', function() {
        if (!isResizing || !$resizeTh) {
            return;
        }

        var finalWidth = $resizeTh.outerWidth();
        var key = String($resizeTh.data('columnKey') || '').trim();
        if (key && resizeTableId) {
            setColumnWidth(resizeTableId, key, finalWidth);
        }

        isResizing = false;
        $resizeTh = null;
        resizeTableId = '';
        $('body').css('cursor', '').removeClass('is-resizing-column');
    });

    function sanitizeDomId(value) {
        return String(value || '')
            .replace(/[^a-zA-Z0-9\-_:.]/g, '_')
            .replace(/^_+/, '')
            .replace(/_+$/, '');
    }

    $(init);
    document.addEventListener('turbo:load', init);
    document.addEventListener('turbo:render', init);

})(window.jQuery);
