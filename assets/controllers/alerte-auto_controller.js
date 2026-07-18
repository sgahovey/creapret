import { Controller } from '@hotwired/stimulus';

/**
 * Fait disparaitre automatiquement une alerte apres un delai (par defaut 5 s).
 *
 * Evite un script inline (compatible avec une politique de securite de contenu stricte).
 * Delai configurable via data-alerte-auto-delai-value. Utilise l'API Bootstrap si presente
 * pour retirer proprement l'element du DOM, sinon retire la classe show en repli.
 */
export default class extends Controller {
    static values = { delai: { type: Number, default: 5000 } };

    connect() {
        this.timeout = window.setTimeout(() => {
            if (window.bootstrap && window.bootstrap.Alert) {
                window.bootstrap.Alert.getOrCreateInstance(this.element).close();
            } else {
                this.element.classList.remove('show');
            }
        }, this.delaiValue);
    }

    disconnect() {
        window.clearTimeout(this.timeout);
    }
}
