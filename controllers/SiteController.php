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
use yii\bootstrap4\Progress;
use app\models\ProgressForm;


class SiteController extends Controller
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
        $filePathName = Yii::$app->params['filePath'] . '_' . $month . '_' . $year . '.csv';

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
                //$headers = 'projectId,projectNumber,client,owner,taskName,taskCompleted,taskStart,taskEnd,logOwnerName,logTotalLogHours,logTaskName,logHours,logMinutes,logTotalMinutes,logLogDate,logCreatedDate,logHoursDisplay,approvalStatus,billStatus,logNotes';
                $headers = 'projectId,projectNumber,client,owner,taskName,logOwnerName,logTotalLogHours,logTaskName,logHours,logMinutes,logTotalMinutes,logLogDate,logCreatedDate,logHoursDisplay,approvalStatus,billStatus,logNotes';
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
            $projectId = '';
            $projectNumber = '';
            $owner = '';
            $client = '';
            $timesheetLink = '';
            $taskCompleted = '';
            //$taskStartDateFormat = '';
            //$taskEndDate = '';            
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

            //begin tracing time
            $startDateTime = new \DateTime(date("Y-m-d h:i:s"));
            
            //get all of the Active Projects from Zoho
            $projects = $zoho->getProjects();                   

                foreach($projects['projects'] as $project){

                    //Check the status of how many zohoCalls have been made within 2 minutes
                    $endDateTime = new \DateTime(date("Y-m-d h:i:s"));
                    $interval = $startDateTime->diff($endDateTime);                

                    if($zohoCalls >= 95){
                        if($interval->s <= 120){
                            //reset the timer and counts                         
                            // $wait = 130 - $interval->s;
                            // echo 'pausing API calls for ' . $wait . ' seconds.....' . "\n";
                            // for ($x=0; $x<$wait; $x++){
                            //     if($x + 1 < $wait){
                            //         echo '.';
                            //     }
                            //     else{
                            //         echo '.' . "\n";
                            //     }
                                
                            //     sleep(1);
                            // }
                            sleep(130 - $interval->s);
                            $zohoCalls = 0;
                            $startDateTime = new \DateTime(date("Y-m-d h:i:s"));                        
                        }                   
                    }
                    else{
                        if($interval->s >= 115){
                            //reset the timer and counts 
                            // $wait = 130 - $interval->s;
                            // echo 'pausing API calls for ' . $wait . ' seconds.....' . "\n";
                            // for ($x=0; $x<$wait; $x++){
                            //     if($x + 1 < $wait){
                            //         echo '.';
                            //     }
                            //     else{
                            //         echo '.' . "\n";
                            //     }
                            //     sleep(1);
                            // }
                            sleep(130 - $interval->s);
                            $zohoCalls = 0;
                            $startDateTime = new \DateTime(date("Y-m-d h:i:s"));                        
                        }
                    }

                    //capture data to output to file
                    $status = $project['custom_status_name'];
                    $openTaskCount = $project['task_count']['open'];
                    $closedTaskCount = $project['task_count']['closed'];
                    $projectId = $project['id'];
                    $projectNumber = $project['key'];
                    $owner = $project['owner_name'];
                    try{
                        $client = $project['group_name'];
                    }
                    catch(\Exception $e){
                        $client = ' ';
                    }               
                    
                    //begin building output with the Project data
                    $fileOutput =  $projectId . ',' . $projectNumber . ',' . $client . ',' . $owner . ',';                    
                    $fileOutputBase =  $projectId . ',' . $projectNumber . ',"' . $client . '",' . $owner . ',';

                    //now get Logs for the Project
                    $taskLogs = $zoho->getAllProjectTaskLogs($projectId, $dateToSearch);

                    //increment once for each task log API call per project
                    $zohoCalls++;
                    //echo 'zoho API calls made: ' . $zohoCalls . ' in ' . $interval->s . ' seconds ' . "\n";

                    if(isset($taskLogs) && !is_null($taskLogs) && !isset($taskLogs['error'])){
                        try{
                            //reassign the taskLog sub-onject to allow better iteration
                            $taskLogs = $taskLogs['timelogs']['date'];
                        }
                        catch(\Exception $e){
                            
                            Yii::$app->session->setFlash('error', "error reassigning taskLogs object");
                            return $this->render('zohoData',['model'=> $model]);
                        }
                    
                        foreach($taskLogs as $taskLog){                       
    
                            for($y = 0; $y < count($taskLog['tasklogs']); $y++){
    
                                //these aren't in this API call results
                                //try{$taskStartDateFormat = $task['start_date_format'];} catch(\Exception $e){$taskStartDateFormat = '';}
                                //try{$taskEndDate = $task['end_date'];} catch(\Exception $e){$taskEndDate = '';}                            
                            
                                $logLogDate = $taskLog['date'];
                                $logNotes = $taskLog['tasklogs'][$y]['notes'];
                                $logTotalLogHours = $taskLog['total_hours'];
                                $taskName = $taskLog['tasklogs'][$y]['task']['name'];
                                $logMinutes = $taskLog['tasklogs'][$y]['minutes'];
                                $logTotalMinutes = $taskLog['tasklogs'][$y]['total_minutes'];
                                $logHours = $taskLog['tasklogs'][$y]['hours'];                    
                                $logCreatedDate = $taskLog['tasklogs'][$y]['created_date'];
                                $logHoursDisplay = $taskLog['tasklogs'][$y]['hours_display'];
                                $logOwnerName = $taskLog['tasklogs'][$y]['owner_name'];
                                $logApprovalStatus = $taskLog['tasklogs'][$y]['approval_status'];
                                $logBillStatus = $taskLog['tasklogs'][$y]['bill_status'];
                                $logTaskName = $taskLog['tasklogs'][0]['task_list']['name'];
    
                                if($taskName == 'PreFlight Art' || $taskName == 'Prep for Data' || $taskName == 'Process Data' || $taskName == 'Generate PDF Proofs' || $taskName == 'Move To Production' || $taskName == 'Data Uploaded to Monticello' || $taskName == 'Revision - Data' || $taskName == 'Revision - PrePress'){
                                        
                                    //$fileOutput = $fileOutputBase . $taskName . ',' . $taskCompleted . ',' . $taskStartDateFormat . ',' . $taskEndDate;
                                    $fileOutput = $fileOutputBase . $taskName;
    
                                        //if the timelog is with the month/year specified then output to file
                                        if(date("d",strtotime($logLogDate)) == $month &&  date("Y",strtotime($logLogDate)) == $year){
                                        $fileOutput = $fileOutput . ',' . $logOwnerName . ',' . $logTotalLogHours . ',' . $logTaskName . ',' . $logHours . ',' . $logMinutes . ',' . $logTotalMinutes . ',' . $logLogDate . ',' . $logCreatedDate . ',' . $logHoursDisplay . ',' . $logApprovalStatus . ',' . $logBillStatus . ',"' . $logNotes . '"';
    
                                        fwrite($fp,$fileOutput."\n");
                                        //fflush($fp);
    
                                        $foundSomeTimesheets = true;
                                    }  
    
                                    //reset in case there are multiple logs for the same task
                                    //$fileOutput = $fileOutputBase . $taskName . ',' . $taskCompleted . ',' . $taskStartDateFormat . ',' . $taskEndDate;
                                    $fileOutput = $fileOutputBase . $taskName;
    
                                }
    
                            }//for loop
    
                            //only reset if still false 
                            if(!$foundSomeTimesheets){
                                $foundSomeTimesheets = false;                                
                            }
    
                         }
                    }
                    else{
                       
                        if(isset($taskLogs) && !is_null($taskLogs) && isset($taskLogs['error'])){                        
                            fclose($fp);                            
                            Yii::$app->session->setFlash('error', "API limit." . ' projectId: ' . $projectId . "\n");
                            //Yii::$app->session->setFlash('error', "API limit has been reached. Unable to continue." . "\n");
                            return $this->render('zohoData',['model'=> $model]);
                        }
    
                        //there are no timesheet entries for this project, so discard output and reset back to the Project String
                        $fileOutput = $fileOutputBase;
    
                        //only reset if still false 
                        if(!$foundSomeTimesheets){
                            $foundSomeTimesheets = false;                                
                        }
                    }
    
                }//foreach project
    

                
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

    public function actionShowProgress(){
        echo "10";
    }

}
