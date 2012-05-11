<?php

class BogusArchive extends CActiveRecord {

    public static $archivesFound = false;

    public static function model($className = __CLASS__) {
        return parent::model($className);
    }

    public function tableName() {
        return 'bogus_archives_bga';
    }

    public function rules() {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return array(
            array('name_bga, type_bga, date_add_bga', 'required'),
            array('id_bga, name_bga, type_bga, date_add_bga', 'safe', 'on' => 'search'),
        );
    }

    public function relations() {
        return array();
    }

    public function attributeLabels() {
        return array(
            'id_bga' => 'Id',
            'name_bga' => 'File name',
            'type_bga' => 'Type',
            'date_add_bga' => 'Last try',
            'pending_action_bga' => 'Pending action',
            'error_message_bga' => 'Error'
        );
    }

    public function search($what = 'samples') {
        $criteria = new CDbCriteria;
        $criteria->compare('id_bga', $this->id_bga, true);
        $criteria->compare('name_bga', $this->name_bga, true);
        $criteria->compare('type_bga', $this->type_bga, true);
        $criteria->compare('date_add_bga', $this->date_add_bga, true);
        if ($what == 'samples') {
            $criteria->compare('type_bga', '<>U');
        } else {
            $criteria->compare('type_bga', 'U');
        }
        return new CActiveDataProvider($this, array(
                    'criteria' => $criteria,
                ));
    }

    public static function unpackWhereCondition($condition) {
        $files = Yii::app()->db->createCommand("SELECT * FROM bogus_archives_bga WHERE {$condition}")->queryAll();
        ALogger::log('Found ' . count($files) . ' archives to process.');

        foreach ($files as $f) {
            $bogusPath = PathFinder::get(VIREX_INCOMING_PATH, $f['detection_bga'], 'bogus', true);
            $originalPath = PathFinder::get(VIREX_INCOMING_PATH, $f['detection_bga'], $f['type_bga'], true);
            try {
                rename($bogusPath . $f['id_bga'], $originalPath . $f['name_bga']);
            } catch (Exception $e) {
                ALogger::error($e->getMessage());
                $err = 1;
            }
            if (!isset($err)) {
                Yii::app()->db->createCommand("DELETE FROM bogus_archives_bga WHERE id_bga = " . $f['id_bga'])->execute();
            } else {
                unset($err);
            }
        }
    }

    public static function deleteWhereCondition($condition) {
        $files = Yii::app()->db->createCommand("SELECT * FROM bogus_archives_bga WHERE {$condition}")->queryAll();
        ALogger::log('Found ' . count($files) . ' archives to delete.');
        foreach ($files as $f) {
            $bogusPath = PathFinder::get(VIREX_INCOMING_PATH, $f['detection_bga'], 'bogus', true);
            try {
                unlink($bogusPath . $f['id_bga']);
            } catch (Exception $e) {
                ALogger::error($e->getMessage());
                $err = 1;
            }
            if (!isset($err)) {
                Yii::app()->db->createCommand("DELETE FROM bogus_archives_bga WHERE id_bga = " . $f['id_bga'])->execute();
            } else {
                unset($err);
            }
        }
    }

}