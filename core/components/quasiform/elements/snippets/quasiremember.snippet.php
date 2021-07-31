<?php
/**
 * Имя отправленного поля
 * Если передан этот параметр, то сниппет вернёт содержимое $_SESSION['quasiform']['key']
 */
$key = $modx->getOption('key', $scriptProperties);

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
	'placeholders' => [],
	'success' => false,
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