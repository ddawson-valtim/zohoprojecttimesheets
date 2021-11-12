<?php
$config = [
    'components' => [
        // 'request' => [
        //     // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
        //     'cookieValidationKey' => 'irpda0RqYi4mmWJPyxr38d9LWuzG69WZ',
        // ],
        // 'db' => [
		// 	'class' => 'yii\db\Connection',
		// 	'dsn' => 'sqlsrv:server=isd-dbs001-qc;database=JobTracking_test',
		// 	'username' => 'jobTrackingUser',
		// 	'password' => 'jobTrackingUser',
		// 	'charset' => 'utf8',
		// ],
        // 'db' => [
		// 	'class' => 'yii\db\Connection',
		// 	'dsn' => 'sqlsrv:server=isd-dkt-001\dannylocalsql;database=JobTracking_test',
		// 	'username' => 'jobTrackingUser',
		// 	'password' => 'jobTrackingUser',
		// 	'charset' => 'utf8',
		// ],
        // 'dmsDb' => [
        //     'class' => 'yii\db\Connection',
        //     'dsn' => 'sqlsrv:server=ISD-DBS001-QC;database=DigitalMailSpecification_Test',
        //     'username' => 'jobTrackingUser',
        //     'password' => 'jobTrackingUser',
        //     'charset' => 'utf8',
        // ],
    ],
];

if (!YII_ENV_TEST) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
    ];
}

return $config;