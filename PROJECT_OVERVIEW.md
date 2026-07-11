# Eyad Mazhar Learning Platform
### Project Overview

## About

This project is a custom educational platform for **Eng. Eyad Mazhar**, an American Mathematics instructor in Egypt.

The website acts as both a marketing website and a secure learning portal where students can watch lectures, download PDFs, and take quizzes.

This project is intentionally kept simple for Version 1.

It is built by adapting an existing production-ready full-stack platform (originally developed for VenturePoint Egypt) into a Learning Management System (LMS), greatly reducing development time while maintaining a high-quality architecture.

---

# Teacher Information

Name:
Eng. Eyad Mazhar

Phone:
01068161808

Specialization:
American Mathematics

Teaching Programs:
- American Math
- EST
- DSAT
- ACT

Target Students:
American education system students in Egypt.

---

# Development Goal

The website is being developed as a gift.

The objective is NOT to build a feature-rich LMS similar to Moodle.

Instead, the objective is to create a clean, modern, easy-to-use platform that solves the teacher's daily workflow with minimal complexity.

---

# Main Concept

Students do NOT register themselves.

Instead,

1. Student pays the teacher outside the website.
2. Teacher manually creates an account.
3. Teacher gives the student:
   - Username
   - Password
4. Student logs in and gains access.

No online payments.

No email verification.

No approval workflow.

Everything remains simple.

---

# Website Sections

## Public Website

Accessible without login.

Pages:

- Home
- About
- Contact
- Login

No registration page.

---

## Student Portal

After login students can access:

- Dashboard
- Lectures
- Resources (PDFs)
- Quizzes

Optional:

- Change Password

Students only see the content assigned to their group.

### Mobile Experience

The student portal must support mobile view as a first-class requirement.
The experience should remain clean, readable, and fully usable on phones and tablets.
Navigation, content cards, forms, and quiz screens should be optimized for smaller screens.

---

## Admin Panel

Teacher-only dashboard.

Modules:

- Dashboard
- Students
- Lecture Groups
- Lectures
- Resources
- Quizzes
- Website Settings

---

# Student Management

Teacher manually creates accounts.

Student fields:

- Name
- Phone Number
- Parent Phone Number
- Username
- Password
- Group
- Active / Inactive

No email required.

No grade required.

Each student is assigned to a group, and the system supports many groups.

---

# Lecture Organization

The platform does NOT organize content by school grade.

Instead, the teacher can create many learning groups.

Each student is assigned to a group, and students can only access content assigned to their group.

Lectures are distributed in Google Drive folders. The admin (teacher) assigns one or more groups to each folder, and students only see the folders that are allowed for their group.

---

# Lecture Structure

Each lecture contains:

- Title
- Description (optional)
- Google Drive folder reference
- Display Order

Videos are NOT stored on the hosting.

Instead, Google Drive hosts the lecture videos inside folders.

The teacher assigns the relevant groups to each folder, so students only access the folders allowed for their group.

---

# Resources (PDFs)

The VenturePoint "Publications" module will be reused.

It will simply be renamed to:

Resources

Each resource contains:

- Title
- Description
- PDF
- Group
- Visibility

---

# Quizzes

Simple MCQ system.

Each quiz contains:

- Question
- Four Answers
- Correct Answer

Version 1 intentionally excludes:

- Timers
- Negative marking
- Detailed analytics
- Randomization

---

# Authentication

Only teacher creates accounts.

Students cannot create accounts.

Authentication is username/password based.

---

# Hosting Strategy

Hosting:

GoDaddy Economy (25GB)

Storage:

Website only.

Videos are hosted externally.

Domain:

Purchased separately.

Expected yearly hosting cost:

Approximately 4,500–5,000 EGP.

---

# Video Strategy

Version 1:

Google Drive embeds.

Future versions may migrate to:

- Vimeo
- Cloudflare R2
- AWS S3
- Bunny Stream

without changing the website architecture.

---

# Existing Platform Reuse

The project is built by adapting VenturePoint.

Expected reused modules:

✔ Authentication

✔ Users

✔ Dashboard

✔ CMS

✔ File Uploads

✔ Settings

✔ Roles

Renamed modules:

Publications
→ Resources

Categories
→ Lecture Groups

Users
→ Students

---

# Design Philosophy

Minimal.

Professional.

Modern.

Academic.

No unnecessary animations.

No visual clutter.

The UI should feel closer to modern SaaS dashboards than traditional educational websites.

---

# Color Palette

Primary:
Navy Blue

Secondary:
White

Accent:
Bright Blue

Neutral:
Dark Gray

The branding should communicate:

- Trust
- Professionalism
- Simplicity
- Education

---

# Logo Direction

The logo should NOT copy existing educational websites.

Instead:

Minimal.

Modern.

Elegant.

Typography-first.

Possibly built around an "EM" monogram.

The logo should work well both on:

- Website
- Social Media
- Mobile
- Printed materials

---

# Future Features (Not Version 1)

Possible future improvements:

- Progress tracking
- Certificates
- Notifications
- Homework submission
- Live classes
- Discussion section
- Multiple teachers
- Payment integration
- Attendance
- Student statistics

These should NOT influence Version 1 architecture.

---

# Project Objective

Deliver a clean, production-ready educational platform that is:

- Fast
- Secure
- Easy for the teacher
- Easy for students
- Low maintenance
- Affordable to host
- Easily extensible in future versions
