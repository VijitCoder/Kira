<?php
/**
 * Подключение скрипта конфига можно сделать более корректно, но будет сложнее
 * Сейчас подключается напрямую в App.php
 */

return array_merge(
    [
        'domain' => DEBUG ? 'waredom.loc' : 'waredom.ru',
        //@DEPRECATED ?
        'indexPage' => 'http://' . (DEBUG ? 'waredom.loc' : 'waredom.ru') . '/',

        'defaultController' => 'MainController',

        'minPass' => 5, //минимальная длина пароля
        'minComb' => 3, //минимальная комбинация наборов символов в пароле

        //ограничения на аватар
        'avatar' => [
            'minSize' => 200,   //размер картинки по узкой стороне
            'minWeight' => 200, //минимальный вес файла, кБ
            'maxWeight' => 2.5, //максимальный вес, Мб
            'format' => 'gif, jpg, png',
            'w' => 300,  //размеры создаваемой миниатюры
            'h' => 300,

        ],

         //Заапрещенные для регистрации mail-сервера (это сервисы 5-тиминутных ящиков)
        'blackServers' => [
            'fakeinbox.com','sharklasers.com','mailcker.com','yopmail.com','jnxjn.com',
            'jnxjn.com','0815.ru','10minutemail.com','agedmail.com','ano-mail.net','asdasd.ru',
            'brennendesreich.de','buffemail.com','bund.us','cool.fr.nf','courriel.fr.nf',
            'cust.in','dbunker.com','dingbone.com','discardmail.com','discardmail.de',
            'dispostable.com','duskmail.com','emaillime.com','flyspam.com','fudgerub.com',
            'guerrillamail.com','hulapla.de','jetable.fr.nf','jetable.org','lookugly.com',
            'mailcatch.com','mailspeed.ru','mega.zik.dj','meltmail.com','moncourrier.fr.nf',
            'monemail.fr.nf','monmail.fr.nf','noclickemail.com','nomail.xl.cx','nospam.ze.tc',
            'rtrtr.com','s0ny.net','smellfear.com','spambog.com','spambog.de','spambog.ru',
            'speed.1s.fr','superstachel.de','teewars.org','tempemail.net','tempinbox.com',
            'tittbit.in','yopmail.fr','yopmail.net','ypmail.webarnak.fr.eu.org','cuvox.de',
            'armyspy.com','dayrep.com','einrot.com','fleckens.hu','gustr.com','jourrapide.com',
            'rhyta.com','superrito.com','teleworm.us','trbvm.com','cherevatyy.ru',
            'my.dropmail.me','dropmail.me','10mail.org','yomail.info','excite.co.jp',
        ],

    ],

    require __DIR__ . '/secret.php'
);
