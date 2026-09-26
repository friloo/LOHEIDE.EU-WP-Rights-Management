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

	/**
	 * Suchfelder verdrahten, die eine Liste filtern.
	 */
	function initFilters() {
		document.querySelectorAll( '[data-lrm-filter]' ).forEach( function ( input ) {
			var target = document.querySelector( input.getAttribute( 'data-lrm-filter' ) );

			if ( ! target ) {
				return;
			}

			input.addEventListener( 'input', function () {
				var term = input.value.trim().toLowerCase();

				target.querySelectorAll( '.lrm-listitem' ).forEach( function ( item ) {
					var text = item.textContent.toLowerCase();
					item.classList.toggle( 'is-hidden', '' !== term && -1 === text.indexOf( term ) );
				} );
			} );

			// Absenden des Formulars durch die Eingabetaste im Suchfeld verhindern.
			input.addEventListener( 'keydown', function ( event ) {
				if ( 'Enter' === event.key ) {
					event.preventDefault();
				}
			} );
		} );
	}

	/**
	 * Menüauswahl: Ein abgeschaltetes Hauptmenü nimmt seine Unterpunkte mit.
	 */
	function initMenuList() {
		document.querySelectorAll( '.lrm-menuitem' ).forEach( function ( item ) {
			var parent = item.querySelector( '.lrm-switch input[type="checkbox"]' );
			var children = item.querySelectorAll( '.lrm-menuitem__children input[type="checkbox"]' );

			if ( ! parent || ! children.length ) {
				return;
			}

			var sync = function ( propagate ) {
				children.forEach( function ( child ) {
					if ( propagate ) {
						child.checked = parent.checked;
					}

					child.disabled = ! parent.checked;
					child.closest( '.lrm-check' ).classList.toggle( 'is-muted', ! parent.checked );
				} );
			};

			parent.addEventListener( 'change', function () {
				sync( true );
			} );

			sync( false );
		} );
	}

	/**
	 * Abschnitte eines Inhaltstyps: nur zeigen, was zum gewählten Modus passt.
	 */
	function initTypePanels() {
		document.querySelectorAll( '.lrm-typepanel' ).forEach( function ( panel ) {
			var radios = panel.querySelectorAll( 'input[data-lrm-mode]' );

			if ( ! radios.length ) {
				return;
			}

			var apply = function () {
				var mode = 'none';

				radios.forEach( function ( radio ) {
					if ( radio.checked ) {
						mode = radio.getAttribute( 'data-lrm-mode' );
					}
				} );

				panel.querySelectorAll( '.lrm-typeoptions' ).forEach( function ( block ) {
					var forMode = block.getAttribute( 'data-lrm-for' );
					var show = ( 'any' === forMode ) ? 'none' !== mode : forMode === mode;
					block.classList.toggle( 'lrm-hidden', ! show );
				} );
			};

			radios.forEach( function ( radio ) {
				radio.addEventListener( 'change', apply );
			} );

			apply();

			// Umschalten der Taxonomie blendet die passenden Begriffe ein.
			var taxSelect = panel.querySelector( '[data-lrm-taxonomy]' );

			if ( taxSelect ) {
				var applyTax = function () {
					panel.querySelectorAll( '[data-lrm-terms]' ).forEach( function ( group ) {
						var active = group.getAttribute( 'data-lrm-terms' ) === taxSelect.value;
						group.style.display = active ? '' : 'none';
						group.querySelectorAll( 'input[type="checkbox"]' ).forEach( function ( input ) {
							input.disabled = ! active;
						} );
					} );
				};

				taxSelect.addEventListener( 'change', applyTax );
				applyTax();
			}
		} );
	}

	/**
	 * Schaltflächen, die eine ganze Liste an- oder abschalten.
	 */
	function initBulkToggles() {
		document.querySelectorAll( '[data-lrm-toggle-all-in]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var target = document.querySelector( button.getAttribute( 'data-lrm-toggle-all-in' ) );

				if ( ! target ) {
					return;
				}

				var on = 'on' === button.getAttribute( 'data-lrm-state' );

				target.querySelectorAll( 'input[type="checkbox"]' ).forEach( function ( input ) {
					input.disabled = false;
					input.checked = on;
				} );

				// Untermenüs richten sich nach ihrem Hauptmenü.
				target.querySelectorAll( '.lrm-menuitem > .lrm-switch input[type="checkbox"]' ).forEach( function ( input ) {
					input.dispatchEvent( new Event( 'change' ) );
				} );
			} );
		} );
	}

	/**
	 * Suche für große Bestände: Treffer anklicken, Auswahl als Marke behalten.
	 */
	function initItemPickers() {
		var settings = window.lrmAdmin || {};

		document.querySelectorAll( '[data-lrm-picker]' ).forEach( function ( picker ) {
			var input = picker.querySelector( '[data-lrm-search]' );
			var results = picker.querySelector( '[data-lrm-results]' );
			var chosen = picker.querySelector( '[data-lrm-chosen]' );
			var postType = picker.getAttribute( 'data-lrm-picker' );
			var field = picker.getAttribute( 'data-lrm-field' );
			var timer = null;

			if ( ! input || ! results || ! chosen ) {
				return;
			}

			/**
			 * Eine Marke für die Auswahl anlegen.
			 *
			 * @param {number} id    Inhalts-ID.
			 * @param {string} title Titel.
			 */
			function addItem( id, title ) {
				if ( chosen.querySelector( 'input[value="' + id + '"]' ) ) {
					return;
				}

				var tag = document.createElement( 'span' );
				tag.className = 'lrm-tag lrm-tag--allow lrm-tag--removable';
				tag.textContent = title + ' ';

				var button = document.createElement( 'button' );
				button.type = 'button';
				button.className = 'lrm-tag__remove';
				button.setAttribute( 'aria-label', settings.i18n && settings.i18n.remove ? settings.i18n.remove : 'Entfernen' );
				button.innerHTML = '&times;';
				tag.appendChild( button );

				var hidden = document.createElement( 'input' );
				hidden.type = 'hidden';
				hidden.name = field + '[items][]';
				hidden.value = id;
				tag.appendChild( hidden );

				chosen.appendChild( tag );
			}

			chosen.addEventListener( 'click', function ( event ) {
				if ( event.target.classList.contains( 'lrm-tag__remove' ) ) {
					event.target.closest( '.lrm-tag' ).remove();
				}
			} );

			results.addEventListener( 'click', function ( event ) {
				var hit = event.target.closest( '[data-lrm-hit]' );

				if ( ! hit ) {
					return;
				}

				event.preventDefault();
				addItem( hit.getAttribute( 'data-id' ), hit.textContent.trim() );
				results.innerHTML = '';
				input.value = '';
			} );

			input.addEventListener( 'keydown', function ( event ) {
				if ( 'Enter' === event.key ) {
					event.preventDefault();
				}
			} );

			input.addEventListener( 'input', function () {
				window.clearTimeout( timer );

				var term = input.value.trim();

				if ( term.length < 2 ) {
					results.innerHTML = '';
					return;
				}

				timer = window.setTimeout( function () {
					var url = settings.ajaxUrl + '?action=lrm_search_items&nonce=' + encodeURIComponent( settings.searchNonce ) +
						'&post_type=' + encodeURIComponent( postType ) + '&term=' + encodeURIComponent( term );

					window.fetch( url, { credentials: 'same-origin' } )
						.then( function ( response ) {
							return response.json();
						} )
						.then( function ( payload ) {
							results.innerHTML = '';

							if ( ! payload || ! payload.success || ! payload.data.length ) {
								results.textContent = settings.i18n && settings.i18n.noHits ? settings.i18n.noHits : '';
								return;
							}

							payload.data.forEach( function ( item ) {
								var hit = document.createElement( 'button' );
								hit.type = 'button';
								hit.className = 'lrm-searchhit';
								hit.setAttribute( 'data-lrm-hit', '1' );
								hit.setAttribute( 'data-id', item.id );
								hit.textContent = item.title;
								results.appendChild( hit );
							} );
						} )
						.catch( function () {
							results.innerHTML = '';
						} );
				}, 250 );
			} );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var box = document.getElementById( 'lrm-box' );

		if ( box ) {
			initBox( box );
		}

		initFilters();
		initMenuList();
		initTypePanels();
		initItemPickers();
		initBulkToggles();
	} );
}() );
