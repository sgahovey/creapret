import { Controller } from '@hotwired/stimulus';
import { couleurToken, chartEstDisponible } from '../chartjs_helpers.js';

/*
 * Rend un graphique en barres du top des materiels les plus empruntes (US-5.1).
 * Les donnees sont fournies en data-attribute JSON (series), pas via un endpoint.
 * Le tableau alternatif (RGAA) est present dans le template, hors de ce controleur.
 *
 * Resilience au timing : en navigation Turbo, le <script> Chart.js peut ne pas etre
 * encore execute au moment du connect(). On attend donc que window.Chart soit pret
 * (retry borne via requestAnimationFrame) avant d'instancier, plutot que d'echouer.
 */
export default class extends Controller {
    static targets = ['canvas'];
    static values = { series: Array };

    connect() {
        if (!this.hasCanvasTarget) {
            return;
        }
        this.tentatives = 0;
        this.dessinerQuandPret();
    }

    disconnect() {
        if (this.imageAnimation) {
            cancelAnimationFrame(this.imageAnimation);
        }
        if (this.chart) {
            this.chart.destroy();
        }
    }

    dessinerQuandPret() {
        // Chart.js pas encore charge : on reessaie au prochain rendu, dans la limite d'environ 1 seconde.
        if (typeof window.Chart === 'undefined') {
            if (this.tentatives < 60) {
                this.tentatives += 1;
                this.imageAnimation = requestAnimationFrame(() => this.dessinerQuandPret());
                return;
            }
            chartEstDisponible(); // journalise l'absence definitive de Chart.js
            return;
        }

        this.chart = new window.Chart(this.canvasTarget, this.configuration());
    }

    configuration() {
        const labels = this.seriesValue.map((ligne) => ligne.materiel);
        const donnees = this.seriesValue.map((ligne) => ligne.total);

        return {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: 'Nombre de prets',
                    data: donnees,
                    backgroundColor: couleurToken('--creapret-primaire', '#0f6e56'),
                }],
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                scales: { x: { ticks: { precision: 0 }, beginAtZero: true } },
                plugins: { legend: { display: false } },
            },
        };
    }
}
