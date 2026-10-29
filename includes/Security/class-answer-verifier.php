<?php
/**
 * Verifies a submitted security-question answer against its stored hash.
 *
 * @package CourseVerify
 */

namespace CourseVerify\Security;

// Do not allow direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Answer_Verifier
 *
 * Single responsibility: given a submitted answer and a stored hash, say
 * whether they match. Does not touch the database, does not know about
 * checkpoints or course access, does not send email.
 */
class Answer_Verifier {

	/**
	 * Check a submitted answer against a stored hash.
	 *
	 * @param string $submitted_answer Raw answer as submitted by the student.
	 * @param string $stored_hash      Hash previously produced by wp_hash_password().
	 * @return bool
	 */
	public function verify( $submitted_answer, $stored_hash ) {
		if ( '' === trim( (string) $submitted_answer ) || '' === (string) $stored_hash ) {
			return false;
		}

		$normalized = Question_Manager::normalize_answer( $submitted_answer );

		return (bool) wp_check_password( $normalized, $stored_hash );
	}
}
