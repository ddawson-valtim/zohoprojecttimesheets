<?php
return [
	'adminEmail' => 'it@valtim.com',
	'senderEmail' => 'it@valtim.com',
	'senderName' => 'Zoho Project Timesheets',
	'rails_is' => '',
	'rails_is_zohoId' => "",
	'rails_pp' => '',
	'rails_pp_zohoId' => "",
	'rails_est' => '',
	'rails_est_zohoId' => "",
	'rails_fin' => '',
	'rails_fin_zohoId' => "",
	'cslist' => '',
	'LDAP-Group-Assignment-Options' => [
		'LOGIN_POSSIBLE_WITH_ROLE_ASSIGNED_MATCHING_REGEX' => "/^(JTS)(.*)/", // a role has to be assign, which starts with IMBT
		'REGEX_GROUP_MATCH_IN_LDAP' => "/^(JTS)(.*)/", // Active Directory groups beginning with IMBT are filtered and if a IMBT role with the same name exists the role is added to the user
		'ADD_GROUPS_FROM_LDAP_MATCHING_REGEX' => true, // add groups/roles matching regex REGEX_GROUP_MATCH_IN_LDAP to the user
		'REMOVE_ONLY_GROUPS_MATCHING_REGEX' => true, // only remove groups matching regex REGEX_GROUP_MATCH_IN_LDAP
		'REMOVE_ALL_GROUPS_NOT_FOUND_IN_LDAP' => false,
		'SEARCH_NESTED_GROUPS' => true,
	],
	'user.passwordMinLength' => 6,
	'user.passwordResetTokenExpire' => 3600,
	
	'bsVersion' => '4.x', // this will set globally `bsVersion` to Bootstrap 4.x for all Krajee Extensions

	'zohoClientId' => "1000.G3WUH3HY6D1M6OI4BGCV83C5EM7JRW",
	'zohoClientSecret' => "c3a09f715d95d845d7c4fff25f38713b552eaf6972",
	'zohoRefreshToken' => "1000.7f818bece9c35c49e0a61135d030b987.bbc59898e6bfa178d2676c278715cec3",
	'zohoTemplateFastTrack' => 1502622000003013051,	
	'redisTimeBeforeReloadInMinutes' => '10',
	'redisZohoAuthIndex' => '1',
	
	'zohoSecretKey' => 'ZhjV!NgWVt3uBZTd2cz+t3+2qa_hjp^U',
	'zohoNewProjectStatus' => 1502622000000020104,
	
	// account used by the application to connect to Active Directory 
	'ldap_username' => 'ldapadmin@valtim.com', 
	'ldap_password' => 'gByUHHAUA9JEBZymdKMA3Dzqc8mxQXNJ',
	
	'logFile' => 'f:\log.log',
	'logLevel' => 'Debug',
	'filePath' => 'c:\temp\ZohoTimesheetData',

];