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
class ZohoTimesheetController extends Controller
{
    /**
     * This command echoes what you have entered as the message.
     * @param string $message the message to be echoed.
     * @return int Exit code
     */
    public function actionIndex($message = '')
    {
        echo 'Usage : ' . "\n";
        echo 'yii zoho-timesheet\get-zoho-data desired_month desired_year filename)' . "\n";
        echo ('filename defaults to c:\temp\ZohoTimesheetData_MMYYYYY.csv') . "\n";

        return ExitCode::OK;
    }

    public function actionGetZohoData($month, $year, $fileName = 'c:\temp\ZohoTimesheetData')
    {
       
        date_default_timezone_set("America/New_York");       
        
        // $startDateTime = new \DateTime(date("Y-m-d h:i:s"));
        // $endDateTime = new \DateTime(date("Y-m-d h:i:s"));
        // $interval = $startDateTime->diff($endDateTime);   
        // $wait = 25 - $interval->s;
        // //echo 'pausing API calls for ' . $interval->format('%s') . ' seconds' . "\n";
        // echo 'pausing API calls for ' . $wait . ' seconds' . "\n";

        // for ($x=0; $x<$wait; $x++){
        //     echo '.';
        //     sleep(1);
        // }
        

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
                //$headers = 'projectId,projectNumber,client,owner,taskName,taskCompleted,taskStart,taskEnd,logOwnerName,logTotalLogHours,logTaskName,logHours,logMinutes,logTotalMinutes,logLogDate,logCreatedDate,logHoursDisplay,approvalStatus,billStatus,logNotes';
                $headers = 'projectId,projectNumber,client,owner,taskName,logOwnerName,logTotalLogHours,logTaskName,logHours,logMinutes,logTotalMinutes,logLogDate,logCreatedDate,logHoursDisplay,approvalStatus,billStatus,logNotes';
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

            $trottleTimerStart = hrtime(true);            
            $trottleTaskTimerStart = hrtime(true); 

            //begin tracing time
            $startDateTime = new \DateTime(date("Y-m-d h:i:s"));

            //echo 'Starting Time: ' . $trottleTimerStart . "\n";

            //get all of the Active Projects from Zoho
            $projects = $zoho->getProjects();                      

            foreach($projects['projects'] as $project){

                //Check the status of how many zohoCalls have been made within 2 minutes
                $endDateTime = new \DateTime(date("Y-m-d h:i:s"));
                $interval = $startDateTime->diff($endDateTime);                

                if($zohoCalls >= 95){
                    if($interval->s <= 120){
                        //reset the timer and counts                         
                        $wait = 130 - $interval->s;
                        echo 'pausing API calls for ' . $wait . ' seconds.....' . "\n";
                        for ($x=0; $x<$wait; $x++){
                            if($x + 1 < $wait){
                                echo '.';
                            }
                            else{
                                echo '.' . "\n";
                            }
                            
                            sleep(1);
                        }
                        //sleep(130 - $interval->s);
                        $zohoCalls = 0;
                        $startDateTime = new \DateTime(date("Y-m-d h:i:s"));                        
                    }                   
                }
                else{
                    if($interval->s >= 115){
                        //reset the timer and counts 
                        $wait = 130 - $interval->s;
                        echo 'pausing API calls for ' . $wait . ' seconds.....' . "\n";
                        for ($x=0; $x<$wait; $x++){
                            if($x + 1 < $wait){
                                echo '.';
                            }
                            else{
                                echo '.' . "\n";
                            }
                            sleep(1);
                        }
                        //sleep(130 - $interval->s);
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
                

                    //TESTING
                    //fwrite($fp,'Project ID: ' . $projectId . '  Project#: ' . $projectNumber . "\n");                
                    echo 'Project ID: ' . $projectId . '  Project#: ' . $projectNumber . "\n";

                // if($projectNumber == 'ZP-2254'){
                //     echo 'ZP-2254';
                // }
                // else{
                //     continue;
                // }
                
                //begin building output with the Project data
                $fileOutput =  $projectId . ',' . $projectNumber . ',"' . $client . '",' . $owner . ',';
                $fileOutputBase =  $projectId . ',' . $projectNumber . ',"' . $client . '",' . $owner . ',';

                // throttle the execution to make sure we don't pass Zoho's APi Limit
                $trottleTimerEnd = hrtime(true);
                // if the rate of calls is getting close to 100 calls per 2 minutes then wait ten seconds
                // if (((120000 * $zohoCalls) / ($trottleTimerEnd - $trottleTimerStart)) > 60)
                // {
                //     sleep(15); // in seconds
                // }

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
                        echo 'error reassigning taskLogs object';
                        return ExitCode::DATAERR;
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
                        echo "API limit has been reached. Unable to continue." . "\n";
                        echo "API calls made: " . $zohoCalls . "\n";
                        //echo "Task API calls made: " . $zohoTaskCalls . "\n";
                        return ExitCode::DATAERR;
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

            echo "File " . $filePathName . ' successfully created.' . "\n";
            if(!$foundSomeTimesheets){                
                echo "However, No Project Task Timesheets found for the given Month/Year."."\n";
            }            

            echo 'Ending Time: ' . hrtime(true) . "\n";

            return ExitCode::OK;

    }

}
