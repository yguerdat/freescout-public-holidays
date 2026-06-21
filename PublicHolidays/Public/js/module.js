/**
 * Public Holidays - Admin Settings JS
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var page = document.getElementById('publicholidays-settings-page');
        if (!page) {
            return;
        }

        var generateUrl = page.getAttribute('data-generate-url');
        var storeUrl = page.getAttribute('data-store-url');
        var deleteUrl = page.getAttribute('data-delete-url');
        var workingText = page.getAttribute('data-working-text');
        var confirmDelete = page.getAttribute('data-confirm-delete');

        function token() {
            var el = document.querySelector('meta[name="csrf-token"]');
            return el ? el.getAttribute('content') : '';
        }

        function post(url, body) {
            return fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token(),
                    'Accept': 'application/json'
                },
                body: JSON.stringify(body || {})
            }).then(function (r) { return r.json(); });
        }

        // Year selector reloads the page with the chosen year.
        var yearSelect = document.getElementById('ph-year');
        if (yearSelect) {
            yearSelect.addEventListener('change', function () {
                var url = new URL(window.location.href);
                url.searchParams.set('year', this.value);
                window.location.href = url.toString();
            });
        }

        // Generate / refresh holidays for the selected year.
        var genBtn = document.getElementById('ph-generate-btn');
        if (genBtn) {
            genBtn.addEventListener('click', function () {
                var btn = this;
                var result = document.getElementById('ph-generate-result');
                var year = yearSelect ? yearSelect.value : new Date().getFullYear();
                btn.disabled = true;
                result.innerHTML = '<i class="glyphicon glyphicon-hourglass"></i> ' + workingText;

                post(generateUrl, { year: year })
                    .then(function (data) {
                        btn.disabled = false;
                        if (data.success) {
                            result.innerHTML = '<span class="text-success"><i class="glyphicon glyphicon-ok"></i> ' + data.message + '</span>';
                            setTimeout(function () { window.location.reload(); }, 600);
                        } else {
                            result.innerHTML = '<span class="text-danger"><i class="glyphicon glyphicon-remove"></i> ' + data.message + '</span>';
                        }
                    })
                    .catch(function () {
                        btn.disabled = false;
                        result.innerHTML = '<span class="text-danger">Error</span>';
                    });
            });
        }

        // Add a custom holiday.
        var addBtn = document.getElementById('ph-add-btn');
        if (addBtn) {
            addBtn.addEventListener('click', function () {
                var date = document.getElementById('ph-custom-date').value;
                var name = document.getElementById('ph-custom-name').value;
                var canton = document.getElementById('ph-custom-canton').value;
                var result = document.getElementById('ph-add-result');

                if (!date || !name) {
                    result.innerHTML = '<span class="text-danger">' + (confirmDelete ? '' : '') + '!</span>';
                    return;
                }

                post(storeUrl, { date: date, name: name, canton: canton })
                    .then(function (data) {
                        if (data.success) {
                            window.location.reload();
                        } else {
                            result.innerHTML = '<span class="text-danger">' + (data.message || 'Error') + '</span>';
                        }
                    });
            });
        }

        // Delete a holiday.
        page.querySelectorAll('.ph-delete').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!window.confirm(confirmDelete)) {
                    return;
                }
                var id = this.getAttribute('data-id');
                post(deleteUrl, { id: id }).then(function (data) {
                    if (data.success) {
                        window.location.reload();
                    }
                });
            });
        });
    });
})();
