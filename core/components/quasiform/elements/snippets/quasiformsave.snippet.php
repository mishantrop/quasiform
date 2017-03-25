<?php
$response = [
	'errors' => [],
	'success' => false,
	'message' => '',
	'messages' => [],
];

// Load the FormSave class
$formSave = $modx->getService('formsave','FormSave', $modx->getOption('formsave.core_path', null, $modx->getOption('core_path').'components/formsave/').'model/formsave/', array());

$formTopic = $modx->getOption('fsFormTopic', $scriptProperties, 'form');
$formFields = $modx->getOption('fsFormFields', $scriptProperties, false);
$formPublished = (int)$modx->getOption('fsFormPublished', $scriptProperties, 1);

if ($formFields !== false) {
	$formFields = explode(',', $formFields);
	foreach($formFields as $key => $value) {
		$formFields[$key] = trim($value);
	}
}

// Create new form object
$newForm = $modx->newObject('fsForm');

// Build the data array
$dataArray = array();

$values = $_POST;

//$values = $hook->getValues();
foreach($formFields as $field) {
	if (!isset($values[$field])) {
		// Add empty field
		$dataArray[$field] = '';
		continue;
	}
	
	$dataArray[$field] = $values[$field];
}


// Fill the database object
$newForm->fromArray(array(
	'topic' => $formTopic,
	'time' => time(),
	'published' => $formPublished,
	'data' => $dataArray,
	'ip' => $_SERVER['REMOTE_ADDR']
));

// Save the form
if ($newForm->save()) {
    $response['success'] = true;
}

return $response;