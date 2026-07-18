# Eyad LMS

Eyad LMS is a lightweight Learning Management System designed for Eng. Eyad Mazhar. The platform is built as a simple, modern, and affordable educational portal for a single instructor managing students, lectures, resources, and quizzes.

## Project Purpose

The system is intended to solve the teacher’s daily workflow without becoming overly complex. It supports:

- a public website for marketing and access
- an admin panel for the teacher
- a student portal for assigned content
- group-based access control
- simple quiz management

## Technology Stack

### Frontend
- HTML5
- CSS3
- JavaScript
- Bootstrap 5

### Backend
- PHP 8+
- MySQL
- Apache

### Authentication
- session-based authentication
- role-based access for admin and student users

## Core Features

### Public Area
- home/login entry point
- accessible public-facing pages

### Admin Panel
- login and protected access
- manage students
- manage groups
- create and assign lectures
- upload and assign PDF resources
- create MCQ quizzes
- view content by group
- access dedicated pages for students, groups, lectures, resources, and quizzes
- use dashboard cards that link directly to the main management pages

### Student Portal
- login and protected access
- view lectures assigned to the student’s group
- view resources assigned to the student’s group
- view quizzes assigned to the student’s group
- submit quiz answers and receive instant scoring feedback
- use a responsive student dashboard and navigation menu

## Current Implementation Status

The project currently includes the following working modules:

- authentication and role-based access
- admin dashboard and navigation
- student dashboard and navigation
- student management
- group management
- lecture management
- resource management
- quiz management
- student-facing lecture, resource, and quiz pages
- public landing, about, and contact pages
- student password change flow
- logo integration in admin and student navigation

## Database

The database is centered around the following entities:

- users
- groups
- lectures
- lecture_folder_access
- resources
- quizzes
- questions
- quiz_attempts

The system uses a simple group-based model where each student belongs to one group and can access content assigned to that group.

## Project Structure

```text
Eyad_LMS/
├── admin/              # admin panel pages
├── student/            # student portal pages
├── public/             # public pages such as login
├── includes/           # shared layout and auth helpers
├── config/             # database configuration
├── database/           # SQL scripts
├── uploads/            # uploaded files
├── Images/             # branding images
└── README.md           # project documentation
```

## Setup Instructions

### 1. Prepare the database
Create a MySQL database and import the SQL schema file from the database folder, then optionally run the dummy content SQL file for sample data.

### 2. Configure database connection
Update the database credentials in:

- [config/database.php](config/database.php)

### 3. Start the project locally
Place the project inside your local web server root such as WAMP and open the login page through your browser.

### 4. Login
Default admin credentials:

- username: admin
- password: password

## Design Notes

The project follows a clean and minimal design philosophy:

- professional academic look
- simple navigation
- responsive layouts where applicable
- no unnecessary animations or clutter

## Future Enhancements

Possible future improvements include:

- progress tracking
- certificates
- notifications
- homework submission
- live classes
- attendance tracking
- richer analytics
- more advanced quiz behavior

## Notes for Development

The current version is intentionally simple and focused on the core educational workflow. The architecture is modular so future versions can extend the platform without a complete rewrite.
