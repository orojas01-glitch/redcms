(function (window, document) {
    'use strict';

    if (window.RedAdminFormBuilder) {
        window.RedAdminFormBuilder.init();
        return;
    }

    var FIELD_TYPES = {
        textfield: {label: 'Short text', short: 'Text', input: true},
        password: {label: 'Password', short: 'Password', input: true},
        textarea: {label: 'Long text', short: 'Long text', input: true},
        select: {label: 'Dropdown', short: 'Select', input: true, options: true},
        radio: {label: 'Radio group', short: 'Radio', input: true, options: true},
        checkbox: {label: 'Checkboxes', short: 'Check', input: true, options: true},
        paragraph: {label: 'Helpful text', short: 'Text block', input: false},
        hidden: {label: 'Hidden value', short: 'Hidden', input: true},
        button: {label: 'Submit button', short: 'Submit', input: false}
    };

    var RESERVED_NAMES = {
        alias: true,
        formtype: true,
        myspamtrap: true,
        recordid: true,
        refid: true,
        submit: true,
        updated: true,
        updatedate: true
    };

    /* These optional keys are consumed by the legacy public renderer. They are
     * intentionally source-backed rather than exposed as everyday controls. */
    var PASSTHROUGH_ATTRIBUTES = ['inputtype', 'autocomplete', 'placeholder', 'inputmode', 'checked'];

    var uidCounter = 0;

    function find(root, selector) {
        return root ? root.querySelector(selector) : null;
    }

    function findAll(root, selector) {
        return root ? Array.prototype.slice.call(root.querySelectorAll(selector)) : [];
    }

    function uid() {
        uidCounter += 1;
        return 'red-form-field-' + Date.now().toString(36) + '-' + uidCounter.toString(36);
    }

    function cleanText(value) {
        return String(value == null ? '' : value);
    }

    function safeDslValue(value, multiline) {
        var result = cleanText(value).replace(/\u0000/g, '');

        result = result.replace(/\|/g, '/').replace(/;/g, ',');
        if (!multiline) {
            result = result.replace(/[\r\n]+/g, ' ').replace(/\s{2,}/g, ' ');
        }
        return result.trim();
    }

    function safeOptionValue(value) {
        return cleanText(value)
            .replace(/\u0000/g, '')
            .replace(/[\r\n]+/g, ' ')
            .replace(/,/g, '，')
            .replace(/\^/g, '＾')
            .replace(/\*/g, '＊')
            .replace(/\|/g, '｜')
            .replace(/;/g, '；')
            .replace(/\s{2,}/g, ' ')
            .trim();
    }

    function normalizeMachineName(value) {
        var normalized = cleanText(value).trim();

        if (normalized.normalize) {
            normalized = normalized.normalize('NFKD').replace(/[\u0300-\u036f]/g, '');
        }
        normalized = normalized
            .replace(/([a-z0-9])([A-Z])/g, '$1_$2')
            .toLowerCase()
            .replace(/[^a-z0-9_]+/g, '_')
            .replace(/_+/g, '_')
            .replace(/^_+|_+$/g, '');

        if (!normalized) {
            normalized = 'field';
        }
        if (!/^[a-z_]/.test(normalized)) {
            normalized = 'field_' + normalized;
        }
        return normalized.slice(0, 64).replace(/_+$/g, '') || 'field';
    }

    function isInputField(field) {
        return !!(field && FIELD_TYPES[field.type] && FIELD_TYPES[field.type].input);
    }

    function isSubmit(field) {
        return !!(field && field.type === 'button');
    }

    function isLogin(root) {
        return cleanText(root.getAttribute('data-form-type')).toLowerCase() === 'login';
    }

    function schemaLocked(root) {
        return root.getAttribute('data-form-schema-locked') === 'true'
            || root.getAttribute('data-register-schema-locked') === 'true';
    }

    function structureLocked(root) {
        return isLogin(root) || schemaLocked(root);
    }

    function announce(root, message) {
        var live = find(root, '[data-form-announcer]');

        if (!live) {
            return;
        }
        live.textContent = '';
        window.setTimeout(function () {
            live.textContent = message;
        }, 20);
    }

    function setAlert(root, message, state) {
        var alert = find(root, '[data-form-builder-alert]');

        if (!alert) {
            return;
        }
        alert.textContent = message || '';
        alert.hidden = !message;
        alert.setAttribute('data-state', state || 'info');
    }

    function sourceField(root) {
        return find(root, '[data-form-definition-source]') || find(root, 'textarea[name="LongDesc"]');
    }

    function splitDefinition(definition) {
        return cleanText(definition).split(';');
    }

    function parseOptions(type, value) {
        var items = [];

        cleanText(value).split(',').forEach(function (entry, index) {
            var raw = entry.trim();
            var selected = false;
            var disabled = false;
            var label;
            var optionValue;
            var pair;
            var statePair;

            if (!raw && index > 0) {
                return;
            }

            if (type === 'checkbox' && /\*$/.test(raw)) {
                selected = true;
                raw = raw.slice(0, -1);
            }

            if (type === 'radio' && /\|$/.test(raw)) {
                selected = true;
                raw = raw.slice(0, -1);
            }

            if (type === 'select') {
                statePair = raw.split('^');
                if (statePair.length > 1 && /^(selected|disabled)$/i.test(statePair[statePair.length - 1])) {
                    disabled = statePair[statePair.length - 1].toLowerCase() === 'disabled';
                    selected = statePair[statePair.length - 1].toLowerCase() === 'selected';
                    statePair.pop();
                    raw = statePair.join('^');
                }
            }

            pair = raw.split('^');
            if (type !== 'select' && pair.length > 1) {
                label = pair.shift();
                optionValue = pair.join('^');
            } else {
                label = raw;
                optionValue = raw;
            }

            items.push({
                id: uid(),
                label: label || ('Option ' + (index + 1)),
                value: optionValue || label || ('option_' + (index + 1)),
                selected: selected,
                disabled: disabled
            });
        });

        if (!items.length) {
            items.push({id: uid(), label: 'Option 1', value: 'Option 1', selected: false, disabled: false});
        }
        return items;
    }

    function parseDefinition(definition) {
        var fields = [];
        var warnings = [];
        var unsupported = [];

        splitDefinition(definition).forEach(function (rowSource, rowIndex) {
            var rowText = rowSource.trim();
            var delimiter;
            var data = {};
            var field;

            if (!rowText) {
                return;
            }

            delimiter = rowText.indexOf('|') !== -1 ? '|' : '*';
            rowText.split(delimiter).forEach(function (part) {
                var piece = part.trim();
                var equals;
                var key;

                if (!piece || piece === '#') {
                    return;
                }
                equals = piece.indexOf('=');
                if (equals < 1) {
                    return;
                }
                key = piece.slice(0, equals).trim();
                if (key) {
                    data[key] = piece.slice(equals + 1);
                }
            });

            data.type = cleanText(data.type).trim().toLowerCase();
            if (!data.type || !FIELD_TYPES[data.type]) {
                unsupported.push(rowIndex + 1);
                return;
            }

            field = {
                id: uid(),
                type: data.type,
                question: cleanText(data.question),
                name: cleanText(data.name),
                required: cleanText(data.required).toLowerCase() !== 'false',
                displayname: cleanText(data.displayname),
                initialvalue: cleanText(data.initialvalue),
                readonly: cleanText(data.readonly).toLowerCase() === 'true',
                cols: parseInt(data.cols, 10) || 45,
                rows: parseInt(data.rows, 10) || 5,
                paragraph: cleanText(data.paragraph),
                options: FIELD_TYPES[data.type].options ? parseOptions(data.type, data.value) : [],
                attributes: {}
            };

            PASSTHROUGH_ATTRIBUTES.forEach(function (attribute) {
                if (Object.prototype.hasOwnProperty.call(data, attribute)) {
                    field.attributes[attribute] = cleanText(data[attribute]);
                }
            });

            if (isInputField(field) && !field.name) {
                warnings.push('Field ' + (rowIndex + 1) + ' has no machine name.');
            }
            fields.push(field);
        });

        if (unsupported.length) {
            warnings.push('Rows ' + unsupported.join(', ') + ' use source-only field syntax.');
        }

        return {
            fields: fields,
            warnings: warnings,
            unsupported: unsupported,
            ok: unsupported.length === 0
        };
    }

    function cloneOption(option) {
        return {
            id: uid(),
            label: cleanText(option.label),
            value: cleanText(option.value),
            selected: option.selected === true,
            disabled: option.disabled === true
        };
    }

    function cloneField(field) {
        var clone = {
            id: uid(),
            type: field.type,
            question: cleanText(field.question),
            name: cleanText(field.name),
            required: field.required === true,
            displayname: cleanText(field.displayname),
            initialvalue: cleanText(field.initialvalue),
            readonly: field.readonly === true,
            cols: parseInt(field.cols, 10) || 45,
            rows: parseInt(field.rows, 10) || 5,
            paragraph: cleanText(field.paragraph),
            options: (field.options || []).map(cloneOption),
            attributes: {}
        };

        PASSTHROUGH_ATTRIBUTES.forEach(function (attribute) {
            if (field.attributes && Object.prototype.hasOwnProperty.call(field.attributes, attribute)) {
                clone.attributes[attribute] = cleanText(field.attributes[attribute]);
            }
        });
        return clone;
    }

    function usedNames(fields, exceptId) {
        var names = {};

        fields.forEach(function (field) {
            if (field.id !== exceptId && isInputField(field)) {
                names[cleanText(field.name).toLowerCase()] = true;
            }
        });
        return names;
    }

    function uniqueMachineName(fields, suggestion, exceptId) {
        var base = normalizeMachineName(suggestion);
        var candidate = base;
        var names = usedNames(fields, exceptId);
        var counter = 2;

        while (names[candidate.toLowerCase()] || RESERVED_NAMES[candidate.toLowerCase()]) {
            candidate = (base.slice(0, 58) + '_' + counter).slice(0, 64);
            counter += 1;
        }
        return candidate;
    }

    function makeField(type, fields) {
        var defaults = {
            textfield: {displayname: 'Short answer', name: 'short_answer'},
            password: {displayname: 'Password', name: 'password'},
            textarea: {displayname: 'Long answer', name: 'long_answer', rows: 5},
            select: {displayname: 'Choose an option', name: 'selection'},
            radio: {displayname: 'Choose one', name: 'choice'},
            checkbox: {displayname: 'Choose all that apply', name: 'choices'},
            paragraph: {paragraph: 'Add helpful instructions for this part of the form.'},
            hidden: {name: 'hidden_value', initialvalue: ''},
            button: {displayname: 'Submit', name: 'Submit'}
        };
        var values = defaults[type] || defaults.textfield;
        var field = {
            id: uid(),
            type: FIELD_TYPES[type] ? type : 'textfield',
            question: '',
            name: cleanText(values.name),
            required: type !== 'paragraph' && type !== 'hidden' && type !== 'button',
            displayname: cleanText(values.displayname),
            initialvalue: cleanText(values.initialvalue),
            readonly: false,
            cols: 45,
            rows: values.rows || 5,
            paragraph: cleanText(values.paragraph),
            options: [],
            attributes: {}
        };

        if (field.type === 'select') {
            field.options = [
                {id: uid(), label: 'Choose an option', value: 'Choose an option', selected: true, disabled: false},
                {id: uid(), label: 'Option 1', value: 'Option 1', selected: false, disabled: false},
                {id: uid(), label: 'Option 2', value: 'Option 2', selected: false, disabled: false}
            ];
        } else if (FIELD_TYPES[field.type].options) {
            field.options = [
                {id: uid(), label: 'Option 1', value: 'Option 1', selected: false, disabled: false},
                {id: uid(), label: 'Option 2', value: 'Option 2', selected: false, disabled: false}
            ];
        }
        if (isInputField(field) && field.type !== 'password') {
            field.name = uniqueMachineName(fields, field.name);
        }
        return field;
    }

    function preset(type) {
        var normalized = cleanText(type).toLowerCase();
        var fields = [];

        function add(fieldType, label, name, required, extras) {
            var field = makeField(fieldType, fields);
            field.displayname = label || field.displayname;
            field.name = name || field.name;
            field.required = required === true;
            Object.keys(extras || {}).forEach(function (key) {
                field[key] = extras[key];
            });
            fields.push(field);
        }

        if (normalized === 'login') {
            add('textfield', 'Username', 'username', true);
            add('password', 'Password', 'password', true);
            add('button', 'Log in', 'Submit', false);
            return fields;
        }

        if (normalized === 'contact') {
            add('textfield', 'Full name', 'name', true);
            add('textfield', 'Email address', 'email', true);
            add('textfield', 'Telephone', 'telephone', false);
            add('textarea', 'Message', 'message', true, {rows: 6});
            add('button', 'Send message', 'Submit', false);
            return fields;
        }

        if (normalized === 'register') {
            add('textfield', 'Full name', 'full_name', true);
            add('textfield', 'Email address', 'email', true);
            add('textarea', 'Notes', 'message', false, {rows: 5});
            add('checkbox', 'Consent', 'consent', true, {
                options: [{id: uid(), label: 'I agree', value: 'Yes', selected: false, disabled: false}]
            });
            add('button', 'Register', 'Submit', false);
            return fields;
        }

        if (normalized === 'response') {
            add('textfield', 'Full name', 'full_name', true);
            add('textfield', 'Email address', 'email', true);
            add('textarea', 'Message', 'message', false, {rows: 5});
            add('button', 'Continue', 'Submit', false);
            return fields;
        }

        add('textfield', 'Full name', 'full_name', true);
        add('textfield', 'Email address', 'email', true);
        add('textarea', 'Message', 'message', false, {rows: 5});
        add('button', 'Submit', 'Submit', false);
        return fields;
    }

    function authorizedPreset(root, type) {
        var script = find(root, '[data-form-type-presets]');
        var definitions = {};
        var entry;
        var parsed;

        if (script) {
            try {
                definitions = JSON.parse(script.textContent || '{}');
            } catch (error) {
                definitions = {};
            }
        }
        entry = definitions && definitions[type] ? definitions[type] : null;
        if (entry && typeof entry.definition === 'string') {
            parsed = parseDefinition(entry.definition);
            if (parsed.ok && parsed.fields.length) {
                return {
                    fields: normalizeSubmit(parsed.fields),
                    response: typeof entry.response === 'string' ? entry.response : ''
                };
            }
        }
        return {fields: normalizeSubmit(preset(type)), response: ''};
    }

    function normalizeSubmit(fields) {
        var submit = null;
        var normalized = [];

        fields.forEach(function (field) {
            if (isSubmit(field)) {
                if (!submit) {
                    submit = field;
                }
                return;
            }
            normalized.push(field);
        });

        if (!submit) {
            submit = makeField('button', normalized);
        }
        submit.name = 'Submit';
        submit.required = false;
        submit.displayname = submit.displayname || 'Submit';
        normalized.push(submit);
        return normalized;
    }

    function serializeOptions(field) {
        return (field.options || []).map(function (option) {
            var label = safeOptionValue(option.label);
            var value = safeOptionValue(option.value);
            var result;

            if (field.type === 'select') {
                result = label || value;
                if (option.disabled) {
                    result += '^disabled';
                } else if (option.selected) {
                    result += '^selected';
                }
                return result;
            }

            result = label === value || !value ? label : label + '^' + value;
            if (field.type === 'checkbox' && option.selected) {
                result += '*';
            }
            /* A radio default marker is a pipe, which collides with the legacy
             * row delimiter. Keep radio groups deterministic and unselected. */
            return result;
        }).join(',');
    }

    function appendPassthroughAttributes(values, field) {
        PASSTHROUGH_ATTRIBUTES.forEach(function (attribute) {
            if (field.attributes && Object.prototype.hasOwnProperty.call(field.attributes, attribute)) {
                values.push(attribute + '=' + safeDslValue(field.attributes[attribute], false));
            }
        });
    }

    function serializeField(field) {
        var values = [];

        values.push('#');
        if (field.type === 'paragraph') {
            values.push('type=paragraph');
            values.push('paragraph=' + safeDslValue(field.paragraph, true));
            appendPassthroughAttributes(values, field);
            return values.join('|');
        }

        values.push('question=' + safeDslValue(field.question, false));
        values.push('name=' + (field.type === 'button' ? 'Submit' : safeDslValue(field.name, false)));
        values.push('type=' + field.type);

        if (field.type !== 'button') {
            values.push('required=' + (field.required ? 'true' : 'false'));
        }
        if (field.type !== 'hidden') {
            values.push('displayname=' + safeDslValue(field.displayname, false));
        }
        if (FIELD_TYPES[field.type].options) {
            values.push('value=' + serializeOptions(field));
        } else if (field.type !== 'button') {
            if (field.type === 'textarea') {
                values.push('readonly=' + (field.readonly ? 'true' : 'false'));
            }
            values.push('initialvalue=' + safeDslValue(field.initialvalue, field.type === 'textarea'));
            if (field.type === 'textarea') {
                values.push('cols=' + Math.max(1, Math.min(120, parseInt(field.cols, 10) || 45)));
                values.push('rows=' + Math.max(2, Math.min(30, parseInt(field.rows, 10) || 5)));
            }
        }
        appendPassthroughAttributes(values, field);
        return values.join('|');
    }

    function serialize(fields) {
        return normalizeSubmit(fields.slice()).map(serializeField).join(';\r\n') + ';';
    }

    function fieldById(root, id) {
        var match = null;

        (root._redFormBuilderState.fields || []).some(function (field) {
            if (field.id === id) {
                match = field;
                return true;
            }
            return false;
        });
        return match;
    }

    function fieldIndex(root, id) {
        var result = -1;

        (root._redFormBuilderState.fields || []).some(function (field, index) {
            if (field.id === id) {
                result = index;
                return true;
            }
            return false;
        });
        return result;
    }

    function updateSourceStats(root) {
        var source = sourceField(root);
        var stats = find(root, '[data-form-source-stats]');
        var lines;

        if (!source || !stats) {
            return;
        }
        lines = cleanText(source.value).split(/\r\n|\r|\n/).length;
        stats.textContent = lines + (lines === 1 ? ' line' : ' lines') + ' · '
            + source.value.length + (source.value.length === 1 ? ' character' : ' characters');
    }

    function updateCount(root) {
        var counter = find(root, '[data-form-field-count]');
        var count = root._redFormBuilderState.fields.length;

        if (counter) {
            counter.textContent = count + (count === 1 ? ' element' : ' elements');
        }
    }

    function setDirty(root, origin) {
        root._redFormBuilderState.dirty = true;
        root._redFormBuilderState.origin = origin || 'builder';
        root.setAttribute('data-form-builder-dirty', 'true');
    }

    function commitBuilder(root) {
        var source = sourceField(root);

        root._redFormBuilderState.fields = normalizeSubmit(root._redFormBuilderState.fields);
        if (source) {
            root._redFormBuilderSyncing = true;
            source.value = serialize(root._redFormBuilderState.fields);
            root._redFormBuilderSyncing = false;
            source.dispatchEvent(new window.Event('change', {bubbles: true}));
        }
        setDirty(root, 'builder');
        updateSourceStats(root);
        updateCount(root);
        setAlert(root, 'Form structure has unsaved changes.', 'changed');
    }

    function typeLabel(field) {
        return FIELD_TYPES[field.type] ? FIELD_TYPES[field.type].label : 'Field';
    }

    function fieldTitle(field) {
        if (field.type === 'paragraph') {
            return field.paragraph || 'Helpful text';
        }
        if (field.type === 'hidden') {
            return field.name || 'Hidden value';
        }
        return field.displayname || typeLabel(field);
    }

    function makeButton(label, hook, value, className) {
        var button = document.createElement('button');

        button.type = 'button';
        button.className = className || 'red-admin-form-field-action';
        button.setAttribute(hook, value || '');
        button.setAttribute('aria-label', label);
        button.title = label;
        button.textContent = label;
        return button;
    }

    function renderCard(root, field, index) {
        var card = document.createElement('li');
        var grip = document.createElement('span');
        var number = document.createElement('span');
        var copy = document.createElement('span');
        var title = document.createElement('strong');
        var meta = document.createElement('small');
        var controls = document.createElement('span');
        var locked = structureLocked(root);
        var earlier;
        var later;
        var duplicate;
        var remove;

        card.className = 'red-admin-form-field-card';
        card.setAttribute('data-form-field-card', '');
        card.setAttribute('data-field-id', field.id);
        card.setAttribute('data-field-type', field.type);
        card.setAttribute('aria-selected', root._redFormBuilderState.selectedId === field.id ? 'true' : 'false');
        card.draggable = !locked && !isSubmit(field);

        grip.className = 'red-admin-form-field-card__grip';
        grip.setAttribute('aria-hidden', 'true');
        grip.innerHTML = '<i></i><i></i><i></i><i></i><i></i><i></i>';

        number.className = 'red-admin-form-field-card__number';
        number.textContent = String(index + 1);

        copy.className = 'red-admin-form-field-card__copy';
        title.textContent = fieldTitle(field);
        meta.textContent = typeLabel(field)
            + (isInputField(field) && field.name ? ' · ' + field.name : '')
            + (field.required && !isSubmit(field) ? ' · Required' : '');
        copy.appendChild(title);
        copy.appendChild(meta);

        controls.className = 'red-admin-form-field-card__actions';
        earlier = makeButton('Move up', 'data-form-move-field', 'earlier');
        later = makeButton('Move down', 'data-form-move-field', 'later');
        duplicate = makeButton('Duplicate', 'data-form-duplicate-field', '');
        remove = makeButton('Delete', 'data-form-remove-field', '', 'red-admin-form-field-action red-admin-form-field-action--danger');
        earlier.disabled = locked || isSubmit(field) || index === 0;
        later.disabled = locked || isSubmit(field) || index >= root._redFormBuilderState.fields.length - 2;
        duplicate.disabled = locked || isSubmit(field);
        remove.disabled = locked || isSubmit(field);
        controls.appendChild(earlier);
        controls.appendChild(later);
        controls.appendChild(duplicate);
        controls.appendChild(remove);

        card.appendChild(grip);
        card.appendChild(number);
        card.appendChild(copy);
        card.appendChild(controls);
        return card;
    }

    function renderCanvas(root) {
        var list = find(root, '[data-form-field-list]');
        var empty = find(root, '[data-form-empty]');
        var fields = root._redFormBuilderState.fields;

        if (!list) {
            return;
        }
        while (list.firstChild) {
            list.removeChild(list.firstChild);
        }
        fields.forEach(function (field, index) {
            list.appendChild(renderCard(root, field, index));
        });
        if (empty) {
            empty.hidden = fields.length > 1;
        }
        updateCount(root);
    }

    function inputField(label, property, value, options) {
        var wrapper = document.createElement('label');
        var title = document.createElement('span');
        var input;
        var help;

        options = options || {};
        wrapper.className = 'red-admin-form-inspector-field';
        title.className = 'red-admin-form-inspector-field__label';
        title.textContent = label;
        wrapper.appendChild(title);

        if (options.multiline) {
            input = document.createElement('textarea');
            input.rows = options.rows || 3;
        } else {
            input = document.createElement('input');
            input.type = options.type || 'text';
        }
        input.value = cleanText(value);
        input.setAttribute('data-form-property', property);
        if (options.placeholder) {
            input.placeholder = options.placeholder;
        }
        if (options.maxlength) {
            input.maxLength = options.maxlength;
        }
        if (options.min != null) {
            input.min = options.min;
        }
        if (options.max != null) {
            input.max = options.max;
        }
        input.disabled = options.disabled === true;
        wrapper.appendChild(input);

        if (options.help) {
            help = document.createElement('small');
            help.className = 'red-admin-form-inspector-field__help';
            help.textContent = options.help;
            wrapper.appendChild(help);
        }
        return wrapper;
    }

    function choiceField(label, property, checked, disabled) {
        var wrapper = document.createElement('label');
        var input = document.createElement('input');
        var control = document.createElement('span');
        var copy = document.createElement('span');

        wrapper.className = 'red-admin-form-inspector-choice';
        input.type = 'checkbox';
        input.checked = checked === true;
        input.disabled = disabled === true;
        input.setAttribute('data-form-property', property);
        control.className = 'red-admin-form-inspector-choice__control';
        copy.textContent = label;
        wrapper.appendChild(input);
        wrapper.appendChild(control);
        wrapper.appendChild(copy);
        return wrapper;
    }

    function renderOptions(root, field, body) {
        var section = document.createElement('section');
        var heading = document.createElement('div');
        var title = document.createElement('strong');
        var add = makeButton('Add option', 'data-form-add-option', '', 'red-admin-form-option-add');
        var locked = structureLocked(root);

        section.className = 'red-admin-form-options';
        heading.className = 'red-admin-form-options__heading';
        title.textContent = 'Choices';
        add.textContent = '+ Add option';
        add.disabled = locked;
        heading.appendChild(title);
        heading.appendChild(add);
        section.appendChild(heading);

        (field.options || []).forEach(function (option, index) {
            var row = document.createElement('div');
            var number = document.createElement('span');
            var label = document.createElement('input');
            var value = document.createElement('input');
            var state = document.createElement('select');
            var remove;

            row.className = 'red-admin-form-option-row';
            row.setAttribute('data-form-option-row', String(index));
            number.textContent = String(index + 1);
            number.className = 'red-admin-form-option-row__number';
            label.type = 'text';
            label.value = option.label;
            label.placeholder = field.type === 'select' ? 'Option text' : 'Label';
            label.setAttribute('aria-label', 'Option ' + (index + 1) + ' label');
            label.setAttribute('data-form-option-property', 'label');
            label.disabled = locked;
            value.type = 'text';
            value.value = option.value;
            value.placeholder = 'Stored value';
            value.setAttribute('aria-label', 'Option ' + (index + 1) + ' stored value');
            value.setAttribute('data-form-option-property', 'value');
            value.disabled = locked;

            state.setAttribute('data-form-option-property', 'state');
            state.setAttribute('aria-label', 'Option ' + (index + 1) + ' state');
            state.innerHTML = '<option value="">Normal</option><option value="selected">Default</option>'
                + (field.type === 'select' ? '<option value="disabled">Disabled</option>' : '');
            state.value = option.disabled ? 'disabled' : (option.selected ? 'selected' : '');
            if (field.type === 'radio') {
                state.disabled = true;
                state.title = 'Legacy radio defaults are not safely representable.';
            }
            if (locked) {
                state.disabled = true;
                state.title = 'This stored form structure is read-only.';
            }

            remove = makeButton('Remove option ' + (index + 1), 'data-form-remove-option', String(index), 'red-admin-form-option-remove');
            remove.textContent = 'Remove';
            remove.disabled = locked || field.options.length < 2;
            row.appendChild(number);
            row.appendChild(label);
            if (field.type !== 'select') {
                row.appendChild(value);
            }
            row.appendChild(state);
            row.appendChild(remove);
            section.appendChild(row);
        });
        body.appendChild(section);
    }

    function renderInspector(root) {
        var inspector = find(root, '[data-form-field-inspector]');
        var field = fieldById(root, root._redFormBuilderState.selectedId);
        var empty;
        var header;
        var body;
        var locked = structureLocked(root);

        if (!inspector) {
            return;
        }
        inspector.hidden = false;
        inspector.innerHTML = '';
        if (!field) {
            empty = document.createElement('div');
            empty.className = 'red-admin-form-inspector-empty';
            empty.innerHTML = '<span aria-hidden="true">&#8592;</span><strong>Select an element</strong><p>Choose a field in the form canvas to edit its label, validation and choices.</p>';
            inspector.appendChild(empty);
            return;
        }

        header = document.createElement('div');
        header.className = 'red-admin-form-inspector__heading';
        header.innerHTML = '<span>' + FIELD_TYPES[field.type].short + '</span><div><strong></strong><small>Field settings</small></div>';
        find(header, 'strong').textContent = fieldTitle(field);
        inspector.appendChild(header);

        body = document.createElement('div');
        body.className = 'red-admin-form-inspector__body';

        if (field.type === 'paragraph') {
            body.appendChild(inputField('Instruction text', 'paragraph', field.paragraph, {
                multiline: true,
                rows: 5,
                disabled: locked,
                help: 'Plain text or simple inline HTML already supported by the legacy form renderer.'
            }));
        } else if (field.type === 'hidden') {
            body.appendChild(inputField('Machine name', 'name', field.name, {
                disabled: locked,
                maxlength: 64,
                help: 'Letters, numbers and underscores only. This value is never shown to visitors.'
            }));
            body.appendChild(inputField('Stored value', 'initialvalue', field.initialvalue, {
                disabled: locked,
                help: 'Avoid secrets: hidden values remain visible in page source.'
            }));
        } else if (field.type === 'button') {
            body.appendChild(inputField('Button label', 'displayname', field.displayname, {
                disabled: locked,
                maxlength: 80
            }));
        } else {
            body.appendChild(inputField('Visible label', 'displayname', field.displayname, {
                disabled: locked,
                maxlength: 160
            }));
            body.appendChild(inputField('Machine name', 'name', field.name, {
                disabled: locked || schemaLocked(root),
                maxlength: 64,
                help: 'Stable identifier used when the form is submitted.'
            }));
            body.appendChild(inputField('Helper text above field', 'question', field.question, {
                disabled: locked,
                maxlength: 240,
                placeholder: 'Optional guidance'
            }));
            body.appendChild(choiceField('Required field', 'required', field.required, locked));

            if (field.type === 'textfield' || field.type === 'password') {
                body.appendChild(inputField('Starting value', 'initialvalue', field.initialvalue, {
                    disabled: locked,
                    help: field.type === 'password' ? 'Leave blank. Never prefill a password.' : 'Optional value shown when the form opens.'
                }));
            }
            if (field.type === 'textarea') {
                body.appendChild(inputField('Starting text', 'initialvalue', field.initialvalue, {
                    multiline: true,
                    rows: 4,
                    disabled: locked
                }));
                body.appendChild(inputField('Visible rows', 'rows', field.rows, {
                    type: 'number', min: 2, max: 30, disabled: locked
                }));
                body.appendChild(choiceField('Read only', 'readonly', field.readonly, locked));
            }
            if (FIELD_TYPES[field.type].options) {
                renderOptions(root, field, body);
            }
        }
        inspector.appendChild(body);
    }

    function selectField(root, id, focusInspector) {
        var field = fieldById(root, id);
        var card;

        if (!field) {
            return;
        }
        root._redFormBuilderState.selectedId = id;
        findAll(root, '[data-form-field-card]').forEach(function (item) {
            item.setAttribute('aria-selected', item.getAttribute('data-field-id') === id ? 'true' : 'false');
        });
        renderInspector(root);
        card = find(root, '[data-form-field-card][data-field-id="' + id + '"]');
        if (focusInspector) {
            var first = find(root, '[data-form-field-inspector] input:not(:disabled), [data-form-field-inspector] textarea:not(:disabled), [data-form-field-inspector] select:not(:disabled)');
            if (first) {
                first.focus();
            } else if (card) {
                card.focus();
            }
        }
    }

    function updateSelectedCard(root) {
        var id = root._redFormBuilderState.selectedId;
        var index = fieldIndex(root, id);
        var oldCard = find(root, '[data-form-field-card][data-field-id="' + id + '"]');

        if (oldCard && index >= 0) {
            oldCard.parentNode.replaceChild(renderCard(root, root._redFormBuilderState.fields[index], index), oldCard);
        }
    }

    function addField(root, type, atIndex) {
        var state = root._redFormBuilderState;
        var field;
        var submitIndex;

        if (!FIELD_TYPES[type] || type === 'button' || structureLocked(root)) {
            announce(root, structureLocked(root) ? 'This form structure is locked.' : 'That element cannot be added.');
            return;
        }
        if (type === 'password' && !isLogin(root)) {
            setAlert(root, 'Password fields are reserved for the protected Admin Login form.', 'warning');
            announce(root, 'Password fields are reserved for Admin Login.');
            return;
        }

        field = makeField(type, state.fields);
        submitIndex = Math.max(0, state.fields.length - 1);
        if (typeof atIndex !== 'number' || atIndex < 0 || atIndex > submitIndex) {
            atIndex = submitIndex;
        }
        state.fields.splice(atIndex, 0, field);
        state.selectedId = field.id;
        commitBuilder(root);
        renderCanvas(root);
        renderInspector(root);
        announce(root, typeLabel(field) + ' added as element ' + (atIndex + 1) + '.');
    }

    function moveField(root, id, directionOrIndex) {
        var state = root._redFormBuilderState;
        var from = fieldIndex(root, id);
        var field;
        var to;

        if (structureLocked(root) || from < 0 || isSubmit(state.fields[from])) {
            return;
        }
        if (typeof directionOrIndex === 'number') {
            to = directionOrIndex;
        } else {
            to = from + (directionOrIndex === 'earlier' ? -1 : 1);
        }
        to = Math.max(0, Math.min(state.fields.length - 2, to));
        if (from === to) {
            return;
        }
        field = state.fields.splice(from, 1)[0];
        state.fields.splice(to, 0, field);
        commitBuilder(root);
        renderCanvas(root);
        renderInspector(root);
        announce(root, fieldTitle(field) + ' moved to position ' + (to + 1) + '.');
    }

    function duplicateField(root, id) {
        var state = root._redFormBuilderState;
        var index = fieldIndex(root, id);
        var source;
        var duplicate;

        if (structureLocked(root) || index < 0 || isSubmit(state.fields[index])) {
            return;
        }
        source = state.fields[index];
        duplicate = cloneField(source);
        if (isInputField(duplicate)) {
            duplicate.name = uniqueMachineName(state.fields, source.name + '_copy');
        }
        state.fields.splice(index + 1, 0, duplicate);
        state.selectedId = duplicate.id;
        commitBuilder(root);
        renderCanvas(root);
        renderInspector(root);
        announce(root, fieldTitle(source) + ' duplicated.');
    }

    function removeField(root, id) {
        var state = root._redFormBuilderState;
        var index = fieldIndex(root, id);
        var field;

        if (structureLocked(root) || index < 0 || isSubmit(state.fields[index])) {
            return;
        }
        field = state.fields[index];
        state.fields.splice(index, 1);
        state.selectedId = state.fields[Math.min(index, state.fields.length - 1)].id;
        commitBuilder(root);
        renderCanvas(root);
        renderInspector(root);
        announce(root, fieldTitle(field) + ' deleted.');
    }

    function switchWorkspace(root, mode, focusPanel) {
        var state = root._redFormBuilderState;
        var source = sourceField(root);
        var parsed;

        if (mode === 'builder' && state.origin === 'source' && source) {
            parsed = parseDefinition(source.value);
            if (!parsed.ok) {
                setAlert(root, 'Some source rows cannot be represented in the visual builder. Keep editing in Expert source to preserve them.', 'warning');
                mode = 'source';
            } else {
                state.fields = normalizeSubmit(parsed.fields);
                state.selectedId = state.fields.length ? state.fields[0].id : null;
                state.origin = 'builder';
                renderCanvas(root);
                renderInspector(root);
            }
        }

        state.workspace = mode;
        findAll(root, '[data-form-workspace-tab]').forEach(function (tab) {
            var active = tab.getAttribute('data-form-workspace-tab') === mode;
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
            tab.tabIndex = active ? 0 : -1;
        });
        findAll(root, '[data-form-workspace-panel]').forEach(function (panel) {
            panel.hidden = panel.getAttribute('data-form-workspace-panel') !== mode;
        });
        root.setAttribute('data-form-workspace', mode);
        if (focusPanel) {
            var target = find(root, '[data-form-workspace-panel="' + mode + '"]');
            var focusable = target && find(target, 'button:not(:disabled), input:not(:disabled), textarea:not(:disabled), select:not(:disabled), [tabindex="0"]');
            if (focusable) {
                focusable.focus();
            }
        }
    }

    function updateConditionalPanels(root, type) {
        var normalized = cleanText(type).toLowerCase();

        findAll(root, '[data-form-types]').forEach(function (panel) {
            var types = cleanText(panel.getAttribute('data-form-types')).toLowerCase().split(/\s+/);
            var active = types.indexOf(normalized) !== -1;

            panel.hidden = !active;
            findAll(panel, 'input[name], select[name], textarea[name], button[name]').forEach(function (control) {
                if (control.getAttribute('data-form-always-enabled') === 'true') {
                    return;
                }
                if (!active) {
                    if (!control.disabled) {
                        control.setAttribute('data-form-was-enabled', 'true');
                    }
                    control.disabled = true;
                } else if (control.matches('[data-form-conditional-control]')) {
                    control.disabled = false;
                    control.removeAttribute('data-form-was-enabled');
                } else if (control.getAttribute('data-form-was-enabled') === 'true') {
                    control.disabled = false;
                    control.removeAttribute('data-form-was-enabled');
                }
            });
        });
    }

    function purposeChange(root, select) {
        var state = root._redFormBuilderState;
        var next = select.value;
        var current = root.getAttribute('data-form-type') || '';
        var shouldReplace = cleanText(sourceField(root) ? sourceField(root).value : '').trim() !== '';
        var selectedPreset;
        var response;

        if (root.getAttribute('data-form-mode') === 'edit') {
            select.value = current;
            return;
        }

        if (shouldReplace && next !== current && !window.confirm('Switch form purpose and replace the current field structure with a focused starter form?')) {
            select.value = current;
            return;
        }

        root.setAttribute('data-form-type', next);
        root.setAttribute('data-form-subtype', next);
        selectedPreset = authorizedPreset(root, next);
        state.fields = selectedPreset.fields;
        state.selectedId = state.fields[0].id;
        state.origin = 'builder';
        response = find(root, '[data-form-response-source]');
        if (response) {
            response.value = selectedPreset.response;
        }
        commitBuilder(root);
        updateConditionalPanels(root, next);
        updatePurposeCopy(root, next);
        renderCanvas(root);
        renderInspector(root);
        updateLocks(root);
        announce(root, next + ' form starter loaded.');
    }

    function updatePurposeCopy(root, type) {
        var metadata = {
            Contact: ['Contact form', 'Collect an inquiry and deliver it to the configured recipients.', 'Email delivery'],
            Response: ['Response form', 'Collect fields and return a custom confirmation after submission.', 'Custom response'],
            Register: ['Registration form', 'Collect a registration in a system-managed storage table.', 'Managed storage'],
            Other: ['Display-only form', 'Render a legacy field layout without a submission handler.', 'Display-only / no submission'],
            Login: ['Administrator login', 'Use the protected username and password contract for administrator access.', 'Secure sign-in']
        };
        var current = metadata[type] || [type || 'Form', 'Build and arrange the fields used by this form.', 'Form submission'];
        var label = find(root, '[data-form-purpose-label]');
        var copy = find(root, '[data-form-purpose-copy]');
        var outcome = find(root, '[data-form-outcome]');
        var badge = find(root, '.red-admin-form-header__badge');

        if (label) {
            label.textContent = current[0];
        }
        if (copy) {
            copy.textContent = current[1];
        }
        if (outcome) {
            outcome.textContent = current[2];
        }
        if (badge) {
            badge.textContent = current[0];
        }
    }

    function updateLocks(root) {
        var locked = structureLocked(root);
        var source = sourceField(root);

        findAll(root, '[data-form-add-field]').forEach(function (button) {
            button.disabled = locked || button.getAttribute('data-form-add-field') === 'password';
            button.draggable = !button.disabled;
        });
        if (source) {
            source.readOnly = locked;
            source.setAttribute('aria-readonly', locked ? 'true' : 'false');
        }
        root.setAttribute('data-form-structure-locked', locked ? 'true' : 'false');
    }

    function onInspectorInput(root, target, final) {
        var state = root._redFormBuilderState;
        var field = fieldById(root, state.selectedId);
        var property = target.getAttribute('data-form-property');
        var optionProperty = target.getAttribute('data-form-option-property');
        var optionRow;
        var optionIndex;
        var option;
        var value;

        if (!field || structureLocked(root)) {
            return;
        }

        if (optionProperty) {
            optionRow = target.closest('[data-form-option-row]');
            optionIndex = optionRow ? parseInt(optionRow.getAttribute('data-form-option-row'), 10) : -1;
            option = field.options[optionIndex];
            if (!option) {
                return;
            }
            if (optionProperty === 'state') {
                option.selected = target.value === 'selected';
                option.disabled = target.value === 'disabled';
                if (option.selected && field.type !== 'checkbox') {
                    field.options.forEach(function (candidate) {
                        if (candidate !== option) {
                            candidate.selected = false;
                        }
                    });
                }
            } else {
                value = safeOptionValue(target.value);
                option[optionProperty] = value;
                if (value !== cleanText(target.value).trim()) {
                    target.value = value;
                    setAlert(
                        root,
                        'Choice punctuation was converted to full-width characters so the legacy form format stays intact.',
                        'info'
                    );
                }
                if (optionProperty === 'label' && field.type === 'select') {
                    option.value = option.label;
                } else if (optionProperty === 'label' && !option.value) {
                    option.value = option.label;
                }
            }
            commitBuilder(root);
            updateSelectedCard(root);
            return;
        }

        if (!property) {
            return;
        }
        value = target.type === 'checkbox' ? target.checked : target.value;

        if (property === 'name') {
            if (!final) {
                return;
            }
            value = uniqueMachineName(state.fields, value, field.id);
            target.value = value;
        } else if (property === 'rows') {
            value = Math.max(2, Math.min(30, parseInt(value, 10) || 5));
            target.value = value;
        } else if (typeof value === 'string') {
            value = safeDslValue(value, property === 'paragraph' || property === 'initialvalue');
        }

        field[property] = value;
        commitBuilder(root);
        updateSelectedCard(root);
    }

    function addOption(root) {
        var field = fieldById(root, root._redFormBuilderState.selectedId);
        var number;

        if (!field || !FIELD_TYPES[field.type].options || structureLocked(root)) {
            return;
        }
        number = field.options.length + 1;
        field.options.push({
            id: uid(),
            label: 'Option ' + number,
            value: 'Option ' + number,
            selected: false,
            disabled: false
        });
        commitBuilder(root);
        renderInspector(root);
        announce(root, 'Option ' + number + ' added.');
    }

    function removeOption(root, index) {
        var field = fieldById(root, root._redFormBuilderState.selectedId);

        if (!field || !field.options || field.options.length < 2 || structureLocked(root)) {
            return;
        }
        field.options.splice(index, 1);
        commitBuilder(root);
        renderInspector(root);
        announce(root, 'Option removed.');
    }

    function copySource(root, button) {
        var source = sourceField(root);
        var text = source ? source.value : '';

        function done(ok) {
            var label = find(button, '[data-copy-label]');
            var original = button.getAttribute('data-form-copy-label') || (label ? label.textContent : button.textContent);
            button.classList.toggle('is-copied', ok);
            button.classList.toggle('has-copy-error', !ok);
            if (label) {
                label.textContent = ok ? 'Copied' : 'Copy failed';
            } else {
                button.textContent = ok ? 'Copied' : 'Copy failed';
            }
            announce(root, ok ? 'Definition copied to clipboard.' : 'Unable to copy definition.');
            window.setTimeout(function () {
                button.classList.remove('is-copied', 'has-copy-error');
                if (label) {
                    label.textContent = original;
                } else {
                    button.textContent = original;
                }
            }, 1800);
        }

        if (window.navigator.clipboard && window.navigator.clipboard.writeText) {
            window.navigator.clipboard.writeText(text).then(function () { done(true); }, function () { done(false); });
            return;
        }
        try {
            source.focus();
            source.select();
            done(document.execCommand('copy'));
        } catch (error) {
            done(false);
        }
    }

    function validateBuilder(root) {
        var source = sourceField(root);
        var parsed;
        var names = {};
        var invalid = null;

        if (root._redFormBuilderState.origin === 'source') {
            parsed = parseDefinition(source ? source.value : '');
            if (!parsed.ok && cleanText(root.getAttribute('data-form-type')).toLowerCase() !== 'other') {
                setAlert(root, 'Expert source contains a field type this form cannot safely publish.', 'error');
                switchWorkspace(root, 'source', true);
                return false;
            }
            if (!parsed.fields.length) {
                setAlert(root, 'Add at least one field and a Submit button before saving.', 'error');
                switchWorkspace(root, 'source', true);
                return false;
            }
            if (parsed.fields.filter(isSubmit).length !== 1) {
                setAlert(root, 'Expert source must contain exactly one Submit button.', 'error');
                switchWorkspace(root, 'source', true);
                return false;
            }
            parsed.fields.some(function (field) {
                var name;
                if (!isInputField(field)) {
                    return false;
                }
                name = cleanText(field.name);
                if (!/^[A-Za-z_][A-Za-z0-9_]{0,63}$/.test(name)
                        || RESERVED_NAMES[name.toLowerCase()]
                        || names[name.toLowerCase()]) {
                    invalid = field;
                    return true;
                }
                names[name.toLowerCase()] = true;
                return false;
            });
            if (invalid) {
                setAlert(root, 'Expert source contains a missing, duplicate, or reserved machine name.', 'error');
                switchWorkspace(root, 'source', true);
                return false;
            }
            return true;
        }

        if (root._redFormBuilderState.origin === 'initial'
                && source
                && source.value === root._redFormBuilderState.originalSource
                && !(root.getAttribute('data-form-mode') === 'create' && source.value.trim() === '')) {
            return true;
        }

        root._redFormBuilderState.fields.some(function (field) {
            var name;

            if (!isInputField(field)) {
                return false;
            }
            name = cleanText(field.name);
            if (!/^[A-Za-z_][A-Za-z0-9_]{0,63}$/.test(name)
                    || RESERVED_NAMES[name.toLowerCase()]
                    || names[name.toLowerCase()]) {
                invalid = field;
                return true;
            }
            names[name.toLowerCase()] = true;
            return false;
        });

        if (invalid) {
            setAlert(root, 'Every field needs a unique, safe machine name before saving.', 'error');
            switchWorkspace(root, 'builder');
            selectField(root, invalid.id, true);
            return false;
        }
        commitBuilder(root);
        return true;
    }

    function bindTabs(root) {
        var tabs = findAll(root, '[data-form-workspace-tab]');

        tabs.forEach(function (tab, index) {
            tab.addEventListener('click', function () {
                switchWorkspace(root, tab.getAttribute('data-form-workspace-tab'), true);
            });
            tab.addEventListener('keydown', function (event) {
                var targetIndex = index;

                if (event.key === 'ArrowRight') {
                    targetIndex = (index + 1) % tabs.length;
                } else if (event.key === 'ArrowLeft') {
                    targetIndex = (index - 1 + tabs.length) % tabs.length;
                } else if (event.key === 'Home') {
                    targetIndex = 0;
                } else if (event.key === 'End') {
                    targetIndex = tabs.length - 1;
                } else {
                    return;
                }
                event.preventDefault();
                tabs[targetIndex].focus();
                switchWorkspace(root, tabs[targetIndex].getAttribute('data-form-workspace-tab'));
            });
        });
    }

    function bindPalette(root) {
        findAll(root, '[data-form-add-field]').forEach(function (button) {
            var type = button.getAttribute('data-form-add-field');

            button.addEventListener('click', function () {
                addField(root, type);
            });
            button.addEventListener('dragstart', function (event) {
                if (button.disabled || structureLocked(root)) {
                    event.preventDefault();
                    return;
                }
                root._redFormBuilderState.dragType = type;
                root.setAttribute('data-form-dragging', 'palette');
                if (event.dataTransfer) {
                    event.dataTransfer.effectAllowed = 'copy';
                    event.dataTransfer.setData('application/x-red-form-field-type', type);
                    event.dataTransfer.setData('text/plain', type);
                }
            });
            button.addEventListener('dragend', function () {
                root._redFormBuilderState.dragType = null;
                root.removeAttribute('data-form-dragging');
                findAll(root, '.is-drop-target').forEach(function (item) { item.classList.remove('is-drop-target'); });
            });
        });
    }

    function bindCanvas(root) {
        var list = find(root, '[data-form-field-list]');

        if (!list) {
            return;
        }

        list.addEventListener('click', function (event) {
            var card = event.target.closest('[data-form-field-card]');
            var move = event.target.closest('[data-form-move-field]');
            var duplicate = event.target.closest('[data-form-duplicate-field]');
            var remove = event.target.closest('[data-form-remove-field]');
            var id = card ? card.getAttribute('data-field-id') : '';

            if (!card) {
                return;
            }
            if (move) {
                moveField(root, id, move.getAttribute('data-form-move-field'));
                return;
            }
            if (duplicate) {
                duplicateField(root, id);
                return;
            }
            if (remove) {
                removeField(root, id);
                return;
            }
            selectField(root, id, event.detail === 0);
        });

        list.addEventListener('dragstart', function (event) {
            var card = event.target.closest('[data-form-field-card]');

            if (!card || structureLocked(root) || card.getAttribute('data-field-type') === 'button') {
                event.preventDefault();
                return;
            }
            root._redFormBuilderState.dragId = card.getAttribute('data-field-id');
            card.classList.add('is-dragging');
            root.setAttribute('data-form-dragging', 'field');
            if (event.dataTransfer) {
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('application/x-red-form-field-id', root._redFormBuilderState.dragId);
                event.dataTransfer.setData('text/plain', root._redFormBuilderState.dragId);
            }
        });

        list.addEventListener('dragend', function () {
            root._redFormBuilderState.dragId = null;
            root.removeAttribute('data-form-dragging');
            findAll(root, '.is-dragging, .is-drop-target').forEach(function (item) {
                item.classList.remove('is-dragging', 'is-drop-target');
            });
        });

        list.addEventListener('dragover', function (event) {
            var card = event.target.closest('[data-form-field-card]');

            if (structureLocked(root)) {
                return;
            }
            event.preventDefault();
            if (event.dataTransfer) {
                event.dataTransfer.dropEffect = root._redFormBuilderState.dragType ? 'copy' : 'move';
            }
            findAll(list, '.is-drop-target').forEach(function (item) { item.classList.remove('is-drop-target'); });
            if (card && card.getAttribute('data-field-type') !== 'button') {
                card.classList.add('is-drop-target');
            }
        });

        list.addEventListener('drop', function (event) {
            var card = event.target.closest('[data-form-field-card]');
            var targetIndex = card ? fieldIndex(root, card.getAttribute('data-field-id')) : root._redFormBuilderState.fields.length - 1;
            var type = root._redFormBuilderState.dragType;
            var id = root._redFormBuilderState.dragId;

            event.preventDefault();
            if (structureLocked(root)) {
                return;
            }
            if (type) {
                addField(root, type, targetIndex);
            } else if (id) {
                moveField(root, id, targetIndex);
            }
            root._redFormBuilderState.dragType = null;
            root._redFormBuilderState.dragId = null;
            root.removeAttribute('data-form-dragging');
        });
    }

    function bindInspector(root) {
        var inspector = find(root, '[data-form-field-inspector]');

        if (!inspector) {
            return;
        }
        inspector.addEventListener('input', function (event) {
            if (event.target.matches('[data-form-property]:not([data-form-property="name"]), [data-form-option-property]')) {
                onInspectorInput(root, event.target, false);
            }
        });
        inspector.addEventListener('change', function (event) {
            if (event.target.matches('[data-form-property], [data-form-option-property]')) {
                onInspectorInput(root, event.target, true);
                if (event.target.matches('[data-form-option-property="state"]')) {
                    renderInspector(root);
                }
            }
        });
        inspector.addEventListener('focusout', function (event) {
            if (event.target.matches('[data-form-property="name"]')) {
                onInspectorInput(root, event.target, true);
            }
        });
        inspector.addEventListener('click', function (event) {
            var add = event.target.closest('[data-form-add-option]');
            var remove = event.target.closest('[data-form-remove-option]');

            if (add) {
                addOption(root);
            } else if (remove) {
                removeOption(root, parseInt(remove.getAttribute('data-form-remove-option'), 10));
            }
        });
    }

    function bindSource(root) {
        var source = sourceField(root);
        var copy = find(root, '[data-form-copy-source]');

        if (source) {
            source.addEventListener('input', function () {
                if (root._redFormBuilderSyncing) {
                    return;
                }
                root._redFormBuilderState.origin = 'source';
                setDirty(root, 'source');
                updateSourceStats(root);
                setAlert(root, 'Expert source has unsaved changes.', 'changed');
            });
        }
        if (copy) {
            var copyLabel = find(copy, '[data-copy-label]');
            copy.setAttribute('data-form-copy-label', copyLabel ? copyLabel.textContent : copy.textContent);
            copy.addEventListener('click', function () {
                copySource(root, copy);
            });
        }
    }

    function formMode(form) {
        return form && form.getAttribute('data-form-mode') === 'edit' ? 'edit' : 'create';
    }

    function setMessage(form, message, state) {
        var box = find(form, '[data-form-message]')
            || find(form, '#msggbox_insert_form')
            || find(form, '#msggbox_update_form');

        if (!box) {
            return;
        }
        box.textContent = message || '';
        box.hidden = !message;
        box.setAttribute('data-state', state || 'info');
    }

    function setSaving(form, saving) {
        var button = find(form, '[data-form-save]') || find(form, '#save');
        var label;
        var defaultLabel;

        if (!button) {
            return;
        }
        label = find(button, '[data-save-label]');
        defaultLabel = button.getAttribute('data-default-label')
            || (formMode(form) === 'edit' ? 'Save changes' : 'Save form');
        button.disabled = saving;
        button.setAttribute('aria-busy', saving ? 'true' : 'false');
        if (label) {
            label.textContent = saving ? 'Saving…' : defaultLabel;
        }
    }

    function initializeDateControls(form) {
        findAll(form, '[data-form-date]').forEach(function (dateInput) {
            var field = dateInput.closest('.red-admin-field');
            var payload = field ? find(field, '[data-date-payload]') : null;
            var original = dateInput.getAttribute('data-original-date');

            if (!payload || original === null) {
                return;
            }

            function synchronize() {
                var changed = dateInput.value !== original;
                payload.disabled = !changed;
                payload.value = changed ? dateInput.value : '';
            }

            dateInput.addEventListener('input', synchronize);
            dateInput.addEventListener('change', synchronize);
            synchronize();
        });
    }

    function initializeAdvancedPanel(form) {
        var details = find(form, '[data-form-advanced]');
        var storageKey = 'red-admin-' + formMode(form) + '-form-advanced-open';

        if (!details) {
            return;
        }
        try {
            details.open = window.sessionStorage.getItem(storageKey) === 'true';
            details.addEventListener('toggle', function () {
                window.sessionStorage.setItem(storageKey, details.open ? 'true' : 'false');
            });
        } catch (error) {
            /* The disclosure still works when session storage is unavailable. */
        }
    }

    function initializeRemovalChoices(form) {
        findAll(form, '[data-form-remove-image]').forEach(function (choice) {
            var current = choice.closest('[data-current-media]');

            function synchronize() {
                if (current) {
                    current.classList.toggle('is-marked-for-removal', choice.checked);
                }
            }
            choice.addEventListener('change', synchronize);
            synchronize();
        });
    }

    function fileExtension(name) {
        var parts = cleanText(name).toLowerCase().split('.');
        return parts.length > 1 ? parts.pop() : '';
    }

    function validateImage(form, file) {
        var allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        var allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        var maxBytes = parseInt(form.getAttribute('data-max-image-bytes'), 10) || (2 * 1024 * 1024);

        if (!file) {
            return 'Choose an image to upload.';
        }
        if (file.size <= 0) {
            return 'The selected image is empty.';
        }
        if (file.size > maxBytes) {
            return 'That image is larger than 2 MB.';
        }
        if (allowedTypes.indexOf(file.type) === -1 && allowedExtensions.indexOf(fileExtension(file.name)) === -1) {
            return 'Choose a JPG, PNG, or GIF image.';
        }
        return '';
    }

    function parseUploadResponse(xhr) {
        try {
            return xhr.responseText ? JSON.parse(xhr.responseText) : {};
        } catch (error) {
            return {status: xhr.responseText || 'The upload server returned an invalid response.'};
        }
    }

    function updateUploadPreview(uploader, file) {
        var preview = find(uploader, '[data-upload-preview]');
        var image = find(uploader, '[data-upload-preview-image]');
        var fileName = find(uploader, '[data-upload-file-name]');
        var reader;

        if (!preview || !image || !fileName) {
            return;
        }
        fileName.textContent = file.name;
        preview.hidden = false;
        reader = new window.FileReader();
        reader.addEventListener('load', function (event) {
            image.src = event.target.result;
            image.alt = 'Preview of ' + file.name;
        });
        reader.readAsDataURL(file);
    }

    function synchronizeUploadedImage(uploader, response) {
        var field = uploader.getAttribute('data-upload-field');
        var storedName = response && typeof response.stored_name === 'string' ? response.stored_name : '';
        var valueInput = find(uploader, '[data-upload-value]');
        var removeInput = find(uploader, '[data-form-remove-image]');
        var current = find(uploader, '[data-current-media]');
        var currentImage = find(uploader, '[data-current-image]');
        var currentName = find(uploader, '[data-current-name]');
        var previewImage = find(uploader, '[data-upload-preview-image]');

        if (!field || !storedName || !valueInput || valueInput.name !== field) {
            return false;
        }
        valueInput.value = storedName;
        if (removeInput) {
            removeInput.checked = false;
            removeInput.dispatchEvent(new window.Event('change', {bubbles: true}));
        }
        if (currentName) {
            currentName.textContent = storedName;
        }
        if (currentImage && previewImage && previewImage.src) {
            currentImage.src = previewImage.src;
            currentImage.alt = 'Current image ' + storedName;
        }
        if (current) {
            current.hidden = false;
            current.classList.remove('is-marked-for-removal');
        }
        return true;
    }

    function initializeUploader(form, uploader) {
        var input = find(uploader, '[data-upload-input]');
        var browse = find(uploader, '[data-upload-browse]');
        var dropzone = find(uploader, '[data-upload-dropzone]');
        var status = find(uploader, '[data-upload-status]');
        var progress = find(uploader, '[data-upload-progress]');
        var uploadUrl = uploader.getAttribute('data-upload-url');

        if (!input || !browse || !dropzone || !status || !progress || !uploadUrl) {
            return;
        }

        function uploadStatus(message, state) {
            status.textContent = message;
            status.setAttribute('data-state', state || 'info');
        }

        function finish() {
            uploader.setAttribute('aria-busy', 'false');
            input.disabled = false;
            browse.disabled = false;
            form._redFormUploadsInFlight = Math.max(0, (form._redFormUploadsInFlight || 1) - 1);
        }

        function upload(file) {
            var validation = validateImage(form, file);
            var payload;
            var xhr;
            var csrf;

            if (validation) {
                uploader.classList.remove('is-complete');
                uploader.classList.add('has-error');
                uploadStatus(validation, 'error');
                return;
            }

            uploader.classList.remove('has-error', 'is-complete');
            uploader.setAttribute('aria-busy', 'true');
            input.disabled = true;
            browse.disabled = true;
            progress.style.width = '0%';
            uploadStatus('Uploading ' + file.name + '…', 'progress');
            updateUploadPreview(uploader, file);
            form._redFormUploadsInFlight = (form._redFormUploadsInFlight || 0) + 1;

            payload = new window.FormData();
            payload.append('pic', file, file.name);
            xhr = new window.XMLHttpRequest();
            xhr.open('POST', uploadUrl, true);
            csrf = find(form, 'input[name="csrf_token"]');
            if (csrf && csrf.value) {
                xhr.setRequestHeader('X-CSRF-Token', csrf.value);
            } else if (window.RED_CSRF_TOKEN) {
                xhr.setRequestHeader('X-CSRF-Token', window.RED_CSRF_TOKEN);
            }

            xhr.upload.addEventListener('progress', function (event) {
                if (event.lengthComputable) {
                    progress.style.width = Math.round((event.loaded / event.total) * 100) + '%';
                }
            });

            xhr.addEventListener('load', function () {
                var response = parseUploadResponse(xhr);
                var successful = xhr.status >= 200 && xhr.status < 300;

                if (successful && !synchronizeUploadedImage(uploader, response)) {
                    successful = false;
                    response.status = 'The server did not confirm the stored image name.';
                }
                if (successful) {
                    progress.style.width = '100%';
                    uploader.classList.add('is-complete');
                    uploadStatus('Uploaded successfully', 'success');
                    setMessage(form, 'Image uploaded. Save the form when you are ready.', 'success');
                } else {
                    progress.style.width = '0%';
                    uploader.classList.add('has-error');
                    uploadStatus(response.status || 'The image could not be uploaded.', 'error');
                    setMessage(form, 'The image could not be uploaded. Review the message and try again.', 'error');
                }
                input.value = '';
                finish();
            });

            xhr.addEventListener('error', function () {
                progress.style.width = '0%';
                uploader.classList.add('has-error');
                uploadStatus('The upload could not reach the server.', 'error');
                setMessage(form, 'The image upload could not reach the server.', 'error');
                input.value = '';
                finish();
            });
            xhr.send(payload);
        }

        browse.addEventListener('click', function () {
            input.click();
        });
        input.addEventListener('change', function () {
            if (input.files && input.files.length) {
                upload(input.files[0]);
            }
        });
        ['dragenter', 'dragover'].forEach(function (eventName) {
            dropzone.addEventListener(eventName, function (event) {
                event.preventDefault();
                event.stopPropagation();
                dropzone.classList.add('is-dragging');
            });
        });
        ['dragleave', 'dragend'].forEach(function (eventName) {
            dropzone.addEventListener(eventName, function (event) {
                event.preventDefault();
                event.stopPropagation();
                dropzone.classList.remove('is-dragging');
            });
        });
        dropzone.addEventListener('drop', function (event) {
            event.preventDefault();
            event.stopPropagation();
            dropzone.classList.remove('is-dragging');
            if (event.dataTransfer && event.dataTransfer.files && event.dataTransfer.files.length) {
                upload(event.dataTransfer.files[0]);
            }
        });
    }

    function validateForm(form) {
        var title = find(form, '[name="Title"]');
        var titleError = find(form, '[data-form-title-error]');
        var start = find(form, '[data-form-date="start"]') || find(form, '[name="StartDate"]');
        var expiration = find(form, '[data-form-date="expiration"]') || find(form, '[name="ExpDate"]');
        var advanced = find(form, '[data-form-advanced]');

        if (!title || !cleanText(title.value).trim()) {
            if (title) {
                title.setAttribute('aria-invalid', 'true');
                title.focus();
            }
            if (titleError) {
                titleError.hidden = false;
            }
            setMessage(form, 'Add a title before saving the form.', 'error');
            return false;
        }
        title.removeAttribute('aria-invalid');
        if (titleError) {
            titleError.hidden = true;
        }

        if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
            if (typeof form.reportValidity === 'function') {
                form.reportValidity();
            }
            setMessage(form, 'Complete the required fields before saving the form.', 'error');
            return false;
        }

        if (start && expiration && start.value && expiration.value && expiration.value < start.value) {
            if (advanced) {
                advanced.open = true;
            }
            expiration.setAttribute('aria-invalid', 'true');
            expiration.focus();
            setMessage(form, 'The expiration date must be on or after the start date.', 'error');
            return false;
        }
        if (expiration) {
            expiration.removeAttribute('aria-invalid');
        }
        if ((form._redFormUploadsInFlight || 0) > 0) {
            setMessage(form, 'Wait for the image upload to finish before saving.', 'warning');
            return false;
        }
        return validateBuilder(form);
    }

    function submitForm(form) {
        var mode = formMode(form);
        var submitUrl = form.getAttribute('data-submit-url')
            || (mode === 'edit' ? '/admin/bin/update_form.php' : '/admin/bin/insert_form.php');

        if (!validateForm(form)) {
            return false;
        }
        setSaving(form, true);
        setMessage(form, 'Saving form…', 'progress');

        if (!window.jQuery || typeof window.jQuery.ajax !== 'function') {
            setSaving(form, false);
            setMessage(form, 'The form could not be saved because the administrator request tools are unavailable.', 'error');
            return false;
        }

        window.jQuery.ajax({
            type: 'POST',
            url: submitUrl,
            data: window.jQuery(form).serialize(),
            success: function (data) {
                if (cleanText(data).trim() === 'yes') {
                    setMessage(form, mode === 'edit' ? 'Changes saved. Refreshing the editor…' : 'Form added. Refreshing the editor…', 'success');
                    window.setTimeout(function () { window.location.reload(); }, 650);
                    return;
                }
                setSaving(form, false);
                setMessage(form, mode === 'edit'
                    ? 'The changes could not be saved. Review the fields and try again.'
                    : 'The form could not be added. Review the fields and try again.', 'error');
            },
            error: function () {
                setSaving(form, false);
                setMessage(form, 'The save request could not reach the server. Try again.', 'error');
            }
        });
        return false;
    }

    function deleteForm(form) {
        var button = find(form, '[data-form-delete]');
        var recordId = find(form, 'input[name="RecordID"]');
        var artRecordId = find(form, 'input[name="ArtRecordID"]');
        var csrf = find(form, 'input[name="csrf_token"]');
        var deleteUrl = form.getAttribute('data-delete-url') || '/admin/bin/delete_label.php';

        if (!button || !recordId || !artRecordId || !csrf) {
            return false;
        }
        if (!window.confirm('Delete this form permanently? This action cannot be undone.')) {
            return false;
        }
        if (!window.jQuery || typeof window.jQuery.ajax !== 'function') {
            setMessage(form, 'The form could not be deleted because the administrator request tools are unavailable.', 'error');
            return false;
        }

        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        setMessage(form, 'Deleting form…', 'progress');
        window.jQuery.ajax({
            type: 'POST',
            url: deleteUrl,
            data: {RecordID: recordId.value, ArtRecordID: artRecordId.value, T: 'form', csrf_token: csrf.value},
            success: function (data) {
                var response = cleanText(data).trim();
                if (response === 'yes' || response === 'yesyes') {
                    setMessage(form, 'Form deleted. Refreshing the content list…', 'success');
                    window.setTimeout(function () { window.location.reload(); }, 650);
                    return;
                }
                button.disabled = false;
                button.setAttribute('aria-busy', 'false');
                setMessage(form, 'The form could not be deleted. Try again.', 'error');
            },
            error: function () {
                button.disabled = false;
                button.setAttribute('aria-busy', 'false');
                setMessage(form, 'The delete request could not reach the server. Try again.', 'error');
            }
        });
        return false;
    }

    function bindFormSubmit(root) {
        var form = root.closest('form');

        if (!form || form.getAttribute('data-red-form-builder-submit-bound') === 'true') {
            return;
        }
        form.setAttribute('data-red-form-builder-submit-bound', 'true');
        form.addEventListener('submit', function (event) {
            if (!validateBuilder(root)) {
                event.preventDefault();
                event.stopImmediatePropagation();
            }
        }, true);
    }

    function initRoot(root) {
        var source;
        var parsed;
        var fields;
        var purpose;
        var safeCreatePreset = null;

        if (!root || root.getAttribute('data-red-form-builder-ready') === 'true') {
            return;
        }
        root.setAttribute('data-red-form-builder-ready', 'true');
        source = sourceField(root);
        parsed = parseDefinition(source ? source.value : '');
        if (!parsed.ok && root.getAttribute('data-form-mode') === 'create') {
            safeCreatePreset = authorizedPreset(root, root.getAttribute('data-form-type'));
        }
        fields = safeCreatePreset
            ? safeCreatePreset.fields
            : (parsed.fields.length ? normalizeSubmit(parsed.fields) : authorizedPreset(root, root.getAttribute('data-form-type')).fields);

        root._redFormBuilderState = {
            fields: fields,
            selectedId: fields.length ? fields[0].id : null,
            originalSource: source ? source.value : '',
            dirty: false,
            origin: 'initial',
            workspace: parsed.ok ? 'builder' : 'source',
            dragType: null,
            dragId: null
        };

        /* Deliberately do not serialize here. Existing LongDesc bytes stay exact
         * until a person changes the visual builder or Expert source. */
        if (safeCreatePreset && source) {
            root._redFormBuilderSyncing = true;
            source.value = serialize(fields);
            root._redFormBuilderSyncing = false;
            root._redFormBuilderState.originalSource = source.value;
            parsed = {ok: true, warnings: []};
        }
        renderCanvas(root);
        renderInspector(root);
        updateSourceStats(root);
        updateLocks(root);
        updateConditionalPanels(root, root.getAttribute('data-form-type'));
        bindTabs(root);
        bindPalette(root);
        bindCanvas(root);
        bindInspector(root);
        bindSource(root);
        bindFormSubmit(root);
        initializeDateControls(root);
        initializeAdvancedPanel(root);
        initializeRemovalChoices(root);
        root._redFormUploadsInFlight = 0;
        findAll(root, '[data-form-upload]').forEach(function (uploader) {
            initializeUploader(root, uploader);
        });
        var deleteButton = find(root, '[data-form-delete]');
        if (deleteButton) {
            deleteButton.addEventListener('click', function () {
                deleteForm(root);
            });
        }

        purpose = find(root, '[data-form-type-select]');
        if (purpose) {
            purpose.addEventListener('change', function () {
                purposeChange(root, purpose);
            });
        }

        switchWorkspace(root, parsed.ok ? 'builder' : 'source');
        if (!parsed.ok) {
            setAlert(root, 'This legacy definition contains source-only rows. It remains unchanged in Expert source.', 'warning');
        } else if (parsed.warnings.length) {
            setAlert(root, parsed.warnings[0], 'warning');
        }
    }

    function init() {
        findAll(document, '[data-red-form-builder]').forEach(initRoot);
    }

    window.RedAdminFormBuilder = {
        init: init,
        parse: parseDefinition,
        serialize: serialize,
        submit: submitForm,
        remove: deleteForm
    };

    window.run_insert_form = function (form) {
        return submitForm(form);
    };

    window.run_update_form = function (form) {
        return submitForm(form);
    };

    window.run_deleterecord = function () {
        return deleteForm(find(document, 'form[data-red-form-builder]'));
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}(window, document));
