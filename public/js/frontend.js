/**
 * CourseVerify frontend behavior.
 *
 * This file never contains a correct answer and never decides whether a
 * checkpoint passes — it only submits student input to the server via
 * AJAX and renders whatever the server decides.
 *
 * @package CourseVerify
 */
( function () {
	'use strict';

	if ( typeof window.CourseVerifyFrontend === 'undefined' ) {
		return;
	}

	var config = window.CourseVerifyFrontend;

	function setMessage( container, text, tone ) {
		var messageEl = container.querySelector( '.courseverify-message' );
		if ( ! messageEl ) {
			return;
		}

		messageEl.classList.remove( 'is-error', 'is-success', 'is-warning' );
		messageEl.innerHTML = '';

		if ( ! text ) {
			return;
		}

		var icon = document.createElement( 'span' );
		icon.className = 'courseverify-message-icon';
		icon.setAttribute( 'aria-hidden', 'true' );

		var textEl = document.createElement( 'span' );
		textEl.className = 'courseverify-message-text';
		textEl.textContent = text;

		if ( 'success' === tone ) {
			messageEl.classList.add( 'is-success' );
			icon.textContent = '\u2713';
		} else if ( 'warning' === tone ) {
			messageEl.classList.add( 'is-warning' );
			icon.textContent = '\u26A0';
		} else {
			messageEl.classList.add( 'is-error' );
			icon.textContent = '\u2715';
		}

		messageEl.appendChild( icon );
		messageEl.appendChild( textEl );
	}

	function postForm( action, nonce, fields ) {
		var body = new URLSearchParams();
		body.set( 'action', action );
		body.set( 'nonce', nonce );

		Object.keys( fields ).forEach( function ( key ) {
			body.set( key, fields[ key ] );
		} );

		return fetch( config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString(),
		} ).then( function ( response ) {
			return response.json();
		} );
	}

	function initSetupForm() {
		var container = document.querySelector( '.courseverify-setup' );
		if ( ! container ) {
			return;
		}

		var form = container.querySelector( '.courseverify-setup-form' );
		var courseId = container.getAttribute( 'data-course-id' );

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();

			var formData = new FormData( form );
			var body = new URLSearchParams();
			body.set( 'action', 'courseverify_save_setup' );
			body.set( 'nonce', config.nonceSaveSetup );
			body.set( 'course_id', courseId );

			for ( var pair of formData.entries() ) {
				body.append( pair[ 0 ], pair[ 1 ] );
			}

			fetch( config.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: body.toString(),
			} )
				.then( function ( response ) {
					return response.json();
				} )
				.then( function ( json ) {
					if ( json.success ) {
						window.location.reload();
					} else {
						setMessage( container, ( json.data && json.data.message ) || 'Unable to save.', 'error' );
					}
				} )
				.catch( function () {
					setMessage( container, 'Something went wrong. Please try again.', 'error' );
				} );
		} );
	}

	function initVerifyForm() {
		var container = document.querySelector( '.courseverify-verification' );
		if ( ! container ) {
			return;
		}

		var form = container.querySelector( '.courseverify-verify-form' );
		var courseId = container.getAttribute( 'data-course-id' );
		var checkpointKey = container.getAttribute( 'data-checkpoint-key' );
		var questionId = container.getAttribute( 'data-question-id' );

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();

			var answerField = form.querySelector( '[name="answer"]' );

			postForm( 'courseverify_submit_verification', config.nonceVerify, {
				course_id: courseId,
				checkpoint_key: checkpointKey,
				question_id: questionId,
				answer: answerField.value,
			} )
				.then( function ( json ) {
					if ( ! json.success ) {
						setMessage( container, ( json.data && json.data.message ) || 'Unable to verify.', 'error' );
						return;
					}

					var data = json.data;

					if ( 'passed' === data.result ) {
						setMessage( container, data.message, 'success' );
						window.location.reload();
						return;
					}

					if ( 'locked' === data.result ) {
						window.location.reload();
						return;
					}

					// failed_retry — warn more strongly on the last remaining attempt.
					setMessage( container, data.message, data.is_final_attempt ? 'warning' : 'error' );
					form.classList.toggle( 'is-final-attempt', !! data.is_final_attempt );
					answerField.value = '';
					answerField.focus();
				} )
				.catch( function () {
					setMessage( container, 'Something went wrong. Please try again.', 'error' );
				} );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		initSetupForm();
		initVerifyForm();
	} );
} )();
