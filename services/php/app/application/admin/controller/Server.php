<?php
namespace app\admin\controller;

use think\Controller;

class Server extends Controller
{
    private $procPath = '/host_proc';

    // GET /admin/server/info
    public function info()
    {
        return json([
            'code' => 0,
            'msg'  => 'success',
            'data' => [
                'cpu'    => $this->getCpuInfo(),
                'memory' => $this->getMemoryInfo(),
                'disk'   => $this->getDiskInfo(),
            ],
        ]);
    }

    private function getCpuInfo(): ?array
    {
        $statFile = $this->procPath . '/stat';
        if (!is_readable($statFile)) {
            $statFile = '/proc/stat';
        }
        if (!is_readable($statFile)) {
            return $this->getCpuFallback();
        }

        $stat1 = $this->readCpuStat($statFile);
        if ($stat1 === null) {
            return $this->getCpuFallback();
        }
        sleep(1);
        $stat2 = $this->readCpuStat($statFile);
        if ($stat2 === null) {
            return $this->getCpuFallback();
        }

        $totalDiff = $stat2['total'] - $stat1['total'];
        $idleDiff  = $stat2['idle'] - $stat1['idle'];
        $usage = $totalDiff > 0 ? round(($totalDiff - $idleDiff) / $totalDiff * 100, 1) : 0;

        $cores = $this->getCpuCores();

        $loadAvg = $this->getLoadAvg();

        return [
            'usage'    => $usage,
            'cores'    => $cores,
            'load_avg' => $loadAvg,
        ];
    }

    private function readCpuStat(string $file): ?array
    {
        $content = @file_get_contents($file);
        if ($content === false) {
            return null;
        }
        $lines = explode("\n", $content);
        if (empty($lines) || strpos($lines[0], 'cpu ') !== 0) {
            return null;
        }
        $fields = preg_split('/\s+/', trim($lines[0]));
        // user, nice, system, idle, iowait, irq, softirq, steal
        $idle  = isset($fields[4]) ? (int)$fields[4] : 0;
        $iowait = isset($fields[5]) ? (int)$fields[5] : 0;
        $total = 0;
        for ($i = 1; $i < count($fields); $i++) {
            $total += (int)$fields[$i];
        }
        return ['total' => $total, 'idle' => $idle + $iowait];
    }

    private function getCpuFallback(): ?array
    {
        $loadAvg = $this->getLoadAvg();
        if ($loadAvg === null) {
            return null;
        }
        return [
            'usage'    => null,
            'cores'    => $this->getCpuCores(),
            'load_avg' => $loadAvg,
        ];
    }

    private function getCpuCores(): int
    {
        $cpuInfo = $this->procPath . '/cpuinfo';
        if (!is_readable($cpuInfo)) {
            $cpuInfo = '/proc/cpuinfo';
        }
        if (is_readable($cpuInfo)) {
            $content = @file_get_contents($cpuInfo);
            if ($content !== false) {
                return substr_count($content, 'processor');
            }
        }
        return 1;
    }

    private function getLoadAvg(): ?string
    {
        if (function_exists('sys_getloadavg')) {
            $load = @sys_getloadavg();
            if ($load !== false) {
                return round($load[0], 2) . ', ' . round($load[1], 2) . ', ' . round($load[2], 2);
            }
        }
        return null;
    }

    private function getMemoryInfo(): ?array
    {
        $memFile = $this->procPath . '/meminfo';
        if (!is_readable($memFile)) {
            $memFile = '/proc/meminfo';
        }
        if (is_readable($memFile)) {
            $content = @file_get_contents($memFile);
            if ($content !== false) {
                $total = $this->parseMemInfo($content, 'MemTotal');
                $avail = $this->parseMemInfo($content, 'MemAvailable');
                if ($total > 0) {
                    $used = $total - $avail;
                    return [
                        'total' => (int)round($total / 1024),
                        'used'  => (int)round($used / 1024),
                        'free'  => (int)round($avail / 1024),
                        'usage' => round($used / $total * 100, 1),
                    ];
                }
            }
        }

        // 回退: shell_exec('free -m')
        $freeOutput = @shell_exec('free -m 2>/dev/null');
        if ($freeOutput !== null && $freeOutput !== false) {
            $lines = explode("\n", trim($freeOutput));
            if (count($lines) >= 2) {
                $fields = preg_split('/\s+/', trim($lines[1]));
                if (count($fields) >= 3) {
                    $total = (int)$fields[1];
                    $used  = (int)$fields[2];
                    $free  = (int)$fields[3];
                    return [
                        'total' => $total,
                        'used'  => $used,
                        'free'  => $free,
                        'usage' => $total > 0 ? round($used / $total * 100, 1) : 0,
                    ];
                }
            }
        }

        return null;
    }

    private function parseMemInfo(string $content, string $key): int
    {
        if (preg_match('/' . $key . ':\s+(\d+)\s+kB/', $content, $m)) {
            return (int)$m[1];
        }
        return 0;
    }

    private function getDiskInfo(): ?array
    {
        $total = @disk_total_space('/');
        $free  = @disk_free_space('/');
        if ($total === false || $free === false || $total <= 0) {
            return null;
        }
        $used = $total - $free;
        return [
            'total' => round($total / 1073741824, 1),
            'used'  => round($used / 1073741824, 1),
            'free'  => round($free / 1073741824, 1),
            'usage' => round($used / $total * 100, 1),
        ];
    }
}
