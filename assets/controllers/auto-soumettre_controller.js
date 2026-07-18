import { Controller } from '@hotwired/stimulus';

/*
 * Soumet le formulaire au changement d'un champ, en remplacement de onchange="this.form.submit()"
 * (interdit par la CSP). Masque a la connexion un bouton de repli (cible « bouton », optionnelle)
 * afin que le filtre reste utilisable sans JavaScript.
 *
 * Usage : sur le <form>, data-controller="auto-soumettre" ; sur le champ,
 * data-action="change->auto-soumettre#soumettre" ; sur le bouton de repli,
 * data-auto-soumettre-target="bouton".
 */
export default class extends Controller {
    static targets = ['bouton'];

    connect() {
        if (this.hasBoutonTarget) {
            this.boutonTarget.hidden = true;
        }
    }

    soumettre() {
        if (typeof this.element.requestSubmit === 'function') {
            this.element.requestSubmit();
        } else {
            this.element.submit();
        }
    }
}
