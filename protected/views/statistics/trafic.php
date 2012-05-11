<style type="text/css">
    div.row h4 {
        margin-bottom: 3px;
    }

    div.form div.inlinerow {
        float: left;
        margin-right: 15px;
    }

    .dateShortcut {
        margin-right: 6px;
    }
</style>

<?php $this->headlineText = "Traffic Statistics"; ?>

<div class="form" id="formDiv" style="display: inline-block;">
    <?php echo CHtml::beginForm('', 'POST', array('id' => 'chartForm')); ?>
    <div>
        <div class="row inlinerow">
            <h4><?php echo CHtml::activeLabel($model, 'type'); ?></h4>
            <?php
            echo CHtml::activeDropDownList($model, 'type', array(
                'file_number' => 'Number of files',
                'file_size' => 'File size',
            ));
            ?>
        </div>

        <div class="row inlinerow">
            <h4><?php echo CHtml::activeLabel($model, 'group'); ?></h4>
            <?php echo CHtml::activeDropDownList($model, 'group', array('hour' => 'Hour', 'day' => 'Day', 'week' => 'Week', 'month' => 'Month', 'user' => 'User'), array('style' => 'width: 105px;')); ?>
        </div>

        <div class="row inlinerow">
            <h4><?php echo CHtml::activeLabel($model, 'idusr_psu'); ?></h4>
            <?php echo CHtml::activeDropDownList($model, 'idusr_psu', $select_users, array('style' => 'width: 105px;')); ?>
        </div>

        <div class="row inlinerow" style="margin:0;">

            <div class="row inlinerow">
                <h4><?php echo CHtml::activeLabel($model, 'start'); ?></h4>

                <?php
                $this->widget('CJuiDatePicker', array(
                    'name' => 'PermanentUserStatistics[start]',
                    'value' => $model->start,
                    // additional javascript options for the date picker plugin
                    'options' => array(
                        'changeMonth' => true,
                        'changeYear' => true,
                        'maxDate' => '+0',
                        'minDate' => '2011-01-12',
                        'dateFormat' => 'yy-mm-dd',
                        'showAnim' => 'fold',
                        'style' => 'margin-bottom: 0; width: 110px;',
                    ),
                    'htmlOptions' => array('style' => 'cursor:pointer; background:url(/images/icons/calendar.png) 98% center no-repeat #F0F0F0; width: 143px;'),
                ));
                ?>
            </div>
            <div class="row inlinerow">
                <h4><?php echo CHtml::activeLabel($model, 'end'); ?></h4>

                <?php
                $this->widget('CJuiDatePicker', array(
                    'name' => 'PermanentUserStatistics[end]',
                    'value' => $model->end,
                    // additional javascript options for the date picker plugin
                    'options' => array(
                        'changeMonth' => true,
                        'changeYear' => true,
                        'minDate' => '2011-01-12',
                        'maxDate' => '+0',
                        'dateFormat' => 'yy-mm-dd',
                        'showAnim' => 'fold',
                        'style' => 'margin-bottom: 0; width: 110px;',
                    ),
                    'htmlOptions' => array('style' => 'cursor:pointer; background:url(/images/icons/calendar.png) 98% center no-repeat #F0F0F0; width: 143px;'),
                ));
                ?>
            </div>
            <br />
            <a href="#" class="dateShortcut" days="-1">yesterday</a>
            <a href="#" class="dateShortcut" days="-7">last 7 days</a>
            <a href="#" class="dateShortcut" days="-30">last 30 days</a>
        </div>

        <div class="row inlinerow">
            <?php echo CHtml::submitButton('Generate', array('id' => 'generateButton', 'style' => 'margin-top: 24px; min-width: 0;')); ?>
        </div>

    </div>

    <?php echo CHtml::endForm(); ?>
    <br clear="all"/>
    <?php echo MiscHelper::outputInfoBar('infoBar', 'Please click the generate button to refresh the chart!') ?>
</div><!-- form -->

<div id="UrlsChart" style="margin:auto;width:850px;min-height:400px;">
</div>

<div style="color: #888888;font-size: 0.8em;margin: 0;padding-top: 10px;">Charts powered by <a href="http://www.fusioncharts.com/free" target="_blank">Fusion Charts Free</a></div>

<script type="text/javascript">

    // do something on document ready
    $(function() {
        var btnBg = function(){ $('#infoBar').animate({'opacity':1},500); }

        $('#infoBar').css('opacity',0);

        $('#generateButton').click(function(){
            var btn = $(this);
            var btnText = $(this).val();

            $(btn).attr("disabled", "disabled");
            $(btn).val("Please wait...");

            $.ajax({
                type: 'POST',
                data: $("#chartForm").serialize(),
                url: '/statistics/trafic?ajaxGetChart=1',
                timeout: 5000,
                error: function () { alert('There is a problem with the charts!'); },
                success:function(data) { eval(data); },
                complete: function() { $(btn).attr("disabled", false).val(btnText).css('background-color',''); $('#infoBar').animate({'opacity':0},500); }
            });

            return false;
        });

        $('.dateShortcut').click(function(){
            $('#PermanentUserStatistics_start').datepicker('setDate',$(this).attr('days'));
            $('#PermanentUserStatistics_end').datepicker('setDate','-1');
            btnBg();
        });


        $("#chartForm").children().change(btnBg);

    });

</script>
