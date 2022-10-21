<?php

namespace wabisoft\qa\records;

use craft\db\ActiveRecord;

class BrokenLinksRecord extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%wabisoft_qa_broken_links}}';
    }
}
