<?php

Yii::import('zii.widgets.grid.CButtonColumn');

class IconButtonColumn extends CButtonColumn {

    public function init() {
        $this->deleteButtonImageUrl = '/images/icons/cross.png';
        //$this->updateButtonImageUrl = '/images/icons/page_white_edit.png';
        //$this->viewButtonImageUrl = '/images/icons/page_white_magnify.png';
        $this->updateButtonImageUrl = '/images/icons/pencil.png';
        $this->viewButtonImageUrl = '/images/icons/magnifier.png';

        $this->htmlOptions = array('style' => 'text-align:center;');

        parent::init();
    }

}