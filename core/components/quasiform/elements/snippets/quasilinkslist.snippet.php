<?php
if (!isset($input) || empty($input)) {
    return '';
}

$items = explode(',', $input);

$output = '';
$itemsOutput = '';

if (is_array($items) && count($items) > 0) {
    foreach ($items as &$item) {
		$anchor = str_replace('http://', '', $item);
		$anchor = str_replace('https://', '', $anchor);
        $itemsOutput .= '<li><a href="'.$item.'">'.$anchor.'</a></li>';
    }
    $output = '<ul>'.$itemsOutput.'</ul>';
}

return $output;
