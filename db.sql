-- Performance Appraisal System Database Schema

-- Users table with comprehensive employee information
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    emp_number VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL COMMENT 'Personal email for login',
    emp_email VARCHAR(255) UNIQUE COMMENT 'Official company email',
    position VARCHAR(255) NOT NULL,
    direct_superior INT NULL,
    department VARCHAR(255) NOT NULL,
    date_joined DATE NOT NULL,
    site VARCHAR(255) NOT NULL,
    role ENUM('admin', 'manager', 'employee', 'worker') DEFAULT 'employee',
    password VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (direct_superior) REFERENCES users(id) ON DELETE SET NULL
);

-- Form templates (Management Staff, General Staff, Workers)
CREATE TABLE forms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    form_type VARCHAR(50) NOT NULL COMMENT 'management, general, worker',
    title VARCHAR(255) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Form sections (Cultural Values, Performance Assessment, etc.)
CREATE TABLE form_sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    form_id INT NOT NULL,
    section_title VARCHAR(255) NOT NULL,
    section_description TEXT,
    section_order INT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE CASCADE
);

-- Questions within each section
CREATE TABLE form_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section_id INT NOT NULL,
    question_text TEXT NOT NULL,
    question_description TEXT,
    response_type ENUM('text', 'textarea', 'rating_5', 'rating_10', 'checkbox', 'radio') NOT NULL,
    options JSON COMMENT 'For checkbox/radio options',
    is_required BOOLEAN DEFAULT TRUE,
    question_order INT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (section_id) REFERENCES form_sections(id) ON DELETE CASCADE
);

-- Cultural values (H³CIS framework)
CREATE TABLE cultural_values (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10) NOT NULL COMMENT 'H, C, I, S',
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE
);

-- Appraisal instances
CREATE TABLE appraisals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    form_id INT NOT NULL,
    appraiser_id INT NULL COMMENT 'Manager who reviews',
    appraisal_period_from DATE NOT NULL,
    appraisal_period_to DATE NOT NULL,
    status ENUM('draft', 'submitted', 'in_review', 'completed', 'cancelled') DEFAULT 'draft',
    total_score DECIMAL(5,2) NULL,
    performance_score DECIMAL(5,2) NULL,
    grade VARCHAR(5) NULL COMMENT 'A, B+, B, B-, C',
    employee_submitted_at TIMESTAMP NULL,
    manager_reviewed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (form_id) REFERENCES forms(id),
    FOREIGN KEY (appraiser_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Response storage (both employee and manager responses)
CREATE TABLE responses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appraisal_id INT NOT NULL,
    question_id INT NOT NULL,
    employee_response TEXT NULL,
    employee_rating INT NULL,
    employee_comments TEXT NULL,
    manager_response TEXT NULL,
    manager_rating INT NULL,
    manager_comments TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (appraisal_id) REFERENCES appraisals(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES form_questions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_response (appraisal_id, question_id)
);

-- Training and development needs
CREATE TABLE training_needs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(255) NOT NULL,
    description TEXT,
    applicable_roles JSON COMMENT 'Array of roles this applies to',
    is_active BOOLEAN DEFAULT TRUE
);

-- Audit logging
CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(100) NOT NULL,
    record_id INT NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    details TEXT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Insert default cultural values (H³CIS)
INSERT INTO cultural_values (code, title, description) VALUES
('H', 'Hard Work', 'Commitment to diligence and perseverance in all aspects of Operations'),
('H', 'Honesty', 'Integrity in dealings with customers, partners and stakeholders'),
('H', 'Harmony', 'Fostering Collaborative relationships and a balanced work environment'),
('C', 'Customer Focus', 'Striving to be the "Only Supplier of Choice" by enhancing customer competitiveness'),
('I', 'Innovation', 'Embracing transformation and agility, as symbolized by their "Evolving with Momentum" theme'),
('S', 'Sustainability', 'Rooted in organic growth and long-term value creation, reflected in their visual metaphors');

-- Insert default training needs
INSERT INTO training_needs (category, description, applicable_roles) VALUES
('Business Writing', 'Improve written communication skills', '["manager", "employee"]'),
('Distribution Management', 'Supply chain and logistics management', '["manager", "employee"]'),
('Product Knowledge', 'Understanding of company products and services', '["manager", "employee", "worker"]'),
('Change Management', 'Leading organizational change initiatives', '["manager"]'),
('Finance for Non Finance Executives', 'Financial literacy for managers', '["manager"]'),
('Project Management', 'Project planning and execution skills', '["manager", "employee"]'),
('Communication Skills', 'Verbal and interpersonal communication', '["manager", "employee", "worker"]'),
('Leadership Skills', 'People management and leadership', '["manager"]'),
('Risk Management', 'Identifying and mitigating risks', '["manager", "employee"]'),
('Consultative Selling', 'Customer-focused sales approach', '["manager", "employee"]'),
('Negotiation & Influencing Skills', 'Persuasion and negotiation techniques', '["manager", "employee"]'),
('Team Effectiveness & Cohesiveness', 'Building high-performing teams', '["manager"]'),
('Creative Thinking', 'Innovation and problem-solving creativity', '["manager", "employee"]'),
('Presentation Skills', 'Public speaking and presentation delivery', '["manager", "employee"]'),
('Time Management', 'Productivity and time optimization', '["manager", "employee", "worker"]'),
('Critical Thinking', 'Analytical and logical reasoning', '["manager", "employee"]'),
('Problem Solving & Decision Making', 'Systematic problem resolution', '["manager", "employee"]'),
('Value Proposition', 'Creating and communicating value', '["manager", "employee"]'),
('5S Methodology', 'Workplace organization and efficiency', '["worker"]'),
('Microsoft Office', 'Computer skills and software proficiency', '["employee", "worker"]'),
('Quality Assurance Mindset', 'Quality standards and continuous improvement', '["worker"]'),
('Work Safety', 'Workplace safety and hazard prevention', '["worker"]');

-- Insert default admin user
INSERT INTO users (name, emp_number, email, emp_email, position, department, date_joined, site, role, password) VALUES
('System Administrator', 'ADMIN001', 'admin@company.com', 'admin@ybs.com', 'IT Administrator', 'Information Technology', CURDATE(), 'Head Office', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
-- Password is 'password' hashed



-- Add attachment column to responses table
ALTER TABLE responses ADD COLUMN employee_attachment VARCHAR(255) NULL AFTER employee_comments;
ALTER TABLE responses ADD COLUMN manager_attachment VARCHAR(255) NULL AFTER manager_comments;

-- Update form_questions response_type enum to include attachment
ALTER TABLE form_questions MODIFY COLUMN response_type 
ENUM('text', 'textarea', 'rating_5', 'rating_10', 'checkbox', 'radio', 'attachment', 'display') NOT NULL;

-- Add visible_to column to form_sections
ALTER TABLE form_sections ADD COLUMN visible_to ENUM('both', 'employee', 'reviewer') DEFAULT 'both' 
AFTER section_order;

ALTER TABLE users
ADD COLUMN is_confirmed BOOLEAN DEFAULT FALSE AFTER is_hr;