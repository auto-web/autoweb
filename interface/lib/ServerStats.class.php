<?php

class ServerStats
{
    public const CPU = 'cpu';
    public const RAM = 'ram';
    public const DISK = 'disk';
    public const LOAD = 'load';
    public const UPTIME = 'uptime';
    public const PROCESSES = 'processes';
    public const CONNECTED_USERS = 'users';

    public function getStat(string $stat): array
    {
        return match ($stat) {
            self::CPU => $this->getCpu(),
            self::RAM => $this->getRam(),
            self::DISK => $this->getStorage(),
            self::LOAD => $this->getLoad(),
            self::UPTIME => $this->getUptime(),
            self::PROCESSES => $this->getProcess(),
            self::CONNECTED_USERS => $this->getConnectedUsers(),
            default => [],
        };
    }

    public function getAllStats(): array
    {
        return [
            self::CPU => $this->getCpu(),
            self::RAM => $this->getRam(),
            self::DISK => $this->getStorage(),
            self::LOAD => $this->getLoad(),
            self::UPTIME => $this->getUptime(),
            self::PROCESSES => $this->getProcess(),
            self::CONNECTED_USERS => $this->getConnectedUsers(),
        ];
    }

    private function getCpu(): array
    {
        $stat1 = file('/proc/stat')[0];
        usleep(100000);
        $stat2 = file('/proc/stat')[0];

        $cpu1 = preg_split('/\s+/', trim($stat1));
        $cpu2 = preg_split('/\s+/', trim($stat2));

        array_shift($cpu1);
        array_shift($cpu2);

        $idle1 = $cpu1[3];
        $idle2 = $cpu2[3];

        $total1 = array_sum($cpu1);
        $total2 = array_sum($cpu2);

        $totalDiff = $total2 - $total1;
        $idleDiff = $idle2 - $idle1;

        $percent = 0;

        if ($totalDiff > 0) {
            $percent = round(
                (1 - ($idleDiff / $totalDiff)) * 100,
                1
            );
        }

        return [
            'percent' => $percent,
        ];
    }

    private function getRam(): array
    {
        $memInfo = file('/proc/meminfo');
        $mem = [];

        foreach ($memInfo as $line) {
            [$key, $value] = explode(':', $line, 2);

            $mem[$key] = (int) filter_var(
                $value,
                FILTER_SANITIZE_NUMBER_INT
            );
        }

        $total = $mem['MemTotal'];
        $available = $mem['MemAvailable'];
        $used = $total - $available;

        return [
            'used_gb' => round($used / 1024 / 1024, 1),
            'total_gb' => round($total / 1024 / 1024, 1),
            'percent' => round(($used / $total) * 100, 1),
        ];
    }

    private function getStorage(): array
    {
        $disks = [];

        $lines = explode(
            PHP_EOL,
            trim(shell_exec(
                'df -B1 --output=source,size,used,avail,pcent,target'
            ))
        );

        array_shift($lines); // header

        foreach ($lines as $line) {
            $parts = preg_split('/\s+/', trim($line));

            if (count($parts) < 6) {
                continue;
            }

            if (!preg_match('#^/dev/#', $parts[0])) {
                continue;
            }

            $disks[] = [
                'device' => $parts[0],
                'total_gb' => round($parts[1] / 1024 / 1024 / 1024, 1),
                'used_gb' => round($parts[2] / 1024 / 1024 / 1024, 1),
                'free_gb' => round($parts[3] / 1024 / 1024 / 1024, 1),
                'percent' => (float) rtrim($parts[4], '%'),
                'mountpoint' => $parts[5],
            ];
        }

        return $disks;
    }

    private function getLoad(): array
    {
        $load = sys_getloadavg();

        return [
            '1min' => round($load[0], 2),
            '5min' => round($load[1], 2),
            '15min' => round($load[2], 2),
        ];
    }

    private function getUptime(): array
    {
        $uptime = (int) explode(
            ' ',
            trim(file_get_contents('/proc/uptime'))
        )[0];

        $days = floor($uptime / 86400);
        $hours = floor(($uptime % 86400) / 3600);
        $minutes = floor(($uptime % 3600) / 60);

        return [
            'seconds' => $uptime,
            'human' => sprintf(
                '%dj %02dh %02dm',
                $days,
                $hours,
                $minutes
            ),
        ];
    }

    private function getProcess(): array
    {
        $count = (int) trim(shell_exec(
            "ps -eo pid --no-headers | awk '\$1 >= 1000' | wc -l"
        ));

        return [
            'count' => $count,
        ];
    }

    private function getConnectedUsers(): array
    {
        $output = trim(shell_exec('who'));

        if (empty($output)) {
            return [
                'count' => 0,
                'list' => [],
            ];
        }

        $users = [];

        foreach (explode("\n", $output) as $line) {
            $parts = preg_split('/\s+/', trim($line));

            $users[] = [
                'user' => $parts[0] ?? '',
                'tty' => $parts[1] ?? '',
            ];
        }

        return [
            'count' => count($users),
            'list' => $users,
        ];
    }
}
