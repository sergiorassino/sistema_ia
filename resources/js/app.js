import './bootstrap';
import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

window.Swal = Swal;

/** SweetAlert2 — éxito institucional (portal alumno / formularios SE). */
window.seSwalExito = function (mensaje, titulo = '¡Guardado!') {
    if (typeof Swal === 'undefined') {
        return;
    }
    Swal.fire({
        icon: 'success',
        title: titulo,
        text: mensaje,
        confirmButtonText: 'Aceptar',
        confirmButtonColor: '#40848D',
    });
};

/** SweetAlert2 — aviso / advertencia institucional (portal alumno). */
window.seSwalAviso = function (mensaje, titulo = 'Atención') {
    if (typeof Swal === 'undefined') {
        return;
    }
    Swal.fire({
        icon: 'warning',
        title: titulo,
        text: mensaje,
        confirmButtonText: 'Aceptar',
        confirmButtonColor: '#40848D',
    });
};

/** SweetAlert2 — error / operación rechazada. */
window.seSwalError = function (mensaje, titulo = 'No se pudo completar') {
    if (typeof Swal === 'undefined') {
        window.alert(mensaje);
        return;
    }
    Swal.fire({
        icon: 'error',
        title: titulo,
        text: mensaje,
        confirmButtonText: 'Aceptar',
        confirmButtonColor: '#40848D',
    });
};

/**
 * SweetAlert2 — confirmación con Sí / Cancelar.
 * Devuelve Promise<boolean> (true si el usuario confirma).
 * No usar window.confirm ni wire:confirm del navegador.
 */
window.seSwalConfirmar = function (mensaje, titulo = '¿Confirma?', opciones = {}) {
    if (typeof Swal === 'undefined') {
        return Promise.resolve(window.confirm(mensaje));
    }
    return Swal.fire({
        icon: 'question',
        title: titulo,
        text: mensaje,
        showCancelButton: true,
        confirmButtonText: opciones.confirmButtonText ?? 'Sí, confirmar',
        cancelButtonText: opciones.cancelButtonText ?? 'Cancelar',
        confirmButtonColor: '#40848D',
        cancelButtonColor: '#6b7280',
        reverseButtons: true,
        focusCancel: true,
        ...opciones,
    }).then((result) => result.isConfirmed === true);
};

/**
 * Carga de calificaciones (secundario / calificacionesSecundario): validación de notas permitidas en el cliente (sin request si es inválida).
 * Delegación `focusout` en `tbody[data-se-calif-tbody]` + toast liviano (sin SweetAlert).
 * Navegación con flechas entre celdas; Enter baja una fila (misma columna) o salta a la columna siguiente en la primera fila al llegar al final.
 */
function seCalifCampoConCatalogo(field) {
    return /^ic(0[1-9]|1[0-9]|2[0-8])$/.test(field) || field === 'dic' || field === 'feb';
}

function seCalifCallSaveCell(root, rowId, field, value) {
    const c = root && root.__livewire;
    if (!c || !c.$wire) {
        return false;
    }
    const w = c.$wire;
    if (typeof w.call === 'function') {
        w.call('saveCell', rowId, field, value);
        return true;
    }
    if (typeof w.saveCell === 'function') {
        w.saveCell(rowId, field, value);
        return true;
    }
    return false;
}

/** Filas × columnas de inputs de nota (orden DOM = orden visual en la tabla). */
function seCalifBuildNavMatrix(tbody) {
    const matrix = [];
    tbody.querySelectorAll(':scope > tr').forEach((tr) => {
        const row = [];
        tr.querySelectorAll('input[id^="se-calif-"]').forEach((inp) => {
            if (inp.type === 'checkbox') {
                return;
            }
            if (!/^se-calif-\d+-.+$/.test(String(inp.id || ''))) {
                return;
            }
            row.push(inp);
        });
        if (row.length) {
            matrix.push(row);
        }
    });
    return matrix;
}

function seCalifFindNavPos(matrix, el) {
    for (let r = 0; r < matrix.length; r++) {
        const c = matrix[r].indexOf(el);
        if (c >= 0) {
            return { row: r, col: c };
        }
    }
    return null;
}

function seCalifFocusNavCell(inp) {
    if (!inp) {
        return;
    }
    inp.focus();
    if (typeof inp.select === 'function') {
        inp.select();
    }
}

/**
 * Globo con flecha hacia la celda con nota inválida (visible con scroll largo).
 * @param {HTMLElement} [anchorEl] input de la celda; si no hay, aviso centrado arriba sin flecha.
 */
window.seCalifToastInvalida = function (anchorEl) {
    const GAP = 5;
    const M = 6;

    const wrap = document.createElement('div');
    wrap.style.position = 'fixed';
    wrap.style.zIndex = '9999';
    wrap.style.pointerEvents = 'none';
    wrap.className =
        'flex min-w-[8.5rem] max-w-[12rem] flex-col items-center';

    const bubble = document.createElement('div');
    bubble.setAttribute('role', 'alert');
    bubble.className =
        'rounded-lg border border-red-300 bg-red-50 px-2.5 py-1.5 text-center text-[11px] font-semibold leading-snug text-red-900 shadow-md';
    bubble.textContent = 'La Calificación no es Válida.';

    const arrowUp = () => {
        const a = document.createElement('div');
        a.setAttribute('aria-hidden', 'true');
        a.className =
            'h-0 w-0 shrink-0 border-x-[7px] border-b-[8px] border-x-transparent border-b-red-300 -mb-px';
        return a;
    };

    const arrowDown = () => {
        const a = document.createElement('div');
        a.setAttribute('aria-hidden', 'true');
        a.className =
            'h-0 w-0 shrink-0 border-x-[7px] border-t-[8px] border-x-transparent border-t-red-300 -mt-px';
        return a;
    };

    const setBelow = () => {
        wrap.replaceChildren(arrowUp(), bubble);
    };

    const setAbove = () => {
        wrap.replaceChildren(bubble, arrowDown());
    };

    const setFallback = () => {
        wrap.replaceChildren(bubble);
    };

    const place = () => {
        const okAnchor =
            anchorEl &&
            typeof anchorEl.getBoundingClientRect === 'function' &&
            document.body.contains(anchorEl);

        if (!okAnchor) {
            setFallback();
            if (!wrap.parentNode) {
                document.body.appendChild(wrap);
            }
            wrap.style.left = '50%';
            wrap.style.top = '4.5rem';
            wrap.style.transform = 'translateX(-50%)';
            return;
        }

        const r = anchorEl.getBoundingClientRect();

        setBelow();
        if (!wrap.parentNode) {
            document.body.appendChild(wrap);
        }

        let h = wrap.offsetHeight;
        let below = r.bottom + GAP + h <= window.innerHeight - M;
        if (!below && r.top - GAP - h >= M) {
            setAbove();
            h = wrap.offsetHeight;
            below = false;
        } else {
            below = true;
        }

        const w = wrap.offsetWidth;
        let left = r.left + r.width / 2 - w / 2;
        left = Math.max(M, Math.min(left, window.innerWidth - w - M));

        let top = below ? r.bottom + GAP : r.top - GAP - h;
        if (below && top + h > window.innerHeight - M) {
            top = Math.max(M, window.innerHeight - h - M);
        }
        if (!below && top < M) {
            top = M;
        }

        wrap.style.left = `${left}px`;
        wrap.style.top = `${top}px`;
        wrap.style.transform = '';
    };

    requestAnimationFrame(() => {
        requestAnimationFrame(place);
    });

    const onScrollOrResize = () => place();
    window.addEventListener('scroll', onScrollOrResize, true);
    window.addEventListener('resize', onScrollOrResize);

    window.setTimeout(() => {
        window.removeEventListener('scroll', onScrollOrResize, true);
        window.removeEventListener('resize', onScrollOrResize);
        wrap.remove();
    }, 3500);
};

const SECALIF_PRIM_INPUT_ID = /^se-calif-prim-(\d+)-(ic0[123])$/;

function seCalifPrimCampoConCatalogo(field) {
    return field === 'ic01' || field === 'ic02' || field === 'ic03';
}

let seCalifPrimNotaPickerOpen = null;

function seCalifPrimNotaPickerOptions(menu) {
    return Array.from(menu.querySelectorAll('.se-calif-prim-nota-picker-option'));
}

function seCalifPrimSyncNotaPickerSelection(menu, value) {
    const val = String(value ?? '').trim();
    seCalifPrimNotaPickerOptions(menu).forEach((opt) => {
        const match = String(opt.dataset.nota ?? '').trim() === val && val !== '';
        opt.classList.toggle('is-selected', match);
        opt.setAttribute('aria-selected', match ? 'true' : 'false');
        opt.classList.remove('is-focused');
    });
}

function seCalifPrimClearNotaPickerFocus(menu) {
    seCalifPrimNotaPickerOptions(menu).forEach((opt) => opt.classList.remove('is-focused'));
}

function seCalifPrimFocusNotaPickerOption(menu, index) {
    const options = seCalifPrimNotaPickerOptions(menu);
    if (!options.length) {
        return null;
    }
    let idx = index;
    if (idx < 0) {
        idx = 0;
    }
    if (idx >= options.length) {
        idx = options.length - 1;
    }
    seCalifPrimClearNotaPickerFocus(menu);
    const opt = options[idx];
    opt.classList.add('is-focused');
    opt.focus({ preventScroll: true });
    return opt;
}

function seCalifPrimFocusNotaPickerInitial(menu, input) {
    const val = String(input?.value ?? '').trim();
    const options = seCalifPrimNotaPickerOptions(menu);
    let idx = options.findIndex((opt) => String(opt.dataset.nota ?? '').trim() === val);
    if (idx < 0) {
        idx = 0;
    }
    return seCalifPrimFocusNotaPickerOption(menu, idx);
}

function seCalifPrimFindNotaPickerMenu(picker) {
    const inputId = picker?.dataset?.seCalifPrimNotaPickerFor ?? '';
    if (inputId === '') {
        return null;
    }
    return document.querySelector(
        `.se-calif-prim-nota-picker-menu[data-se-calif-prim-nota-menu-for="${CSS.escape(String(inputId))}"]`,
    );
}

function seCalifPrimCloseNotaPicker(focusBack = false) {
    if (!seCalifPrimNotaPickerOpen) {
        return;
    }
    const { menu, btn, picker, placeholder } = seCalifPrimNotaPickerOpen;
    menu.hidden = true;
    menu.classList.add('hidden');
    menu.style.cssText = '';
    if (btn) {
        btn.setAttribute('aria-expanded', 'false');
    }
    if (placeholder && picker && menu.parentElement === document.body) {
        picker.insertBefore(menu, placeholder);
        placeholder.remove();
    }
    delete menu._seCalifPrimPlaceholder;
    delete menu._seCalifPrimHome;
    const returnFocus = focusBack ? btn : null;
    seCalifPrimNotaPickerOpen = null;
    if (returnFocus) {
        returnFocus.focus({ preventScroll: true });
    }
}

function seCalifPrimApplyNotaFromPicker(input, nota, menu) {
    input.dataset.seCalifPrimLast = input.value ?? '';
    input.dataset.seCalifPrimMatLast = input.value ?? '';
    input.value = nota;
    if (menu) {
        seCalifPrimSyncNotaPickerSelection(menu, nota);
    }
    input.dispatchEvent(new FocusEvent('focusout', { bubbles: true }));
}

function seCalifPrimPositionNotaPickerMenu(btn, menu) {
    menu.hidden = false;
    menu.classList.remove('hidden');
    menu.style.visibility = 'hidden';
    menu.style.position = 'fixed';
    menu.style.zIndex = '250';
    const menuWidth = Math.max(36, Math.ceil(menu.getBoundingClientRect().width));
    const rect = btn.getBoundingClientRect();
    let left = rect.right - menuWidth;
    left = Math.max(4, Math.min(left, window.innerWidth - menuWidth - 4));
    menu.style.width = `${menuWidth}px`;
    menu.style.top = `${Math.round(rect.bottom + 2)}px`;
    menu.style.left = `${Math.round(left)}px`;
    menu.style.visibility = 'visible';
}

function seCalifPrimOpenNotaPicker(btn, menu, picker, input) {
    seCalifPrimCloseNotaPicker(false);
    const placeholder = document.createComment('se-calif-prim-nota-picker-menu');
    picker.insertBefore(placeholder, menu);
    menu._seCalifPrimPlaceholder = placeholder;
    menu._seCalifPrimHome = picker;
    document.body.appendChild(menu);
    btn.setAttribute('aria-expanded', 'true');
    seCalifPrimSyncNotaPickerSelection(menu, input.value);
    seCalifPrimPositionNotaPickerMenu(btn, menu);
    seCalifPrimNotaPickerOpen = { menu, btn, picker, input, placeholder };
    seCalifPrimFocusNotaPickerInitial(menu, input);
}

function seCalifPrimHandleNotaPickerClick(e) {
    const opt = e.target.closest('.se-calif-prim-nota-picker-option');
    if (opt && seCalifPrimNotaPickerOpen?.menu?.contains(opt)) {
        e.preventDefault();
        e.stopPropagation();
        const { menu, input } = seCalifPrimNotaPickerOpen;
        const nota = String(opt.dataset.nota ?? opt.textContent ?? '').trim();
        if (nota !== '') {
            seCalifPrimApplyNotaFromPicker(input, nota, menu);
        }
        seCalifPrimCloseNotaPicker(true);
        return;
    }

    const btn = e.target.closest('.se-calif-prim-nota-picker-btn');
    if (btn) {
        e.preventDefault();
        e.stopPropagation();
        const picker = btn.closest('[data-se-calif-prim-nota-picker-for]');
        if (!picker) {
            return;
        }
        const menu = seCalifPrimFindNotaPickerMenu(picker);
        const input = document.getElementById(picker.dataset.seCalifPrimNotaPickerFor);
        if (!menu || !input || input.disabled) {
            return;
        }
        if (seCalifPrimNotaPickerOpen && seCalifPrimNotaPickerOpen.menu === menu) {
            seCalifPrimCloseNotaPicker(true);
            return;
        }
        seCalifPrimOpenNotaPicker(btn, menu, picker, input);
        return;
    }

    if (!seCalifPrimNotaPickerOpen) {
        return;
    }
    const { menu, btn: openBtn } = seCalifPrimNotaPickerOpen;
    if (menu.contains(e.target) || openBtn.contains(e.target)) {
        return;
    }
    seCalifPrimCloseNotaPicker(true);
}

function seCalifPrimBindNotaPickerDocClose() {
    if (window._seCalifPrimNotaPickerDocBound) {
        return;
    }
    window._seCalifPrimNotaPickerDocBound = true;

    document.addEventListener(
        'mousedown',
        (e) => {
            if (e.target.closest('.se-calif-prim-nota-picker-btn')) {
                e.preventDefault();
            }
        },
        true,
    );

    document.addEventListener(
        'click',
        (e) => {
            seCalifPrimHandleNotaPickerClick(e);
        },
        true,
    );

    document.addEventListener('keydown', (e) => {
        if (!seCalifPrimNotaPickerOpen) {
            return;
        }
        const { menu, input, btn } = seCalifPrimNotaPickerOpen;
        const options = seCalifPrimNotaPickerOptions(menu);
        let idx = options.findIndex((opt) => opt.classList.contains('is-focused'));

        if (e.key === 'Escape') {
            e.preventDefault();
            seCalifPrimCloseNotaPicker(true);
            return;
        }

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (idx < 0) {
                idx = 0;
            } else {
                idx = Math.min(idx + 1, options.length - 1);
            }
            seCalifPrimFocusNotaPickerOption(menu, idx);
            return;
        }

        if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (idx < 0) {
                idx = options.length - 1;
            } else {
                idx = Math.max(idx - 1, 0);
            }
            seCalifPrimFocusNotaPickerOption(menu, idx);
            return;
        }

        if (e.key === 'Enter' || e.key === ' ') {
            const focused = options[idx];
            if (!focused || !menu.contains(document.activeElement)) {
                return;
            }
            e.preventDefault();
            const nota = String(focused.dataset.nota ?? '').trim();
            if (nota !== '') {
                seCalifPrimApplyNotaFromPicker(input, nota, menu);
            }
            seCalifPrimCloseNotaPicker(true);
        }
    });

    window.addEventListener('scroll', () => seCalifPrimCloseNotaPicker(true), true);
    window.addEventListener('resize', () => seCalifPrimCloseNotaPicker(true));
}

/** Desplegable compacto de notas permitidas → copia al input y dispara guardado (focusout). */
function seCalifPrimBindNotaPickerCombo(tbody) {
    if (tbody._seCalifPrimNotaPickerBound) {
        return;
    }
    tbody._seCalifPrimNotaPickerBound = true;
    seCalifPrimBindNotaPickerDocClose();

    tbody.addEventListener(
        'keydown',
        (e) => {
            const btn = e.target.closest('.se-calif-prim-nota-picker-btn');
            if (!btn) {
                return;
            }
            if (e.key !== 'Enter' && e.key !== ' ' && e.key !== 'ArrowDown') {
                return;
            }
            e.preventDefault();
            const picker = btn.closest('[data-se-calif-prim-nota-picker-for]');
            if (!picker) {
                return;
            }
            const menu = seCalifPrimFindNotaPickerMenu(picker);
            const input = document.getElementById(picker.dataset.seCalifPrimNotaPickerFor);
            if (!menu || !input || input.disabled) {
                return;
            }
            if (seCalifPrimNotaPickerOpen && seCalifPrimNotaPickerOpen.menu === menu) {
                return;
            }
            seCalifPrimOpenNotaPicker(btn, menu, picker, input);
        },
        true,
    );
}

function seCalifPrimCallSaveCell(root, ord, field, value) {
    const c = root && root.__livewire;
    if (!c || !c.$wire) {
        return false;
    }
    const w = c.$wire;
    if (typeof w.call === 'function') {
        w.call('saveCell', ord, field, value);
        return true;
    }
    if (typeof w.saveCell === 'function') {
        w.saveCell(ord, field, value);
        return true;
    }
    return false;
}

/** Filas × columnas (inputs de la grilla primario; incluye celdas deshabilitadas para alinear columnas). */
function seCalifPrimBuildNavMatrix(tbody) {
    const matrix = [];
    tbody.querySelectorAll(':scope > tr').forEach((tr) => {
        const row = [];
        tr.querySelectorAll('input[id^="se-calif-prim-"]').forEach((inp) => {
            if (inp.type === 'checkbox' || inp.disabled) {
                row.push(inp);
                return;
            }
            if (!SECALIF_PRIM_INPUT_ID.test(String(inp.id || ''))) {
                return;
            }
            row.push(inp);
        });
        if (row.length) {
            matrix.push(row);
        }
    });
    return matrix;
}

function seCalifPrimFindNavPos(matrix, el) {
    for (let r = 0; r < matrix.length; r++) {
        const c = matrix[r].indexOf(el);
        if (c >= 0) {
            return { row: r, col: c };
        }
    }
    return null;
}

/** Avanza en la dirección indicada saltando celdas deshabilitadas. */
function seCalifPrimStep(matrix, row, col, dRow, dCol) {
    const nrows = matrix.length;
    const ncols = matrix[0] ? matrix[0].length : 0;
    if (!nrows || !ncols) {
        return null;
    }
    let nr = row;
    let nc = col;
    const maxSteps = nrows * ncols;
    for (let i = 0; i < maxSteps; i++) {
        nr += dRow;
        nc += dCol;
        if (nr < 0 || nr >= nrows || nc < 0 || nc >= ncols) {
            return null;
        }
        const el = matrix[nr][nc];
        if (el && !el.disabled) {
            return el;
        }
    }
    return null;
}

function bindCalifPrimarioTablas() {
    document.querySelectorAll('[data-se-calif-prim-tbody]').forEach((tbody) => {
        if (tbody._seCalifPrimBound) {
            return;
        }
        tbody._seCalifPrimBound = true;
        seCalifPrimBindNotaPickerCombo(tbody);

        tbody.addEventListener(
            'focusin',
            (e) => {
                const el = e.target;
                if (!el || el.tagName !== 'INPUT' || el.type === 'checkbox') {
                    return;
                }
                el.dataset.seCalifPrimLast = el.value ?? '';
            },
            true,
        );

        tbody.addEventListener(
            'focusout',
            (e) => {
                const el = e.target;
                if (!el || el.tagName !== 'INPUT' || el.type === 'checkbox') {
                    return;
                }
                if (el.disabled) {
                    return;
                }
                const m = el.id && String(el.id).match(SECALIF_PRIM_INPUT_ID);
                if (!m) {
                    return;
                }
                const ord = parseInt(m[1], 10);
                const field = m[2];
                const val = (el.value || '').trim();

                const activa = tbody.getAttribute('data-se-calif-prim-activa') === '1';
                let allowed = [];
                try {
                    allowed = JSON.parse(tbody.getAttribute('data-se-calif-prim-allowed') || '[]');
                } catch {
                    allowed = [];
                }
                const set = new Set(allowed.map((x) => String(x).trim()));

                if (activa && seCalifPrimCampoConCatalogo(field) && val !== '' && !set.has(val)) {
                    el.value = el.dataset.seCalifPrimLast ?? '';
                    window.seCalifToastInvalida(el);
                    queueMicrotask(() => {
                        seCalifFocusNavCell(el);
                    });
                    return;
                }

                const root = el.closest('[wire\\:id]');
                if (!root) {
                    return;
                }
                seCalifPrimCallSaveCell(root, ord, field, el.value);
            },
            true,
        );

        tbody.addEventListener(
            'keydown',
            (e) => {
                if (e.ctrlKey || e.metaKey || e.altKey) {
                    return;
                }
                const el = e.target;
                if (!el || el.tagName !== 'INPUT' || el.type === 'checkbox') {
                    return;
                }
                if (!SECALIF_PRIM_INPUT_ID.test(String(el.id || ''))) {
                    return;
                }
                const navKeys = ['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'Enter'];
                if (!navKeys.includes(e.key)) {
                    return;
                }

                const matrix = seCalifPrimBuildNavMatrix(tbody);
                const pos = seCalifPrimFindNavPos(matrix, el);
                if (!pos) {
                    return;
                }

                const nrows = matrix.length;
                const ncols = matrix[0] ? matrix[0].length : 0;
                if (!nrows || !ncols) {
                    return;
                }

                const { row, col } = pos;
                let next = null;

                if (e.key === 'ArrowLeft') {
                    next = seCalifPrimStep(matrix, row, col, 0, -1);
                } else if (e.key === 'ArrowRight') {
                    next = seCalifPrimStep(matrix, row, col, 0, 1);
                } else if (e.key === 'ArrowUp') {
                    next = seCalifPrimStep(matrix, row, col, -1, 0);
                } else if (e.key === 'ArrowDown') {
                    next = seCalifPrimStep(matrix, row, col, 1, 0);
                } else if (e.key === 'Enter') {
                    next = seCalifPrimStep(matrix, row, col, 1, 0);
                    if (!next && col + 1 < ncols) {
                        for (let r = 0; r < nrows; r++) {
                            const candidate = matrix[r][col + 1];
                            if (candidate && !candidate.disabled) {
                                next = candidate;
                                break;
                            }
                        }
                    }
                }

                if (!next || next === el) {
                    return;
                }

                e.preventDefault();
                seCalifFocusNavCell(next);
            },
            true,
        );
    });
}

const SECALIF_PRIM_MAT_INPUT_ID = /^se-calif-prim-mat-(\d+)-(ic\d{2})$/;

function seCalifPrimMatCampoConCatalogo(field) {
    return (
        field === 'ic01' ||
        field === 'ic02' ||
        field === 'ic03' ||
        (field >= 'ic05' && field <= 'ic16')
    );
}

function seCalifPrimMatCallSaveCell(root, idMatricula, field, value) {
    const c = root && root.__livewire;
    if (!c || !c.$wire) {
        return false;
    }
    const w = c.$wire;
    if (typeof w.call === 'function') {
        w.call('saveCell', idMatricula, field, value);
        return true;
    }
    if (typeof w.saveCell === 'function') {
        w.saveCell(idMatricula, field, value);
        return true;
    }
    return false;
}

function seCalifPrimMatBuildNavMatrix(tbody) {
    const matrix = [];
    tbody.querySelectorAll(':scope > tr').forEach((tr) => {
        const row = [];
        tr.querySelectorAll('input[id^="se-calif-prim-mat-"]').forEach((inp) => {
            if (inp.type === 'checkbox' || inp.disabled) {
                row.push(inp);
                return;
            }
            if (!SECALIF_PRIM_MAT_INPUT_ID.test(String(inp.id || ''))) {
                return;
            }
            row.push(inp);
        });
        if (row.length) {
            matrix.push(row);
        }
    });
    return matrix;
}

function bindCalifPrimarioMateriaTablas() {
    document.querySelectorAll('[data-se-calif-prim-mat-tbody]').forEach((tbody) => {
        if (tbody._seCalifPrimMatBound) {
            return;
        }
        tbody._seCalifPrimMatBound = true;
        seCalifPrimBindNotaPickerCombo(tbody);

        tbody.addEventListener(
            'focusin',
            (e) => {
                const el = e.target;
                if (!el || el.tagName !== 'INPUT' || el.type === 'checkbox') {
                    return;
                }
                el.dataset.seCalifPrimMatLast = el.value ?? '';
                el.dataset.seCalifPrimMatScopeMateria = tbody.getAttribute('data-se-calif-prim-mat-materia-id') ?? '';
                el.dataset.seCalifPrimMatScopeOrd = tbody.getAttribute('data-se-calif-prim-mat-ord') ?? '';
            },
            true,
        );

        tbody.addEventListener(
            'focusout',
            (e) => {
                const el = e.target;
                if (!el || el.tagName !== 'INPUT' || el.type === 'checkbox') {
                    return;
                }
                if (el.disabled) {
                    return;
                }
                const m = el.id && String(el.id).match(SECALIF_PRIM_MAT_INPUT_ID);
                if (!m) {
                    return;
                }
                const scopeMateria = el.dataset.seCalifPrimMatScopeMateria ?? '';
                const scopeOrd = el.dataset.seCalifPrimMatScopeOrd ?? '';
                if (
                    scopeMateria !== '' &&
                    (scopeMateria !== (tbody.getAttribute('data-se-calif-prim-mat-materia-id') ?? '') ||
                        scopeOrd !== (tbody.getAttribute('data-se-calif-prim-mat-ord') ?? ''))
                ) {
                    return;
                }
                if (!el.isConnected) {
                    return;
                }
                const idMatricula = parseInt(m[1], 10);
                const field = m[2];
                const val = (el.value || '').trim();

                const activa = tbody.getAttribute('data-se-calif-prim-mat-activa') === '1';
                let allowed = [];
                try {
                    allowed = JSON.parse(tbody.getAttribute('data-se-calif-prim-mat-allowed') || '[]');
                } catch {
                    allowed = [];
                }
                const set = new Set(allowed.map((x) => String(x).trim()));

                if (activa && seCalifPrimMatCampoConCatalogo(field) && val !== '' && !set.has(val)) {
                    el.value = el.dataset.seCalifPrimMatLast ?? '';
                    window.seCalifToastInvalida(el);
                    queueMicrotask(() => {
                        seCalifFocusNavCell(el);
                    });
                    return;
                }

                const root = el.closest('[wire\\:id]');
                if (!root) {
                    return;
                }
                seCalifPrimMatCallSaveCell(root, idMatricula, field, el.value);
            },
            true,
        );

        tbody.addEventListener(
            'keydown',
            (e) => {
                if (e.ctrlKey || e.metaKey || e.altKey) {
                    return;
                }
                const el = e.target;
                if (!el || el.tagName !== 'INPUT' || el.type === 'checkbox') {
                    return;
                }
                if (!SECALIF_PRIM_MAT_INPUT_ID.test(String(el.id || ''))) {
                    return;
                }
                const navKeys = ['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'Enter'];
                if (!navKeys.includes(e.key)) {
                    return;
                }

                const matrix = seCalifPrimMatBuildNavMatrix(tbody);
                const pos = seCalifPrimFindNavPos(matrix, el);
                if (!pos) {
                    return;
                }

                const nrows = matrix.length;
                const ncols = matrix[0] ? matrix[0].length : 0;
                if (!nrows || !ncols) {
                    return;
                }

                const { row, col } = pos;
                let next = null;

                if (e.key === 'ArrowLeft') {
                    next = seCalifPrimStep(matrix, row, col, 0, -1);
                } else if (e.key === 'ArrowRight') {
                    next = seCalifPrimStep(matrix, row, col, 0, 1);
                } else if (e.key === 'ArrowUp') {
                    next = seCalifPrimStep(matrix, row, col, -1, 0);
                } else if (e.key === 'ArrowDown') {
                    next = seCalifPrimStep(matrix, row, col, 1, 0);
                } else if (e.key === 'Enter') {
                    next = seCalifPrimStep(matrix, row, col, 1, 0);
                    if (!next && col + 1 < ncols) {
                        for (let r = 0; r < nrows; r++) {
                            const candidate = matrix[r][col + 1];
                            if (candidate && !candidate.disabled) {
                                next = candidate;
                                break;
                            }
                        }
                    }
                }

                if (!next || next === el) {
                    return;
                }

                e.preventDefault();
                seCalifFocusNavCell(next);
            },
            true,
        );
    });
}

function bindCalifCargaTablas() {
    document.querySelectorAll('[data-se-calif-tbody]').forEach((tbody) => {
        if (tbody._seCalifBound) {
            return;
        }
        tbody._seCalifBound = true;

        tbody.addEventListener(
            'focusin',
            (e) => {
                const el = e.target;
                if (!el || el.tagName !== 'INPUT' || el.type === 'checkbox') {
                    return;
                }
                el.dataset.seCalifLast = el.value ?? '';
            },
            true,
        );

        tbody.addEventListener(
            'focusout',
            (e) => {
                if (tbody.getAttribute('data-se-calif-solo-lectura') === '1') {
                    return;
                }
                const el = e.target;
                if (!el || el.tagName !== 'INPUT' || el.type === 'checkbox') {
                    return;
                }
                if (el.readOnly || el.disabled) {
                    return;
                }
                const m = el.id && String(el.id).match(/^se-calif-(\d+)-(.+)$/);
                if (!m) {
                    return;
                }
                const rowId = parseInt(m[1], 10);
                const field = m[2];
                if (field === 'calif') {
                    return;
                }
                const val = (el.value || '').trim();

                const activa = tbody.getAttribute('data-se-calif-activa') === '1';
                let allowed = [];
                try {
                    allowed = JSON.parse(tbody.getAttribute('data-se-calif-allowed') || '[]');
                } catch {
                    allowed = [];
                }
                // Todo como string (por si el JSON trae números p. ej. 10 vs "10").
                const set = new Set(allowed.map((x) => String(x).trim()));

                if (activa && seCalifCampoConCatalogo(field) && val !== '' && !set.has(val)) {
                    el.value = el.dataset.seCalifLast ?? '';
                    window.seCalifToastInvalida(el);
                    queueMicrotask(() => {
                        el.focus();
                        if (typeof el.select === 'function') {
                            el.select();
                        }
                    });
                    return;
                }

                const root = el.closest('[wire\\:id]');
                if (!root) {
                    return;
                }
                seCalifCallSaveCell(root, rowId, field, el.value);
            },
            true,
        );

        tbody.addEventListener(
            'keydown',
            (e) => {
                if (e.ctrlKey || e.metaKey || e.altKey) {
                    return;
                }
                const el = e.target;
                if (!el || el.tagName !== 'INPUT' || el.type === 'checkbox') {
                    return;
                }
                if (!/^se-calif-\d+-.+$/.test(String(el.id || ''))) {
                    return;
                }
                const navKeys = ['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'Enter'];
                if (!navKeys.includes(e.key)) {
                    return;
                }

                const matrix = seCalifBuildNavMatrix(tbody);
                const pos = seCalifFindNavPos(matrix, el);
                if (!pos) {
                    return;
                }

                const nrows = matrix.length;
                const ncols = matrix[0] ? matrix[0].length : 0;
                if (!nrows || !ncols) {
                    return;
                }

                const { row, col } = pos;
                let nr = row;
                let nc = col;

                if (e.key === 'ArrowLeft') {
                    nc = col - 1;
                } else if (e.key === 'ArrowRight') {
                    nc = col + 1;
                } else if (e.key === 'ArrowUp') {
                    nr = row - 1;
                } else if (e.key === 'ArrowDown') {
                    nr = row + 1;
                } else if (e.key === 'Enter') {
                    if (row + 1 < nrows) {
                        nr = row + 1;
                        nc = col;
                    } else if (col + 1 < ncols) {
                        nr = 0;
                        nc = col + 1;
                    } else {
                        return;
                    }
                }

                if (nr < 0 || nr >= nrows || nc < 0 || nc >= ncols) {
                    return;
                }

                const next = matrix[nr][nc];
                if (!next || next === el) {
                    return;
                }

                e.preventDefault();
                seCalifFocusNavCell(next);
            },
            true,
        );
    });
}

function seCiiCallCommitCell(root, key, field, value) {
    const c = root && root.__livewire;
    if (!c || !c.$wire) {
        return false;
    }
    const w = c.$wire;
    if (typeof w.call === 'function') {
        w.call('commitDraftCell', key, field, value);
        return true;
    }
    if (typeof w.commitDraftCell === 'function') {
        w.commitDraftCell(key, field, value);
        return true;
    }
    return false;
}

/**
 * Importes por curso (cuotas): teclado numérico / punto → coma en campos decimales;
 * flechas y Enter para moverse entre inputs y selects de la grilla.
 * Importe/valor: value en DOM + commitDraftCell en focusout (como calificaciones).
 * Signo/%/$: wire:model.live en el select.
 */
function seCiiBuildNavMatrix(tbody) {
    const matrix = [];
    tbody.querySelectorAll(':scope > tr.se-cii-tr').forEach((tr) => {
        const row = [];
        tr.querySelectorAll('[data-se-cii-nav]').forEach((el) => {
            row.push(el);
        });
        if (row.length) {
            matrix.push(row);
        }
    });
    return matrix;
}

function seCiiFindNavPos(matrix, el) {
    for (let r = 0; r < matrix.length; r++) {
        const c = matrix[r].indexOf(el);
        if (c >= 0) {
            return { row: r, col: c };
        }
    }
    return null;
}

function seCiiFocusNavCell(from, to) {
    if (!to) {
        return;
    }

    const focusTarget = () => {
        to.focus();
        if (to.tagName === 'INPUT' && typeof to.select === 'function') {
            to.select();
        }
        to.scrollIntoView({ block: 'nearest', inline: 'nearest' });
    };

    if (from && from !== to && document.activeElement === from) {
        from.blur();
        queueMicrotask(focusTarget);

        return;
    }

    focusTarget();
}

function seCiiInsertCommaDecimal(el) {
    const val = el.value ?? '';
    const start = el.selectionStart ?? val.length;
    const end = el.selectionEnd ?? start;
    if (val.includes(',')) {
        return;
    }
    el.value = val.slice(0, start) + ',' + val.slice(end);
    const pos = start + 1;
    el.setSelectionRange(pos, pos);
}

function seCiiIsNumpadDecimalKey(e) {
    return e.key === 'Decimal' || (e.key === '.' && e.code === 'NumpadDecimal');
}

/** VALOR: también el punto del teclado alfanumérico. IMPORTE: solo teclado numérico (el punto puede ser miles). */
function seCiiShouldConvertDecimalKeyToComma(el, e) {
    if (!el.hasAttribute('data-se-cii-decimal')) {
        return false;
    }
    if (seCiiIsNumpadDecimalKey(e)) {
        return true;
    }

    return el.hasAttribute('data-se-cii-valor') && e.key === '.' && e.code === 'Period';
}

function bindCuotasImportesForm() {
    document.querySelectorAll('[data-se-cii-tbody]').forEach((tbody) => {
        if (tbody._seCiiBound) {
            return;
        }
        tbody._seCiiBound = true;

        tbody.addEventListener(
            'focusin',
            (e) => {
                const el = e.target;
                if (!el || el.tagName !== 'INPUT' || !el.dataset.seCiiRowKey || !el.dataset.seCiiField) {
                    return;
                }
                el.dataset.seCiiLast = el.value ?? '';
            },
            true,
        );

        tbody.addEventListener(
            'focusout',
            (e) => {
                const el = e.target;
                if (!el || el.tagName !== 'INPUT' || !el.dataset.seCiiRowKey || !el.dataset.seCiiField) {
                    return;
                }
                const val = el.value ?? '';
                if (val === (el.dataset.seCiiLast ?? '')) {
                    return;
                }
                const root = el.closest('[wire\\:id]');
                if (!root) {
                    return;
                }
                seCiiCallCommitCell(root, el.dataset.seCiiRowKey, el.dataset.seCiiField, val);
            },
            true,
        );

        tbody.addEventListener(
            'keydown',
            (e) => {
                if (e.ctrlKey || e.metaKey || e.altKey) {
                    return;
                }
                const el = e.target;
                if (!el || !el.hasAttribute('data-se-cii-nav')) {
                    return;
                }

                if (el.tagName === 'INPUT' && seCiiShouldConvertDecimalKeyToComma(el, e)) {
                    e.preventDefault();
                    seCiiInsertCommaDecimal(el);
                    return;
                }

                const navKeys = ['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'Enter'];
                if (!navKeys.includes(e.key)) {
                    return;
                }

                const matrix = seCiiBuildNavMatrix(tbody);
                const pos = seCiiFindNavPos(matrix, el);
                if (!pos) {
                    return;
                }

                const nrows = matrix.length;
                const ncols = matrix[0] ? matrix[0].length : 0;
                if (!nrows || !ncols) {
                    return;
                }

                const { row, col } = pos;
                let nr = row;
                let nc = col;

                if (e.key === 'ArrowLeft') {
                    nc = col - 1;
                } else if (e.key === 'ArrowRight') {
                    nc = col + 1;
                } else if (e.key === 'ArrowUp') {
                    nr = row - 1;
                } else if (e.key === 'ArrowDown' || e.key === 'Enter') {
                    if (e.key === 'Enter' && row + 1 < nrows) {
                        nr = row + 1;
                        nc = col;
                    } else if (e.key === 'Enter' && col + 1 < ncols) {
                        nr = 0;
                        nc = col + 1;
                    } else if (e.key === 'ArrowDown') {
                        nr = row + 1;
                    } else {
                        return;
                    }
                }

                if (nr < 0 || nr >= nrows || nc < 0 || nc >= ncols) {
                    return;
                }

                const next = matrix[nr][nc];
                if (!next || next === el) {
                    return;
                }

                e.preventDefault();
                seCiiFocusNavCell(el, next);
            },
            true,
        );
    });
}

/**
 * Alinea cabecera y cuerpo cuando el tbody tiene barra vertical (scrollbar-gutter / overflow).
 */
function syncCierreHeadScrollbarGutter(head, body) {
    const gutter =
        body.scrollHeight > body.clientHeight
            ? Math.max(0, body.offsetWidth - body.clientWidth)
            : 0;
    head.style.paddingRight = `${gutter}px`;
}

/**
 * Cierre anual: cabecera de columnas fija; scroll vertical/horizontal en el cuerpo de la grilla.
 */
function bindCierreAnualGrillas() {
    document.querySelectorAll('.se-cierre-anual-grilla').forEach((grilla) => {
        if (grilla._seCierreBound) {
            return;
        }
        grilla._seCierreBound = true;

        const head = grilla.querySelector('[data-se-cierre-head]');
        const body = grilla.querySelector('[data-se-cierre-body]');
        if (!body) {
            return;
        }

        if (head) {
            const syncFromBody = () => {
                head.scrollLeft = body.scrollLeft;
                syncCierreHeadScrollbarGutter(head, body);
            };
            body.addEventListener('scroll', syncFromBody, { passive: true });
            syncFromBody();
            if (typeof ResizeObserver !== 'undefined') {
                const ro = new ResizeObserver(syncFromBody);
                ro.observe(body);
                ro.observe(head);
            }
            window.addEventListener('resize', syncFromBody, { passive: true });
        }

        grilla.addEventListener(
            'wheel',
            (e) => {
                if (body.scrollHeight <= body.clientHeight) {
                    return;
                }
                if (Math.abs(e.deltaY) <= Math.abs(e.deltaX)) {
                    return;
                }
                const maxTop = body.scrollHeight - body.clientHeight;
                const next = Math.min(maxTop, Math.max(0, body.scrollTop + e.deltaY));
                if (next === body.scrollTop) {
                    return;
                }
                e.preventDefault();
                body.scrollTop = next;
            },
            { passive: false },
        );
    });
}

document.addEventListener('DOMContentLoaded', () => {
    seCalifPrimBindNotaPickerDocClose();
    queueMicrotask(bindCalifCargaTablas);
    queueMicrotask(bindCalifPrimarioTablas);
    queueMicrotask(bindCalifPrimarioMateriaTablas);
    queueMicrotask(bindCuotasImportesForm);
    queueMicrotask(bindCierreAnualGrillas);
});

document.addEventListener('alpine:initialized', () => {
    scheduleSeShellPeekBootAfterAlpine();
});

window.addEventListener(
    'load',
    () => {
        scheduleSeShellPeekBootAfterAlpine();
    },
    { once: true },
);

/**
 * Fuerza el estado rail/ancho del sidebar según ruta (peek) tras la primera hidratación.
 * En la carga inicial Alpine/Livewire a veces deja `sidebarCollapsed` en el default hasta un morph/navegación;
 * `respectInteraction: false` ignora hover/focus para corregir ese primer frame.
 */
function triggerSeShellPeekBoot(respectInteraction = false) {
    const shell = document.getElementById('se-shell');
    if (!shell) {
        return;
    }
    const Alpine = window.Alpine;
    if (!Alpine || typeof Alpine.$data !== 'function') {
        return;
    }
    try {
        const data = Alpine.$data(shell);
        if (data && typeof data.applyPeekSidebarBootState === 'function') {
            data.applyPeekSidebarBootState(respectInteraction);
        }
    } catch (e) {
        // Alpine aún no hidrató el shell
    }
}

function scheduleSeShellPeekBootAfterAlpine() {
    queueMicrotask(() => triggerSeShellPeekBoot(false));
    requestAnimationFrame(() => triggerSeShellPeekBoot(false));
    window.setTimeout(() => triggerSeShellPeekBoot(false), 0);
    window.setTimeout(() => triggerSeShellPeekBoot(false), 80);
}

function triggerSeSidebarOverflowSync() {
    const shell = document.getElementById('se-shell');
    if (!shell) {
        return;
    }

    const Alpine = window.Alpine;
    if (Alpine && typeof Alpine.$data === 'function') {
        try {
            const data = Alpine.$data(shell);
            if (data && typeof data.applyPeekSidebarBootState === 'function') {
                data.applyPeekSidebarBootState(true);
            }
            if (data && typeof data.syncSidebarCollapse === 'function') {
                data.syncSidebarCollapse();
                return;
            }
        } catch (e) {
            // Alpine aún no hidrató el shell
        }
    }

    shell.dispatchEvent(new CustomEvent('se-sidebar-sync-overflow', { bubbles: false }));
}

document.addEventListener('livewire:navigated', () => {
    queueMicrotask(bindCalifCargaTablas);
    queueMicrotask(bindCalifPrimarioTablas);
    queueMicrotask(bindCalifPrimarioMateriaTablas);
    queueMicrotask(bindCuotasImportesForm);
    queueMicrotask(bindCierreAnualGrillas);
    queueMicrotask(triggerSeSidebarOverflowSync);
    window.setTimeout(triggerSeSidebarOverflowSync, 200);
});

document.addEventListener('livewire:init', () => {
    queueMicrotask(() => triggerSeShellPeekBoot(false));
    window.setTimeout(() => triggerSeShellPeekBoot(false), 50);

    const L = window.Livewire;
    if (L && typeof L.hook === 'function') {
        L.hook('morph.updated', () => {
            queueMicrotask(bindCalifCargaTablas);
            queueMicrotask(bindCalifPrimarioTablas);
    queueMicrotask(bindCalifPrimarioMateriaTablas);
            queueMicrotask(bindCuotasImportesForm);
            queueMicrotask(bindCierreAnualGrillas);
            queueMicrotask(triggerSeSidebarOverflowSync);
        });
    }
});

// Alpine.js es inyectado y gestionado por Livewire 4.
// NO importar ni iniciar Alpine aquí para evitar conflictos.
// Si necesitás plugins de Alpine, usá el hook de Livewire:
//
// import collapse from '@alpinejs/collapse';
// import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
// Alpine.plugin(collapse);
// Livewire.start();

/**
 * Enlaces WhatsApp manual (wa.me / web.whatsapp.com): reutilizar la misma ventana/pestaña.
 * <a target="nombre"> no es fiable con destinos externos; window.open(href, nombre) sí (gesto del usuario).
 */
/**
 * Editor HTML liviano (salidas educativas): fuentes, tamaños, enlaces; sin imágenes.
 */
/** Sincroniza todos los editores HTML livianos con Livewire (p. ej. antes de guardar). */
window.syncSeHtmlEditors = function syncSeHtmlEditors(root) {
    (root || document).querySelectorAll('.se-html-editor__area').forEach((el) => {
        el.dispatchEvent(new Event('blur'));
    });
};

window.seHtmlEditor = function seHtmlEditor(config) {
    const wireModel = config?.wireModel ?? 'formTexto';
    let initialHtml = config?.initialHtml ?? '';

    return {
        resolveHtml() {
            if (this.$wire && typeof this.$wire.get === 'function') {
                const fromWire = this.$wire.get(wireModel);
                if (fromWire != null && String(fromWire).trim() !== '') {
                    return String(fromWire);
                }
            }

            return initialHtml ?? '';
        },
        init() {
            this.$nextTick(() => {
                const html = this.resolveHtml();
                if (this.$refs.editor) {
                    this.$refs.editor.innerHTML = html;
                }
            });
        },
        resetEditor(html) {
            initialHtml = html ?? '';
            if (this.$refs.editor) {
                this.$refs.editor.innerHTML = this.resolveHtml();
            }
        },
        cmd(command, value = null) {
            this.$refs.editor?.focus();
            document.execCommand(command, false, value);
            this.stripImages();
            this.sync();
        },
        setFontFamily(family) {
            if (!family) {
                return;
            }
            this.cmd('fontName', family);
        },
        setFontSize(size) {
            if (!size) {
                return;
            }
            this.cmd('fontSize', size);
        },
        insertLink() {
            const url = window.prompt('URL del enlace (https://…)');
            if (!url) {
                return;
            }
            const trimmed = String(url).trim();
            if (!trimmed || /^javascript:/i.test(trimmed)) {
                return;
            }
            this.cmd('createLink', trimmed);
        },
        stripImages() {
            this.$refs.editor?.querySelectorAll('img').forEach((img) => img.remove());
        },
        onPaste(event) {
            event.preventDefault();
            const text = event.clipboardData?.getData('text/html')
                || event.clipboardData?.getData('text/plain')
                || '';
            const sanitized = text
                .replace(/<img\b[^>]*>/gi, '')
                .replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '');
            document.execCommand('insertHTML', false, sanitized);
            this.stripImages();
            this.sync();
        },
        sync() {
            this.stripImages();
            const html = this.$refs.editor?.innerHTML ?? '';
            if (this.$wire && typeof this.$wire.set === 'function') {
                this.$wire.set(wireModel, html);
            }
        },
    };
};

document.addEventListener('click', (e) => {
    const a = e.target.closest('a[data-se-wa-reuse="1"]');
    if (!a) {
        return;
    }
    if (e.defaultPrevented) {
        return;
    }
    if (e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) {
        return;
    }
    const winName = a.getAttribute('data-se-wa-window-name');
    if (!winName || !a.href) {
        return;
    }
    e.preventDefault();
    const w = window.open(a.href, winName);
    if (w) {
        try {
            w.focus();
        } catch {
            // ignorar restricciones cross-origin
        }
    }
});
