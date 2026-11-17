USE hireswipe_db;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS interviews;
DROP TABLE IF EXISTS invitations;
DROP TABLE IF EXISTS swipes;
DROP TABLE IF EXISTS jobs;
DROP TABLE IF EXISTS job_seeker_profiles;
DROP TABLE IF EXISTS employer_profiles;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS ref_industries;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Reference Industries
CREATE TABLE ref_industries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    industry_name VARCHAR(100) NOT NULL UNIQUE
);

INSERT INTO ref_industries (industry_name) VALUES 
('Information Technology & Cybersecurity'),
('Finance & FinTech'),
('Healthcare & Biotech'),
('E-Commerce & Retail'),
('Manufacturing & Engineering'),
('Media, Marketing & Design');

-- 2. Users (Authentication & Roles)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(10) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('employer', 'job_seeker', 'pending') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. Employer Profiles
CREATE TABLE employer_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    company_name VARCHAR(255) NOT NULL,
    industry_id INT NULL,
    location VARCHAR(255) NULL,
    latitude DECIMAL(10,8) NULL,
    longitude DECIMAL(11,8) NULL,
    company_logo VARCHAR(255) NULL,
    is_setup TINYINT(1) DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (industry_id) REFERENCES ref_industries(id) ON DELETE SET NULL
);

-- 4. Job Seeker Profiles
CREATE TABLE job_seeker_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    age INT NULL,
    location VARCHAR(255) NULL,
    nationality VARCHAR(100) NULL,
    qualification VARCHAR(255) NULL,
    tickets TEXT NULL,
    experience_years INT DEFAULT 0,
    expected_salary DECIMAL(10,2) NULL,
    preferred_employment_type VARCHAR(50) NULL,
    preferred_industries VARCHAR(255) NULL,
    resume_path VARCHAR(255) NULL,
    profile_picture VARCHAR(255) NULL,
    is_setup TINYINT(1) DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 5. Jobs
CREATE TABLE jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employer_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    employment_type ENUM('Full-time', 'Part-time', 'Contract', 'Internship') NOT NULL,
    experience_required INT DEFAULT 0,
    salary_range VARCHAR(100) NULL,
    location VARCHAR(255) NULL,
    job_type VARCHAR(100) NULL,
    workplace_type ENUM('Remote', 'On-site', 'Hybrid') DEFAULT 'On-site',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_deleted TINYINT(1) DEFAULT 0,
    FOREIGN KEY (employer_id) REFERENCES employer_profiles(id) ON DELETE CASCADE
);

-- 6. Swipes
CREATE TABLE swipes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    job_seeker_id INT NOT NULL,
    direction ENUM('interested', 'rejected') NOT NULL,
    initiator ENUM('employer', 'job_seeker') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    FOREIGN KEY (job_seeker_id) REFERENCES job_seeker_profiles(id) ON DELETE CASCADE,
    UNIQUE KEY unique_swipe_v2 (job_id, job_seeker_id, initiator)
);

-- 7. Invitations
CREATE TABLE invitations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employer_id INT NOT NULL,
    job_seeker_id INT NOT NULL,
    job_id INT NOT NULL,
    status ENUM('pending', 'accepted', 'declined') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employer_id) REFERENCES employer_profiles(id) ON DELETE CASCADE,
    FOREIGN KEY (job_seeker_id) REFERENCES job_seeker_profiles(id) ON DELETE CASCADE,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    UNIQUE KEY unique_invite (employer_id, job_seeker_id, job_id)
);

-- 8. Interviews
CREATE TABLE interviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invitation_id INT NOT NULL UNIQUE,
    scheduled_at DATETIME NOT NULL,
    meeting_link VARCHAR(255) NULL,
    location VARCHAR(255) NULL,
    FOREIGN KEY (invitation_id) REFERENCES invitations(id) ON DELETE CASCADE
);