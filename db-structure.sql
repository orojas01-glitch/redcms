-- MySQL dump 10.13  Distrib 8.4.10, for macos15 (arm64)
--
-- Host: 127.0.0.1    Database: redcms_clean_install
-- ------------------------------------------------------
-- Server version	8.4.10

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `RED_Admin`
--

DROP TABLE IF EXISTS `RED_Admin`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `RED_Admin` (
  `RecordID` int unsigned NOT NULL AUTO_INCREMENT,
  `Username` varchar(50) NOT NULL,
  `Password` varchar(255) NOT NULL,
  `Administrator` varchar(150) NOT NULL,
  `Alias` varchar(14) NOT NULL,
  `AdminType` varchar(10) NOT NULL COMMENT 'superadmin,guest',
  `AdminComponents` varchar(200) NOT NULL COMMENT 'RecordID from RED_Components',
  `AdminTools` varchar(50) NOT NULL DEFAULT '1,2' COMMENT 'RecordID from RED_Tools',
  `Email` varchar(254) NOT NULL,
  `Contact_Form` varchar(1) NOT NULL DEFAULT 'N',
  `Contact_Form_Pref` varchar(3) NOT NULL DEFAULT 'to' COMMENT 'to,bc,bcc',
  `Donation_Form` varchar(1) NOT NULL DEFAULT 'N',
  `Donation_Form_Pref` varchar(3) NOT NULL DEFAULT 'to' COMMENT 'to,bc,bcc',
  PRIMARY KEY (`RecordID`),
  UNIQUE KEY `uniq_red_admin_username` (`Username`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `RED_Admin`
--

-- Public seed admin passwords are disabled placeholders. Set fresh credentials before production use.

LOCK TABLES `RED_Admin` WRITE;
/*!40000 ALTER TABLE `RED_Admin` DISABLE KEYS */;
INSERT INTO `RED_Admin` (`RecordID`, `Username`, `Password`, `Administrator`, `Alias`, `AdminType`, `AdminComponents`, `AdminTools`, `Email`, `Contact_Form`, `Contact_Form_Pref`, `Donation_Form`, `Donation_Form_Pref`) VALUES (1,'admin','$2y$12$OTiENcqJ.UJZiwsRnUKXauau2Nv37PuBLQbuTxW.wgmphEpZeYpY2','Admin','Administrator','webmaster','100,102,103,104,105,117,107,111,116','1,2','','Y','bcc','Y','bcc'),(2,'guest','$2y$12$UhcRJ5sRhH39xJMs0Kufb.n6xQShgcXajZV7keqDVciACf2XCmEme','Admin','Guest','guest','100,107,116,111','1,2','','N','to','N','to');
/*!40000 ALTER TABLE `RED_Admin` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `RED_Admin_Roles`
--

DROP TABLE IF EXISTS `RED_Admin_Roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `RED_Admin_Roles` (
  `AdminRecordID` int unsigned NOT NULL,
  `RoleName` varchar(32) NOT NULL,
  `AssignedByAdminRecordID` int unsigned NOT NULL,
  `AssignedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`AdminRecordID`),
  KEY `idx_red_admin_roles_name` (`RoleName`,`AdminRecordID`),
  CONSTRAINT `fk_red_admin_roles_admin` FOREIGN KEY (`AdminRecordID`) REFERENCES `RED_Admin` (`RecordID`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `RED_Admin_Capabilities`
--

DROP TABLE IF EXISTS `RED_Admin_Capabilities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `RED_Admin_Capabilities` (
  `AdminRecordID` int unsigned NOT NULL,
  `Capability` varchar(64) NOT NULL,
  `GrantedByAdminRecordID` int unsigned NOT NULL,
  `GrantedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`AdminRecordID`,`Capability`),
  KEY `idx_red_admin_capabilities_capability` (`Capability`,`AdminRecordID`),
  CONSTRAINT `fk_red_admin_capabilities_admin` FOREIGN KEY (`AdminRecordID`) REFERENCES `RED_Admin` (`RecordID`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `RED_Addon_Installations`
--

DROP TABLE IF EXISTS `RED_Addon_Installations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `RED_Addon_Installations` (
  `PackageID` varchar(127) NOT NULL,
  `PackageVersion` varchar(120) NOT NULL,
  `PackageType` varchar(32) NOT NULL,
  `ManifestSHA256` char(64) NOT NULL,
  `InventorySHA256` char(64) NOT NULL,
  `LifecycleState` varchar(32) NOT NULL,
  `InstalledByAdminRecordID` int unsigned NOT NULL,
  `InstalledAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedByAdminRecordID` int unsigned NOT NULL,
  `UpdatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`PackageID`),
  KEY `idx_red_addon_installations_state` (`LifecycleState`,`PackageID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `RED_Addon_Migrations`
--

DROP TABLE IF EXISTS `RED_Addon_Migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `RED_Addon_Migrations` (
  `PackageID` varchar(127) NOT NULL,
  `MigrationID` varchar(120) NOT NULL,
  `MigrationPath` varchar(255) NOT NULL,
  `Checksum` char(64) NOT NULL,
  `AppliedByAdminRecordID` int unsigned NOT NULL,
  `AppliedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ExecutionMs` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`PackageID`,`MigrationID`),
  UNIQUE KEY `uq_red_addon_migrations_path` (`PackageID`,`MigrationPath`),
  CONSTRAINT `fk_red_addon_migrations_installation` FOREIGN KEY (`PackageID`) REFERENCES `RED_Addon_Installations` (`PackageID`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `RED_Admin_Activity_Log`
--

DROP TABLE IF EXISTS `RED_Admin_Activity_Log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `RED_Admin_Activity_Log` (
  `RecordID` bigint unsigned NOT NULL AUTO_INCREMENT,
  `EventName` varchar(64) NOT NULL,
  `ActorAdminRecordID` int unsigned NOT NULL,
  `TargetType` varchar(32) NOT NULL,
  `TargetRecordID` bigint unsigned NOT NULL,
  `OccurredAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`RecordID`),
  KEY `idx_red_admin_activity_time` (`OccurredAt`),
  KEY `idx_red_admin_activity_actor_time` (`ActorAdminRecordID`,`OccurredAt`),
  KEY `idx_red_admin_activity_target_time` (`TargetType`,`TargetRecordID`,`OccurredAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `RED_Login_Attempts`
--

DROP TABLE IF EXISTS `RED_Login_Attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `RED_Login_Attempts` (
  `RecordID` bigint unsigned NOT NULL AUTO_INCREMENT,
  `UsernameHash` binary(32) NOT NULL,
  `ClientAddress` varbinary(16) NOT NULL,
  `AttemptedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`RecordID`),
  KEY `idx_red_login_attempt_username_time` (`UsernameHash`,`AttemptedAt`),
  KEY `idx_red_login_attempt_client_time` (`ClientAddress`,`AttemptedAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `RED_Advanced`
--

DROP TABLE IF EXISTS `RED_Advanced`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `RED_Advanced` (
  `RecordID` int NOT NULL AUTO_INCREMENT,
  `Item` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Content` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Language` varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`RecordID`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `RED_Advanced`
--

LOCK TABLES `RED_Advanced` WRITE;
/*!40000 ALTER TABLE `RED_Advanced` DISABLE KEYS */;
INSERT INTO `RED_Advanced` (`RecordID`, `Item`, `Content`, `Language`) VALUES (1,'Website_Title','','sp'),(2,'Website_Slogan','','sp'),(3,'Website_Logo','','sp'),(5,'Website_Footer','','sp'),(4,'Website_Header','','sp'),(7,'Website_CSS','','sp'),(8,'System_Active_Theme','starter-reference',''),(9,'System_Previous_Theme','legacy-bootstrap',''),(10,'Website_Red_Sphere_Credit','Y','sp');
/*!40000 ALTER TABLE `RED_Advanced` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `RED_Articles`
--

DROP TABLE IF EXISTS `RED_Articles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `RED_Articles` (
  `RecordID` int unsigned NOT NULL,
  `Title` mediumtext NOT NULL,
  `Component` varchar(50) NOT NULL,
  `Alias` mediumtext NOT NULL,
  `Sections` varchar(100) NOT NULL,
  `HomePosition` int NOT NULL,
  `HomePositionOrder` int NOT NULL,
  `SectionPosition` int NOT NULL,
  `SectionPositionOrder` int NOT NULL,
  `Categories` varchar(100) NOT NULL,
  `CategoryPosition` int NOT NULL,
  `CategoryPositionOrder` int NOT NULL,
  `SubCategories` varchar(100) NOT NULL,
  `SubCategoryPosition` int NOT NULL,
  `SubCategoryPositionOrder` int NOT NULL,
  `Layout` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Full-Width' COMMENT 'Validated theme layout id',
  `Article` varchar(255) NOT NULL,
  `PagePosition` int NOT NULL DEFAULT '1',
  `PagePositionOrder` int NOT NULL,
  `Tags` mediumtext NOT NULL,
  `Active` varchar(1) NOT NULL DEFAULT 'Y',
  `HomeFeature` varchar(1) NOT NULL,
  `HomeFeatures` varchar(100) NOT NULL COMMENT 'slider, kwicks, wrapperblock',
  `HomeFeatures_Order` int NOT NULL,
  `SectionFeatures` varchar(100) NOT NULL COMMENT 'slider, kwicks, wrapperblock',
  `SectionFeatures_Order` int NOT NULL,
  `CategoryFeatures` varchar(100) NOT NULL COMMENT 'slider, kwicks, wrapperblock',
  `CategoryFeatures_Order` int NOT NULL,
  `SubCategoryFeatures` varchar(100) NOT NULL COMMENT 'slider, kwicks, wrapperblock',
  `SubCategoryFeatures_Order` int NOT NULL,
  `ArticleFeatures` varchar(100) NOT NULL COMMENT 'slider, kwicks, wrapperblock',
  `StartDate` datetime NOT NULL,
  `EventDate` datetime NOT NULL,
  `ExpDate` datetime NOT NULL,
  `ShortDesc` mediumtext NOT NULL,
  `LongDesc` mediumtext NOT NULL,
  `SliderDesc` mediumtext NOT NULL,
  `Link` mediumtext NOT NULL,
  `NewWindow` char(6) NOT NULL,
  `VideoSrc` mediumtext NOT NULL,
  `AlbumSrc` mediumtext NOT NULL,
  `BigPict` varchar(255) NOT NULL,
  `SmallPict` varchar(255) NOT NULL,
  `SmallPictAlign` varchar(6) NOT NULL COMMENT 'Left, Right, Top',
  `SmallPict2` varchar(255) NOT NULL,
  `SmallPictAlign2` varchar(6) NOT NULL COMMENT 'Left, Right, Top',
  `EditedBy` varchar(10) NOT NULL,
  `Language` varchar(2) NOT NULL,
  `Updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`RecordID`),
  KEY `idx_red_articles_public_route` (`Language`,`Active`,`Alias`(191),`Sections`,`Categories`,`SubCategories`),
  KEY `idx_red_articles_section_content` (`Language`,`Active`,`Sections`,`SectionPosition`,`SectionPositionOrder`,`StartDate`),
  KEY `idx_red_articles_category_content` (`Language`,`Active`,`Sections`,`Categories`,`CategoryPosition`,`CategoryPositionOrder`,`StartDate`),
  KEY `idx_red_articles_subcategory_content` (`Language`,`Active`,`Sections`,`Categories`,`SubCategories`,`SubCategoryPosition`,`SubCategoryPositionOrder`,`StartDate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `RED_Articles`
--

LOCK TABLES `RED_Articles` WRITE;
/*!40000 ALTER TABLE `RED_Articles` DISABLE KEYS */;
INSERT INTO `RED_Articles` (`RecordID`, `Title`, `Component`, `Alias`, `Sections`, `HomePosition`, `HomePositionOrder`, `SectionPosition`, `SectionPositionOrder`, `Categories`, `CategoryPosition`, `CategoryPositionOrder`, `SubCategories`, `SubCategoryPosition`, `SubCategoryPositionOrder`, `Layout`, `Article`, `PagePosition`, `PagePositionOrder`, `Tags`, `Active`, `HomeFeature`, `HomeFeatures`, `HomeFeatures_Order`, `SectionFeatures`, `SectionFeatures_Order`, `CategoryFeatures`, `CategoryFeatures_Order`, `SubCategoryFeatures`, `SubCategoryFeatures_Order`, `ArticleFeatures`, `StartDate`, `EventDate`, `ExpDate`, `ShortDesc`, `LongDesc`, `SliderDesc`, `Link`, `NewWindow`, `VideoSrc`, `AlbumSrc`, `BigPict`, `SmallPict`, `SmallPictAlign`, `SmallPict2`, `SmallPictAlign2`, `EditedBy`, `Language`, `Updated`) VALUES (89196971,'Instructions','Article','instructions','administracion',0,0,2,0,'',0,0,'',0,0,'index-2','',1,0,'instructions','Y','','',0,'',0,'',0,'',0,'','0000-00-00 00:00:00','0000-00-00 00:00:00','0000-00-00 00:00:00','<p id=\"instructions\">RED-CMS&trade; is an easy to use Content Management System to update your website.&nbsp; It provides full control of navigation, unlimited sections, pages, as well as advanced items including CSS, header and footer, page layouts and multiuser accounts with different privileges to edit your content. &nbsp;The administration area loads over &nbsp;your website for facilitated &nbsp;navigation and edition.&nbsp; In this manual you will find:</p>\r\n<ul id=\"instructions\">\r\n<li><a href=\"instructions#interface_guidelines\">Interface Guidelines</a></li>\r\n<li><a href=\"instructions#areas\">How to Add Sections, Categories or Subcategories</a></li>\r\n<li><a href=\"instructions#add_new_page\">How to Add a New Page</a></li>\r\n<li><a href=\"instructions#navigation\">Edit Navigation</a></li>\r\n<li><a href=\"instructions#move_content\">Move Article(s) and Components Location</a></li>\r\n<li><a href=\"instructions#advanced\">Advanced Settings</a></li>\r\n</ul>','<h1 id=\"instructions\">Red-CMS&trade;</h1>\r\n<p id=\"instructions\">RED-CMS&trade; is an easy to use Content Management System to update your website.&nbsp; It provides full control of navigation, unlimited sections, pages, as well as advanced items including CSS, header and footer, page layouts and multiuser accounts with different privileges to edit your content. &nbsp;The administration area loads over &nbsp;your website for facilitated &nbsp;navigation and edition.&nbsp; In this manual you will find:</p>\r\n<ul id=\"instructions\">\r\n<li><a href=\"instructions#interface_guidelines\">Interface Guidelines</a></li>\r\n<li><a href=\"instructions#areas\">How to Add Sections, Categories or Subcategories</a></li>\r\n<li><a href=\"instructions#add_new_page\">How to Add a New Page</a></li>\r\n<li><a href=\"instructions#navigation\">Edit Navigation</a></li>\r\n<li><a href=\"instructions#move_content\">Move Article(s) and Components Location</a></li>\r\n<li><a href=\"instructions#advanced\">Advanced Settings</a></li>\r\n</ul>\r\n<p id=\"instructions\">&nbsp;</p>\r\n<hr />\r\n<p id=\"instructions\"><a name=\"interface_guidelines\"></a></p>\r\n<h1 id=\"instructions\">Interface Guidelines</h1>\r\n<p id=\"instructions\">The Interface loads over your website.&nbsp; It includes the following main areas:</p>\r\n<ol id=\"instructions\">\r\n<li><strong><a href=\"#1-content\">Content</a>:</strong>&nbsp; This area controls the content and layout of the current page.</li>\r\n<li><strong><a href=\"#2-inactive-articles\">Inactive Articles</a>:</strong>&nbsp; This area controls articles that have being marked as inactive.</li>\r\n<li><strong><a href=\"#3-areas\">Sections, Categories, Subcategories</a>:</strong>&nbsp;This area controls the Name of the Section and other variables.</li>\r\n<li><strong><a href=\"#4-advanced\">Advanced</a>:</strong>&nbsp;This area controls the Title, Slogan, Logo, Header, Footer, CSS, and Language of the website.</li>\r\n</ol>\r\n<p id=\"instructions-img\"><img src=\"../admin/images/red-cms-instructions-manual_files/image005.png\" alt=\"\" width=\"1001\" height=\"722\" border=\"0\" /></p>\r\n<p id=\"instructions-ref\">image 1.&nbsp;</p>\r\n<p><a name=\"1-content\"></a></p>\r\n<h2 id=\"instructions\">1. Content</h2>\r\n<p id=\"instructions\">This area controls the content and layout of the current page.<br />This area is divided in 3 tabs:</p>\r\n<ul id=\"instructions\">\r\n<li>Edit content</li>\r\n<li>Add content</li>\r\n<li>Tools</li>\r\n</ul>\r\n<h3 id=\"instructions\">Edit Content</h3>\r\n<p id=\"instructions\">This view displays the representation of content distribution in the layout of the page (image 2).&nbsp;&nbsp;<br />Note each Layout may have a different number of available Positions to add content, which are highlighted in a black box.</p>\r\n<p id=\"instructions-img\"><img src=\"../admin/images/red-cms-instructions-manual_files/image010.png\" alt=\"\" width=\"1001\" height=\"455\" name=\"image010\" border=\"0\" /></p>\r\n<p id=\"instructions-ref\">image 2. Layout Example.</p>\r\n<p id=\"instructions\">Each Position includes an Order box (except position 0). The fields on the side of the Position (image 3) can be use to update the order in which the elements of this particular Position are ordered.&nbsp; Use the OK! &ndash; Button to reorder.</p>\r\n<p id=\"instructions-img\"><img src=\"../admin/images/red-cms-instructions-manual_files/image012.png\" alt=\"\" width=\"1001\" height=\"728\" border=\"0\" /></p>\r\n<p id=\"instructions-ref\">image 3</p>\r\n<p id=\"instructions\">Some Specific Sections, Categories and Subcategories, may use special&nbsp;<strong>Features</strong>, for example a&nbsp;<em>Photo Slider&nbsp;</em>(image 4). &nbsp;You can specify which areas use features from the Sections, Categories or Subcategories controls.&nbsp; When a feature is assigned, it will be presented in the Edit Content area.</p>\r\n<p id=\"instructions\"><strong>Top Navigation</strong>&nbsp;is present on all pages, which you can edit with your own Links.</p>\r\n<p id=\"instructions-img\"><img src=\"../admin/images/red-cms-instructions-manual_files/image015.png\" alt=\"\" width=\"1002\" height=\"778\" name=\"image015\" border=\"0\" /></p>\r\n<p id=\"instructions-ref\">image 4</p>\r\n<h3 id=\"instructions\">Add Content</h3>\r\n<p id=\"instructions\">This segment shows an example of the available Components to Add Content for your website (image 5).&nbsp; Each component has different capabilities, but there are some shared variables:</p>\r\n<ul id=\"instructions\">\r\n<li>Red-CMS automatically assigns the Section, Category, Subcategory and Article depending on the page you are at, but you can manually edit and change the location according to your needs.</li>\r\n<li>The Title, Homepage feature, Layout position, Position Order, and Active are mandatory for all Installed Components. Refer to&nbsp;<a href=\"#add_new_page\">How To Add a New Page</a>.</li>\r\n</ul>\r\n<p id=\"instructions-img\"><img src=\"../admin/images/red-cms-instructions-manual_files/image016.png\" alt=\"\" width=\"992\" height=\"286\" border=\"0\" /></p>\r\n<p id=\"instructions-ref\">image 5. Components Example.</p>\r\n<h3 id=\"instructions\">Tools</h3>\r\n<p id=\"instructions\">This area is to access of Custom made tools.&nbsp; As default it includes a Tool to Move content and assign Positions (image 6).</p>\r\n<p id=\"instructions-img\"><img src=\"../admin/images/red-cms-instructions-manual_files/image017.jpg\" alt=\"\" width=\"899\" height=\"261\" border=\"0\" /></p>\r\n<p id=\"instructions-ref\">image 6</p>\r\n<p id=\"instructions\">&nbsp;</p>\r\n<p><a name=\"2-inactive-articles\"></a></p>\r\n<h2 id=\"instructions\">2. Inactive Articles</h2>\r\n<p id=\"instructions\">This area saves content &nbsp;that is being worked on to facilitate access (image7).</p>\r\n<p id=\"instructions-img\"><img src=\"../admin/images/red-cms-instructions-manual_files/image018.png\" alt=\"\" width=\"1002\" height=\"256\" border=\"0\" /></p>\r\n<p id=\"instructions-ref\">image 7</p>\r\n<p><a name=\"3-areas\"></a></p>\r\n<h2 id=\"instructions\">3. Section, Categories &amp; Subcategories</h2>\r\n<p id=\"instructions\">Each of these areas includes SEO controls, article limits and control features that come with your selected template.<br />The administration areas are divided in 3 tabs:</p>\r\n<ul id=\"instructions\">\r\n<li>Edit</li>\r\n<li>Add</li>\r\n<li>Tools</li>\r\n</ul>\r\n<h3 id=\"instructions\">Edit</h3>\r\n<p id=\"instructions\">This view displays the Title, Layout, Active or Inactive status and button to Edit (image 8).</p>\r\n<p id=\"instructions-img\"><img src=\"../admin/images/red-cms-instructions-manual_files/image019.png\" alt=\"\" width=\"1000\" height=\"405\" border=\"0\" /></p>\r\n<p id=\"instructions-ref\">image 8</p>\r\n<h3 id=\"instructions\">Add</h3>\r\n<p id=\"instructions\">All areas use the same configuration.&nbsp; You can set a Title Name, Select a Layout, add Features, add Tags (SEO Keywords) and Page Description (SEO description). Refer to:&nbsp;<a href=\"#areas\">Add Section, Category, SubCategory</a>.</p>\r\n<h3 id=\"instructions\">Tools</h3>\r\n<p id=\"instructions\">Filter all content that is assigned to one specific area.&nbsp; You can also move content from this interface. Refer to:&nbsp;<a href=\"#move_content\">Move Article Location and Components</a>.</p>\r\n<p id=\"instructions-img\"><img src=\"../admin/images/red-cms-instructions-manual_files/image021.png\" alt=\"\" width=\"1002\" height=\"199\" border=\"0\" /></p>\r\n<p id=\"instructions-ref\">image 9</p>\r\n<p><a name=\"4-advanced\"></a></p>\r\n<h2 id=\"instructions\">4. Advanced</h2>\r\n<p id=\"instructions\">This area gives you control over Website Title, slogan, Logo, Header, Footer, CSS and Language.</p>\r\n<p id=\"instructions\">&nbsp;</p>\r\n<p id=\"instructions\">&nbsp;</p>\r\n<h1 id=\"instructions\"><a id=\"areas\" name=\"areas\"></a>How to Add Sections, Categories, Subcategories</h1>\r\n<p id=\"instructions\">Using the Admin interface:</p>\r\n<p id=\"instructions-img\"><img src=\"../admin/images/red-cms-instructions-manual_files/image023.jpg\" alt=\"\" width=\"998\" height=\"560\" border=\"0\" /></p>\r\n<p id=\"instructions-ref\">image 10</p>\r\n<p id=\"instructions\">You can set these on each area:</p>\r\n<ul id=\"instructions\">\r\n<li>Section name</li>\r\n<li>Choose layout</li>\r\n<li>Set features</li>\r\n<li>Tags: SEO tags</li>\r\n<li>Long Description: SEO Description</li>\r\n<li>Articles limit</li>\r\n</ul>\r\n<p id=\"instructions\">Once you save, the system automatically creates an Alias based on the Title.&nbsp; That alias is used on the URL.<br />Please note the SEO tags you set here only apply for the area assigned to it (Section, Category or Subcategory).&nbsp; Each article has its own set of Tags.</p>\r\n<p id=\"instructions\">&nbsp;</p>\r\n<p id=\"instructions\">&nbsp;</p>\r\n<h1 id=\"instructions\"><a id=\"add_new_page\" name=\"add_new_page\"></a>How to Add a new Page</h1>\r\n<p id=\"instructions\">Every piece of content must be place inside a Landing Page.&nbsp; To add a Landing Page you create a new Article.</p>\r\n<p id=\"instructions\">First, determine the Location you want for the New Article. The location is determined by the Section, Category, Subcategory assigned. The location is also used for the URL to access it. Note that the Section, Category or Subcategory&nbsp;MUST&nbsp;be created before any Article can be assigned to them.&nbsp;</p>\r\n<blockquote id=\"instructions\">\r\n<h4 id=\"instructions\">Example</h4>\r\n<p id=\"instructions\">Add a new page for Aspen-Colorado State, inside Design Projects.&nbsp;&nbsp;</p>\r\n<p id=\"instructions\">Check out the following URL to determine location:</p>\r\n<p id=\"instructions\">http://your-url.com/services/design-projects/new article goes here<br /><strong>Section:</strong>&nbsp;services<br /><strong>Category:</strong>&nbsp;design-projects<br /><strong>New Article or Landing Page (alias):</strong>&nbsp;aspen-colorado-state<br /><br />Note in this example no&nbsp;<strong>Subcategory</strong>&nbsp;is assigned.</p>\r\n</blockquote>\r\n<ol id=\"instructions\">\r\n<li>Login to your Content Management system.</li>\r\n<li>Click on&nbsp;<strong>Add Content &gt; Article</strong>.&nbsp;<br />You can also navigate closer to a page you want the new page to be access.<br />The difference is that the Section, Category, Subcategory are automatically selected from the URL if they exist.&nbsp; But you can always select the Location from the Drop Down.<br /><img src=\"../admin/images/red-cms-instructions-manual_files/image025.png\" alt=\"\" width=\"1001\" height=\"295\" border=\"0\" />\r\n<p id=\"instructions-ref\">image 11</p>\r\n</li>\r\n<li>You&nbsp;<strong>MUST</strong>&nbsp;add or Select the following information:<ol type=\"a\">\r\n<li><strong>Title</strong>: This is also going to be used to generate the URL friendly (alias). (image 12).</li>\r\n<li><strong>Content</strong>: Only add it if you want to use the Article as the content holder. Make sure to select a&nbsp;<strong>Position</strong>.</li>\r\n<li><strong>Section, Category or Subcategory</strong>:&nbsp; At least one Section (home) must be assigned. (image 13).</li>\r\n</ol>\r\n<p id=\"instructions-img\"><img src=\"../admin/images/red-cms-instructions-manual_files/image027.png\" alt=\"\" width=\"1003\" height=\"301\" border=\"0\" /></p>\r\n<p id=\"instructions-ref\">image 12</p>\r\n<p id=\"instructions-img\"><img src=\"../admin/images/red-cms-instructions-manual_files/image029.png\" alt=\"\" width=\"1000\" height=\"501\" border=\"0\" /></p>\r\n<p id=\"instructions-ref\">image 13. Note the button&nbsp;More&nbsp;expand the advanced options.</p>\r\n</li>\r\n<li>Click&nbsp;<strong>Save</strong>.</li>\r\n</ol>\r\n<p id=\"instructions\">Now that you have added the&nbsp;<strong>Article</strong>, you can add any component to it.&nbsp; A landing page can be made out of multiple components.</p>\r\n<p>Next, you have to determine how you want to access the new landing page:&nbsp;Included&nbsp;in&nbsp;<strong>Navigation</strong>&nbsp;or&nbsp;Not Included&nbsp;in&nbsp;<strong>Top Navigation</strong>&nbsp;or&nbsp;<strong>Submenu</strong>.</p>\r\n<h3 id=\"instructions\">Included in Navigation</h3>\r\n<p id=\"instructions\"><strong>Means the page is accessible through the Top Navigation or Submenu.</strong><br />Refer to:&nbsp;<a href=\"#navigation\">How to Edit Top Navigation</a>.</p>\r\n<h3 id=\"instructions\">Not Included in Navigation</h3>\r\n<p id=\"instructions\"><strong>Means the page is only accessible through the direct URL.</strong></p>\r\n<p id=\"instructions\">To determine the URL, you can refer to the&nbsp;<strong>Article</strong>&nbsp;and check the alias created. The&nbsp;<strong>Article</strong>&nbsp;will be located at the end of the Section, Category, and Subcategory you have selected.</p>\r\n<blockquote id=\"instructions\">\r\n<h4 id=\"instructions\">Example</h4>\r\n<p id=\"instructions\"><strong>Aspen-Colorado State</strong>&nbsp;Article was added inside&nbsp;<strong>Design Projects</strong>:<br />http://your-url.com/services/design-projects/aspen-colorado-state&nbsp;<br /><strong>Section:</strong>&nbsp;services<br /><strong>Category:</strong>&nbsp;design-projects<br /><strong>New Article or Landing Page (alias):</strong>&nbsp;aspen-colorado-state<br /><br />Note the url alias is changed from Aspen<strong>-</strong>Colorado State to aspen-colorado-state</p>\r\n</blockquote>\r\n<p>The system automatically creates the URL alias, which replaces the Title using only valid characters:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</p>\r\n<blockquote id=\"instructions\"><strong>-</strong>&nbsp;or&nbsp;<strong>_</strong>&nbsp;are valid.<br /><strong>@</strong>&nbsp;or&nbsp;<strong>%</strong>&nbsp;are converted to&nbsp;<strong>at</strong>&nbsp;or&nbsp;<strong>percentage<br />space&nbsp;</strong>is converted to&nbsp;<strong>-<br /></strong>All other characters, including Spanish accent are removed or replaced.&nbsp;&nbsp;&nbsp; You can edit the&nbsp;<strong>Article</strong>&nbsp;to update the generated&nbsp;<strong>alias</strong>.&nbsp; This is useful for SEO.</blockquote>\r\n<p id=\"instructions\">&nbsp;</p>\r\n<p id=\"instructions\">&nbsp;</p>\r\n<h1 id=\"instructions\"><a id=\"navigation\" name=\"navigation\"></a>How to Edit Top Navigation or Submenu(s)</h1>\r\n<p id=\"instructions\">Determine which menu you want to update.&nbsp;&nbsp;<strong>Top Navigation</strong>&nbsp;is present on all pages.&nbsp;&nbsp;<strong>Submenu</strong>&nbsp;is present only in selected pages.&nbsp; Follow the instructions for both:</p>\r\n<ol id=\"instructions\">\r\n<li>Login to your Content Management system.</li>\r\n<li>Click on&nbsp;<strong>Top Navigation &gt; Edit</strong>.&nbsp;<br />or Locate the&nbsp;<strong>Submenu &gt; Edit</strong><br /><img src=\"../admin/images/red-cms-instructions-manual_files/image031.png\" alt=\"\" width=\"1002\" height=\"637\" name=\"image031\" border=\"0\" />\r\n<p id=\"instructions-ref\">image 14</p>\r\n</li>\r\n</ol>\r\n<h2 id=\"instructions\"><strong>To Edit</strong></h2>\r\n<ol type=\"a\">\r\n<li>\r\n<p id=\"instructions\">Click any of the Buttons in the&nbsp;<strong>Menu Item Manager</strong>&nbsp;to expand and see available options.</p>\r\n<p id=\"instructions-img\"><img src=\"../admin/images/red-cms-instructions-manual_files/image033.png\" alt=\"\" width=\"1000\" height=\"960\" name=\"image033\" border=\"0\" /></p>\r\n<p id=\"instructions-ref\">image 15</p>\r\n</li>\r\n<li>To Edit any button, update Level&nbsp; 1,2 or 3 Button, or Order, select a link from the populated links available on the website, or enter your own URL. (image 3)<br />Note that&nbsp; some options included may not contain content.&nbsp; Please choose carefully.\r\n<p id=\"instructions-img\"><img src=\"../admin/images/red-cms-instructions-manual_files/image034.png\" alt=\"\" width=\"1000\" height=\"844\" name=\"image034\" border=\"0\" /></p>\r\n<p id=\"instructions-ref\">image 16</p>\r\n</li>\r\n<li>Click&nbsp;<strong>Save</strong>.</li>\r\n</ol>\r\n<h2 id=\"instructions\">To Add</h2>\r\n<ol type=\"a\">\r\n<li>The&nbsp;<strong>Top Navigation</strong>&nbsp;includes 3 (three) level deep navegation (image 17).&nbsp;&nbsp;<strong>Submenus</strong>&nbsp;include only 1 (one) level. (image 18)\r\n<p id=\"instructions-img\"><img src=\"../admin/images/red-cms-instructions-manual_files/image038.png\" alt=\"\" width=\"1001\" height=\"1033\" border=\"0\" /></p>\r\n<p id=\"instructions-ref\">image 17</p>\r\n<p id=\"instructions-img\"><img src=\"../admin/images/red-cms-instructions-manual_files/image040.png\" alt=\"\" width=\"999\" height=\"748\" border=\"0\" /></p>\r\n<p id=\"instructions-ref\">image 18</p>\r\n</li>\r\n<li>Click&nbsp;<strong>Save</strong>. The menu interface will have been updated.</li>\r\n</ol>\r\n<p id=\"instructions\">&nbsp;</p>\r\n<p id=\"instructions\">&nbsp;</p>\r\n<h1 id=\"instructions\"><a id=\"move_content\" name=\"move_content\"></a>How to Move Article Location</h1>\r\n<p id=\"instructions\">Determine all the Components (content) that need to be moved from the page.&nbsp; i.e:&nbsp; Article(s), Gallery(s), Sub-Menu(s), etc.<br />All components have the option to be assigned to one (1) Section, Category and Subcategory.<br />Some components have the option to be assigned to multiple Articles. i.e: SubMenus, Articles, Forms, Other or Galleries.&nbsp;</p>\r\n<p id=\"instructions\"><strong>There are two(2) ways of doing it:</strong></p>\r\n<ol id=\"instructions\">\r\n<li>Using the&nbsp;<strong>Move Tools</strong>&nbsp;or&nbsp;<strong>Filter Tools</strong>.</li>\r\n<li>Assigning one component at a time.</li>\r\n</ol>\r\n<p id=\"instructions\">To check how the content is distributed, use the&nbsp;<strong>Move Tools</strong>&nbsp;on Top, and the&nbsp;<strong>Filter Tools</strong>&nbsp;under each Section, Category and Subcategory.&nbsp; (image 19)</p>\r\n<p id=\"instructions-img\"><img src=\"../admin/images/red-cms-instructions-manual_files/image041.jpg\" alt=\"\" width=\"993\" height=\"560\" border=\"0\" /></p>\r\n<p id=\"instructions-ref\">image 19</p>\r\n<h2 id=\"instructions\">Move Tool</h2>\r\n<p id=\"instructions\">The&nbsp;<strong>Move Tool</strong>&nbsp;(image 20) displays the content of the current page.&nbsp; It contains the following Options:</p>\r\n<ol id=\"instructions\">\r\n<li>Select the Article(s) you want to move.<br />You can see the Article Title, Position in the Page, Component type, Section, Category, Subcategory and Article(s) assigned.</li>\r\n<li>Select the New Article(s) location.&nbsp; You can assign every article to a specific Section, Category, Subcategory and to Multiple Article(s).</li>\r\n<li>Select the position to move within the same page.</li>\r\n</ol>\r\n<p id=\"instructions-img\"><img src=\"../admin/images/red-cms-instructions-manual_files/image042.jpg\" alt=\"\" width=\"999\" height=\"1011\" border=\"0\" /></p>\r\n<p id=\"instructions-ref\">image 20</p>\r\n<h2 id=\"instructions\">Filter Tool</h2>\r\n<p id=\"instructions\">The&nbsp;<strong>Filter Tool</strong>&nbsp;(image 21) displays the list of Components assigned to a specific Section, Category or Subcategory. It contains the following Options:</p>\r\n<ol id=\"instructions\">\r\n<li>Select the Article(s) you want to move.<br />You can see the Article Title, Position in the Page, Component type, Section, Category, Subcategory and Article(s) assigned.</li>\r\n<li>Select the New Article(s) location.&nbsp; You can assign every article to a specific Section, Category, Subcategory or Multiple Article(s).</li>\r\n</ol>\r\n<p id=\"instructions-img\"><img src=\"../admin/images/red-cms-instructions-manual_files/image043.jpg\" alt=\"\" width=\"1001\" height=\"799\" border=\"0\" /></p>\r\n<p id=\"instructions-ref\">image 21</p>\r\n<p id=\"instructions\">&nbsp;</p>\r\n<p id=\"instructions\">&nbsp;</p>\r\n<h1 id=\"instructions\"><a id=\"advanced\" name=\"advanced\"></a>Advanced</h1>\r\n<p id=\"instructions\">This area gives you control over Website Title, slogan, Logo, Header, Footer, CSS and languages of the website (image 22).</p>\r\n<p id=\"instructions-img\"><img src=\"../admin/images/red-cms-instructions-manual_files/image044.jpg\" alt=\"\" width=\"994\" height=\"345\" border=\"0\" /></p>\r\n<p id=\"instructions-ref\">image 22</p>','','','','','','','','','','','Admin','sp','2020-10-15 03:09:05'),(459269660,'Contact','Form','contact','contacto',0,0,1,1,'',0,0,'',0,0,'Two-Columns','',1,0,'','Y','','',0,'',0,'',0,'',0,'','0000-00-00 00:00:00','0000-00-00 00:00:00','0000-00-00 00:00:00','','','','','','','','','','','','','Admin','sp','2026-07-10 18:02:24'),(880701099,'Como agregar contenido','Gallery','test-vimeo','administracion',0,0,2,0,'',0,0,'',0,0,'','',1,0,'corouniversaldelamor','Y','','',0,'',0,'',0,'',0,'','0000-00-00 00:00:00','0000-00-00 00:00:00','0000-00-00 00:00:00','','','','','','','','','','','','','Admin','sp','2020-10-14 20:42:06'),(887799848,'ContÃ¡ctenos','ShortArticle','cont-ctenos','contacto',0,0,0,1,'',0,0,'',0,0,'Full-Width','',1,0,'cont,ctenos','Y','','',0,'',0,'',0,'',0,'','0000-00-00 00:00:00','0000-00-00 00:00:00','0000-00-00 00:00:00','<h4>Tel&eacute;fonos</h4>\r\n<p><strong>Bogot&aacute; - Colombia</strong></p>\r\n<p>(313) 371-9980<br />(1) 814-7114</p>\r\n<p><strong>Cali - Colombia</strong></p>\r\n<p>(321) 240-9712</p>','','','','','','','','','','','','Admin','sp','2017-11-01 15:48:07'),(966111194,'Login','Form','login','administracion',1,5,1,1,'',1,0,'',1,0,'index-2','',1,0,'login','Y','','flex',1,'',0,'',0,'',0,'','2012-06-01 00:00:00','0000-00-00 00:00:00','0000-00-00 00:00:00','Login now!','','','','','','','','','','','','admin','sp','2015-02-28 01:11:39');
/*!40000 ALTER TABLE `RED_Articles` ENABLE KEYS */;
UNLOCK TABLES;

-- The clean installer excludes retired Short Article content rows.
DELETE FROM `RED_Articles` WHERE `Component` = 'ShortArticle';

-- Keep the recovered Contact form shell on the registered contacto layout.
UPDATE `RED_Articles`
SET `Layout` = 'index-1'
WHERE `RecordID` = 459269660
  AND `Component` = 'Form'
  AND `Alias` = 'contact'
  AND `Sections` = 'contacto'
  AND `Layout` = 'Two-Columns';

-- Keep the seeded Instructions article aligned with the available component set.
UPDATE RED_Articles
SET LongDesc = REPLACE(LongDesc, '&nbsp;or&nbsp;<strong>Submenu</strong>', '')
WHERE Title = 'Instructions' AND Component = 'Article' AND Alias = 'instructions';

UPDATE RED_Articles
SET LongDesc = REPLACE(LongDesc, 'Top Navigation or Submenu.', 'Top Navigation.')
WHERE Title = 'Instructions' AND Component = 'Article' AND Alias = 'instructions';

UPDATE RED_Articles
SET LongDesc = REPLACE(LongDesc, 'How to Edit Top Navigation or Submenu(s)', 'How to Edit Top Navigation')
WHERE Title = 'Instructions' AND Component = 'Article' AND Alias = 'instructions';

UPDATE RED_Articles
SET LongDesc = REPLACE(
  LongDesc,
  '&nbsp;&nbsp;<strong>Submenu</strong>&nbsp;is present only in selected pages.&nbsp; Follow the instructions for both:',
  '&nbsp; Follow these instructions:'
)
WHERE Title = 'Instructions' AND Component = 'Article' AND Alias = 'instructions';

UPDATE RED_Articles
SET LongDesc = REPLACE(
  LongDesc,
  '&nbsp;<br />or Locate the&nbsp;<strong>Submenu &gt; Edit</strong><br />',
  '<br />'
)
WHERE Title = 'Instructions' AND Component = 'Article' AND Alias = 'instructions';

UPDATE RED_Articles
SET LongDesc = REPLACE(
  LongDesc,
  '&nbsp;&nbsp;<strong>Submenus</strong>&nbsp;include only 1 (one) level. (image 18)',
  ''
)
WHERE Title = 'Instructions' AND Component = 'Article' AND Alias = 'instructions';

UPDATE RED_Articles
SET LongDesc = REPLACE(
  LongDesc,
  '\r\n<p id="instructions-img"><img src="../admin/images/red-cms-instructions-manual_files/image040.png" alt="" width="999" height="748" border="0" /></p>',
  ''
)
WHERE Title = 'Instructions' AND Component = 'Article' AND Alias = 'instructions';

UPDATE RED_Articles
SET LongDesc = REPLACE(LongDesc, '\r\n<p id="instructions-ref">image 18</p>', '')
WHERE Title = 'Instructions' AND Component = 'Article' AND Alias = 'instructions';

UPDATE RED_Articles
SET LongDesc = REPLACE(LongDesc, ', Sub-Menu(s)', '')
WHERE Title = 'Instructions' AND Component = 'Article' AND Alias = 'instructions';

UPDATE RED_Articles
SET LongDesc = REPLACE(LongDesc, 'SubMenus, ', '')
WHERE Title = 'Instructions' AND Component = 'Article' AND Alias = 'instructions';

--
-- Table structure for table `RED_Content_Revisions`
--

DROP TABLE IF EXISTS `RED_Content_Revisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `RED_Content_Revisions` (
  `RevisionID` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ContentRecordID` int unsigned NOT NULL,
  `ContentType` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `RevisionNumber` int unsigned NOT NULL,
  `Operation` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ActorAdminRecordID` int unsigned NOT NULL,
  `ActorAlias` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Snapshot` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `SnapshotHash` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `RestoredFromRevisionID` bigint unsigned DEFAULT NULL,
  `CreatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`RevisionID`),
  UNIQUE KEY `uniq_red_content_revision_number` (`ContentRecordID`,`RevisionNumber`),
  KEY `idx_red_content_revision_timeline` (`ContentRecordID`,`CreatedAt`,`RevisionID`),
  KEY `idx_red_content_revision_actor_time` (`ActorAdminRecordID`,`CreatedAt`),
  KEY `idx_red_content_revision_hash` (`ContentRecordID`,`SnapshotHash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `RED_Content_Revisions`
--

LOCK TABLES `RED_Content_Revisions` WRITE;
/*!40000 ALTER TABLE `RED_Content_Revisions` DISABLE KEYS */;
/*!40000 ALTER TABLE `RED_Content_Revisions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `RED_Custom_Layouts`
--

DROP TABLE IF EXISTS `RED_Custom_Layouts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `RED_Custom_Layouts` (
  `LayoutID` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `DraftLabel` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `DraftDefinition` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `DraftHash` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `PublishedLabel` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `PublishedDefinition` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `PublishedHash` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `RevisionNumber` int unsigned NOT NULL DEFAULT 1,
  `Archived` char(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'N',
  `CreatedByAdminRecordID` int unsigned NOT NULL,
  `UpdatedByAdminRecordID` int unsigned NOT NULL,
  `CreatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `PublishedAt` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`LayoutID`),
  KEY `idx_red_custom_layout_status` (`Archived`,`PublishedAt`),
  KEY `idx_red_custom_layout_updated` (`UpdatedAt`,`LayoutID`),
  CONSTRAINT `chk_red_custom_layout_archived` CHECK (`Archived` IN ('Y','N'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `RED_Custom_Layouts`
--

LOCK TABLES `RED_Custom_Layouts` WRITE;
/*!40000 ALTER TABLE `RED_Custom_Layouts` DISABLE KEYS */;
/*!40000 ALTER TABLE `RED_Custom_Layouts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `RED_Custom_Layout_Revisions`
--

DROP TABLE IF EXISTS `RED_Custom_Layout_Revisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `RED_Custom_Layout_Revisions` (
  `RevisionID` bigint unsigned NOT NULL AUTO_INCREMENT,
  `LayoutID` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `RevisionNumber` int unsigned NOT NULL,
  `Operation` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ActorAdminRecordID` int unsigned NOT NULL,
  `ActorAlias` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Snapshot` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `SnapshotHash` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `RestoredFromRevisionID` bigint unsigned DEFAULT NULL,
  `CreatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`RevisionID`),
  UNIQUE KEY `uniq_red_custom_layout_revision` (`LayoutID`,`RevisionNumber`),
  KEY `idx_red_custom_layout_timeline` (`LayoutID`,`CreatedAt`,`RevisionID`),
  KEY `idx_red_custom_layout_revision_actor` (`ActorAdminRecordID`,`CreatedAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `RED_Custom_Layout_Revisions`
--

LOCK TABLES `RED_Custom_Layout_Revisions` WRITE;
/*!40000 ALTER TABLE `RED_Custom_Layout_Revisions` DISABLE KEYS */;
/*!40000 ALTER TABLE `RED_Custom_Layout_Revisions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `RED_Categories`
--

DROP TABLE IF EXISTS `RED_Categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `RED_Categories` (
  `RecordID` int unsigned NOT NULL AUTO_INCREMENT,
  `SectionRecordID` int unsigned DEFAULT NULL COMMENT 'Parent RED_Sections.RecordID',
  `Categories` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Title` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Layout` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `QueryLimit` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `AccessLevel` char(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Public',
  `Features` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'slider, wrapperblock, kwicks',
  `Active` varchar(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'N',
  `Description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Tags` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Language` char(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `CreationDate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`RecordID`),
  KEY `idx_red_categories_public_alias` (`Language`,`Active`,`Categories`),
  KEY `idx_red_categories_parent` (`SectionRecordID`,`Language`,`Active`,`Categories`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `RED_Categories`
--

LOCK TABLES `RED_Categories` WRITE;
/*!40000 ALTER TABLE `RED_Categories` DISABLE KEYS */;
/*!40000 ALTER TABLE `RED_Categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `RED_Components`
--

DROP TABLE IF EXISTS `RED_Components`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `RED_Components` (
  `RecordID` int NOT NULL AUTO_INCREMENT,
  `UniqueName` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Layout` varchar(35) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `CompGroup` char(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ButtonTag` varchar(35) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Template` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ResponseTemplate` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`RecordID`)
) ENGINE=InnoDB AUTO_INCREMENT=130 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `RED_Components`
--

LOCK TABLES `RED_Components` WRITE;
/*!40000 ALTER TABLE `RED_Components` DISABLE KEYS */;
INSERT INTO `RED_Components` (`RecordID`, `UniqueName`, `Layout`, `CompGroup`, `ButtonTag`, `Template`, `ResponseTemplate`) VALUES (106,'Gallery','Gallery','Y','Gallery','',''),(107,'Gallery','Video','Y','Video','',''),(108,'Gallery','Banner','Y','Banner','',''),(102,'Form','Contact','Y','Form Contact','#|question=|name=name|type=textfield|required=true|displayname=Enter your Full Name:|initialvalue=;\r\n#|question=|name=title|type=textfield|required=false|displayname=Enter your Title:|initialvalue=;\r\n#|question=|name=email|type=textfield|required=true|displayname=Enter your e-mail:|initialvalue=;\r\n#|question=|name=telephone|type=textfield|required=true|displayname=Enter your Telephone:|initialvalue=;\r\n#|question=|name=fax|type=textfield|required=false|displayname=Enter your Fax:|initialvalue=;\r\n#|question=|name=message|type=textarea|required=false|displayname=Enter your Message:|readonly=false|initialvalue=|cols=45|rows=5;\r\n#|question=|name=Submit|type=button|displayname=submit',''),(103,'Form','Login','Y','Form Login','#|question=|name=username|type=textfield|required=true|displayname=Enter your Username:|initialvalue=;\r\n#|question=|name=password|type=password|required=true|displayname=Enter your Password:|initialvalue=;\r\n#|question=|name=Submit|type=button|displayname=submit',''),(113,'SubMenu','Vertical','Y','SubMenu Vertical','',''),(104,'Form','Response','Y','Form Response','#|question=|name=full_name|type=textfield|required=true|displayname=Full Name:|initialvalue=;#|question=|name=email|type=textfield|required=true|displayname=Email:|initialvalue=;#|question=|name=message|type=textarea|required=false|displayname=Message:|readonly=false|initialvalue=|cols=45|rows=5;#|question=|name=Submit|type=button|displayname=Submit','<p><strong>Thank you. Your response has been received.</strong></p>'),(105,'Form','Other','Y','Form Other','question=*name=name*type=textfield*required=true*displayname=Enter your Name:*initialvalue=;\r\nquestion=*name=title*type=textfield*required=false*displayname=Enter your Title:*initialvalue=;\r\nquestion=*name=email*type=textfield*required=true*displayname=Enter your e-mail:*initialvalue=;\r\nquestion=*name=telephone*type=textfield*required=true*displayname=Enter your Telephone:*initialvalue=;\r\nquestion=*name=fax*type=textfield*required=false*displayname=Enter your Fax:*initialvalue=;\r\nquestion=*name=message*type=textarea*required=false*displayname=Enter your Message:*initialvalue=*cols=45*rows=5;\r\nquestion=*name=Subscribe*type=checkbox*required=false*displayname=subscribe to newsletter*checked=false;\r\nquestion=*name=radiogroup1*type=radio*required=false*displayname=select radio*value=radio 1,radio 2,radio 3;\r\nquestion=*name=selectgroup1*type=select*required=true*displayname=select from options*value=Please select|selected,--------|disabled,select 1,select 2,select 3;\r\n#|question=|name=terms_and_conditions|type=textarea|required=false|displayname=|readonly=true|initialvalue=TERMS AND CONDITIONS.|cols=45|rows=5;\r\nquestion=*type=hidden*required=true*initialvalue=value;\r\nquestion=*name=Submit*type=button*displayname=submit',''),(110,'News','News','','News','',''),(101,'Event','Event','','Event','',''),(112,'ShortArticle','ShortArticle','','Short Article','',''),(100,'Article','Article','','Article','',''),(118,'ContentBox','ContentBox','','Content Box','',''),(111,'Other','Other','','Other','',''),(115,'Testimonial','Testimonial','','Testimonial','',''),(114,'SubMenu','Horizontal','Y','SubMenu Horizontal','',''),(116,'FTP','FTP','','FTP','',''),(117,'Form','Register','Y','Form Register','#|question=|name=full_name|type=textfield|required=true|displayname=Full Name:|initialvalue=;#|question=|name=email|type=textfield|required=true|displayname=Email:|initialvalue=;#|question=|name=message|type=textarea|required=false|displayname=Message (optional):|readonly=false|initialvalue=|cols=45|rows=5;#|question=|name=Submit|type=button|displayname=Register','<p><strong>Thank you. Your registration has been received.</strong></p>');
/*!40000 ALTER TABLE `RED_Components` ENABLE KEYS */;
UNLOCK TABLES;

-- The clean installer excludes unused/non-working component choices.
DELETE FROM `RED_Components`
WHERE (`RecordID` = 101 AND `UniqueName` = 'Event')
   OR (`RecordID` = 110 AND `UniqueName` = 'News')
   OR (`RecordID` = 112 AND `UniqueName` = 'ShortArticle')
   OR (`RecordID` = 113 AND `UniqueName` = 'SubMenu' AND `Layout` = 'Vertical')
   OR (`RecordID` = 114 AND `UniqueName` = 'SubMenu' AND `Layout` = 'Horizontal')
   OR (`RecordID` = 115 AND `UniqueName` = 'Testimonial')
   OR (`RecordID` = 118 AND `UniqueName` = 'ContentBox');

--
-- Table structure for table `RED_C_Form`
--

DROP TABLE IF EXISTS `RED_C_Form`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `RED_C_Form` (
  `RecordID` int unsigned NOT NULL,
  `RefID` varchar(10) NOT NULL,
  `Title` mediumtext NOT NULL,
  `Alias` mediumtext NOT NULL,
  `FormType` varchar(20) NOT NULL COMMENT 'contact,login',
  `ShortDesc` mediumtext NOT NULL,
  `LongDesc` mediumtext NOT NULL,
  `Subject` varchar(100) NOT NULL,
  `Submitter` text NOT NULL,
  `Destinatary` text NOT NULL,
  `CC` text NOT NULL,
  `BCC` text NOT NULL,
  `Response` mediumtext NOT NULL,
  `TableName` varchar(64) NOT NULL,
  `Updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`RecordID`),
  KEY `idx_red_c_form_refid` (`RefID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `RED_C_Form`
--

LOCK TABLES `RED_C_Form` WRITE;
/*!40000 ALTER TABLE `RED_C_Form` DISABLE KEYS */;
INSERT INTO `RED_C_Form` (`RecordID`, `RefID`, `Title`, `Alias`, `FormType`, `ShortDesc`, `LongDesc`, `Subject`, `Submitter`, `Destinatary`, `CC`, `BCC`, `Response`, `TableName`, `Updated`) VALUES (93039112,'459269660','Contact','contact','Contact','','#|question=|name=name|type=textfield|required=true|displayname=Enter your Full Name:|initialvalue=;\r\n#|question=|name=title|type=textfield|required=false|displayname=Enter your Title:|initialvalue=;\r\n#|question=|name=email|type=textfield|required=true|displayname=Enter your e-mail:|initialvalue=;\r\n#|question=|name=telephone|type=textfield|required=true|displayname=Enter your Telephone:|initialvalue=;\r\n#|question=|name=fax|type=textfield|required=false|displayname=Enter your Fax:|initialvalue=;\r\n#|question=|name=message|type=textarea|required=false|displayname=Enter your Message:|readonly=false|initialvalue=|cols=45|rows=5;\r\n#|question=|name=Submit|type=button|displayname=submit','RED-CMS Starter','noreply@example.com','owner@example.com','','','','','2014-06-04 20:21:54'),(884542279,'966111194','Login','login','Login','','#|question=|name=username|type=textfield|required=true|displayname=Username:|initialvalue=;\r\n#|question=|name=password|type=password|required=true|displayname=Password:|initialvalue=;\r\n#|question=|name=Submit|type=button|displayname=submit','','','','','','','','2015-02-22 23:20:39');
/*!40000 ALTER TABLE `RED_C_Form` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `RED_C_Gallery`
--

DROP TABLE IF EXISTS `RED_C_Gallery`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `RED_C_Gallery` (
  `RecordID` int unsigned NOT NULL,
  `RefID` varchar(10) NOT NULL,
  `Title` mediumtext NOT NULL,
  `Alias` mediumtext NOT NULL,
  `GalleryType` varchar(20) NOT NULL COMMENT 'Gallery, Video, Banner',
  `ShortDesc` mediumtext NOT NULL,
  `Link` mediumtext NOT NULL,
  `LongDesc` mediumtext NOT NULL,
  `NewWindow` char(1) NOT NULL,
  `Updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`RecordID`),
  KEY `idx_red_c_gallery_refid` (`RefID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `RED_C_Gallery`
--

LOCK TABLES `RED_C_Gallery` WRITE;
/*!40000 ALTER TABLE `RED_C_Gallery` DISABLE KEYS */;
INSERT INTO `RED_C_Gallery` (`RecordID`, `RefID`, `Title`, `Alias`, `GalleryType`, `ShortDesc`, `Link`, `LongDesc`, `NewWindow`, `Updated`) VALUES (1968830051,'880701099','Como agregar contenido','test-vimeo','Video','','','https://www.youtube.com/watch?v=pP8VJwjSnqA&feature=youtu.be','','2020-10-14 20:42:06');
/*!40000 ALTER TABLE `RED_C_Gallery` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `RED_C_Menu`
--

DROP TABLE IF EXISTS `RED_C_Menu`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `RED_C_Menu` (
  `RecordID` int unsigned NOT NULL AUTO_INCREMENT,
  `RefID` int unsigned NOT NULL,
  `RootOrder` varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Title` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `MenuType` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Label` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Parent` int NOT NULL,
  `Link` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `NewWindow` char(6) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `MenuOrder` int NOT NULL,
  PRIMARY KEY (`RecordID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `RED_C_Menu`
--

LOCK TABLES `RED_C_Menu` WRITE;
/*!40000 ALTER TABLE `RED_C_Menu` DISABLE KEYS */;
/*!40000 ALTER TABLE `RED_C_Menu` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `RED_Features`
--

DROP TABLE IF EXISTS `RED_Features`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `RED_Features` (
  `RecordID` int NOT NULL AUTO_INCREMENT,
  `UniqueName` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`RecordID`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `RED_Features`
--

LOCK TABLES `RED_Features` WRITE;
/*!40000 ALTER TABLE `RED_Features` DISABLE KEYS */;
INSERT INTO `RED_Features` (`RecordID`, `UniqueName`) VALUES (1,'slider'),(2,'kwicks');
/*!40000 ALTER TABLE `RED_Features` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `RED_Layouts`
--

DROP TABLE IF EXISTS `RED_Layouts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `RED_Layouts` (
  `UniqueName` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Positions` int NOT NULL COMMENT 'number of positions in the layout',
  `w_Pos1` varchar(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vw_Pos1` varchar(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'video width',
  `vh_Pos1` varchar(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'video height',
  `w_Pos2` varchar(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vw_Pos2` varchar(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'video width',
  `vh_Pos2` varchar(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'video height',
  `w_Pos3` varchar(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vw_Pos3` varchar(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'video width',
  `vh_Pos3` varchar(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'video height',
  `w_Pos4` varchar(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vw_Pos4` varchar(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'video width',
  `vh_Pos4` varchar(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'video height',
  `w_div_Pos1` varchar(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `w_div_Pos2` varchar(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `w_div_Pos3` varchar(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `w_div_Pos4` varchar(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`UniqueName`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `RED_Layouts`
--

LOCK TABLES `RED_Layouts` WRITE;
/*!40000 ALTER TABLE `RED_Layouts` DISABLE KEYS */;
INSERT INTO `RED_Layouts` (`UniqueName`, `Positions`, `w_Pos1`, `vw_Pos1`, `vh_Pos1`, `w_Pos2`, `vw_Pos2`, `vh_Pos2`, `w_Pos3`, `vw_Pos3`, `vh_Pos3`, `w_Pos4`, `vw_Pos4`, `vh_Pos4`, `w_div_Pos1`, `w_div_Pos2`, `w_div_Pos3`, `w_div_Pos4`) VALUES ('index',3,'415','','239','415','425','239','415','425','239','415','','239','1','1','1','1'),('index-1',3,'288','288','162','288','288','162','288','288','162','900','','506','1','1','1','1'),('index-2',4,'450','','506','900','','506','900','','506','900','','506','1','1','1','1'),('index-3',2,'196','','110','196','390','219','196','','110','196','','110','3','3','3','3');
/*!40000 ALTER TABLE `RED_Layouts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `RED_Menu`
--

DROP TABLE IF EXISTS `RED_Menu`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `RED_Menu` (
  `RecordID` int NOT NULL AUTO_INCREMENT,
  `RootOrder` varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Title` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Label` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Parent` int NOT NULL,
  `Link` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `NewWindow` char(6) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `MenuOrder` int NOT NULL,
  `Active` varchar(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Language` char(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`RecordID`),
  KEY `idx_red_menu_public_order` (`Language`,`Active`,`MenuOrder`)
) ENGINE=InnoDB AUTO_INCREMENT=68 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `RED_Menu`
--

LOCK TABLES `RED_Menu` WRITE;
/*!40000 ALTER TABLE `RED_Menu` DISABLE KEYS */;
INSERT INTO `RED_Menu` (`RecordID`, `RootOrder`, `Title`, `Label`, `Parent`, `Link`, `NewWindow`, `MenuOrder`, `Active`, `Language`) VALUES (1,'1','Top Navigation','Inicio',0,'/','',1,'Y','sp'),(67,'1','Top Navigation','Contacto',0,'/contacto/','',5,'Y','sp');
/*!40000 ALTER TABLE `RED_Menu` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `RED_Sections`
--

DROP TABLE IF EXISTS `RED_Sections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `RED_Sections` (
  `RecordID` int unsigned NOT NULL AUTO_INCREMENT,
  `Sections` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Title` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Layout` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `QueryLimit` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `AccessLevel` char(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Public',
  `Features` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'slider, wrapperblock, kwicks',
  `Active` varchar(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'N',
  `Description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Tags` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Language` char(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `CreationDate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`RecordID`),
  KEY `idx_red_sections_public_alias` (`Language`,`Active`,`Sections`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `RED_Sections`
--

LOCK TABLES `RED_Sections` WRITE;
/*!40000 ALTER TABLE `RED_Sections` DISABLE KEYS */;
INSERT INTO `RED_Sections` (`RecordID`, `Sections`, `Title`, `Layout`, `QueryLimit`, `AccessLevel`, `Features`, `Active`, `Description`, `Tags`, `Language`, `CreationDate`) VALUES (13,'home','Home','index-2','100','Public','slider','Y','','','sp','2014-01-07 21:48:39'),(24,'contacto','contacto','index-1','100','Public','','Y','','','sp','2015-02-27 03:05:53'),(25,'administracion','administracion','index-3','100','Public','','Y','','','sp','2015-02-28 01:11:16');
/*!40000 ALTER TABLE `RED_Sections` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `RED_Schema_Migrations`
--

DROP TABLE IF EXISTS `RED_Schema_Migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `RED_Schema_Migrations` (
  `Migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Checksum` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `AppliedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ExecutionMs` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`Migration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `RED_Schema_Migrations`
--

LOCK TABLES `RED_Schema_Migrations` WRITE;
/*!40000 ALTER TABLE `RED_Schema_Migrations` DISABLE KEYS */;
/*!40000 ALTER TABLE `RED_Schema_Migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `RED_SubCategories`
--

DROP TABLE IF EXISTS `RED_SubCategories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `RED_SubCategories` (
  `RecordID` int unsigned NOT NULL AUTO_INCREMENT,
  `CategoryRecordID` int unsigned DEFAULT NULL COMMENT 'Parent RED_Categories.RecordID',
  `SubCategories` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Title` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Layout` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `QueryLimit` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `AccessLevel` char(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Public',
  `Features` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'slider, wrapperblock, kwicks',
  `Active` varchar(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'N',
  `Description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Tags` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Language` char(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `CreationDate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`RecordID`),
  KEY `idx_red_subcategories_public_alias` (`Language`,`Active`,`SubCategories`),
  KEY `idx_red_subcategories_parent` (`CategoryRecordID`,`Language`,`Active`,`SubCategories`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

ALTER TABLE `RED_Categories`
  ADD CONSTRAINT `fk_red_categories_section`
  FOREIGN KEY (`SectionRecordID`) REFERENCES `RED_Sections` (`RecordID`)
  ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE `RED_SubCategories`
  ADD CONSTRAINT `fk_red_subcategories_category`
  FOREIGN KEY (`CategoryRecordID`) REFERENCES `RED_Categories` (`RecordID`)
  ON DELETE RESTRICT ON UPDATE RESTRICT;

--
-- Dumping data for table `RED_SubCategories`
--

LOCK TABLES `RED_SubCategories` WRITE;
/*!40000 ALTER TABLE `RED_SubCategories` DISABLE KEYS */;
/*!40000 ALTER TABLE `RED_SubCategories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `RED_Tools`
--

DROP TABLE IF EXISTS `RED_Tools`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `RED_Tools` (
  `RecordID` int NOT NULL AUTO_INCREMENT,
  `UniqueName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `CompGroup` char(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Content, Areas',
  `ButtonTag` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `AltContent` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Template` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`RecordID`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `RED_Tools`
--

LOCK TABLES `RED_Tools` WRITE;
/*!40000 ALTER TABLE `RED_Tools` DISABLE KEYS */;
INSERT INTO `RED_Tools` (`RecordID`, `UniqueName`, `CompGroup`, `ButtonTag`, `AltContent`, `Template`) VALUES (1,'MoveContent','Content','Move','Move Content between Sections, Categories and SubCategories',''),(2,'FilterAreas','Areas','Filter','Filter Content according to Sections, Categories and SubCategories','');
/*!40000 ALTER TABLE `RED_Tools` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed
