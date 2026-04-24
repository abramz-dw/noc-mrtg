<?php

namespace App\Domain\Dashboard\Service;

use App\Infrastructure\Database\Connection;
use Psr\Log\LoggerInterface;

class ServerHealthService
{
    public function __construct(
        private readonly Connection $db,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Get server health metrics
     */
    public function getServerHealth(): array
    {
        try {
            return [
                'cpu' => $this->getCpuMetrics(),
                'memory' => $this->getMemoryMetrics(),
                'disk' => $this->getDiskMetrics(),
                'network' => $this->getNetworkMetrics(),
                'uptime' => $this->getUptimeMetrics(),
                'timestamp' => date('Y-m-d H:i:s')
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to get server health', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get CPU metrics using load average
     */
    private function getCpuMetrics(): array
    {
        $loadAvg = sys_getloadavg(); // [1min, 5min, 15min]
        $cpuCount = (int)shell_exec('nproc');

        $usage1min = $cpuCount > 0 ? min(100, ($loadAvg[0] / $cpuCount) * 100) : 0;

        return [
            'cores' => $cpuCount,
            'loadAverage' => [
                'oneMin' => round($loadAvg[0], 2),
                'fiveMin' => round($loadAvg[1], 2),
                'fifteenMin' => round($loadAvg[2], 2)
            ],
            'usagePercent' => round($usage1min, 2)
        ];
    }

    /**
     * Get memory metrics from /proc/meminfo
     */
    private function getMemoryMetrics(): array
    {
        if (!file_exists('/proc/meminfo')) {
            return [
                'total' => 0,
                'used' => 0,
                'available' => 0,
                'usagePercent' => 0
            ];
        }

        $meminfo = file_get_contents('/proc/meminfo');
        $lines = explode("\n", $meminfo);
        $memdata = [];

        foreach ($lines as $line) {
            if (preg_match('/^(\w+):\s+(\d+)/', $line, $m)) {
                $memdata[$m[1]] = (int)$m[2] * 1024; // Convert to bytes
            }
        }

        $total = $memdata['MemTotal'] ?? 0;
        $available = $memdata['MemAvailable'] ?? 0;
        $used = $total - $available;
        $usagePercent = $total > 0 ? ($used / $total) * 100 : 0;

        return [
            'total' => $total,
            'totalGb' => round($total / (1024 ** 3), 2),
            'used' => $used,
            'usedGb' => round($used / (1024 ** 3), 2),
            'available' => $available,
            'availableGb' => round($available / (1024 ** 3), 2),
            'usagePercent' => round($usagePercent, 2)
        ];
    }

    /**
     * Get disk metrics from mount points
     */
    private function getDiskMetrics(): array
    {
        $disks = [];

        // Common mount points
        $mountPoints = ['/', '/var', '/home', '/tmp'];

        foreach ($mountPoints as $mount) {
            if (is_dir($mount)) {
                $total = disk_total_space($mount);
                $free = disk_free_space($mount);

                if ($total && $free !== false) {
                    $used = $total - $free;
                    $usagePercent = ($used / $total) * 100;

                    $disks[] = [
                        'mount' => $mount,
                        'total' => $total,
                        'totalGb' => round($total / (1024 ** 3), 2),
                        'used' => $used,
                        'usedGb' => round($used / (1024 ** 3), 2),
                        'free' => $free,
                        'freeGb' => round($free / (1024 ** 3), 2),
                        'usagePercent' => round($usagePercent, 2)
                    ];
                }
            }
        }

        return $disks;
    }

    /**
     * Get network I/O metrics from /proc/net/dev
     */
    private function getNetworkMetrics(): array
    {
        if (!file_exists('/proc/net/dev')) {
            return [
                'interfaces' => []
            ];
        }

        $netdev = file_get_contents('/proc/net/dev');
        $lines = array_slice(explode("\n", $netdev), 2); // Skip header lines
        $interfaces = [];

        foreach ($lines as $line) {
            if (empty(trim($line))) continue;

            if (preg_match('/^\s*([^:]+):\s+(.+)$/', $line, $m)) {
                $name = trim($m[1]);
                $parts = preg_split('/\s+/', trim($m[2]));

                if (count($parts) >= 8) {
                    $interfaces[] = [
                        'interface' => $name,
                        'rxBytes' => (int)$parts[0],
                        'rxPackets' => (int)$parts[1],
                        'rxErrors' => (int)$parts[2],
                        'rxDropped' => (int)$parts[3],
                        'txBytes' => (int)$parts[8],
                        'txPackets' => (int)$parts[9],
                        'txErrors' => (int)$parts[10],
                        'txDropped' => (int)$parts[11]
                    ];
                }
            }
        }

        return [
            'interfaces' => $interfaces
        ];
    }

    /**
     * Get system uptime
     */
    private function getUptimeMetrics(): array
    {
        if (!file_exists('/proc/uptime')) {
            return [
                'uptime' => 0,
                'formatted' => 'N/A'
            ];
        }

        $uptime = (int)explode(' ', file_get_contents('/proc/uptime'))[0];
        $days = floor($uptime / 86400);
        $hours = floor(($uptime % 86400) / 3600);
        $minutes = floor(($uptime % 3600) / 60);

        return [
            'uptime' => $uptime,
            'days' => $days,
            'hours' => $hours,
            'minutes' => $minutes,
            'formatted' => "{$days}d {$hours}h {$minutes}m"
        ];
    }

    /**
     * Get dashboard summary (dummy devices)
     */
    public function getDashboardSummary(): array
    {
        return [
            'totalDevices' => 45,
            'onlineDevices' => 42,
            'offlineDevices' => 3,
            'averageLatency' => 12.5,
            'totalDataCenters' => 3,
            'lastUpdate' => date('Y-m-d H:i:s')
        ];
    }
}
