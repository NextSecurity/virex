<?php

class StatisticsController extends Controller {

    public function actionSharedfiles() {
        if (isset($_GET['ajaxGetChart'])) {
            if (!Yii::app()->request->isPostRequest)
                throw new CHttpException(400, 'Invalid request. Please do not repeat this request again.');
            $postData = $_POST["PermanentFtpStatistics"];
            if (strtotime($postData['start']) < strtotime('2010-12-01'))
                throw new CHttpException(400, 'Invalid request. Please do not repeat this request again.');

            $qJoins = '';
            //CONSTRUCT THE QUERY COMPONENTS:
            switch ($postData['type']) {
                case 'file_number':
                    $qValue = array('Samples' => 'SUM(`files_number_psf`)', 'Archives' => 'SUM(`archives_number_psf`)');
                    $FCyLabel = 'Number';
                    $FCTitle = 'Upload(FTP) Statistics';
                    $FCType = 'FCF_MSBar2D';
                    break;
                case 'sample_size':
                    $qValue = array('Average Sample Size' => '(SUM(`files_size_psf`) / SUM(`files_number_psf`) / 1024)');
                    $FCyLabel = 'Size (KB)';
                    $FCTitle = 'Upload(FTP) Statistics';
                    $FCType = 'FCF_MSBar2D';
                    break;
                case 'archive_size':
                    $qValue = array('Average Archive Size' => '(SUM(`files_size_psf`) / SUM(`archives_number_psf`) / 1048576)');
                    $FCyLabel = 'Size (MB)';
                    $FCTitle = 'Upload(FTP) Statistics';
                    $FCType = 'FCF_MSBar2D';
                    break;
                case 'total_file_size':
                    $qValue = array('Samples Size(MB)' => '(SUM(`files_size_psf`) / 1000000)', 'Samples Number' => 'SUM(`files_number_psf`)');
                    $FCyLabel = '';
                    $FCTitle = 'Upload(FTP) Statistics';
                    $FCType = 'FCF_MSBar2D';
                    break;
                default:
                    throw new CHttpException(400, 'Invalid request. Please do not repeat this request again.');
            }
            $FCxAllLabels = array();
            $startTime = strtotime($postData['start'] . ' 00:00:00');
            $endTime = strtotime($postData['end'] . ' 23:59:59');
            $curTime = $startTime;
            switch ($postData['group']) {
                case 'hour':
                    $qLabel = "hour_psf";
                    $qGroup = $qLabel;
                    $qOrder = $qLabel . ' ASC';
                    $FCxLabel = 'Hour Of The Day';
                    for ($i = 0; $i <= 23; $i++) {
                        $FCxAllLabels[] = $i;
                    }
                    break;
                case 'day':
                    $qLabel = "date_psf";
                    $qGroup = $qLabel;
                    $qOrder = $qLabel . ' ASC';
                    $FCxLabel = 'Date';
                    do {
                        $FCxAllLabels[] = date('Y-m-d', $curTime);
                        $curTime = strtotime('+ 1 day', $curTime);
                    } while ($curTime < $endTime);
                    break;
                case 'week':
                    $qLabel = "CONCAT('w', WEEK(date_psf))";
                    $qGroup = "WEEK(date_psf)";
                    $qOrder = "DATE(date_psf) ASC";
                    $FCxLabel = 'Week Of The Year';
                    break;
                case 'month':
                    $qLabel = "MONTH(date_psf)";
                    $qGroup = $qLabel;
                    $qOrder = "DATE(date_psf) ASC";
                    $FCxLabel = 'Month Of The Year';
                    break;
                default:
                    throw new CHttpException(400, 'Invalid request. Please do not repeat this request again.');
            }
            $qDateWhere = ' `date_psf`>=:start AND `date_psf`<=:end ';
            $qParams = array('start' => $postData['start'], 'end' => $postData['end']);

            $FCNames = array_keys($qValue);
            $FCxLabels = array();
            $FCData = array();

            $qSelect = '';
            $fieldCount = count($qValue);
            $qValue = array_values($qValue);
            for ($i = 0; $i < $fieldCount; $i++)
                $qSelect.="{$qValue[$i]} AS `value{$i}`, ";

            $query = "SELECT {$qSelect} {$qLabel} AS `label` FROM `permanent_statistics_ftp_psf` WHERE {$qDateWhere} GROUP BY {$qGroup} ORDER BY {$qOrder}";
            $command = Yii::app()->db->createCommand($query);
            foreach ($qParams as $k => $v) {
                $command->bindValue($k, $v);
            }
            $dataReader = $command->queryAll();
            foreach ($dataReader as $row) {
                $FCxLabels[] = $row['label'];
                for ($i = 0; $i < $fieldCount; $i++)
                    $FCData[$i][$row['label']] = $row["value{$i}"];
            }
            if (count($FCxAllLabels)) {
                $FCxLabels = $FCxAllLabels;
            } else {
                $FCxLabels = array_unique($FCxLabels);
            }

            //populate any missing values, if perhaps we get the data from multiple sources
            foreach ($FCxLabels as $label) {
                for ($i = 0; $i < $fieldCount; $i++)
                    if (!isset($FCData[$i][$label]))
                        $FCData[$i][$label] = 0;
            }
            foreach ($FCData as &$data)
                ksort($data);

            //build the final data array
            $FCDataArray = array();
            if (!empty($FCData))
                for ($i = 0; $i < $fieldCount; $i++) {
                    $FCDataArray[$FCNames[$i]] = array_values($FCData[$i]);
                }

            $FC = $this->beginWidget('application.extensions.fusionchart.FusionChart', array(
                'id' => 'UrlsChart',
                'chartName' => $FCType,
                'width' => 800,
                'height' => 25 * count($FCxLabels) + 100,
                'chartOptions' => array(
                    'caption' => $FCTitle,
                    'yAxisName' => $FCyLabel,
                    'xAxisName' => $FCxLabel,
                    'showValues' => 0,
                    'decimalPrecision' => 0,
                    'formatNumberScale' => 0,
                    'showDivLineValues' => 1,
                    'rotateLabels' => 1,
                    'slantLabels' => 1,
                    'numDivLines' => 3,
//                    'canvasPadding' => 10,
//                    'toolTipSepChar' => ' : ',
//                    'palette' => 3,
//                    'captionPadding' => 30,
                ),
                'chartData' => array('categories' => $FCxLabels, 'sets' => $FCDataArray),
                    ));

            $FC->publishAssets();
            echo $FC->createJsCode();

            Yii::app()->end();
        }//end::ajax chart call

        $stats = new PermanentFtpStatistics;
        $stats->group = 'day';
        $FC = new FusionChart();
        $FC->publishAssets();
        $FC->registerClientScripts(true);

        if (!isset($stats->start))
            $stats->start = date('Y-m-d', strtotime(' - 7 days'));
        if (!isset($stats->end))
            $stats->end = date('Y-m-d', strtotime(' - 1 days'));
        $this->render('sharedfiles', array('model' => $stats));
    }

    public function actionTrafic() {
        if (isset($_GET['ajaxGetChart'])) {
            if (!Yii::app()->request->isPostRequest)
                throw new CHttpException(400, 'Invalid request. Please do not repeat this request again.');
            $postData = $_POST["PermanentUserStatistics"];
            if (strtotime($postData['start']) < strtotime('2010-12-01'))
                throw new CHttpException(400, 'Invalid request. Please do not repeat this request again.');
            $qJoins = '';
            //CONSTRUCT THE QUERY COMPONENTS:
            switch ($postData['type']) {
                case 'file_number':
                    $qValue = array('Requested files' => 'SUM(`files_in_list_count_psu`)', 'Downloaded files' => 'SUM(`files_number_psu`)', 'New files' => 'SUM(`files_unique_number_psu`)');
                    $FCyLabel = 'Number of files';
                    $FCTitle = 'Traffic';
//                    $FCType = 'FCF_MSArea2D';
                    $FCType = 'FCF_MSBar2D';
                    break;
                case 'file_size':
                    $qValue = array('Data size(MB)' => 'SUM(`files_size_psu`)/1000000', 'Files Number' => 'SUM(`files_number_psu`)',);
                    $FCyLabel = '';
                    $FCTitle = 'Traffic';
                    $FCType = 'FCF_MSBar2D';
                    break;

                default:
                    throw new CHttpException(400, 'Invalid request. Please do not repeat this request again.');
            }

            $tz = isset(Yii::app()->user->tz) ? Yii::app()->user->tz : '';

            $FCxAllLabels = array();
            $startTime = $postData['start'];
            $endTime = $postData['end'];
            $curTime = $startTime;
            switch ($postData['group']) {
                case 'hour':
                    $qLabel = "hour_psu";
                    $qGroup = $qLabel;
                    $qOrder = $qLabel . ' ASC';
                    $FCxLabel = 'Hour Of The Day';
                    for ($i = 0; $i <= 23; $i++) {
                        $FCxAllLabels[] = $i;
                    }
                    break;
                case 'day':
                    $qLabel = "date_psu";
                    $qGroup = $qLabel;
                    $qOrder = $qLabel . ' ASC';
                    $FCxLabel = 'Date';
                    do {
                        $FCxAllLabels[] = $curTime;
                        $curTime = date('Y-m-d', strtotime('+ 1 day', strtotime($curTime)));
                    } while ($curTime <= $endTime);
                    break;
                case 'week':
                    $qLabel = "CONCAT('w', WEEK(date_psu))";
                    $qGroup = "WEEK(date_psu)";
                    $qOrder = "date_psu ASC";
                    $FCxLabel = 'Week Of The Year';
                    break;
                case 'month':
                    $qLabel = "MONTH(date_psu)";
                    $qGroup = $qLabel;
                    $qOrder = "DATE(date_psu) ASC";
                    $FCxLabel = 'Month Of The Year';
                    break;
                case 'user':
                    $qLabel = "name_usr";
                    $qGroup = $qLabel;
                    $qOrder = "name_usr ASC";
                    $FCxLabel = 'User';
                    $FCType = 'FCF_MSColumn3D';
                    break;
                default:
                    throw new CHttpException(400, 'Invalid request. Please do not repeat this request again.');
            }
            $qDateWhere = ' `date_psu`>=:start AND `date_psu`<=:end ';
            $qParams = array('start' => $postData['start'], 'end' => $postData['end']);

            $qUserWhere = '';
            if ($postData['group'] == 'user') {
                $qJoins.= ' INNER JOIN external_users_usr ON (idusr_psu  = id_usr)';
            }
            if ($postData['idusr_psu']) {
                $qUserWhere = ' AND idusr_psu=' . (int) $postData['idusr_psu'];
            }
            $FCNames = array_keys($qValue);
            $FCxLabels = array();
            $FCData = array();

            $qSelect = '';
            $fieldCount = count($qValue);
            $qValue = array_values($qValue);
            for ($i = 0; $i < $fieldCount; $i++)
                $qSelect.="{$qValue[$i]} AS `value{$i}`, ";

            $query = "SELECT {$qSelect} {$qLabel} AS `label` FROM `permanent_statistics_user_psu` {$qJoins} WHERE {$qDateWhere}{$qUserWhere} GROUP BY {$qGroup} ORDER BY {$qOrder}";
            $command = Yii::app()->db->createCommand($query);
            $dataReader = $command->query($qParams);
            while (($row = $dataReader->read()) !== false) {
                $FCxLabels[] = $row['label'];
                for ($i = 0; $i < $fieldCount; $i++)
                    $FCData[$i][$row['label']] = $row["value{$i}"];
            }


            if (count($FCxAllLabels)) {
                $FCxLabels = $FCxAllLabels;
            } else {
                $FCxLabels = array_unique($FCxLabels);
            }

            //populate any missing values, if perhaps we get the data from multiple sources
            foreach ($FCxLabels as $label) {
                for ($i = 0; $i < $fieldCount; $i++)
                    if (!isset($FCData[$i][$label]))
                        $FCData[$i][$label] = 0;
            }
            foreach ($FCData as &$data)
                ksort($data);

            //build the final data array
            $FCDataArray = array();
            if (!empty($FCData))
                for ($i = 0; $i < $fieldCount; $i++) {
                    $FCDataArray[$FCNames[$i]] = array_values($FCData[$i]);
                }

            $FC = $this->beginWidget('application.extensions.fusionchart.FusionChart', array(
                'id' => 'UrlsChart',
                'chartName' => $FCType,
                'width' => 800,
                'height' => 25 * count($FCxLabels) + 100,
                'chartOptions' => array(
                    'caption' => $FCTitle,
                    'yAxisName' => $FCyLabel,
                    'xAxisName' => $FCxLabel,
                    'showValues' => 0,
                    'decimalPrecision' => 0,
//                    'formatNumberScale' => 0,
                    'showDivLineValues' => 1,
                    'rotateLabels' => 1,
                    'slantLabels' => 1,
                    'numDivLines' => 3,
//                    'canvasPadding' => 10,
//                    'toolTipSepChar' => ' : ',
//                    'palette' => 3,
//                    'captionPadding' => 30,
                ),
                'chartData' => array('categories' => $FCxLabels, 'sets' => $FCDataArray),
                    ));

            $FC->publishAssets();
            echo $FC->createJsCode();

            Yii::app()->end();
        }//end::ajax chart call

        $stats = new PermanentUserStatistics;
        $stats->group = 'day';
        $FC = new FusionChart();
        $FC->publishAssets();
        $FC->registerClientScripts(true);
        $users = Yii::app()->db->createCommand("SELECT name_usr, company_usr, id_usr FROM external_users_usr")->queryAll();
        $selectUsers = array();
        $selectUsers[0] = 'All';
        foreach ($users as $u) {
            $selectUsers[$u['id_usr']] = $u['name_usr'] . '(' . $u['company_usr'] . ')';
        }
        if (!isset($stats->start))
            $stats->start = date('Y-m-d', strtotime(' - 7 days'));
        if (!isset($stats->end))
            $stats->end = date('Y-m-d', strtotime(' - 1 days'));
        $this->render('trafic', array('model' => $stats, 'select_users' => $selectUsers));
    }

}