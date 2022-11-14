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
                    'timeToComplete' => $this->integer(),
                    'complete' => $this->boolean(),
                ]
            );
        }
        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%wabisoft_qa_element_urls}}');
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                '{{%wabisoft_qa_element_urls}}',
                [
                    'id' => $this->primaryKey(),
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
                    'url' => $this->string(),
                    'class' => $this->string(255),
                    'elementId' => $this->integer()->notNull(),
                    'runId' =>  $this->integer(),
                    'status' => $this->string(255),
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
                    'url' => $this->string(),
                    'runId' =>  $this->integer(),
                    'elementId' =>  $this->integer(),
                    'rechecked' => $this->boolean(),
                    'errorCode' => $this->string(255)
                ]
            );
        }


        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%wabisoft_qa_inline_links}}');
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                '{{%wabisoft_qa_inline_links}}',
                [
                    'id' => $this->primaryKey(),
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
                    'url' => $this->mediumText(),
                    'elementId' => $this->integer(),
                    'markup' => $this->mediumText(),
                    'foundOn' => $this->string(255),
                    'runId' =>  $this->integer(),
                    'rechecked' => $this->boolean(),
                    'status' => $this->string(255),
                    'broken' => $this->boolean(),
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
        $this->addForeignKey(
            $this->db->getForeignKeyName('{{%wabisoft_qa_broken_links}}', 'runId'),
            '{{%wabisoft_qa_broken_links}}',
            'elementId',
            '{{%elements}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
        $this->addForeignKey(
            $this->db->getForeignKeyName('{{%wabisoft_qa_element_urls}}', 'runId'),
            '{{%wabisoft_qa_element_urls}}',
            'runId',
            '{{%wabisoft_qa_runs}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
        $this->addForeignKey(
            $this->db->getForeignKeyName('{{%wabisoft_qa_element_urls}}', 'elementId'),
            '{{%wabisoft_qa_element_urls}}',
            'elementId',
            '{{%elements}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
        $this->addForeignKey(
            $this->db->getForeignKeyName('{{%wabisoft_qa_inline_links}}', 'runId'),
            '{{%wabisoft_qa_inline_links}}',
            'runId',
            '{{%wabisoft_qa_runs}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
        $this->addForeignKey(
            $this->db->getForeignKeyName('{{%wabisoft_qa_inline_links}}', 'elementId'),
            '{{%wabisoft_qa_inline_links}}',
            'elementId',
            '{{%elements}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }
    protected function removeTables()
    {
        $this->dropTableIfExists('{{%wabisoft_qa_broken_links}}');
        $this->dropTableIfExists('{{%wabisoft_qa_inline_links}}');
        $this->dropTableIfExists('{{%wabisoft_qa_element_urls}}');
        $this->dropTableIfExists('{{%wabisoft_qa_runs}}');

    }
}
