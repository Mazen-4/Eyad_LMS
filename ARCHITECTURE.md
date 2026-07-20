# Eyad Mazhar Learning Platform
## Technical Architecture

---

# Overview

The Eyad Mazhar Learning Platform is a lightweight Learning Management System (LMS) designed for a single instructor.

The project is built by adapting the VenturePoint full-stack platform into an educational platform while reusing as much production-tested code as possible.

The system follows a modular architecture so future educational websites can be built on top of it with minimal modifications.

### Mobile-first requirement

The student portal must support mobile view as a core requirement.
The interface should be responsive and optimized for phones and tablets, especially for lectures, resources, and quizzes.

---

# Technology

Frontend
- HTML5
- CSS3
- JavaScript
- Bootstrap 5

Backend
- PHP 8+
- MySQL

Server
- Apache

Authentication
- Session Authentication

Video Hosting
- Google Drive Embed (Version 1)

Hosting
- GoDaddy Economy (25GB)

---

# System Architecture

                    +----------------------+
                    |     Public Website   |
                    +----------+-----------+
                               |
                               |
                               v
                    +----------------------+
                    | Authentication Layer |
                    +----------+-----------+
                               |
              +----------------+----------------+
              |                                 |
              v                                 v
      +---------------+               +----------------+
      | Student Panel |               |  Admin Panel   |
      +---------------+               +----------------+

---

# Roles

## Admin

Permissions

- Full Dashboard Access
- Create Students
- Edit Students
- Delete Students
- Create Lecture Groups
- Upload PDFs
- Add Lectures
- Create Quizzes

---

## Student

Permissions

- Login
- Watch Lectures
- Download PDFs
- Solve Quizzes
- Change Password

Students cannot

- Register
- Upload Files
- Edit Content
- Access Admin

---

# Authentication Flow

Teacher

↓

Admin Dashboard

↓

Create Student

↓

Assign Group

↓

Generate Username & Password

↓

Send Credentials

↓

Student Login

↓

Access Assigned Content

---

# Lecture Organization

Lecture Groups

The platform supports many lecture groups.

Each student is assigned to a group, and the teacher can create as many groups as needed.

Lectures are organized in Google Drive folders. The admin assigns one or more groups to each folder, and students only access the folders allowed for their group.

---

# Phase 2 Client Requirements

The following requirements should be treated as part of the next implementation phase:

- Rename the content area from "Lectures" to "Sessions" in the interface and navigation
- Update the branding in the navbar to show "Eng. Eyad Mazhar" instead of "Eyad LMS"
- Allow students to belong to multiple groups for broader access control
- Enforce a limit on the number of devices that can access one account
- Support multiple admin accounts with role-based permissions
- Provide quiz review functionality with attempt history and score visibility
- Remove the button that opens the lecture in Google Drive from the lecture page
- Automatically convert uploaded Google Drive links into the correct preview/embed format
- Improve lecture security by tracking view attempts and preventing recording or screenshot capture where technically feasible

---

# Module Structure

Public Website

- Home
- About
- Contact
- Login

Student Portal

- Dashboard
- Lectures
- Resources
- Quizzes
- Profile

Admin Panel

- Dashboard
- Students
- Lecture Groups
- Lectures
- Resources
- Quizzes
- Website Settings

---

# Database Design

Tables

Users

id
name
username
password
phone
parent_phone
group_id
role
status

---

Groups

id
name

Examples

Basic

Advanced 1

Advanced 2

---

Lectures

id
title
description
drive_folder_id
display_order
status

---

Lecture Folder Access

id
folder_id
group_id

---

Resources

id
title
description
pdf
group_id
status

---

Quizzes

id
title
group_id

---

Questions

id
quiz_id
question

choice_1

choice_2

choice_3

choice_4

correct_answer

---

Quiz Attempts

id
student_id
quiz_id
score
submitted_at

---

Website Settings

id

site_name

phone

logo

hero_title

hero_description

social_links

---

Relationships

Group

↓

Students

↓

Lecture Folder Access

↓

Lectures

↓

Resources

↓

Quizzes

---

Folder Structure

project/

│

├── admin/

├── student/

├── public/

├── assets/

│   ├── css/

│   ├── js/

│   ├── images/

│

├── uploads/

│   ├── pdfs/

│

├── includes/

│

├── controllers/

│

├── models/

│

├── middleware/

│

├── config/

│

└── database/

---

Reused VenturePoint Modules

Authentication

Dashboard

Settings

Users

Uploads

Permissions

Responsive Layout

CMS Components

These modules should remain as generic as possible for future projects.

---

Modified VenturePoint Modules

Users

↓

Students

Publications

↓

Resources

Categories

↓

Lecture Groups

Homepage

↓

Educational Landing Page

---

Removed Modules

Consulting Services

Economic Reports

Clients

Business Analytics

Consultation Forms

Any VenturePoint-specific branding

---

Content Flow

Teacher

↓

Uploads Google Drive Video

↓

Copies Embed Link

↓

Creates Lecture

↓

Student Logs In

↓

Embedded Video Loads

↓

Student Watches Lecture

---

PDF Flow

Teacher

↓

Upload PDF

↓

Assign Lecture Group

↓

Students Can Download

---

Quiz Flow

Teacher

↓

Create Quiz

↓

Add Questions

↓

Publish

↓

Student Solves

↓

Score Saved

---

Security

Password Hashing

Prepared SQL Statements

Role Middleware

Session Authentication

Admin Route Protection

Input Validation

File Type Validation

Upload Restrictions

---

Future Roadmap

Version 1

✔ Login

✔ Lectures

✔ PDFs

✔ Quizzes

✔ Students

✔ Admin Panel

Version 2

Attendance

Homework

Progress Tracking

Certificates

Notifications

Search

Version 3

Multiple Teachers

Payment Integration

Student Dashboard Analytics

Mobile App

API

Cloud Video Hosting

---

Design Goals

Minimal

Fast

Responsive

Maintainable

Reusable

Production Ready

Simple enough for teachers with limited technical experience.