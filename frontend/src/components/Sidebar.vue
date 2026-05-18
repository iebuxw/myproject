<template>
  <el-menu
    :default-active="activeMenu"
    background-color="#304156"
    text-color="#bfcbd9"
    active-text-color="#409EFF"
    router
    style="border-right:none"
  >
    <template v-for="menu in menus">
      <el-submenu v-if="menu.children && menu.children.length" :key="menu.id" :index="menu.path">
        <template slot="title">
          <i v-if="menu.icon" :class="menu.icon"></i>
          <span>{{ menu.name }}</span>
        </template>
        <el-menu-item v-for="child in menu.children" :key="child.id" :index="child.path">
          <i v-if="child.icon" :class="child.icon"></i>
          <span>{{ child.name }}</span>
        </el-menu-item>
      </el-submenu>
      <el-menu-item v-else :key="menu.id" :index="menu.path">
        <i v-if="menu.icon" :class="menu.icon"></i>
        <span>{{ menu.name }}</span>
      </el-menu-item>
    </template>
  </el-menu>
</template>

<script>
export default {
  name: 'Sidebar',
  props: {
    menus: { type: Array, default: () => [] }
  },
  computed: {
    activeMenu() {
      return this.$route.path
    }
  }
}
</script>
