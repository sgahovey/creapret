// Utilitaires partages pour les graphiques Chart.js.

// Recupere une couleur depuis une variable CSS, avec repli si absente.
export function couleurToken(nom, repli) {
    const valeur = getComputedStyle(document.documentElement).getPropertyValue(nom).trim();
    return valeur || repli;
}

// Verifie que le bundle global Chart.js est charge (window.Chart) avant toute instanciation.
export function chartEstDisponible() {
    if (typeof window.Chart === 'undefined') {
        console.error('Chart.js n\'est pas charge (window.Chart absent).');
        return false;
    }
    return true;
}
