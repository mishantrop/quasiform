<?php
$debug = $modx->getOption('debug', $scriptProperties, false);

$response = [
	'errors' => [],
	'placeholders' => [],
	'success' => false,
];

try {
	// Шаблон письма в формате HTML
	$emailTpl = $modx->getOption('emailTpl', $scriptProperties);
	// Шаблон текстового письма
	$emailTextTpl = $modx->getOption('emailTextTpl', $scriptProperties);
	// Адреса получателей через запятую
	$recipientEmails = $modx->getOption('recipientEmails', $scriptProperties, $modx->getOption('emailsender'));
	$recipientEmailsIsOptional = $modx->getOption('recipientEmailsIsOptional', $scriptProperties);
	// Тема письма
	$subject = $modx->getOption('subject', $scriptProperties);
	// Адрес электронной почты отправителя
	$senderName = $modx->getOption('senderName', $scriptProperties, $modx->getOption('emailsender'));
	// Имя отправителя
	$senderEmail = $modx->getOption('senderEmail', $scriptProperties, $modx->getOption('site_name'));
	// Плейсхолдеры для передачи в шаблон письма
	$placeholders = $modx->getOption('placeholders', $scriptProperties, []);
	$placeholders['subject'] = $subject;

	// Информация о сервере
	if (isset($_SERVER) && is_array($_SERVER)) {
		$placeholders['quasiform']['serverArray'] = print_r($_SERVER, true);
	}

	// Валидация и обработка адресов получателей
	$recipientEmails = explode(',', $recipientEmails);
	foreach ($recipientEmails as $index => $email) {
		$recipientEmails[$index] = trim($email);
	}
	foreach ($recipientEmails as $email) {
		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			$modx->log(modX::LOG_LEVEL_ERROR, 'quasiEmail: некорректный адрес получателя: '.$email);
			if ($recipientEmailsIsOptional) {
				$response['success'] = true;
			}
			return $response;
		}
	}

	// Валидация и обработка адреса отправителя
	if (!is_string($senderEmail) || !filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
		$modx->log(modX::LOG_LEVEL_ERROR, 'quasiEmail: невалидный адрес отправителя');
		return $response;
	}

	// Отправка основного письма
	if (!is_string($emailTpl)) {
		$modx->log(modX::LOG_LEVEL_ERROR, 'quasiEmail: невалидный параметр emailTpl');
		return $response;
	}

	$messageHtml = $modx->getChunk($emailTpl, $placeholders);
	$modx->getService('mail', 'mail.modPHPMailer');
	$modx->mail->setHTML(true);
	$modx->mail->set(modMail::MAIL_BODY, $messageHtml);
	$modx->mail->set(modMail::MAIL_CHARSET, 'utf-8');
	$modx->mail->set(modMail::MAIL_FROM, $senderEmail);
	$modx->mail->set(modMail::MAIL_FROM_NAME, $senderName);
	$modx->mail->set(modMail::MAIL_SUBJECT, $subject);
	$modx->mail->set(modMail::MAIL_ENCODING, '8bit');

	if (!empty($emailTextTpl)) {
		$messageText = $modx->getChunk($emailTextTpl, $placeholders);
		$modx->mail->set(modMail::MAIL_BODY_TEXT, $messageText);
	}

	foreach ($recipientEmails as $email) {
		$modx->mail->address('to', $email);
	}
	$modx->mail->address('reply-to', $senderEmail);

	if ($modx->mail->send()) {
		$response['success'] = true;
	} else {
		// if ($modx->mail->hasError()) {
			// $modx->log(modX::LOG_LEVEL_ERROR, 'quasiEmail | Не удалось отправить письмо: '.$modx->mail->getError()->messsage);
		// }
	}
} catch (Exception $e) {
	$modx->log(modX::LOG_LEVEL_ERROR, 'quasiEmail | Поймано исключение: '.$e->getMessage());
}

$modx->mail->reset();

return $response;
