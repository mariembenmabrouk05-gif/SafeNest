CREATE DATABASE IF NOT EXISTS safenest DEFAULT CHARACTER SET utf8mb4;
USE safenest;

CREATE TABLE IF NOT EXISTS users (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(100) UNIQUE NOT NULL,
    password   VARCHAR(255) NOT NULL,
    role       ENUM('parent','child') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS threat_events (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    child_id    INT NOT NULL,
    threat_type VARCHAR(100) NOT NULL,
    severity    ENUM('low','medium','high') NOT NULL,
    context     TEXT NOT NULL,
    status      ENUM('new','reviewed','resolved') DEFAULT 'new',
    timestamp   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (child_id) REFERENCES users(id) ON DELETE CASCADE
);
