<?php
$debug = $modx->getOption('debug', $scriptProperties, false);

$response = [
	'errors' => [],
	'messages' => $modx->getOption('messages', $scriptProperties, []),
	'placeholders' => $modx->getOption('placeholders', $scriptProperties, []),
	'success' => false,
];

/**
 * Шаблон письма в формате HTML
 */
$emailTpl = $modx->getOption('emailTpl', $scriptProperties, false);
/**
 * Шаблон текстового письма
 */
$emailTextTpl = $modx->getOption('emailTextTpl', $scriptProperties, false);
/**
 * Шаблон письма, отправляемого тому, кто отправил форму
 */
$emailTplFeedback = $modx->getOption('emailTplFeedback', $scriptProperties, false);
/**
 * Адреса получателей через запятую
 */
$emailTo = $modx->getOption('emailTo', $scriptProperties, $modx->getOption('emailsender'));
/**
 * Адреса получателей через запятую
 */
$emailToFeedback = $modx->getOption('email', $_POST, '');
/**
 * Тема письма
 */
$emailSubject = $modx->getOption('emailSubject', $scriptProperties, false);
/**
 * Тема письма, отправляемого тому, кто отправил форму
 */
$emailSubjectFeedback = $modx->getOption('emailSubjectFeedback', $scriptProperties, $emailSubject);
/**
 * Адрес электронной почты отправителя
 */
$emailSenderEmail = $modx->getOption('emailSenderEmail', $scriptProperties, $modx->getOption('emailsender'));
/**
 * Имя отправителя
 */
$emailSenderName = $modx->getOption('emailSenderName', $scriptProperties, $modx->getOption('site_name'));
/**
 * Плейсхолдеры для передачи в шаблон письма
 */
$placeholders = $modx->getOption('placeholders', $scriptProperties, []);
$placeholders['subject'] = $emailSubject;

/**
 * Информация о сервере
 */
$placeholderServer = [];
if (isset($_SERVER) && is_array($_SERVER)) {
    foreach ($_SERVER as $key => $value) {
        if (is_string($value)) {
            $placeholderServer[$key] = $value;
        }
    }
}
$placeholders['quasiform']['server'] = $placeholderServer;
$placeholders['quasiform']['serverArray'] = print_r($placeholderServer, true);

/**
 * Если пустой адрес основного получателя
 */
if (empty($emailTo)) {
	if ($debug) {
		$modx->log(modX::LOG_LEVEL_ERROR, 'quasiEmail: пустой адрес основного получателя');
	}
	$response['errors'][] = 'Пустой адрес основного получателя';
	return $response;
}
/**
 * Если пустой адрес основного отправителя
 */
if (empty($emailSenderEmail)) {
	if ($debug) {
		$modx->log(modX::LOG_LEVEL_ERROR, 'quasiEmail: пустой адрес основного отправителя');
	}
	$response['errors'][] = 'Пустой адрес основного отправителя';
	return $response;
}



/**
 * Если нет ошибок, то попытка отправки письмо
 */
if (!count($response['errors'])) {
	/**
	 * Отправка основного письма
	 */
	$messageHtml = (!empty($emailTpl)) ? $modx->getChunk($emailTpl, $placeholders) : '';
	$messageText = (!empty($emailTextTpl)) ? $modx->getChunk($emailTextTpl, $placeholders) : '';
    $modx->getService('mail', 'mail.modPHPMailer');
    $modx->mail->setHTML(true);
    $modx->mail->set(modMail::MAIL_BODY, $messageHtml);
    if (!empty($messageText)) {
    	$modx->mail->set(modMail::MAIL_BODY_TEXT, $messageText);
    }
    $modx->mail->set(modMail::MAIL_CHARSET, 'utf-8');
    $modx->mail->set(modMail::MAIL_FROM, $emailSenderEmail);
    $modx->mail->set(modMail::MAIL_FROM_NAME, $emailSenderName);
    $modx->mail->set(modMail::MAIL_SUBJECT, $emailSubject);
    $modx->mail->set(modMail::MAIL_ENCODING, '8bit');
    $emails = explode(',', $emailTo);
    if (is_array($emails)) {
	    foreach ($emails as $email) {
	    	$email = trim($email);
	    	if (!empty($email)) {
	    		$modx->mail->address('to', $email);
	    	}
	    }
    }
    $modx->mail->address('reply-to', $emailSenderEmail);
    if ($modx->mail->send()) {
        $response['success'] = true;
		$response['messages'][] = 'Сообщение успешно отправлено';
		/**
		 * Если основное письмо отправлено успешно, то отправляется дополнительное — отправившему форму
		 */
    } else {
 		if ($debug) {
			$modx->log(modX::LOG_LEVEL_ERROR, 'quasiEmail: не удалось отправить основное письмо');
		}
		$response['errors'][] = 'Ошибка при отправке письма';
    }
}

return $response;