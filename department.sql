-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 28, 2026 at 08:20 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `department`
--

-- --------------------------------------------------------

--
-- Table structure for table `department_monthly_log`
--

CREATE TABLE `department_monthly_log` (
  `id` int(11) NOT NULL,
  `cawangan` varchar(255) NOT NULL,
  `kod_produk` varchar(100) NOT NULL,
  `produk_info` text NOT NULL,
  `unit_masuk` int(11) NOT NULL DEFAULT 0,
  `log_year` int(11) NOT NULL,
  `log_month` int(11) NOT NULL,
  `log_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `department_monthly_log`
--

INSERT INTO `department_monthly_log` (`id`, `cawangan`, `kod_produk`, `produk_info`, `unit_masuk`, `log_year`, `log_month`, `log_date`, `created_at`) VALUES
(1, 'SUPT & VAL', 'C7977A', 'HPE LTO-7 DATA CARTRIDGE ULTRIUM RW 15 TB', 1, 2026, 4, '2026-04-01', '2026-04-22 00:54:52'),
(2, 'ADMIN & ENF', 'C6036A', 'HP DesignJet Bright White Inkjet Paper (36inX150ft)', 1, 2026, 4, '2026-04-01', '2026-04-23 01:15:37'),
(3, 'CMS, SRVY COMP, SS', 'B0232F', 'Ribbon Snug-Cart for PR2 Printer Olivetti', 1, 2026, 4, '2026-04-01', '2026-04-23 02:02:15'),
(4, 'CMS, SRVY COMP, SS', 'B0232F', 'Ribbon Snug-Cart for PR2 Printer Olivetti', 1, 2026, 4, '2026-04-01', '2026-04-23 03:40:44'),
(5, 'CMS, SRVY COMP, SS', 'B0232F', 'Ribbon Snug-Cart for PR2 Printer Olivetti', 1, 2026, 4, '2026-04-01', '2026-04-23 03:53:46'),
(6, 'ADMIN & ENF', 'B0232F', 'Ribbon Snug-Cart for PR2 Printer Olivetti', 2, 2026, 6, '2026-06-01', '2026-04-23 03:54:10'),
(7, 'ADMIN & ENF', 'C6036A', 'HP DesignJet Bright White Inkjet Paper (36inX150ft)', 2, 2026, 3, '2026-03-01', '2026-04-23 06:35:15'),
(8, 'ADMIN & ENF', 'C6036A', 'HP DesignJet Bright White Inkjet Paper (36inX150ft)', 2, 2026, 4, '2026-04-01', '2026-04-27 01:44:24'),
(9, 'ADMIN & ENF', 'C6036A', 'HP DesignJet Bright White Inkjet Paper (36inX150ft)', 1, 2026, 3, '2026-03-01', '2026-04-27 01:44:38'),
(10, 'CMS, SVY COMP, SVI, PLAN, VAL', 'C6036A', 'HP DesignJet Bright White Inkjet Paper (36inX150ft)', 1, 2026, 4, '2026-04-27', '2026-04-27 06:09:43');

-- --------------------------------------------------------

--
-- Table structure for table `department_stock`
--

CREATE TABLE `department_stock` (
  `id` int(11) NOT NULL,
  `cawangan` varchar(255) NOT NULL,
  `unit_masuk` int(11) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `department_stock`
--

INSERT INTO `department_stock` (`id`, `cawangan`, `unit_masuk`, `updated_at`) VALUES
(1, 'CMS, SVY COMP, SVI, PLAN, VAL', 0, '2026-04-21 07:34:43'),
(2, 'REV & LAND TITLE', 0, '2026-04-21 07:34:43'),
(3, 'CC', 0, '2026-04-21 07:34:43'),
(4, 'SVY,REG,ENF', 0, '2026-04-21 07:34:43'),
(5, 'VAL & COMP.', 0, '2026-04-21 07:34:43'),
(6, 'REG,OSC,LB', 0, '2026-04-21 07:34:43'),
(7, 'REV/REG', 0, '2026-04-21 07:34:43'),
(8, 'LB & REG', 0, '2026-04-21 07:34:43'),
(9, 'ADMIN & ENF', 0, '2026-04-21 07:34:43'),
(10, 'SUPT & VAL', 2, '2026-04-21 07:34:56'),
(11, 'STENO & CC', 0, '2026-04-21 07:34:43'),
(12, 'CC,SS,LB,VAL,PLAN', 0, '2026-04-21 07:34:43'),
(13, 'CMS, SRVY COMP, SS', 0, '2026-04-21 07:34:43'),
(14, 'CMS, PLANNING & VALUATION', 0, '2026-04-21 07:34:43'),
(15, 'CMS', 0, '2026-04-21 07:34:43');

-- --------------------------------------------------------

--
-- Table structure for table `department_stock_item`
--

CREATE TABLE `department_stock_item` (
  `id` int(11) NOT NULL,
  `cawangan` varchar(255) NOT NULL,
  `kod_produk` varchar(100) NOT NULL,
  `produk_info` text NOT NULL,
  `unit_masuk_total` int(11) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `department_stock_item`
--

INSERT INTO `department_stock_item` (`id`, `cawangan`, `kod_produk`, `produk_info`, `unit_masuk_total`, `updated_at`) VALUES
(8, 'ADMIN & ENF', 'C6036A', 'HP DesignJet Bright White Inkjet Paper (36inX150ft)', 5, '2026-04-27 01:44:38'),
(11, 'CMS, SVY COMP, SVI, PLAN, VAL', 'C6036A', 'HP DesignJet Bright White Inkjet Paper (36inX150ft)', 1, '2026-04-27 06:09:43');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `department_monthly_log`
--
ALTER TABLE `department_monthly_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cawangan` (`cawangan`),
  ADD KEY `idx_year_month` (`log_year`,`log_month`),
  ADD KEY `idx_log_date` (`log_date`);

--
-- Indexes for table `department_stock`
--
ALTER TABLE `department_stock`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cawangan` (`cawangan`);

--
-- Indexes for table `department_stock_item`
--
ALTER TABLE `department_stock_item`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cawangan_produk` (`cawangan`,`kod_produk`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `department_monthly_log`
--
ALTER TABLE `department_monthly_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `department_stock`
--
ALTER TABLE `department_stock`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=76;

--
-- AUTO_INCREMENT for table `department_stock_item`
--
ALTER TABLE `department_stock_item`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
