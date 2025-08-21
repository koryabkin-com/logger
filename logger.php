<?php
/**
 * Class for an advanced debugging system.
 *
 * @author      koryabkin.ev
 *  @version     v1.0.0
 *  @copyright   Copyright (c) 2025, koryabkin.ev
 **/

class Logger
{
    // Process ID
    private $pid = null;
    private $mlogs = [];

    // Output options in the system: echo, file, function
    private $acctype = ['echo', 'file', 'function'];
    private $global_type = 'echo';

    private $skip_null = false;

    private $debug_lvl = [ 0 => 'FATAL', 1 => 'ERROR', 2 => 'WARN', 3 => 'INFO', 4 => 'DEBUG', 5 => 'TRACE', 6 => 'SYSTEM'];
    private $default_lvl = 2;
    private $fatal_lvl = 1;

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
    public function __construct($pid = null, $setSkip = false)
    {
        $this->setPID($pid);
        $this->setSkip($setSkip);
    }

    /**
     * Function for setting a unique process identifier
     * @param string|null $pid - can accept any string value
     * @return void
     */
    public function setPID(string $pid = null){
        if (!empty($pid)){
            $this->pid = $pid;
        }
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
        if (!empty($name) && in_array($name, $this->acctype)){
            if ($name == 'function'){
                if (!empty($nameFunc) && function_exists($nameFunc)){
                    $this->func = $nameFunc;
                }
                if (!empty($this->func)){
                    $this->global_type = 'function';
                }
            } else {
                $this->global_type = $name;
            }
        }
        return $this;
    }

    /**
     * Set the current debug level.
     * @param int $min_lvl - the starting level below which all provided log messages are output.
     */
    public function setDebugLvl(int $min_lvl)
    {
        if (($min_lvl >= 0) && ($min_lvl <= count($this->debug_lvl))) {
            $this->default_lvl = $min_lvl;
        }
        return $this;
    }

    /**
     * Setting the trigger level for the fatal log output that has been hidden.
     * @param int $max_lvl - the level below which the fatal output will be triggered.
     */
    public function setFatalLvl(int $max_lvl)
    {
        if (($max_lvl >= 0) && ($max_lvl < $this->default_lvl)) {
            $this->fatal_lvl = $max_lvl;
        }
        return $this;
    }

    /**
     * A function that skips all log messages for output and processing at the end of execution.
     * @param bool $skip - true or false
     */
    public function setSkip(bool $skip = true)
    {
        $this->skip_null = !empty($skip);
        return $this;
    }

    /**
     * Standard logging output function.
     * @param $msg - message or array, can be an object
     * @param int $debug_lvl - debug output level
     * @param bool $skip - skip message for the next trigger
     * @param array $replace - if any replacement is needed
     */
    public function log($msg, int $debug_lvl = 2, bool $skip = false, array $replace = [])
    {
        $_skip = $this->skip_null;
        if (empty($_skip)){
            $_skip = !empty($skip);
        }
        $now_time = time();
        if ($debug_lvl <= $this->default_lvl) {
            // If the minimum tolerance is met, then output the name

            $text_log = '';
            if (empty($_skip)) {
                if (!empty($this->mlogs)) {
                    if ($debug_lvl <= $this->fatal_lvl) {
                        $text_log .= "\n--- {$now_time} - [{$this->debug_lvl[$debug_lvl]}] ---\n";
                        foreach ($this->mlogs as $_log) {
                            $text_log .= $this->render_log($_log);
                        }
                    }
                    if ($debug_lvl != $this->fatal_lvl) {
                        foreach ($this->mlogs as $_log) {
                            if (!empty($_log['show'])) {
                                $text_log .= $this->render_log($_log);
                            }
                        }
                    }
                }
            }

            // Execution of filling or enforcement
            $_msg = [
                'time' => $now_time,
                'msg' => $msg,
                'skip' => $skip,
                'lvl' => $debug_lvl
            ];
            if (!empty($replace)){
                $_msg['replace'] = $replace;
            }
            if (!empty($_skip)) {
                $_msg['show'] = true;
                $this->mlogs[] = $_msg;
            } else {
                $text_log .= $this->render_log($_msg);
            }

            if (empty($_skip)) {
                if (!empty($this->mlogs)) {
                    if ($debug_lvl <= $this->fatal_lvl) {
                        $text_log .= "--------------------------\n\n";
                        // Resetting the savings array
                        $this->mlogs = [];
                    }
                    if ($debug_lvl != $this->fatal_lvl) {
                        // Resetting the savings array
                        $this->mlogs = [];
                    }
                }
            }

            if (!empty($text_log)){
                $this->echoLog($text_log);
            }

        } else {
            // If it was overstated, then we accumulate
            $_msg = [
                'time' => $now_time,
                'msg' => $msg,
                'skip' => $skip,
                'lvl' => $debug_lvl
            ];
            if (!empty($replace)){
                $_msg['replace'] = $replace;
            }
            $this->mlogs[] = $_msg;
        }
    }

    /**
     * Private message rendering function
     * @param array $arr - array with data based on which the log generation will be performed
     */
    private function render_log(array $arr){
        if ($arr)
            $msg = gmdate('[d-m-Y H:i:s]');
        if (!empty($this->pid)){
            $msg .= "[$this->pid]\t\t";
        }
        $type = $this->debug_lvl[$arr['lvl']];
        $msg .= "[{$type}]";
        if (!empty($arr['msg'])){
            if (!empty($arr['msg']) && (is_array($arr['msg']) || is_object($arr['msg']))){
                ob_start();
                print_r($arr['msg']);
                $arr['msg'] = ob_get_clean();
            } else {
                if (is_scalar($arr['msg'])) {
                    $arr['msg'] = (string)$arr['msg'];
                } else {
                    ob_start();
                    var_dump($arr['msg']);
                    $arr['msg'] = ob_get_clean();
                }
            }
        } else {
            return '';
        }
        if (!empty($arr['replace']) && (count($arr['replace']) == 2)){
            $arr['msg'] = str_replace($arr['replace'][0],$arr['replace'][1], $arr['msg']);
        }
        return $msg. " {$arr['msg']} \n";
    }


    /**
     * A function that outputs logs according to standard formatting, ignores
     * @param $lvl - logging level, will return standard output if absent.
     */
    public function showLogs($lvl = null)
    {
        if (!empty($this->mlogs)){
            if (!is_null($lvl)){
                $lvl = (int)$lvl;
            }
            $text_log = '';
            $arr_msg = [];
            foreach ($this->mlogs as $_log){
                if (!is_null($lvl)){
                    if ($_log['lvl'] <= $lvl){
                        $text_log .= $this->render_log($_log);
                    }
                } else {
                    if (!empty($_log['show'])) {
                        if (empty($_log['skip'])) {
                            if (!empty($arr_msg)) {
                                if ($_log['lvl'] <= $this->fatal_lvl) {
                                    $text_log .= "\n--- {$_log['time']} - [{$this->debug_lvl[$_log['lvl']]}] ---\n";
                                    foreach ($arr_msg as $a_log) {
                                        $text_log .= $this->render_log($a_log);
                                    }
                                }
                            }
                        }
                        if (!empty($_log['skip'])) {
                            $arr_msg[] = $_log;
                        } else {
                            $text_log .= $this->render_log($_log);
                        }
                        if (empty($_log['skip'])) {
                            if (!empty($arr_msg)) {
                                if ($_log['lvl'] <= $this->fatal_lvl) {
                                    $text_log .= "--------------------------\n\n";
                                }
                            }
                            if (!empty($arr_msg)){
                                $arr_msg = [];
                            }
                        }
                    } else {
                        $arr_msg[] = $_log;
                    }
                }
            }
            $this->echoLog($text_log);
        }
    }

    /**
     * Setting up an extended directory for writing
     * @param string $add_dir - directories for writing.
     */
    public function setDir(string $add_dir = '')
    {
        if (!empty($add_dir)){
            // Allowed characters: letters, numbers, period, underscore, hyphen
            $add_dir = preg_replace('/[^\w\.\/\-]/', '', $add_dir);
            $add_dir = trim(preg_replace('/\/+/', '/', $add_dir), '/');
            if (!empty($add_dir)) {
                if ($this->global_type == 'file'){
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
        if (!empty($file_name)){
            if ($this->global_type == 'file'){
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
        if (!empty($text_log)){
            if ($this->global_type == 'echo'){
                echo $text_log;
            }
            if ($this->global_type == 'file'){
                file_put_contents(
                    '/' . $this->log_dir . (!empty($this->add_dir) ? '/' . $this->add_dir : '') . '/' . $this->file_name . '.log',
                    $text_log,
                    FILE_APPEND
                );
            }
            if ($this->global_type == 'function'){
                ($this->func)($text_log);
            }
        }
    }


}

$logger = !empty($logger) ? $logger : new Logger();
