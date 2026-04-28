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
-- Database: `toner_item`
--

-- --------------------------------------------------------

--
-- Table structure for table `toner`
--

CREATE TABLE `toner` (
  `KodProduk` varchar(10) NOT NULL,
  `ProdukInfo` varchar(100) DEFAULT NULL,
  `Harga` decimal(10,2) DEFAULT NULL,
  `Jumlah` int(11) DEFAULT NULL,
  `UnitMasuk` int(11) NOT NULL DEFAULT 0,
  `UnitKeluar` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `toner`
--

INSERT INTO `toner` (`KodProduk`, `ProdukInfo`, `Harga`, `Jumlah`, `UnitMasuk`, `UnitKeluar`) VALUES
('C6036A', 'HP DesignJet Bright White Inkjet Paper (36inX150ft)', 5.00, 3, 2, 1),
('C7977A', 'HPE LTO-7 DATA CARTRIDGE ULTRIUM RW 15 TB', 300.00, 3, 0, 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `toner`
--
ALTER TABLE `toner`
  ADD PRIMARY KEY (`KodProduk`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
