# Eyad LMS Documentation

This document describes the current implementation of Eyad LMS, a lightweight learning management system built for Eng. Eyad Mazhar. The platform is designed to be simple, professional, and practical for a single instructor managing students, learning sessions, resources, and quizzes.

## 1. Project Overview

Eyad LMS is a web-based educational portal that combines three main experiences:

- a public website for visitors and prospective students
- an admin panel for the instructor
- a student portal for assigned learning content

The system is intentionally focused on the core teaching workflow rather than trying to replicate a large enterprise LMS. The current version is meant to be affordable, easy to maintain, and suitable for a teacher running a small or medium-sized course program.

## 2. Goals of the System

The implementation aims to provide:

- secure login for admins and students
- flexible group-based access control for educational content
- a clean student experience for sessions, PDFs, and quizzes
- a simple admin workflow for managing all core LMS data
- responsive layouts that work well on desktop and mobile devices
- improved quiz workflows with attempt tracking, timers, and history

## 3. What Has Been Implemented

The current codebase includes the following completed features:

### Public Website
- Home page with a modern landing experience
- About page
- Contact page with a completed contact form using Web3Forms API and async form submission
- Login page with improved visibility for username/password labels and hidden helper text
- Shared navigation and footer components
- Branding and logo integration

### Authentication and Access Control
- session-based authentication
- login/logout flow
- role-based redirection for admin and student users
- protected pages for admin and student sections
- student group information loaded from the database during the session
- metadata tags added to public pages for better SEO and social preview (Open Graph + Twitter cards)

### Admin Panel
- admin dashboard with quick access cards for Students, Admins, Groups, Sessions, Resources, and Quizzes
- student management with create, update, delete, and multi-group assignment
- group management for organizing students into learning cohorts
- session/lecture management with title, description, display order, status, and group visibility
- resource management with PDF upload, description, status, and group visibility
- quiz management with MCQ questions, image support, group access, time limits, attempt limits, extra-attempt overrides, and question removal during quiz editing
- minimal admin account creation for additional administrators

### Student Portal
- student dashboard
- session listing filtered by all groups assigned to the student
- lecture/player experience with support for direct video files, YouTube links, Google Drive links, and Drive folders
- experimental lecture player security overlay to discourage recording and screenshot capture, based on visibility, blur, keyboard shortcuts, and player interaction events (prototype only)
- resource listing for PDFs assigned to the student’s groups
- quiz experience with start/continue functionality, timer support, attempt history, score percentages, and dismissible feedback
- password change flow for students

### Latest Site State Notes
- Contact form now sends messages through Web3Forms with browser-side async handling and button feedback.
- Public pages include SEO and social metadata tags for Open Graph and Twitter.
- Login page text and helper labels were fixed so low-contrast hidden text is visible.
- Quiz editing now supports removing individual questions and optionally removing associated images.
- The lecture player includes a black overlay prototype to discourage screen recording, but it is not a production-secure recording protection solution.

## 4. Technology Stack

### Frontend
- HTML5
- CSS3
- JavaScript
- Bootstrap 5

### Backend
- PHP 8+
- MySQL / MariaDB
- Apache through WAMP/XAMPP

### Other Notes
- The UI uses a simple, modern, academic-style design with shared layout partials.
- The project relies on PHP sessions and MySQL for persistence.

## 5. Project Structure

```text
Eyad_LMS/
├── admin/                # admin pages
├── student/              # student portal pages
├── public/               # public pages such as home/about/contact/login
├── includes/             # shared layout and auth helpers
├── config/               # database and environment configuration
├── assets/               # CSS and front-end assets
├── uploads/              # uploaded PDF resources and quiz images
├── Images/               # branding/logo assets
└── README.md             # project documentation
```

## 6. Core Application Modules

### 6.1 Public Pages
The public experience is available to anyone without authentication.

Pages include:
- Home
- About
- Contact
- Login

These pages are designed to act as a simple entrance point into the LMS and provide a professional public-facing presence.

### 6.2 Admin Pages
The admin area is restricted to users with the admin role.

Available admin modules:
- Dashboard
- Students
- Groups
- Sessions
- Resources
- Quizzes
- Admins

Admin users can manage the full learning content lifecycle from one panel.

### 6.3 Student Pages
The student area is restricted to users with the student role.

Available student modules:
- Dashboard
- Sessions
- Resources
- Quizzes
- Change Password

Students see content assigned to any group they belong to, which allows broader access when they are linked to multiple groups.

## 7. Authentication and Authorization Model

Authentication is handled through the shared auth helper in the includes folder.

### How it works
- Users log in with a username and password.
- Passwords are verified using PHP password hashing.
- A session is created with the user identity and role.
- The application redirects users to the correct dashboard based on their role.

### Roles
- admin: full access to the admin panel and management features
- student: access only to allowed student portal pages and assigned content

### Access control behavior
- Students can access sessions, resources, and quizzes assigned to any group they belong to.
- Admin users can manage all content regardless of group.

## 8. Database Design

The system uses a MySQL database with the following main tables:

### users
Stores user accounts for admins and students.

Important fields include:
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
Stores learning groups.

Used to control student access to lectures, resources, and quizzes.

### lectures
Stores lecture records.

Contains:
- title
- description
- drive_folder_id
- display_order
- status

### lecture_folder_access
Links lectures to the groups that should be able to view them.

### resources
Stores PDF resource records.

Contains:
- title
- description
- pdf_path
- status

### resource_group_access
Links resources to the groups that can access them.

### quizzes
Stores quiz metadata.

Contains:
- title
- group_id
- status
- time_limit_minutes
- max_attempts

### quiz_group_access
Links quizzes to the groups that can access them.

### questions
Stores MCQ questions for each quiz.

Contains:
- question
- choice_1 to choice_4
- correct_answer
- image_path

### quiz_attempts
Tracks student quiz submissions and scores.

### quiz_extra_attempts
Stores admin-granted extra quiz attempts for specific students.

## 9. Core Workflows

### 9.1 Admin Workflow
An admin user can:

1. Create student accounts and assign them to one or more groups.
2. Create learning groups such as Basic, Advanced 1, and Advanced 2.
3. Add sessions/lectures and connect them to one or more groups.
4. Upload PDF resources and assign them to groups.
5. Create quizzes with multiple questions and correct answers.
6. Set time limits and maximum attempts for quizzes.
7. Grant extra attempts to students when needed.
8. Create additional admin accounts when needed.

### 9.2 Student Workflow
A student user can:

1. Log in to the student portal.
2. View sessions assigned to any group they belong to.
3. Open session content through the session viewer.
4. Download or view PDF resources assigned to their groups.
5. Start, continue, and complete quizzes available for their groups.
6. Review previous quiz attempts and scores.
7. Change their password.

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

