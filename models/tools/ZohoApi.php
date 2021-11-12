<?php

namespace app\models\tools;

use Yii;
use yii\base\ErrorException;

class ZohoApi
{
	// zoho projects id string for different items
	const TASK_STATUS_OPEN = 1502622000000016068;
	const TASK_STATUS_ONHOLD = 1502622000000373111;
	const TASK_STATUS_CLOSED = 1502622000000016071;
	const TASK_STATUS_VALIDATE = 1502622000001136470;

	const PROJECT_STATUS_ACTIVE = 1502622000000020089;
	const PROJECT_STATUS_COMPLETED = 1502622000000020116;
	const PROJECT_STATUS_ONHOLD = 1502622000000020104;
	const PROJECT_STATUS_CANCELLED = 1502622000000020110;

	const PROJECT_CUSTOM_FIELD_ESTIMATE_NUMBER = 'UDF_CHAR1';
	const PROJECT_CUSTOM_FIELD_ESTIMATE_LINK = 'UDF_CHAR2';
	const PROJECT_CUSTOM_FIELD_JOB_NUMBERS = 'UDF_TEXT1';

	const TASK_LIST_FINANCE = 'Finance';
	const TASK_LIST_POSTAGE = 'Postal Request, Receipt and Verification ';
	const TASK_LIST_CLIENT = 'Client Tasks';
	const TASK_LIST_PREPRESS = 'Pre-Press Operations';
	const TASK_LIST_JOBPLANNING = 'Job Planning';
	const TASK_LIST_RECEIVABLES = 'Receivables';
	const TASK_LIST_PROJECTSTART = 'Project Start';
	const TASK_LIST_GENERAL = 'General';

	//const TEMPLATE_GREATER_PUBLIC = 1502622000000992337;
	//const TEMPLATE_FAST_TRACK = 1502622000000158025;
	//const TEMPLATE_FAST_TRACK_Testing = 1502622000003013051;
	//const TEMPLATE_GP_TEST = 1502622000001447031;

	const USER_RAILS_IS = 715696625;
	const USER_IT_ADMIN = 697067345;

	const GROUP_GREATER_PUBLIC = 1502622000001671081;

	const PROJECTS_PORTAL = 'https://projectsapi.zoho.com/restapi/portal/696935741/';

	//TODO: we should use the yiisoft/yii2-httpclient extension to call APIs (when and if possible)
	/**
	 * @return mixed
	 */
	private function getAuthorizationToken($username = null)
	{
		// if called with a username then it will try to override the IT Admin credentials with the user's
		$clientId = Yii::$app->params['zohoClientId'];
		$clientSecret = Yii::$app->params['zohoClientSecret'];
		$refreshToken = Yii::$app->params['zohoRefreshToken'];

		//DDD dont need this part of the Zoho Interface
		// if (!is_null($username) && is_a(Yii::$app, '\yii\web\Application'))
		// {
		// 	$oauth = \common\models\ZohoApiOAuth::getOAuth($username);
		// 	if ($oauth)
		// 	{
		// 		$clientId = $oauth->clientId;
		// 		$clientSecret = $oauth->clientSecret;
		// 		$refreshToken = $oauth->refreshToken;
		// 	}
		// }
		
		$authUrl = "https://accounts.zoho.com/oauth/v2/token";
		$grantType = "refresh_token";

		$requestUrl = $authUrl . "?" . "refresh_token=" . $refreshToken . "&client_id=" . $clientId . "&client_secret=" . $clientSecret .
			"&grant_type=" . $grantType;

		$header = "Content_type: application/x-www-form-urlencoded";

		//echo $requestUrl;

		//begin curl
		$curl = curl_init();
		// set method to POST
		curl_setopt($curl, CURLOPT_POST, true);
		// give it the url
		curl_setopt($curl, CURLOPT_URL, $requestUrl);
		// give it the header
		curl_setopt($curl, CURLOPT_HTTPHEADER, [$header]);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		// get the response
		$response = curl_exec($curl);
		$response_info = curl_getinfo($curl);

		curl_close($curl);
		//echo $response . "<br>";
		$response = json_decode($response, true); //because of true, it's in an array

		//check for error
		// if there was an error when using the user's Zoho credentials, then try again with IT Admin's credentials
		if (array_key_exists("error", $response))
		{
			if (!is_null($username))
			{
				$response = $this->getAuthorizationToken();
				$response = ['access_token' => $response[0], 'token_type' => $response[1]];
			}
			else 
			{
				throw new yii\base\ErrorException("Zoho Authorizaion Error");
			}
		}
		// pull out the necessary data
		$accessToken = $response['access_token'];
		$tokenType = $response['token_type'];

		$results = [$accessToken, $tokenType];

		return $results;
	}

	private function webAppAuthorization()
	{
		// when running as a web app, save the accesstoken to the session to be reused until it expires
		$session = Yii::$app->session;

		if ($session->has('accessToken'))
		{
			// check if the token has expired
			$oauthTimeStamp = $session->get('oauthTimeStamp');
			$oauthTimeStamp = new \DateTime($oauthTimeStamp);
			$dateDiff = $oauthTimeStamp->diff(new \DateTime());
			$dateDiffInMinutes = ($dateDiff->days * 24 * 60) + ($dateDiff->h * 60) + ($dateDiff->i);
			// if the token is older than 25 minutes then make a new auth token request
			// one minute for development
			if ($dateDiffInMinutes >= Yii::$app->params['redisTimeBeforeReloadInMinutes'])
			{
				$authArray = $this->getAuthorizationToken(Yii::$app->user->identity->username);
				$session->set('accessToken', $authArray[0]);
				$session->set('tokenType', $authArray[1]);
				$session->set('oauthTimeStamp', (new \DateTime())->format("Y-m-d H:i:s"));
			}
			// if token has not expired
			else 
			{
				$authArray = [$session->get('accessToken'), $session->get('tokenType')];
			}
		}
		else
		{
			// if session doesn't have a previous accessToken
			$authArray = $this->getAuthorizationToken(Yii::$app->user->identity->username);
			$session->set('accessToken', $authArray[0]);
			$session->set('tokenType', $authArray[1]);
			$session->set('oauthTimeStamp', (new \DateTime())->format("Y-m-d H:i:s"));
		}

		return $authArray;
	}

	private function consoleAppAuthorization()
	{
		// when the app is running as a console app, then save the accessToken to the Redis cache to be reused until it expires
		if(isset(Yii::$app->cache->redis)){
			$redis = Yii::$app->cache->redis;
			$authToken = null;
			if ($redis->ping())
			{
				$authToken = $redis->hget('zohoAuth:' . Yii::$app->params['redisZohoAuthIndex'], 'authToken');
				if ($authToken != null)
				{
					// check the auth token and time stamp on redis to see if the token is expired
					$authTimeStamp = $redis->hget('zohoAuth:' . Yii::$app->params['redisZohoAuthIndex'], 'timeStamp');
					$authTimeStamp = new \DateTime($authTimeStamp);
					$dateDiff = $authTimeStamp->diff(new \DateTime());
					$dateDiffInMinutes = ($dateDiff->days * 24 * 60) + ($dateDiff->h * 60) + ($dateDiff->i);
					// if the token is older than 25 minutes then make a new auth token request
					// one minute for development
					if ($dateDiffInMinutes >= Yii::$app->params['redisTimeBeforeReloadInMinutes'])
					{
						// save new auth token to redis with current time stamp
						$authArray = $this->getAuthorizationToken();
						$redis->hset('zohoAuth:' . Yii::$app->params['redisZohoAuthIndex'], 'authToken', $authArray[0]);
						$redis->hset('zohoAuth:' . Yii::$app->params['redisZohoAuthIndex'], 'tokenType', $authArray[1]);
						$redis->hset('zohoAuth:' . Yii::$app->params['redisZohoAuthIndex'], 'timeStamp', date("Y-m-d H:i:s"));
					}
					// if the token is not expired then return the auth token
					else
					{
						$authArray = [$authToken, $redis->hget('zohoAuth:' . Yii::$app->params['redisZohoAuthIndex'], 'tokenType')];
					}
				}
				else
				{
					// if redis isn't reachable then get a new auth token
					$authArray = $this->getAuthorizationToken();
					$redis->hset('zohoAuth:' . Yii::$app->params['redisZohoAuthIndex'], 'authToken', $authArray[0]);
					$redis->hset('zohoAuth:' . Yii::$app->params['redisZohoAuthIndex'], 'tokenType', $authArray[1]);
					$redis->hset('zohoAuth:' . Yii::$app->params['redisZohoAuthIndex'], 'timeStamp', date("Y-m-d H:i:s"));
				}
			}
			else
			{
				// if redis isn't reachable then get a new auth token
				$authArray = $this->getAuthorizationToken();
			}
			return $authArray;
		}
		else{
			$authArray = $this->getAuthorizationToken();
			return $authArray;
		}
	}

	/**
	 * @return mixed
	 */
	public function checkAuthToken()
	{
		// change how authoization is handled depending on web or console app
		if (is_a(Yii::$app, '\yii\web\Application') && is_object(Yii::$app->user->identity))
		{
			//DDD 11/10/2021 override for this application and force console auth
			//$authArray = $this->webAppAuthorization();
			$authArray = $this->consoleAppAuthorization();
		}
		else 
		{
			$authArray = $this->consoleAppAuthorization();
		}

		return $authArray;
	}

	/**
	 * @return mixed
	 */
	private function getPortal()
	{
		$authArray = $this->checkAuthToken();
		$accessToken = $authArray[0];
		$tokenType = $authArray[1];

		// must use this url for projects
		$requestUrl = "https://projectsapi.zoho.com/restapi/portals/";

		$headers[] = "Authorization: " . $tokenType . " " . $accessToken;

		$portalCurl = curl_init();
		curl_setopt($portalCurl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($portalCurl, CURLOPT_URL, $requestUrl);
		curl_setopt($portalCurl, CURLOPT_HTTPHEADER, $headers);
		$response = curl_exec($portalCurl);
		$response_info = curl_getinfo($portalCurl);

		curl_close($portalCurl);

		//echo print_r($response, true);
		//echo "<br>";

		$response = json_decode($response, true); //because of true, it's in an array

		// pull out the necessary data
		$projectsUrl = $response['portals'][0]['link']['project']['url'];
		//echo $projectsUrl;
		$results = [$projectsUrl, $accessToken, $tokenType];
		return $results;
	}

	/**
	 * @return mixed
	 */
	public function getProjects()
	{
		//$portalArray = $this->getPortal();
		$authArray = $this->checkAuthToken();
		$projectsUrl = self::PROJECTS_PORTAL . "projects/";
		$accessToken = $authArray[0];
		$tokenType = $authArray[1];

		$requestParams = [
			'index' => 1,

			//DDD TEST only grab one project to start with
			//'range' => 200,
			'range' => 300,

			'status' => 'active',			
		];
		$requestUrl = $projectsUrl . "?" . http_build_query($requestParams);

		$headers[] = "Authorization: " . $tokenType . " " . $accessToken;
		//print_r($headers);

		$projectsCurl = curl_init();
		curl_setopt($projectsCurl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($projectsCurl, CURLOPT_URL, $requestUrl);
		curl_setopt($projectsCurl, CURLOPT_HTTPHEADER, $headers);

		curl_setopt($projectsCurl, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($projectsCurl, CURLOPT_SSL_VERIFYPEER, 0);

		$response = curl_exec($projectsCurl);
		$response_info = curl_getinfo($projectsCurl);

		if ($response === false) {
    		$response = curl_error($projectsCurl);
			echo stripslashes($response);
		}

		curl_close($projectsCurl);

		$response = json_decode($response, true); //because of true, it's in an array

		//echo print_r($response, true);
		return $response;
	}

	/**
	 * @return mixed
	 */
	public function getProjectByJob($projects, $jobNumber, $zohoProjectNumber)
	{
		if (is_array($projects))
		{
			if (isset($projects['projects']))
			{
				foreach ($projects['projects'] as $project)
				{
				
					//DDD 10/05/2021
					$jobNumberSearch = '';
					try{
						$customFieldCount = count($project['custom_fields']);
						for ($x=0; $x < $customFieldCount; $x++ ){						
							try{
								$jobNumberSearch = $project['custom_fields'][$x]['Job Number(s)'];
								break;
							}
							catch(\Exception $e){
								$jobNumberSearch = '';
							}
						}
					}
					catch(\Exception $e){
						return false; // return false if nothing found
					}

					// EFI Estimate Number:
					// EFI Estimate Link:
					// Job Numbers:
					// Requires Scan Line?:

					//if ((!empty($zohoProjectNumber) && strtoupper($project['key']) === strtoupper($zohoProjectNumber)) || (isset($project['custom_fields']) && strpos($project['custom_fields'][2]['Job Number(s)'], $jobNumber) !== false))					
					if ((!empty($zohoProjectNumber) && strtoupper($project['key']) === strtoupper($zohoProjectNumber)) || (isset($project['custom_fields']) && strpos($jobNumberSearch, $jobNumber) !== false))					
					{
						return $project;
					}
					// or if the JobNumber in the custom_fields matches the Pace Job
					// if a project doesn't have a Job Number, then the custom_fields array wont exist
					else if (array_key_exists('custom_fields', $project))
					{
						if (array_key_exists('Job Number(s)', $project['custom_fields'][0]) && str_contains($project['custom_fields'][0]['Job Number(s)'], $jobNumber))
						{
							return $project;
						}
					}
					
				}
			}
		}

		return false; // return false if nothing found
	}

	/**
	 * @param  $projectId
	 * @return mixed
	 */
	public function getProject($projectId)
	{
		$details = false;
		// if the projectId is empty, just end
		if (!empty($projectId))
		{
			//$portalArray = $this->getPortal();
			$authArray = $this->checkAuthToken();
			$projectsUrl = self::PROJECTS_PORTAL . "projects/";
			$accessToken = $authArray[0];
			$tokenType = $authArray[1];

			$requestUrl = $projectsUrl . $projectId . "/";

			$headers[] = "Authorization: " . $tokenType . " " . $accessToken;
			//print_r($headers);

			$projectsCurl = curl_init();
			curl_setopt($projectsCurl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($projectsCurl, CURLOPT_URL, $requestUrl);
			curl_setopt($projectsCurl, CURLOPT_HTTPHEADER, $headers);

			curl_setopt($projectsCurl, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($projectsCurl, CURLOPT_SSL_VERIFYPEER, 0);

			$response = curl_exec($projectsCurl);
			$response_info = curl_getinfo($projectsCurl);

			curl_close($projectsCurl);

			$response = json_decode($response, true); //because of true, it's in an array

			// when project exists, it will get some details, for now just the name to ensure the project exists
			try {
				//$projectName = $response['projects'][0]['name'];
				$projectKey = $response['projects'][0]['key'];
				$details = [
					//'projectName' => $projectName,
					'projectKey' => $projectKey];
			}
			// if the project doesn't exist return false
			catch (ErrorException $e)
			{
				$details = false;
			}
		}
		return $details;
	}

	/**
	 * @param  $projectId
	 * @return mixed
	 */
	public function readProject($projectId)
	{
		$details = false;
		// if the projectId is empty, just end
		if (!empty($projectId))
		{
			//$portalArray = $this->getPortal();
			$authArray = $this->checkAuthToken();
			$projectsUrl = self::PROJECTS_PORTAL . "projects/";
			$accessToken = $authArray[0];
			$tokenType = $authArray[1];

			$requestUrl = $projectsUrl . $projectId . "/";

			$headers[] = "Authorization: " . $tokenType . " " . $accessToken;
			//print_r($headers);

			$projectsCurl = curl_init();
			curl_setopt($projectsCurl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($projectsCurl, CURLOPT_URL, $requestUrl);
			curl_setopt($projectsCurl, CURLOPT_HTTPHEADER, $headers);
			$response = curl_exec($projectsCurl);
			$response_info = curl_getinfo($projectsCurl);

			curl_close($projectsCurl);

			$response = json_decode($response, true); //because of true, it's in an array

			// when project exists, it will get some details, for now just the name to ensure the project exists
			try {
				//$projectName = $response['projects'][0]['name'];
				$details = $response['projects'][0];
			}
			// if the project doesn't exist return false
			catch (ErrorException $e)
			{
				$details = false;
			}
		}
		return $details;
	}

	/**
	 * @param  $params
	 * @return mixed
	 */
	public function makeProject($params)
	{
		//params ['name', 'description', 'template_id', 'start_date', 'end_date']
		//$portalArray = $this->getPortal();
		$authArray = $this->checkAuthToken();
		$projectsUrl = self::PROJECTS_PORTAL . "projects/";
		$accessToken = $authArray[0];
		$tokenType = $authArray[1];

		$requestUrl = $projectsUrl;

		$headers[] = "Authorization: " . $tokenType . " " . $accessToken;

		$projectsCurl = curl_init();
		curl_setopt($projectsCurl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($projectsCurl, CURLOPT_URL, $requestUrl);
		curl_setopt($projectsCurl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($projectsCurl, CURLOPT_POST, true);
		curl_setopt($projectsCurl, CURLOPT_POSTFIELDS, http_build_query($params));
		curl_setopt($projectsCurl, CURLOPT_CONNECTTIMEOUT, 0);
		curl_setopt($projectsCurl, CURLOPT_TIMEOUT, 60); //timeout in seconds

		curl_setopt($projectsCurl, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($projectsCurl, CURLOPT_SSL_VERIFYPEER, 0);

		$response = curl_exec($projectsCurl);
		$response_info = curl_getinfo($projectsCurl);

		curl_close($projectsCurl);

		$response = json_decode($response, true); //because of true, it makes an array
		// Yii::trace($response, 'response');
		// echo $projectsUrl . "<br>" . $accessToken;
		// echo "<br>";
		// echo print_r($response_info);
		// echo "<br>";
		// echo print_r($response);

		// pull out the necessary data, return false if creating the project didn't work
		if (!is_null($response) && array_key_exists('projects', $response))
		{
			$projectId = $response['projects']['0']['id'];
		}
		else
		{
			$projectId = false;
		}
		return $projectId;
	}

	/**
	 * @param  $email
	 * @return mixed
	 */
	public function getUserId($email)
	{
		// Zoho Api only has a function to get all users, this function has to get all the users then find the coreect user from the list
		//params ['name', 'description', 'template_id', 'start_date', 'end_date']
		//$portalArray = $this->getPortal();
		$authArray = $this->checkAuthToken();
		$projectsUrl = self::PROJECTS_PORTAL . "projects/";
		$accessToken = $authArray[0];
		$tokenType = $authArray[1];

		// mamke the portal url look like: https://projectsapi.zoho.com/restapi/portal/696935741/users/
		$portalUrl = substr($projectsUrl, 0,
			strpos($projectsUrl, 'projects/'));
		$requestUrl = $portalUrl . 'users/';

		$headers[] = "Authorization: " . $tokenType . " " . $accessToken;

		$projectsCurl = curl_init();
		curl_setopt($projectsCurl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($projectsCurl, CURLOPT_URL, $requestUrl);
		curl_setopt($projectsCurl, CURLOPT_HTTPHEADER, $headers);

		curl_setopt($projectsCurl, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($projectsCurl, CURLOPT_SSL_VERIFYPEER, 0);

		$response = curl_exec($projectsCurl);
		$response_info = curl_getinfo($projectsCurl);

		curl_close($projectsCurl);
		$response = json_decode($response, true); //because of true, it's in an array

		if (!empty($response))
		{
			foreach ($response['users'] as $user)
			{
				if ($user['email'] == $email)
				{
					return $user['id'];
				}

			}
		}
		// if it gets to this point then it didnt find the user
		throw new yii\base\ErrorException('The current user is could not be found in the Zoho Portal');

		//return $userId;
	}

	/**
	 * @param  $projectId
	 * @param  $userEmail
	 * @return mixed
	 */
	public function addUserToProject($projectId, $userEmail)
	{
		//params ['name', 'description', 'template_id', 'start_date', 'end_date']
		//$portalArray = $this->getPortal();
		$authArray = $this->checkAuthToken();
		$projectsUrl = self::PROJECTS_PORTAL . "projects/";
		$accessToken = $authArray[0];
		$tokenType = $authArray[1];

		$requestUrl = $projectsUrl . $projectId . '/users/';
		//echo $requestUrl;

		$headers[] = "Authorization: " . $tokenType . " " . $accessToken;

		$params = [
			"email" => $userEmail,
		];

		$projectsCurl = curl_init();
		curl_setopt($projectsCurl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($projectsCurl, CURLOPT_URL, $requestUrl);
		curl_setopt($projectsCurl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($projectsCurl, CURLOPT_POST, true);
		curl_setopt($projectsCurl, CURLOPT_POSTFIELDS, http_build_query($params));

		curl_setopt($projectsCurl, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($projectsCurl, CURLOPT_SSL_VERIFYPEER, 0);

		$response = curl_exec($projectsCurl);
		$response_info = curl_getinfo($projectsCurl);

		curl_close($projectsCurl);

		$response = json_decode($response, true); //because of true, it's in an array

		// return feedback on whether the update worked
		if (!is_null($response) && array_key_exists('users', $response))
		{
			$result = true;
		}
		else
		{
			$result = false;
		}
		return $result;
	}

	/**
	 * @param  $projectId
	 * @param  $params
	 * @return mixed
	 */
	public function updateProject($projectId, $params)
	{
		//params ['name', 'description', 'template_id', 'start_date', 'end_date']
		//$portalArray = $this->getPortal();
		$authArray = $this->checkAuthToken();
		$projectsUrl = self::PROJECTS_PORTAL . "projects/";
		$accessToken = $authArray[0];
		$tokenType = $authArray[1];

		$requestUrl = $projectsUrl . $projectId . "/";

		$headers[] = "Authorization: " . $tokenType . " " . $accessToken;

		$projectsCurl = curl_init();
		curl_setopt($projectsCurl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($projectsCurl, CURLOPT_URL, $requestUrl);
		curl_setopt($projectsCurl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($projectsCurl, CURLOPT_POST, true);
		curl_setopt($projectsCurl, CURLOPT_POSTFIELDS, http_build_query($params));

		curl_setopt($projectsCurl, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($projectsCurl, CURLOPT_SSL_VERIFYPEER, 0);

		$response = curl_exec($projectsCurl);
		$response_info = curl_getinfo($projectsCurl);

		curl_close($projectsCurl);

		//echo print_r($response, true);
		//echo '<br/>';

		$response = json_decode($response, true); //because of true, it's in an array
		//echo print_r($response, true);

		// return feedback on whether the update worked
		if (!is_null($response) && array_key_exists('projects', $response))
		{
			$result = true;
		}
		else
		{
			$result = false;
		}
		return $result;
	}

	/**
	 * @param  $projectId
	 * @return mixed
	 */
	public function deleteProject($projectId)
	{
		$authArray = $this->checkAuthToken();
		$projectsUrl = self::PROJECTS_PORTAL . "projects/";
		$accessToken = $authArray[0];
		$tokenType = $authArray[1];
		$headers[] = "Authorization: " . $tokenType . " " . $accessToken;

		$requestUrl = $projectsUrl . $projectId . "/";

		$projectsCurl = curl_init();
		curl_setopt($projectsCurl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($projectsCurl, CURLOPT_URL, $requestUrl);
		curl_setopt($projectsCurl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($projectsCurl, CURLOPT_CUSTOMREQUEST, "DELETE");

		curl_setopt($projectsCurl, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($projectsCurl, CURLOPT_SSL_VERIFYPEER, 0);

		$response = curl_exec($projectsCurl);
		$response_info = curl_getinfo($projectsCurl);

		curl_close($projectsCurl);

		if ($response_info['http_code'] == 200)
		{
			return true;
		}
		return false;
	}

	/**
	 * @param  $projectId
	 * @param $taskId
	 * @return mixed
	 */
	public function deleteTask($projectId, $taskId)
	{
		$authArray = $this->checkAuthToken();
		$projectsUrl = self::PROJECTS_PORTAL . "projects/";
		$accessToken = $authArray[0];
		$tokenType = $authArray[1];
		$headers[] = "Authorization: " . $tokenType . " " . $accessToken;

		$requestUrl = $projectsUrl . $projectId . "/tasks/" . $taskId . "/";

		$projectsCurl = curl_init();
		curl_setopt($projectsCurl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($projectsCurl, CURLOPT_URL, $requestUrl);
		curl_setopt($projectsCurl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($projectsCurl, CURLOPT_CUSTOMREQUEST, "DELETE");

		curl_setopt($projectsCurl, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($projectsCurl, CURLOPT_SSL_VERIFYPEER, 0);

		$response = curl_exec($projectsCurl);
		$response_info = curl_getinfo($projectsCurl);

		$response = json_decode($response, true); //because of true, it's in an array

		curl_close($projectsCurl);

		if ($response_info['http_code'] == 200 || $response['error']['code'] == 6404)
		{
			return true;
		}
		return false;
	}

	/**
	 * @param  $projectId
	 * @return mixed
	 */
	public function cancelProject($projectId)
	{
		$projectCancelled = true;
		$project = $this->getProject($projectId);
		if ($project)
		{
			$projectCancelled = $this->updateProject($projectId, ['custom_status' => ZohoApi::PROJECT_STATUS_CANCELLED]);
		}
		return $projectCancelled;
	}

	/**
	 * @param $projectId, array $params $taskListName
	 * $taskListName option -- add the task to a task list on the project, requires an extra query to get the task list id
	 * @param  $projectId, array $params $taskListName
	 * @return $taskId     or false
	 */
	public function createTask($projectId, array $params, $taskListName = null)
	{
		// params example
		// $params = [
		// 	person_responsible
		// 	tasklist_id
		// 	name* -- Name of the task.
		// 	start_date -- [MM-DD-YYYY]
		// 	end_date -- [MM-DD-YYYY]
		// 	duration
		// 	duration_type
		// 	priority
		// 	description
		// 	uploaddoc
		// 	start_time
		// 	end_time
		// 	owner_work
		// 	work_type
		// 	rate_per_hour
		// 	custom_fields
		// 	completed_on
		// 	reminder_string
		// 	budget_value
		// 	budget_threshold
		// 	tagIds
		// ]

		if (isset($taskListName))
		{
			$taskListId = $this->getTaskListId($projectId, $taskListName);
			if ($taskListId)
			{
				$params += ['tasklist_id' => $taskListId];
			}
			else
			{
				return false;
			}
		}

		$authArray = $this->checkAuthToken();
		$projectsUrl = self::PROJECTS_PORTAL . "projects/";
		$accessToken = $authArray[0];
		$tokenType = $authArray[1];

		$requestUrl = $projectsUrl . $projectId . "/tasks/";

		$headers[] = "Authorization: " . $tokenType . " " . $accessToken;

		$projectsCurl = curl_init();
		curl_setopt($projectsCurl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($projectsCurl, CURLOPT_URL, $requestUrl);
		curl_setopt($projectsCurl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($projectsCurl, CURLOPT_POST, true);
		curl_setopt($projectsCurl, CURLOPT_POSTFIELDS, http_build_query($params));

		curl_setopt($projectsCurl, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($projectsCurl, CURLOPT_SSL_VERIFYPEER, 0);

		$response = curl_exec($projectsCurl);
		$response_info = curl_getinfo($projectsCurl);

		curl_close($projectsCurl);

		//echo print_r($response, true);
		//echo '<br/>';

		$response = json_decode($response, true); //because of true, it's in an array
		//echo print_r($response, true);

		// return feedback on whether the update worked
		if ($response_info['http_code'] == 201)
		{
			$result = $response['tasks'][0]['id_string'];

		}
		else
		{
			$result = false;
		}
		return $result;
	}

	/**
	 * @param  $projectId
	 * @param $taskId
	 * @return mixed
	 */
	public function readTask($projectId, $taskId)
	{
		$details = false;
		// if the projectId is empty, just end
		if (!empty($projectId))
		{
			//$portalArray = $this->getPortal();
			$authArray = $this->checkAuthToken();
			$projectsUrl = self::PROJECTS_PORTAL . "projects/";
			$accessToken = $authArray[0];
			$tokenType = $authArray[1];

			$requestUrl = $projectsUrl . $projectId . "/tasks/" . $taskId . "/";

			$headers[] = "Authorization: " . $tokenType . " " . $accessToken;
			//print_r($headers);

			$projectsCurl = curl_init();
			curl_setopt($projectsCurl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($projectsCurl, CURLOPT_URL, $requestUrl);
			curl_setopt($projectsCurl, CURLOPT_HTTPHEADER, $headers);
			$response = curl_exec($projectsCurl);
			$response_info = curl_getinfo($projectsCurl);

			curl_close($projectsCurl);

			$response = json_decode($response, true); //because of true, it's in an array

			// when project exists, it will get some details, for now just the name to ensure the project exists
			try {
				//$projectName = $response['projects'][0]['name'];
				$details = $response['tasks'][0];
			}
			// if the project doesn't exist return false
			catch (ErrorException $e)
			{
				$details = false;
			}
		}
		return $details;
	}

	/**
	 * @param  $projectId
	 * @return mixed
	 */
	public function getAllTasks($projectId)
	{
		//POST  /portal/[PORTALID]/projects/[PROJECTID]/tasks/[TASKID]/
		//params ['name', 'description', 'template_id', 'start_date', 'end_date']
		//$portalArray = $this->getPortal();
		$authArray = $this->checkAuthToken();
		$projectsUrl = self::PROJECTS_PORTAL . "projects/";
		$accessToken = $authArray[0];
		$tokenType = $authArray[1];

		$requestUrl = $projectsUrl . $projectId . "/tasks/";

		$headers[] = "Authorization: " . $tokenType . " " . $accessToken;

		$projectsCurl = curl_init();
		curl_setopt($projectsCurl, CURLOPT_CONNECTTIMEOUT ,0); 
		curl_setopt($projectsCurl, CURLOPT_TIMEOUT, 500);
		curl_setopt($projectsCurl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($projectsCurl, CURLOPT_URL, $requestUrl);
		curl_setopt($projectsCurl, CURLOPT_HTTPHEADER, $headers);
		// Get requests don't use this line ↓
		//curl_setopt($projectsCurl, CURLOPT_POST, true);
		//curl_setopt($projectsCurl, CURLOPT_POSTFIELDS, http_build_query($params));

		curl_setopt($projectsCurl, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($projectsCurl, CURLOPT_SSL_VERIFYPEER, 0);

		$response = curl_exec($projectsCurl);
		$response_info = curl_getinfo($projectsCurl);

		curl_close($projectsCurl);

		//echo print_r($response, true);
		//echo '<br/>';

		$taskList = json_decode($response, true); //because of true, it's in an array
		//echo print_r($response, true);

		if (array_key_exists('error', $taskList))
		{
			if ($taskList['error']['title'] == "URL_ROLLING_THROTTLES_LIMIT_EXCEEDED")
			{
				echo "Api Limit Reached ";
				if (array_key_exists('logFile', Yii::$app->params))
				{
					$log = new Log(Yii::$app->params['logFile'], array_key_exists('logLevel', Yii::$app->params) ? Yii::$app->params['logLevel'] : Log::LEVEL_WARNING);
					$log->error("Zoho Api Limit reached when querying Zoho Project '" . $projectId . "'");
				}
			}
		}

		// return whether finding the tasks were successfull
		if (!is_null($taskList) && array_key_exists('tasks', $taskList))
		{
			foreach ($taskList['tasks'] as $task)
			{
				if ($task['subtasks'] == true)
				{
					$subTasks = $this->getSubTasks($projectId, $task['id_string']);
					if (!empty($subTasks))
					{
						foreach ($subTasks as $subtask)
						{
							$taskList['tasks'][] = $subtask;
						}
					}
				}
			}
		}
		else
		{
			$taskList = false;
		}
		return $taskList;
	}

	/**
	 * @param  $projectId
	 * @param  $taskId
	 * @return mixed
	 */
	public function activateTask($projectId, $taskId)
	{
		$taskUpdated = false;
		if ($this->updateTask($projectId, $taskId, $params = ['custom_status' => self::TASK_STATUS_OPEN]))
		{
			$taskUpdated = true;
		}
		return $taskUpdated;
	}

	/**
	 * @param  $projectId
	 * @param  $taskId
	 * @return boolean|integer
	 */
	public function closeTask($projectId, $taskId, $datetime = null)
	{
		//DDD 09/09/2021 added check to see if blank to prevent error
		if (!empty($datetime))
		{
			// guarantee the date format
			$datetime = (\DateTime::createFromFormat('m-d-Y', $datetime, (new \DateTimeZone('America/New_York'))))->format('m-d-Y');
		}
		// else{
		// 	//set to today's date
		// 	$datetime = (\DateTime::createFromFormat('m-d-Y', date('m-d-Y'), (new \DateTimeZone('America/New_York'))))->format('m-d-Y');
		// }

		$taskUpdated = false;
		if ($this->updateTask($projectId, $taskId, $params = ['custom_status' => self::TASK_STATUS_CLOSED, 'completed' => 1, 
			//'completed_on' => $datetime
			]))
		{
			// task has been closed, now try to edit the completed on date
			$taskUpdated = 1;
			// updating the completed_on date will fail if the start and end dates are not set and if the completed on date is before the start date
			if (!empty($datetime) && !$this->updateCompletionDate($projectId, $taskId, $datetime))
			{
				$taskUpdated = 2;
			}
		}
		return $taskUpdated;
	}

	public function updateCompletionDate($projectId, $taskId, $completed_on)
	{
		// needs to be admin to change completion dates
		$authArray = $this->consoleAppAuthorization();
		$projectsUrl = self::PROJECTS_PORTAL . "projects/";
		$accessToken = $authArray[0];
		$tokenType = $authArray[1];

		$params = ['completed_on' => $completed_on];

		$requestUrl = $projectsUrl . $projectId . "/tasks/" . $taskId . "/";

		$headers[] = "Authorization: " . $tokenType . " " . $accessToken;

		$projectsCurl = curl_init();
		curl_setopt($projectsCurl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($projectsCurl, CURLOPT_URL, $requestUrl);
		curl_setopt($projectsCurl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($projectsCurl, CURLOPT_POST, true);
		curl_setopt($projectsCurl, CURLOPT_POSTFIELDS, http_build_query($params));
		$response = curl_exec($projectsCurl);
		$response_info = curl_getinfo($projectsCurl);

		curl_close($projectsCurl);

		$response = json_decode($response, true); //because of true, it's in an array

		// echo print_r($response, true);
		// echo '<br/>';
		// echo print_r($response_info, true);
		// echo '<br/>';

		// return feedback on whether the update worked
		if ($response_info['http_code'] == 200)
		{
			$result = true;
		}
		else
		{
			$result = false;
		}
		return $result;
	}


	/**
	 * @param  $projectId
	 * @param  $taskId
	 * @return mixed
	 */
	public function getTask($projectId, $taskId)
	{
		$authArray = $this->checkAuthToken();
		//$portalArray = $this->getPortal();
		$projectsUrl = self::PROJECTS_PORTAL . "projects/";
		$accessToken = $authArray[0];
		$tokenType = $authArray[1];

		$requestUrl = $projectsUrl . $projectId . "/tasks/" . $taskId . "/";

		$headers[] = "Authorization: " . $tokenType . " " . $accessToken;

		$projectsCurl = curl_init();
		curl_setopt($projectsCurl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($projectsCurl, CURLOPT_URL, $requestUrl);
		curl_setopt($projectsCurl, CURLOPT_HTTPHEADER, $headers);
		//curl_setopt($projectsCurl, CURLOPT_POST, true);
		//curl_setopt($projectsCurl, CURLOPT_POSTFIELDS, http_build_query($params));

		curl_setopt($projectsCurl, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($projectsCurl, CURLOPT_SSL_VERIFYPEER, 0);

		$response = curl_exec($projectsCurl);
		$response_info = curl_getinfo($projectsCurl);

		curl_close($projectsCurl);

		//echo print_r($response, true);
		//echo '<br/>';

		$response = json_decode($response, true); //because of true, it's in an array
		//echo print_r($response, true);

		// return feedback on whether the update worked
		if (array_key_exists('tasks', $response))
		{
			$result = $response;
		}
		else
		{
			$result = false;
		}
		return $result;
	}

	//recursively find all subtasks
	/**
	 * @param  $projectId
	 * @param  $taskId
	 * @param  array         $subTasks
	 * @return mixed
	 */
	public function getSubTasks($projectId, $taskId, array $subTasks = null)
	{
		$authArray = $this->checkAuthToken();
		$projectsUrl = self::PROJECTS_PORTAL . "projects/";
		$accessToken = $authArray[0];
		$tokenType = $authArray[1];

		$requestUrl = $projectsUrl . $projectId . "/tasks/" . $taskId . "/subtasks/";

		$headers[] = "Authorization: " . $tokenType . " " . $accessToken;

		$projectsCurl = curl_init();

		//DDD added to prevent timeouts
		curl_setopt($projectsCurl, CURLOPT_CONNECTTIMEOUT ,0); 
		curl_setopt($projectsCurl, CURLOPT_TIMEOUT, 500);

		curl_setopt($projectsCurl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($projectsCurl, CURLOPT_URL, $requestUrl);
		curl_setopt($projectsCurl, CURLOPT_HTTPHEADER, $headers);
		//curl_setopt($projectsCurl, CURLOPT_POST, true);
		//curl_setopt($projectsCurl, CURLOPT_POSTFIELDS, http_build_query($params));

		curl_setopt($projectsCurl, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($projectsCurl, CURLOPT_SSL_VERIFYPEER, 0);

		$response = curl_exec($projectsCurl);
		$response_info = curl_getinfo($projectsCurl);

		curl_close($projectsCurl);

		//echo print_r($response, true);
		//echo '<br/>';

		$response = json_decode($response, true); //because of true, it's in an array
		//echo print_r($response, true);

		if (!isset($subTasks))
		{
			$subTasks = [];
		}
		// recursively pull more subtasks
		if (array_key_exists('tasks', $response))
		{
			foreach ($response['tasks'] as $task)
			{
				$subTasks[] = $task;
				if ($task['subtasks'] == true)
				{
					$subTasks[] = $this->getSubTasks($projectId, $task['id_string'], $subTasks);
				}
			}
		}
		return $subTasks;
	}

	/**
	 * @param  $projectId
	 * @param  $taskName
	 * @return mixed
	 */
	public function getTaskByName($projectId, $taskName)
	{
		$taskObject = null;
		$taskList = $this->getAllTasks($projectId);

		if ($taskList != false)
		{
			foreach ($taskList['tasks'] as $task)
			{
				if (strcasecmp($task['name'], $taskName) == 0)
				{
					$taskObject = $task;
				}
			}
			if (isset($taskObject))
			{
				return $taskObject;
			}
			else
			{
				return false;
			}

		}
		else
		{
			return false;
		}

	}

	/**
	 * @param  $projectId
	 * @param  $taskId
	 * @param  $params
	 * @return mixed
	 */
	public function updateTask($projectId, $taskId, $params)
	{
		//POST  /portal/[PORTALID]/projects/[PROJECTID]/tasks/[TASKID]/
		//params ['name', 'description', 'template_id', 'start_date', 'end_date']
		$authArray = $this->checkAuthToken();
		$projectsUrl = self::PROJECTS_PORTAL . "projects/";
		$accessToken = $authArray[0];
		$tokenType = $authArray[1];

		$requestUrl = $projectsUrl . $projectId . "/tasks/" . $taskId . "/";

		$headers[] = "Authorization: " . $tokenType . " " . $accessToken;

		$projectsCurl = curl_init();
		curl_setopt($projectsCurl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($projectsCurl, CURLOPT_URL, $requestUrl);
		curl_setopt($projectsCurl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($projectsCurl, CURLOPT_POST, true);
		curl_setopt($projectsCurl, CURLOPT_POSTFIELDS, http_build_query($params));

		curl_setopt($projectsCurl, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($projectsCurl, CURLOPT_SSL_VERIFYPEER, 0);

		$response = curl_exec($projectsCurl);
		$response_info = curl_getinfo($projectsCurl);

		curl_close($projectsCurl);

		$response = json_decode($response, true); //because of true, it's in an array

		// echo print_r($response, true);
		// echo '<br/>';
		// echo print_r($response_info, true);
		// echo '<br/>';

		// return feedback on whether the update worked
		if ($response_info['http_code'] == 200)
		{
			$result = true;
		}
		else
		{
			$result = false;
		}
		return $result;
	}

	/**
	 * Adds a new comment to a task
	 * @return boolean
	 */
	public function addCommentToTask($projectId, $taskId, $comment)
	{
		$authArray = $this->checkAuthToken();
		$projectsUrl = self::PROJECTS_PORTAL . "projects/";
		$accessToken = $authArray[0];
		$tokenType = $authArray[1];
		$result = false;

		if ($taskId != false)
		{
			// add comment url
			$requestUrl = $projectsUrl . $projectId . "/tasks/" . $taskId . "/comments/";

			$headers[] = "Authorization: " . $tokenType . " " . $accessToken;

			$params = ['content' => $comment];

			$projectsCurl = curl_init();
			curl_setopt($projectsCurl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($projectsCurl, CURLOPT_URL, $requestUrl);
			curl_setopt($projectsCurl, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($projectsCurl, CURLOPT_POST, true);
			curl_setopt($projectsCurl, CURLOPT_POSTFIELDS, http_build_query($params));

			curl_setopt($projectsCurl, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($projectsCurl, CURLOPT_SSL_VERIFYPEER, 0);

			$response = curl_exec($projectsCurl);
			$response_info = curl_getinfo($projectsCurl);

			curl_close($projectsCurl);

			// echo print_r($response, true);
			// echo '<br/>';
			// echo print_r($response_info, true);

			$response = json_decode($response, true); //because of true, it's in an array
			//echo print_r($response, true);

			// return feedback on whether the update worked
			if ($response_info['http_code'] == 200)
			{
				$result = true;
			}
		}

		return $result;
	}

	/**
	 * each task is divided into a task list and each project has their own group of task lists
	 * with the template, we can look by taskList name to get the id of the task list
	 * get the full list of tasks and iterate through until we find the tasklist with the name that we want
	 * @param  $projectId, $taskListName
	 * @return mixed       return taskList id if found, otherwise return false
	 */
	public function getTaskListId($projectId, $taskListName)
	{
		$allTasks = $this->getTaskLists($projectId);
		$foundId = false;
		$taskId = null;
		$index = 0;
		if ($allTasks != false)
		{
			$taskCount = count($allTasks['tasklists']);
			while ($foundId == false && $index < $taskCount)
			{
				if ($allTasks['tasklists'][$index]['name'] == $taskListName)
				{
					$taskId = $allTasks['tasklists'][$index]['id'];
					$foundId = true;
				}
				$index += 1;
			}
		}
		if ($foundId)
		{
			return intval($taskId);
		}
		else
		{
			return $foundId;
		}
	}

	/**
	 * @param  $projectId
	 * @return mixed
	 */
	public function getTaskLists($projectId)
	{
		$authArray = $this->checkAuthToken();
		$projectsUrl = self::PROJECTS_PORTAL . "projects/";
		$accessToken = $authArray[0];
		$tokenType = $authArray[1];
		$result = false;

		$params = ['flag' => 'internal'];

		$requestUrl = $projectsUrl . $projectId . "/tasklists/?" . http_build_query($params);

		$headers[] = "Authorization: " . $tokenType . " " . $accessToken;

		$projectsCurl = curl_init();
		curl_setopt($projectsCurl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($projectsCurl, CURLOPT_URL, $requestUrl);
		curl_setopt($projectsCurl, CURLOPT_HTTPHEADER, $headers);

		curl_setopt($projectsCurl, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($projectsCurl, CURLOPT_SSL_VERIFYPEER, 0);

		$response = curl_exec($projectsCurl);
		$response_info = curl_getinfo($projectsCurl);

		curl_close($projectsCurl);

		// echo print_r($response, true);
		// echo '<br/>';
		// echo print_r($response_info, true);

		$response = json_decode($response, true); //because of true, it's in an array
		//echo print_r($response, true);
		// return tasklist worked
		if ($response_info['http_code'] == 200)
		{
			$result = $response;
		}
		return $result;
	}

	public function getGroupIdFromClientNumber($number)
	{
		$authArray = $this->checkAuthToken();
		$requestUrl = self::PROJECTS_PORTAL . "projects/groups";
		$accessToken = $authArray[0];
		$tokenType = $authArray[1];
		$result = null;

		$headers[] = "Authorization: " . $tokenType . " " . $accessToken;

		$projectsCurl = curl_init();
		curl_setopt($projectsCurl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($projectsCurl, CURLOPT_URL, $requestUrl);
		curl_setopt($projectsCurl, CURLOPT_HTTPHEADER, $headers);

		curl_setopt($projectsCurl, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($projectsCurl, CURLOPT_SSL_VERIFYPEER, 0);

		$response = curl_exec($projectsCurl);
		$response_info = curl_getinfo($projectsCurl);

		curl_close($projectsCurl);

		$response = json_decode($response, true); //because of true, it's in an array
		foreach ($response['groups'] as $group)
		{
			if (str_contains($group['name'], strval($number)))
			{
				$result = $group['id'];
			}
		}
		return $result;
	}

	/**
	 * @return mixed
	 */
	public function getTimesheets($url,$dateToSearch)
	{
		//$portalArray = $this->getPortal();
		$authArray = $this->checkAuthToken();
		//$projectsUrl = self::PROJECTS_PORTAL . "projects/";
		$accessToken = $authArray[0];
		$tokenType = $authArray[1];

		$requestParams = [
			'users_list' => 'all',
			'view_type' => 'month',
			'date' => $dateToSearch,
			'bill_status' => 'All',
			'component_type' => 'task',			
		];

		$requestUrl = $url . "?" . http_build_query($requestParams);

		$headers[] = "Authorization: " . $tokenType . " " . $accessToken;
		$headers[] = "Scope: ZohoProjects.timesheets.READ";
		//print_r($headers);

		$logsCurl = curl_init();
		curl_setopt($logsCurl, CURLOPT_CONNECTTIMEOUT ,0); 
		curl_setopt($logsCurl, CURLOPT_TIMEOUT, 500);
		curl_setopt($logsCurl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($logsCurl, CURLOPT_URL, $requestUrl);
		curl_setopt($logsCurl, CURLOPT_HTTPHEADER, $headers);

		curl_setopt($logsCurl, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($logsCurl, CURLOPT_SSL_VERIFYPEER, 0);

		try{
			$response = curl_exec($logsCurl);
		}
		catch(\Exception $e){
			return false;
		}

		$response_info = curl_getinfo($logsCurl);

		if ($response === false) {
    		$response = curl_error($logsCurl);
			echo stripslashes($response);
		}

		curl_close($logsCurl);

		$response = json_decode($response, true); //because of true, it's in an array

		//echo print_r($response, true);
		return $response;
	}

	
		/**
	 * @return mixed
	 */
	public function getAllProjectTaskLogs($projectId,$dateToSearch)
	{
		//$portalArray = $this->getPortal();
		$authArray = $this->checkAuthToken();
		$url = self::PROJECTS_PORTAL . "projects/";
		$accessToken = $authArray[0];
		$tokenType = $authArray[1];

		$requestParams = [
			'users_list' => 'all',
			'view_type' => 'month',
			'date' => $dateToSearch,
			'bill_status' => 'All',
			'component_type' => 'task',			
		];

		$requestUrl = $url . $projectId . "/logs/?" . http_build_query($requestParams);

		$headers[] = "Authorization: " . $tokenType . " " . $accessToken;
		$headers[] = "Scope: ZohoProjects.timesheets.READ";
		//print_r($headers);

		$logsCurl = curl_init();
		curl_setopt($logsCurl, CURLOPT_CONNECTTIMEOUT ,0); 
		curl_setopt($logsCurl, CURLOPT_TIMEOUT, 500);
		curl_setopt($logsCurl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($logsCurl, CURLOPT_URL, $requestUrl);
		curl_setopt($logsCurl, CURLOPT_HTTPHEADER, $headers);

		curl_setopt($logsCurl, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($logsCurl, CURLOPT_SSL_VERIFYPEER, 0);

		try{
			$response = curl_exec($logsCurl);
		}
		catch(\Exception $e){
			return false;
		}

		$response_info = curl_getinfo($logsCurl);

		if ($response === false) {
    		$response = curl_error($logsCurl);
			echo stripslashes($response);
		}

		curl_close($logsCurl);

		$response = json_decode($response, true); //because of true, it's in an array

		//echo print_r($response, true);
		return $response;
	}

}
