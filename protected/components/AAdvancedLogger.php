
<?php

define('AAL_ERROR', 1);
define('AAL_MESSAGE', 2);
define('AAL_NOTICE', 3);
define('AAL_STEP', 4);
define('AAL_CONTINUE', 5);
define('AAL_CONTINUE_ERROR', 6);
define('AAL_CONTINUE_OK', 7);
define('AAL_CONTINUE_BROWN', 7);
define('AAL_CONTINUE_CYAN', 7);

class AAdvancedLogger {

    private static $logHistory;
    private static $lineLog;
    private static $currentParent = false;
    private static $currentLines = array(-1);
    private static $lastVariable = array(false);
    private static $currentDeepPos = 0;
    public static $color;
    // settings send from ConsoleCommand to show log(errors are visible even if debug is not active but in this case an email is also send)
    public static $advancedDebug = false;
    public static $debug = false;

    public static function startSublevel() {
        if (self::$currentParent) {
            if ((self::$currentParent['items']) && (count(self::$currentParent['items']))) {
                $parent = &self::$currentParent;
                end(self::$currentParent['items']);
                $endKey = key(self::$currentParent['items']);
                reset(self::$currentParent['items']);
                self::$currentParent = &self::$currentParent['items'][$endKey];
                self::$currentParent['items'] = array();
                self::$currentParent['parent'] = &$parent;
                self::$currentDeepPos++;
                self::$currentLines[self::$currentDeepPos] = -1;
            } else {
                die('NOT GOOD! there is no parent to start a sublevel');
            }
        } else {
            if ((self::$logHistory['items']) && (count(self::$logHistory['items']))) {
                end(self::$logHistory['items']);
                $endKey = key(self::$logHistory['items']);
                reset(self::$logHistory['items']);
                self::$currentParent = &self::$logHistory['items'][$endKey];
                self::$currentParent['items'] = array();
                self::$currentParent['parent'] = &self::$logHistory;
                self::$currentDeepPos++;
                self::$currentLines[self::$currentDeepPos] = -1;
            } else {
                die('NOT GOOD! there is no parent to start a sublevel');
            }
        }
    }

    public static function endSublevel() {
        if (!self::$currentParent) {
            die('NOT GOOD! There is no sublevel open');
        }
        self::$currentParent = &self::$currentParent['parent'];
        self::$currentLines[self::$currentDeepPos] = -1; // restart for next sublevel
        self::$currentDeepPos--;
    }

    public static function log($message, $type = AAL_MESSAGE, $noBr = false, $notime = false) {
        if ($notime) {
            $date = false;
        } else {
            $date = date('Y-m-d H:i:s');
        }
        if (!self::$currentParent) {
            self::$logHistory['items'][] = array('message' => $message, 'type' => $type, 'items' => array(), 'time' => $date, 'no_br' => $noBr);
            self::$lastVariable[self::$currentDeepPos] = false;
            if (self::$debug || self::$advancedDebug) {
                self::show(); // show if debug is active
            } elseif (($type == AAL_ERROR) || ($type == AAL_CONTINUE_ERROR)) {
                self::show(); // or an error is detected
            }
        } else {
            self::$currentParent['items'][] = array('message' => $message, 'type' => $type, 'items' => array(), 'time' => $date, 'no_br' => $noBr);
            self::$lastVariable[self::$currentDeepPos] = false;
            if (($type == AAL_ERROR) || ($type == AAL_CONTINUE_ERROR)) {
                self::show(); // show if an error is detected
            }
            if (self::$advancedDebug) {
                self::show(); // or advanced debug is active
            }
            if (!isset(self::$currentParent['parent'])) {
                if (self::$debug || self::$advancedDebug) {
                    self::show(); // or is first level and debug is active
                }
            }
        }
    }

    public static function variable($name, $value) {
        if (!self::$currentParent) {
            if (count(self::$logHistory['items'])) {
                end(self::$logHistory['items']);
                $endKey = key(self::$logHistory['items']);
                reset(self::$logHistory['items']);
                self::$logHistory['items'][$endKey]['variables'][$name] = $value;
            } else {
                self::$logHistory['variables'][$name] = $value;
            }
        } else {
            if (count(self::$currentParent['items'])) {
                end(self::$currentParent['items']);
                $endKey = key(self::$currentParent['items']);
                reset(self::$currentParent['items']);
                self::$currentParent['items'][$endKey]['variables'][$name] = $value;
            } else {
                self::$currentParent['variables'][$name] = $value;
            }
        }
        if (self::$advancedDebug) {
            self::show();
        }
    }

    private static $timeSpace = '                     ';

    private static function wrt($text, $date = '', $no_br = false) {
        $br = "\n";
        if ($no_br) {
            $br = "";
        }
        if ($date) {
            echo '[' . $date . ']' . $text . $br;
        } else {
            echo $text . $br;
        }
    }

    public static function show() {
        if (self::$currentLines[0] == -1) {
            if (isset(self::$logHistory['variables'])) {
                if (!self::$lastVariable[0]) {
                    $show = true;
                } else {
                    $show = false;
                }
                foreach (self::$logHistory['variables'] as $k => $v) {
                    if ($show) {
                        self::wrt(self::$timeSpace . '::' . $k . ' = ' . $v);
                        self::$lastVariable[0] = $k;
                    } else {
                        if ($k == self::$lastVariable[0]) {
                            $show = true;
                        }
                    }
                }
            }
        }
        $i = false;
        end(self::$logHistory['items']);
        $endKey = key(self::$logHistory['items']);
        reset(self::$logHistory['items']);
        foreach (self::$logHistory['items'] as $k => $i) {
            if (self::$currentLines[0] < $k) {
                if ($i['type'] == AAL_CONTINUE) {
                    self::wrt($i['message']);
                } elseif ($i['type'] == AAL_CONTINUE_ERROR) {
                    self::wrt(self::$color->red($i['message']));
                } elseif ($i['type'] == AAL_CONTINUE_OK) {
                    self::wrt(self::$color->green($i['message']));
                } elseif ($i['type'] == AAL_CONTINUE_BROWN) {
                    self::wrt(self::$color->brown($i['message']));
                } elseif ($i['type'] == AAL_CONTINUE_CYAN) {
                    self::wrt(self::$color->cyan($i['message']));
                } elseif ($i['type'] == AAL_STEP) {
                    self::wrt(self::$color->cyan('>>>>> ' . $i['message']));
                } elseif ($i['type'] == AAL_ERROR) {
                    self::wrt(self::$color->red($i['message']), $i['time'], $i['no_br']);
                } else {
                    self::wrt($i['message'], $i['time'], $i['no_br']);
                }
                self::$currentLines[0] = $k;
            }
            if ($k != $endKey) {
                unset(self::$logHistory['items'][$k]);
            }
        }
        if ($i) {
            self::show_sublevel($i, 1, '|->');
        }
    }

    private static function show_sublevel($level, $lvNumber, $prefix) {
        if (!isset(self::$currentLines[$lvNumber])) {
            self::$currentLines[$lvNumber] = -1; // nothing to show
        }
        if (self::$currentLines[$lvNumber] == -1) {
            if (isset($level['variables'])) {
                if (@!self::$lastVariable[$lvNumber]) {
                    $show = true;
                } else {
                    $show = false;
                }
                foreach ($level['variables'] as $k => $v) {
                    if ($show) {
                        self::wrt(self::$timeSpace . $prefix . '::' . $k . ' = ' . $v);
                        self::$lastVariable[$lvNumber] = $k;
                    } else {
                        if ($k == self::$lastVariable[$lvNumber]) {
                            $show = true;
                        }
                    }
                }
            }
        }
        $i = false;
        foreach ($level['items'] as $k => $i) {
            if (self::$currentLines[$lvNumber] < $k) {
                if ($i['type'] == AAL_CONTINUE) {
                    self::wrt($i['message']);
                } elseif ($i['type'] == AAL_CONTINUE_ERROR) {
                    self::wrt(self::$color->red($i['message']));
                } elseif ($i['type'] == AAL_CONTINUE_OK) {
                    self::wrt(self::$color->green($i['message']));
                } elseif ($i['type'] == AAL_CONTINUE_BROWN) {
                    self::wrt(self::$color->brown($i['message']));
                } elseif ($i['type'] == AAL_CONTINUE_CYAN) {
                    self::wrt(self::$color->cyan($i['message']));
                } elseif ($i['type'] == AAL_STEP) {
                    self::wrt(self::$color->light_gray('>>>>> ' . $i['message']));
                } elseif ($i['type'] == AAL_ERROR) {
                    self::wrt(self::$color->red($i['message']), $i['time'], $i['no_br']);
                } else {
                    self::wrt($i['message'], $i['time'], $i['no_br']);
                }
                self::$currentLines[$lvNumber] = $k;
            }
        }
        if ($i) {
            self::show_sublevel($i, $lvNumber + 1, '|-' . $prefix);
        }
    }

}

class Colors {

    private $foreground_colors = array();
    private $background_colors = array();

    public function __construct() {
        // Set up shell colors
        $this->foreground_colors['black'] = '0;30';
        $this->foreground_colors['dark_gray'] = '1;30';
        $this->foreground_colors['blue'] = '0;34';
        $this->foreground_colors['light_blue'] = '1;34';
        $this->foreground_colors['green'] = '0;32';
        $this->foreground_colors['light_green'] = '1;32';
        $this->foreground_colors['cyan'] = '0;36';
        $this->foreground_colors['light_cyan'] = '1;36';
        $this->foreground_colors['red'] = '0;31';
        $this->foreground_colors['light_red'] = '1;31';
        $this->foreground_colors['purple'] = '0;35';
        $this->foreground_colors['light_purple'] = '1;35';
        $this->foreground_colors['brown'] = '0;33';
        $this->foreground_colors['yellow'] = '1;33';
        $this->foreground_colors['light_gray'] = '0;37';
        $this->foreground_colors['white'] = '1;37';

        $this->background_colors['black'] = '40';
        $this->background_colors['red'] = '41';
        $this->background_colors['green'] = '42';
        $this->background_colors['yellow'] = '43';
        $this->background_colors['blue'] = '44';
        $this->background_colors['magenta'] = '45';
        $this->background_colors['cyan'] = '46';
        $this->background_colors['light_gray'] = '47';
    }

    /// added by Mirel
    public function __call($color, $params) {
        $text = (isset($params[0])) ? $params[0] : '';
        $background = (isset($params[1])) ? $params[1] : '';
        if (!isset($this->background_colors[$background])) {
            $background = null;
        }
        if (!isset($this->foreground_colors[$color])) {
            $color = null;
        }
        return $this->getColoredString($text, $color, $background);
    }

    // Returns colored string
    public function getColoredString($string, $foreground_color = null, $background_color = null) {
        $colored_string = "";
        // Check if given foreground color found
        if (isset($this->foreground_colors[$foreground_color])) {
            $colored_string .= "\033[" . $this->foreground_colors[$foreground_color] . "m";
        }
        // Check if given background color found
        if (isset($this->background_colors[$background_color])) {
            $colored_string .= "\033[" . $this->background_colors[$background_color] . "m";
        }

        // Add string and end coloring
        $colored_string .= $string . "\033[0m";

        return $colored_string;
    }

    // Returns all foreground color names
    public function getForegroundColors() {
        return array_keys($this->foreground_colors);
    }

    // Returns all background color names
    public function getBackgroundColors() {
        return array_keys($this->background_colors);
    }

}

AAdvancedLogger::$color = new Colors;