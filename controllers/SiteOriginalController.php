<?php

namespace app\controllers;


use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\VerbFilter;
use app\models\tools\ZohoApi;
use app\models\ZohoDataForm;
use DateInterval;
use DateTime;
use Exception;
use yii\helpers\Json;



class SiteOriginalController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'only' => ['logout'],
                'rules' => [
                    [
                        'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    /**
     * Displays homepage.
     *
     * @return string
     */
    public function actionIndex()
    {
        return $this->actionGetZohoData();
    }

    /**
     * Displays about page.
     *
     * @return string
     */
    public function actionAbout()
    {
        return $this->render('about');
    }

        /**
     * Runs the Zoho Download of Project Timesheets
     *
     * @return string
     */
    public function actionGetZohoData()
    {
        $month = date('m');
        $year = date('Y');
        $filePathName = Yii::$app->params['filePath'] . $month . '_' . $year . '.csv';

        $model = new ZohoDataForm(['month'=>$month  ,'year'=>$year,'filePath'=>$filePathName]);      

        if ($model->load(Yii::$app->request->post()))
        {
            $zoho = new ZohoApi();

            //validate the month and year entered by user
            if(is_numeric($model->month)){
                if($model->month < 0 || $model->month > 12){
                    Yii::$app->session->setFlash('error', "Invalid Month. Enter value between 1 and 12");
                return $this->render('zohoData',['model'=> $model]); 
                }
            }
            else{
                Yii::$app->session->setFlash('error', "Invalid Month. Enter value between 1 and 12");
                return $this->render('zohoData',['model'=> $model]); 
            }
            if(is_numeric($model->year)){
                if($model->year < 2018 || $model->year > $year){
                    Yii::$app->session->setFlash('error', "Invalid Year. Enter value between 2018 and " . $year);
                    return $this->render('zohoData',['model'=> $model]); 
                }
            }
            else{
                Yii::$app->session->setFlash('error', "Invalid Year. Enter value between 2018 and " . $year);
                return $this->render('zohoData',['model'=> $model]); 
            }

            //calculate the date to search that is required by ZohoAPI for timesheets
            //currently, defaulted to MONTH in the API
            if($model->month < 10){
                $model->month = '0' . $model->month;
            }

            $dateToSearch = $model->month . '-01-' . $model->year;

            //create the file to dump the data
            try{
                $fp = fopen($model->filePath, 'w');
                
                //write the headers to the file                
                $headers = 'projectId,projectNumber,client,owner,taskName,taskCompleted,taskStart,taskEnd,logOwnerName,logTotalLogHours,logTaskName,logHours,logMinutes,logTotalMinutes,logLogDate,logCreatedDate,logHoursDisplay,approvalStatus,billStatus,logNotes';
                fwrite($fp,$headers."\n");

            }
            catch(\Exception $e){
                Yii::$app->session->setFlash('error', "Failed to Create File : " . $filePathName);
                return $this->render('zohoData',['model'=> $model]);
            }

            $projectCounter = 0;
            $taskCounter = 0;
            $zohoCalls = 0;
            $zohoTaskCalls = 0;
            $logNotes = '';
            $status = '';
            $openTaskCount = 0;
            $closedTaskCount = 0;
            $projectId = '';
            $projectNumber = '';
            $owner = '';
            $client = '';
            $timesheetLink = '';
            $taskCompleted = '';
            $taskStartDateFormat = '';
            $taskEndDate = '';
            $taskWork = '';
            $taskLastUpdatedTime = '';
            $taskName = '';            
            $fileOutput = '';
            $foundSomeTimesheets = false;

            $logTotalLogHours = 0;
            $logTaskName = '';
            $logMinutes = 0;
            $logTotalMinutes = 0;
            $logHours = 0;
            $logLogDate = '';
            $logCreatedDate = '';
            $logHoursDisplay = '';
            $logOwnerName = '';
            $logApprovalStatus = '';
            $logBillStatus = '';

            $trottleTimerStart = hrtime(true);            
            $trottleTaskTimerStart = hrtime(true); 

            //get all of the Active Projects from Zoho
            $projects = $zoho->getProjects();
            //1502622000004138033
            //$projects = $zoho->readProject('1502622000004138033');
                //for each project, get data for file
                $zohoCalls++;               

                foreach($projects['projects'] as $project){

                    //DDD wait 10 seconds for each project
                    try{
                        sleep(10);
                    }
                    catch(\Exception $e){
                        echo '';
                    }

                    //capture data to output to file
                    $status = $project['custom_status_name'];
                    $openTaskCount = $project['task_count']['open'];
                    $closedTaskCount = $project['task_count']['closed'];
                    $projectId = $project['id'];
                    $projectNumber = $project['key'];
                    $owner = $project['owner_name'];
                    $client = $project['group_name'];
                    //$timesheetLink = $project[0]['link']['timesheet']['url'];                   

                    // if($projectNumber == 'ZP-2254'){
                    //     echo 'ZP-2254';
                    // }
                    // else{
                    //     continue;
                    // }
                    
                    //begin building output with the Project data
                    $fileOutput =  $projectId . ',' . $projectNumber . ',' . $client . ',' . $owner . ',';
                    $fileOutputBase =  $projectId . ',' . $projectNumber . ',' . $client . ',' . $owner . ',';

                    // throttle the execution to make sure we don't pass Zoho's APi Limit
                    $trottleTimerEnd = hrtime(true);
                    // if the rate of calls is getting close to 100 calls per 2 minutes then wait ten seconds
                    if (((120000 * $zohoCalls) / ($trottleTimerEnd - $trottleTimerStart)) > 60)
                    {
                        sleep(10); // in seconds
                    }

                    //now get Tasks to see if they are part of PrePress
                    $tasks = $zoho->getAllTasks($projectId);                    

                    //typical task count is 26
                    $zohoCalls++;
                    $zohoTaskCalls++;

                    //test to see if the $tasks variable is empty/null or false. if so, thrw error
                    if(!isset($tasks) || is_null($tasks) || $tasks === false)  {                        
                        fclose($fp);
                        Yii::$app->session->setFlash('error', "Zoho returned NO Project Tasks. This could be resulting from API limit reached.");
                        return $this->render('zohoData',['model'=> $model]);
                    }

                     // throttle the execution to make sure we don't pass Zoho's APi Limit
                     $trottleTimerEnd = hrtime(true);
                     // if the rate of calls is getting close to 100 calls per 2 minutes then wait ten seconds
                     if (((120000 * $zohoCalls) / ($trottleTimerEnd - $trottleTimerStart)) > 60)
                     {
                         sleep(10); // in seconds
                     }

                    try{
                        $taskCounter = count($tasks['tasks']);
                        //reassign to the actual task array elements
                        $tasks = $tasks['tasks'];
                    }
                    catch(\Exception $e){
                        $taskCounter = 0;
                    }                       

                    foreach($tasks as $task){                       

                        sleep(10); // in seconds

                        //capture data for output
                        Try{
                            $taskCompleted = $task['completed'];
                        }
                        catch(\Exception $e) {
                            fclose($fp);
                            Yii::$app->session->setFlash('error', "Failed to obtain Task Completed property. Unable to continue.");
                            return $this->render('zohoData',['model'=> $model]);
                        }

                        try{$taskStartDateFormat = $task['start_date_format'];} catch(\Exception $e){$taskStartDateFormat = '';}
                        try{$taskEndDate = $task['end_date'];} catch(\Exception $e){$taskEndDate = '';}
                        try{$taskWork = $task['work'];} catch(\Exception $e){$taskWork = '';}
                        try{$taskLastUpdatedTime = $task['last_updated_time'];} catch(\Exception $e){$taskLastUpdatedTime = '';}
                        try{
                            $taskName = $task['name'];
                        } 
                        catch(\Exception $e){
                            fclose($fp);
                            Yii::$app->session->setFlash('error', "Failed to obtain Task Name property. Unable to continue.");
                            return $this->render('zohoData',['model'=> $model]);
                        }                        

                        if($taskName == 'PreFlight Art' || $taskName == 'Prep for Data' || $taskName == 'Process Data' || $taskName == 'Generate PDF Proofs' || $taskName == 'Move To Production' || $taskName == 'Data Uploaded to Monticello' || $taskName == 'Revision - Data' || $taskName == 'Revision - PrePress'){
                                  
                            $fileOutput = $fileOutputBase . $taskName . ',' . $taskCompleted . ',' . $taskStartDateFormat . ',' . $taskEndDate;

                            $timesheetLink = $task['link']['timesheet']['url'];

                            $timelogs = $zoho->getTimesheets($timesheetLink,$dateToSearch);
                            $zohoCalls++;
                            $zohoTaskCalls++;

                            // throttle the execution to make sure we don't pass Zoho's APi Limit
                            $trottleTimerEnd = hrtime(true);
                            // if the rate of calls is getting close to 100 calls per 2 minutes then wait ten seconds
                            if (((120000 * $zohoCalls) / ($trottleTimerEnd - $trottleTimerStart)) > 60)
                            {
                                sleep(10); // in seconds
                            }
                            // throttle the execution to make sure we don't pass Zoho's APi Limit
                            $trottleTaskTimerEnd = hrtime(true);
                            // if the rate of calls is getting close to 100 calls per 2 minutes then wait ten seconds
                            if (((120000 * $zohoTaskCalls) / ($trottleTaskTimerEnd - $trottleTaskTimerStart)) > 60)
                            {
                                sleep(10); // in seconds
                            }

                            if(isset($timelogs) && !is_null($timelogs) && !isset($timelogs['error'])){

                                foreach($timelogs as $timeLog){

                                    sleep(10); // in seconds
                                    
                                    $foundSomeTimesheets = true;

                                    for($y = 0; $y < count($timeLog['tasklogs']); $y++){
                                        $logNotes = $timeLog['tasklogs'][$y]['notes'];
                                        $logTotalLogHours = $timeLog['total_log_hours'];
                                        $logTaskName = $timeLog['tasklogs'][$y]['task_list']['name'];
                                        $logMinutes = $timeLog['tasklogs'][$y]['minutes'];
                                        $logTotalMinutes = $timeLog['tasklogs'][$y]['total_minutes'];
                                        $logHours = $timeLog['tasklogs'][$y]['hours'];
                                        $logLogDate = $timeLog['tasklogs'][$y]['log_date'];
                                        $logCreatedDate = $timeLog['tasklogs'][$y]['created_date'];
                                        $logHoursDisplay = $timeLog['tasklogs'][$y]['hours_display'];
                                        $logOwnerName = $timeLog['tasklogs'][$y]['owner_name'];
                                        $logApprovalStatus = $timeLog['tasklogs'][$y]['approval_status'];
                                        $logBillStatus = $timeLog['tasklogs'][$y]['bill_status'];

                                        //if the timelog is with the month/year specified then output to file
                                        if(date("d",strtotime($logLogDate)) == $model->month &&  date("Y",strtotime($logLogDate)) == $model->year){
                                            $fileOutput = $fileOutput . ',' . $logOwnerName . ',' . $logTotalLogHours . ',' . $logTaskName . ',' . $logHours . ',' . $logMinutes . ',' . $logTotalMinutes . ',' . $logLogDate . ',' . $logCreatedDate . ',' . $logHoursDisplay . ',' . $logApprovalStatus . ',' . $logBillStatus . ',"' . $logNotes . '"';

                                            fwrite($fp,$fileOutput."\n");

                                        }  

                                        //reset in case there are multiple logs for the same task
                                        $fileOutput = $fileOutputBase . $taskName . ',' . $taskCompleted . ',' . $taskStartDateFormat . ',' . $taskEndDate;

                                        $zohoCalls++; 
                                        $zohoTaskCalls++;

                                    }                                 
                                   
                                }
                            }
                            else{

                                if(isset($timelogs) && !is_null($timelogs) && isset($timelogs['error'])){
                                    fclose($fp);
                                    Yii::$app->session->setFlash('error', "API limit has been reached. Unable to continue.");
                                    Yii::$app->session->setFlash('error', "API calls made." . $zohoCalls);
                                    return $this->render('zohoData',['model'=> $model]);
                                }

                                //there are no timesheet entries for this task, so discard output and reset back to the Project String
                                $fileOutput = $fileOutputBase;

                                //only reset if still false 
                                if(!$foundSomeTimesheets){
                                    $foundSomeTimesheets = false;                                
                                }
                            }

                        }

                        $zohoTaskCalls++;
                        $trottleTaskTimerEnd = hrtime(true);
                        // throttle the execution to make sure we don't pass Zoho's APi Limit                        
                        // if the rate of calls is getting close to 100 calls per 2 minutes then wait ten seconds
                        if (((120000 * $zohoTaskCalls) / ($trottleTaskTimerEnd - $trottleTaskTimerStart)) > 60)
                        {
                            sleep(10); // in seconds
                        }

                    }
                }

            //check to see if there are no task timesheet records. if so write note to file
            if(!$foundSomeTimesheets){

                fwrite($fp,'No Project Task Timesheets found for the given Month/Year.'."\n");
            }
            
            fclose($fp);

            Yii::$app->session->setFlash('success', "File " . $filePathName . ' successfully created.');
            if(!$foundSomeTimesheets){                
                Yii::$app->session->setFlash('error', "However, No Project Task Timesheets found for the given Month/Year."."\n");
            }
           
            //Yii::$app->session->setFlash('success', "API calls made." . $zohoCalls);

            $model = new ZohoDataForm(['month'=>'','year'=>'']); 
            return $this->render('zohoData',['model'=> $model]);
        }

        return $this->render('zohoData',['model'=> $model]);

    }


}
