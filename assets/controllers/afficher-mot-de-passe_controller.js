import { Controller } from '@hotwired/stimulus';

/**
 * Bascule la visibilite d'un champ mot de passe (afficher / masquer).
 *
 * Amelioration progressive : sans JavaScript, le champ reste un mot de passe classique.
 * Cible le champ et l'icone ; met a jour le type, l'icone et l'etat d'accessibilite.
 */
export default class extends Controller {
    static targets = ['champ', 'icone'];

    basculer(event) {
        if (!this.hasChampTarget) {
            return;
        }

        const estMasque = this.champTarget.type === 'password';
        this.champTarget.type = estMasque ? 'text' : 'password';

        if (this.hasIconeTarget) {
            this.iconeTarget.classList.toggle('bi-eye', !estMasque);
            this.iconeTarget.classList.toggle('bi-eye-slash', estMasque);
        }

        const bouton = event.currentTarget;
        bouton.setAttribute('aria-pressed', estMasque ? 'true' : 'false');
        bouton.setAttribute('aria-label', estMasque ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
    }
}
