<?php

class telnet
{
    protected $ip = '0.0.0.0';
    protected $port = 8765;
    protected $sd;

    protected $multiRead = [
        'conv_tree' => ']'
    ];
    public function __construct($argc, $argv)
    {
        $this->runServer();
    }
    private function getAddress(): string
    {
        return sprintf('tcp://%s:%d', $this->ip, $this->port);
    }

    private function runServer()
    {
        $this->sd = stream_socket_server($this->getAddress(), $errno, $error);

        if (!$this->sd) {
            exit('start socket server error ' . $error);
        }
        stream_set_timeout($this->sd, 0);

        while (true) {

            while ($conn = @stream_socket_accept($this->sd, 2)) {
                echo "new connection\n";

                $this->recv($conn);
                fclose($conn);

                echo "connection closed\n";
            }
        }
        fclose($this->sd);
    }

    /**
     * TODO thread
     */
    private function recv($conn)
    {
        stream_socket_sendto($conn, "Welcome\n");
        $multiReadEnd = '';
        $cmd = '';
        $args = [];
        while (true) {
            while ($input = stream_get_line($conn, 0, "\r\n")) {
                if ($multiReadEnd) {
                    if (!isset($args[0]))
                        $args[0] = '';
                    $args[0] .= $input;
                    $end = substr($input, strlen($input) - strlen($multiReadEnd));
                    if ($multiReadEnd !== $end) {
                        continue;
                    }

                    $multiReadEnd = '';
                } else {
                    $args = preg_split('/ +/', $input);
                    if (!$args) {
                        stream_socket_sendto($conn,  "Please Input\n");
                        continue;
                    }
                    $cmd = $args[0];
                    if ($cmd === 'quit' || $cmd === 'q') {
                        return;
                    }
                    if (isset($this->multiRead[$cmd])) {
                        $multiReadEnd = $this->multiRead[$cmd];
                        continue;
                    }
                }

                $method = "do_$cmd";
                if (!method_exists($this, $method)) {
                    stream_socket_sendto($conn, "Unokwn command: $cmd\n");
                    continue;
                }
                $ret = call_user_func_array(array($this, $method), [$args]);
                if (is_string($ret) && strlen($ret) > 0) {
                    stream_socket_sendto($conn, "$ret\n");
                    continue;
                } elseif ($ret === false) {
                    return;
                }
            }
            usleep(200);
        }
    }
    protected function do_mul(array $args)
    {
        if (count($args) != 2) {
            return "error: mul [int] [int]";
        }

        $a = (int)$args[0];
        $b = (int)$args[1];

        if ($a != $args[0] || $b != $args[1]) {
            return "error: You must input two integer";
        }

        $ret = $a * $b;
        return sprintf("%d\n", $ret);
    }

    protected function do_incr(array $args)
    {
        if (count($args) != 1) {
            return "error: incr [int]";
        }
        if ('' . intval($args[0]) != $args[0]) {
            return "error: You must input a integer";
        }

        $a = (int)$args[0];
        $b = (int)$args[1];

        $ret = $a * $b;

        return sprintf("%d\n", $ret);
    }
    protected function do_div(array $args)
    {
        if (count($args) != 2) {
            return "div [number] [number]";
        }

        $a = (float)$args[0];
        $b = (float)$args[1];

        if ($a != $args[0] || $b != $args[1]) {
            return "You must input two integer";
        }
        if ($b === 0) {
            return "div can not be zero";
        }

        $ret = $a / $b;

        return sprintf("%f\n", $ret);
    }

    protected function do_conv_tree($conn, array $args)
    {
        if (count($args) != 1) {
            return 'not a json';
        }
        $json = json_decode($args[0], true, JSON_INVALID_UTF8_IGNORE);
        if (!$json) {
            return 'not a json';
        }
        $tree = [];
        $treeCur = &$tree;

        $retTree = [];

        foreach ($json as $v) {
            if (!is_array($v) || !isset($v['id'], $v['name'], $v['level'], $v['namePath'])) {
                return 'unknown format';
            }
            $paths = explode(',', $v['namePath']);
            $treeCur = &$tree;
            $retCur = &$retTree;
            $parent = [];
            foreach ($paths as $p) {
                if (!isset($treeCur[$p])) {
                    $treeCur[$p] = [
                        'id' => substr(uniqid(mt_rand()), 10),
                        'id_path' => ',' . ($parent ? implode(',', $parent) . ',' : ''),
                        'level' => count($parent) + 1,
                        'name' => $p,
                        'name_path' => $v['namePath'],
                        'parent_id' => $parent ? end($parent) : '',
                        'children' => [],
                    ];
                    $retCur[] = $treeCur[$p];
                }
                $parent[] = $treeCur[$p]['id'];
                $treeCur = &$treeCur[$p]['children'];

                $retCur = &$retCur[count($retCur) - 1]['children'];
            }
            $retCur[] = [
                'id' => $v['id'],
                'id_path' => ',' . ($parent ? implode(',', $parent) . ',' . $v['id'] . ',' : ''),
                'level' => count($parent) + 1,
                'name' => $v['name'],
                'name_path' => $v['namePath'],
                'parent_id' => $parent ? end($parent) : '',
            ];
            unset($retCur);
        }

        return json_encode($retTree, JSON_UNESCAPED_UNICODE);
    }
}


new telnet($argc, $argv);