<?php
$params = array_merge(
	require __DIR__ . '/params.php',
	require __DIR__ . '/params-local.php'
);

return [
	'aliases' => [
		'@bower' => '@vendor/bower-asset',
		'@npm' => '@vendor/npm-asset',
	],
	'vendorPath' => dirname(dirname(__DIR__)) . '/vendor',
	'components' => [
		// 'cache' => [
		// 	'class' => 'yii\caching\FileCache',
		// ],
		'cache' => [
            'class' => 'yii\redis\Cache',
            'redis' => [
                'hostname' => 'isd-lixweb-qc',
                'port' => 6379,
                'database' => 0,
            ],
        ],
		'bundles' => [
			'class' => 'yii\web\JqueryAsset',
			'sourcePath' => null,
			'basePath' => '@webroot',
			'baseUrl' => '@web',
			'js' => [
				'js/jquery-3.5.1.min.js',
			],
			// 'jsOptions' => ['type' => 'text/javascript'],
		],
		'authManager' => [
			'class' => 'yii\rbac\DbManager',
			// uncomment if you want to cache RBAC items hierarchy
			// 'cache' => 'cache',
		],
	],
	'modules' => [
		'gridview' => [
			'class' => '\kartik\grid\Module',
			// enter optional module parameters below - only if you need to
			// use your own export download action or custom translation
			// message source
			// 'downloadAction' => 'gridview/export/download',
			// 'i18n' => []
		],
	],
	'params' => $params,
];
