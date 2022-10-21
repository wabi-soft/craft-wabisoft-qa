<?php

namespace wabisoft\qa\migrations;
use Craft;
use craft\config\DbConfig;
use craft\db\Migration;

class Install extends Migration
{
    public $driver;
    public function safeUp() {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        if ($this->createTables()) {
            $this->createIndexes();
            $this->addForeignKeys();
            Craft::$app->db->schema->refresh();
        }
        return true;
    }

    public function safeDown()
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        $this->removeTables();
        return true;
    }
    protected function createTables()
    {
        $tablesCreated = false;
        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%wabisoft_qa_runs}}');
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                '{{%wabisoft_qa_runs}}',
                [
                    'id' => $this->primaryKey(),
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
                    'type' => $this->string(255),
                    'checked' =>  $this->integer(),
                    'normal' =>  $this->integer(),
                    'error' =>  $this->integer(),
                    'complete' => $this->boolean(),
                ]
            );
        }
        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%wabisoft_qa_broken_links}}');
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                '{{%wabisoft_qa_broken_links}}',
                [
                    'id' => $this->primaryKey(),
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
                    'url' => $this->string(255),
                    'runId' =>  $this->integer(),
                    'rechecked' => $this->boolean(),
                    'errorCode' => $this->string(255)
                ]
            );
        }
        return $tablesCreated;
    }
    protected function createIndexes() {

    }
    protected function addForeignKeys()
    {
        $this->addForeignKey(
            $this->db->getForeignKeyName('{{%wabisoft_qa_broken_links}}', 'runId'),
            '{{%wabisoft_qa_broken_links}}',
            'runId',
            '{{%wabisoft_qa_runs}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }
    protected function removeTables()
    {
        $this->dropTableIfExists('{{%wabisoft_qa_broken_links}}');
        $this->dropTableIfExists('{{%wabisoft_qa_runs}}');
    }
}
