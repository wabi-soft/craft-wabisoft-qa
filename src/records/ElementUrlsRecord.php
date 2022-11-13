<?php

namespace wabisoft\qa\records;

use craft\db\ActiveRecord;

class ElementUrlsRecord extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%wabisoft_qa_element_urls}}';
    }
}
