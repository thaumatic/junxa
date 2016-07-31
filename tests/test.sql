DROP TABLE IF EXISTS `category`;
CREATE TABLE `category` (
    `id` mediumint unsigned not null auto_increment,
    `name` varchar(250) not null,
    `type` enum('A''s', 'B''s', 'C''s'),
    `active` bool not null default 1,
    `created_at` datetime not null,
    `changed_at` timestamp not null,
    PRIMARY KEY (`id`),
    UNIQUE KEY `name` (`name`),
    KEY `active` (`active`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `item`;
CREATE TABLE `item` (
    `id` mediumint unsigned not null auto_increment,
    `category_id` mediumint unsigned not null,
    `name` varchar(250) not null,
    `active` bool not null default 1,
    `created_at` datetime not null,
    `changed_at` timestamp not null,
    PRIMARY KEY (`id`),
    UNIQUE KEY (`name`),
    FOREIGN KEY `category_id` (`category_id`) REFERENCES `category` (`id`),
    KEY `category_id_by_active` (`category_id`, `active`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;
