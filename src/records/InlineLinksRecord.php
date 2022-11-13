<?php

namespace wabisoft\qa\records;

use craft\db\ActiveRecord;

class InlineLinksRecord extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%wabisoft_qa_inline_links}}';
    }
}
