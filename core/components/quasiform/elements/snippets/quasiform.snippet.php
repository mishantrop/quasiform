<?php
/**
 * Параметры
 * @param fields Поля в формате JSON
 * @param hooks Сниппеты в формате JSON, вызываемые во время исполнения текущего сниппета 
 */
$fields = $modx->fromJSON($modx->getOption('fields', $scriptProperties, false));
$messageSuccess = $modx->getOption('messageSuccess', $scriptProperties, 'Ваше сообщение успешно отправлено. Спасибо.');
$messageError = $modx->getOption('messageError', $scriptProperties, 'Форма заполнена с ошибками. Исправьте их и отправьте снова.');
$hooks = $modx->fromJSON($modx->getOption('hooks', $scriptProperties, []));
$debug = $modx->getOption('debug', $scriptProperties, false);

$placeholders = [];

$post = $_POST;
/**
 * Ответ, возвращаемый в формате JSON
 */
$response = [
	'author' => 'Михаил Серышев — quasi-art.ru',
	'errors' => [],
	'field_errors' => [],
	'messages' => [],
	'success' => false,
];
$errorsHTML = '';

/**
 * Массив имён полей с правилами проверки
 */
if (is_array($fields)) {
	foreach ($fields as $fieldName => &$fieldProperties) {
		if ($debug) {
			$response['debug'][] = 'field: '.$fieldName;
		}
		/**
		 * Перебор правил проверки одного поля
		 */
		$fieldLabel = (isset($fieldProperties['label'])) ? $fieldProperties['label'] : '';
		$fieldValue = (isset($post[$fieldName])) ? $post[$fieldName] : '';
		foreach ($fieldProperties as $fieldPropertyName => &$fieldPropertyValue) {
			if ($debug) {
				$response['debug'][] = 'fieldProperty: '.$fieldPropertyValue;
			}
			switch ($fieldPropertyName) {
				/**
				 * Валидаторы
				 */
				case 'blank':
				    /**
				     * Поле должно быть пустым
				     */
					if (!empty($fieldValue)) {
						$response['errors'][] = 'Ошибка заполнения формы';
					}
					break;
				case 'email':
				    /**
				     * Поле должно быть адресом электроной почты
				     * Пустое значение не проверяется (можно комбинировать с required)
				     */
					if (!empty($fieldValue)) {
						if (!filter_var($fieldValue, FILTER_VALIDATE_EMAIL)) {
							$response['field_errors'][$fieldName][] = 'Поле «'.$fieldLabel.'» должно быть адресом электронной почты';
						}
					}
					break;
				/**
				 * Значение поля должно быть равно какому-то определённому значению
				 * "equal":"2015"
				 */
				case 'equal':
				case 'equals':
					if ($fieldValue != $fieldPropertyValue) {
						$response['errors'][] = 'Ошибка заполнения формы';
					}

					break;
				case 'length':
					if (strlen($fieldValue) != strlen($fieldPropertyValue)) {
					  	$response['field_errors'][$fieldName][] = 'Количество символов для поля «'.$fieldLabel.'» должно быть равно '.$fieldPropertyValue;
					}
					break;
				case 'minlength':
					if (strlen($fieldValue) < $fieldPropertyValue) {
						$response['field_errors'][$fieldName][] = 'Количество символов для поля «'.$fieldLabel.'» ('.strlen($fieldValue).') должно быть не менее '.$fieldPropertyValue;
					}
					break;
				case 'maxlength':
					if (strlen($fieldValue) > $fieldPropertyValue) {
						$response['field_errors'][$fieldName][] = 'Превышено количество символов для поля «'.$fieldLabel.'»: '.strlen($fieldValue).'/'.$fieldPropertyValue;
					}
					break;
				case 'required':
					if (empty($fieldValue) && $fieldPropertyValue) {
						$response['field_errors'][$fieldName][] = 'Поле «'.$fieldLabel.'» обязательно для заполнения';
					}
					break;
				/**
				 * Модификаторы
				 */
				case 'strip_tags':
					$fieldValue = ($fieldPropertyValue) ? strip_tags($fieldValue) : $fieldValue;
					break;
				case 'trim':
					$fieldValue = ($fieldPropertyValue) ? trim($fieldValue) : $fieldValue;
					break;
				case 'htmlentities':
					$fieldValue = ($fieldPropertyValue) ? htmlentities($fieldValue) : $fieldValue;
					break;
				default:
					break;
			}
		}
	
		/**
		 * Установка плейсхолдеров для передачи в шаблон письма
		 */
		$placeholders[$fieldName] = $fieldValue;
	}
} else {
	if ($debug) {
		$response['debug'][] = 'fields is not an array ('.gettype($fields).')';
	}
}

// Если поля прошли валидацию, то вызываются плагины-сниппеты
if (!count($response['errors']) && !count($response['field_errors'])) {
	// Список сниппетов, которые должны выполниться после валидации полей
	if ($debug) {
		$response['hooks'] = $hooks;
	}
	if (is_array($hooks)) {
		foreach ($hooks as $hook) {
			/**
			 * Параметры для передачи в плагин-сниппет
			 */
			$hookProperties = $hook['options'];
			$hookName = $hook['name'];
			if (is_array($hookProperties)) {
				$properties = array_merge($hookProperties, ['placeholders' => $placeholders]);
			} else {
				$properties = ['placeholders' => $placeholders];
			}
			if ($debug) {
				$response['properties'][] = $properties;
			}
			/**
			 * Вызов плагина-сниппета
			 */
			$hookResponse = $modx->runSnippet($hookName, $properties);
			if ($debug) {
				$modx->log(xPDO::LOG_LEVEL_ERROR, 'run snippet '.$hookName);
				$modx->log(xPDO::LOG_LEVEL_ERROR, print_r($properties, true));
			}
			
			if (is_array($hookResponse)) {
				if (is_array($hookResponse['errors'])) {
					foreach ($hookResponse['errors'] as &$responseError) {
						$response['errors'][] = $responseError;
					}
				}
				if (is_array($hookResponse['messages'])) {
					foreach ($hookResponse['messages'] as &$responseMessage) {
						$response['messages'][] = $responseMessage;
					}
				}
				if (array_key_exists('placeholders', $hookResponse) && is_array($hookResponse['placeholders'])) {
					foreach ($hookResponse['placeholders'] as $placeholderName => &$placeholderValue) {
						$placeholders[$placeholderName] = $placeholderValue;
					}
				}
				if ($debug) {
					$modx->log(xPDO::LOG_LEVEL_ERROR, 'Hook response:');
					$modx->log(xPDO::LOG_LEVEL_ERROR, print_r($hookResponse, true));
				}
				/**
				 * Если плагин-сниппет завершился ошибкой, прекращается выполнение последующих плагинов
				 */
				if (!$hookResponse['success']) {
					if ($debug) {
						$response['debug'][] = $hookName.' is fail';
					}
					break;
				}
			} else {
				if ($debug) {
					$response['debug'][] = $hookName.' is failed';
					$response[$hookName]['response'] = $hookResponse;
				}
				break;
			}
	
		}
	} else {
		$response['errors'][] = 'Неверный формат вызова плагинов quasiForm';
	}
}

/**
 * Если плагины тоже выполнены успешно
 */
if (!count($response['errors']) && !count($response['field_errors'])) {
	$response['success'] = true;
}

if ($response['success']) {
	$response['messages'][] = $messageSuccess;
} else {
	$response['errors'][] = $messageError;
}

// Результат работы скрипта в JSON-формате
return json_encode($response, JSON_UNESCAPED_UNICODE);
