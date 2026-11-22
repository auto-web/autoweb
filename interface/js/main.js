var autoweb = {

    jobs: [],
    is_admin: false,

    main: function() {
        if (autoweb.is_admin) {
            autoweb.refreshJobs();
        }

        jQuery('.placeholder-password').click(function() {
            jQuery(this).parent().addClass('show-password');
        });
        jQuery('.hidden-password').click(function() {
            jQuery(this).parent().removeClass('show-password');
        });

        if (jQuery('#users-table').length) {
            autoweb.usersTable.init();
        }
    },

    refreshJobs: function() {
        jQuery.getJSON('/jobs.php', function(jobs) {
            if (jobs !== undefined && jobs !== null && jobs.length > 0) {
                autoweb.jobs = jobs;
                jQuery('#jobs-tooltip').html('<ul>' + autoweb.jobs.reduce(function(result, item) {
                    return result + '<li>' + (item.status == 'running' ? '<div class="spinner"><div class="double-bounce1"></div><div class="double-bounce2"></div></div>' : '') + '<span>' + item.date + ' ' + item.operation + '</span></li>';
                }, '') + '</ul>');
                jQuery('#jobs-notify').addClass('has-jobs').find('.content').html('<a href="#" class="btn btn-info m-2 rounded">' + jobs.length + ' ' + (jobs.length <= 1 ? 'tâche' : 'tâches') + ' en attente</a>');
                jQuery('#jobs-notify a').click(autoweb.onClickJobsNotification);
                setTimeout(autoweb.refreshJobs, 10000);
            } else {
                autoweb.jobs = [];
                jQuery('#jobs-notify').removeClass('has-jobs').html('');
            }
        });
    },

    onClickJobsNotification: function(e) {
        e.preventDefault();
    },

    // Users list of admin.php (templates/admin/index.twig): filters, sort and columns, in the browser
    usersTable: {

        // Hidden columns, kept in the browser between visits
        storageKey: 'autoweb.users.hiddenColumns',
        // Columns searched by the text filter (never the password)
        searchColumns: ['first-name', 'last-name', 'description', 'email', 'username'],
        hiddenColumns: [],
        sort: {column: null, direction: 1},

        init: function() {
            var table = autoweb.usersTable;

            try {
                table.hiddenColumns = JSON.parse(window.localStorage.getItem(table.storageKey)) || [];
            } catch (e) {
                table.hiddenColumns = [];
            }

            table.initColumns();
            table.initPhpVersions();
            table.applyColumns();

            jQuery('#users-table th[data-sort]').addClass('sortable').attr('tabindex', 0)
                .on('click', table.onClickHeader)
                .on('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        table.onClickHeader.call(this);
                    }
                });
            jQuery('#users-search').on('input', table.filter);
            jQuery('#users-status, #users-php, #users-quota-exceeded').on('change', table.filter);
            jQuery('#users-table-scroll .table-responsive').on('scroll', table.updateScroll);
            jQuery(window).on('resize', table.updateScroll);

            table.filter();
        },

        // Table wider than the page: hint, and shadow on each side that still has hidden columns
        updateScroll: function() {
            var scroll = jQuery('#users-table-scroll .table-responsive')[0];
            var left = scroll.scrollLeft > 0;
            var right = scroll.scrollLeft + scroll.clientWidth < scroll.scrollWidth - 1;

            jQuery('#users-table-scroll').toggleClass('can-scroll-left', left).toggleClass('can-scroll-right', right);
            jQuery('#users-scroll-hint').toggleClass('d-none', !left && !right);
        },

        // Text without case nor accents, for the search
        normalize: function(text) {
            return text.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
        },

        // One checkbox per column (th[data-col]) in the "Colonnes" menu
        initColumns: function() {
            var table = autoweb.usersTable;
            var menu = jQuery('#users-columns');

            jQuery('#users-table th[data-col]').each(function() {
                var column = jQuery(this).data('col');
                var id = 'users-column-' + column;
                var item = jQuery('<li><label class="dropdown-item"><input type="checkbox" class="form-check-input me-2"> </label></li>');

                item.find('input').attr('id', id).prop('checked', table.hiddenColumns.indexOf(column) === -1)
                    .on('change', function() {
                        if (this.checked) {
                            table.hiddenColumns = table.hiddenColumns.filter(function(hidden) {
                                return hidden !== column;
                            });
                        } else {
                            table.hiddenColumns.push(column);
                        }
                        window.localStorage.setItem(table.storageKey, JSON.stringify(table.hiddenColumns));
                        table.applyColumns();
                    });
                item.find('label').append(document.createTextNode(jQuery(this).text().trim()));
                menu.append(item);
            });
        },

        applyColumns: function() {
            jQuery('#users-table [data-col]').each(function() {
                jQuery(this).toggleClass('d-none', autoweb.usersTable.hiddenColumns.indexOf(jQuery(this).data('col')) !== -1);
            });
            autoweb.usersTable.updateScroll();
        },

        // PHP versions of the accounts, in the PHP filter
        initPhpVersions: function() {
            var versions = [];

            jQuery('#users-table tbody tr').each(function() {
                var version = String(jQuery(this).data('php'));
                if (version !== '' && versions.indexOf(version) === -1) {
                    versions.push(version);
                }
            });
            versions.sort(function(a, b) {
                return parseFloat(a) - parseFloat(b);
            });
            versions.forEach(function(version) {
                jQuery('#users-php').append(jQuery('<option>').val(version).text('PHP ' + version));
            });
        },

        filter: function() {
            var table = autoweb.usersTable;
            var search = table.normalize(jQuery('#users-search').val());
            var status = jQuery('#users-status').val();
            var php = jQuery('#users-php').val();
            var quotaExceeded = jQuery('#users-quota-exceeded').prop('checked');
            var rows = jQuery('#users-table tbody tr');
            var shown = 0;

            rows.each(function() {
                var row = jQuery(this);
                var text = table.searchColumns.map(function(column) {
                    return row.children('[data-col="' + column + '"]').text();
                }).join(' ');
                var visible = (search === '' || table.normalize(text).indexOf(search) !== -1)
                    // "Actifs" also shows the pending accounts: just created, or to repair
                    && (status === '' || row.data('status') === status
                        || (status === 'active' && row.data('status') === 'pending'))
                    && (php === '' || String(row.data('php')) === php)
                    && (!quotaExceeded || String(row.data('quota-exceeded')) === '1');

                row.toggleClass('d-none', !visible);
                if (visible) {
                    shown++;
                }
            });

            jQuery('#users-count').text(shown + ' / ' + rows.length + ' compte' + (rows.length > 1 ? 's' : ''));
            jQuery('#users-empty').toggleClass('d-none', shown > 0);
            table.updateScroll();
        },

        // Sort on the clicked column, ascending then descending
        onClickHeader: function() {
            var table = autoweb.usersTable;
            var header = jQuery(this);
            var column = header.data('col');
            var numeric = header.data('sort') === 'number';
            var tbody = jQuery('#users-table tbody');

            table.sort.direction = (table.sort.column === column) ? -table.sort.direction : 1;
            table.sort.column = column;

            var value = function(row) {
                var cell = jQuery(row).children('[data-col="' + column + '"]');
                var raw = cell.attr('data-value') !== undefined ? cell.attr('data-value') : cell.text().trim();
                return numeric ? (parseFloat(raw) || 0) : raw;
            };

            var rows = tbody.children('tr').get().sort(function(a, b) {
                var va = value(a);
                var vb = value(b);
                var result = numeric ? va - vb : String(va).localeCompare(String(vb), 'fr', {sensitivity: 'base', numeric: true});
                return result * table.sort.direction;
            });
            tbody.append(rows);

            jQuery('#users-table th[data-sort]').removeClass('sorted-asc sorted-desc').removeAttr('aria-sort');
            header.addClass(table.sort.direction === 1 ? 'sorted-asc' : 'sorted-desc')
                .attr('aria-sort', table.sort.direction === 1 ? 'ascending' : 'descending');
        }
    }

};

jQuery(document).ready(autoweb.main);
