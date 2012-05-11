<?php

class SystemHelper {

    public static function getLoad() {
        if (function_exists('sys_getloadavg')) {
            $uptime = sys_getloadavg();
            $procs = trim(file_get_contents('/proc/cpuinfo'));
            return floor(($uptime[0] / substr_count($procs, 'processor')) * 100);
        } else {
            // windows
            exec('typeperf -sc 1 "\Processor(_Total)\% Processor Time"', $output);
            if (isset($output[2]) && strstr($output[2], ',')) {
                list(, $proc) = explode(',', $output[2]);
                return floor(trim($proc, '"'));
            }
        }
        return 0;
    }

    public static function emptyDir($dir) {
        if (!is_dir($dir)) {
            return false;
        }
        foreach (glob($dir . '/*') as $file) {
            if (is_dir($file)) {
                self::emptyDir($file);
                rmdir($file);
            } else {
                unlink($file);
            }
        }
    }

}
