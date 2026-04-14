import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        placeholder: { type: String, default: 'Rechercher...' },
        emptyLabel: { type: String, default: 'Aucune option disponible.' },
        selectedLabel: { type: String, default: 'Aucune sélection.' },
    };

    connect() {
        if (!(this.element instanceof HTMLSelectElement) || !this.element.multiple) {
            return;
        }

        this.options = Array.from(this.element.options).map((option) => ({
            value: option.value,
            label: option.textContent?.trim() ?? option.value,
            selected: option.selected,
        }));

        this.element.classList.add('is-hidden');
        this.buildUi();
        this.bindFormSubmit();
        this.render();
    }

    disconnect() {
        this.form?.removeEventListener('submit', this.submitListener);
        document.removeEventListener('click', this.documentClickListener, true);
        this.wrapper?.remove();
    }

    buildUi() {
        this.wrapper = document.createElement('div');
        this.wrapper.className = 'relation-picker';

        this.control = document.createElement('button');
        this.control.type = 'button';
        this.control.className = 'relation-picker__control';
        this.control.addEventListener('click', () => {
            this.open();
            this.searchInput.focus();
        });

        this.selectedContainer = document.createElement('div');
        this.selectedContainer.className = 'relation-picker__selected';

        this.searchInput = document.createElement('input');
        this.searchInput.type = 'search';
        this.searchInput.className = 'relation-picker__search';
        this.searchInput.placeholder = this.placeholderValue;
        this.searchInput.autocomplete = 'off';
        this.searchInput.addEventListener('focus', () => this.open());
        this.searchInput.addEventListener('input', () => {
            this.open();
            this.render();
        });
        this.searchInput.addEventListener('keydown', (event) => this.handleKeydown(event));

        this.summary = document.createElement('div');
        this.summary.className = 'relation-picker__summary';

        this.list = document.createElement('div');
        this.list.className = 'relation-picker__dropdown';

        this.control.append(this.selectedContainer, this.searchInput);
        this.wrapper.append(this.control, this.summary, this.list);
        this.element.insertAdjacentElement('afterend', this.wrapper);

        this.documentClickListener = (event) => {
            if (!this.wrapper.contains(event.target)) {
                this.close();
            }
        };
        document.addEventListener('click', this.documentClickListener, true);
    }

    bindFormSubmit() {
        this.form = this.element.form;
        if (!this.form) {
            return;
        }

        this.submitListener = () => this.syncSelect();
        this.form.addEventListener('submit', this.submitListener);
    }

    render() {
        this.renderSelected();
        this.renderSummary();
        this.renderOptions();
        this.syncSelect();
    }

    renderSelected() {
        this.selectedContainer.innerHTML = '';
        const selected = this.options.filter((option) => option.selected);

        if (0 === selected.length) {
            this.control.classList.remove('has-selection');
            return;
        }

        this.control.classList.add('has-selection');

        for (const option of selected) {
            const tag = document.createElement('span');
            tag.className = 'relation-picker__tag';

            const label = document.createElement('span');
            label.className = 'relation-picker__tag-label';
            label.textContent = option.label;

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'relation-picker__tag-remove';
            remove.setAttribute('aria-label', `Retirer ${option.label}`);
            remove.textContent = '×';
            remove.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                this.toggle(option.value, false);
                this.open();
                this.searchInput.focus();
            });

            tag.append(label, remove);
            this.selectedContainer.append(tag);
        }
    }

    renderSummary() {
        const selectedCount = this.options.filter((option) => option.selected).length;
        this.summary.textContent = 0 === selectedCount
            ? this.selectedLabelValue
            : `${selectedCount} sélection${selectedCount > 1 ? 's' : ''}`;
    }

    renderOptions() {
        this.list.innerHTML = '';
        const query = this.searchInput.value.trim().toLowerCase();
        const visible = this.options.filter((option) => {
            if (option.selected) {
                return false;
            }

            return '' === query || option.label.toLowerCase().includes(query);
        });

        if (0 === visible.length) {
            const empty = document.createElement('div');
            empty.className = 'relation-picker__empty';
            empty.textContent = this.emptyLabelValue;
            this.list.append(empty);

            return;
        }

        for (const option of visible) {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'relation-picker__option';
            item.addEventListener('click', () => {
                this.toggle(option.value, true);
                this.open();
                this.searchInput.focus();
            });

            const label = document.createElement('span');
            label.textContent = option.label;

            const add = document.createElement('span');
            add.className = 'relation-picker__option-add';
            add.textContent = 'Ajouter';

            item.append(label, add);
            this.list.append(item);
        }
    }

    toggle(value, selected) {
        const option = this.options.find((item) => item.value === value);
        if (!option) {
            return;
        }

        option.selected = selected;
        this.render();
    }

    handleKeydown(event) {
        if ('Backspace' === event.key && '' === this.searchInput.value.trim()) {
            const selected = this.options.filter((option) => option.selected);
            const last = selected.at(-1);
            if (last) {
                this.toggle(last.value, false);
            }
        }

        if ('Escape' === event.key) {
            this.close();
            this.searchInput.blur();
        }
    }

    open() {
        this.wrapper.classList.add('is-open');
    }

    close() {
        this.wrapper.classList.remove('is-open');
    }

    syncSelect() {
        for (const domOption of Array.from(this.element.options)) {
            const state = this.options.find((option) => option.value === domOption.value);
            domOption.selected = state?.selected ?? false;
        }
    }
}
