<?php

namespace app\models;

use Yii;
use yii\base\Model;

/**
 * LoginForm is the model behind the login form.
 *
 * @property-read User|null $user This property is read-only.
 *
 */
class ZohoDataForm extends Model
{
    public $month;
    public $year;
    public $filePath;

    /**
     * @return array the validation rules.
     */
    public function rules()
    {
        return [
            // username and password are both required
            [['month', 'year','filePath'], 'required'],
        ];
    }

   
}
