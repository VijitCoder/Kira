<?php
/**
 * Интеграционный тест пошаговой загрузки конфигурации.
 *
 * Последний файл из группы основных. У него зависимость от первого файла группы.
 */
use kira\configuration\ConfigManager;

return [
    'wrapper' => [
        'SCP' => 'report-uri ' . ConfigManager::getInstance()->getValue('wrapper.domain'),
    ],
];
