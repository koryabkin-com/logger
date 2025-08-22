<?php
/**
 * Class for an advanced debugging system.
 *
 * @author      koryabkin.ev
 * @version     v1.0.4
 * @copyright   Copyright (c) 2025, koryabkin.ev
 **/

if (!class_exists('Logger')) {
    class Logger
    {
        private $activate = true;
        // Process ID
        private $pid = null;
        private $setpid = false;
        // Show time log
        private $show_time = false;
        private $mlogs = [];

        // Output options in the system: echo, file, function
        private $acctype = ['echo', 'file', 'function'];
        private $global_type = 'echo';

        private $skip_null = false;

        private $debug_lvl = [
            0 => 'FATAL',
            1 => 'ERROR',
            2 => 'WARN',
            3 => 'INFO',
            4 => 'DEBUG',
            5 => 'TRACE',
            6 => 'SYSTEM'
        ];
        private $default_lvl = 2;
        private $fatal_lvl = 0;

        private $func = '';

        // Output options in the system
        private $log_dir = '_tmp';
        private $add_dir = '';
        private $file_name = 'logger';

        /**
         * @param $pid
         * @param $isDebug
         * @param $db
         * @param $proxy
         */
        public function __construct($pid = null, $setSkip = false, $show_time = false)
        {
            $this->setPID($pid)->setSkip($setSkip)->showTime($show_time);
        }

        public function setActivate($activate = true)
        {
            $this->activate = !empty($activate);
        }

        /**
         * Function for setting a unique process identifier
         * @param string|null $pid - can accept any string value
         */
        public function setPID(string $pid = null)
        {
            if (!empty($pid) && $this->activate) {
                $this->pid = $pid;
                $this->setpid = true;
            }
            return $this;
        }

        public function showTime($show = true){
            if ($this->activate) {
                $this->show_time = !empty($show);
            }
            return $this;
        }

        /**
         * Output type setting function:
         * echo - simply outputs to the current context
         * file - saves to a file
         * function - passes the saved log text to another function
         * *
         * @param string $name - name of the method
         */
        public function setTypeLog(string $name = '', string $nameFunc = '')
        {
            if ($this->activate) {
                if (!empty($name) && in_array($name, $this->acctype)) {
                    if ($name == 'function') {
                        if (!empty($nameFunc) && function_exists($nameFunc)) {
                            $this->func = $nameFunc;
                        }
                        if (!empty($this->func)) {
                            $this->global_type = 'function';
                        }
                    } else {
                        $this->global_type = $name;
                    }
                }
            }
            return $this;
        }

        /**
         * Set the current debug level.
         * @param int $min_lvl - the starting level below which all provided log messages are output.
         */
        public function setDebugLvl(int $min_lvl = 2)
        {
            if ($this->activate) {
                if (($min_lvl >= 0) && ($min_lvl <= count($this->debug_lvl))) {
                    $this->default_lvl = $min_lvl;
                }
            }
            return $this;
        }

        /**
         * Setting the trigger level for the fatal log output that has been hidden.
         * @param int $max_lvl - the level below which the fatal output will be triggered.
         */
        public function setFatalLvl(int $max_lvl)
        {
            if ($this->activate) {
                if (($max_lvl >= -1) && ($max_lvl <= 1)) {
                    $this->fatal_lvl = $max_lvl;
                }
            }
            return $this;
        }

        /**
         * A function that skips all log messages for output and processing at the end of execution.
         * @param bool $global_skip - true or false
         */
        public function setSkip(bool $global_skip = true)
        {
            if ($this->activate) {
                $this->skip_null = !empty($global_skip);
            }
            return $this;
        }

        /**
         * This is a forced cache reset function for accumulated messages.
         * @return $this
         */
        public function resetLog()
        {
            $this->mlogs = [];
            return $this;
        }

        /**
         * Standard logging output function.
         * @param $msg - message or array, can be an object
         * @param int $debug_lvl - debug output level
         * @param array $options - [ 's' => '\n', 'one_line' => true, 'skip' => false, 'replace' => [] ]
         */
        public function log($msg, int $debug_lvl = 2, array $options = [])
        {
            if ($this->activate) {
                if ($debug_lvl <= $this->default_lvl) {
                    $_skip = $this->skip_null;
                    if (!$_skip) {
                        $_skip = !empty($options['skip']);
                    }
                    if ($_skip) {
                        if ($this->fatal_lvl != -1){
                            // If it was overstated, then we accumulate
                            $_msg = [
                                'time' => time(),
                                'msg' => $msg,
                                'lvl' => $debug_lvl,
                                'show' => true
                            ];
                            if (!empty($options)) {
                                $_msg['options'] = $options;
                            }
                            $this->mlogs[] = $_msg;
                        }
                    } else {
                        // If the minimum tolerance is met, then output the name
                        $text_log = '';
                        $show_end = false;
                        // If it was overstated, then we accumulate
                        $now_time = time();
                        $_msg = [
                            'time' => $now_time,
                            'msg' => $msg,
                            'lvl' => $debug_lvl
                        ];
                        if (!empty($options)) {
                            $_msg['options'] = $options;
                        }

                        if (!empty($this->mlogs)) {
                            if ($debug_lvl <= $this->fatal_lvl) {
                                $text_log .= "\n--- {$now_time} - [{$this->debug_lvl[$debug_lvl]}] ---\n";
                                foreach ($this->mlogs as $_log) {
                                    $text_log .= $this->render_msg($_log);
                                }
                                // Resetting the savings array
                                $this->resetLog();
                                $show_end = true;
                            } else {
                                foreach ($this->mlogs as $_log) {
                                    if (!empty($_log['show'])) {
                                        $text_log .= $this->render_msg($_log);
                                    }
                                }
                                // Resetting the savings array
                                $this->resetLog();
                            }
                        }

                        $text_log .= $this->render_msg($_msg);

                        if ($show_end) {
                            $text_log .= "-=-=-=-=-=-=-=-=-=-=-=-=-=-\n\n";
                        }

                        if (!empty($text_log)) {
                            $this->echoLog($text_log);
                        }
                    }
                } else {
                    if ($this->fatal_lvl != -1){
                        // If it was overstated, then we accumulate
                        $_msg = [
                            'time' => time(),
                            'msg' => $msg,
                            'lvl' => $debug_lvl
                        ];
                        if (!empty($options)) {
                            $_msg['options'] = $options;
                        }
                        $this->mlogs[] = $_msg;
                    }
                }
            }
        }

        /**
         * Private message rendering function
         * @param array $arr - array with data based on which the log generation will be performed
         */
        private function render_msg(array $arr)
        {
            if (empty($arr)) {
                return '';
            } else {
                $msg = '';
                if (!empty($arr['options']) && !empty($arr['options']['s'])){
                    $msg .= $arr['options']['s'];
                    unset($arr['options']['s']);
                }
                if ($this->show_time) {
                    $msg .= gmdate('[d-m-Y H:i:s]', $arr['time']);
                }
                if ($this->setpid) {
                    $msg .= "[$this->pid]\t\t";
                }

                $msg .= "[{$this->debug_lvl[$arr['lvl']]}]";

                if (is_scalar($arr['msg'])){
                    $msg_to_process = (string)$arr['msg'];
                } else {
                    $msg_to_process = print_r($arr['msg'], true);
                }

                // Checking options
                if (!empty($arr['options'])) {
                    // Replacement of substrings (optional)
                    if (!empty($arr['options']['replace'])) {
                        if (count($arr['options']['replace']) === 2) {
                            $msg_to_process = str_replace(
                                $arr['options']['replace'][0],
                                $arr['options']['replace'][1],
                                $msg_to_process
                            );
                        }
                    }

                    // Transformation into a single line (optional)
                    if (!empty($arr['options']['one_line'])) {
                        $msg_to_process = preg_replace('/\s+/', ' ', $msg_to_process);
                    }
                }

                return "{$msg} {$msg_to_process}\n";
            }
        }


        /**
         * A function that outputs logs according to standard formatting, ignores
         * @param $lvl - logging level, will return standard output if absent.
         */
        public function showLogs($lvl = null)
        {
            if ($this->activate) {
                if (!empty($this->mlogs)) {
                    if (!is_null($lvl)) {
                        $lvl = (int)$lvl;
                    }
                    $text_log = '';
                    $arr_msg = [];

                    foreach ($this->mlogs as $_log) {
                        if (!is_null($lvl)) {
                            if ($_log['lvl'] <= $lvl) {
                                $text_log .= $this->render_msg($_log);
                            }
                        } else {
                            if (!empty($_log['show'])) {
                                $log_skip = (!empty($_log['options']) && !empty($_log['options']['skip']));
                                if ($log_skip) {
                                    $arr_msg[] = $_log;
                                } else {
                                    $show_end = false;
                                    if (!empty($arr_msg)) {
                                        if ($_log['lvl'] <= $this->fatal_lvl) {
                                            $text_log .= "\n--- {$_log['time']} - [{$this->debug_lvl[$_log['lvl']]}] ---\n";
                                            foreach ($arr_msg as $a_log) {
                                                $text_log .= $this->render_msg($a_log);
                                            }
                                            $show_end = true;
                                        }
                                    }
                                    $text_log .= $this->render_msg($_log);
                                    if ($show_end) {
                                        $text_log .= "-=-=-=-=-=-=-=-=-=-=-=-=-=-\n\n";
                                    }
                                    $arr_msg = [];
                                }
                            } else {
                                $arr_msg[] = $_log;
                            }
                        }
                    }
                    $this->echoLog($text_log);
                }
            }
        }

        /**
         * Setting up an extended directory for writing
         * @param string $add_dir - directories for writing.
         */
        public function setDir(string $add_dir = '')
        {
            if (!empty($add_dir) && $this->activate) {
                // Allowed characters: letters, numbers, period, underscore, hyphen
                $add_dir = preg_replace('/[^\w\.\/\-]/', '', $add_dir);
                $add_dir = trim(preg_replace('/\/+/', '/', $add_dir), '/');
                if (!empty($add_dir)) {
                    if ($this->global_type == 'file') {
                        $this->showLogs();
                        // If we have progressed further after the cleaning, we check for availability.
                        $this->add_dir = $add_dir;
                        $dir_name = '/' . $this->log_dir . (!empty($this->add_dir) ? '/' . $this->add_dir : '');
                        // Checking for the existence of such a directive; if not, then we try to create it.
                        if (!is_dir($dir_name)) {
                            $oldUmask = umask(0);
                            mkdir($dir_name, 0777, true);
                            umask($oldUmask);
                        }
                    }
                }
            }
            return $this;
        }

        /**
         * Setting the name in which the recording needs to be made
         * @param string $file_name - name of the file for recording.
         */
        public function setDirName(string $file_name = '')
        {
            if (!empty($file_name) && $this->activate) {
                if ($this->global_type == 'file') {
                    $this->showLogs();
                }
                $this->file_name = $file_name;
            }
            return $this;
        }

        /**
         * Private version of the specified output in the system!
         * @param $text_log - the generated string for output.
         */
        private function echoLog($text_log)
        {
            if (!empty($text_log)) {
                if ($this->global_type == 'echo') {
                    echo $text_log;
                } elseif ($this->global_type == 'file') {
                    file_put_contents(
                        '/' . $this->log_dir . (!empty($this->add_dir) ? '/' . $this->add_dir : '') . '/' . $this->file_name . '.log',
                        $text_log,
                        FILE_APPEND
                    );
                } elseif ($this->global_type == 'function') {
                    ($this->func)($text_log);
                }
            }
        }
    }
}

$logger = !empty($logger) ? $logger : new Logger();