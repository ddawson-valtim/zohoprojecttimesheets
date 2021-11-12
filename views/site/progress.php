<?php


use yii\helpers\Html;
use yii\bootstrap4\ActiveForm;
use yii\bootstrap4\Progress;


$this->title = '';

?>
<div class="progress">
    <h1><?= Html::encode($this->title) ?></h1>

    <?php $form = ActiveForm::begin([
        'id' => 'progress-form',
        'layout' => 'horizontal',
        'fieldConfig' => [
            'template' => "{label}\n<div class=\"col-lg-3\">{input}</div>\n<div class=\"col-lg-8\">{error}</div>",
            'labelOptions' => ['class' => 'col-lg-1 col-form-label'],
        ],
    ]); ?>

        <?php  
            echo Progress::widget(
                [
                    'percent' => $model->percent,
                    'label' => $model->message,
                ]
            ); 
        ?>

    <?php ActiveForm::end(); ?>

</div>
