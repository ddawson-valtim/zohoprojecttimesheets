<?php

use yii\helpers\Html;
use yii\bootstrap4\ActiveForm;


$this->title = 'Zoho Project Timesheets';

?>
<div class="site-login">
    <h1><?= Html::encode($this->title) ?></h1>

    <p>Please enter the Month and Year to Pull Zoho Timesheet log data:</p>

    <?php $form = ActiveForm::begin([
        'id' => 'index-form',
        'layout' => 'horizontal',
        'fieldConfig' => [
            'template' => "{label}\n<div class=\"col-lg-3\">{input}</div>\n<div class=\"col-lg-8\">{error}</div>",
            'labelOptions' => ['class' => 'col-lg-1 col-form-label'],
        ],
    ]); ?>

        <?= $form->field($model, 'month')->textInput(['autofocus' => true]) ?>

        <?= $form->field($model, 'year')->passwordInput() ?>

        <div class="form-group">
            <div class="offset-lg-1 col-lg-11">
                <?= Html::submitButton('Get Data', ['class' => 'btn btn-primary', 'name' => 'getData-button']) ?>
            </div>
        </div>

    <?php ActiveForm::end(); ?>

</div>
