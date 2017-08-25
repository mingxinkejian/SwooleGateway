<?php
function get_caller_info() {
        $c = '';
        $file = '';
        $func = '';
        $class = '';
        $line = 0;
        $trace = debug_backtrace();
        if (isset($trace[2])) {
            $file = $trace[1]['file'];
            $func = $trace[2]['function'];
            if ((substr($func, 0, 7) == 'include') || (substr($func, 0, 7) == 'require')) {
                $func = '';
            }
            $line = $trace[2]['line'];
        } else if (isset($trace[1])) {
            $file = $trace[1]['file'];
            $func = '';
            $line = $trace[1]['line'];
        }
        if (isset($trace[3]['class'])) {
            $class = $trace[3]['class'];
            $func = $trace[3]['function'];
            $file = $trace[2]['file'];
            $line = $trace[2]['line'];
        } else if (isset($trace[2]['class'])) {
            $class = $trace[2]['class'];
            $func = $trace[2]['function'];
            $file = $trace[1]['file'];
            $line = $trace[2]['line'];
        }
        if ($file != '') $file = basename($file);
        $c = 'LINE:' . $line . $file . ": ";
        $c .= ($class != '') ? ":" . $class . "->" : "";
        $c .= ($func != '') ? $func . "(): " : "";
        return($c);
}

class Test1 {
    public function test()
    {
        $this->test1();
    }
    public function test1()
    {
        $this->test2();
    }
    public function test2()
    {
        var_dump(get_caller_info());
    }
}
$obj = new Test1();
$obj->test();