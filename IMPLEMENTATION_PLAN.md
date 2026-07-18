# Eyad LMS Implementation Plan

## 1. Project Understanding

This project is a simple, professional Learning Management System for Eng. Eyad Mazhar. The platform is built for a single instructor and focuses on solving daily teaching workflow without adding unnecessary complexity.

### Core goals
- Provide a modern public website and a secure student portal
- Let the teacher manage students, groups, lectures, resources, and quizzes
- Keep the system simple, affordable, and easy to maintain
- Use Google Drive for lecture video hosting to reduce storage cost
- Avoid self-registration, online payments, and complex workflows

### Version 1 scope
- Public pages: Home, About, Contact, Login
- Student portal: Dashboard, Lectures, Resources, Quizzes, Password change
- Admin panel: Dashboard, Students, Lecture Groups, Lectures, Resources, Quizzes
- Authentication: username/password for students and teacher
- Content access: group-based access only

---

## 2. Recommended Technical Direction

### Stack
- Frontend: HTML, CSS, JavaScript, Bootstrap 5
- Backend: PHP 8+
- Database: MySQL
- Server: Apache
- Hosting: GoDaddy economy-style shared hosting

### Design principles
- Keep the architecture modular
- Reuse proven patterns from the existing VenturePoint platform where possible
- Keep admin workflows straightforward
- Make group-based access the main permission system
- Favor reliability over feature bloat

---

## 3. Proposed System Structure

```text
project/
├── public/                 # public-facing pages
├── admin/                  # teacher/admin interface
├── student/                # student portal
├── includes/               # shared header/footer/config helpers
├── controllers/            # request handling logic
├── models/                 # database access logic
├── middleware/             # authentication and role checks
├── config/                 # app config and DB connection
├── uploads/                # uploaded PDFs and assets
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
└── database/               # schema and seed scripts
```

---

## 4. Database Plan

### Core tables
- users
  - id
  - name
  - username
  - password
  - phone
  - parent_phone
  - group_id
  - role
  - status

- groups
  - id
  - name

- lectures
  - id
  - title
  - description
  - drive_folder_id
  - display_order
  - status

- lecture_folder_access
  - id
  - folder_id
  - group_id

- resources
  - id
  - title
  - description
  - pdf_path
  - group_id
  - status

- quizzes
  - id
  - title
  - group_id

- questions
  - id
  - quiz_id
  - question
  - choice_1
  - choice_2
  - choice_3
  - choice_4
  - correct_answer

- quiz_attempts
  - id
  - student_id
  - quiz_id
  - score
  - submitted_at

---

## 5. Implementation Phases

### Phase 1 - Foundation
Goal: create the base application structure and authentication.

Tasks:
- Set up folder structure
- Create database connection and config files
- Create users table and seed an admin account
- Build login/logout flow
- Add role-based access control
- Create reusable layout components

Deliverable:
- Admin can log in
- Student can log in
- Access is restricted by role

### Phase 2 - Public Website
Goal: build the public-facing marketing pages.

Tasks:
- Home page
- About page
- Contact page
- Login page
- Responsive, modern UI

Deliverable:
- Visitors can view the public website and log in

### Phase 3 - Student Portal
Goal: give students access to their assigned content.

Tasks:
- Student dashboard
- Group-based lecture listing
- Group-based resource listing
- Quiz listing and answering
- Change password

Deliverable:
- Students only see content assigned to their group

### Phase 4 - Admin Panel
Goal: let the teacher manage the platform.

Tasks:
- Admin dashboard
- Student management CRUD
- Group management CRUD
- Lecture management CRUD
- Resource upload and management
- Quiz and question management

Deliverable:
- Teacher can manage all core LMS features from one panel

### Phase 5 - Polish and Deployment
Goal: harden the experience and prepare it for hosting.

Tasks:
- Improve validation and error handling
- Secure file uploads
- Test role permissions
- Prepare environment config for production
- Deploy to shared hosting

Deliverable:
- Production-ready version 1 release

---

## 6. Priority Order

1. Authentication and roles
2. Student and admin dashboard shells
3. Group-based content access
4. Lectures and resources
5. Quizzes
6. Polish and deployment

---

## 7. Recommended Development Approach

### Start with these first
- Login system
- User roles
- Group-based permission logic
- Basic admin CRUD screens

### Keep simple for version 1
- No online payments
- No email verification
- No complex analytics
- No timers or advanced quiz logic
- No self-registration

---

## 8. Suggested Milestones

### Milestone 1
Basic login and role-based access working

### Milestone 2
Admin can manage students and groups

### Milestone 3
Students can access assigned lectures and resources

### Milestone 4
Quizzes are working end-to-end

### Milestone 5
Site is deployed and usable by the teacher

---

## 9. Next Action

The immediate next step should be to build the foundation layer:
- database schema
- authentication system
- base admin/student layouts

This will create the backbone for the rest of the platform.
