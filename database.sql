CREATE DATABASE IF NOT EXISTS `pergudangan`;
USE `pergudangan`;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('Admin', 'Petugas', 'Pegawai') NOT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `category_name` VARCHAR(100) NOT NULL UNIQUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT NOT NULL,
  `nama_barang` VARCHAR(100) NOT NULL,
  `kode_barang` VARCHAR(50) NOT NULL UNIQUE,
  `deskripsi` TEXT DEFAULT NULL,
  `stok` INT NOT NULL DEFAULT 0,
  `foto` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `transactions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `item_id` INT NOT NULL,
  `tanggal` DATE NOT NULL,
  `qty` INT NOT NULL,
  `tipe` ENUM('Masuk', 'Keluar') NOT NULL,
  `keterangan` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed default users (password: 'password')
INSERT INTO `users` (`name`, `username`, `password`, `role`, `phone`)
VALUES
('Administrator', 'admin', '$2y$12$V4KYkpKzmPj6ygJmecYzjOhxlmyi9tIt.SmBUyge5d2xu2VEKA5oe', 'Admin', '08123456789'),
('Petugas Gudang', 'petugas', '$2y$12$V4KYkpKzmPj6ygJmecYzjOhxlmyi9tIt.SmBUyge5d2xu2VEKA5oe', 'Petugas', '08123456780'),
('Pegawai Staf', 'pegawai', '$2y$12$V4KYkpKzmPj6ygJmecYzjOhxlmyi9tIt.SmBUyge5d2xu2VEKA5oe', 'Pegawai', '08123456781')
ON DUPLICATE KEY UPDATE `id`=`id`;
