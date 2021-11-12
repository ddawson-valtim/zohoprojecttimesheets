<?php


use yii\helpers\Html;
use yii\bootstrap4\ActiveForm;
use yii\bootstrap4\Progress;
use yii\helpers\Url;


$this->title = 'Zoho Project Timesheets';

?>
<div class="zohoData">
    <h1><?= Html::encode($this->title) ?></h1>

    <p>Please enter the Month and Year to Pull Zoho Timesheet log data</p>
    <p>The default file path and file name has been provided.</p>

    <?php $form = ActiveForm::begin([
        'id' => 'zohoData-form',
        'layout' => 'horizontal',
        'fieldConfig' => [
            'template' => "{label}\n<div class=\"col-lg-3\">{input}</div>\n<div class=\"col-lg-8\">{error}</div>",
            'labelOptions' => ['class' => 'col-lg-1 col-form-label'],
        ],
    ]); ?>        

        <?= $form->field($model, 'month')->textInput(['autofocus' => true]) ?>

        <?= $form->field($model, 'year')->textInput() ?>

        <?= $form->field($model, 'filePath')->textInput(['style'=>'width:600px']) ?>

        <div class="form-group">
            <div class="offset-lg-1 col-lg-11">
                <?= Html::submitButton('Get Data', ['id' => 'getData', 'class' => 'btn btn-primary', 'name' => 'getData', 'onClick' => 'myFunction();'] ) ?>                                

                <div id="spinner" class="text-left align-items-center" style="width: 100%; height: 13rem; display: none">
                    <p><?php echo ' '; ?></p>
                    <p><?php echo ' '; ?></p>
                    <p>
                        <button class="btn btn-info" type="button" style="width: 100%; height: 6rem;" disabled >
                            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                Loading Zoho Timesheet data, please wait....
                        </button>
                        </p>
                </div>

            </div>
        </div>      
        

        <script>
            function myFunction() {
                document.getElementById("getData").disabled = true;
                document.getElementById("spinner").style.display = "block";                
            }
        </script>

    <?php ActiveForm::end(); ?>

