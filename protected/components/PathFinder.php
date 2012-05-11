<?php

class PathFinder {

    public static function get($base, $detection = 'detected', $type = 'daily', $trlS = false) {
        $dir = $base . DIRECTORY_SEPARATOR . $detection . DIRECTORY_SEPARATOR . $type;
        self::ensure($dir);
        return $dir . ($trlS ? DIRECTORY_SEPARATOR : '');
    }

    public static function ensure($dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }

}
