--
-- Структура таблицы `user`
--

CREATE TABLE `user` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'id юзера',
  `login` char(30) NOT NULL COMMENT 'логин',
  `mail` char(50) NOT NULL COMMENT 'мыло',
  `password` char(32) NOT NULL COMMENT 'md5-хеш пароля\n',
  `salt` char(10) NOT NULL COMMENT 'соль',
  `status` enum('new','active','banned') NOT NULL DEFAULT 'new' COMMENT 'статус',
  `regdate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'дата регистрации',
  PRIMARY KEY (`id`),
  UNIQUE KEY `login` (`login`),
  UNIQUE KEY `mail` (`mail`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='Учетки юзеров' AUTO_INCREMENT=1;

-- --------------------------------------------------------

--
-- Структура таблицы `user_profile`
--

CREATE TABLE `user_profile` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'id юзера',
  `firstname` varchar(50) DEFAULT NULL COMMENT 'Имя',
  `secondname` varchar(50) DEFAULT NULL COMMENT 'Фамилия',
  `sex` enum('none','male','female') DEFAULT NULL COMMENT 'пол',
  `birth_date` date DEFAULT NULL COMMENT 'дата рождения',
  `town` varchar(100) DEFAULT NULL COMMENT 'город проживания',
  `avatar` varchar(50) DEFAULT NULL COMMENT 'ссылка на аватар',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='Профили юзеров' AUTO_INCREMENT=1;


-- В `user_profile` индексов нет, т.к. по задаче они не требуются. Неизвестно, какие именно поля индексировать.