<?php
$modx->log(xPDO::LOG_LEVEL_ERROR, 'QUASIFORM START '.time());
/**
 * Параметры
 * @param debug Включён ли режим отладки
 * @param fields Поля в формате JSON
 * @param plugins Сниппеты в формате JSON, вызываемые во время исполнения текущего сниппета
 */
$fields = $modx->fromJSON($modx->getOption('fields', $scriptProperties));
$plugins = $modx->fromJSON($modx->getOption('plugins', $scriptProperties));
// $debug = $modx->getOption('debug', $scriptProperties);
// $debug = $debug === true || $debug === '1' || $debug === 1;

// Плейсхолдеры для передачи в шаблон письма
$placeholders = [];
$requestData = $_POST;

// Ответ в формате JSON, который вернётся в браузер
$response = [
	'author' => 'quasi-art.ru',
	'errors' => [],
	'placeholders' => [],
	'success' => false,
];

/**
 * Ассоциативный массив полей с правилами проверки
 * &fields=`[ { "label": "Имя", "required": true }, ... ]`
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	$modx->log(xPDO::LOG_LEVEL_ERROR, 'quasiForm | Invalid request method: '.$_SERVER['REQUEST_METHOD']);
	die('Use POST, Luke');
}

/**
 * Ассоциативный массив полей с правилами проверки
 * &fields=`[ { "label": "Имя", "required": true }, ... ]`
 */
if (!is_array($fields)) {
	$modx->log(xPDO::LOG_LEVEL_ERROR, 'quasiForm | fields parameter is not an array; type: '.gettype($fields));
}

// Модификация данных
foreach ($fields as $field) {
	$fieldName = $field['name'];
	$fieldModifiers = isset($field['modifiers']) ? $field['modifiers'] : [];
	$fieldValue = $requestData[$fieldName];
	$fieldLabel = isset($field['label']) ? $field['label'] : $fieldName;

	foreach ($fieldModifiers as $modifier) {
		if (function_exists($modifier)) {
			$requestData[$fieldName] = call_user_func($modifier, $fieldValue);
		} else {
			$modx->log(xPDO::LOG_LEVEL_ERROR, 'quasiForm | Modifier function does not exists: '.$modifier);
		}
	}
}
$modx->log(xPDO::LOG_LEVEL_ERROR, print_r($requestData, true));

// Валидация данных
foreach ($fields as $field) {
	$fieldName = $field['name'];
	$fieldValidators = isset($field['validators']) ? $field['validators'] : [];
	$fieldValue = $requestData[$fieldName];
	$fieldLabel = isset($field['label']) ? $field['label'] : $fieldName;

	foreach ($fieldValidators as $validatorName => $validatorOptions) {
		switch ($validatorName) {
			// "Обезличенные" валидаторы
			// Значение поля должно быть равно какому-то определённому значению
			case '_equals':
				if ($fieldValue != $validatorOptions) {
					$response['errors'][] = [
						'description' => 'Ошибка заполнения формы',
					];
				}

				break;
			case '_length':
				if (strlen($fieldValue) != $validatorOptions) {
						$response['errors'][] = [
							'description' => 'Ошибка заполнения формы',
						];
				}
				break;

			// Валидаторы
			case 'email':
					/**
					 * Поле должно быть адресом электроной почты
					 * Пустое значение не проверяется (можно комбинировать с required)
					 */
				if (!filter_var($fieldValue, FILTER_VALIDATE_EMAIL)) {
					$response['errors'][] = [
						'description' => 'Поле должно быть адресом электронной почты',
						'field' => $fieldName,
					];
				}
				break;
			case 'minLength':
				if (strlen($fieldValue) < $validatorOptions) {
					$response['errors'][] = [
						'description' => 'Минимум '.$validatorOptions.' символов',
						'field' => $fieldName,
					];
				}
				break;
			case 'maxLength':
				if (strlen($fieldValue) > $validatorOptions) {
					$response['errors'][] = [
						'description' => 'Максимум '.$validatorOptions.' символов',
						'field' => $fieldName,
					];
				}
				break;
			case 'required':
				if (!isset($requestData[$fieldName])) {
					$response['errors'][$fieldName][] = [
						'description' => 'Поле обязательно для заполнения',
						'field' => $fieldName,
					];
				}
				break;
			default:
				$modx->log(xPDO::LOG_LEVEL_ERROR, 'quasiForm | Undefined validator: '.$validatorName);
				break;
		}
	}
}

// Если поля не прошли валидацию, то происходит выход из сниппета
if (count($response['errors']) > 0) {
  http_response_code(400);
	return json_encode($response, JSON_UNESCAPED_UNICODE);
}

// Установка плейсхолдеров для передачи в шаблон письма
// Использование: Имя: [[+field.name:strip_tags:ellipsis=`1024`]]<br/>
foreach ($fields as $field) {
	$fieldName = $field['name'];
	$placeholders['field.'.$fieldName] = $requestData[$fieldName];
}

/**
	* Список сниппетов, которые должны выполниться после валидации полей
	* &hooks=`&plugins=[{ "name": "foo", "options": { "bar": true } }]`
	*/
if (!is_array($plugins)) {
	$modx->log(xPDO::LOG_LEVEL_ERROR, 'quasiForm | Plugins must be an array of objects, current type: '.gettype($fields));
}
foreach ($plugins as $plugin) {
	$pluginName = $plugin['name'];
	$pluginOptions = $plugin['options'];

	// В плагине quasiEmail (и других) понадобятся плейсхолдеры с именами и значениями полей формы
	if (!is_array($pluginOptions)) {
		$pluginOptions = [];
	}
	$pluginOptions['placeholders'] = $placeholders;

	// Вызов плагина-сниппета
	// $modx->log(xPDO::LOG_LEVEL_ERROR, 'quasiForm | Running Snippet '.$pluginName);
	// $modx->log(xPDO::LOG_LEVEL_ERROR, 'quasiForm | Options '.print_r($pluginOptions, true));
	$pluginResponse = $modx->runSnippet($pluginName, $pluginOptions);

	$response['errors'] = array_merge($response['errors'], $pluginResponse['errors']);
	$placeholders = array_merge($placeholders, $pluginResponse['placeholders']);

	// Если плагин-сниппет завершился ошибкой, прекращается выполнение последующих плагинов
	if (!$pluginResponse['success']) {
		break;
	}
}

$response['success'] = count($response['errors']) === 0;

return json_encode($response, JSON_UNESCAPED_UNICODE);
