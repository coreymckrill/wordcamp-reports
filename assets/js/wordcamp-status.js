
( function( window, $ ) {

	'use strict';

	var WordCampStatus = window.WordCampStatus || {};

	$.extend( WordCampStatus, {
		/**
		 * Initialize the script.
		 */
		init: function() {
			var self = this;

			this.cache = {
				$logs: $( '.status-log' ),
				toggles: []
			};

			this.setupSingleToggles();
			this.setupBulkToggles();

			$( document ).ready( function() {
				self.cache.$hideAll.trigger( 'click' );
			} );
		},

		/**
		 * Set up status log toggles for individual camps.
		 */
		setupSingleToggles: function() {
			var self = this,
				$logs = this.cache.$logs;

			if ( $logs.length ) {
				$logs.each( function() {
					var $button = $( '<button>' )
							.addClass( 'status-log-toggle' )
							.data( {
								status: 'visible',
								showLabel: 'Show Details',
								hideLabel: 'Hide details'
							} )
							.on( 'click', self.toggleDetails ),
						$icon = $( '<span>' )
							.addClass( 'status-log-toggle-icon dashicons dashicons-arrow-down' )
							.attr( 'aria-hidden', true ),
						$label = $( '<span>' )
							.addClass( 'status-log-toggle-label screen-reader-text' )
							.text( $button.data( 'hideLabel' ) )
					;

					$button
						.append( $icon )
						.append( $label )
						.appendTo( $( this ).prev( 'p' ) )
					;

					self.cache.toggles.push( $button );
				} );
			}
		},

		/**
		 * Toggle the visibility of a status log.
		 *
		 * @param e
		 */
		toggleDetails: function( e ) {
			e.preventDefault();

			var $button = $( e.target ),
				$icon = $button.find( '.status-log-toggle-icon' ),
				$label = $button.find( '.status-log-toggle-label' ),
				$log = $button.parent().next( '.status-log' );

			if ( $log.is( ':visible' ) ) {
				// Currently visible. Hide.
				$log.hide();
				$icon.removeClass( 'dashicons-arrow-up' ).addClass( 'dashicons-arrow-down' );
				$label.text( $button.data( 'showLabel' ) );
				$button.data( 'status', 'hidden' );
			} else {
				// Currently hidden. Show.
				$log.show();
				$icon.removeClass( 'dashicons-arrow-down' ).addClass( 'dashicons-arrow-up' );
				$label.text( $button.data( 'hideLabel' ) );
				$button.data( 'status', 'visible' );
			}
		},

		/**
		 * Set up buttons to toggle every status log at once.
		 */
		setupBulkToggles: function () {
			var self = this,
				$activeheading = $( '#active-heading' ),
				$bar;

			if ( $activeheading.length ) {
				$bar = $( '<div>' ).attr( 'id', 'status-log-bulk-bar' ).insertAfter( $activeheading );

				self.cache.$showAll = $( '<button>' )
					.addClass( 'button status-log-bulk-toggle' )
					.text( 'Show all details' )
					.on( 'click', self.showAll )
					.appendTo( $bar )
				;

				self.cache.$hideAll = $( '<button>' )
					.addClass( 'button status-log-bulk-toggle' )
					.text( 'Hide all details' )
					.on( 'click', self.hideAll )
					.appendTo( $bar )
				;
			}
		},

		/**
		 * Show all status logs.
		 *
		 * @param e
		 */
		showAll: function( e ) {
			e.preventDefault();

			$.each( WordCampStatus.cache.toggles, function( index, $toggle ) {
				if ( 'hidden' === $toggle.data( 'status' ) ) {
					$toggle.trigger( 'click' );
				}
			} );
		},

		/**
		 * Hide all status logs.
		 *
		 * @param e
		 */
		hideAll: function( e ) {
			e.preventDefault();

			$.each( WordCampStatus.cache.toggles, function( index, $toggle ) {
				if ( 'visible' === $toggle.data( 'status' ) ) {
					$toggle.trigger( 'click' );
				}
			} );
		}
	} );

	WordCampStatus.init();

} )( window, jQuery );
