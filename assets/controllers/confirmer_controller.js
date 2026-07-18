import { Controller } from '@hotwired/stimulus';

/*
 * Confirmation de soumission de formulaire, en remplacement de onsubmit="return confirm(...)"
 * (interdit par la CSP a script-src strict, sans 'unsafe-inline').
 *
 * Usage : sur le <form>, data-controller="confirmer" + data-action="submit->confirmer#demander"
 * et data-confirmer-message-value="<question>". Sans JavaScript, le formulaire se soumet
 * directement : degrade mais fonctionnel.
 */
export default class extends Controller {
    static values = { message: { type: String, default: 'Confirmer cette action ?' } };

    demander(event) {
        if (!window.confirm(this.messageValue)) {
            event.preventDefault();
        }
    }
}
