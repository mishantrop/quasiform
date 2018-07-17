<?php
if (!isset($input) || empty($input)) {
    return '';
}
$output = '';
$items = explode(',', $input);
if (is_array($items) && count($items) > 0) {
    $output .= '<ul>';
    foreach ($items as &$item)
    {
		$anchor = str_replace('http://', '', $item);
		$anchor = str_replace('https://', '', $anchor);
        $output .= '<li><a href="'.$item.'">'.$anchor.'</a></li>';
    }
    $output .= '</ul>';
}
return $output;