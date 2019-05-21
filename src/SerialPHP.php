<?php

namespace Cronox\SerialPHP;

class SerialPHP
{

    private $isOpened = false;
    private $isSetted = false;
    private $autoFlush = true;

    private $serialPortName = null;
    private $serialPortHandle;

    private $lastCommand;
    private $_buffer;

    private $validBauds = [
        110 => 11,
        150 => 15,
        300 => 30,
        600 => 60,
        1200 => 12,
        2400 => 24,
        4800 => 48,
        9600 => 96,
        19200 => 19,
        38400 => 38400,
        57600 => 57600,
        115200 => 115200,
    ];


    public function __construct()
    {
        setlocale(LC_ALL, 'en_US');
    }

    public function __destruct()
    {
        $this->closeSerialPort();
    }


    public function setSerialPort($device)
    {
        if (true === $this->isOpened) {
            throw new \Exception('Serial port is already opened');
        }

        if ($this->_exec('stty -F '.$device, $output) === 0) {
            $this->_exec('stty -F '.$device.' -echo', $output);
            $this->serialPortName = $device;
            $this->isSetted = true;
        } else {
            throw new \Exception('Specified serial port "'.$device.'" is not valid');
        }

        return true;
    }


    public function openSerialPort($mode = "r+b")
    {
        if (true === $this->isOpened) {
            return true;
        }

        if (false === $this->isSetted) {
            throw new \Exception("Serial port must be set before to be open");
        }

        if (!preg_match("@^[raw]\\+?b?$@", $mode)) {
            throw new \Exception("Invalid opening mode : ".$mode.". Use fopen() modes.");
        }

        $this->serialPortHandle = @fopen($this->serialPortName, $mode);

        if (false !== $this->serialPortHandle) {
            stream_set_blocking($this->serialPortHandle, 0);
            $this->isOpened = true;

            return true;
        }

        throw new \Exception("Unable to open serial port");
    }

    public function closeSerialPort()
    {

        if (false === $this->isOpened) {
            return true;
        }

        if (fclose($this->serialPortHandle)) {
            $this->serialPortHandle = null;
            $this->isOpened = false;

            return true;
        }

        return false;
    }


    private function _exec($cmd, &$out = null)
    {
        $desc = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $proc = proc_open($cmd, $desc, $pipes);

        $ret = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $retVal = proc_close($proc);

        if (func_num_args() == 2) {
            $out = [$ret, $err];
        }

        return $retVal;
    }

    private function flushBuffer()
    {
        if (false === $this->isOpened) {
            return false;
        }

        if (fwrite($this->serialPortHandle, $this->_buffer) !== false) {
            $this->_buffer = "";

            return true;
        } else {
            $this->_buffer = "";

            return false;
        }
    }

    public function setBaudRate($rate)
    {
        $rate = (int)$rate;
        if (false === $this->isOpened || false === $this->isSetted) {
            throw new \Exception("Unable to set baud rate. Serial port is not opened or setted.");
        }

        if (false === isset($this->validBauds[$rate])) {
            throw new \Exception("Invalid baud rate.");
        }

        $returnCode = $this->_exec(sprintf('stty -F %s %d', $this->serialPortName, $rate), $out);
        if (0 !== $returnCode) {
            throw new \Exception("Unable to set baud rate: ".PHP_EOL.implode(PHP_EOL, $out));
        }
    }

    public function setStreamTimeout($seconds)
    {
        if (false === $this->isOpened) {
            throw new \Exception("Serial port must be opened to set timeout");
        }

        stream_set_timeout($this->serialPortHandle, $seconds);
    }

    public function send($string, $waitForReply = 0.1)
    {
        $this->_buffer .= $string.PHP_EOL;
        $this->lastCommand = $string;

        if ($this->autoFlush === true) {
            $this->flushBuffer();
        }

        usleep((int)($waitForReply * 1000000));
    }

    public function read($count = false, $default = false)
    {
        if (false === $this->isOpened) {
            throw new \Exception("Serial port must be opened to read it");
        }

        $content = "";
        $i = 0;

        if (false !== $count) {
            do {
                if ($i > $count) {
                    $content .= fread($this->serialPortHandle, ($count - $i));
                } else {
                    $content .= fread($this->serialPortHandle, 128);
                }
            } while (($i += 128) === strlen($content));
        } else {
            do {
                $content .= fread($this->serialPortHandle, 10);
            } while (($i += 10) === strlen($content));
        }

        if (empty($content)) {
            return $default;
        }

        return array_values(array_filter(explode(PHP_EOL, trim($content))));

    }

    public function readWithoutCommand($count = false, $default = false)
    {
        $result = $this->read($count, $default);
        if (empty($result)) {
            return $default;
        }

        $key = array_search($this->lastCommand, $result);
        if (is_numeric($key)) {
            unset($result[$key]);
        }

        $result = array_values($result);

        return $result;

    }

}
