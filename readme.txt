=== CourseVerify – Student Identity Checkpoints ===
Contributors: skfreelancers, saqibabbasi
Tags: learndash, identity verification, course security, quiz, checkpoints
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Configurable identity-verification checkpoints for LearnDash courses, gated on tracked course time via Uncanny Toolkit Pro.

== Description ==

CourseVerify lets a course administrator require students to periodically verify their identity as they progress through a LearnDash course — at course start, at configurable time-based checkpoints (e.g. every 5 tracked hours), and before a designated final exam.

At each checkpoint, the student answers a previously configured personal security question. Course access is blocked server-side until verification succeeds. CourseVerify provides a configurable identity-verification mechanism that educational providers can use to implement their own institutional policies — it does not itself guarantee compliance with any government or regulatory requirement.

**Core features**

* Student-configured security questions, answers stored as one-way hashes (never plaintext, never shown to administrators)
* Configurable, per-course checkpoint schedule based on tracked course time
* Configurable maximum verification attempts, with automatic lock-out and administrator unlock
* Optional final-exam verification, gated on an administrator-selected quiz
* Full audit log of verification and administrative events
* Works with any theme; assets are only loaded on protected course pages
* No telemetry, no tracking, no external requests, no advertising

**Supported integrations**

* LearnDash (course/lesson/topic/quiz access, enrollment, final exam)
* Uncanny Toolkit Pro — Simple Course Timer (`[uo_time]`) as the course-time source

CourseVerify is an independent plugin and is not affiliated with LearnDash, Uncanny Owl, or any regulatory agency.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/` or install it through the WordPress plugins screen.
2. Activate the plugin. LearnDash and Uncanny Toolkit Pro (with the Simple Course Timer module) should be active for full functionality; the plugin will show a notice if either is missing rather than fatal-erroring.
3. Open a LearnDash course's edit screen and use the "CourseVerify — Identity Checkpoints" box to enable CourseVerify for that course and configure its checkpoint schedule, attempt limit, and (optionally) its final exam quiz.
4. Review global defaults under CourseVerify → Settings.

== Configuration ==

Per-course settings (on each course's edit screen):

* Enable CourseVerify
* Number of security questions required at setup
* Checkpoint schedule, in hours of tracked course time
* Maximum verification attempts before locking
* Whether to lock on max failures
* Final-exam verification, and which quiz it applies to

Global settings (CourseVerify → Settings) provide defaults used when a course hasn't been individually configured, plus the uninstall data-deletion preference.

== Frequently Asked Questions ==

= Does this guarantee compliance with my state's or institution's identity-verification requirements? =

No. CourseVerify provides a configurable mechanism your institution can use as part of meeting its own requirements. Whether a given configuration satisfies a particular regulatory or institutional policy is a determination for your school or the applicable authority — not a claim this plugin makes.

= What happens if Uncanny Toolkit Pro or LearnDash isn't active? =

CourseVerify shows an admin notice and does not enforce checkpoints until the dependency is active. It never causes a fatal error.

= What happens to a student's data if I uninstall the plugin? =

By default, nothing is deleted on uninstall. An administrator can opt in to full data deletion under Settings; only then are CourseVerify's own tables and options removed.

= Are security answers recoverable? =

No — answers are stored using the same one-way password hashing WordPress uses for user passwords. They cannot be displayed or exported in their original form, by anyone, including administrators.

== Screenshots ==

1. Student verification prompt.
2. Course settings meta box.
3. Admin Students screen.
4. Admin Logs screen.

== Changelog ==

= 1.0.1 =
* Fixed: text domain now matches the plugin's actual WordPress.org slug (courseverify-student-identity-checkpoints).
* Security: security-question text submitted from the pool-based setup form is now re-validated server-side against the configured pool, so it can no longer be substituted via a browser dev-tools edit of the hidden field.
* Fixed: the per-course "Lock on Failure" setting is now actually consulted — when disabled, students are never locked out after repeated wrong answers (the counter resets so they can keep retrying), and failure messages no longer threaten a lockout that was never going to happen.
* Fixed: the security question shown at a checkpoint is now assigned once per attempt and consistently re-served on reload/poll, and a submitted answer is validated against that specific assigned question rather than any of the student's configured questions.
* Fixed: a redundant unslash of already-sanitized setup input that could, in rare cases, alter a literal backslash character in a submitted answer.
* Removed dev-only test scaffolding from the distributed plugin package.

= 1.0.0 =
* Initial release: security-question setup, time-based checkpoints, final-exam verification, admin dashboard/students/logs/settings, LearnDash and Uncanny Toolkit Pro integration, privacy exporter/eraser.

== Privacy ==

CourseVerify stores, per student per course: configured security-question text and a one-way hash of the answer (never the plaintext answer), checkpoint verification status and timestamps, verification attempt outcomes (never the submitted answer text), and an administrative audit trail of setup/verification/lock/unlock events. No data is sent to any external server. Administrators can review stored data via the Students and Logs screens, and students' data is included in WordPress's built-in personal-data export and erasure tools (audit-log rows are retained by default as an administrative record; site owners can change this via the `courseverify_privacy_erase_audit` filter).

== Limitations ==

* The bundled Uncanny Toolkit Pro time-provider adapter reads course time via the `[uo_time]` shortcode's own rendering rather than a documented per-user API, and can currently only read the *active, logged-in* student's own time (not an arbitrary student looked up by an administrator). See the compatibility note in `includes/Integrations/Uncanny/class-uncanny-timer.php`.
* Course-content gating is enforced via the `the_content` filter on singular LearnDash content; a fully version-proof hard block on LearnDash's own AJAX quiz-submission endpoint has not yet been verified against a specific LearnDash version.
