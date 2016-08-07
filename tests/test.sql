DROP TABLE IF EXISTS `category`;
CREATE TABLE `category` (
    `id` mediumint unsigned not null auto_increment,
    `name` varchar(250) not null,
    `type` enum('A''s', 'B''s', 'C''s'),
    `active` bool not null default 1,
    `createdAt` datetime not null,
    `changedAt` timestamp not null,
    PRIMARY KEY (`id`),
    UNIQUE KEY `name` (`name`),
    KEY `active` (`active`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `item`;
CREATE TABLE `item` (
    `id` mediumint unsigned not null auto_increment,
    `categoryId` mediumint unsigned not null,
    `name` varchar(250) not null,
    `price` decimal(10,2),
    `active` bool not null default 1,
    `createdAt` datetime not null,
    `changedAt` timestamp not null,
    PRIMARY KEY (`id`),
    UNIQUE KEY (`name`),
    FOREIGN KEY `categoryId` (`categoryId`) REFERENCES `category` (`id`),
    KEY `categoryId_by_active` (`categoryId`, `active`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;
