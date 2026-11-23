<?php

class ServerStats {
    public const CPU = 'cpu';
    public const RAM = 'ram';
    public const DISK = 'disk';
    public const LOAD = 'load';
    public const UPTIME = 'uptime';
    public const PROCESSES = 'processes';
    public const CONNECTED_USERS = 'users';

    /**
     * Get one stat value from the server.
     *
     * @param string $stat One of the constants of this class (CPU, RAM, DISK...)
     * @return array The statistic (see the private getters), empty for an unknown name
     */
    public function getStat(string $stat): array {
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

    /**
     * All the stats of the server (admin page, "État du serveur").
     *
     * @return array Statistics indexed by constant of this class
     */
    public function getAllStats(): array {
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

    /**
     * Returns CPU usage over 0.1 s, from /proc/stat.
     *
     * @return array ['percent' => float]
     */
    private function getCpu(): array {
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

    /**
     * Returns Memory used (total - available), from /proc/meminfo.
     *
     * @return array ['used_gb' => float, 'total_gb' => float, 'percent' => float]
     */
    private function getRam(): array {
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

    /**
     * Returns space of the mounted /dev/* file systems, from df.
     *
     * @return array One entry per file system: ['device' => string, 'total_gb' => float, 'used_gb' => float,
     *               'free_gb' => float, 'percent' => float, 'mountpoint' => string]
     */
    private function getStorage(): array {
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

    /**
     * Returns Load averages.
     *
     * @return array ['1min' => float, '5min' => float, '15min' => float]
     */
    private function getLoad(): array {
        $load = sys_getloadavg();

        return [
            '1min' => round($load[0], 2),
            '5min' => round($load[1], 2),
            '15min' => round($load[2], 2),
        ];
    }

    /**
     * Returns Time since the boot, from /proc/uptime.
     *
     * @return array ['seconds' => int, 'human' => string, e.g. "3j 04h 12m"]
     */
    private function getUptime(): array {
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

    /**
     * Returns the number of processes run by the AutoWeb accounts (members of the jailkit group, see
     * roles/create_user), e.g. their PHP sites (fcgid) and SSH/SFTP sessions, from ps.
     *
     * @return array ['count' => int]
     */
    private function getProcess(): array {
        // UIDs of the AutoWeb accounts
        $group = posix_getgrnam('jailkit');
        $uids = [];
        foreach ($group ? $group['members'] : [] as $login) {
            $user = posix_getpwnam($login);
            if ($user !== false) {
                $uids[$user['uid']] = true;
            }
        }

        // Real UID of each process, with ps: /proc/<pid> is out of the open_basedir of the interface
        $count = 0;
        foreach (preg_split('/\s+/', trim((string) shell_exec('ps -eo ruid=')), -1, PREG_SPLIT_NO_EMPTY) as $uid) {
            if (isset($uids[(int) $uid])) {
                $count++;
            }
        }

        return [
            'count' => $count,
        ];
    }

    /**
     * Returns Users logged in on the server, from who.
     *
     * @return array ['count' => int, 'list' => array of ['user' => string, 'tty' => string]]
     */
    private function getConnectedUsers(): array {
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
