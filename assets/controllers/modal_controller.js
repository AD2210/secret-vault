import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['overlay', 'panel'];
    static classes = ['open'];

    connect() {
        if (this.element.dataset.modalOpen === 'true') {
            this.open();
        }
    }

    open(event) {
        if (event) {
            event.preventDefault();
        }

        this.overlayTarget.classList.add(this.openClass);
        document.body.classList.add('modal-open');

        const autofocusTarget = this.panelTarget.querySelector('input, button, textarea, select');
        autofocusTarget?.focus();
    }

    close(event) {
        if (event) {
            event.preventDefault();
        }

        this.overlayTarget.classList.remove(this.openClass);
        document.body.classList.remove('modal-open');
    }

    closeOnBackdrop(event) {
        if (event.target === this.overlayTarget) {
            this.close();
        }
    }

    closeOnEscape(event) {
        if ('Escape' === event.key) {
            this.close();
        }
    }
}
