-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 26, 2026 at 04:17 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `safenest`
--

-- --------------------------------------------------------

--
-- Table structure for table `threat_events`
--

CREATE TABLE `threat_events` (
  `id` int(11) NOT NULL,
  `child_id` varchar(50) NOT NULL,
  `threat_type` varchar(100) NOT NULL,
  `severity` varchar(20) NOT NULL,
  `timestamp` datetime DEFAULT current_timestamp(),
  `context` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'open'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `threat_events`
--

INSERT INTO `threat_events` (`id`, `child_id`, `threat_type`, `severity`, `timestamp`, `context`, `status`) VALUES
(1, 'child1', 'cyberbullying', 'high', '2026-02-25 11:38:22', 'Detected abusive message in chat', 'acknowledged'),
(2, 'child1', 'cyberbullying', 'high', '2026-02-25 12:31:25', 'Detected abusive message in chat', 'open');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('parent','child','admin') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`) VALUES
(1, 'parent1', 'hashed_password_here', 'parent'),
(2, 'child1', 'hashed_password_here', 'child');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `threat_events`
--
ALTER TABLE `threat_events`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `threat_events`
--
ALTER TABLE `threat_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
ALTER TABLE users ADD consent_given TINYINT(1) DEFAULT 0;
