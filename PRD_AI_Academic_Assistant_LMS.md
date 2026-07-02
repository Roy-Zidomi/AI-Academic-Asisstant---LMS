# PRODUCT REQUIREMENTS DOCUMENT (PRD)

## AI Academic Assistant Learning Management System (AI-AA LMS)

**Version:** 1.0\
**Author:** Roy Aldonit Zidomi\
**Date:** July 2026\
**Status:** Draft for Implementation

------------------------------------------------------------------------

# 1. Executive Summary

## 1.1 Project Name

**AI Academic Assistant Learning Management System (AI-AA LMS)**

## 1.2 Vision

Develop an intelligent Learning Management System based on Moodle
integrated with Artificial Intelligence to improve teaching
effectiveness, learning experience, and academic productivity.

## 1.3 Objective

The project aims to build a Proof-of-Concept (PoC) LMS that combines
traditional LMS features with AI-powered academic assistance
capabilities.

## 1.4 Expected Outcome

-   Functional Moodle deployment using Docker
-   PostgreSQL integration
-   AI Academic Assistant
-   AI Quiz Generator
-   AI Material Summarizer
-   Documentation and evaluation report

------------------------------------------------------------------------

# 2. Background

Traditional LMS platforms provide learning management capabilities but
lack intelligent assistance features. Students often struggle to
understand materials independently, while lecturers spend significant
time preparing educational content.

Recent advances in Large Language Models (LLMs) enable the development
of intelligent educational systems capable of assisting students and
lecturers through natural language interactions and automated content
generation.

------------------------------------------------------------------------

# 3. Problem Statement

## Student Problems

-   Difficulty understanding learning materials.
-   Lack of personalized tutoring.
-   Difficulty identifying important topics.
-   No interactive academic assistant.

## Lecturer Problems

-   Time-consuming quiz creation.
-   Manual content summarization.
-   Difficulty creating diverse assessments.

------------------------------------------------------------------------

# 4. Goals

## Business Goals

-   Explore AI implementation in education.
-   Evaluate Moodle as a corporate LMS platform.
-   Produce a functional AI-powered LMS prototype.

## User Goals

### Student

-   Ask academic questions.
-   Obtain learning summaries.
-   Improve learning efficiency.

### Lecturer

-   Generate quizzes automatically.
-   Generate summaries.
-   Improve productivity.

------------------------------------------------------------------------

# 5. Scope

## Included

-   Moodle LMS
-   PostgreSQL
-   Docker deployment
-   Authentication
-   Course management
-   Assignment
-   Quiz
-   AI Chatbot
-   AI Quiz Generator
-   AI Summarizer

## Excluded

-   Mobile app
-   Video conferencing
-   AI grading
-   Recommendation engine
-   Fine-tuned models

------------------------------------------------------------------------

# 6. Stakeholders

  Role            Responsibility
  --------------- ---------------------
  Project Owner   Project direction
  Admin           System management
  Lecturer        Course management
  Student         Learning activities
  Developer       Implementation

------------------------------------------------------------------------

# 7. User Personas

## Student

Age: 18-25 years Goals: - Understand materials - Ask AI questions -
Learn efficiently

## Lecturer

Age: 25-60 years Goals: - Create content quickly - Generate quizzes -
Monitor students

------------------------------------------------------------------------

# 8. Functional Requirements

## FR-01 Authentication

-   Login
-   Logout
-   Session management
-   Role management

## FR-02 User Management

-   Create user
-   Edit user
-   Delete user
-   Assign role

## FR-03 Course Management

-   Create course
-   Manage enrollment
-   Upload material

## FR-04 Assignment

-   Create assignment
-   Submit assignment
-   Grade assignment

## FR-05 Quiz

-   Create quiz
-   Take quiz
-   Automatic grading

------------------------------------------------------------------------

# 9. AI Features

## AI-01 Academic Assistant

### Objective

Provide intelligent tutoring support.

### Input

Natural language question.

### Output

AI-generated academic answer.

### Flow

Student → Moodle → AI Service → LLM → Response

------------------------------------------------------------------------

## AI-02 Quiz Generator

### Objective

Generate quiz questions from learning materials.

### Input

PDF learning material.

### Output

-   Multiple choice questions
-   Essay questions
-   True/False questions

### Flow

PDF → Extraction → LLM → Quiz → Moodle

------------------------------------------------------------------------

## AI-03 Material Summarizer

### Objective

Summarize learning material.

### Input

PDF document.

### Output

-   Summary
-   Key points
-   Important concepts

------------------------------------------------------------------------

# 10. Non-Functional Requirements

  Category          Requirement
  ----------------- -------------
  Availability      99%
  Response Time     \<5 seconds
  Security          HTTPS
  Scalability       Docker
  Reliability       High
  Maintainability   Modular

------------------------------------------------------------------------

# 11. System Architecture

``` text
Browser
   |
Moodle Frontend
   |
Moodle Backend (PHP)
   |
+-------------+
| PostgreSQL  |
+-------------+
   |
AI Service
   |
Ollama
   |
Llama3/Qwen
```

------------------------------------------------------------------------

# 12. Technology Stack

  Layer        Technology
  ------------ ---------------
  LMS          Moodle 5
  Backend      PHP
  Database     PostgreSQL 17
  AI Runtime   Ollama
  LLM          Llama3/Qwen
  Container    Docker
  DB Admin     pgAdmin

------------------------------------------------------------------------

# 13. Docker Architecture

``` text
Docker Compose
    |
    +-- Moodle
    +-- PostgreSQL
    +-- Ollama
    +-- pgAdmin
```

------------------------------------------------------------------------

# 14. Security Requirements

-   RBAC
-   Session management
-   Input validation
-   SQL injection prevention
-   XSS prevention
-   HTTPS

------------------------------------------------------------------------

# 15. Success Metrics

  Metric               Target
  -------------------- ----------------
  Moodle Deployment    100%
  AI Response          \<5 sec
  Summary Generation   \<30 sec
  Quiz Generation      \>10 questions
  User Satisfaction    \>80%

------------------------------------------------------------------------

# 16. Milestones

  Week   Activity
  ------ ---------------------
  1      Docker & PostgreSQL
  1      Moodle Installation
  2      Course Setup
  3      Ollama Setup
  4      AI Chatbot
  5      AI Summarizer
  5      AI Quiz Generator
  6      Testing
  6      Documentation

------------------------------------------------------------------------

# 17. Deliverables

## Technical

-   Docker Compose
-   Moodle Instance
-   PostgreSQL Database
-   Ollama Integration

## Documentation

-   PRD
-   Architecture Diagram
-   ERD
-   API Documentation
-   Testing Report

------------------------------------------------------------------------

# 18. Future Development

-   AI Recommendation Engine
-   Adaptive Learning
-   RAG Knowledge Base
-   Voice Assistant
-   AI Analytics
-   Mobile Application

------------------------------------------------------------------------

# Final Project Title

**AI Academic Assistant Learning Management System Using Moodle,
PostgreSQL, Docker, and Large Language Models for Intelligent Learning
Support**
