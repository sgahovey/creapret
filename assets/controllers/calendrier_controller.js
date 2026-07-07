import { Controller } from '@hotwired/stimulus';

/*
 * Initialise FullCalendar (bundle global window.FullCalendar, cf. §10) sur la vue d'occupation.
 * Consomme l'endpoint gestionnaire /gestion/api/calendrier/prets (start/end envoyes automatiquement).
 * Accessibilite : un tableau alternatif (data-cible tableau) est rempli en parallele de la grille.
 */
export default class extends Controller {
    static targets = ['calendrier', 'tableau', 'statut'];
    static values = { url: String };

    connect() {
        if (typeof window.FullCalendar === 'undefined') {
            this.annoncer('Le calendrier n\'a pas pu etre charge.');
            return;
        }

        this.calendar = new window.FullCalendar.Calendar(this.calendrierTarget, {
            locale: 'fr',
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,listWeek',
            },
            height: 'auto',
            events: (info, successCallback, failureCallback) => {
                this.annoncer('Chargement du calendrier en cours.');
                const url = `${this.urlValue}?start=${encodeURIComponent(info.startStr)}&end=${encodeURIComponent(info.endStr)}`;
                fetch(url, { headers: { Accept: 'application/json' } })
                    .then((r) => {
                        if (!r.ok) {
                            throw new Error('Reponse non valide');
                        }
                        return r.json();
                    })
                    .then((events) => {
                        successCallback(events);
                        this.remplirTableau(events);
                        this.annoncer(`${events.length} pret(s) affiche(s).`);
                    })
                    .catch((e) => {
                        failureCallback(e);
                        this.annoncer('Erreur lors du chargement du calendrier.');
                    });
            },
        });

        this.calendar.render();
    }

    disconnect() {
        if (this.calendar) {
            this.calendar.destroy();
        }
    }

    remplirTableau(events) {
        if (!this.hasTableauTarget) {
            return;
        }
        const lignes = events
            .map((e) => {
                const debut = new Date(e.start).toLocaleDateString('fr-FR');
                const fin = new Date(e.end).toLocaleDateString('fr-FR');
                const statut = e.extendedProps && e.extendedProps.statut ? e.extendedProps.statut : '';
                return `<tr><td>${this.echapper(e.title)}</td><td>${debut}</td><td>${fin}</td><td>${this.echapper(statut)}</td></tr>`;
            })
            .join('');
        this.tableauTarget.innerHTML = lignes || '<tr><td colspan="4">Aucun pret sur cette periode.</td></tr>';
    }

    annoncer(message) {
        if (this.hasStatutTarget) {
            this.statutTarget.textContent = message;
        }
    }

    echapper(texte) {
        const div = document.createElement('div');
        div.textContent = texte;
        return div.innerHTML;
    }
}
