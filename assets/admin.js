/**
 * FX Forms — admin field builder. Vanilla JS, no jQuery.
 */
(() => {
    'use strict';

    const builder = document.querySelector('.fxforms-builder');
    if (!builder) return;

    const initialConfig = JSON.parse(builder.dataset.config || '{}');
    const fieldTypes = JSON.parse(builder.dataset.types || '{}');
    const fieldWidths = JSON.parse(builder.dataset.widths || '{"full":"Full width","half":"Half width"}');
    const list = builder.querySelector('.fxforms-builder-fields');
    const hidden = builder.querySelector('#fxforms_fields');
    const addBtn = document.getElementById('fxforms-add-field');

    const el = (tag, attrs = null, children = null) => {
        const node = document.createElement(tag);
        if (attrs) {
            for (const [key, val] of Object.entries(attrs)) {
                if (val == null) continue;
                if (key === 'className') node.className = val;
                else if (key === 'text') node.textContent = val;
                else node.setAttribute(key, val);
            }
        }
        if (children) {
            for (const c of Array.isArray(children) ? children : [children]) {
                if (c == null) continue;
                node.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
            }
        }
        return node;
    };

    const randomId = () => 'f_' + Math.random().toString(36).slice(2, 8);

    const sync = () => {
        const items = [...list.querySelectorAll('.fxforms-builder-field')].map((li) => {
            const type = li.querySelector('[data-prop="type"]').value;
            const optionsValue = li.querySelector('[data-prop="options"]').value;
            const options = type === 'select'
                ? optionsValue.split(',').map((s) => s.trim()).filter(Boolean)
                : [];
            return {
                id: li.dataset.id,
                label: li.querySelector('[data-prop="label"]').value.trim(),
                type,
                required: li.querySelector('[data-prop="required"]').checked,
                width: li.querySelector('[data-prop="width"]').value,
                description: li.querySelector('[data-prop="description"]').value,
                options,
            };
        });
        hidden.value = JSON.stringify(items);
    };

    const renderField = (field) => {
        const typeSel = el('select', { 'data-prop': 'type', className: 'fxforms-builder-field-type' });
        for (const [val, label] of Object.entries(fieldTypes)) {
            const opt = el('option', { value: val, text: label });
            if (val === field.type) opt.selected = true;
            typeSel.appendChild(opt);
        }

        const labelInput = el('input', {
            type: 'text',
            'data-prop': 'label',
            value: field.label || '',
            placeholder: 'Field label',
            className: 'fxforms-builder-field-label',
        });

        const idLabel = el('code', {
            className: 'fxforms-builder-field-id',
            text: field.id || '',
        });

        const optionsInput = el('input', {
            type: 'text',
            'data-prop': 'options',
            value: Array.isArray(field.options) ? field.options.join(', ') : '',
            placeholder: 'Option A, Option B, Option C',
            className: 'fxforms-builder-field-options',
        });
        const optionsWrap = el('span', { className: 'fxforms-builder-field-options-wrap' }, optionsInput);
        optionsWrap.style.display = field.type === 'select' ? '' : 'none';

        const widthSel = el('select', { 'data-prop': 'width', className: 'fxforms-builder-field-width' });
        for (const [val, label] of Object.entries(fieldWidths)) {
            const opt = el('option', { value: val, text: label });
            if (val === (field.width || 'full')) opt.selected = true;
            widthSel.appendChild(opt);
        }

        const requiredInput = el('input', { type: 'checkbox', 'data-prop': 'required' });
        requiredInput.checked = !!field.required;
        const requiredLabel = el('label', { className: 'fxforms-builder-field-required' }, [requiredInput, ' Required']);

        const deleteBtn = el('button', {
            type: 'button',
            className: 'button-link button-link-delete fxforms-builder-field-delete',
            title: 'Delete field',
            'aria-label': 'Delete field',
        }, '×');

        const handle = el('span', {
            className: 'fxforms-builder-field-handle dashicons dashicons-menu',
            title: 'Drag to reorder',
            'aria-hidden': 'true',
        });

        const descInput = el('input', {
            type: 'text',
            'data-prop': 'description',
            value: field.description || '',
            placeholder: 'Help text shown under the field (optional)',
            className: 'fxforms-builder-field-desc',
        });

        const mainRow = el('div', { className: 'fxforms-builder-field-main' }, [
            handle, labelInput, typeSel, widthSel, requiredLabel, idLabel, deleteBtn,
        ]);
        const extraRow = el('div', { className: 'fxforms-builder-field-extra' }, [descInput, optionsWrap]);

        const li = el('li', {
            className: 'fxforms-builder-field',
            'data-id': field.id || randomId(),
        }, [mainRow, extraRow]);

        typeSel.addEventListener('change', () => {
            optionsWrap.style.display = typeSel.value === 'select' ? '' : 'none';
            sync();
        });
        labelInput.addEventListener('input', sync);
        requiredInput.addEventListener('change', sync);
        widthSel.addEventListener('change', sync);
        descInput.addEventListener('input', sync);
        optionsInput.addEventListener('input', sync);
        deleteBtn.addEventListener('click', () => {
            li.remove();
            sync();
        });

        return li;
    };

    const initial = Array.isArray(initialConfig.fields) ? initialConfig.fields : [];
    for (const f of initial) list.appendChild(renderField(f));

    addBtn?.addEventListener('click', () => {
        list.appendChild(renderField({
            id: randomId(),
            label: '',
            type: 'text',
            required: false,
            width: 'full',
            description: '',
            options: [],
        }));
        sync();
    });

    if (typeof window.Sortable !== 'undefined') {
        window.Sortable.create(list, {
            animation: 150,
            handle: '.fxforms-builder-field-handle',
            onEnd: sync,
        });
    }

    const copyBtn = document.querySelector('.fxforms-shortcode-copy');
    if (copyBtn) {
        copyBtn.addEventListener('click', async () => {
            const fb = copyBtn.parentElement.querySelector('.fxforms-shortcode-feedback');
            try {
                await navigator.clipboard.writeText(copyBtn.dataset.clipboard);
                fb.textContent = 'Copied!';
                fb.style.color = '#46b450';
                setTimeout(() => { fb.textContent = ''; }, 1500);
            } catch {
                fb.textContent = 'Copy failed';
                fb.style.color = '#a00';
            }
        });
    }

    sync();
})();
