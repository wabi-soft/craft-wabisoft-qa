<?php

namespace wabisoft\qa\records;

use craft\db\ActiveRecord;

class RunsRecord extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%wabisoft_qa_runs}}';
    }
}
