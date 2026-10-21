/* Client interface: local French defaults, with no external language requests. */
(function () {
  'use strict';
  if (window.jQuery && jQuery.fn.dataTable) {
    jQuery.extend(true, jQuery.fn.dataTable.defaults, {language: {
      emptyTable: 'Aucune donnée disponible', info: 'Affichage de _START_ à _END_ sur _TOTAL_ résultats',
      infoEmpty: 'Aucun résultat', infoFiltered: '(filtré sur _MAX_ résultats)',
      lengthMenu: 'Afficher _MENU_ résultats par page', loadingRecords: 'Chargement…',
      processing: 'Traitement en cours…', search: 'Rechercher :', zeroRecords: 'Aucun résultat trouvé',
      thousands: ' ', decimal: ',',
      paginate: {first: 'Premier', last: 'Dernier', next: 'Suivant', previous: 'Précédent'},
      aria: {sortAscending: ': trier par ordre croissant', sortDescending: ': trier par ordre décroissant'},
      buttons: {copy: 'Copier', print: 'Imprimer', colvis: 'Colonnes', copyTitle: 'Copie dans le presse-papiers',
        copySuccess: {'_': '%d lignes copiées', '1': '1 ligne copiée'}}
    }});
  }
  if (window.flatpickr) {
    flatpickr.localize({
      weekdays: {shorthand: ['dim', 'lun', 'mar', 'mer', 'jeu', 'ven', 'sam'], longhand: ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi']},
      months: {shorthand: ['janv', 'févr', 'mars', 'avr', 'mai', 'juin', 'juil', 'août', 'sept', 'oct', 'nov', 'déc'], longhand: ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre']},
      firstDayOfWeek: 1, rangeSeparator: ' au ', weekAbbreviation: 'Sem',
      scrollTitle: 'Défiler pour augmenter la valeur', toggleTitle: 'Cliquer pour basculer', time_24hr: true
    });
  }
  if (window.jQuery && jQuery.fn.select2) {
    jQuery.fn.select2.defaults.set('language', {
      errorLoading: function () { return 'Le chargement a échoué.'; },
      inputTooShort: function () { return 'Saisissez davantage de caractères.'; },
      inputTooLong: function () { return 'Saisissez moins de caractères.'; },
      loadingMore: function () { return 'Chargement…'; },
      maximumSelected: function () { return 'Nombre maximal de sélections atteint.'; },
      noResults: function () { return 'Aucun résultat'; }, searching: function () { return 'Recherche…'; }
    });
  }
}());
