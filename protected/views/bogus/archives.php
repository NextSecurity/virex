<?php $this->headlineText = 'Archives errors'; ?>
<?php

$this->widget('zii.widgets.grid.CGridView', array(
    'id' => 'grid',
    'dataProvider' => $model->search('samples'),
    'filter' => $model,
    'selectableRows' => 2,
    'ajaxUpdate' => false,
    'rowCssClassExpression' => '($row%2?"even ":"odd "). ($data->pending_action_bga?$data->pending_action_bga:"")',
    'cssFile' => Yii::app()->request->baseUrl . '/css/gridview/styles.css',
    'columns' => array(
        array(
            'name' => 'id_bga',
            'headerHtmlOptions' => array('style' => 'width:70px;'),
            'htmlOptions' => array('style' => 'text-align:right;'),
        ),
        array(
            'name' => 'name_bga',
        ),
        array(
            'name' => 'date_add_bga',
        ),
        array(
            'name' => 'error_message_bga',
        ),
        array(
            'name' => 'pending_action_bga',
            'value' => '($data->pending_action_bga=="Rescan")?"Reprocess":$data->pending_action_bga;',
            'headerHtmlOptions' => array('style' => 'width:100px;'),
            'htmlOptions' => array('style' => 'text-align:center;'),
        ),
        array(
            'class' => 'IconButtonColumn',
            'template' => '{unpack} {delete}',
            'buttons' => array(
                'unpack' => array(
                    'label' => 'Retry processing file',
                    'url' => 'Yii::app()->createUrl("/bogus/archives", array("id"=>$data->id_bga, "action"=>"unpack"))',
                    'imageUrl' => Yii::app()->request->baseUrl . '/images/icons/page_refresh.png',
                ),
                'delete' => array(
                    'url' => 'Yii::app()->createUrl("/bogus/archives", array("id"=>$data->id_bga, "action"=>"delete"))'
                )
            )
        )
    )
));
?>