<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$cfg['suppress_issue_types'][] = 'PhanPluginDuplicateAdjacentStatement';
$cfg['suppress_issue_types'][] = 'PhanPossiblyUndeclaredVariable';
$cfg['suppress_issue_types'][] = 'PhanTypeMismatchArgumentInternal';

$cfg['directory_list'] = array_merge(
	$cfg['directory_list'], [
		'vendor/dompdf/dompdf',
		'vendor/phpmailer/phpmailer'
	]
);

$cfg['exclude_analysis_directory_list'] = array_merge(
	$cfg['exclude_analysis_directory_list'], [
		'vendor/dompdf/dompdf',
		'vendor/phpmailer/phpmailer'
	]
);

return $cfg;
