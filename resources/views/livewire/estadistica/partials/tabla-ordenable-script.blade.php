@script
<script>
    (function () {
        function setSortIndicator(thead, activeIdx, sortAsc) {
            thead.querySelectorAll('th').forEach(function (th, idx) {
                th.classList.remove('se-th-sort-asc', 'se-th-sort-desc');
                if (th.getAttribute('data-sort-disabled') === '1') {
                    th.removeAttribute('aria-sort');
                    return;
                }
                if (idx === activeIdx) {
                    th.classList.add(sortAsc ? 'se-th-sort-asc' : 'se-th-sort-desc');
                    th.setAttribute('aria-sort', sortAsc ? 'ascending' : 'descending');
                } else {
                    th.setAttribute('aria-sort', 'none');
                }
            });
        }

        function initSortableTables() {
            document.querySelectorAll('[data-se-tabla-ordenable]').forEach(function (table) {
                if (table.dataset.sortInit === '1') return;
                table.dataset.sortInit = '1';
                const thead = table.querySelector('thead');
                const tbody = table.querySelector('tbody');
                if (!thead || !tbody) return;
                let sortCol = -1;
                let sortAsc = true;
                thead.querySelectorAll('th').forEach(function (th, idx) {
                    if (th.getAttribute('data-sort-disabled') === '1') {
                        return;
                    }
                    th.classList.add('se-th-sortable');
                    th.setAttribute('title', 'Clic para ordenar');
                    th.setAttribute('aria-sort', 'none');
                    th.addEventListener('click', function () {
                        const isNum = th.classList.contains('num') || th.dataset.sortNum === '1';
                        if (sortCol === idx) sortAsc = !sortAsc;
                        else { sortCol = idx; sortAsc = true; }
                        const rows = Array.from(tbody.querySelectorAll('tr'));
                        rows.sort(function (a, b) {
                            const va = (a.cells[idx] && a.cells[idx].textContent) ? a.cells[idx].textContent.trim() : '';
                            const vb = (b.cells[idx] && b.cells[idx].textContent) ? b.cells[idx].textContent.trim() : '';
                            if (isNum) {
                                const na = parseFloat(va.replace(/\s/g, '').replace(',', '.')) || 0;
                                const nb = parseFloat(vb.replace(/\s/g, '').replace(',', '.')) || 0;
                                return sortAsc ? na - nb : nb - na;
                            }
                            const cmp = va.localeCompare(vb, 'es');
                            return sortAsc ? cmp : -cmp;
                        });
                        rows.forEach(function (r) { tbody.appendChild(r); });
                        setSortIndicator(thead, idx, sortAsc);
                    });
                });
            });
        }
        initSortableTables();
        $wire.hook('morph.updated', () => setTimeout(initSortableTables, 50));
    })();
</script>
@endscript
