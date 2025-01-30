-- Create the database if it does not exist
CREATE DATABASE IF NOT EXISTS learning_lms;
USE learning_lms;

-- Person Table
CREATE TABLE `person` (
    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,  
    `first_name` VARCHAR(255) NOT NULL,
    `last_name` VARCHAR(255) NOT NULL,
    `username` VARCHAR(255) NOT NULL,
    `status` TINYINT(1) DEFAULT 1,
    `access_level` INT(11) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) UNIQUE,
    `profile_type` VARCHAR(255),
    `title` VARCHAR(255),
    `company` VARCHAR(255),
    `website` VARCHAR(255),
    `inactive_date` DATETIME,
    `external_employee_id` VARCHAR(255),
    `address` TEXT,
    `city` VARCHAR(255),
    `state_province` VARCHAR(255),
    `zip_code` VARCHAR(255),
    `country` VARCHAR(255),
    `timezone` VARCHAR(255),
    `language` VARCHAR(255),
    `date_format` VARCHAR(255),
    `brand` VARCHAR(255),
    `work_phone` VARCHAR(255),
    `mobile_phone` VARCHAR(255),
    `skype` VARCHAR(255),
    `twitter` VARCHAR(255),
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `role` ENUM('Admin', 'Instructor', 'Learner', 'Manager', 'Support', 'Guest') NOT NULL DEFAULT 'Learner'
);

-- Modules Table
CREATE TABLE `modules` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `module_type` ENUM('assessment', 'learner_upload', 'survey', 'page_of_content', 'live_session', 'checklist', 'embed_content', 'link_to_website', 'esignature') NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NOT NULL,
    `active` BOOLEAN DEFAULT TRUE,
    `mandatory` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Assessment Modules Table
CREATE TABLE `assessment_modules` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `module_id` INT NOT NULL,
    `question_text` TEXT NOT NULL,
    `options` JSON NULL,
    `correct_answer` TEXT NULL,
    `total_marks` INT NOT NULL,
    `pass_marks` INT NOT NULL,
    FOREIGN KEY (`module_id`) REFERENCES `modules`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
);

-- Learner Upload Modules Table
CREATE TABLE `learner_upload_modules` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `module_id` INT NOT NULL,
    `question` TEXT NOT NULL,
    `category` ENUM('assignment', 'certificate', 'other') NOT NULL,
    FOREIGN KEY (`module_id`) REFERENCES `modules`(`id`) ON DELETE CASCADE
);

-- Survey Modules Table
CREATE TABLE `survey_modules` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `module_id` INT NOT NULL,
    FOREIGN KEY (`module_id`) REFERENCES `modules`(`id`) ON DELETE CASCADE
);

-- Page of Content Modules Table
CREATE TABLE `page_of_content_modules` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `module_id` INT NOT NULL,
    `content` TEXT NOT NULL,
    FOREIGN KEY (`module_id`) REFERENCES `modules`(`id`) ON DELETE CASCADE
);

-- Live Session Modules Table
CREATE TABLE `live_session_modules` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `module_id` INT NOT NULL,
    `session_details` TEXT NOT NULL,
    FOREIGN KEY (`module_id`) REFERENCES `modules`(`id`) ON DELETE CASCADE
);

-- Checklist Modules Table
CREATE TABLE `checklist_modules` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `module_id` INT NOT NULL,
    `checklist_items` JSON NOT NULL,
    FOREIGN KEY (`module_id`) REFERENCES `modules`(`id`) ON DELETE CASCADE
);

-- Embed Content Modules Table
CREATE TABLE `embed_content_modules` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `module_id` INT NOT NULL,
    `embed_code` TEXT NOT NULL,
    FOREIGN KEY (`module_id`) REFERENCES `modules`(`id`) ON DELETE CASCADE
);

-- Link to Website Modules Table
CREATE TABLE `link_to_website_modules` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `module_id` INT NOT NULL,
    `external_link` VARCHAR(2083) NOT NULL,
    FOREIGN KEY (`module_id`) REFERENCES `modules`(`id`) ON DELETE CASCADE
);

-- E-signature Modules Table
CREATE TABLE `esignature_modules` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `module_id` INT NOT NULL,
    `esignature_text` TEXT NOT NULL,
    `user_signature_file` VARCHAR(255) NOT NULL,
    FOREIGN KEY (`module_id`) REFERENCES `modules`(`id`) ON DELETE CASCADE
);

-- Module Files Table
CREATE TABLE `module_files` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `module_id` INT NOT NULL,
    `file_path` VARCHAR(255) NOT NULL,
    FOREIGN KEY (`module_id`) REFERENCES `modules`(`id`) ON DELETE CASCADE
);

-- Teams Table
CREATE TABLE `teams` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Team Members Table
CREATE TABLE `team_members` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `team_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `role` ENUM('Member', 'Manager') DEFAULT 'Member',
    `added_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `person`(`id`) ON DELETE CASCADE
);

-- Team Courses Table
CREATE TABLE `team_courses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `team_id` INT NOT NULL,
    `course_id` INT NOT NULL,
    `assigned_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE CASCADE
    -- Uncomment if you have a `courses` table:
    -- FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE
);

-- Course Instructors Table
CREATE TABLE `course_instructors` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `course_id` INT NOT NULL,
    `instructor_id` INT NOT NULL,
    FOREIGN KEY (`course_id`) REFERENCES `course`(`id`),
    FOREIGN KEY (`instructor_id`) REFERENCES `person`(`id`),
    UNIQUE (`course_id`, `instructor_id`)
);

-- Noticeboard Table
CREATE TABLE `noticeboard` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `course_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `content` TEXT NOT NULL,
    `posted_by` INT NOT NULL,
    `post_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `expiry_date` DATETIME NULL,
    FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`),
    FOREIGN KEY (`posted_by`) REFERENCES `person`(`id`)
);

-- Attachments Table
CREATE TABLE `attachments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `noticeboard_id` INT NOT NULL,
    `file_path` VARCHAR(255) NULL,
    `file_name` VARCHAR(255) NULL,
    `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`noticeboard_id`) REFERENCES `noticeboard`(`id`)
);

-- Module Completion Table
CREATE TABLE `module_completion` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `module_id` INT NOT NULL,
    `course_id` INT NOT NULL,
    `status` ENUM('Not Started', 'In Progress', 'Completed') DEFAULT 'Not Started',
    `completed_at` DATETIME DEFAULT NULL,
    FOREIGN KEY (`user_id`) REFERENCES `person`(`id`),
    FOREIGN KEY (`module_id`) REFERENCES `modules`(`id`),
    FOREIGN KEY (`course_id`) REFERENCES `course`(`id`)
);

-- Assessment Answers Table
CREATE TABLE `assessment_answers` (
    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT(11) NOT NULL,
    `module_id` INT(11) NOT NULL,
    `question_id` INT(11) NOT NULL,
    `user_answer` TEXT NOT NULL,
    `is_correct` TINYINT(1) NOT NULL,
    `answered_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `person`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (`module_id`) REFERENCES `modules`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (`question_id`) REFERENCES `assessment_modules`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE TABLE scorm_modules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    module_id INT,
    file_url VARCHAR(255),
    scorm_metadata JSON,  -- Store imsmanifest.xml data as JSON
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (module_id) REFERENCES modules(id)
);