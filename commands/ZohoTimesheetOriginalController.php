<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace app\commands;

use yii\console\Controller;
use yii\console\ExitCode;
use yii\console\Exception;
use app\models\tools\ZohoApi;

/**
 * This command echoes the first argument that you have entered.
 *
 * This command is provided as an example for you to learn how to create console commands.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class ZohoTimesheetOriginalController extends Controller
{
    /**
     * This command echoes what you have entered as the message.
     * @param string $message the message to be echoed.
     * @return int Exit code
     */
    public function actionIndex($message = '')
    {
        echo 'Usage : ' . "\n";
        echo 'GetZohoData(desired_month, desired_year, filename)' . "\n";
        echo ('filename defaults to c:\temp\ZohoTimesheetData_MMYYYYY.csv') . "\n";

        return ExitCode::OK;
    }

    public function actionGetZohoData($month, $year, $fileName = 'c:\temp\ZohoTimesheetData')
    {
       
        if(!isset($month) || !isset($year)){
            echo 'Missing Month and/or Year parameter';
            return ExitCode::DATAERR;
        }
       
        //validate the month and year entered by user
        if(is_numeric($month)){
            if($month < 0 || $month > 12){
                echo "Invalid Month. Enter value between 1 and 12";
                return ExitCode::DATAERR;
            }
        }
        else{
            echo "Invalid Month. Enter value between 1 and 12";
            return ExitCode::DATAERR;
        }
        if(is_numeric($year)){
            if($year < 2018 || $year > $year){
                echo "Invalid Year. Enter value between 2018 and " . $year;
                return ExitCode::DATAERR;
            }
        }
        else{
            echo "Invalid Year. Enter value between 2018 and " . $year;
            return ExitCode::DATAERR;
        }

        //calculate the date to search that is required by ZohoAPI for timesheets
        //currently, defaulted to MONTH in the API
        if($month < 10){
            $month = '0' . $month;
        }

            
        $filePathName = $fileName . '_' . $month . '_' . $year . '.csv';
        $zoho = new ZohoApi();


        $dateToSearch = $month . '-01-' . $year;

            //create the file to dump the data
            try{
                $fp = fopen($filePathName, 'w');
                //fflush($fp);

                //write the headers to the file                
                $headers = 'projectId,projectNumber,client,owner,taskName,taskCompleted,taskStart,taskEnd,logOwnerName,logTotalLogHours,logTaskName,logHours,logMinutes,logTotalMinutes,logLogDate,logCreatedDate,logHoursDisplay,approvalStatus,billStatus,logNotes';
                fwrite($fp,$headers."\n");

            }
            catch(\Exception $e){
                echo "Failed to Create File : " . $filePathName;
                return ExitCode::DATAERR;
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

            echo 'Starting Time: ' . $trottleTimerStart . "\n";

            //get all of the Active Projects from Zoho
            $projects = $zoho->getProjects();
            //1502622000004138033
            //$projects = $zoho->readProject('1502622000004138033');
                //for each project, get data for file
                $zohoCalls++;               

                foreach($projects['projects'] as $project){

                    //DDD wait 5 seconds for each project
                    try{
                        sleep(5);
                        echo 'sleeping at Project for 5 secs' . "\n";
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

                    //TESTING
                    fwrite($fp,'Project ID: ' . $projectId . '  Project#: ' . $projectNumber . "\n");
                    //fflush($fp);
                    echo 'Project ID: ' . $projectId . '  Project#: ' . $projectNumber . "\n";

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
                        sleep(15); // in seconds
                    }

                    //now get Tasks to see if they are part of PrePress
                    $tasks = $zoho->getAllTasks($projectId);                    

                    //typical task count is 26
                    $zohoCalls++;
                    $zohoTaskCalls++;

                    //test to see if the $tasks variable is empty/null or false. if so, throw error
                    if(!isset($tasks) || is_null($tasks) || $tasks === false)  {    
                        //fflush($fp);
                        fclose($fp);
                        echo "Zoho returned NO Project Tasks. This could be resulting from API limit reached.";
                        return ExitCode::DATAERR;
                    }

                     // throttle the execution to make sure we don't pass Zoho's APi Limit
                     $trottleTimerEnd = hrtime(true);
                     // if the rate of calls is getting close to 100 calls per 2 minutes then wait ten seconds
                     if (((120000 * $zohoCalls) / ($trottleTimerEnd - $trottleTimerStart)) > 60)
                     {
                         sleep(15); // in seconds
                     }

                    try{
                        $taskCounter = count($tasks['tasks']);
                        //reassign to the actual task array elements to remove the high-level tasks object
                        $tasks = $tasks['tasks'];
                    }
                    catch(\Exception $e){
                        $taskCounter = 0;
                    }                       

                    foreach($tasks as $task){                       

                        sleep(3); // in seconds
                        echo 'sleeping at Task# ' . $task['id'] . ' for 3 sec on Project# ' . $projectNumber . "\n";

                        //capture data for output
                        Try{
                            $taskCompleted = $task['completed'];
                        }
                        catch(\Exception $e) {
                            fclose($fp);
                            echo "Failed to obtain Task Completed property. Unable to continue.";
                            return ExitCode::DATAERR;
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
                            "Failed to obtain Task Name property. Unable to continue.";
                            return ExitCode::DATAERR;
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
                                sleep(15); // in seconds
                            }
                            // throttle the execution to make sure we don't pass Zoho's APi Limit
                            $trottleTaskTimerEnd = hrtime(true);
                            // if the rate of calls is getting close to 100 calls per 2 minutes then wait ten seconds
                            if (((120000 * $zohoTaskCalls) / ($trottleTaskTimerEnd - $trottleTaskTimerStart)) > 60)
                            {
                                sleep(15); // in seconds
                            }

                            if(isset($timelogs) && !is_null($timelogs) && !isset($timelogs['error'])){

                                foreach($timelogs as $timeLog){
                                    $foundSomeTimesheets = true;

                                    sleep(3); // in seconds
                                    echo 'sleeping at Timsheets for 3 sec';

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
                                        if(date("d",strtotime($logLogDate)) == $month &&  date("Y",strtotime($logLogDate)) == $year){
                                            $fileOutput = $fileOutput . ',' . $logOwnerName . ',' . $logTotalLogHours . ',' . $logTaskName . ',' . $logHours . ',' . $logMinutes . ',' . $logTotalMinutes . ',' . $logLogDate . ',' . $logCreatedDate . ',' . $logHoursDisplay . ',' . $logApprovalStatus . ',' . $logBillStatus . ',"' . $logNotes . '"';

                                            fwrite($fp,$fileOutput."\n");
                                            //fflush($fp);

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
                                    //fflush($fp);
                                    //fclose($fp);
                                    echo "API limit has been reached. Unable to continue." . "\n";
                                    echo "API calls made: " . $zohoCalls . "\n";
                                    echo "Task API calls made: " . $zohoTaskCalls . "\n";
                                    //return ExitCode::DATAERR;
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
                            sleep(15); // in seconds
                        }

                    }
                }

            //check to see if there are no task timesheet records. if so write note to file
            if(!$foundSomeTimesheets){

                fwrite($fp,'No Project Task Timesheets found for the given Month/Year.'."\n");
            }
            
            fclose($fp);

            echo "File " . $filePathName . ' successfully created.';
            if(!$foundSomeTimesheets){                
                echo "However, No Project Task Timesheets found for the given Month/Year."."\n";
            }
           
            //Yii::$app->session->setFlash('success', "API calls made." . $zohoCalls);

            echo 'Ending Time: ' . hrtime(true) . "\n";

            return ExitCode::OK;

    }

}
