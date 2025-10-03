CREATE DATABASE IF NOT EXISTS notesrapides CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE todo_app;

CREATE TABLE IF NOT EXISTS tasks (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  category ENUM('maison','boulot','perso') NOT NULL,
  status ENUM('a_faire','en_cours','termine') NOT NULL DEFAULT 'a_faire',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
