import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['source', 'button'];
    static values = {
        logUrl: String,
    };

    async copy() {
        const value = this.sourceTarget.textContent?.trim() ?? '';
        if ('' === value) {
            return;
        }

        try {
            await navigator.clipboard.writeText(value);
            this.logCopy();
            this.flashState('Copié', true);
        } catch (error) {
            this.flashState('Échec', false);
        }
    }

    logCopy() {
        if (!this.hasLogUrlValue || '' === this.logUrlValue) {
            return;
        }

        void fetch(this.logUrlValue, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
    }

    flashState(label, success) {
        if (!this.hasButtonTarget) {
            return;
        }

        this.buttonTarget.setAttribute('title', label);
        this.buttonTarget.setAttribute('aria-label', label);
        this.buttonTarget.classList.toggle('is-success', success);
        this.buttonTarget.classList.toggle('is-error', !success);

        window.clearTimeout(this.timeoutId);
        this.timeoutId = window.setTimeout(() => {
            this.buttonTarget.setAttribute('title', 'Copier');
            this.buttonTarget.setAttribute('aria-label', 'Copier');
            this.buttonTarget.classList.remove('is-success', 'is-error');
        }, 1600);
    }
}
