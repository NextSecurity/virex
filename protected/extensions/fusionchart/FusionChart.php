<?php

class FusionChart extends CWidget {

    var $chartsPath = 'Charts'; // Relative or absolute path.
    var $fusionChartsJsPath = 'JSClass/FusionCharts.js'; // Relative or absolute path.
    var $chartName = 'FCF_MSBar2D';
    var $width = '400', $height = '300';
    var $chartFile = '', $jsFile = ''; // internal vars
    var $chartData = array(), $chartOptions = array(), $htmlOptions = array(), $data = ''; // External vars. Should be passed from widget creation.
    var $colors = array('FFA500', '6B8E23', '6495ED', 'FF4500', '808000');

    public function init() {
        if (!$this->getId(false))
            $this->setId('chart');

        $this->data = CHtml::openTag('graph', $this->chartOptions);

        if (count($this->chartData['sets']) > 1 || strstr($this->chartName, 'Scroll')) {
            $this->data .= "<categories>";
            foreach ($this->chartData['categories'] as $category) {
                $this->data .= "<category name='" . $category . "' />";
            }
            $this->data .= "</categories>";
        }
        $i = 0;
        foreach ($this->chartData['sets'] as $setlabel => $setdata) {
            if (count($this->chartData['sets']) > 1 || strstr($this->chartName, 'Scroll'))
                $this->data .= "<dataset seriesName='" . $setlabel . "' color='" . $this->colors[$i++] . "'>";
            foreach ($setdata as $key => $value) {
                $this->data .= "<set" . (count($this->chartData['sets']) > 1 ? "" : " label='" . $this->chartData['categories'][$key] . "'") . " value='" . $value . "' />";
            }
            if (count($this->chartData['sets']) > 1 || strstr($this->chartName, 'Scroll'))
                $this->data .= "</dataset>";
        }
        $this->data .= CHtml::closeTag('graph');
        parent::init();
    }

    public function run() {
        // this method is called by CController::endWidget()
        $this->publishAssets();
        $this->registerClientScripts();
        $this->htmlOptions['id'] = $this->getId();
        echo "\n\n";
        echo CHtml::openTag('div', $this->htmlOptions) . "\n";
        echo 'The chart will appear within this DIV. This text will be replaced by the chart.' . "\n";
        echo CHtml::closeTag('div');
        // $this->renderChart();
        parent::run();

        echo "\n<!-- Fusion Chart " . $this->getId() . " -->\n";
    }

    /**
     * Publishes the assets
     */
    public function publishAssets() {
        if (substr($this->chartsPath, 0, 1) != '/')
            $this->chartsPath = dirname(__FILE__) . DIRECTORY_SEPARATOR . $this->chartsPath;

        if (substr($this->fusionChartsJsPath, 0, 1) != '/')
            $this->fusionChartsJsPath = dirname(__FILE__) . DIRECTORY_SEPARATOR . $this->fusionChartsJsPath;

        $this->chartFile = Yii::app()->getAssetManager()->publish($this->chartsPath . DIRECTORY_SEPARATOR . $this->chartName . '.swf');
        $this->jsFile = Yii::app()->getAssetManager()->publish($this->fusionChartsJsPath);
    }

    /**
     * Registers the external javascript files
     */
    public function registerClientScripts($headerOnly = false) {
        // add the script
        $cs = Yii::app()->getClientScript();
        $cs->registerScriptFile($this->jsFile, CClientScript::POS_HEAD);

        if (!$headerOnly) {
            $js = $this->createJsCode();
            $cs->registerScript('fusioncharts_' . $this->getId(), $js, CClientScript::POS_READY);
        }
    }

    public function createJsCode() {
        $js = '
	var FC' . $this->getId() . ' = new FusionCharts("' . $this->chartFile . '", "FC' . $this->getId() . '", "' . $this->width . '", "' . $this->height . '", "0", "0");
        FC' . $this->getId() . '.addParam("wmode","transparent");
        FC' . $this->getId() . '.setDataXML("' . addslashes($this->data) . '");
        FC' . $this->getId() . '.render("' . $this->getId() . '");
	';
        return $js;
    }

}