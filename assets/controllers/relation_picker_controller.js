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
        this.render();
    }

    disconnect() {
        this.wrapper?.remove();
    }

    buildUi() {
        this.wrapper = document.createElement('div');
        this.wrapper.className = 'relation-picker';

        this.searchInput = document.createElement('input');
        this.searchInput.type = 'search';
        this.searchInput.className = 'relation-picker__search';
        this.searchInput.placeholder = this.placeholderValue;
        this.searchInput.autocomplete = 'off';
        this.searchInput.addEventListener('input', () => this.render());

        this.selectedContainer = document.createElement('div');
        this.selectedContainer.className = 'relation-picker__selected';

        this.list = document.createElement('div');
        this.list.className = 'relation-picker__list';

        this.wrapper.append(this.searchInput, this.selectedContainer, this.list);
        this.element.insertAdjacentElement('afterend', this.wrapper);
    }

    render() {
        this.renderSelected();
        this.renderOptions();
        this.syncSelect();
    }

    renderSelected() {
        this.selectedContainer.innerHTML = '';
        const selected = this.options.filter((option) => option.selected);

        if (0 === selected.length) {
            const empty = document.createElement('div');
            empty.className = 'relation-picker__empty';
            empty.textContent = this.selectedLabelValue;
            this.selectedContainer.append(empty);

            return;
        }

        for (const option of selected) {
            const tag = document.createElement('button');
            tag.type = 'button';
            tag.className = 'relation-picker__tag';
            tag.textContent = option.label;
            tag.addEventListener('click', () => this.toggle(option.value, false));
            this.selectedContainer.append(tag);
        }
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
            item.textContent = option.label;
            item.addEventListener('click', () => this.toggle(option.value, true));
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

    syncSelect() {
        for (const domOption of Array.from(this.element.options)) {
            const state = this.options.find((option) => option.value === domOption.value);
            domOption.selected = state?.selected ?? false;
        }
    }
}
