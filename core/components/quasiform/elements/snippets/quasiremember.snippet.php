<?php
/**
 * Имя отправленного поля
 * Если передан этот параметр, то сниппет вернёт содержимое $_SESSION['quasiform']['key']
 */
$key = $modx->getOption('key', $scriptProperties, false);
/**
 * Режим отладки
 */
$debug = $modx->getOption('debug', $scriptProperties, false);

/**
 * Вернуть значение из сессии
 */
if (strlen($key)) {
    if (isset($_SESSION['quasiform'])) {
        if (isset($_SESSION['quasiform'][$key])) {
            return $_SESSION['quasiform'][$key];
        }
    }
    return '';
}

$response = [
	'errors' => [],
	'success' => false,
	'message' => '',
	'messages' => [],
	'placeholders' => $modx->getOption('placeholders', $scriptProperties, []),
];

/**
 * Занесение плейсхолдеров в сессию
 */
foreach ($response['placeholders'] as $placeholderKey => &$placeholderValue) {
    /* Пока можно заполнять только строковые значения плейсхолдеров */
    if (is_string($placeholderValue)) {
        $_SESSION['quasiform'][$placeholderKey] = $placeholderValue;
    }
}

$response['success'] = true;
return $response;