
<div align="center">
	<img src="Images/eyad_logo1.jpeg" alt="Eng. Eyad Mazhar logo" width="112" height="112" />

	# Eyad LMS

	**A focused learning platform for American Mathematics students**

	Public website, protected student portal, and practical admin workspace for Eng. Eyad Mazhar.

	<p>
		<img src="https://img.shields.io/badge/PHP-8%2B-777BB4?logo=php&logoColor=white" alt="PHP 8+" />
		<img src="https://img.shields.io/badge/Bootstrap-5.3.3-7952B3?logo=bootstrap&logoColor=white" alt="Bootstrap 5.3.3" />
		<img src="https://img.shields.io/badge/MySQL%20%2F%20MariaDB-Database-4479A1?logo=mysql&logoColor=white" alt="MySQL or MariaDB" />
		<img src="https://img.shields.io/badge/Responsive-Desktop%20%7C%20Tablet%20%7C%20Mobile-198754" alt="Responsive interface" />
	</p>
</div>

Eyad LMS is a lightweight learning management system and public website for **Eng. Eyad Mazhar**, an American Mathematics instructor in Egypt. It combines a public information site with protected admin and student portals for managing and consuming sessions, PDF resources, and multiple-choice quizzes.

The current implementation is intentionally small and practical. Students do not register or pay through the site: the teacher creates their accounts and assigns their learning access.

## At A Glance

| Experience | Purpose | Access |
| --- | --- | --- |
| **Public website** | Introduce the instructor, programs, and contact options | Everyone |
| **Admin workspace** | Manage students, groups, sessions, resources, quizzes, and activity | `admin` role |
| **Student portal** | Watch sessions, access PDFs, take quizzes, and review results | `student` role |

### What Makes It Useful

- **Group-aware delivery** - assign students to one or more learning groups and show each group its own content.
- **External-first media** - keep videos on Google Drive, YouTube, or another media host instead of filling local hosting storage.
- **Teacher-friendly quiz control** - support timers, attempt limits, extra attempts, question images, scoring, and detailed review.
- **Operational visibility** - record session views and quiz activity so the teacher can understand how content is being used.

## Contents

- [Current Website](#current-website)
- [Roles and Access Control](#roles-and-access-control)
- [Admin Workflows](#admin-workflows)
- [Student Workflows](#student-workflows)
- [Technology](#technology)
- [Project Structure](#project-structure)
- [Database Tables](#database-tables)
- [Installation](#installation)
- [Security and Operational Notes](#security-and-operational-notes)
- [Hosting Model and Limitations](#hosting-model-and-limitations)

## Current Website

### Public website

The public area is available without authentication:

- `public/index.php` - home page for American Math, EST, DSAT, and ACT preparation
- `public/about.php` - instructor and platform information
- `public/contact.php` - contact form submitted asynchronously through Web3Forms
- `public/login.php` - shared username/password login for admins and students
- `index.php` - redirects visitors to `public/index.php`

The public pages use shared navigation and footer partials, responsive Bootstrap components, the project theme, and page metadata for search engines and social previews. The login page is marked `noindex`; the home, about, and contact pages are indexable.

### Admin portal

All admin pages require the `admin` role. The shared admin navigation provides:

- `admin/dashboard.php` - administrative home page and links to every module
- `admin/students.php` - create, edit, transfer, deactivate, and delete student accounts
- `admin/admins.php` - create, edit, deactivate, and delete additional admin accounts
- `admin/groups.php` - create, edit, and delete learning groups with a level label
- `admin/lectures.php` - manage sessions, descriptions, order, active status, and group access
- `admin/resources.php` - upload, edit, activate/deactivate, assign, and delete PDF resources
- `admin/quizzes.php` - create and edit quizzes, questions, images, access, limits, and overrides
- `admin/activity_log.php` - review lecture views and completed or expired quiz attempts

The dashboard also includes student-facing previews for sessions, resources, and quizzes. Preview requests use `?preview=1`; an admin can see active content without needing to belong to its assigned groups.

### Student portal

Student pages require the `student` role unless explicitly noted as supporting admin preview:

- `student/dashboard.php` - student portal home
- `student/lectures.php` - active sessions available to the student's groups
- `student/lecture_player.php` - protected session player and view tracking
- `student/resources.php` - active PDF resources available to the student's groups
- `student/quizzes.php` - quiz listing, attempt start/continue, submission, scoring, and attempt status
- `student/quiz_review.php` - score and question-by-question result review
- `student/change_password.php` - change the logged-in student's password

Supporting protected endpoints include `student/quiz_time_check.php`, `student/proxy_media.php`, and `student/widevine_license.php`. The Widevine endpoint is currently a placeholder and is not a complete DRM license service.

## Roles and Access Control

Authentication is implemented in `includes/auth.php` with PHP sessions and password verification using `password_verify()`.

- **Admin**: can use all admin modules, access active student content for preview, and review activity and quiz attempts.
- **Student**: can use the student portal and only see active sessions, resources, and quizzes assigned to their groups.

There is no self-registration, email verification, approval workflow, online payment, or public account recovery flow. The teacher creates accounts and gives students their credentials.

Students support both a primary `users.group_id` assignment and additional memberships in `user_group_access`. The application combines both sources, removes duplicates, and uses the resulting group list for content filtering. Content can also have a primary group plus additional access rows:

- sessions use `lecture_folder_access`
- resources use `resource_group_access`
- quizzes use `quiz_group_access`

Inactive content is excluded from normal student listings and direct access checks.

## Admin Workflows

### Students and admins

Student records contain a name, username, hashed password, phone, parent phone, primary group, role, and active/inactive status. Admins can create and edit student credentials, change the primary group, manage additional group membership, and remove student accounts.

Admin records use the same `users` table with `role = admin`. Admins can create multiple admin accounts, update usernames and names, change passwords, toggle status, and delete admin records.

### Groups

Groups represent cohorts or learning tracks rather than school grades. Each group has a name and level, such as `Morning Batch` and `Advanced 1`. Groups are used as the access-control layer for all learning content.

### Sessions

The admin interface labels lectures as **Sessions**. Each session has:

- title
- optional description
- external media or Google Drive reference
- display order
- active/inactive status
- one or more allowed groups

Sessions are not stored as video files on the hosting account. The player accepts Google Drive file URLs, Google Drive folder URLs, Drive IDs, YouTube URLs, and direct video URLs. Google Drive file and folder references are normalized before rendering.

### Resources

Resources are PDF files uploaded to `uploads/pdfs/`. Each resource has a title, optional description, active/inactive status, and one or more allowed groups. A new resource must contain a valid PDF no larger than 20 MB. The server checks both the file extension and detected MIME type. Replacing or deleting a resource also removes the stored old PDF when possible.

### Quizzes

Admins can create and edit active or inactive MCQ quizzes. A quiz includes:

- title
- one or more allowed groups
- optional time limit in minutes; `0` means no time limit
- optional maximum attempts; `0` means unlimited attempts
- one or more questions

Each question contains question text, four choices, one correct choice, and an optional JPG, JPEG, PNG, or GIF image. Quiz images are limited to 2 MB and are validated by extension and MIME type. Editing replaces the question set and cleans up removed images. Deleting a quiz also removes its questions, stored question images, access rows, attempts, and related quiz access records.

Admins can grant a student extra attempts for a specific quiz and optionally record a reason. Existing overrides are updated rather than duplicated. The quiz management page also displays extra-attempt overrides and a recent attempt summary.

### Activity log

The activity log combines up to the 200 most recent records from:

- `lecture_views` - session view events, deduplicated so the same student/session is not recorded more than once within ten minutes
- `quiz_attempts` - submitted and expired attempts, including score, percentage, and status

The page displays total lecture views, total non-in-progress quiz attempts, student identity, item title, action, details, and timestamp.

## Student Workflows

### Sessions and media

Students see active sessions assigned to at least one of their groups. Opening a session checks the same access rule and records a view event. The player can render:

- Google Drive file previews
- Google Drive folders
- YouTube embeds
- direct MP4, WebM, OGG, M4V, and MOV sources
- other external links when the source cannot be classified more specifically

The player is responsive and changes its frame height for smaller screens. It also includes best-effort client-side recording and capture deterrents such as focus/visibility detection, screenshot and print shortcut handling, a recording warning overlay, disabled context-menu behavior, and blocked `window.open` calls. These browser controls are deterrents only and do not provide guaranteed DRM, copying prevention, or screenshot prevention.

### Resources

Students see only active PDFs assigned to their groups. The resource page provides the available file links and descriptions.

### Quizzes

Students can:

1. View active quizzes assigned to any of their groups.
2. Start a new attempt or continue the existing in-progress attempt.
3. Submit answers to four-choice questions, including unanswered questions.
4. Receive a score and percentage calculated from the stored correct answers.
5. Have timed-out attempts marked `expired` with a zero score.
6. Review submitted or expired attempts.

Only one quiz can be in progress for a student at a time. A timed quiz uses its stored start time and the configured limit; attempts are checked for expiry when quiz pages and supporting time checks are requested. The available attempt count is the quiz limit plus any student-specific extra attempts. A zero limit means unlimited attempts.

Quiz reviews show the score, percentage, submission time, status, question text, optional image, selected answer, correct answer, and highlighted answer choices. Per-question records are stored so the review remains tied to the submitted attempt.

### Password changes

An authenticated student can update their password from `student/change_password.php`. The new password is stored using PHP password hashing.

## Technology

- PHP 8 or newer
- MySQL or MariaDB
- Apache, commonly through WAMP or XAMPP
- HTML5, CSS3, and JavaScript
- Bootstrap 5.3.3 loaded from jsDelivr
- MySQLi prepared statements and `utf8mb4` connections
- PHP sessions for authentication
- Web3Forms for public contact submissions

The interface is responsive for desktop, tablet, and mobile layouts. Shared navigation files load the Bootstrap bundle and use `assets/css/theme.css` for the application theme.

## Project Structure

```text
Eyad_LMS/
├── admin/                 # admin-only management pages
├── assets/css/            # shared theme stylesheet
├── config/                # database connection and application setup
├── database/              # reserved for database scripts; currently empty
├── Images/                # branding image assets
├── includes/              # authentication and shared navigation/footer partials
├── public/                # public home, about, contact, and login pages
├── student/               # student portal and protected media endpoints
├── uploads/pdfs/          # uploaded resource PDFs
├── uploads/quizzes/       # uploaded quiz question images
├── db_credentials.php     # optional local/hosting database credentials file
├── index.php              # redirect to public/index.php
├── logout.php             # session logout endpoint
├── robots.txt             # crawler directives
└── README.md              # this documentation
```

## Database Tables

There is currently no SQL dump in `database/`. Several application pages create or upgrade supporting tables with `CREATE TABLE IF NOT EXISTS` and `ALTER TABLE` statements when they are accessed. The configured database must still exist, and the foundational tables used by the application must be available before normal operation.

The current application uses these tables:

- `users` - admin and student accounts, credentials, roles, status, and primary group
- `groups` - group names and level labels
- `user_group_access` - additional student-to-group memberships
- `lectures` - session metadata and external media references
- `lecture_folder_access` - session-to-group access rows
- `resources` - PDF metadata and stored paths
- `resource_group_access` - resource-to-group access rows
- `quizzes` - quiz title, primary group, status, time limit, and attempt limit
- `quiz_group_access` - additional quiz-to-group access rows
- `questions` - question text, four choices, correct answer, and optional image path
- `quiz_attempts` - student attempt lifecycle, score, percentage, timestamps, and status
- `quiz_attempt_answers` - selected and correct answer state per question and attempt
- `quiz_extra_attempts` - student-specific attempt allowances and reasons
- `lecture_views` - session view history

The application creates the tracking and access tables as needed in the relevant pages. Existing quiz tables are also upgraded with fields such as `started_at`, `status`, `total_questions`, `score_percent`, `time_limit_minutes`, and `max_attempts` when those columns are missing.

## Installation

### Requirements

- PHP 8+
- Apache with PHP enabled
- MySQL or MariaDB
- PHP extensions used by the application, including MySQLi, fileinfo, and sessions

### Local setup on WAMP

1. Place the project in the web root, for example `C:\\wamp64\\www\\Eyad_LMS`.
2. Start Apache and MySQL from WAMP.
3. Create a database named `eyad_lms`, or choose another name and configure it below.
4. Configure credentials in `db_credentials.php` or through `DB_HOST`, `DB_PORT`, `DB_SOCKET`, `DB_USER`, `DB_PASS`, and `DB_NAME` environment variables.
5. Ensure `uploads/pdfs/` and `uploads/quizzes/` are writable by the web server.
6. Create at least one admin row in `users` with `role = 'admin'`, an active status, and a password generated with PHP `password_hash()`.
7. Open `http://localhost/Eyad_LMS/` or `http://localhost/Eyad_LMS/public/index.php`.

For local requests whose host contains `localhost`, `config/database.php` uses the local defaults `localhost`, user `root`, an empty password, database `eyad_lms`, and port `3306`. Explicit environment variables or the optional credentials file are intended for other environments. Keep credentials outside version control and outside publicly downloadable locations.

## Security and Operational Notes

Implemented protections include:

- password hashing and verification
- session-based authentication and logout
- role checks on protected pages
- group checks on student content and direct content URLs
- prepared statements for database operations
- HTML escaping for rendered user/database values
- PDF and image extension, MIME, and size validation
- generated randomized upload filenames
- active/inactive checks for accounts and content

The application is a lightweight educational portal and still needs production hardening before public deployment. In particular, the current code does not provide a complete DRM system, guaranteed screenshot or recording prevention, CSRF tokens on forms, rate limiting, email-based recovery, or a formal migration/backup system. The public Web3Forms integration also depends on the external service being available.

## Hosting Model and Limitations

The intended low-cost hosting model keeps videos on external providers and stores only application data, PDFs, and quiz images locally. Google Drive is the primary session-hosting workflow, while the player also supports YouTube and direct media URLs.

The current version does not include:

- student self-registration
- online payments or subscription billing
- email verification or password reset by email
- automated enrollment or approval workflows
- gradebook, attendance, certificates, or advanced analytics
- randomized questions or negative marking
- a complete DRM/license server
- device-count enforcement
- a formal database schema file or migration runner
- complex grading rules

