<?php
// This file is generated. Do not modify it manually.
return array(
	'mailmojo' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'mailmojo/mailmojo-popup-button',
		'version' => '1.0.0',
		'title' => 'Mailmojo Popup Button',
		'category' => 'widgets',
		'icon' => 'email',
		'description' => 'Button that opens a specific Mailmojo subscribe popup selected when inserting the block.',
		'example' => array(
			
		),
		'attributes' => array(
			'popupId' => array(
				'type' => 'number'
			),
			'popupUrl' => array(
				'type' => 'string',
				'default' => ''
			)
		),
		'supports' => array(
			'html' => false
		),
		'textdomain' => 'mailmojo',
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./index.css',
		'style' => 'file:./style-index.css'
	)
);
