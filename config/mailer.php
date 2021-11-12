<?php
return [
	'class' => 'yii\swiftmailer\Mailer',
	'useFileTransport' => false, //John Jaminet - set useFileTransport to true when debugging
	'transport' => [
		'class' => 'Swift_SmtpTransport',
		'host' => 'valtim-com.mail.protection.outlook.com',
		'encryption' => 'tls',
		// 'username' => 'username',
		// 'password' => 'password',
		'port' => '25',
	],
];