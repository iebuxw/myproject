<template>
  <el-container style="height:100%">
    <el-aside width="220px" style="background:#304156">
      <div class="logo">
        <span>后台管理</span>
      </div>
      <sidebar :menus="menus" />
    </el-aside>
    <el-container>
      <el-header style="height:50px;background:#fff;border-bottom:1px solid #e6e6e6;display:flex;align-items:center;justify-content:flex-end;padding:0 20px">
        <el-dropdown @command="handleCommand">
          <span style="cursor:pointer">
            {{ admin ? admin.username : '' }} <i class="el-icon-arrow-down"></i>
          </span>
          <el-dropdown-menu slot="dropdown">
            <el-dropdown-item command="logout">退出登录</el-dropdown-item>
          </el-dropdown-menu>
        </el-dropdown>
      </el-header>
      <el-main>
        <router-view />
      </el-main>
    </el-container>
  </el-container>
</template>

<script>
import { mapState } from 'vuex'
import Sidebar from '@/components/Sidebar'

export default {
  name: 'Layout',
  components: { Sidebar },
  computed: {
    ...mapState(['admin', 'menus'])
  },
  created() {
    if (!this.admin) {
      this.$store.dispatch('getInfo')
    }
  },
  methods: {
    handleCommand(cmd) {
      if (cmd === 'logout') {
        this.$store.dispatch('logout').then(() => {
          this.$router.push('/login')
        })
      }
    }
  }
}
</script>

<style scoped>
.logo {
  height: 50px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 18px;
  font-weight: bold;
  border-bottom: 1px solid rgba(255,255,255,0.1);
}
</style>
