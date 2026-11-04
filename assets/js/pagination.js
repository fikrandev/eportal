/**
 * Global Table Pagination System for E-Portal
 * Automatically adds professional pagination controls to any HTML table in the application.
 */

(function(window, $) {
    'use strict';

    if (!$) return;

    /**
     * Apply pagination to a specific table element
     */
    function applyTablePagination(table) {
        if (!table) return;
        const $table = $(table);

        // Exclude special tables that shouldn't be paginated (e.g. calendar, print views, explicitly disabled)
        if ($table.hasClass('no-pagination') || 
            $table.parents('.calendar, .no-pagination-container, .print-only, .ttd-wrapper, .kop-wrapper').length > 0) {
            return;
        }

        const $tbody = $table.find('tbody');
        if ($tbody.length === 0) return;

        // Valid rows: exclude no-row-data, empty-row, and rows marked as search-filtered-out
        const $allRows = $tbody.find('> tr').not('.no-row-data, .empty-row, .no-paginate, .pf-empty, .sp-empty');
        const $rows = $allRows.filter(function() {
            return $(this).attr('data-search-hidden') !== 'true';
        });
        const totalRows = $rows.length;

        // Determine insertion anchor for pagination wrapper (after table or its .table-responsive wrapper)
        let $targetInsert = $table;
        if ($table.parent().hasClass('table-responsive') || $table.parent().hasClass('sp-table-responsive')) {
            $targetInsert = $table.parent();
        }

        // Generate or get stable table ID
        let tableId = $table.attr('id');
        if (!tableId) {
            tableId = 'tbl_pg_' + Math.random().toString(36).substring(2, 9);
            $table.attr('id', tableId);
        }

        // Find existing wrapper associated with this table
        let $wrapper = $targetInsert.next(`.table-pagination-wrapper[data-for="${tableId}"]`);
        if ($wrapper.length === 0) {
            $wrapper = $targetInsert.next('.table-pagination-wrapper');
        }

        // If no rows, clean up pagination controls
        if (totalRows === 0) {
            if ($wrapper.length) $wrapper.remove();
            $table.removeAttr('data-paginated');
            $allRows.show();
            return;
        }

        // Read page size & current page state
        let pageSize = parseInt($table.attr('data-page-size')) || 10;
        let currentPage = parseInt($table.attr('data-current-page')) || 1;

        // Calculate total pages
        let totalPages = Math.ceil(totalRows / pageSize);
        if (totalPages < 1) totalPages = 1;
        if (currentPage > totalPages) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;

        // Save state on table element
        $table.attr('data-page-size', pageSize);
        $table.attr('data-current-page', currentPage);
        $table.attr('data-row-count', totalRows);
        $table.attr('data-paginated', 'true');

        // Apply page slice display
        const startIndex = (currentPage - 1) * pageSize;
        const endIndex = startIndex + pageSize;

        $rows.each(function(index) {
            if (index >= startIndex && index < endIndex) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });

        // Hide search-hidden rows
        $allRows.filter('[data-search-hidden="true"]').hide();

        // Calculate visible items range label
        const displayStart = totalRows === 0 ? 0 : startIndex + 1;
        const displayEnd = Math.min(endIndex, totalRows);

        // Generate pagination numbered buttons
        let pageButtonsHtml = '';
        const maxVisibleButtons = 5;
        let startPage = Math.max(1, currentPage - 2);
        let endPage = Math.min(totalPages, startPage + maxVisibleButtons - 1);

        if (endPage - startPage < maxVisibleButtons - 1) {
            startPage = Math.max(1, endPage - maxVisibleButtons + 1);
        }

        if (startPage > 1) {
            pageButtonsHtml += `<button type="button" class="table-page-number-btn" data-page="1">1</button>`;
            if (startPage > 2) {
                pageButtonsHtml += `<span class="table-page-ellipsis">&hellip;</span>`;
            }
        }

        for (let p = startPage; p <= endPage; p++) {
            pageButtonsHtml += `<button type="button" class="table-page-number-btn ${p === currentPage ? 'active' : ''}" data-page="${p}">${p}</button>`;
        }

        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                pageButtonsHtml += `<span class="table-page-ellipsis">&hellip;</span>`;
            }
            pageButtonsHtml += `<button type="button" class="table-page-number-btn" data-page="${totalPages}">${totalPages}</button>`;
        }

        // Render wrapper element
        if ($wrapper.length === 0) {
            $wrapper = $(`<div class="table-pagination-wrapper" data-for="${tableId}"></div>`);
            $targetInsert.after($wrapper);
        } else {
            $wrapper.attr('data-for', tableId);
        }

        $wrapper.html(`
            <div class="table-pagination-left">
                <span>Tampilkan</span>
                <select class="table-page-size-select" aria-label="Jumlah baris per halaman">
                    <option value="10" ${pageSize === 10 ? 'selected' : ''}>10</option>
                    <option value="25" ${pageSize === 25 ? 'selected' : ''}>25</option>
                    <option value="50" ${pageSize === 50 ? 'selected' : ''}>50</option>
                    <option value="100" ${pageSize === 100 ? 'selected' : ''}>100</option>
                </select>
                <span>baris &bull; Menampilkan <strong>${displayStart}</strong>&ndash;<strong>${displayEnd}</strong> dari <strong>${totalRows}</strong> data</span>
            </div>
            <div class="table-pagination-right">
                <button type="button" class="table-page-btn btn-prev" ${currentPage === 1 ? 'disabled' : ''} title="Halaman Sebelumnya">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:middle;margin-right:4px;"><polyline points="15 18 9 12 15 6"></polyline></svg>Sebelumnya
                </button>
                <div class="table-page-numbers">
                    ${pageButtonsHtml}
                </div>
                <button type="button" class="table-page-btn btn-next" ${currentPage === totalPages ? 'disabled' : ''} title="Halaman Berikutnya">
                    Berikutnya<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:middle;margin-left:4px;"><polyline points="9 18 15 12 9 6"></polyline></svg>
                </button>
            </div>
        `);

        // Event: Change Page Size
        $wrapper.find('.table-page-size-select').off('change').on('change', function() {
            const newSize = parseInt($(this).val()) || 10;
            $table.attr('data-page-size', newSize);
            $table.attr('data-current-page', 1);
            applyTablePagination(table);
        });

        // Event: Previous Page
        $wrapper.find('.btn-prev').off('click').on('click', function() {
            if (currentPage > 1) {
                $table.attr('data-current-page', currentPage - 1);
                applyTablePagination(table);
                scrollTableIntoView($table);
            }
        });

        // Event: Next Page
        $wrapper.find('.btn-next').off('click').on('click', function() {
            if (currentPage < totalPages) {
                $table.attr('data-current-page', currentPage + 1);
                applyTablePagination(table);
                scrollTableIntoView($table);
            }
        });

        // Event: Numbered Buttons
        $wrapper.find('.table-page-number-btn').off('click').on('click', function() {
            const pageNum = parseInt($(this).attr('data-page'));
            if (pageNum && pageNum !== currentPage) {
                $table.attr('data-current-page', pageNum);
                applyTablePagination(table);
                scrollTableIntoView($table);
            }
        });
    }

    function scrollTableIntoView($table) {
        try {
            const offset = $table.offset();
            if (offset && offset.top < $(window).scrollTop() + 50) {
                $('html, body').animate({ scrollTop: Math.max(0, offset.top - 80) }, 200);
            }
        } catch(e) {}
    }

    // Debounced runner for scanning tables
    let _scanTimeout = null;
    function scanAndPaginateAllTables() {
        if (_scanTimeout) clearTimeout(_scanTimeout);
        _scanTimeout = setTimeout(function() {
            $('table:not(.no-pagination)').each(function() {
                const $tbl = $(this);
                if ($tbl.parents('.calendar, .no-pagination-container, .print-only, .ttd-wrapper, .kop-wrapper').length > 0) {
                    return;
                }
                const $tbody = $tbl.find('tbody');
                if ($tbody.length === 0) return;

                const currentCount = $tbody.find('> tr').not('.no-row-data, .empty-row, .no-paginate, .pf-empty, .sp-empty').length;
                const lastCount = parseInt($tbl.attr('data-row-count')) || 0;
                const isPaginated = $tbl.attr('data-paginated') === 'true';

                if (!isPaginated || currentCount !== lastCount) {
                    if (isPaginated && currentCount !== lastCount) {
                        $tbl.attr('data-current-page', 1);
                    }
                    applyTablePagination(this);
                }
            });
        }, 60);
    }

    /**
     * Initialize MutationObserver to auto-paginate any dynamically rendered table
     */
    function initGlobalTablePagination() {
        if (window._globalTablePaginationInitialized) return;
        window._globalTablePaginationInitialized = true;

        if (typeof MutationObserver !== 'undefined') {
            const observer = new MutationObserver(function(mutations) {
                let shouldCheck = false;
                for (let i = 0; i < mutations.length; i++) {
                    const m = mutations[i];
                    // Ignore mutations originating from within the pagination wrapper itself
                    if (m.target && m.target.classList && m.target.classList.contains('table-pagination-wrapper')) {
                        continue;
                    }
                    if (m.target && m.target.closest && m.target.closest('.table-pagination-wrapper')) {
                        continue;
                    }
                    if (m.addedNodes.length || m.removedNodes.length) {
                        shouldCheck = true;
                        break;
                    }
                }
                if (shouldCheck) {
                    scanAndPaginateAllTables();
                }
            });

            if (document.body) {
                observer.observe(document.body, { childList: true, subtree: true });
            } else {
                $(document).ready(function() {
                    observer.observe(document.body, { childList: true, subtree: true });
                });
            }
        }

        // Initial scan on DOM ready
        $(document).ready(function() {
            scanAndPaginateAllTables();
        });

        // Trigger on window resize or dynamic custom events
        $(document).on('repaginate', function() {
            scanAndPaginateAllTables();
        });
    }

    // Expose globally
    window.applyTablePagination = applyTablePagination;
    window.initGlobalTablePagination = initGlobalTablePagination;
    window.scanAndPaginateAllTables = scanAndPaginateAllTables;

    // Auto-initialize immediately
    initGlobalTablePagination();

})(window, window.jQuery || window.$);
