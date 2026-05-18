import Vue from 'vue'
import Vuex from 'vuex'
import { getToken, setToken, removeToken } from '@/utils/auth'
import request from '@/api'

Vue.use(Vuex)

export default new Vuex.Store({
  state: {
    token: getToken(),
    admin: null,
    roles: [],
    menus: []
  },
  mutations: {
    SET_TOKEN(state, token) {
      state.token = token
      setToken(token)
    },
    SET_ADMIN(state, admin) {
      state.admin = admin
    },
    SET_ROLES(state, roles) {
      state.roles = roles
    },
    SET_MENUS(state, menus) {
      state.menus = menus
    },
    CLEAR(state) {
      state.token = ''
      state.admin = null
      state.roles = []
      state.menus = []
      removeToken()
    }
  },
  actions: {
    // 登录
    async login({ commit, dispatch }, { username, password }) {
      const res = await request.post('/auth/login', { username, password })
      if (res.code === 0) {
        commit('SET_TOKEN', res.data.token)
        await dispatch('getInfo')
      }
      return res
    },

    // 获取管理员信息+菜单权限
    async getInfo({ commit }) {
      const res = await request.get('/auth/info')
      if (res.code === 0) {
        commit('SET_ADMIN', res.data.admin)
        commit('SET_ROLES', res.data.roles)
        commit('SET_MENUS', res.data.menus)
      }
      return res
    },

    // 登出
    async logout({ commit }) {
      await request.post('/auth/logout')
      commit('CLEAR')
    }
  }
})
