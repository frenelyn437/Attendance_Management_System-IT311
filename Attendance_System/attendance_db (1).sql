CREATE TABLE `attendance` ( 
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_code` varchar(50) DEFAULT NULL,
  `year_level` int(11) DEFAULT NULL,
  `section` varchar(10) DEFAULT NULL,
  `attendance_date` date DEFAULT NULL,
  `attendance_time` time DEFAULT NULL, -- Added column
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `attendance_details` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `attendance_id` int(11) DEFAULT NULL,
  `student_id` varchar(100) DEFAULT NULL,
  `status` enum('Present','Absent','Late','-') DEFAULT '-',
  PRIMARY KEY (`id`),
  KEY `attendance_id` (`attendance_id`),
  CONSTRAINT `attendance_details_ibfk_1` FOREIGN KEY (`attendance_id`) REFERENCES `attendance` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `id_number` varchar(50) NOT NULL,
  `year_level` varchar(10) NOT NULL,
  `section` varchar(10) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` int(10) NOT NULL DEFAULT 0,
  `status` varchar(50) NOT NULL DEFAULT 'active',
  `profile_image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


INSERT INTO `users` (`id`, `first_name`, `last_name`, `id_number`, `year_level`, `section`, `password`, `role`, `status`, `profile_image`, `created_at`) VALUES
(10, 'System', 'Admin', 'admin', '0', '0', '$2y$10$nIv1L9mwsykpY7FvjU7vQeUnfCZeiQLnqHT0uCHeVtYcj7RLiYea.', 2, 'active', 'logo.png', '2025-08-11 09:53:53');

ALTER TABLE `student_attendance_submissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_submission` (`student_id`,`course_code`,`attendance_date`,`attendance_time`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `verified_by` (`verified_by`),
  ADD KEY `attendance_lookup` (`course_code`,`attendance_date`,`year_level`,`section`);