<?php
/**
 * @param string $input Значение атрибута поля выбора файла
 * @param string $dir Каталог загрузки файлов
 * @param string $types Список расширений через запятую
 * @param string $size Максимальный размер каждого файла
 * @param string $mincount Минимальное количество файлов
 * @param string $maxcount Максимальное количество файлов
 * @param string $translit Нужно ли модифицировать имена файлов
 */
$input = $modx->getOption('field', $scriptProperties, false);
$dir = $modx->getOption('dir', $scriptProperties, false);
$types = $modx->getOption('types', $scriptProperties, false);
$size = $modx->getOption('maxsize', $scriptProperties, false);
$mincount = $modx->getOption('mincount', $scriptProperties, 0);
$maxcount = $modx->getOption('maxcount', $scriptProperties, NULL);
$translit = $modx->getOption('translit', $scriptProperties, false);
$debug = $modx->getOption('debug', $scriptProperties, false);

if ($debug) {
	ini_set('display_errors', 1);
	error_reporting(E_ALL);
	$modx->log(modX::LOG_LEVEL_ERROR, print_r($scriptProperties, true));
}

/**
 * Ответ сниппета
 */
$response = [
	'errors' => [],
	'success' => false,
	'files' => [],
	'messages' => [],
	'placeholders' => [],
];

if (!function_exists('getFileUploadErrorDescription')) {
	function getFileUploadErrorDescription($code = 0) {
		$output = '';
		
		switch ($code) {
		    case 1:
		        $output = 'Размер принятого файла превысил максимально допустимый размер, который задан директивой upload_max_filesize конфигурационного файла php.ini';
		        break;
		    case 2:
		        $output = 'Размер загружаемого файла превысил значение MAX_FILE_SIZE, указанное в HTML-форме';
		        break;
		    case 3:
		        $output = 'Загружаемый файл был получен только частично.';
		        break;
		    case 4:
		        $output = 'Файл не был загружен.';
		        break;
		    case 6:
		        $output = 'Отсутствует временная папка.';
		        break;
		    case 7:
		        $output = 'Не удалось записать файл на диск.';
		        break;
		    case 8:
		        $output = 'PHP-расширение остановило загрузку файла.';
		        break;
		}
		
		return $output;
	}
}

if (!function_exists('getFileExtension')) {
	/**
	 * Получение расширения файла
	 * @param string $filename Имя файла
	 * @param boolean $strtolower Привести символы к строчным
	 * @return string Расширение файла
	 */
	function getFileExtension($filename, $strtolower = true) {
		if (!is_string($filename)) {
			return '';
		}
		if ($strtolower) {
			$filename = strtolower($filename);
		}
		$filename = explode('.', $filename);
		return (is_array($filename) && count($filename) > 1) ? end($filename) : '';
	}
}

if (!function_exists('genFilename')) {
	/**
	 * Генерация имени файла
	 * @return string Имя файла без расширения
	 */
	function genFilename() {
		// Строка вида 1234567890_nu6tFrgh
		return time().'_'.genPassword();
	}
}

if (!function_exists('genPassword')) {
	/**
	 * Генерация последовательности случайных символов определённой длины
	 * @param integer $length Длина строки
	 * @return string $result Созданная строка
	 */
	function genPassword($length = 8) {
		$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		$count = mb_strlen($chars);
		for ($i = 0, $result = ''; $i < $length; $i++) {
			$index = rand(0, $count - 1);
			$result .= mb_substr($chars, $index, 1);
		}
		return $result;
	}
}

$filesOriginal = $_FILES[$input];
if ($debug) {
	$modx->log(modX::LOG_LEVEL_ERROR, 'Загрузка файлов: '.print_r($_FILES[$input], true));
}

/**
 * Создание более удобного массива загруженных файлов
 */
for ($i = 0; $i < count($filesOriginal['name']); $i++) {
    $name = $filesOriginal['name'][$i];
	$type = $filesOriginal['type'][$i];
    $tmp_name = $filesOriginal['tmp_name'][$i];
	$error = $filesOriginal['error'][$i];
	$size = $filesOriginal['size'][$i];
	
	/**
	 * Если ни один файл не прикреплён
	 */
	$modx->log(modX::LOG_LEVEL_ERROR, 'quasiUpload['.$i.']: count = '.count($filesOriginal['name']).'; name: '.$name.' type: '.$type);
	if (count($filesOriginal['name']) == 1 && empty($name) && empty($type) && empty($tmp_name) && $error == 4 && $size == 0) {
	    $modx->log(modX::LOG_LEVEL_ERROR, 'quasiUpload['.$i.'] continue');
	    continue;
	}
	
    $array = [
		'name' => $filesOriginal['name'][$i],
		'type' => $filesOriginal['type'][$i],
		'tmp_name' => $filesOriginal['tmp_name'][$i],
		'error' => $filesOriginal['error'][$i],
		'size' => $filesOriginal['size'][$i],
	];
	$response['files'][] = $array;
}
/**
 * Если указано максимальное количество файлов, то количество загружаемых файлов не должно
 * превышать указанное ограничение
 */
if ($maxcount > 0 && count($response['files']) > $maxcount) {
	$response['errors'][] = 'Превышен лимит количества файлов (отправлено '.count($response['files']).')';
	if ($debug) {
		$modx->log(modX::LOG_LEVEL_ERROR, 'Превышен лимит количества файлов (отправлено '.count($response['files']).')');
	}
	return $response;
}
/**
 * Если указано минимальное количество файлов, то количество загружаемых файлов не должно
 * быть меньше указанного ограничения
 */
if (count($response['files']) < $mincount) {
	$response['errors'][] = 'Не загружено необходимое количество файлов (отправлено '.count($response['files']).')';
	if ($debug) {
		$modx->log(modX::LOG_LEVEL_ERROR, 'Не загружено необходимое количество файлов (отправлено '.count($response['files']).')');
	}
	return $response;
}

$ds = DIRECTORY_SEPARATOR;
/* Место сохранения */
$targetPath = $_SERVER['DOCUMENT_ROOT'].$ds.$dir.$ds;
$targetPath = str_replace($ds.$ds, $ds, $targetPath);
/**
 * URL каталога, содержащего файлы
 */
$dirUrl = str_replace($_SERVER['DOCUMENT_ROOT'], 'http://'.$_SERVER['HTTP_HOST'], $targetPath);
if (!is_dir($targetPath)) {
	mkdir($targetPath, 777, true);
}
if (!is_dir($targetPath)) {
	$response['errors'][] = 'Неверный каталог загрузки';
	if ($debug) {
		$modx->log(modX::LOG_LEVEL_ERROR, 'Неверный каталог загрузки');
	}
	return $response;
}

/**
 * Проверка массива файлов
 */
foreach ($response['files'] as $k => &$file) {
	if ($file['error']) {
		$response['errors'][] = 'Ошибка при загрузке файла '.$file['name'].': '.getFileUploadErrorDescription($file['error']);
		if ($debug) {
			$modx->log(modX::LOG_LEVEL_ERROR, 'Ошибка при загрузке файла '.$file['name']);
		}
		return $response;
	}
	if ($file['size'] > $size) {
		$response['errors'][] = 'Файл '.$file['name'].' слишком большой';
		if ($debug) {
			$modx->log(modX::LOG_LEVEL_ERROR, 'Файл '.$file['name'].' слишком большой');
		}
		return $response;
	}
	/**
	 * Расширение файла
	 */
	$extension = getFileExtension($file['name']);
	/**
	 * Массив разрешённых расширений файлов
	 */
	$allowedExtensions = explode(',', $types);
	/**
	 * Разрешён ли файл данного типа к загрузке
	 */
	if (!in_array($extension, $allowedExtensions)) {
		$response['errors'][] = 'Файл '.$file['name'].' (расширение '.$extension.') запрещён к загрузке';
		if ($debug) {
			$modx->log(modX::LOG_LEVEL_ERROR, 'Файл '.$file['name'].' запрещён к загрузке');
		}
		return $response;
	}
}

/**
 * Перемещение файлов
 */
$fileUrls = [];
foreach ($response['files'] as $k => &$file) {
	// Временное имя файла
	$tempFile = $file['tmp_name'];
	// Полный адрес файла
	$extension = getFileExtension($file['name']);
	$filename = genFilename().'.'.$extension;
	$file['filename'] = $filename;
	$targetFile =  $targetPath.$filename;

	/* Если не удалось переместить файл */
	if (!move_uploaded_file($tempFile, $targetFile)) {
		$response['errors'][] = 'Не удалось загрузить файл '.$file['name'];
		if (!is_writable($targetPath)) {
			$response['errors'][] = 'Каталог не доступен для записи';
		}
		if ($debug) {
			$modx->log(modX::LOG_LEVEL_ERROR, 'Не удалось загрузить файл '.$file['name']." $tempFile to $targetFile");
		}
		return $response;
	}
	$fileUrls[] = $dirUrl.$filename;
}
$response['placeholders']['files'] = implode(',', $fileUrls);

if (!count($response['errors'])) {
	$response['success'] = true;
	if (count($response['files']) == 1) {
		$response['messages'][] = 'Файл успешно загружен';
	} elseif (count($response['files']) > 1) {
		$response['messages'][] = 'Все файлы успешно загружены';
	}
	if ($debug) {
		$modx->log(modX::LOG_LEVEL_ERROR, 'Все файлы ('.count($response['files']).') успешно загружены');
	}
}

return $response;