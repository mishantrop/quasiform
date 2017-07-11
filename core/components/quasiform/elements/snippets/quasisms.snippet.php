<?php
/**
 * Режим отладки
 * Сообщения складываются только в каталог /sms/%H%i%s.txt
 */
$debug = $modx->getOption('debug', $scriptProperties, false);

$response = [
	'success' => false,
	'errors' => [],
	'messages' => $modx->getOption('messages', $scriptProperties, []),
	'placeholders' => $modx->getOption('placeholders', $scriptProperties, []),
];
/**
 * API-ключ
 */
$key = $modx->getOption('key', $scriptProperties, false);
/**
 * Номер получателя
 */
$to = $modx->getOption('to', $scriptProperties, false);
/**
 * Текст сообщения
 */
$text = $modx->getOption('text', $scriptProperties, false);
/**
 * Запрос, отправляемый сервису по отправке смс
 */
$query = 'http://sms.ru/sms/send?api_id='.$key.'&to='.$to.'&text='.$text;

if (!function_exists('sendSms')) {
	function sendSms($key, $to, $text, $debug = false) {
		if ($debug) {
			return (bool)file_put_contents($_SERVER['DOCUMENT_ROOT'].'/sms/'.$to.'.txt', $text);
		} else {
			$ch = curl_init('http://sms.ru/sms/send');
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
			curl_setopt($ch, CURLOPT_TIMEOUT, 30);
			curl_setopt($ch, CURLOPT_POSTFIELDS, [
				"api_id"		=>	$key,
				"to"			=>	$to,
				//"text"		=>	iconv("windows-1251', 'utf-8', $text")
				"text"			=>	$text,
			]);
			$data = curl_exec($ch);
			file_put_contents($_SERVER['DOCUMENT_ROOT'].'/sms/'.$to.'_answer.txt', $data);
			curl_close($ch);
			return true;
		}
	}
}

if (empty($key)) {
	$response['errors'][] = 'Ошибка авторизации';
}
if (empty($to)) {
	$response['errors'][] = 'Пустой номер телефона';
}
if (empty($text)) {
	$response['errors'][] = 'Пустое сообщение';
}
if (strlen($text) > 70) {
	$response['errors'][] = 'Максимальная длина сообщения — 70 символов';
}

if (!count($response['errors'])) {
	if (sendSms($key, $to, $text, $debug)) {
	  	$response['success'] = true;
	} else {
	  	$response['errors'][] = 'Не удалось отправить смс';
	}
} else {

}

return $response;
