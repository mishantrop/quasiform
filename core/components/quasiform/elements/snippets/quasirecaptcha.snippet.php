<?php
$messageError = $modx->getOption('messageError', $scriptProperties, 'Ты робот');

$response = [
	'errors' => [],
	'success' => false,
	'message' => '',
	'messages' => [],
];

/**
 * Ключ
 */
$key = $modx->getOption('key', $scriptProperties, false);
/**
 * Секретный ключ
 */
$secret = $modx->getOption('secret', $scriptProperties, false);
/**
 * Режим отладки
 * Если включён, то в журнал ошибок записывается отладочная информация
 */
$debug = $modx->getOption('debug', $scriptProperties, false);

if (!function_exists('sendRecaptchaRequest')) {
	/**
	 * Функция отправки запроса на верификацию reCAPTCHA
	 */
	function sendRecaptchaRequest($secret, $response, $remoteip) {
		$ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
		curl_setopt($ch, CURLOPT_TIMEOUT, 4);
		curl_setopt($ch, CURLOPT_POSTFIELDS, [
			"secret"   => $secret,
			"response" => $response,
			"remoteip" => $remoteip,
		]);
		$data = curl_exec($ch);
		curl_close($ch);
		return $data;
	}
}

$googleResponse = sendRecaptchaRequest($secret, $_POST['g-recaptcha-response'], $_SERVER['REMOTE_ADDR']);
if ($debug) {
	$modx->log(modX::LOG_LEVEL_ERROR, 'google response: '.$googleResponse);
}
$googleResponse = $modx->fromJSON($googleResponse);
if ($debug) {
	$modx->log(modX::LOG_LEVEL_ERROR, 'google array response: '.print_r($googleResponse, true));
}
if (is_array($googleResponse) && $googleResponse['success']) {
	$response['success'] = true;
} else {
	$response['errors'][] = $messageError;
}

return $response;