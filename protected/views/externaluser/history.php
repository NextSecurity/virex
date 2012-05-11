<?php $this->headlineText = 'Account history';?>
<div class="actionBar">
	<a class="icon back" href="<?php echo Yii::app()->createUrl('externaluser/update', array('id'=>$userId)); ?>" >Go back to user details</a>
	<a class="icon list" href="<?php echo Yii::app()->createUrl('externaluser/statistics', array('id'=>$userId)); ?>" >Show download statistics</a>
</div>
<?php $this->widget('zii.widgets.CListView', array(
	'dataProvider'=>$dataProvider,
	'itemView'=>'_view',
)); ?>
