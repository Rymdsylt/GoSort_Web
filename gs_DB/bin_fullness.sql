CREATE TABLE IF NOT EXISTS `bin_fullness` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `device_id` int(11) NOT NULL,
  `bin_name` varchar(50) NOT NULL,
  `distance` int(11) NOT NULL,
  `timestamp` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `device_bin` (`device_id`, `bin_name`),
  FOREIGN KEY (`device_id`) REFERENCES `sorters`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;