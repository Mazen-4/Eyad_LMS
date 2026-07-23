# Eyad LMS Documentation

This document describes the current implementation of Eyad LMS, a lightweight learning management system for Eng. Eyad Mazhar. The platform is designed to be simple, professional, and practical for a single instructor managing students, sessions, resources, and quizzes.

## 1. Project Overview

Eyad LMS is a web-based educational portal with three main experiences:

- a public marketing website for visitors and prospective students
- an admin panel for the instructor to manage content and users
- a student portal for enrolled learners to access assigned content

The system focuses on the teacher’s daily workflow and avoids complexity while providing secure content access, quiz tracking, and administrative control.

## 2. Current Site State

The current version includes the following full implementation details:

### Public Website
- Home page
- About page
- Contact page with Web3Forms API async submission
- Login page
- Shared public navigation and footer
- SEO open graph and Twitter metadata on public pages

### Authentication
- session-based login/logout
- role-aware redirects and protected pages
- `admin` and `student` roles supported
- shared auth helper in `includes/auth.php`

### Admin Features
- admin dashboard with quick cards and direct student preview links
- full student management with create/update/delete
- group management for learning cohorts
- session/lecture management with title, description, display order, status, and group visibility
- PDF resource management with upload, description, status, and group access
- quiz management with MCQ questions, image support, group access, time limits, max attempts, and extra attempts
- admin ability to preview student-facing content and bypass group restrictions when previewing
- activity log for student lecture views and quiz attempts

### Student Portal
- student dashboard
- session listing filtered by student group membership
- lecture player supporting Google Drive, YouTube, and direct media URLs
- resource listing for assigned PDFs
- quiz experience with start/continue behavior, attempt tracking, and result review
- quiz review page showing selected answers, correct answers, and per-question feedback
- change password support

### Tracking and Audit
- lecture views recorded in `lecture_views`
- quiz submissions recorded in `quiz_attempts`
- per-question answer records stored in `quiz_attempt_answers`
- admin-accessible audit and review flows for student activity

## 3. Goals of the System

The implementation aims to provide:

- secure login for admins and students
- flexible group-based access control for educational content
- a clean student experience for sessions, PDFs, and quizzes
- a simple admin workflow for managing all core LMS data
- responsive layouts for desktop and mobile
- quiz tracking and review for students and admins
- admin preview access that does not require group membership

## 4. Technology Stack

### Frontend
- HTML5
- CSS3
- JavaScript
- Bootstrap 5

### Backend
- PHP 8+
- MySQL / MariaDB
- Apache via WAMP/XAMPP

### Other Notes
- The project uses PHP sessions for authentication
- The UI reuses shared header/footer partials for consistency
- Content access is enforced via group membership and role checks

## 5. Project Structure

```text
Eyad_LMS/
├── admin/                # admin pages
├── student/              # student portal pages
├── public/               # public marketing pages
├── includes/             # shared layout and auth helpers
├── config/               # database and application configuration
├── assets/               # CSS and front-end assets
├── uploads/              # uploaded PDFs and quiz images
├── database/             # database schema or scripts
└── README.md             # project documentation
```

## 6. Core Application Modules

### Public Pages
Public pages are available without login and include:
- Home
- About
- Contact
- Login

### Admin Pages
Restricted to `admin` users. Available modules:
- Dashboard
- Students
- Groups
- Sessions
- Resources
- Quizzes
- Activity Log

Admins can manage all learning content, users, and preview the student experience.

### Student Pages
Restricted to `student` users. Available modules:
- Dashboard
- Sessions
- Resources
- Quizzes
- Quiz Review
- Change Password

Students see content assigned to the groups they belong to.

## 7. Authentication and Authorization

Authentication is handled via the shared auth helper in `includes/auth.php`.

### Roles
- `admin`: full access to admin pages and preview content
- `student`: access only student portal pages and assigned content

### Access Behavior
- Student content is filtered by group membership
- Admins can bypass group restrictions when using preview links
- Admins and students both log in through the same login page

## 8. Database Design

### users
Stores admin and student accounts.

Fields include:
- id
- name
- username
- password
- phone
- parent_phone
- group_id
- role
- status

### groups
Defines learning groups and cohorts.

### lectures
Stores session/lecture records.

Fields include:
- id
- title
- description
- drive_folder_id
- display_order
- status

### lecture_folder_access
Links lectures to groups for access control.

### resources
Stores PDF resource records.

Fields include:
- id
- title
- description
- pdf_path
- status

### resource_group_access
Links resources to groups.

### quizzes
Stores quiz metadata.

Fields include:
- id
- title
- group_id
- status
- time_limit_minutes
- max_attempts

### quiz_group_access
Links quizzes to groups.

### questions
Stores quiz questions.

Fields include:
- id
- quiz_id
- question
- choice_1
- choice_2
- choice_3
- choice_4
- correct_answer
- image_path

### quiz_attempts
Tracks student quiz submissions.

Fields include:
- id
- student_id
- quiz_id
- score
- total_questions
- score_percent
- submitted_at
- started_at
- status

### quiz_attempt_answers
Stores individual selected answers for each quiz attempt.

### lecture_views
Stores lecture view history for audit and reporting.

## 9. Recent Feature Additions

- Admin dashboard now includes direct student preview cards linking to the student portal.
- Admin preview pages now allow admins to view all sessions, resources, and quizzes regardless of group restrictions.
- Student quiz review was added so users can see their selected answer, correct answer, and question-level feedback.
- Activity logging was added to capture student lecture views and quiz attempts for audit purposes.
- The quiz engine now stores submitted answer details and supports reviewing past attempts.

## 10. Core Workflows

### Admin Workflow
An admin user can:
1. Create and edit student accounts
2. Assign students to one or more groups
3. Create learning groups
4. Add sessions/lectures and assign them to groups
5. Upload PDF resources and assign them to groups
6. Build quizzes and manage questions
7. Grant extra quiz attempts to students
8. Review student activity through the admin activity log

### Student Workflow
A student user can:
1. Log in to the student portal
2. View assigned sessions
3. Open the session player
4. Download or view assigned PDFs
5. Start or continue quizzes
6. Submit quizzes and review results
7. Change their password

## 11. Notes for Developers

- `includes/auth.php` controls authentication and role enforcement.
- Student-facing pages use group membership to restrict content.
- Admin preview behavior is intentionally implemented to bypass these restrictions for demo and audit purposes.
- The lecture player resolves Google Drive, YouTube, and direct media links.
- The contact form uses Web3Forms for public message delivery.

## 12. Recommended Deployment Notes

- Use PHP 8+ and MySQL/MariaDB.
- Keep `config/database.php` credentials secure and out of public access.
- Protect admin and student directories with proper session-based auth.
- Review `robots.txt` to ensure sensitive paths are not exposed to crawlers.

## 10. Lecture Handling

Lectures are intended to be hosted externally, typically through Google Drive.

The admin can enter:
- a Google Drive folder URL
- a Google Drive file URL
- a direct video URL
- a simple Drive folder ID

The student lecture player then attempts to display the content appropriately. This keeps storage usage low and makes the system easier to maintain.

## 11. Resource Handling

Resources are PDF files uploaded by the admin.

They are stored in the uploads/pdfs folder and linked to one or more groups. Students only see resources allowed for their own group.

The current implementation validates:
- file type (PDF only)
- file size (maximum 20MB)
- upload success

## 12. Quiz Handling

The quiz system is currently a simple MCQ implementation.

### Supported features
- multiple questions per quiz
- four answer choices per question
- correct answer selection
- optional question images
- group-based quiz availability
- time-limit support
- attempt-limit support
- extra-attempt overrides from admin
- quiz attempt tracking with score percentages and status history

### Student quiz behavior
- Students can start an attempt.
- They may continue an in-progress attempt.
- The system uses the selected answers to calculate a score.
- Results are stored in the quiz_attempts table.
- Students can view their previous attempts and see whether a quiz was not attempted yet.
- The student interface also shows dismissible success/error feedback and a countdown timer when a time limit is active.

## 13. Installation and Setup

### Prerequisites
- PHP 8+
- MySQL / MariaDB
- Apache server (WAMP/XAMPP recommended on Windows)

### Setup Steps
1. Place the project in your local web server directory, for example:
   - C:\wamp64\www\Eyad_LMS

2. Create a MySQL database, for example:
   - eyad_lms

3. Configure the database connection in config/database.php or by setting environment variables:
   - DB_HOST
   - DB_USER
   - DB_PASS
   - DB_NAME

4. Start Apache and MySQL.

5. Open the project in the browser:
   - http://localhost/Eyad_LMS/public/index.php

6. Create an initial admin account in the users table with role = admin.

### Note on database initialization
The application uses CREATE TABLE IF NOT EXISTS statements in several admin pages, so many database tables can be created automatically when those pages are first accessed. A formal SQL dump is not required for the basic setup flow.

## 14. Configuration Notes

The main database configuration is defined in config/database.php.

The file currently uses:
- environment variables when available
- fallback values for local development

If your local setup uses a different username, password, or database name, update the values there accordingly.

## 15. File Uploads and Storage

Uploaded files are stored under the uploads folder:

- uploads/pdfs for resource PDFs
- uploads/quizzes for quiz question images

These directories should be writable by the web server.

## 16. Security Notes

The current implementation includes several basic security practices:

- password hashing for user credentials
- session-based login checks
- role-based access restrictions
- file validation for uploaded PDFs and images

However, the project is still a lightweight educational portal and should be hardened further before production use in a public environment.

## 17. Current Limitations

This version intentionally stays simple. It does not currently include:

- self-registration
- email verification
- online payments
- advanced analytics
- live video streaming
- complex grading rules
- fully expanded permissions beyond admin/student roles
- fully completed recording/screenshot protection for the lecture player (the current black overlay is a prototype and not a production-proof solution)

## 18. Deployment Checklist

Before deploying the project to a live server, confirm the following:

- the database connection is correct
- the uploads folders are writable
- PHP extensions needed for file handling and MySQL are enabled
- the site is served over HTTPS
- admin credentials are created securely
- student content access is tested end to end

## 19. Future Enhancements

The current implementation can be extended in many ways, including:

- progress tracking
- certificates
- attendance tracking
- homework submission
- notifications
- discussion boards
- richer analytics
- multi-teacher support
- more advanced quiz behavior

## 20. Summary

Eyad LMS is now a functional, simple, and practical learning platform for a single instructor. It covers the core education workflow needed for a teacher to manage students, multi-group access, learning sessions, resources, quizzes, and student progress in one place.

