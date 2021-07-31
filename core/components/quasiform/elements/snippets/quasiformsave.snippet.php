<?php
$formSave = $modx->getService('formsave', 'FormSave', $modx->getOption('formsave.core_path', null, $modx->getOption('core_path').'components/formsave/').'model/formsave/', []);

$response = [
	'errors' => [],
	'placeholders' => [],
	'success' => false,
];

$topic = $modx->getOption('topic', $scriptProperties);
$values = $_POST;

$newForm = $modx->newObject('fsForm');

$dataArray = [];
foreach($values as $fieldName => $fieldValue) {
	$dataArray[$fieldName] = $fieldValue;
}

$newForm->fromArray([
	'topic' => $topic,
	'time' => time(),
	'published' => 1,
	'data' => $dataArray,
	'ip' => $_SERVER['REMOTE_ADDR'],
]);

if ($newForm->save()) {
    $response['success'] = true;
} else {
	$modx->log(xPDO::LOG_LEVEL_ERROR, 'quasiFormSave | Не получилось сохранить форму');
}

return $response;
