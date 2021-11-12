<?php

$params = array_merge(
	require __DIR__ . '/params.php',
	require __DIR__ . '/params-local.php'
);

return [
	'components' => [
		// 'db' => [
		// 	'class' => 'yii\db\Connection',
		// 	'dsn' => 'sqlsrv:server=isd-dkt-001\dannylocalsql;database=JobTracking',
		// 	'username' => 'jobTrackingUser',
		// 	'password' => 'jobTrackingUser',
		// 	'charset' => 'utf8',
		// ],
		
		'cache' => [
            'class' => 'yii\redis\Cache',
            'redis' => [
                'hostname' => 'isd-lixweb-qc',
                'port' => 6379,
                'database' => 0,
            ],
        ],
		'mailer' => [
			'class' => 'yii\swiftmailer\Mailer',
			'viewPath' => '@common/mail',
			// send all mails to a file by default. You have to set
			// 'useFileTransport' to false and configure a transport
			// for the mailer to send real emails.
			'useFileTransport' => false,
			'transport' => [
				'class' => 'Swift_SmtpTransport',
				'host' => 'valtim-com.mail.protection.outlook.com',
//				'username' => 'noreply@valtim.com',
//				'password' => 'password',
				'port' => '25', // Port 587 is a very common port too
//				'encryption' => 'tls', // It is often used, check your provider or mail server specs
			]
		],
	],
	'params' => $params,
];