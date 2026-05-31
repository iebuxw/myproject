# 系统资源监控 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 在后台管理系统仪表盘添加 CPU/内存/磁盘使用率卡片展示

**Architecture:** PHP 新增 Server 控制器，通过读取 /proc 文件系统获取宿主机资源数据，暴露 GET /admin/server/info 接口；前端仪表盘页面新增 3 张 el-card 展示数据，进度条颜色随使用率动态变化。

**Tech Stack:** PHP 7.4 (ThinkPHP 5.1), Vue 2 + Element UI, Docker Compose

---

## File Structure

| Action | File | Responsibility |
|--------|------|----------------|
| Create | `services/php/app/application/admin/controller/Server.php` | 获取 CPU/内存/磁盘数据，返回 JSON |
| Modify | `services/php/app/route/route.php` | 注册 GET server/info 路由 |
| Modify | `docker-compose.yml` | PHP 容器挂载 /host_proc 只读 |
| Modify | `frontend/src/views/dashboard/index.vue` | 添加 3 张资源监控卡片 |

---

### Task 1: 创建 PHP Server 控制器

**Files:**
- Create: `services/php/app/application/admin/controller/Server.php`

- [ ] **Step 1: 创建 Server.php 控制器**

```php
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
```

- [ ] **Step 2: 提交 Server 控制器**

```bash
git add services/php/app/application/admin/controller/Server.php
git commit -m "feat: 添加 Server 控制器获取系统资源信息"
```

---

### Task 2: 注册路由 + docker-compose 挂载

**Files:**
- Modify: `services/php/app/route/route.php` — 在认证路由组内添加 server/info 路由
- Modify: `docker-compose.yml` — PHP 容器添加 /host_proc 只读挂载

- [ ] **Step 1: 在 route.php 认证路由组内添加路由**

在 `services/php/app/route/route.php` 的路由组内，OperationLog 部分之后添加：

```php
    // Server
    Route::get('server/info', 'admin/Server/info');
```

具体位置：在 `Route::delete('operation_log/delete', 'admin/OperationLog/delete');` 这一行之后，`})->middleware` 闭合之前。

- [ ] **Step 2: 在 docker-compose.yml 中为 PHP 容器添加 /host_proc 挂载**

在 `docker-compose.yml` 的 `php` 服务的 `volumes` 列表末尾添加：

```yaml
      - /proc:/host_proc:ro
```

- [ ] **Step 3: 提交路由和 docker-compose 修改**

```bash
git add services/php/app/route/route.php docker-compose.yml
git commit -m "feat: 注册 server/info 路由，挂载宿主机 /proc 到 PHP 容器"
```

---

### Task 3: 前端仪表盘页面添加资源监控卡片

**Files:**
- Modify: `frontend/src/views/dashboard/index.vue`

- [ ] **Step 1: 替换 dashboard/index.vue 全部内容**

```vue
<template>
  <div>
    <el-row :gutter="20" style="margin-bottom:20px">
      <el-col :span="8">
        <el-card shadow="hover">
          <div slot="header">CPU 使用率</div>
          <div class="monitor-value">{{ serverInfo.cpu ? serverInfo.cpu.usage + '%' : '--' }}</div>
          <el-progress
            :percentage="serverInfo.cpu ? serverInfo.cpu.usage : 0"
            :color="progressColor(serverInfo.cpu ? serverInfo.cpu.usage : 0)"
            :stroke-width="12"
          />
          <div class="monitor-sub" v-if="serverInfo.cpu">
            {{ serverInfo.cpu.cores }} 核 / 负载 {{ serverInfo.cpu.load_avg || '--' }}
          </div>
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card shadow="hover">
          <div slot="header">内存使用率</div>
          <div class="monitor-value">
            {{ serverInfo.memory ? formatMemory(serverInfo.memory.used) + ' / ' + formatMemory(serverInfo.memory.total) : '--' }}
          </div>
          <el-progress
            :percentage="serverInfo.memory ? serverInfo.memory.usage : 0"
            :color="progressColor(serverInfo.memory ? serverInfo.memory.usage : 0)"
            :stroke-width="12"
          />
          <div class="monitor-sub" v-if="serverInfo.memory">
            已用 {{ formatMemory(serverInfo.memory.used) }} / 总量 {{ formatMemory(serverInfo.memory.total) }}
          </div>
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card shadow="hover">
          <div slot="header">磁盘使用率</div>
          <div class="monitor-value">
            {{ serverInfo.disk ? serverInfo.disk.used + ' / ' + serverInfo.disk.total + ' GB' : '--' }}
          </div>
          <el-progress
            :percentage="serverInfo.disk ? serverInfo.disk.usage : 0"
            :color="progressColor(serverInfo.disk ? serverInfo.disk.usage : 0)"
            :stroke-width="12"
          />
          <div class="monitor-sub" v-if="serverInfo.disk">
            已用 {{ serverInfo.disk.used }} GB / 总量 {{ serverInfo.disk.total }} GB
          </div>
        </el-card>
      </el-col>
    </el-row>
    <el-card v-if="serverError" style="margin-bottom:20px">
      <p style="color:#909399;text-align:center">{{ serverError }}</p>
    </el-card>
    <el-card>
      <h2>欢迎使用后台管理系统</h2>
      <p style="margin-top:10px;color:#666">管理员: {{ admin ? admin.username : '' }}</p>
    </el-card>
  </div>
</template>

<script>
import { mapState } from 'vuex'
import request from '@/api'

export default {
  name: 'Dashboard',
  data() {
    return {
      serverInfo: {},
      serverError: ''
    }
  },
  computed: { ...mapState(['admin']) },
  created() {
    this.fetchServerInfo()
  },
  methods: {
    async fetchServerInfo() {
      try {
        const res = await request.get('/server/info')
        if (res.code === 0) {
          this.serverInfo = res.data
        } else {
          this.serverError = '暂无数据'
        }
      } catch (e) {
        this.serverError = '暂无数据'
      }
    },
    formatMemory(mb) {
      if (mb === null || mb === undefined) return '--'
      return mb >= 1024 ? (mb / 1024).toFixed(1) + ' GB' : mb + ' MB'
    },
    progressColor(percentage) {
      if (percentage >= 80) return '#F56C6C'
      if (percentage >= 60) return '#E6A23C'
      return '#67C23A'
    }
  }
}
</script>

<style scoped>
.monitor-value {
  font-size: 24px;
  font-weight: 600;
  color: #303133;
  margin-bottom: 12px;
}
.monitor-sub {
  margin-top: 8px;
  font-size: 13px;
  color: #909399;
}
</style>
```

- [ ] **Step 2: 提交前端修改**

```bash
git add frontend/src/views/dashboard/index.vue
git commit -m "feat: 仪表盘添加 CPU/内存/磁盘使用率卡片"
```

---

### Task 4: 构建并验证

- [ ] **Step 1: 重新构建并启动所有服务**

```bash
docker-compose up -d --build
```

- [ ] **Step 2: 验证 API 响应**

登录后台后访问 `http://localhost/admin/server/info`，确认返回 JSON 结构正确。

- [ ] **Step 3: 验证前端展示**

打开仪表盘页面，确认 3 张卡片正常显示数据和进度条，颜色随使用率变化。
