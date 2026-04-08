import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['note', 'source', 'copyButton', 'trigger'];
    static values = {
        expiresAt: String,
        maskedValue: { type: String, default: '••••••••••' },
        expiredMessage: { type: String, default: 'Fenêtre expirée. Validez à nouveau le TOTP pour consulter ce secret.' },
        revealLabel: { type: String, default: 'Révéler 60s' },
    };

    connect() {
        if (!this.hasExpiresAtValue || '' === this.expiresAtValue) {
            return;
        }

        const expiresAt = Date.parse(this.expiresAtValue);
        if (Number.isNaN(expiresAt)) {
            return;
        }

        const delay = expiresAt - Date.now();
        if (delay <= 0) {
            this.remask();

            return;
        }

        this.timeoutId = window.setTimeout(() => this.remask(), delay);
    }

    disconnect() {
        window.clearTimeout(this.timeoutId);
    }

    remask() {
        this.sourceTargets.forEach((element) => {
            element.textContent = this.maskedValueValue;
            element.classList.add('secret-box--masked');
        });

        this.copyButtonTargets.forEach((button) => {
            button.classList.add('is-hidden');
            button.disabled = true;
        });

        if (this.hasNoteTarget) {
            this.noteTarget.textContent = this.expiredMessageValue;
        }

        if (this.hasTriggerTarget) {
            this.triggerTarget.textContent = this.revealLabelValue;
        }
    }
}
