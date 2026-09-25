/**
 * LOHEIDE Rechteverwaltung – Interaktion im Editor.
 */
( function () {
	'use strict';

	var i18n = ( window.lrmAdmin && window.lrmAdmin.i18n ) || {};

	/**
	 * Platzhalter %s ersetzen.
	 *
	 * @param {string} template Vorlage.
	 * @param {string} value    Wert.
	 * @return {string} Ergebnis.
	 */
	function format( template, value ) {
		return String( template || '' ).replace( '%s', value );
	}

	/**
	 * Beschriftungen der aktiven Auswahl einer Liste.
	 *
	 * @param {HTMLElement} box  Container.
	 * @param {string}      list allow|deny.
	 * @return {Array} Beschriftungen.
	 */
	function selectedLabels( box, list ) {
		var labels = [];

		box.querySelectorAll( 'input[data-list="' + list + '"]:checked' ).forEach( function ( input ) {
			var label = input.parentNode.querySelector( '.lrm-chip__label' );
			labels.push( label ? label.textContent.trim() : input.value );
		} );

		return labels;
	}

	/**
	 * Metabox verdrahten.
	 *
	 * @param {HTMLElement} box Metabox.
	 */
	function initBox( box ) {
		var enabled = box.querySelector( '#lrm-enabled' );
		var body = box.querySelector( '#lrm-body' );
		var conflict = box.querySelector( '#lrm-conflict' );
		var conflictText = box.querySelector( '#lrm-conflict-text' );
		var summary = box.querySelector( '#lrm-summary' );
		var action = box.querySelector( '#lrm-action' );
		var fieldRedirect = box.querySelector( '#lrm-field-redirect' );
		var fieldMessage = box.querySelector( '#lrm-field-message' );
		var cardAllow = box.querySelector( '#lrm-card-allow' );

		/**
		 * Aktiven Sichtbarkeitsmodus lesen.
		 *
		 * @return {string} Modus.
		 */
		function currentMode() {
			var checked = box.querySelector( 'input[name="lrm[visibility]"]:checked' );

			return checked ? checked.value : 'logged_in';
		}

		/**
		 * Konflikte markieren: eine Rolle in beiden Listen bedeutet Sperre.
		 *
		 * @return {Array} Betroffene Beschriftungen.
		 */
		function markConflicts() {
			var denied = {};
			var labels = [];

			box.querySelectorAll( 'input[data-list="deny"]:checked' ).forEach( function ( input ) {
				denied[ input.value ] = true;
			} );

			box.querySelectorAll( 'input[data-list="allow"]' ).forEach( function ( input ) {
				var chip = input.closest( '.lrm-chip' );

				if ( ! chip ) {
					return;
				}

				if ( input.checked && denied[ input.value ] ) {
					chip.classList.add( 'is-overruled' );
					var label = chip.querySelector( '.lrm-chip__label' );
					labels.push( label ? label.textContent.trim() : input.value );
				} else {
					chip.classList.remove( 'is-overruled' );
				}
			} );

			return labels;
		}

		/**
		 * Oberfläche aktualisieren.
		 */
		function refresh() {
			var isEnabled = ! enabled || enabled.checked;
			var mode = currentMode();

			box.classList.toggle( 'is-disabled', ! isEnabled );

			if ( cardAllow ) {
				cardAllow.style.opacity = 'roles' === mode ? '1' : '.55';
			}

			// Abhängige Felder.
			if ( action ) {
				var effective = action.value;

				if ( fieldRedirect ) {
					fieldRedirect.classList.toggle( 'lrm-hidden', 'redirect' !== effective );
				}

				if ( fieldMessage ) {
					fieldMessage.classList.toggle( 'lrm-hidden', 'login' === effective || '404' === effective || 'redirect' === effective );
				}
			}

			// Konflikte.
			var conflicts = markConflicts();

			if ( conflict && conflictText ) {
				if ( conflicts.length ) {
					conflictText.textContent = format( i18n.conflict, conflicts.join( ', ' ) );
					conflict.classList.remove( 'lrm-hidden' );
				} else {
					conflict.classList.add( 'lrm-hidden' );
				}
			}

			// Zusammenfassung.
			if ( ! summary ) {
				return;
			}

			if ( ! isEnabled ) {
				summary.textContent = i18n.sumOff || '';
				return;
			}

			var allowLabels = selectedLabels( box, 'allow' );
			var denyLabels = selectedLabels( box, 'deny' );
			var text;

			if ( 'public' === mode ) {
				text = i18n.sumPublic;
			} else if ( 'logged_in' === mode ) {
				text = i18n.sumLoggedIn;
			} else if ( allowLabels.length ) {
				text = format( i18n.sumRoles, allowLabels.join( ', ' ) );
			} else {
				text = i18n.sumNoRoles;
			}

			if ( denyLabels.length ) {
				text += ' ' + format( i18n.sumDenied, denyLabels.join( ', ' ) );
			}

			summary.textContent = text;
		}

		// "Alle"-Schalter.
		box.querySelectorAll( '[data-lrm-toggle-all]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var list = button.getAttribute( 'data-lrm-toggle-all' );
				var inputs = box.querySelectorAll( 'input[data-list="' + list + '"]' );
				var allChecked = true;

				inputs.forEach( function ( input ) {
					if ( ! input.checked ) {
						allChecked = false;
					}
				} );

				inputs.forEach( function ( input ) {
					input.checked = ! allChecked;
				} );

				refresh();
			} );
		} );

		box.addEventListener( 'change', refresh );

		if ( body ) {
			refresh();
		}
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var box = document.getElementById( 'lrm-box' );

		if ( box ) {
			initBox( box );
		}
	} );
}() );
