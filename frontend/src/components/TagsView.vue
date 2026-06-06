<template>
  <div class="tags-view">
    <router-link
      v-for="tag in visitedViews"
      :key="tag.path"
      :to="tag.path"
      class="tags-view-item"
      :class="{ active: isActive(tag) }"
      @contextmenu.prevent.native="openMenu(tag, $event)"
    >
      <span>{{ tag.title }}</span>
      <span v-if="tag.path !== '/dashboard'" class="tags-close" @click.prevent.stop="closeTag(tag)">
        <i class="el-icon-close"></i>
      </span>
    </router-link>
    <ul v-show="visible" :style="{ left: left + 'px', top: top + 'px' }" class="contextmenu">
      <li @click="refreshTag">刷新</li>
      <li @click="closeTag(selectedTag)">关闭</li>
      <li @click="closeOthers">关闭其他</li>
      <li @click="closeAll">关闭全部</li>
    </ul>
  </div>
</template>

<script>
import { mapState } from 'vuex'

export default {
  name: 'TagsView',
  data() {
    return {
      visible: false,
      top: 0,
      left: 0,
      selectedTag: {}
    }
  },
  computed: {
    ...mapState(['visitedViews'])
  },
  watch: {
    $route() {
      this.addView()
    },
    visible(val) {
      if (val) {
        document.addEventListener('click', this.closeMenu)
      } else {
        document.removeEventListener('click', this.closeMenu)
      }
    }
  },
  mounted() {
    this.addView()
  },
  methods: {
    isActive(tag) {
      return tag.path === this.$route.path
    },
    addView() {
      if (this.$route.name && this.$route.path !== '/login') {
        this.$store.commit('ADD_VISITED_VIEW', this.$route)
      }
    },
    closeTag(tag) {
      const isActive = this.isActive(tag)
      this.$store.commit('DEL_VISITED_VIEW', tag.path)
      if (isActive) {
        this.toLastView()
      }
    },
    refreshTag() {
      const { path } = this.selectedTag
      this.$store.commit('DEL_VISITED_VIEW', path)
      this.$nextTick(() => {
        this.$router.replace({ path: '/redirect' + path })
      })
    },
    closeOthers() {
      this.$store.commit('DEL_OTHER_VIEWS', this.selectedTag.path)
      if (this.selectedTag.path !== this.$route.path) {
        this.$router.push(this.selectedTag.path)
      }
    },
    closeAll() {
      this.$store.commit('DEL_ALL_VIEWS')
      if (this.$route.path !== '/dashboard') {
        this.$router.push('/dashboard')
      }
    },
    toLastView() {
      const views = this.$store.state.visitedViews
      const latest = views[views.length - 1]
      if (latest) {
        this.$router.push(latest.path)
      } else {
        this.$router.push('/dashboard')
      }
    },
    openMenu(tag, e) {
      this.selectedTag = tag
      this.left = e.clientX
      this.top = e.clientY
      this.visible = true
    },
    closeMenu() {
      this.visible = false
    }
  }
}
</script>

<style scoped>
.tags-view {
  height: 34px;
  background: #fff;
  border-bottom: 1px solid #d8dce5;
  box-shadow: 0 1px 3px 0 rgba(0,0,0,.12), 0 0 3px 0 rgba(0,0,0,.04);
  display: flex;
  align-items: center;
  padding: 0 10px;
  position: relative;
  overflow-x: auto;
  white-space: nowrap;
}
.tags-view::-webkit-scrollbar {
  height: 0;
}
.tags-view-item {
  display: inline-flex;
  align-items: center;
  height: 26px;
  line-height: 26px;
  border: 1px solid #d8dce5;
  color: #495060;
  background: #fff;
  padding: 0 8px;
  font-size: 12px;
  text-decoration: none;
  margin-right: 5px;
  border-radius: 2px;
}
.tags-view-item.active {
  background-color: #409EFF;
  color: #fff;
  border-color: #409EFF;
}
.tags-view-item.active .tags-close {
  color: #fff;
}
.tags-close {
  margin-left: 5px;
  border-radius: 50%;
  width: 14px;
  height: 14px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 10px;
}
.tags-close:hover {
  background: rgba(0,0,0,.15);
}
.contextmenu {
  position: fixed;
  margin: 0;
  padding: 5px 0;
  background: #fff;
  z-index: 3000;
  list-style: none;
  border-radius: 4px;
  box-shadow: 2px 2px 3px 0 rgba(0,0,0,.3);
}
.contextmenu li {
  margin: 0;
  padding: 7px 16px;
  cursor: pointer;
  font-size: 12px;
}
.contextmenu li:hover {
  background: #eee;
}
</style>
