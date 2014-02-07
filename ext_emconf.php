<?php

########################################################################
# Extension Manager/Repository config file for ext: "t3extblog"
#
# Auto generated by Extension Builder 2013-04-25
#
# Manual updates:
# Only the data in the array - anything else is removed by next write.
# "version" and "dependencies" must not be touched!
########################################################################

$EM_CONF[$_EXTKEY] = array(
	'title' => 'T3Blog Extbase',
	'description' => '',
	'category' => 'plugin',
	'author' => 'Felix Nagel',
	'author_email' => 'info@felixnagel.com',
	'author_company' => '',
	'shy' => '',
	'priority' => '',
	'module' => '',
	'state' => 'alpha',
	'internal' => '',
	'uploadfolder' => '0',
	'createDirs' => '',
	'modify_tables' => '',
	'clearCacheOnLoad' => 0,
	'lockType' => '',
	'version' => '',
	'constraints' => array(
		'depends' => array(
			'extbase' => '1.5',
			'fluid' => '1.5',
			'typo3' => '4.5.0-4.7.99',
		),
		'conflicts' => array(
			't3blog' => '',
		),
		'suggests' => array(
			'sfpantispam' => '',
			'pagebrowse' => '',
		),
	),
);

?>