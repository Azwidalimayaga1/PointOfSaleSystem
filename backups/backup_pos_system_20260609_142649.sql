-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: pos_system
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `activity_log`
--

DROP TABLE IF EXISTS `activity_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `action` varchar(50) NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_log`
--

LOCK TABLES `activity_log` WRITE;
/*!40000 ALTER TABLE `activity_log` DISABLE KEYS */;
INSERT INTO `activity_log` VALUES (1,1,'admin','delete_product','Deleted product: Tea 100pk','2026-05-21 14:07:39'),(2,5,'azwdali','login','Logged in from ::1','2026-05-21 14:21:45'),(3,5,'azwdali','login','Logged in from ::1','2026-05-21 14:22:13'),(4,1,'admin','login','Logged in from ::1','2026-05-21 18:08:51'),(5,5,'azwdali','login','Logged in from ::1','2026-05-21 18:10:33'),(6,1,'admin','login','Logged in from ::1','2026-05-21 18:11:01'),(7,1,'admin','login','Logged in from ::1','2026-05-21 18:23:25'),(8,1,'admin','add_product','Added product: Azwidali Manyaga','2026-05-21 18:32:22'),(9,1,'admin','login','Logged in from ::1','2026-05-21 18:45:08'),(10,5,'azwdali','login','Logged in from ::1','2026-05-21 20:18:47'),(11,5,'azwdali','login','Logged in from ::1','2026-05-21 20:20:21'),(12,5,'azwdali','login','Logged in from ::1','2026-05-23 11:22:51'),(13,1,'admin','login','Logged in from ::1','2026-05-23 11:36:56'),(14,1,'admin','login','Logged in from ::1','2026-05-23 12:18:51'),(15,1,'admin','delete_product','Deleted product: Azwidali Manyaga','2026-05-23 12:21:13'),(16,1,'admin','login','Logged in from ::1','2026-05-23 12:38:11'),(17,1,'admin','login','Logged in from ::1','2026-05-23 12:38:20'),(18,1,'admin','login','Logged in from ::1','2026-05-23 12:39:11'),(19,1,'admin','login','Logged in from ::1','2026-05-23 16:39:44'),(20,1,'admin','login','Logged in from ::1','2026-05-23 17:09:29'),(21,1,'admin','login','Logged in from ::1','2026-05-26 09:45:47'),(22,1,'admin','login','Logged in from ::1','2026-05-26 10:10:03'),(23,1,'admin','login','Logged in from ::1','2026-05-26 11:27:39'),(24,1,'admin','login','Logged in from ::1','2026-05-26 11:28:03'),(25,1,'admin','login','Logged in from ::1','2026-05-26 13:01:40'),(26,1,'admin','login','Logged in from ::1','2026-05-26 13:01:50'),(27,1,'admin','login','Logged in from ::1','2026-05-26 13:07:15'),(28,1,'admin','login','Logged in from ::1','2026-05-26 13:07:50'),(29,1,'admin','login','Logged in from ::1','2026-05-26 13:10:33'),(30,10,'wizzy','login','Logged in from ::1','2026-05-26 13:11:05'),(31,1,'admin','login','Logged in from ::1','2026-05-26 19:01:24'),(32,1,'admin','login','Logged in from ::1','2026-05-26 19:07:25'),(33,1,'admin','login','Logged in from ::1','2026-05-27 07:42:01'),(34,1,'admin','login','Logged in from ::1','2026-05-28 21:19:41'),(35,1,'admin','login','Logged in from ::1','2026-05-28 21:20:10'),(36,1,'admin','login','Logged in from ::1','2026-05-28 21:20:20'),(37,1,'admin','login','Logged in from ::1','2026-05-28 21:25:48'),(38,1,'admin','login','Logged in from ::1','2026-05-28 21:29:04'),(39,1,'admin','login','Logged in from ::1','2026-05-28 21:29:23'),(40,1,'admin','login','Logged in from ::1','2026-05-31 16:07:22'),(41,1,'admin','login','Logged in from ::1','2026-05-31 16:14:18'),(42,1,'admin','login','Logged in from ::1','2026-05-31 16:15:17'),(43,1,'admin','login','Logged in from ::1','2026-05-31 16:20:11'),(44,1,'admin','login','Logged in from ::1','2026-05-31 16:23:52'),(45,1,'admin','login','Logged in from ::1','2026-06-04 17:35:56'),(46,1,'admin','login','Logged in from ::1','2026-06-04 18:00:33'),(47,1,'admin','login','Logged in from ::1','2026-06-09 10:35:33');
/*!40000 ALTER TABLE `activity_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `user_name` varchar(255) NOT NULL,
  `user_role` varchar(50) NOT NULL,
  `store_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_action` (`action`),
  KEY `idx_audit_entity` (`entity_type`,`entity_id`),
  KEY `idx_audit_store` (`store_id`),
  KEY `idx_audit_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (1,1,'Administrator','admin',2,'test_action','test',42,'Test log entry','0.0.0.0','2026-06-09 14:14:04'),(2,1,'Administrator','admin',2,'test_action','test',42,'Test log entry','0.0.0.0','2026-06-09 14:14:30'),(3,1,'Administrator','admin',2,'test_action','test',42,'Test log entry','0.0.0.0','2026-06-09 14:15:55');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `backups`
--

DROP TABLE IF EXISTS `backups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) NOT NULL,
  `file_size` bigint(20) DEFAULT 0,
  `status` varchar(50) DEFAULT 'completed',
  `type` varchar(50) DEFAULT 'manual',
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `backups`
--

LOCK TABLES `backups` WRITE;
/*!40000 ALTER TABLE `backups` DISABLE KEYS */;
INSERT INTO `backups` VALUES (1,'backup_pos_system_20260609_141430.sql',102,'failed','manual',NULL,'2026-06-09 14:14:30'),(2,'backup_pos_system_20260609_141555.sql',45998,'completed','manual',NULL,'2026-06-09 14:15:58');
/*!40000 ALTER TABLE `backups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customers`
--

DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `total_spent` decimal(10,2) DEFAULT 0.00,
  `visit_count` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `store_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customers`
--

LOCK TABLES `customers` WRITE;
/*!40000 ALTER TABLE `customers` DISABLE KEYS */;
/*!40000 ALTER TABLE `customers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_verification_tokens`
--

DROP TABLE IF EXISTS `email_verification_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email_verification_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `email_verification_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_verification_tokens`
--

LOCK TABLES `email_verification_tokens` WRITE;
/*!40000 ALTER TABLE `email_verification_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `email_verification_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `held_sales`
--

DROP TABLE IF EXISTS `held_sales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `held_sales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cashier_id` int(11) NOT NULL,
  `cashier_name` varchar(255) NOT NULL,
  `items` text NOT NULL,
  `subtotal` decimal(10,2) DEFAULT 0.00,
  `discount` int(11) DEFAULT 0,
  `tax` decimal(10,2) DEFAULT 0.00,
  `total` decimal(10,2) DEFAULT 0.00,
  `created_at` datetime DEFAULT current_timestamp(),
  `store_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cashier_id` (`cashier_id`),
  CONSTRAINT `held_sales_ibfk_1` FOREIGN KEY (`cashier_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `held_sales`
--

LOCK TABLES `held_sales` WRITE;
/*!40000 ALTER TABLE `held_sales` DISABLE KEYS */;
/*!40000 ALTER TABLE `held_sales` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `login_attempts`
--

DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `attempted_at` datetime DEFAULT current_timestamp(),
  `success` tinyint(4) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_login_attempts_ip` (`ip_address`,`attempted_at`),
  KEY `idx_login_attempts_email` (`email`,`attempted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_attempts`
--

LOCK TABLES `login_attempts` WRITE;
/*!40000 ALTER TABLE `login_attempts` DISABLE KEYS */;
INSERT INTO `login_attempts` VALUES (1,'::1','admin@pos.local','2026-06-09 10:35:33',1);
/*!40000 ALTER TABLE `login_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `messages`
--

DROP TABLE IF EXISTS `messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_id` int(11) NOT NULL,
  `sender_name` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(4) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `store_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sender_id` (`sender_id`),
  CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `messages`
--

LOCK TABLES `messages` WRITE;
/*!40000 ALTER TABLE `messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `password_reset_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `barcode` varchar(255) DEFAULT NULL,
  `category` varchar(255) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `cost_price` decimal(10,2) DEFAULT 0.00,
  `stock_quantity` int(11) DEFAULT 0,
  `low_stock_threshold` int(11) DEFAULT 10,
  `supplier` varchar(255) DEFAULT NULL,
  `image` text DEFAULT NULL,
  `status` varchar(50) DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `store_id` int(11) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `barcode` (`barcode`)
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (1,'Bread','BRD','Food',22.00,10.00,64,10,NULL,'assets/products/1.jpg','active','2026-05-16 11:00:11','2026-06-09 13:50:50',2,NULL),(2,'Bread','1001','Food',15.00,8.00,25,10,'','assets/products/2.jpg','active','2026-05-16 11:03:18','2026-06-09 13:50:50',2,NULL),(3,'Rice 2kg','1002','Food',45.00,30.00,18,5,'','assets/products/3.jpg','active','2026-05-16 11:03:18','2026-06-09 13:50:50',2,NULL),(4,'Cooking Oil 750ml','1003','Food',35.00,25.00,12,5,'','assets/products/4.jpg','active','2026-05-16 11:03:18','2026-06-09 13:50:50',2,NULL),(5,'Sugar 1kg','1004','Food',25.00,18.00,25,5,'','assets/products/5.jpg','active','2026-05-16 11:03:18','2026-06-09 13:50:50',2,NULL),(6,'Flour 1kg','1005','Food',18.00,12.00,17,5,'','assets/products/6.jpg','active','2026-05-16 11:03:18','2026-06-09 13:50:50',2,NULL),(7,'Pasta 500g','1006','Food',12.00,8.00,18,5,'','assets/products/7.jpg','active','2026-05-16 11:03:18','2026-06-09 13:50:50',2,NULL),(8,'Tomato Sauce','1007','Food',22.00,15.00,12,5,'','assets/products/8.jpg','active','2026-05-16 11:03:18','2026-06-09 13:50:50',2,NULL),(9,'Baked Beans','1008','Food',14.00,9.00,100,5,NULL,'assets/products/9.jpg','active','2026-05-16 11:03:18','2026-06-09 13:50:50',2,'2026-06-06'),(10,'Canned Tuna','1009','Food',18.00,12.00,11,5,'','assets/products/10.jpg','active','2026-05-16 11:03:18','2026-06-09 13:50:50',2,NULL),(11,'Salt 500g','1010','Food',8.00,5.00,20,5,'','assets/products/11.jpg','active','2026-05-16 11:03:18','2026-06-09 13:50:50',2,NULL),(12,'Jam Strawberry','1011','Food',24.00,16.00,10,4,'','assets/products/12.jpg','active','2026-05-16 11:03:18','2026-06-09 13:50:50',2,NULL),(13,'Peanut Butter','1012','Food',28.00,19.00,8,3,'','assets/products/13.jpg','active','2026-05-16 11:03:18','2026-06-09 13:50:50',2,NULL),(14,'Maize Meal 2kg','1013','Food',30.00,20.00,9,5,'','assets/products/14.jpg','active','2026-05-16 11:03:18','2026-06-09 13:50:50',2,NULL),(15,'Coke 330ml','2001','Beverages',10.00,6.00,48,12,'','assets/products/15.jpg','active','2026-05-16 11:03:18','2026-06-09 13:50:50',2,NULL),(16,'Sprite 330ml','2002','Beverages',10.00,6.00,36,12,'','assets/products/16.jpg','active','2026-05-16 11:03:18','2026-06-09 13:50:50',2,NULL),(17,'Water 500ml','2003','Beverages',7.00,4.00,60,12,'','assets/products/17.jpg','active','2026-05-16 11:03:18','2026-06-09 13:50:50',2,NULL),(18,'Orange Juice 1L','2004','Beverages',22.00,15.00,9,4,'','assets/products/18.jpg','active','2026-05-16 11:03:18','2026-06-09 13:50:50',2,NULL),(19,'Milk 1L','2005','Beverages',18.00,12.00,10,4,'','assets/products/19.jpg','active','2026-05-16 11:03:18','2026-06-09 13:50:50',2,NULL),(20,'Coffee 250g','2006','Beverages',45.00,30.00,8,3,'','assets/products/20.jpg','active','2026-05-16 11:03:18','2026-06-09 13:50:50',2,NULL),(22,'Potato Chips','3001','Snacks',12.00,7.00,27,10,'','assets/products/22.jpg','active','2026-05-16 11:03:18','2026-06-09 13:50:50',2,NULL),(23,'Chocolate Bar','3002','Snacks',15.00,9.00,25,8,'','assets/products/23.jpg','active','2026-05-16 11:03:18','2026-06-09 13:50:50',2,NULL),(24,'Biscuits','3003','Snacks',14.00,9.00,15,6,'','assets/products/24.jpg','active','2026-05-16 11:03:18','2026-06-09 13:50:50',2,NULL),(25,'Lollipops (jar)','3004','Snacks',25.00,15.00,0,3,'','assets/products/25.jpg','active','2026-05-16 11:03:18','2026-06-09 13:50:50',2,NULL),(26,'Nuts Mixed 200g','3005','Snacks',20.00,13.00,7,3,'','assets/products/26.jpg','active','2026-05-16 11:03:18','2026-06-09 13:50:50',2,NULL),(27,'Dish Soap','4001','Household',16.00,10.00,13,5,'','assets/products/27.jpg','active','2026-05-16 11:03:18','2026-06-09 13:50:50',2,NULL),(28,'Laundry Detergent 500g','4002','Household',32.00,22.00,12,5,'','assets/products/28.jpg','active','2026-05-16 11:03:18','2026-06-09 13:50:50',2,NULL),(29,'Multi-Purpose Cleaner','4003','Household',20.00,14.00,10,4,'','assets/products/29.jpg','active','2026-05-16 11:03:18','2026-06-09 13:50:50',2,NULL),(30,'Sponges (5pk)','4004','Household',12.00,7.00,16,5,'','assets/products/30.jpg','active','2026-05-16 11:03:18','2026-06-09 13:50:50',2,NULL),(31,'Garbage Bags 20pk','4005','Household',18.00,12.00,14,5,'','assets/products/31.jpg','active','2026-05-16 11:03:18','2026-06-09 13:50:50',2,NULL),(32,'Toilet Paper 9roll','5001','Toiletries',45.00,32.00,20,5,'','assets/products/32.jpg','active','2026-05-16 11:03:18','2026-06-09 13:50:50',2,NULL),(33,'Soap Bar','5002','Toiletries',8.00,5.00,39,10,'','assets/products/33.jpg','active','2026-05-16 11:03:18','2026-06-09 13:50:50',2,NULL),(34,'Shampoo 250ml','5003','Toiletries',28.00,20.00,10,4,'','assets/products/34.jpg','active','2026-05-16 11:03:18','2026-06-09 13:50:50',2,NULL),(35,'Toothpaste','5004','Toiletries',18.00,12.00,15,5,'','assets/products/35.jpg','active','2026-05-16 11:03:18','2026-06-09 13:50:50',2,NULL),(36,'Deodorant','5005','Toiletries',25.00,18.00,8,3,'','assets/products/36.jpg','active','2026-05-16 11:03:18','2026-06-09 13:50:50',2,NULL),(37,'Tissues (box)','5006','Toiletries',15.00,10.00,12,4,'','assets/products/37.jpg','active','2026-05-16 11:03:18','2026-06-09 13:50:50',2,NULL),(38,'Matches (box)','6001','Other',5.00,3.00,25,10,'','assets/products/38.jpg','active','2026-05-16 11:03:18','2026-06-09 13:50:50',2,NULL),(40,'Light Bulb','6003','Other',15.00,9.00,10,4,'','assets/products/40.jpg','active','2026-05-16 11:03:18','2026-06-09 13:50:50',2,NULL),(41,'Notebook A5','6004','Other',12.00,7.00,13,5,'','assets/products/41.jpg','active','2026-05-16 11:03:18','2026-06-09 13:50:50',2,NULL),(42,'Pens (10pk)','6005','Other',18.00,11.00,10,4,'','assets/products/42.jpg','active','2026-05-16 11:03:18','2026-06-09 13:50:50',2,NULL);
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rate_limits`
--

DROP TABLE IF EXISTS `rate_limits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rate_limits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `identifier` varchar(255) NOT NULL,
  `action` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_rate_limits_lookup` (`identifier`,`action`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rate_limits`
--

LOCK TABLES `rate_limits` WRITE;
/*!40000 ALTER TABLE `rate_limits` DISABLE KEYS */;
/*!40000 ALTER TABLE `rate_limits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `return_requests`
--

DROP TABLE IF EXISTS `return_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `return_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sale_id` int(11) NOT NULL,
  `receipt_number` varchar(255) NOT NULL,
  `cashier_id` int(11) NOT NULL,
  `cashier_name` varchar(255) NOT NULL,
  `items` text NOT NULL,
  `reason` varchar(50) NOT NULL CHECK (`reason` in ('return','damage')),
  `resolution` varchar(50) NOT NULL CHECK (`resolution` in ('refund','exchange')),
  `refund_amount` decimal(10,2) DEFAULT 0.00,
  `exchange_product_id` int(11) DEFAULT NULL,
  `exchange_product_name` varchar(255) DEFAULT NULL,
  `exchange_qty` int(11) DEFAULT 0,
  `status` varchar(50) DEFAULT 'pending' CHECK (`status` in ('pending','approved','rejected')),
  `admin_id` int(11) DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `store_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sale_id` (`sale_id`),
  KEY `cashier_id` (`cashier_id`),
  KEY `admin_id` (`admin_id`),
  CONSTRAINT `return_requests_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`),
  CONSTRAINT `return_requests_ibfk_2` FOREIGN KEY (`cashier_id`) REFERENCES `users` (`id`),
  CONSTRAINT `return_requests_ibfk_3` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `return_requests`
--

LOCK TABLES `return_requests` WRITE;
/*!40000 ALTER TABLE `return_requests` DISABLE KEYS */;
INSERT INTO `return_requests` VALUES (1,22,'RCP-20260521-0022',1,'Administrator','[{\"id\":39,\"product_id\":1,\"product_name\":\"Bread\",\"qty\":1,\"price\":20,\"total\":20}]','return','refund',20.00,NULL,NULL,0,'approved',1,'','2026-05-21 13:27:41','2026-06-04 18:50:26',1);
/*!40000 ALTER TABLE `return_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sale_items`
--

DROP TABLE IF EXISTS `sale_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sale_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sale_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `cost_price` decimal(10,2) DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sale_id` (`sale_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`),
  CONSTRAINT `sale_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sale_items`
--

LOCK TABLES `sale_items` WRITE;
/*!40000 ALTER TABLE `sale_items` DISABLE KEYS */;
INSERT INTO `sale_items` VALUES (1,1,1,'Bread',2,20.00,10.00,40.00),(2,2,1,'Bread',1,20.00,10.00,20.00),(3,3,6,'Flour 1kg',3,18.00,12.00,54.00),(4,3,9,'Baked Beans',2,14.00,9.00,28.00),(5,3,25,'Lollipops (jar)',3,25.00,15.00,75.00),(6,3,33,'Soap Bar',1,8.00,5.00,8.00),(7,3,30,'Sponges (5pk)',1,12.00,7.00,12.00),(8,3,3,'Rice 2kg',1,45.00,30.00,45.00),(9,3,22,'Potato Chips',1,12.00,7.00,12.00),(10,4,9,'Baked Beans',6,14.00,9.00,84.00),(11,4,24,'Biscuits',3,14.00,9.00,42.00),(12,4,1,'Bread',1,20.00,10.00,20.00),(13,4,4,'Cooking Oil 750ml',2,35.00,25.00,70.00),(14,4,14,'Maize Meal 2kg',2,30.00,20.00,60.00),(15,5,9,'Baked Beans',4,14.00,9.00,56.00),(16,6,2,'Bread',2,15.00,8.00,30.00),(17,7,9,'Baked Beans',1,14.00,9.00,14.00),(18,8,1,'Bread',1,20.00,10.00,20.00),(19,9,24,'Biscuits',1,14.00,9.00,14.00),(20,10,30,'Sponges (5pk)',1,12.00,7.00,12.00),(21,10,22,'Potato Chips',1,12.00,7.00,12.00),(22,10,3,'Rice 2kg',1,45.00,30.00,45.00),(23,10,18,'Orange Juice 1L',1,22.00,15.00,22.00),(24,10,26,'Nuts Mixed 200g',1,20.00,13.00,20.00),(25,10,14,'Maize Meal 2kg',1,30.00,20.00,30.00),(26,11,41,'Notebook A5',1,12.00,7.00,12.00),(27,12,2,'Bread',2,15.00,8.00,30.00),(28,13,24,'Biscuits',1,14.00,9.00,14.00),(29,14,9,'Baked Beans',1,14.00,9.00,14.00),(30,15,10,'Canned Tuna',1,18.00,12.00,18.00),(31,16,10,'Canned Tuna',1,18.00,12.00,18.00),(32,16,27,'Dish Soap',1,16.00,10.00,16.00),(33,16,25,'Lollipops (jar)',1,25.00,15.00,25.00),(34,17,27,'Dish Soap',1,16.00,10.00,16.00),(35,18,10,'Canned Tuna',1,18.00,12.00,18.00),(36,19,2,'Bread',1,15.00,8.00,15.00),(37,20,4,'Cooking Oil 750ml',1,35.00,25.00,35.00),(38,21,22,'Potato Chips',1,12.00,7.00,12.00),(39,22,1,'Bread',1,20.00,10.00,20.00),(40,23,1,'Bread',1,20.00,10.00,20.00),(41,24,25,'Lollipops (jar)',1,25.00,15.00,25.00),(42,24,41,'Notebook A5',1,12.00,7.00,12.00),(43,25,1,'Bread',1,22.00,10.00,22.00),(44,26,10,'Canned Tuna',1,18.00,12.00,18.00),(45,27,1,'Bread',1,22.00,10.00,22.00);
/*!40000 ALTER TABLE `sale_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales`
--

DROP TABLE IF EXISTS `sales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `receipt_number` varchar(255) NOT NULL,
  `cashier_id` int(11) NOT NULL,
  `cashier_name` varchar(255) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tax` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tax_rate` decimal(10,2) NOT NULL DEFAULT 15.00,
  `discount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_type` varchar(50) DEFAULT 'percentage',
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` varchar(50) NOT NULL,
  `cash_amount` decimal(10,2) DEFAULT 0.00,
  `card_amount` decimal(10,2) DEFAULT 0.00,
  `change_amount` decimal(10,2) DEFAULT 0.00,
  `status` varchar(50) DEFAULT 'completed',
  `created_at` datetime DEFAULT current_timestamp(),
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `customer_email` varchar(255) DEFAULT NULL,
  `customer_phone` varchar(50) DEFAULT NULL,
  `store_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `receipt_number` (`receipt_number`),
  KEY `cashier_id` (`cashier_id`),
  CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`cashier_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales`
--

LOCK TABLES `sales` WRITE;
/*!40000 ALTER TABLE `sales` DISABLE KEYS */;
INSERT INTO `sales` VALUES (1,'RCP-20260516-0001',1,'John Admin',40.00,6.00,15.00,0.00,'percentage',46.00,'cash',50.00,0.00,4.00,'completed','2026-05-16 11:00:11',NULL,NULL,NULL,NULL,2),(2,'RCP-20260516-0002',2,'Sarah Cashier',20.00,3.00,15.00,0.00,'percentage',23.00,'card',0.00,23.00,0.00,'completed','2026-05-16 11:00:11',NULL,NULL,NULL,NULL,2),(3,'RCP-20260516-0003',5,'Azwidali',234.00,35.10,15.00,0.00,'percentage',269.10,'cash',300.00,0.00,30.90,'completed','2026-05-16 11:05:47',NULL,NULL,NULL,NULL,2),(4,'RCP-20260516-0004',5,'Azwidali',276.00,41.40,15.00,0.00,'percentage',317.40,'cash',400.00,0.00,82.60,'completed','2026-05-16 11:06:35',NULL,NULL,NULL,NULL,2),(5,'RCP-20260516-0005',6,'fulufhelo',56.00,8.40,15.00,0.00,'percentage',64.40,'card',0.00,64.40,0.00,'completed','2026-05-16 11:14:00',NULL,NULL,NULL,NULL,2),(6,'RCP-20260516-0006',1,'Admin',30.00,4.50,15.00,0.00,'percentage',34.50,'cash',50.00,0.00,20.00,'completed','2026-05-16 14:06:09',NULL,NULL,NULL,NULL,2),(7,'RCP-20260516-0007',2,'Cashier User',14.00,2.10,15.00,0.00,'percentage',16.10,'cash',20.00,0.00,3.90,'completed','2026-05-16 16:02:27',NULL,NULL,NULL,NULL,2),(8,'RCP-20260516-0008',2,'Cashier User',20.00,3.00,15.00,0.00,'percentage',23.00,'cash',60.00,0.00,37.00,'completed','2026-05-16 16:02:46',NULL,NULL,NULL,NULL,2),(9,'RCP-20260516-0009',2,'Cashier User',14.00,2.10,15.00,0.00,'percentage',16.10,'cash',50.00,0.00,33.90,'completed','2026-05-16 16:05:29',NULL,NULL,NULL,NULL,2),(10,'RCP-20260516-0010',1,'Administrator',141.00,21.15,15.00,0.00,'percentage',162.15,'cash',200.00,0.00,37.85,'completed','2026-05-16 19:37:54',NULL,NULL,NULL,NULL,2),(11,'RCP-20260521-0011',5,'Azwidali',12.00,1.80,15.00,0.00,'percentage',13.80,'cash',15.00,0.00,1.20,'completed','2026-05-21 12:09:30',NULL,NULL,NULL,NULL,2),(12,'RCP-20260521-0012',5,'Azwidali',30.00,4.50,15.00,0.00,'percentage',34.50,'cash',50.00,0.00,15.50,'completed','2026-05-21 12:09:58',NULL,NULL,NULL,NULL,2),(13,'RCP-20260521-0013',5,'Azwidali',14.00,2.10,15.00,0.00,'percentage',16.10,'cash',60.00,0.00,43.90,'completed','2026-05-21 12:10:17',NULL,NULL,NULL,NULL,2),(14,'RCP-20260521-0014',1,'Administrator',14.00,2.10,15.00,0.00,'percentage',16.10,'cash',23.00,0.00,6.90,'completed','2026-05-21 12:13:56',NULL,NULL,NULL,NULL,2),(15,'RCP-20260521-0015',7,'Self Checkout',18.00,2.70,15.00,0.00,'percentage',20.70,'card',0.00,20.70,0.00,'completed','2026-05-21 12:18:10',NULL,NULL,NULL,NULL,2),(16,'RCP-20260521-0016',7,'Self Checkout',59.00,8.85,15.00,0.00,'percentage',67.85,'card',0.00,67.85,0.00,'completed','2026-05-21 12:23:44',NULL,NULL,NULL,NULL,2),(17,'RCP-20260521-0017',7,'Self Checkout',16.00,2.40,15.00,0.00,'percentage',18.40,'card',0.00,18.40,0.00,'completed','2026-05-21 12:24:38',NULL,NULL,NULL,NULL,2),(18,'RCP-20260521-0018',7,'Self Checkout',18.00,2.70,15.00,0.00,'percentage',20.70,'cash',77.00,0.00,56.30,'completed','2026-05-21 12:32:31',NULL,NULL,NULL,NULL,2),(19,'RCP-20260521-0019',7,'Self Checkout',15.00,2.25,15.00,0.00,'percentage',17.25,'cash',20.00,0.00,2.75,'completed','2026-05-21 12:34:59',NULL,NULL,NULL,NULL,2),(20,'RCP-20260521-0020',7,'Self Checkout',35.00,5.25,15.00,0.00,'percentage',40.25,'cash',199.99,0.00,159.74,'completed','2026-05-21 12:36:04',NULL,NULL,NULL,NULL,2),(21,'RCP-20260521-0021',7,'Self Checkout',12.00,1.80,15.00,0.00,'percentage',13.80,'card',0.00,13.80,0.00,'completed','2026-05-21 12:36:36',NULL,NULL,NULL,NULL,2),(22,'RCP-20260521-0022',7,'Self Checkout',20.00,3.00,15.00,0.00,'percentage',23.00,'cash',60.00,0.00,37.00,'completed','2026-05-21 12:42:58',NULL,NULL,NULL,NULL,2),(23,'RCP-20260521-0023',7,'Self Checkout',20.00,3.00,15.00,0.00,'percentage',23.00,'cash',30.00,0.00,7.00,'completed','2026-05-21 13:24:01',NULL,NULL,NULL,NULL,2),(24,'RCP-20260521-0024',7,'Self Checkout',37.00,5.55,15.00,0.00,'percentage',42.55,'cash',444.00,0.00,401.45,'completed','2026-05-21 18:12:10',NULL,NULL,NULL,NULL,2),(25,'RCP-20260521-0025',7,'Self Checkout',22.00,3.30,15.00,0.00,'percentage',25.30,'cash',79.00,0.00,53.70,'completed','2026-05-21 18:23:06',NULL,NULL,NULL,NULL,2),(26,'RCP-20260523-0026',7,'Self Checkout',18.00,2.70,15.00,0.00,'percentage',20.70,'cash',30.00,0.00,9.30,'completed','2026-05-23 16:41:31',NULL,NULL,NULL,NULL,2),(27,'RCP-20260609-0027',1,'Administrator',22.00,3.30,15.00,0.00,'percentage',25.30,'cash',30.00,0.00,4.70,'completed','2026-06-09 12:37:10',NULL,NULL,NULL,NULL,2);
/*!40000 ALTER TABLE `sales` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `access_token` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `store_id` int(11) DEFAULT NULL,
  `refresh_token` varchar(255) DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `access_token` (`access_token`),
  KEY `idx_sessions_expires` (`expires_at`),
  KEY `idx_sessions_user` (`user_id`),
  CONSTRAINT `sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings` (
  `key` varchar(255) NOT NULL,
  `value` text NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES ('currency','R'),('daily_target','2500'),('receipt_footer','Thank you for your purchase!'),('self_checkout_enabled','1'),('store_address','39 Grant Drive'),('store_contact','+27 72 674 0883'),('store_name','wapanda'),('tax_rate','15');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stock_adjustments`
--

DROP TABLE IF EXISTS `stock_adjustments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock_adjustments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_name` varchar(255) NOT NULL,
  `type` varchar(50) NOT NULL CHECK (`type` in ('sale','purchase','return','adjustment','damage')),
  `quantity` int(11) NOT NULL,
  `previous_stock` int(11) NOT NULL,
  `new_stock` int(11) NOT NULL,
  `reason` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `store_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `stock_adjustments_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `stock_adjustments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=50 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock_adjustments`
--

LOCK TABLES `stock_adjustments` WRITE;
/*!40000 ALTER TABLE `stock_adjustments` DISABLE KEYS */;
INSERT INTO `stock_adjustments` VALUES (1,1,1,'Administrator','sale',2,50,48,'Sale RCP-20260516-0001','2026-05-16 11:00:11',2),(2,1,2,'Cashier User','sale',1,48,47,'Sale RCP-20260516-0002','2026-05-16 11:00:11',2),(3,6,5,'Azwidali','sale',3,20,17,'Sale RCP-20260516-0003','2026-05-16 11:05:47',2),(4,9,5,'Azwidali','sale',2,24,22,'Sale RCP-20260516-0003','2026-05-16 11:05:47',2),(5,25,5,'Azwidali','sale',3,5,2,'Sale RCP-20260516-0003','2026-05-16 11:05:47',2),(6,33,5,'Azwidali','sale',1,40,39,'Sale RCP-20260516-0003','2026-05-16 11:05:47',2),(7,30,5,'Azwidali','sale',1,18,17,'Sale RCP-20260516-0003','2026-05-16 11:05:47',2),(8,3,5,'Azwidali','sale',1,20,19,'Sale RCP-20260516-0003','2026-05-16 11:05:47',2),(9,22,5,'Azwidali','sale',1,30,29,'Sale RCP-20260516-0003','2026-05-16 11:05:47',2),(10,9,5,'Azwidali','sale',6,22,16,'Sale RCP-20260516-0004','2026-05-16 11:06:35',2),(11,24,5,'Azwidali','sale',3,20,17,'Sale RCP-20260516-0004','2026-05-16 11:06:35',2),(12,1,5,'Azwidali','sale',1,47,46,'Sale RCP-20260516-0004','2026-05-16 11:06:35',2),(13,4,5,'Azwidali','sale',2,15,13,'Sale RCP-20260516-0004','2026-05-16 11:06:35',2),(14,14,5,'Azwidali','sale',2,12,10,'Sale RCP-20260516-0004','2026-05-16 11:06:35',2),(15,9,6,'fulufhelo','sale',4,16,12,'Sale RCP-20260516-0005','2026-05-16 11:14:00',2),(16,2,1,'Administrator','sale',2,30,28,'Sale RCP-20260516-0006','2026-05-16 14:06:09',2),(17,9,2,'Cashier User','sale',1,12,11,'Sale RCP-20260516-0007','2026-05-16 16:02:27',2),(18,1,2,'Cashier User','sale',1,46,45,'Sale RCP-20260516-0008','2026-05-16 16:02:46',2),(19,24,2,'Cashier User','sale',1,17,16,'Sale RCP-20260516-0009','2026-05-16 16:05:29',2),(20,30,1,'Administrator','sale',1,17,16,'Sale RCP-20260516-0010','2026-05-16 19:37:54',2),(21,22,1,'Administrator','sale',1,29,28,'Sale RCP-20260516-0010','2026-05-16 19:37:54',2),(22,3,1,'Administrator','sale',1,19,18,'Sale RCP-20260516-0010','2026-05-16 19:37:54',2),(23,18,1,'Administrator','sale',1,10,9,'Sale RCP-20260516-0010','2026-05-16 19:37:54',2),(24,26,1,'Administrator','sale',1,8,7,'Sale RCP-20260516-0010','2026-05-16 19:37:54',2),(25,14,1,'Administrator','sale',1,10,9,'Sale RCP-20260516-0010','2026-05-16 19:37:54',2),(26,41,5,'Azwidali','sale',1,15,14,'Sale RCP-20260521-0011','2026-05-21 12:09:30',2),(27,2,5,'Azwidali','sale',2,28,26,'Sale RCP-20260521-0012','2026-05-21 12:09:58',2),(28,24,5,'Azwidali','sale',1,16,15,'Sale RCP-20260521-0013','2026-05-21 12:10:17',2),(29,9,1,'Administrator','sale',1,11,10,'Sale RCP-20260521-0014','2026-05-21 12:13:56',2),(30,10,7,'Self Checkout','sale',1,15,14,'Self-checkout RCP-20260521-0015','2026-05-21 12:18:10',2),(31,10,7,'Self Checkout','sale',1,14,13,'Self-checkout RCP-20260521-0016','2026-05-21 12:23:44',2),(32,27,7,'Self Checkout','sale',1,15,14,'Self-checkout RCP-20260521-0016','2026-05-21 12:23:44',2),(33,25,7,'Self Checkout','sale',1,2,1,'Self-checkout RCP-20260521-0016','2026-05-21 12:23:44',2),(34,27,7,'Self Checkout','sale',1,14,13,'Self-checkout RCP-20260521-0017','2026-05-21 12:24:38',2),(35,10,7,'Self Checkout','sale',1,13,12,'Self-checkout RCP-20260521-0018','2026-05-21 12:32:31',2),(36,2,7,'Self Checkout','sale',1,26,25,'Self-checkout RCP-20260521-0019','2026-05-21 12:34:59',2),(37,4,7,'Self Checkout','sale',1,13,12,'Self-checkout RCP-20260521-0020','2026-05-21 12:36:04',2),(38,22,7,'Self Checkout','sale',1,28,27,'Self-checkout RCP-20260521-0021','2026-05-21 12:36:36',2),(39,1,7,'Self Checkout','sale',1,45,44,'Self-checkout RCP-20260521-0022','2026-05-21 12:42:58',2),(40,1,7,'Self Checkout','sale',1,44,43,'Self-checkout RCP-20260521-0023','2026-05-21 13:24:01',2),(41,1,1,'Administrator','return',1,43,44,'Return - return','2026-05-21 13:27:41',2),(42,1,1,'Administrator','adjustment',22,44,66,'more stock','2026-05-21 13:40:42',2),(43,9,1,'Administrator','return',10,10,0,'','2026-05-21 13:49:21',2),(44,9,1,'Administrator','purchase',100,0,100,'','2026-05-21 13:49:36',2),(45,25,7,'Self Checkout','sale',1,1,0,'Self-checkout RCP-20260521-0024','2026-05-21 18:12:10',2),(46,41,7,'Self Checkout','sale',1,14,13,'Self-checkout RCP-20260521-0024','2026-05-21 18:12:10',2),(47,1,7,'Self Checkout','sale',1,66,65,'Self-checkout RCP-20260521-0025','2026-05-21 18:23:06',2),(48,10,7,'Self Checkout','sale',1,12,11,'Self-checkout RCP-20260523-0026','2026-05-23 16:41:31',2),(49,1,1,'Administrator','sale',1,65,64,'Sale RCP-20260609-0027','2026-06-09 12:37:10',2);
/*!40000 ALTER TABLE `stock_adjustments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stores`
--

DROP TABLE IF EXISTS `stores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `address` text DEFAULT NULL,
  `contact` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `currency` varchar(10) DEFAULT 'R',
  `tax_rate` decimal(10,2) DEFAULT 15.00,
  `receipt_footer` text DEFAULT NULL,
  `daily_target` decimal(10,2) DEFAULT 5000.00,
  `self_checkout_enabled` tinyint(4) DEFAULT 1,
  `status` varchar(50) DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp(),
  `contact_email` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stores`
--

LOCK TABLES `stores` WRITE;
/*!40000 ALTER TABLE `stores` DISABLE KEYS */;
INSERT INTO `stores` VALUES (1,'My Store','123 Main Street','+27 12 345 6789',NULL,'R',15.00,'Thank you for your purchase!',5000.00,1,'active','2026-06-04 17:57:12',NULL),(2,'wapanda','244 mamakwana street','0826805841','ST10440560@rcconnet.edu.za','R',15.00,'thank you for shoping with us',5000.00,1,'active','2026-06-04 18:02:09',NULL),(3,'fast food','39 Grant Dr','00000000','azwidalimanyaga244@gmail.com','R',15.00,'test mode',5000.00,1,'active','2026-06-09 13:28:51',NULL);
/*!40000 ALTER TABLE `stores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'cashier',
  `status` varchar(50) DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp(),
  `email` varchar(255) DEFAULT NULL,
  `supabase_id` varchar(255) DEFAULT NULL,
  `store_id` int(11) DEFAULT NULL,
  `email_verified_at` datetime DEFAULT NULL,
  `password_changed_at` datetime DEFAULT NULL,
  `failed_login_attempts` int(11) DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin@pos.local','$2y$10$Sg0BUa73gV/RnikDL0TrfODJVT/sUxuG5898LUGpwmnXGFk98ZlWG','Administrator','admin','active','2026-05-16 10:49:40','admin@pos.local','fdd54bcd-d1b4-4a04-9a8f-abf81d4a0857',1,NULL,NULL,0,NULL),(2,'cashier','$2y$10$DRQn1GCdhZKv21hahfmideZbV7WHA56TNivbvVasyKiHxCymwEiEa','Cashier User','cashier','active','2026-05-16 10:49:40',NULL,NULL,1,NULL,NULL,0,NULL),(3,'newcashier','$2y$10$gb7UOX2aP9D2irQN6VZLper0plSQNMZiQue/WlJ5xvqk4GeZAJYIW','New Cashier','cashier','inactive','2026-05-16 10:49:50',NULL,NULL,1,NULL,NULL,0,NULL),(4,'Azwidail','$2y$10$tK.5DZYo9Qd4clRjskwd.uaDjihHsXwLNu8QXPN.APJbS0TL6iXSO','Azwidali','manager','inactive','2026-05-16 10:51:44',NULL,NULL,1,NULL,NULL,0,NULL),(5,'azwdali','$2y$10$.jCEBxuLPrL3TKhsG8OyeexB2zeNwiAl7RToep49G/Ti7AXXvkYSi','Azwidali','cashier','active','2026-05-16 10:56:16',NULL,NULL,1,NULL,NULL,0,NULL),(6,'fulu','$2y$10$elKCn9arKKcRnI0.8qdeze53O3F/MjWCKo6J2eyIR8S1u6XdsdsMG','fulufhelo','cashier','active','2026-05-16 11:12:18',NULL,NULL,1,NULL,NULL,0,NULL),(7,'selfcheckout','$2y$10$2z9S3Q6b/ZPwXpkRA3lxxOtsphuDsDxfb4TIn.w4UlM2HkuG8xREi','Self Checkout','cashier','active','2026-05-21 11:54:30',NULL,NULL,1,NULL,NULL,0,NULL),(10,'wizzy','$2y$10$xE/SxAy/ZcRTvVnp.D.Y8ueNKSRFRyrQVmO/lbxYyaV8Sjape6oSG','wizzy goto','cashier','active','2026-05-26 13:10:28','wizzy@gmail.com','',1,NULL,NULL,0,NULL),(11,'admin_store1','$2y$10$CxejqU8qyvMrQ9Yu/1CD8O0xuYEZuxq8PfQUtnmggSz57wpS9MDYe','My Store Admin','store_admin','active','2026-06-09 13:31:40',NULL,NULL,1,NULL,NULL,0,NULL),(12,'admin_store2','$2y$10$4VIJmbTvSWLQtTmdVpoTyOLEdymMEzJu7v9ll7NaC35MaKNpoXhru','wapanda Admin','store_admin','active','2026-06-09 13:31:40',NULL,NULL,2,NULL,NULL,0,NULL),(13,'admin_store3','$2y$10$tHC4w7wx5DIanU/tZ7zW7uy5bZ9ihSdnOJUBmoXEeMNdqbH5tYTKG','fast food Admin','store_admin','active','2026-06-09 13:31:40',NULL,NULL,3,NULL,NULL,0,NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-06-09 14:26:51
